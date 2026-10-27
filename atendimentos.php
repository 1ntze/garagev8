
<?php
require_once __DIR__ . '/auth.php';
exigirLogin();

require_once __DIR__ . '/config/conexao.php';

function e($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'cadastrar') {
        $idCliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
        $idVeiculo = filter_input(INPUT_POST, 'id_veiculo', FILTER_VALIDATE_INT);
        $tipo = $_POST['tipo_atendimento'] ?? 'Orçamento';
        $dataAtendimento = trim($_POST['data_atendimento'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');
        $servicosPost = $_POST['servicos'] ?? [];
        $quantidadesPost = $_POST['quantidades'] ?? [];

        $tiposValidos = ['Orçamento', 'Agendamento', 'Realizado'];

        if (!$idCliente || !$idVeiculo) {
            $erro = 'Selecione o cliente e o veículo.';
        } elseif (!in_array($tipo, $tiposValidos, true)) {
            $erro = 'Selecione um tipo de atendimento válido.';
        } elseif (!is_array($servicosPost) || !is_array($quantidadesPost)) {
            $erro = 'Os serviços informados são inválidos.';
        } elseif (count($servicosPost) === 0 || count($servicosPost) > 50) {
            $erro = 'Adicione pelo menos um serviço (máximo de 50).';
        } elseif (strlen($observacoes) > 50000) {
            $erro = 'As observações são muito longas.';
        } else {
            try {
                // Confirma que o veículo pertence ao cliente escolhido.
                $stmt = $pdo->prepare(
                    'SELECT id_veiculo
                     FROM veiculos
                     WHERE id_veiculo = ? AND id_cliente = ?'
                );
                $stmt->execute([$idVeiculo, $idCliente]);

                if (!$stmt->fetch()) {
                    throw new DomainException(
                        'O veículo não pertence ao cliente selecionado.'
                    );
                }

                // Valida e carrega os serviços ativos.
                $itens = [];
                $vistos = [];
                $total = 0.0;

                foreach ($servicosPost as $indice => $idServicoPost) {
                    $idServico = filter_var($idServicoPost, FILTER_VALIDATE_INT);
                    $quantidadePost = $quantidadesPost[$indice] ?? null;
                    $quantidade = filter_var($quantidadePost, FILTER_VALIDATE_INT);

                    if (
                        !$idServico ||
                        !$quantidade ||
                        $quantidade < 1 ||
                        $quantidade > 1000 ||
                        isset($vistos[$idServico])
                    ) {
                        throw new DomainException(
                            'Confira os serviços e suas quantidades. Não repita serviços.'
                        );
                    }

                    $vistos[$idServico] = true;

                    $stmt = $pdo->prepare(
                        'SELECT id_servico, nome, valor_referencia
                         FROM servicos
                         WHERE id_servico = ? AND ativo = 1'
                    );
                    $stmt->execute([$idServico]);
                    $servico = $stmt->fetch();

                    if (!$servico) {
                        throw new DomainException(
                            'Um dos serviços não existe ou está inativo.'
                        );
                    }

                    $valorUnitario = (float) $servico['valor_referencia'];
                    $valorItem = round($valorUnitario * $quantidade, 2);
                    $total = round($total + $valorItem, 2);

                    $itens[] = [
                        'id_servico' => $idServico,
                        'quantidade' => $quantidade,
                        'valor_unitario' => $valorUnitario,
                        'valor_total' => $valorItem
                    ];
                }

                // Data recebida do formulário ou data atual.
                if ($dataAtendimento === '') {
                    $dataAtendimento = date('Y-m-d\TH:i');
                }

                $dataObj = DateTimeImmutable::createFromFormat(
                    '!Y-m-d\TH:i',
                    $dataAtendimento
                );

                if (
                    !$dataObj ||
                    $dataObj->format('Y-m-d\TH:i') !== $dataAtendimento
                ) {
                    throw new DomainException(
                        'Informe uma data e hora válidas.'
                    );
                }

                $dataBanco = $dataObj->format('Y-m-d H:i:s');

                // Salva o atendimento e seus itens em uma única transação.
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "INSERT INTO atendimentos
                    (
                        id_cliente,
                        id_veiculo,
                        data_atendimento,
                        tipo_atendimento,
                        status,
                        observacoes,
                        valor_total,
                        cadastrado_por
                    )
                    VALUES (?, ?, ?, ?, 'Pendente', ?, ?, ?)"
                );

                $stmt->execute([
                    $idCliente,
                    $idVeiculo,
                    $dataBanco,
                    $tipo,
                    $observacoes !== '' ? $observacoes : null,
                    number_format($total, 2, '.', ''),
                    1
                ]);

                $idAtendimento = (int) $pdo->lastInsertId();

                $stmtItem = $pdo->prepare(
                    'INSERT INTO itens_atendimento
                    (
                        id_atendimento,
                        id_servico,
                        quantidade,
                        valor_unitario,
                        valor_total,
                        custo_produto,
                        custo_material
                    )
                    VALUES (?, ?, ?, ?, ?, 0, 0)'
                );

                foreach ($itens as $item) {
                    $stmtItem->execute([
                        $idAtendimento,
                        $item['id_servico'],
                        $item['quantidade'],
                        number_format($item['valor_unitario'], 2, '.', ''),
                        number_format($item['valor_total'], 2, '.', '')
                    ]);
                }

                $pdo->commit();

                header(
                    'Location: atendimentos.php?sucesso=1&id=' . $idAtendimento
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
                $erro = 'Não foi possível salvar o atendimento. Confira os dados e tente novamente.';
            }
        }
    }
}

if (isset($_GET['sucesso'])) {
    $sucesso = 'Atendimento #' .
        (int) ($_GET['id'] ?? 0) .
        ' cadastrado com sucesso!';
}

// Carrega clientes, veículos e serviços ativos.
$clientes = $pdo->query(
    'SELECT id_cliente, nome FROM clientes ORDER BY nome'
)->fetchAll();

$veiculos = $pdo->query(
    'SELECT id_veiculo, id_cliente, marca, modelo, placa
     FROM veiculos
     ORDER BY marca, modelo'
)->fetchAll();

$servicos = $pdo->query(
    'SELECT id_servico, nome, valor_referencia
     FROM servicos
     WHERE ativo = 1
     ORDER BY nome'
)->fetchAll();

// Lista os atendimentos registrados.
$atendimentos = $pdo->query(
    "SELECT
        a.id_atendimento,
        a.data_atendimento,
        a.tipo_atendimento,
        a.status,
        a.valor_total,
        c.nome AS cliente,
        v.marca,
        v.modelo,
        v.placa
     FROM atendimentos a
     INNER JOIN clientes c ON c.id_cliente = a.id_cliente
     INNER JOIN veiculos v ON v.id_veiculo = a.id_veiculo
     ORDER BY a.id_atendimento DESC"
)->fetchAll();

$totalAtendimentos = count($atendimentos);
$totalPendentes = count(array_filter(
    $atendimentos,
    fn($a) => strtolower($a['status']) === 'pendente'
));
$totalFaturamento = array_sum(array_map(
    fn($a) => (float) $a['valor_total'],
    $atendimentos
));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atendimentos | Garage V8</title>
    <link rel="icon" type="image/png" href="img/logogarage.png">
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
            --panel-light: #19191f;
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

        button, input, select, textarea {
            font: inherit;
        }

        .layout {
            min-height: 100vh;
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
            color: #fff;
            background: #1a1a21;
        }

        .menu a.active {
            color: #fff;
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
            font-size: 25px;
            font-weight: 700;
            line-height: 1.3;
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

        .field input,
        .field select,
        .field textarea,
        .item-linha input,
        .item-linha select {
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
        .field textarea:focus,
        .item-linha input:focus,
        .item-linha select:focus {
            border-color: var(--red-light);
            box-shadow: 0 0 0 3px rgba(239, 35, 60, .1);
        }

        .field input:disabled,
        .field select:disabled {
            opacity: .55;
            cursor: not-allowed;
        }

        .field select option,
        .item-linha select option {
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

        .services-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 13px;
        }

        .services-heading strong {
            font-size: 13px;
        }

        .item-linha {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(100px, .7fr) auto;
            align-items: end;
            gap: 12px;
            margin-bottom: 12px;
            padding: 15px;
            background: #101015;
            border: 1px solid #292932;
            border-radius: 10px;
        }

        .item-linha .field {
            gap: 7px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 11px 16px;
            color: #fff;
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

        .btn-secondary {
            color: #e6e6ec;
            background: #202027;
            border-color: #383842;
        }

        .btn-secondary:hover {
            background: #2a2a33;
        }

        .btn-remover {
            min-height: 42px;
            padding: 10px 12px;
            color: #ff9ba6;
            background: rgba(217, 4, 41, .09);
            border: 1px solid rgba(217, 4, 41, .3);
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-remover:hover {
            background: rgba(217, 4, 41, .2);
        }

        .total-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 17px;
            padding: 17px 19px;
            background: rgba(217, 4, 41, .08);
            border: 1px solid rgba(217, 4, 41, .24);
            border-radius: 10px;
        }

        .total-label {
            color: #d0d0d9;
            font-size: 13px;
        }

        .total-value {
            color: #ff5266;
            font-size: 25px;
            font-weight: 700;
            white-space: nowrap;
        }

        .form-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 3px;
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
            min-width: 850px;
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

        .status-pendente {
            color: #f2ce7a;
            background: rgba(242, 189, 80, .11);
        }

        .status-concluido {
            color: #81e6ab;
            background: rgba(80, 216, 144, .11);
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

            .header-date {
                font-size: 11px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 17px;
            }

            .stat-value {
                font-size: 23px;
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

            .item-linha {
                grid-template-columns: minmax(0, 1fr) 95px;
                gap: 10px;
            }

            .item-linha .remover {
                grid-column: 1 / -1;
            }

            .item-linha .btn-remover {
                width: 100%;
            }

            .total-box {
                align-items: flex-start;
                flex-direction: column;
            }

            .total-value {
                font-size: 23px;
            }

            .form-actions {
                align-items: stretch;
                flex-direction: column;
            }

            .form-actions .btn {
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

            <a href="atendimentos.php" class="active">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>Atendimentos</span>
            </a>

            <a href="pagamentos.php">
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
                <div class="eyebrow">Controle operacional</div>
                <h1>Atendimentos</h1>
                <p class="muted">
                    Gerencie orçamentos, agendamentos e serviços realizados.
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
                    <span class="stat-label">Atendimentos registrados</span>
                    <span class="stat-icon">
                        <i class="fa-solid fa-clipboard-list"></i>
                    </span>
                </div>
                <div class="stat-value"><?= $totalAtendimentos ?></div>
                <div class="stat-note">Total de registros no sistema</div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Pendentes</span>
                    <span class="stat-icon">
                        <i class="fa-regular fa-clock"></i>
                    </span>
                </div>
                <div class="stat-value"><?= $totalPendentes ?></div>
                <div class="stat-note">Aguardando andamento</div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Valor dos atendimentos</span>
                    <span class="stat-icon">
                        <i class="fa-solid fa-wallet"></i>
                    </span>
                </div>
                <div class="stat-value" style="font-size:22px">
                    R$ <?= number_format($totalFaturamento, 2, ',', '.') ?>
                </div>
                <div class="stat-note">Soma dos valores registrados, não necessariamente recebidos</div>
            </article>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2 class="panel-title">
                        <i class="fa-solid fa-plus-circle"></i>
                        Novo atendimento
                    </h2>
                    <p class="panel-description">
                        Selecione o cliente, o veículo e os serviços desejados.
                    </p>
                </div>
            </div>

            <?php if (!$clientes || !$veiculos || !$servicos): ?>
                <div class="message error">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>
                        Para criar um atendimento, cadastre pelo menos um cliente,
                        um veículo e um serviço ativo.
                    </span>
                </div>
            <?php else: ?>
                <form method="post" id="form-atendimento">
                    <input type="hidden" name="acao" value="cadastrar">

                    <div class="form-grid">
                        <label class="field">
                            Cliente <span>*</span>
                            <select name="id_cliente" id="cliente" required>
                                <option value="">Selecione o cliente</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?= (int) $cliente['id_cliente'] ?>">
                                        <?= e($cliente['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="field">
                            Veículo <span>*</span>
                            <select name="id_veiculo" id="veiculo" required disabled>
                                <option value="">Selecione primeiro o cliente</option>
                            </select>
                        </label>

                        <label class="field">
                            Tipo de atendimento <span>*</span>
                            <select name="tipo_atendimento" required>
                                <option value="Orçamento">Orçamento</option>
                                <option value="Agendamento">Agendamento</option>
                                <option value="Realizado">Realizado</option>
                            </select>
                        </label>

                        <label class="field">
                            Data e hora <span>*</span>
                            <input
                                type="datetime-local"
                                name="data_atendimento"
                                value="<?= e(date('Y-m-d\TH:i')) ?>"
                                required
                            >
                        </label>

                        <div class="field full">
                            <div class="services-heading">
                                <strong>Serviços do atendimento <span>*</span></strong>
                                <button class="btn btn-secondary" type="button" id="adicionar">
                                    <i class="fa-solid fa-plus"></i>
                                    Adicionar serviço
                                </button>
                            </div>

                            <div id="itens"></div>

                            <div class="total-box">
                                <div class="total-label">
                                    <i class="fa-solid fa-calculator"></i>
                                    Total estimado do atendimento
                                </div>
                                <div class="total-value">
                                    R$ <span id="total">0,00</span>
                                </div>
                            </div>

                            <p class="muted" style="margin-top:10px">
                                O cálculo utiliza os valores de referência dos serviços selecionados.
                            </p>
                        </div>

                        <label class="field full">
                            Observações
                            <textarea
                                name="observacoes"
                                placeholder="Adicione detalhes importantes sobre o veículo ou o atendimento..."
                            ></textarea>
                        </label>

                        <div class="full form-actions">
                            <button class="btn" type="submit">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Salvar atendimento
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2 class="panel-title">
                        <i class="fa-solid fa-list-check"></i>
                        Atendimentos registrados
                    </h2>
                    <p class="panel-description">
                        Histórico de atendimentos cadastrados no sistema.
                    </p>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Número</th>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Veículo</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Total</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if (!$atendimentos): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fa-solid fa-clipboard-list"></i>
                                    Nenhum atendimento cadastrado ainda.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($atendimentos as $a): ?>
                            <?php
                            $statusClasse = 'status-pendente';
                            $statusNormalizado = strtolower(trim($a['status']));

                            if (in_array($statusNormalizado, ['concluído', 'concluido', 'realizado', 'pago'], true)) {
                                $statusClasse = 'status-concluido';
                            } elseif (in_array($statusNormalizado, ['cancelado', 'cancelada'], true)) {
                                $statusClasse = 'status-cancelado';
                            }
                            ?>
                            <tr>
                                <td>
                                    <span class="id-badge">
                                        #<?= (int) $a['id_atendimento'] ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e(date('d/m/Y H:i', strtotime($a['data_atendimento']))) ?>
                                </td>

                                <td><?= e($a['cliente']) ?></td>

                                <td>
                                    <strong><?= e($a['marca'] . ' ' . $a['modelo']) ?></strong>
                                    <?php if (!empty($a['placa'])): ?>
                                        <div class="muted"><?= e($a['placa']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <td><?= e($a['tipo_atendimento']) ?></td>

                                <td>
                                    <span class="status <?= e($statusClasse) ?>">
                                        <?= e($a['status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <strong>
                                        R$ <?= number_format((float) $a['valor_total'], 2, ',', '.') ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span>
                    Exibindo <?= $totalAtendimentos ?>
                    atendimento(s) registrado(s).
                </span>
                <span>Garage V8 · Gestão Automotiva</span>
            </div>
        </section>
    </main>
</div>

<script>
const clientesSelect = document.getElementById('cliente');
const veiculosSelect = document.getElementById('veiculo');
const itensContainer = document.getElementById('itens');
const totalEl = document.getElementById('total');
const botaoAdicionar = document.getElementById('adicionar');

const veiculos = <?= json_encode(
    array_map(fn($v) => [
        'id' => (int) $v['id_veiculo'],
        'cliente' => (int) $v['id_cliente'],
        'nome' => $v['marca'] . ' ' . $v['modelo'] .
            ($v['placa'] ? ' — ' . $v['placa'] : '')
    ], $veiculos),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?>;

const servicos = <?= json_encode(
    array_map(fn($s) => [
        'id' => (int) $s['id_servico'],
        'nome' => $s['nome'],
        'valor' => (float) $s['valor_referencia']
    ], $servicos),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?>;

function atualizarVeiculos() {
    if (!clientesSelect || !veiculosSelect) return;

    const idCliente = Number(clientesSelect.value);
    veiculosSelect.replaceChildren();

    const disponiveis = veiculos.filter(v => v.cliente === idCliente);

    const inicial = document.createElement('option');
    inicial.value = '';
    inicial.textContent = disponiveis.length
        ? 'Selecione o veículo'
        : 'Nenhum veículo para este cliente';

    veiculosSelect.appendChild(inicial);
    veiculosSelect.disabled = disponiveis.length === 0;

    disponiveis.forEach(v => {
        const option = document.createElement('option');
        option.value = v.id;
        option.textContent = v.nome;
        veiculosSelect.appendChild(option);
    });
}

function atualizarTotal() {
    let total = 0;

    itensContainer.querySelectorAll('.item-linha').forEach(linha => {
        const seletor = linha.querySelector('.servico');
        const quantidade = Number(linha.querySelector('.quantidade').value) || 0;
        const servico = servicos.find(s => s.id === Number(seletor.value));

        if (servico && quantidade > 0) {
            total += servico.valor * quantidade;
        }
    });

    totalEl.textContent = total.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function atualizarOpcoes() {
    const selecionados = [...itensContainer.querySelectorAll('.servico')]
        .map(el => el.value)
        .filter(Boolean);

    itensContainer.querySelectorAll('.servico').forEach(seletor => {
        const atual = seletor.value;

        [...seletor.options].forEach(opcao => {
            if (!opcao.value) return;

            opcao.disabled = selecionados.includes(opcao.value)
                && opcao.value !== atual;
        });
    });
}

function adicionarServico() {
    if (itensContainer.querySelectorAll('.item-linha').length >= 50) {
        alert('O limite é de 50 serviços por atendimento.');
        return;
    }

    const linha = document.createElement('div');
    linha.className = 'item-linha';

    const campoServico = document.createElement('label');
    campoServico.className = 'field';
    campoServico.append('Serviço');

    const seletor = document.createElement('select');
    seletor.className = 'servico';
    seletor.name = 'servicos[]';
    seletor.required = true;

    const inicial = document.createElement('option');
    inicial.value = '';
    inicial.textContent = 'Selecione um serviço';
    seletor.appendChild(inicial);

    servicos.forEach(s => {
        const option = document.createElement('option');
        option.value = s.id;
        option.textContent = s.nome + ' — R$ ' +
            s.valor.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

        seletor.appendChild(option);
    });

    campoServico.appendChild(seletor);

    const campoQuantidade = document.createElement('label');
    campoQuantidade.className = 'field';
    campoQuantidade.append('Quantidade');

    const quantidade = document.createElement('input');
    quantidade.type = 'number';
    quantidade.name = 'quantidades[]';
    quantidade.className = 'quantidade';
    quantidade.min = '1';
    quantidade.max = '1000';
    quantidade.step = '1';
    quantidade.value = '1';
    quantidade.required = true;
    campoQuantidade.appendChild(quantidade);

    const areaRemover = document.createElement('div');
    areaRemover.className = 'remover';

    const remover = document.createElement('button');
    remover.type = 'button';
    remover.className = 'btn-remover';
    remover.innerHTML = '<i class="fa-solid fa-trash"></i> Remover';

    remover.addEventListener('click', () => {
        linha.remove();
        atualizarOpcoes();
        atualizarTotal();
    });

    areaRemover.appendChild(remover);
    linha.append(campoServico, campoQuantidade, areaRemover);
    itensContainer.appendChild(linha);

    seletor.addEventListener('change', () => {
        atualizarOpcoes();
        atualizarTotal();
    });

    quantidade.addEventListener('input', atualizarTotal);

    atualizarOpcoes();
    atualizarTotal();
}

clientesSelect?.addEventListener('change', atualizarVeiculos);
botaoAdicionar?.addEventListener('click', adicionarServico);

if (clientesSelect) atualizarVeiculos();
if (itensContainer && servicos.length > 0) adicionarServico();

document.getElementById('form-atendimento')?.addEventListener('submit', event => {
    if (itensContainer.querySelectorAll('.item-linha').length === 0) {
        event.preventDefault();
        alert('Adicione pelo menos um serviço.');
    }
});
</script>
</body>
</html>
