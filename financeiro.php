
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

function dataValida($data) {
    if (!is_string($data) || $data === '') {
        return false;
    }

    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

    return $d && $d->format('Y-m-d') === $data;
}

$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fim = $_GET['fim'] ?? date('Y-m-t');

if (!dataValida($inicio) || !dataValida($fim) || $inicio > $fim) {
    $inicio = date('Y-m-01');
    $fim = date('Y-m-t');
}

/*
|--------------------------------------------------------------------------
| RECEITAS RECEBIDAS
|--------------------------------------------------------------------------
*/

$sqlReceitas = "
    SELECT COALESCE(SUM(valor_pago), 0)
    FROM pagamentos
    WHERE status = 'Pago'
      AND data_pagamento >= ?
      AND data_pagamento < DATE_ADD(?, INTERVAL 1 DAY)
";

$stmt = $pdo->prepare($sqlReceitas);
$stmt->execute([$inicio, $fim]);
$receitas = (float) $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| DESPESAS PAGAS
|--------------------------------------------------------------------------
*/

$sqlDespesas = "
    SELECT COALESCE(SUM(valor), 0)
    FROM despesas
    WHERE status = 'Paga'
      AND data_pagamento >= ?
      AND data_pagamento < DATE_ADD(?, INTERVAL 1 DAY)
";

$stmt = $pdo->prepare($sqlDespesas);
$stmt->execute([$inicio, $fim]);
$despesasPagas = (float) $stmt->fetchColumn();

$saldo = $receitas - $despesasPagas;

/*
|--------------------------------------------------------------------------
| MOVIMENTAÇÕES FINANCEIRAS
|--------------------------------------------------------------------------
*/

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
            DATE(data_pagamento) AS data_movimento,
            descricao,
            'Despesa' AS tipo,
            valor AS valor,
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

/*
|--------------------------------------------------------------------------
| PREPARAÇÃO DOS DADOS DOS GRÁFICOS
|--------------------------------------------------------------------------
| Os gráficos são calculados a partir das movimentações reais retornadas
| pelo banco de dados e respeitam o período selecionado.
|--------------------------------------------------------------------------
*/

$dadosPorDia = [];
$totalReceitas = 0;
$totalSaidas = 0;

foreach ($movimentacoes as $movimento) {
    $data = $movimento['data_movimento'];

    if (!isset($dadosPorDia[$data])) {
        $dadosPorDia[$data] = [
            'receitas' => 0,
            'despesas' => 0,
        ];
    }

    if ($movimento['tipo'] === 'Receita') {
        $dadosPorDia[$data]['receitas'] += (float) $movimento['valor'];
        $totalReceitas++;
    } else {
        $dadosPorDia[$data]['despesas'] += (float) $movimento['valor'];
        $totalSaidas++;
    }
}

/* Ordem cronológica para o gráfico de evolução. */
ksort($dadosPorDia);

$labelsGrafico = [];
$receitasGrafico = [];
$despesasGrafico = [];
$saldoGrafico = [];

foreach ($dadosPorDia as $data => $valores) {
    $labelsGrafico[] = date('d/m', strtotime($data));
    $receitasGrafico[] = round($valores['receitas'], 2);
    $despesasGrafico[] = round($valores['despesas'], 2);
    $saldoGrafico[] = round(
        $valores['receitas'] - $valores['despesas'],
        2
    );
}

$totalMovimentacoes = count($movimentacoes);

