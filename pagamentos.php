
<?php
require_once __DIR__ . '/auth.php';
exigirLogin();
require_once __DIR__ . '/config/conexao.php';

function e($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function dinheiro($valor) {
    return number_format((float) $valor, 2, ',', '.');
}

$erro = '';
$sucesso = '';

$formasPagamento = [
    'Dinheiro',
    'Pix',
    'Cartão',
    'Transferência',
    'Outro'
];

$statusValidos = ['Pago', 'Pendente', 'Cancelado'];

// Registrar pagamento.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'cadastrar') {
        $idAtendimento = filter_input(
            INPUT_POST,
            'id_atendimento',
            FILTER_VALIDATE_INT
        );

        $valorTexto = trim($_POST['valor_pago'] ?? '');
        $forma = $_POST['forma_pagamento'] ?? '';
        $status = $_POST['status'] ?? 'Pago';
        $dataTexto = trim($_POST['data_pagamento'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');

        $valorValido = preg_match(
            '/^\d{1,8}(\.\d{1,2})?$/',
            $valorTexto
        );

        if (!$idAtendimento) {
            $erro = 'Selecione um atendimento válido.';
        } elseif (!$valorValido || (float) $valorTexto <= 0) {
            $erro = 'Informe um valor positivo, com até duas casas decimais.';
        } elseif (!in_array($forma, $formasPagamento, true)) {
            $erro = 'Selecione uma forma de pagamento válida.';
        } elseif (!in_array($status, $statusValidos, true)) {
            $erro = 'Selecione um status válido.';
        } elseif (strlen($observacoes) > 50000) {
            $erro = 'As observações são muito longas.';
        } else {
            try {
                $dataObj = DateTimeImmutable::createFromFormat(
                    '!Y-m-d\TH:i',
                    $dataTexto
                );

                if (
                    !$dataObj ||
                    $dataObj->format('Y-m-d\TH:i') !== $dataTexto
                ) {
                    throw new DomainException(
                        'Informe uma data e hora válidas.'
                    );
                }

                $valor = round((float) $valorTexto, 2);
                $dataBanco = $dataObj->format('Y-m-d H:i:s');

                $pdo->beginTransaction();

                // Bloqueia o atendimento durante a validação.
                $stmt = $pdo->prepare(
                    'SELECT id_atendimento, valor_total
                     FROM atendimentos
                     WHERE id_atendimento = ?
                     FOR UPDATE'
                );
                $stmt->execute([$idAtendimento]);
                $atendimento = $stmt->fetch();

                if (!$atendimento) {
                    throw new DomainException(
                        'O atendimento selecionado não foi encontrado.'
                    );
                }

                // Soma apenas os pagamentos efetivamente pagos.
                $stmt = $pdo->prepare(
                    "SELECT COALESCE(SUM(valor_pago), 0)
                     FROM pagamentos
                     WHERE id_atendimento = ?
                       AND status = 'Pago'"
                );
                $stmt->execute([$idAtendimento]);
                $totalPago = (float) $stmt->fetchColumn();

                $valorAtendimento = (float) $atendimento['valor_total'];
                $saldo = round($valorAtendimento - $totalPago, 2);

                if ($saldo <= 0) {
                    throw new DomainException(
                        'Este atendimento já está totalmente pago.'
                    );
                }

                if ($valor > $saldo) {
                    throw new DomainException(
                        'O valor informado excede o saldo de R$ ' .
                        dinheiro($saldo) . '.'
                    );
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO pagamentos
                    (
                        id_atendimento,
                        valor_pago,
                        forma_pagamento,
                        data_pagamento,
                        status,
                        observacoes
                    )
                    VALUES (?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $idAtendimento,
                    number_format($valor, 2, '.', ''),
                    $forma,
                    $dataBanco,
                    $status,
                    $observacoes !== '' ? $observacoes : null
                ]);

                $idPagamento = (int) $pdo->lastInsertId();

                $pdo->commit();

                header(
                    'Location: pagamentos.php?sucesso=1&id=' .
                    $idPagamento
                );
                exit;

            } catch (DomainException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $erro = $ex->getMessage();

            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log($ex->getMessage());
                $erro = 'Não foi possível registrar o pagamento. Confira os dados e tente novamente.';
            }
        }
    }
}

if (isset($_GET['sucesso'])) {
    $sucesso = 'Pagamento #' .
        (int) ($_GET['id'] ?? 0) .
        ' registrado com sucesso!';
}

// Atendimentos e respectivos saldos.
$atendimentos = $pdo->query(
    "SELECT
        a.id_atendimento,
        a.data_atendimento,
        a.valor_total,
        c.nome AS cliente,
        v.marca,
        v.modelo,
        COALESCE(
            SUM(
                CASE WHEN p.status = 'Pago'
                     THEN p.valor_pago ELSE 0 END
            ), 0
        ) AS total_pago
     FROM atendimentos a
     INNER JOIN clientes c ON c.id_cliente = a.id_cliente
     INNER JOIN veiculos v ON v.id_veiculo = a.id_veiculo
     LEFT JOIN pagamentos p ON p.id_atendimento = a.id_atendimento
     GROUP BY
        a.id_atendimento, a.data_atendimento, a.valor_total,
        c.nome, v.marca, v.modelo
     ORDER BY a.id_atendimento DESC"
)->fetchAll();

// Histórico de pagamentos.
$pagamentos = $pdo->query(
    "SELECT
        p.id_pagamento,
        p.id_atendimento,
        p.valor_pago,
        p.forma_pagamento,
        p.data_pagamento,
        p.status,
        p.observacoes,
        c.nome AS cliente,
        v.marca,
        v.modelo
     FROM pagamentos p
     INNER JOIN atendimentos a ON a.id_atendimento = p.id_atendimento
     INNER JOIN clientes c ON c.id_cliente = a.id_cliente
     INNER JOIN veiculos v ON v.id_veiculo = a.id_veiculo
     ORDER BY p.id_pagamento DESC"
)->fetchAll();

// Indicadores financeiros.
$resumo = $pdo->query(
    "SELECT
        COALESCE(SUM(CASE WHEN status = 'Pago'
                          THEN valor_pago ELSE 0 END), 0) AS recebido,
        COALESCE(SUM(CASE WHEN status = 'Pendente'
                          THEN valor_pago ELSE 0 END), 0) AS pendente
     FROM pagamentos"
)->fetch();

$totalAReceber = 0.0;

foreach ($atendimentos as $a) {
    $saldo = (float) $a['valor_total'] - (float) $a['total_pago'];

    if ($saldo > 0) {
        $totalAReceber += $saldo;
    }
}

$totalPagamentos = count($pagamentos);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="img/logogarage.png">
    <title>Pagamentos | Garage V8</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --red: #d90429;
            --red-light: #ef233c;
            --bg: #0b0b0e;
            --panel: #141419;
            --border: #2b2b34;
            --text: #f5f5f7;
            --muted: #92929e;
            --green: #50d890;
            --yellow: #f2bd50;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        input, select, textarea, button {
            font: inherit;
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 20;
            display: flex;
            flex-direction: column;
            width: 250px;
            padding: 26px 17px 18px;
            background: #101014;
            border-right: 1px solid var(--border);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 0 8px 25px;
            border-bottom: 1px solid var(--border);
        }

        .brand-logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .brand-name {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 1px;
            line-height: 1.2;
        }

        .brand-name strong {
            color: var(--red-light);
        }

        .brand-subtitle {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            font-size: 9px;
            letter-spacing: 1.3px;
        }

        .menu-label {
            margin: 25px 11px 10px;
            color: #696974;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
        }

        .menu {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 13px;
            color: #b4b4bf;
            border: 1px solid transparent;
            border-radius: 9px;
            transition: background .2s, color .2s;
        }

        .menu a i {
            width: 19px;
            text-align: center;
            font-size: 15px;
        }

        .menu a:hover {
            color: white;
            background: #1a1a21;
        }

        .menu a.active {
            color: white;
            background: rgba(217, 4, 41, .14);
            border-color: rgba(239, 35, 60, .25);
        }

        .menu a.active i {
            color: var(--red-light);
        }

        .logout-link {
            display: flex;
            align-items: center;
            gap: 11px;
            margin-top: auto;
            padding: 13px;
            color: #ff6878;
            border-top: 1px solid var(--border);
        }

        .main {
            min-width: 0;
            margin-left: 250px;
            padding: 32px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 27px;
        }

        .eyebrow {
            margin-bottom: 5px;
            color: var(--red-light);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.7px;
            text-transform: uppercase;
        }

        h1 {
            font-size: clamp(24px, 3vw, 31px);
            font-weight: 700;
            letter-spacing: -.7px;
        }

        .muted {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .header-date {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 11px 14px;
            color: #c6c6d0;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 9px;
            white-space: nowrap;
        }

        .header-date i {
            color: var(--red-light);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 23px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            padding: 20px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 13px;
        }

        .stat-card::after {
            position: absolute;
            top: -25px;
            right: -20px;
            width: 85px;
            height: 85px;
            background: rgba(217, 4, 41, .07);
            border-radius: 50%;
            content: "";
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 15px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
        }

        .stat-icon {
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            color: var(--red-light);
            background: rgba(217, 4, 41, .12);
            border-radius: 9px;
        }

        .stat-value {
            font-size: clamp(21px, 2vw, 26px);
            font-weight: 700;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .stat-note {
            margin-top: 5px;
            color: var(--muted);
            font-size: 11px;
        }

        .panel {
            margin-bottom: 22px;
            padding: 24px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 13px;
        }

        .panel-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 23px;
        }

        .panel-title {
            display: flex;
            align-items: center;
            gap: 11px;
            font-size: 17px;
            font-weight: 600;
        }

        .panel-title i {
            color: var(--red-light);
        }

        .panel-description {
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 19px;
        }

        .field {
            display: flex;
            min-width: 0;
            flex-direction: column;
            gap: 8px;
            color: #d9d9e1;
            font-size: 12px;
            font-weight: 500;
        }

        .required {
            color: var(--red-light);
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            min-width: 0;
            color: var(--text);
            background: #0f0f14;
            border: 1px solid #34343e;
            border-radius: 8px;
            outline: none;
            padding: 12px 13px;
            transition: border-color .2s, box-shadow .2s;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            border-color: var(--red-light);
            box-shadow: 0 0 0 3px rgba(239, 35, 60, .1);
        }

        .field select option {
            color: var(--text);
            background: #101014;
        }

        .field textarea {
            min-height: 100px;
            resize: vertical;
        }

        .full {
            grid-column: 1 / -1;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 11px 16px;
            color: white;
            background: var(--red);
            border: 1px solid var(--red);
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: background .2s, transform .2s;
        }

        .btn:hover {
            background: var(--red-light);
            transform: translateY(-1px);
        }

        .message {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
            padding: 14px 16px;
            border: 1px solid transparent;
            border-radius: 9px;
            font-size: 12px;
        }

        .message i {
            margin-top: 2px;
        }

        .message.ok {
            color: #a4edbd;
            background: rgba(34, 139, 76, .13);
            border-color: rgba(80, 216, 144, .25);
        }

        .message.error {
            color: #ffb0b9;
            background: rgba(217, 4, 41, .12);
            border-color: rgba(239, 35, 60, .28);
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
            text-align: left;
        }

        thead {
            background: #1b1b22;
        }

        th {
            padding: 14px 15px;
            color: #b7b7c3;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .6px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            padding: 15px;
            color: #e3e3e9;
            border-top: 1px solid #292932;
            font-size: 12px;
            vertical-align: middle;
        }

        tbody tr {
            transition: background .2s;
        }

        tbody tr:hover {
            background: #19191f;
        }

        .id-badge {
            display: inline-block;
            padding: 5px 8px;
            color: #ff7888;
            background: rgba(217, 4, 41, .12);
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-pago {
            color: #81e6ab;
            background: rgba(80, 216, 144, .11);
        }

        .status-pendente {
            color: #f2ce7a;
            background: rgba(242, 189, 80, .11);
        }

        .status-cancelado {
            color: #ff9ba6;
            background: rgba(239, 35, 60, .11);
        }

        .empty-state {
            padding: 35px 15px;
            color: var(--muted);
            text-align: center;
        }

        .empty-state i {
            display: block;
            margin-bottom: 12px;
            color: #5e5e6a;
            font-size: 28px;
        }

        .table-footer {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
            color: var(--muted);
            font-size: 11px;
        }

        @media (max-width: 1100px) {
            .main {
                padding: 25px;
            }

            .stats {
                gap: 11px;
            }

            .stat-card {
                padding: 16px;
            }
        }

        @media (max-width: 850px) {
            .sidebar {
                position: relative;
                width: 100%;
                padding: 15px;
                border-right: 0;
                border-bottom: 1px solid var(--border);
            }

            .brand {
                padding: 0 5px 15px;
            }

            .menu-label {
                margin-top: 15px;
            }

            .menu {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .menu a {
                padding: 10px;
                font-size: 12px;
            }

            .logout-link {
                margin-top: 12px;
                padding-bottom: 0;
                border-top: 1px solid var(--border);
            }

            .main {
                margin-left: 0;
                padding: 22px 16px;
            }
        }

        @media (max-width: 600px) {
            .header {
                align-items: flex-start;
                flex-direction: column;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 17px;
            }

            .panel {
                padding: 17px 14px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .full {
                grid-column: auto;
            }

            .form-grid .full {
                grid-column: 1 / -1;
            }

            .form-grid .field.full {
                grid-column: 1 / -1;
            }

            .form-grid > .full .btn {
                width: 100%;
            }

            .menu {
                grid-template-columns: 1fr 1fr;
            }

            .menu a {
                gap: 8px;
                font-size: 11px;
            }
        }
    </style>
</head>

<body>
<div class="layout">

    <aside class="sidebar">
        <a href="index.php" class="brand">
            <img src="img/logogarage.png" alt="Logo Garage V8" class="brand-logo">
            <div>
                <div class="brand-name">GARAGE <strong>V8</strong></div>
                <span class="brand-subtitle">GESTÃO AUTOMOTIVA</span>
            </div>
        </a>

        <div class="menu-label">MENU PRINCIPAL</div>

        <nav class="menu">
            <a href="index.php">
                <i class="fa-solid fa-house"></i>
                <span>Painel inicial</span>
            </a>

            <a href="clientes.php">
                <i class="fa-solid fa-users"></i>
                <span>Clientes</span>
            </a>

            <a href="veiculos.php">
                <i class="fa-solid fa-car-side"></i>
                <span>Veículos</span>
            </a>

            <a href="servicos.php">
                <i class="fa-solid fa-screwdriver-wrench"></i>
                <span>Serviços</span>
            </a>

            <a href="atendimentos.php">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>Atendimentos</span>
            </a>

            <a href="pagamentos.php" class="active">
                <i class="fa-solid fa-credit-card"></i>
                <span>Pagamentos</span>
            </a>

            <a href="despesas.php">
                <i class="fa-solid fa-arrow-trend-down"></i>
                <span>Despesas</span>
            </a>

            <a href="financeiro.php">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Financeiro</span>
            </a>
        </nav>

        <a href="logout.php" class="logout-link">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Sair do sistema</span>
        </a>
    </aside>

    <main class="main">
        <header class="header">
            <div>
                <div class="eyebrow">Controle financeiro</div>
                <h1>Pagamentos</h1>
                <p class="muted">
                    Acompanhe recebimentos, pendências e saldos dos atendimentos.
                </p>
            </div>

            <div class="header-date">
                <i class="fa-regular fa-calendar"></i>
                <?= e(date('d/m/Y')) ?>
            </div>
        </header>

        <?php if ($sucesso): ?>
            <div class="message ok">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= e($sucesso) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="message error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($erro) ?></span>
            </div>
        <?php endif; ?>

        <section class="stats">
            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Total recebido</span>
                    <span class="stat-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </span>
                </div>
                <div class="stat-value">
                    R$ <?= dinheiro($resumo['recebido']) ?>
                </div>
                <div class="stat-note">Pagamentos com status Pago</div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Pagamentos pendentes</span>
                    <span class="stat-icon">
                        <i class="fa-regular fa-clock"></i>
                    </span>
                </div>
                <div class="stat-value">
                    R$ <?= dinheiro($resumo['pendente']) ?>
                </div>
                <div class="stat-note">Registros marcados como pendentes</div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Saldo dos atendimentos</span>
                    <span class="stat-icon">
                        <i class="fa-solid fa-wallet"></i>
                    </span>
                </div>
                <div class="stat-value">
                    R$ <?= dinheiro($totalAReceber) ?>
                </div>
                <div class="stat-note">Valores ainda não cobertos por pagamentos pagos</div>
            </article>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2 class="panel-title">
                        <i class="fa-solid fa-plus-circle"></i>
                        Registrar pagamento
                    </h2>
                    <p class="panel-description">
                        Registre um recebimento ou acompanhe um pagamento pendente.
                    </p>
                </div>
            </div>

            <?php if (!$atendimentos): ?>
                <div class="message error">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>
                        Cadastre um atendimento antes de registrar pagamentos.
                    </span>
                </div>
            <?php else: ?>
                <?php
                $atendimentosComSaldo = array_filter(
                    $atendimentos,
                    fn($a) => (
                        (float) $a['valor_total'] -
                        (float) $a['total_pago']
                    ) > 0
                );
                ?>

                <?php if (!$atendimentosComSaldo): ?>
                    <div class="message ok">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>
                            Todos os atendimentos cadastrados estão totalmente pagos.
                        </span>
                    </div>
                <?php else: ?>
                    <form method="post" id="form-pagamento">
                        <input type="hidden" name="acao" value="cadastrar">

                        <div class="form-grid">
                            <label class="field full">
                                Atendimento <span class="required">*</span>
                                <select name="id_atendimento" id="id_atendimento" required>
                                    <option value="">Selecione um atendimento</option>

                                    <?php foreach ($atendimentosComSaldo as $a): ?>
                                        <?php
                                        $saldo = max(
                                            0,
                                            (float) $a['valor_total'] -
                                            (float) $a['total_pago']
                                        );
                                        ?>
                                        <option
                                            value="<?= (int) $a['id_atendimento'] ?>"
                                            data-saldo="<?= e(number_format($saldo, 2, '.', '')) ?>"
                                        >
                                            #<?= (int) $a['id_atendimento'] ?>
                                            — <?= e($a['cliente']) ?>
                                            — <?= e($a['marca'] . ' ' . $a['modelo']) ?>
                                            — Saldo: R$ <?= dinheiro($saldo) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="field">
                                Valor do pagamento (R$) <span class="required">*</span>
                                <input
                                    type="number"
                                    name="valor_pago"
                                    id="valor_pago"
                                    min="0.01"
                                    max="99999999.99"
                                    step="0.01"
                                    placeholder="Ex.: 100.00"
                                    required
                                >
                                <span class="muted" id="saldo-ajuda">
                                    Selecione um atendimento para consultar o saldo.
                                </span>
                            </label>

                            <label class="field">
                                Forma de pagamento <span class="required">*</span>
                                <select name="forma_pagamento" required>
                                    <option value="">Selecione a forma</option>
                                    <?php foreach ($formasPagamento as $forma): ?>
                                        <option value="<?= e($forma) ?>">
                                            <?= e($forma) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="field">
                                Status <span class="required">*</span>
                                <select name="status" required>
                                    <option value="Pago">Pago — recebido</option>
                                    <option value="Pendente">Pendente — a receber</option>
                                    <option value="Cancelado">Cancelado</option>
                                </select>
                            </label>

                            <label class="field">
                                Data e hora <span class="required">*</span>
                                <input
                                    type="datetime-local"
                                    name="data_pagamento"
                                    value="<?= e(date('Y-m-d\TH:i')) ?>"
                                    required
                                >
                            </label>

                            <label class="field full">
                                Observações
                                <textarea
                                    name="observacoes"
                                    placeholder="Ex.: primeira parcela, comprovante ou detalhes do pagamento."
                                ></textarea>
                            </label>

                            <div class="full">
                                <button type="submit" class="btn">
                                    <i class="fa-solid fa-floppy-disk"></i>
                                    Registrar pagamento
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2 class="panel-title">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        Histórico de pagamentos
                    </h2>
                    <p class="panel-description">
                        Consulte os pagamentos registrados e seus respectivos atendimentos.
                    </p>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Pagamento</th>
                        <th>Atendimento</th>
                        <th>Cliente</th>
                        <th>Veículo</th>
                        <th>Data</th>
                        <th>Forma</th>
                        <th>Valor</th>
                        <th>Status</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if (!$pagamentos): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fa-solid fa-receipt"></i>
                                    Nenhum pagamento registrado ainda.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pagamentos as $p): ?>
                            <?php
                            $statusNormalizado = strtolower(trim($p['status']));

                            if ($statusNormalizado === 'pago') {
                                $statusClasse = 'status-pago';
                                $statusIcone = 'fa-circle-check';
                            } elseif ($statusNormalizado === 'pendente') {
                                $statusClasse = 'status-pendente';
                                $statusIcone = 'fa-clock';
                            } else {
                                $statusClasse = 'status-cancelado';
                                $statusIcone = 'fa-circle-xmark';
                            }
                            ?>
                            <tr>
                                <td>
                                    <span class="id-badge">
                                        #<?= (int) $p['id_pagamento'] ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="id-badge">
                                        #<?= (int) $p['id_atendimento'] ?>
                                    </span>
                                </td>

                                <td><?= e($p['cliente']) ?></td>

                                <td><?= e($p['marca'] . ' ' . $p['modelo']) ?></td>

                                <td>
                                    <?= e(date(
                                        'd/m/Y H:i',
                                        strtotime($p['data_pagamento'])
                                    )) ?>
                                </td>

                                <td><?= e($p['forma_pagamento']) ?></td>

                                <td>
                                    <strong>
                                        R$ <?= dinheiro($p['valor_pago']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <span class="status <?= e($statusClasse) ?>">
                                        <i class="fa-solid <?= e($statusIcone) ?>"></i>
                                        <?= e($p['status']) ?>
                                    </span>
                                </td>
                            </tr>

                            <?php if (!empty($p['observacoes'])): ?>
                                <tr>
                                    <td colspan="8" style="padding-top:0; color:var(--muted)">
                                        <i class="fa-regular fa-note-sticky"></i>
                                        <?= e($p['observacoes']) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span>
                    Exibindo <?= $totalPagamentos ?>
                    pagamento(s) registrado(s).
                </span>
                <span>Garage V8 · Gestão Automotiva</span>
            </div>
        </section>
    </main>
</div>

<script>
const atendimentoSelect = document.getElementById('id_atendimento');
const valorInput = document.getElementById('valor_pago');
const saldoAjuda = document.getElementById('saldo-ajuda');

function atualizarSaldo() {
    if (!atendimentoSelect || !valorInput || !saldoAjuda) return;

    const opcao = atendimentoSelect.selectedOptions[0];

    if (!opcao || !opcao.dataset.saldo) {
        valorInput.removeAttribute('max');
        saldoAjuda.textContent = 'Selecione um atendimento para consultar o saldo.';
        return;
    }

    const saldo = Number(opcao.dataset.saldo);

    valorInput.max = saldo.toFixed(2);

    saldoAjuda.textContent = 'Saldo disponível: R$ ' +
        saldo.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
}

atendimentoSelect?.addEventListener('change', atualizarSaldo);
atualizarSaldo();

document.getElementById('form-pagamento')?.addEventListener('submit', event => {
    if (!atendimentoSelect.value) {
        event.preventDefault();
        alert('Selecione um atendimento.');
        return;
    }

    const opcao = atendimentoSelect.selectedOptions[0];
    const saldo = Number(opcao.dataset.saldo);
    const valor = Number(valorInput.value);

    if (valor <= 0 || valor > saldo) {
        event.preventDefault();
        alert('Informe um valor positivo que não ultrapasse o saldo do atendimento.');
    }
});
</script>
</body>
</html>
