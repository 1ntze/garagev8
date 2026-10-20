<?php
require_once __DIR__ . '/auth.php';
exigirLogin();
require_once __DIR__ . '/config/conexao.php';

function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function dinheiro($valor) {
    return number_format((float)$valor, 2, ',', '.');
}

$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fim = $_GET['fim'] ?? date('Y-m-t');

function dataValida($data) {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    return $d && $d->format('Y-m-d') === $data;
}

if (!dataValida($inicio) || !dataValida($fim) || $inicio > $fim) {
    $inicio = date('Y-m-01');
    $fim = date('Y-m-t');
}

$sqlReceitas = "
    SELECT COALESCE(SUM(valor_pago), 0)
    FROM pagamentos
    WHERE status = 'Pago'
      AND data_pagamento >= ?
      AND data_pagamento < DATE_ADD(?, INTERVAL 1 DAY)
";

$stmt = $pdo->prepare($sqlReceitas);
$stmt->execute([$inicio, $fim]);
$receitas = (float)$stmt->fetchColumn();

$sqlDespesas = "
    SELECT COALESCE(SUM(valor), 0)
    FROM despesas
    WHERE status = 'Paga'
      AND data_pagamento >= ?
      AND data_pagamento < DATE_ADD(?, INTERVAL 1 DAY)
";

$stmt = $pdo->prepare($sqlDespesas);
$stmt->execute([$inicio, $fim]);
$despesasPagas = (float)$stmt->fetchColumn();

$saldo = $receitas - $despesasPagas;

$sqlMovimentacoes = "
    SELECT
        data_movimento,
        descricao,
        tipo,
        valor,
        forma,
        origem
    FROM (
        SELECT
            DATE(data_pagamento) AS data_movimento,
            CONCAT('Atendimento #', id_atendimento) AS descricao,
            'Receita' AS tipo,
            valor_pago AS valor,
            forma_pagamento AS forma,
            'Pagamento' AS origem
        FROM pagamentos
        WHERE status = 'Pago'
          AND data_pagamento >= ?
          AND data_pagamento < DATE_ADD(?, INTERVAL 1 DAY)

        UNION ALL

        SELECT
            data_pagamento AS data_movimento,
            descricao,
            'Despesa' AS tipo,
            valor,
            forma_pagamento AS forma,
            'Despesa' AS origem
        FROM despesas
        WHERE status = 'Paga'
          AND data_pagamento >= ?
          AND data_pagamento < DATE_ADD(?, INTERVAL 1 DAY)
    ) AS movimentos
    ORDER BY data_movimento DESC
";

$stmt = $pdo->prepare($sqlMovimentacoes);
$stmt->execute([$inicio, $fim, $inicio, $fim]);
$movimentacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Financeiro | Garage V8</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .filtros {
            display: flex;
            align-items: end;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }
        .filtros label {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }
        .filtros input {
            background: #101116;
            color: white;
            border: 1px solid #383945;
            padding: 11px;
            border-radius: 7px;
        }
        .indicadores {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }
        .indicador {
            background: #191a22;
            border: 1px solid #30313a;
            border-radius: 10px;
            padding: 20px;
        }
        .indicador strong {
            display: block;
            font-size: 25px;
            margin-top: 10px;
        }
        .positivo { color: #70dda0; }
        .negativo { color: #ff6471; }
        .tabela-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 650px; }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #30313a;
        }
        th { color: #ff4655; }
        .receita { color: #70dda0; }
        .despesa { color: #ff6471; }
        @media (max-width: 750px) {
            .indicadores { grid-template-columns: 1fr; }
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
            <a href="pagamentos.php">▣ Pagamentos</a>
            <a href="despesas.php">↗ Despesas</a>
            <a href="financeiro.php" class="active">▥ Financeiro</a>
        </nav>
        <a href="logout.php" class="logout-link">↪ Sair do sistema</a>
    </aside>

    <main class="main">
        <header class="header">
            <div>
                <h1>Controle financeiro</h1>
                <p>Entradas, saídas e saldo operacional.</p>
            </div>
        </header>

        <section class="card">
            <h2>Filtrar período</h2>
            <form method="get" class="filtros">
                <label>
                    Data inicial
                    <input type="date" name="inicio"
                           value="<?= e($inicio) ?>" required>
                </label>
                <label>
                    Data final
                    <input type="date" name="fim"
                           value="<?= e($fim) ?>" required>
                </label>
                <button class="btn" type="submit">Consultar</button>
            </form>
        </section>

        <section class="indicadores" style="margin-top:22px;">
            <div class="indicador">
                <span>Receitas recebidas</span>
                <strong class="positivo">
                    R$ <?= dinheiro($receitas) ?>
                </strong>
            </div>
            <div class="indicador">
                <span>Despesas pagas</span>
                <strong class="negativo">
                    R$ <?= dinheiro($despesasPagas) ?>
                </strong>
            </div>
            <div class="indicador">
                <span>Saldo operacional</span>
                <strong class="<?= $saldo >= 0 ? 'positivo' : 'negativo' ?>">
                    R$ <?= dinheiro($saldo) ?>
                </strong>
            </div>
        </section>

        <section class="card" style="margin-top:22px;">
            <h2>Movimentações financeiras</h2>
            <p>
                Período:
                <?= e(date('d/m/Y', strtotime($inicio))) ?>
                até
                <?= e(date('d/m/Y', strtotime($fim))) ?>
            </p>

            <div class="tabela-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Tipo</th>
                        <th>Forma</th>
                        <th>Valor</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$movimentacoes): ?>
                        <tr>
                            <td colspan="5">
                                Nenhuma movimentação no período.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movimentacoes as $m): ?>
                            <tr>
                                <td>
                                    <?= e(date(
                                        'd/m/Y',
                                        strtotime($m['data_movimento'])
                                    )) ?>
                                </td>
                                <td><?= e($m['descricao']) ?></td>
                                <td class="<?= $m['tipo'] === 'Receita'
                                    ? 'receita' : 'despesa' ?>">
                                    <?= e($m['tipo']) ?>
                                </td>
                                <td><?= e($m['forma'] ?? '—') ?></td>
                                <td>
                                    <?= $m['tipo'] === 'Receita' ? '+' : '-' ?>
                                    R$ <?= dinheiro($m['valor']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