$opcoesJson = JSON_HEX_TAG
    | JSON_HEX_APOS
    | JSON_HEX_AMP
    | JSON_HEX_QUOT
    | JSON_UNESCAPED_UNICODE;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Financeiro | Garage V8</title>
    <link rel="icon" type="image/png" href="img/logogarage.png">
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

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
            --blue: #60a5fa;
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

        input,
        button {
            font: inherit;
        }

        /* MENU LATERAL */

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
            flex-shrink: 0;
            object-fit: contain;
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

        /* CONTEÚDO PRINCIPAL */

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

        /* PAINÉIS */

        .panel {
            min-width: 0;
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
            margin-bottom: 22px;
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
            line-height: 1.7;
        }

        /* FILTROS */

        .filtros {
            display: flex;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 14px;
        }

        .filtros label {
            display: flex;
            min-width: 180px;
            flex: 1;
            flex-direction: column;
            gap: 8px;
            color: #d9d9e1;
            font-size: 12px;
            font-weight: 500;
        }

        .filtros input {
            width: 100%;
            min-width: 0;
            padding: 12px 13px;
            color: var(--text);
            color-scheme: dark;
            background: #0f0f14;
            border: 1px solid #34343e;
            border-radius: 8px;
            outline: none;
        }

        .filtros input:focus {
            border-color: var(--red-light);
            box-shadow: 0 0 0 3px rgba(239, 35, 60, .1);
        }

        .btn,
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 11px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: .2s;
        }

        .btn {
            color: white;
            background: var(--red);
            border: 1px solid var(--red);
        }

        .btn:hover {
            background: var(--red-light);
            transform: translateY(-1px);
        }

        .btn-secondary {
            color: #dedee5;
            background: #202027;
            border: 1px solid #363640;
        }

        .btn-secondary:hover {
            background: #30303a;
        }

        /* INDICADORES */

        .indicadores {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 23px;
        }

        .indicador {
            position: relative;
            overflow: hidden;
            min-width: 0;
            padding: 20px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 13px;
        }

        .indicador::after {
            position: absolute;
            top: -25px;
            right: -20px;
            width: 85px;
            height: 85px;
            background: rgba(217, 4, 41, .07);
            border-radius: 50%;
            content: "";
        }

        .indicador.saida::after {
            background: rgba(239, 35, 60, .07);
        }

        .indicador.saldo::after {
            background: rgba(80, 216, 144, .06);
        }

        .indicador-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 15px;
        }

        .indicador-label {
            color: var(--muted);
            font-size: 12px;
        }

        .indicador-icon {
            display: grid;
            width: 36px;
            height: 36px;
            flex-shrink: 0;
            place-items: center;
            color: var(--green);
            background: rgba(80, 216, 144, .1);
            border-radius: 9px;
        }

        .saida .indicador-icon {
            color: var(--red-light);
            background: rgba(217, 4, 41, .12);
        }

        .saldo .indicador-icon {
            color: var(--yellow);
            background: rgba(242, 189, 80, .1);
        }

        .indicador strong {
            position: relative;
            z-index: 1;
            display: block;
            margin-top: 5px;
            font-size: clamp(21px, 2vw, 27px);
            font-weight: 700;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .indicador-nota {
            margin-top: 7px;
            color: var(--muted);
            font-size: 11px;
        }

        .positivo,
        .receita {
            color: var(--green);
        }

        .negativo,
        .despesa {
            color: #ff6471;
        }

        /* GRÁFICOS */

        .graficos-grid {
            display: grid;
            grid-template-columns: minmax(0, .85fr) minmax(0, 1.5fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .grafico-card {
            min-width: 0;
            padding: 22px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 13px;
        }

        .grafico-card .panel-heading {
            margin-bottom: 12px;
        }

        .grafico-card .panel-title {
            font-size: 15px;
        }

        .grafico-card .panel-description {
            font-size: 11px;
        }

        .grafico-area {
            position: relative;
            width: 100%;
            height: 280px;
            margin-top: 12px;
        }

        .grafico-area.donut {
            height: 260px;
        }

        .grafico-resumo {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        .legenda-item {
            display: flex;
            align-items: center;
            gap: 7px;
            color: #c9c9d2;
            font-size: 11px;
        }

        .legenda-cor {
            width: 9px;
            height: 9px;
            border-radius: 3px;
        }

        .grafico-vazio {
            display: grid;
            height: 100%;
            place-content: center;
            gap: 10px;
            color: var(--muted);
            text-align: center;
            font-size: 12px;
        }

        .grafico-vazio i {
            color: #555560;
            font-size: 26px;
        }

        /* TABELA */

        .tabela-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        table {
            width: 100%;
            min-width: 650px;
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

        tbody tr:hover {
            background: #19191f;
        }

        .tipo-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
        }

        .tipo-badge.receita {
            color: #81e6ab;
            background: rgba(80, 216, 144, .11);
        }

        .tipo-badge.despesa {
            color: #ff9ba6;
            background: rgba(239, 35, 60, .11);
        }

        .valor-movimento {
            font-weight: 600;
            white-space: nowrap;
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

            .indicadores {
                gap: 11px;
            }

            .indicador {
                padding: 16px;
            }

            .graficos-grid {
                grid-template-columns: 1fr;
            }

            .grafico-area.donut {
                max-width: 400px;
                margin-right: auto;
                margin-left: auto;
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

            .indicadores {
                grid-template-columns: 1fr;
            }

            .indicador {
                padding: 17px;
            }

            .panel,
            .grafico-card {
                padding: 17px 14px;
            }

            .filtros {
                align-items: stretch;
                flex-direction: column;
            }

            .filtros label {
                min-width: 0;
                width: 100%;
            }

            .filtros .btn,
            .filtros .btn-secondary {
                width: 100%;
            }

            .menu {
                grid-template-columns: 1fr 1fr;
            }

            .menu a {
                gap: 8px;
                font-size: 11px;
            }

            .grafico-area {
                height: 250px;
            }

            .grafico-area.donut {
                height: 230px;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">

    <a href="index.php" class="brand">
        <img
            src="img/logogarage.png"
            alt="Logo Garage V8"
            class="brand-logo"
        >

        <div>
            <div class="brand-name">
                GARAGE <strong>V8</strong>
            </div>

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

        <a href="pagamentos.php">
            <i class="fa-solid fa-credit-card"></i>
            <span>Pagamentos</span>
        </a>

        <a href="despesas.php">
            <i class="fa-solid fa-arrow-trend-down"></i>
            <span>Despesas</span>
        </a>

        <a href="financeiro.php" class="active">
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
            <div class="eyebrow">Gestão financeira</div>

            <h1>Controle financeiro</h1>

            <p class="muted">
                Acompanhe as entradas, saídas e o saldo operacional da Garage V8.
            </p>
        </div>

        <div class="header-date">
            <i class="fa-regular fa-calendar"></i>
            <?= e(date('d/m/Y')) ?>
        </div>
    </header>

    <!-- FILTRO DE PERÍODO -->

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2 class="panel-title">
                    <i class="fa-solid fa-filter"></i>
                    Filtrar período
                </h2>

                <p class="panel-description">
                    Os indicadores, gráficos e movimentações acompanham as datas selecionadas.
                </p>
            </div>
        </div>

        <form method="get" class="filtros">

            <label>
                Data inicial
                <input
                    type="date"
                    name="inicio"
                    value="<?= e($inicio) ?>"
                    max="<?= e($fim) ?>"
                    required
                >
            </label>

            <label>
                Data final
                <input
                    type="date"
                    name="fim"
                    value="<?= e($fim) ?>"
                    min="<?= e($inicio) ?>"
                    required
                >
            </label>

            <button class="btn" type="submit">
                <i class="fa-solid fa-magnifying-glass"></i>
                Consultar período
            </button>

            <a href="financeiro.php" class="btn-secondary">
                <i class="fa-solid fa-rotate-left"></i>
                Mês atual
            </a>

        </form>
    </section>

    <!-- INDICADORES -->

    <section class="indicadores">

        <article class="indicador">
            <div class="indicador-top">
                <span class="indicador-label">Receitas recebidas</span>

                <span class="indicador-icon">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                </span>
            </div>

            <strong class="positivo">
                R$ <?= dinheiro($receitas) ?>
            </strong>

            <p class="indicador-nota">
                <?= $totalReceitas ?> entrada(s) no período
            </p>
        </article>

        <article class="indicador saida">
            <div class="indicador-top">
                <span class="indicador-label">Despesas pagas</span>

                <span class="indicador-icon">
                    <i class="fa-solid fa-arrow-trend-down"></i>
                </span>
            </div>

            <strong class="negativo">
                R$ <?= dinheiro($despesasPagas) ?>
            </strong>

            <p class="indicador-nota">
                <?= $totalSaidas ?> saída(s) no período
            </p>
        </article>

        <article class="indicador saldo">
            <div class="indicador-top">
                <span class="indicador-label">Saldo operacional</span>

                <span class="indicador-icon">
                    <i class="fa-solid fa-wallet"></i>
                </span>
            </div>

            <strong class="<?= $saldo >= 0 ? 'positivo' : 'negativo' ?>">
                R$ <?= dinheiro($saldo) ?>
            </strong>

            <p class="indicador-nota">
                Receitas recebidas menos despesas pagas
            </p>
        </article>

    </section>

    <!-- GRÁFICOS -->

    <section class="graficos-grid">

        <!-- GRÁFICO DE COMPARAÇÃO -->

        <article class="grafico-card">
            <div class="panel-heading">
                <div>
                    <h2 class="panel-title">
                        <i class="fa-solid fa-chart-pie"></i>
                        Receitas x despesas
                    </h2>

                    <p class="panel-description">
                        Comparação dos valores pagos e recebidos.
                    </p>
                </div>
            </div>

            <div class="grafico-area donut">
                <?php if ($receitas == 0 && $despesasPagas == 0): ?>

                    <div class="grafico-vazio">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Sem movimentações financeiras neste período.</span>
                    </div>

                <?php else: ?>

                    <canvas id="graficoComparacao"></canvas>

                <?php endif; ?>
            </div>

            <div class="grafico-resumo">
                <div class="legenda-item">
                    <span
                        class="legenda-cor"
                        style="background:#50d890"
                    ></span>
                    Receitas: R$ <?= dinheiro($receitas) ?>
                </div>

                <div class="legenda-item">
                    <span
                        class="legenda-cor"
                        style="background:#ef233c"
                    ></span>
                    Despesas: R$ <?= dinheiro($despesasPagas) ?>
                </div>
            </div>
        </article>

        <!-- GRÁFICO DE EVOLUÇÃO -->

        <article class="grafico-card">
            <div class="panel-heading">
                <div>
                    <h2 class="panel-title">
                        <i class="fa-solid fa-chart-line"></i>
                        Evolução financeira
                    </h2>

                    <p class="panel-description">
                        Entradas, saídas e saldo diário nos dias com movimentações.
                    </p>
                </div>
            </div>

            <div class="grafico-area">
                <?php if (empty($dadosPorDia)): ?>

                    <div class="grafico-vazio">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>Não há dados suficientes para exibir a evolução.</span>
                    </div>

                <?php else: ?>

                    <canvas id="graficoEvolucao"></canvas>

                <?php endif; ?>
            </div>

            <div class="grafico-resumo">
                <div class="legenda-item">
                    <span
                        class="legenda-cor"
                        style="background:#50d890"
                    ></span>
                    Receitas
                </div>

                <div class="legenda-item">
                    <span
                        class="legenda-cor"
                        style="background:#ef233c"
                    ></span>
                    Despesas
                </div>

                <div class="legenda-item">
                    <span
                        class="legenda-cor"
                        style="background:#60a5fa"
                    ></span>
                    Saldo diário
                </div>
            </div>
        </article>

    </section>

    <!-- MOVIMENTAÇÕES -->

    <section class="panel">

        <div class="panel-heading">
            <div>
                <h2 class="panel-title">
                    <i class="fa-solid fa-money-bill-transfer"></i>
                    Movimentações financeiras
                </h2>

                <p class="panel-description">
                    Histórico de entradas e saídas registradas no período.
                </p>
            </div>
        </div>

        <p class="muted" style="margin-bottom:18px;">
            Período consultado:
            <strong>
                <?= e(date('d/m/Y', strtotime($inicio))) ?>
                até
                <?= e(date('d/m/Y', strtotime($fim))) ?>
            </strong>
        </p>

        <div class="tabela-wrap">
            <table>
                <thead>
                <tr>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Tipo</th>
                    <th>Forma de pagamento</th>
                    <th>Valor</th>
                </tr>
                </thead>

                <tbody>
                <?php if (!$movimentacoes): ?>

                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <i class="fa-solid fa-receipt"></i>
                                Nenhuma movimentação encontrada no período.
                            </div>
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($movimentacoes as $m): ?>
                        <?php $ehReceita = $m['tipo'] === 'Receita'; ?>

                        <tr>
                            <td>
                                <?= e(date(
                                    'd/m/Y',
                                    strtotime($m['data_movimento'])
                                )) ?>
                            </td>

                            <td>
                                <strong><?= e($m['descricao']) ?></strong>
                            </td>

                            <td>
                                <span class="tipo-badge <?= $ehReceita
                                    ? 'receita'
                                    : 'despesa' ?>">

                                    <i class="fa-solid <?= $ehReceita
                                        ? 'fa-arrow-up'
                                        : 'fa-arrow-down' ?>"></i>

                                    <?= e($m['tipo']) ?>
                                </span>
                            </td>

                            <td>
                                <?= e($m['forma'] ?? '—') ?>
                            </td>

                            <td>
                                <span class="valor-movimento <?= $ehReceita
                                    ? 'receita'
                                    : 'despesa' ?>">

                                    <?= $ehReceita ? '+' : '-' ?>
                                    R$ <?= dinheiro($m['valor']) ?>
                                </span>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <span>
                Total de movimentações:
                <strong><?= $totalMovimentacoes ?></strong>
            </span>

            <span>
                <?= $totalReceitas ?> entrada(s) ·
                <?= $totalSaidas ?> saída(s)
            </span>
        </div>

    </section>

</main>

<!-- BIBLIOTECA DOS GRÁFICOS -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    const valoresFinanceiros = {
        receitas: <?= json_encode($receitas, $opcoesJson) ?>,
        despesas: <?= json_encode($despesasPagas, $opcoesJson) ?>,
        saldo: <?= json_encode($saldo, $opcoesJson) ?>,

        labels: <?= json_encode($labelsGrafico, $opcoesJson) ?>,
        receitasDia: <?= json_encode($receitasGrafico, $opcoesJson) ?>,
        despesasDia: <?= json_encode($despesasGrafico, $opcoesJson) ?>,
        saldoDia: <?= json_encode($saldoGrafico, $opcoesJson) ?>
    };

    /*
     * Formatação de valores em reais.
     */
    const formatoReal = new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });

    /*
     * Configurações gerais do Chart.js.
     */
    Chart.defaults.color = '#92929e';
    Chart.defaults.font.family = 'Poppins';
    Chart.defaults.font.size = 11;

    /*
     * GRÁFICO 1: RECEITAS X DESPESAS
     */
    const elementoComparacao = document.getElementById('graficoComparacao');

    if (elementoComparacao) {
        new Chart(elementoComparacao, {
            type: 'doughnut',

            data: {
                labels: ['Receitas recebidas', 'Despesas pagas'],

                datasets: [{
                    data: [
                        valoresFinanceiros.receitas,
                        valoresFinanceiros.despesas
                    ],

                    backgroundColor: [
                        '#50d890',
                        '#ef233c'
                    ],

                    borderColor: [
                        '#141419',
                        '#141419'
                    ],

                    borderWidth: 4,
                    hoverOffset: 7
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',

                plugins: {
                    legend: {
                        display: false
                    },

                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' +
                                    formatoReal.format(context.raw);
                            }
                        }
                    }
                }
            }
        });
    }

    /*
     * GRÁFICO 2: EVOLUÇÃO FINANCEIRA DIÁRIA
     */
    const elementoEvolucao = document.getElementById('graficoEvolucao');

    if (elementoEvolucao) {
        new Chart(elementoEvolucao, {
            type: 'line',

            data: {
                labels: valoresFinanceiros.labels,

                datasets: [
                    {
                        label: 'Receitas',
                        data: valoresFinanceiros.receitasDia,
                        borderColor: '#50d890',
                        backgroundColor: 'rgba(80, 216, 144, 0.10)',
                        pointBackgroundColor: '#50d890',
                        pointBorderColor: '#141419',
                        pointBorderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        borderWidth: 2,
                        tension: 0.35,
                        fill: false
                    },

                    {
                        label: 'Despesas',
                        data: valoresFinanceiros.despesasDia,
                        borderColor: '#ef233c',
                        backgroundColor: 'rgba(239, 35, 60, 0.08)',
                        pointBackgroundColor: '#ef233c',
                        pointBorderColor: '#141419',
                        pointBorderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        borderWidth: 2,
                        tension: 0.35,
                        fill: false
                    },

                    {
                        label: 'Saldo diário',
                        data: valoresFinanceiros.saldoDia,
                        borderColor: '#60a5fa',
                        backgroundColor: 'rgba(96, 165, 250, 0.08)',
                        pointBackgroundColor: '#60a5fa',
                        pointBorderColor: '#141419',
                        pointBorderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        borderWidth: 2,
                        tension: 0.35,
                        fill: false
                    }
                ]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },

                plugins: {
                    legend: {
                        display: false
                    },

                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' +
                                    formatoReal.format(context.raw);
                            }
                        }
                    }
                },

                scales: {
                    x: {
                        grid: {
                            display: false
                        },

                        ticks: {
                            maxRotation: 45,
                            minRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 9
                        },

                        border: {
                            color: '#2b2b34'
                        }
                    },

                    y: {
                        beginAtZero: true,

                        grid: {
                            color: 'rgba(146, 146, 158, 0.12)'
                        },

                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('pt-BR', {
                                    notation: 'compact',
                                    maximumFractionDigits: 1
                                }).format(value);
                            }
                        },

                        border: {
                            display: false
                        }
                    }
                }
            }
        });
    }
</script>

<!-- VLibras - Acessibilidade -->
<div vw class="enabled">
    <div vw-access-button class="active"></div>

    <div vw-plugin-wrapper>
        <div class="vw-plugin-top-wrapper"></div>
    </div>
</div>

<script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>

<script>
    new window.VLibras.Widget('https://vlibras.gov.br/app');
</script>

</body>
</html>
