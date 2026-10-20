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

// Registrar pagamento
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

        // Aceita valores como 100 ou 100.50.
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

                // Soma somente os valores efetivamente pagos.
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
                    (id_atendimento, valor_pago, forma_pagamento,
                     data_pagamento, status, observacoes)
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

// Carrega os atendimentos e seus saldos.
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

// Indicadores financeiros: somente pagamentos com status Pago.
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pagamentos | Garage V8</title>
    <link rel="stylesheet" href="assets/style.css">

    <style>
        input, select, textarea, button { font: inherit; }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .field input, .field select, .field textarea {
            width: 100%;
            background: #101116;
            color: #f5f5f5;
            border: 1px solid #383945;
            border-radius: 7px;
            padding: 12px;
        }

        .field select option { background: #101116; }
        .field textarea { min-height: 80px; resize: vertical; }
        .full { grid-column: 1 / -1; }

        .btn {
            background: #e51b2b;
            color: white;
            border: 0;
            padding: 11px 16px;
            border-radius: 7px;
            cursor: pointer;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .finance-card {
            background: #191a22;
            border: 1px solid #30313a;
            border-radius: 10px;
            padding: 18px;
        }

        .finance-card .valor {
            font-size: 23px;
            font-weight: bold;
            margin-top: 10px;
        }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #30313a;
            text-align: left;
        }

        th { color: #ff4655; }

        .message {
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 16px;
        }

        .ok { background: #163421; color: #9de5b1; }
        .error { background: #421a20; color: #ffadb5; }
        .muted { color: #9b9eaa; }

        .badge-status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 15px;
            font-size: 12px;
        }

        .pago { background: #163421; color: #9de5b1; }
        .pendente { background: #44391d; color: #f6d98b; }
        .cancelado { background: #3c3030; color: #ffb5b5; }

        @media (max-width: 700px) {
            .form-grid, .cards { grid-template-columns: 1fr; }
            .full { grid-column: auto; }
        }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="logo">GARAGE V8</div>
        <div class="subtitle">GESTÃO AUTOMOTIVA</div>

        <nav class="menu">
            <a href="index.php">⌂ Painel inicial</a>
            <a href="clientes.php">♙ Clientes</a>
            <a href="veiculos.php">🚘 Veículos</a>
            <a href="servicos.php">🔧 Serviços</a>
            <a href="atendimentos.php">▤ Atendimentos</a>
            <a href="pagamentos.php" class="active">▣ Pagamentos</a>
            <a href="despesas.php">↗ Despesas</a>
            <a href="financeiro.php">▥ Financeiro</a>
        </nav>
        <a href="logout.php" class="logout-link">↪ Sair do sistema</a>
    </aside>

    <main class="main">
        <header class="header">
            <div>
                <h1>Pagamentos</h1>
                <div class="muted">
                    Controle os recebimentos da oficina.
                </div>
            </div>
        </header>

        <?php if ($sucesso): ?>
            <div class="message ok"><?= e($sucesso) ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="message error"><?= e($erro) ?></div>
        <?php endif; ?>

        <div class="cards">
            <div class="finance-card">
                <div class="muted">Total recebido</div>
                <div class="valor">
                    R$ <?= dinheiro($resumo['recebido']) ?>
                </div>
            </div>

            <div class="finance-card">
                <div class="muted">Pagamentos pendentes</div>
                <div class="valor">
                    R$ <?= dinheiro($resumo['pendente']) ?>
                </div>
            </div>

            <div class="finance-card">
                <div class="muted">Saldo dos atendimentos</div>
                <div class="valor">
                    R$ <?= dinheiro($totalAReceber) ?>
                </div>
            </div>
        </div>

        <section class="card">
            <h2>Registrar pagamento</h2>

            <?php if (!$atendimentos): ?>
                <p class="muted">
                    Cadastre um atendimento antes de registrar pagamentos.
                </p>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="acao" value="cadastrar">

                    <div class="form-grid">
                        <label class="field full">
                            Atendimento *
                            <select name="id_atendimento" required>
                                <option value="">Selecione um atendimento</option>

                                <?php foreach ($atendimentos as $a): ?>
                                    <?php
                                    $saldo = max(
                                        0,
                                        (float) $a['valor_total'] -
                                        (float) $a['total_pago']
                                    );
                                    ?>
                                    <?php if ($saldo > 0): ?>
                                        <option value="<?= (int) $a['id_atendimento'] ?>">
                                            #<?= (int) $a['id_atendimento'] ?>
                                            — <?= e($a['cliente']) ?>
                                            — <?= e($a['marca'] . ' ' . $a['modelo']) ?>
                                            — Saldo: R$ <?= dinheiro($saldo) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="field">
                            Valor do pagamento (R$) *
                            <input type="number" name="valor_pago"
                                   min="0.01" max="99999999.99"
                                   step="0.01" placeholder="Ex.: 100.00"
                                   required>
                        </label>

                        <label class="field">
                            Forma de pagamento *
                            <select name="forma_pagamento" required>
                                <option value="">Selecione</option>
                                <?php foreach ($formasPagamento as $forma): ?>
                                    <option value="<?= e($forma) ?>">
                                        <?= e($forma) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="field">
                            Status *
                            <select name="status" required>
                                <option value="Pago">Pago — recebido</option>
                                <option value="Pendente">Pendente — a receber</option>
                                <option value="Cancelado">Cancelado</option>
                            </select>
                        </label>

                        <label class="field">
                            Data e hora *
                            <input type="datetime-local" name="data_pagamento"
                                   value="<?= date('Y-m-d\TH:i') ?>" required>
                        </label>

                        <label class="field full">
                            Observações
                            <textarea name="observacoes"
                                      placeholder="Ex.: primeira parcela, comprovante, detalhes"></textarea>
                        </label>

                        <div class="full">
                            <button type="submit" class="btn">
                                Registrar pagamento
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="card" style="margin-top:22px;">
            <h2>Histórico de pagamentos</h2>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
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
                            <td colspan="8">Nenhum pagamento registrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pagamentos as $p): ?>
                            <tr>
                                <td>#<?= (int) $p['id_pagamento'] ?></td>
                                <td>#<?= (int) $p['id_atendimento'] ?></td>
                                <td><?= e($p['cliente']) ?></td>
                                <td><?= e($p['marca'] . ' ' . $p['modelo']) ?></td>
                                <td><?= e(date('d/m/Y H:i', strtotime($p['data_pagamento']))) ?></td>
                                <td><?= e($p['forma_pagamento']) ?></td>
                                <td>R$ <?= dinheiro($p['valor_pago']) ?></td>
                                <td>
                                    <span class="badge-status <?= strtolower(
                                        iconv('UTF-8', 'ASCII//TRANSLIT', $p['status'])
                                    ) ?>">
                                        <?= e($p['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <p class="muted">
                Total exibido: <?= count($pagamentos) ?> pagamento(s).
            </p>
        </section>
    </main>
</div>
</body>
</html>
