<?php
require_once __DIR__ . '/auth.php';
exigirLogin();

require_once __DIR__ . '/config/conexao.php';

$stmt = $pdo->query("SELECT COUNT(*) AS total FROM usuarios");
$totalUsuarios = (int) $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" type="image/png" href="img/logogarage.png">
    <title>Garage V8 | Painel</title>

    <link rel="stylesheet" href="assets/style.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --red: #d90429;
            --red-light: #ef233c;
            --bg: #0b0b0d;
            --sidebar: #101012;
            --panel: #151518;
            --border: rgba(255, 255, 255, .07);
            --text: #f4f4f5;
            --muted: #85858e;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: 'Poppins', sans-serif;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .layout {
            min-height: 100vh;
            display: flex;
        }

        /* BARRA LATERAL */

        .sidebar {
            width: 255px;
            min-width: 255px;
            background: var(--sidebar);
            border-right: 1px solid var(--border);
            padding: 30px 18px 20px;
            display: flex;
            flex-direction: column;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 8px 28px;
            border-bottom: 1px solid var(--border);
        }

        /* LOGO ORIGINAL GARAGE V8 */

        .brand-logo {
            width: 52px;
            height: 52px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .brand-name {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 1px;
            white-space: nowrap;
        }

        .brand-name strong {
            color: var(--red-light);
        }

        .brand-subtitle {
            color: var(--muted);
            font-size: 8px;
            letter-spacing: 1.3px;
            white-space: nowrap;
        }

        .menu-label {
            margin: 30px 12px 13px;
            color: #66666f;
            font-size: 9px;
            font-weight: 600;
            letter-spacing: 1.5px;
        }

        .menu {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .menu a {
            min-height: 45px;
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 0 14px;
            border: 1px solid transparent;
            border-radius: 9px;
            color: #9999a2;
            font-size: 12px;
            font-weight: 400;
            transition: .2s ease;
        }

        .menu a i {
            width: 17px;
            text-align: center;
            font-size: 14px;
        }

        .menu a:hover {
            color: #fff;
            background: #1a1a1e;
        }

        .menu a.active {
            color: #fff;
            background: rgba(217, 4, 41, .12);
            border-color: rgba(217, 4, 41, .18);
            box-shadow: inset 3px 0 var(--red-light);
        }

        .menu a.active i {
            color: var(--red-light);
        }

        .sidebar-bottom {
            margin-top: auto;
            padding-top: 24px;
        }

        .sidebar-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 11px;
            background: #171719;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .status-dot {
            display: inline-block;
            width: 7px;
            height: 7px;
            flex: 0 0 7px;
            border-radius: 50%;
            background: #39c879;
            box-shadow: 0 0 10px rgba(57, 200, 121, .35);
        }

        .sidebar-status div {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .sidebar-status strong {
            font-size: 10px;
            font-weight: 500;
        }

        .sidebar-status small {
            color: var(--muted);
            font-size: 9px;
        }

        .logout-link {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 10px;
            padding: 13px;
            border-radius: 8px;
            color: #9999a2;
            font-size: 12px;
            transition: .2s;
        }

        .logout-link:hover {
            background: rgba(217, 4, 41, .1);
            color: #ff5268;
        }

        /* CONTEÚDO PRINCIPAL */

        .main {
            min-width: 0;
            flex: 1;
            padding: 40px 42px 22px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 32px;
        }

        .eyebrow {
            display: block;
            margin-bottom: 9px;
            color: var(--red-light);
            font-size: 9px;
            font-weight: 600;
            letter-spacing: 2px;
        }

        .page-heading h1 {
            margin: 0;
            font-size: clamp(25px, 3vw, 34px);
            line-height: 1.3;
            font-weight: 600;
            letter-spacing: -.8px;
        }

        .page-heading h1 span {
            color: var(--red-light);
        }

        .page-heading p {
            margin: 9px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .header-status {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 11px 14px;
            border: 1px solid var(--border);
            border-radius: 30px;
            color: #bdbdc4;
            background: #111113;
            font-size: 10px;
            white-space: nowrap;
        }

        /* CARDS */

        .cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .stat-card {
            min-width: 0;
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: 13px;
            background: linear-gradient(145deg, #171719, #111113);
            transition: border-color .2s, transform .2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: rgba(217, 4, 41, .35);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            color: var(--red-light);
            background: rgba(217, 4, 41, .12);
            font-size: 17px;
        }

        .stat-tag {
            color: #777780;
            font-size: 8px;
            font-weight: 600;
            letter-spacing: 1px;
        }

        .stat-label {
            margin: 22px 0 4px;
            color: #b0b0b8;
            font-size: 11px;
        }

        .number {
            font-size: 32px;
            line-height: 1.4;
            font-weight: 600;
            letter-spacing: -.5px;
        }

        .stat-footer {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 17px;
            padding-top: 14px;
            border-top: 1px solid var(--border);
            color: #85858e;
            font-size: 9px;
        }

        .stat-footer i {
            color: var(--red-light);
            font-size: 10px;
        }

        /* BANNER DE BOAS-VINDAS */

        .welcome-card {
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 245px;
            margin-top: 23px;
            padding: 34px;
            border: 1px solid rgba(217, 4, 41, .2);
            border-radius: 14px;
            background:
                radial-gradient(
                    ellipse at 90% 50%,
                    rgba(217, 4, 41, .15),
                    transparent 42%
                ),
                linear-gradient(110deg, #171719, #101012);
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            max-width: 570px;
        }

        .welcome-content h2 {
            margin: 0;
            font-size: clamp(25px, 3vw, 36px);
            font-weight: 600;
            line-height: 1.3;
            letter-spacing: -.7px;
        }

        .welcome-content h2 span {
            color: var(--red-light);
        }

        .welcome-content p {
            max-width: 480px;
            margin: 13px 0 20px;
            color: #a0a0a9;
            font-size: 11px;
            line-height: 1.9;
        }

        .welcome-features {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 18px;
        }

        .welcome-features span {
            display: flex;
            align-items: center;
            gap: 7px;
            color: #bdbdc4;
            font-size: 9px;
        }

        .welcome-features i {
            color: var(--red-light);
        }

        .welcome-decoration {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 175px;
            height: 175px;
            margin: 0 22px 0 10px;
            flex-shrink: 0;
            border: 1px solid rgba(239, 35, 60, .2);
            border-radius: 50%;
            color: rgba(239, 35, 60, .22);
        }

        .welcome-decoration::before,
        .welcome-decoration::after {
            content: "";
            position: absolute;
            inset: 13px;
            border: 1px solid rgba(239, 35, 60, .15);
            border-radius: 50%;
        }

        .welcome-decoration::after {
            inset: 27px;
        }

        .welcome-decoration i {
            font-size: 69px;
        }

        .welcome-decoration span {
            position: absolute;
            right: 0;
            bottom: 18px;
            color: var(--red-light);
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 2px;
        }

        /* RODAPÉ */

        .footer {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 28px;
            padding-top: 17px;
            border-top: 1px solid var(--border);
            color: #66666f;
            font-size: 9px;
            letter-spacing: .5px;
        }

        .footer span:first-child {
            color: #a0a0a9;
            font-weight: 600;
            letter-spacing: 1.5px;
        }

        /* TABLETS */

        @media (max-width: 1100px) {
            .sidebar {
                width: 225px;
                min-width: 225px;
            }

            .main {
                padding: 32px 25px 20px;
            }

            .cards {
                gap: 12px;
            }

            .stat-card {
                padding: 17px;
            }

            .welcome-decoration {
                width: 130px;
                height: 130px;
                margin-right: 0;
            }
        }

        /* CELULARES E TELAS MENORES */

        @media (max-width: 800px) {
            .layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-width: 0;
                padding: 15px;
                border-right: 0;
                border-bottom: 1px solid var(--border);
            }

            .brand {
                padding: 0 4px 15px;
            }

            .brand-logo {
                width: 45px;
                height: 45px;
            }

            .menu-label {
                margin: 17px 5px 10px;
            }

            .menu {
                flex-direction: row;
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 7px;
            }

            .menu a {
                min-width: max-content;
                min-height: 40px;
                padding: 0 12px;
            }

            .menu a.active {
                box-shadow: inset 0 -2px var(--red-light);
            }

            .sidebar-bottom {
                padding-top: 10px;
            }

            .sidebar-status {
                display: none;
            }

            .logout-link {
                margin-top: 5px;
                padding: 8px 5px;
            }

            .main {
                padding: 27px 18px 20px;
            }

            .header {
                align-items: flex-start;
                flex-direction: column;
                margin-bottom: 24px;
            }

            .cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .welcome-decoration {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .cards {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 20px;
            }

            .welcome-card {
                min-height: auto;
                padding: 25px 21px;
            }

            .welcome-features {
                flex-direction: column;
                gap: 10px;
            }

            .header-status {
                font-size: 9px;
            }

            .footer {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
<div class="layout">

    <!-- NAVEGAÇÃO LATERAL -->
    <aside class="sidebar">

        <a href="index.php" class="brand">
            <img
                src="img/logogarage.png"
                alt="Logo Garage V8"
                class="brand-logo"
            >

            <div class="brand-text">
                <span class="brand-name">GARAGE <strong>V8</strong></span>
                <span class="brand-subtitle">GESTÃO AUTOMOTIVA</span>
            </div>
        </a>

        <div class="menu-label">MENU PRINCIPAL</div>

        <nav class="menu">
            <a href="index.php" class="active">
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

            <a href="financeiro.php">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Financeiro</span>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <div class="sidebar-status">
                <span class="status-dot"></span>
                <div>
                    <strong>Sistema online</strong>
                    <small>Conexão ativa</small>
                </div>
            </div>

            <a href="logout.php" class="logout-link">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Sair do sistema</span>
            </a>
        </div>
    </aside>

    <!-- CONTEÚDO -->
    <main class="main">

        <header class="header">
            <div class="page-heading">
                <span class="eyebrow">VISÃO GERAL</span>

                <h1>Painel inicial<span>.</span></h1>

                <p>
                    Bem-vindo ao centro de gestão da Garage V8.
                </p>
            </div>

            <div class="header-status">
                <span class="status-dot"></span>
                Sistema conectado
            </div>
        </header>

        <!-- INDICADORES -->
        <section class="cards">

            <article class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <span class="stat-tag">CADASTROS</span>
                </div>

                <p class="stat-label">Usuários cadastrados</p>

                <div class="number">
                    <?= $totalUsuarios ?>
                </div>

                <div class="stat-footer">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Dados carregados do sistema</span>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon">
                        <i class="fa-solid fa-car-side"></i>
                    </div>
                    <span class="stat-tag">CLIENTES</span>
                </div>

                <p class="stat-label">Clientes cadastrados</p>

                <div class="number">—</div>

                <div class="stat-footer">
                    <i class="fa-solid fa-clock"></i>
                    <span>Indicador em implantação</span>
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <span class="stat-tag">OPERAÇÃO</span>
                </div>

                <p class="stat-label">Atendimentos</p>

                <div class="number">—</div>

                <div class="stat-footer">
                    <i class="fa-solid fa-clock"></i>
                    <span>Indicador em implantação</span>
                </div>
            </article>

        </section>

        <!-- APRESENTAÇÃO -->
        <section class="welcome-card">
            <div class="welcome-content">

                <span class="eyebrow">
                    GARAGE V8 • GESTÃO INTELIGENTE
                </span>

                <h2>
                    Controle total.<br>
                    <span>Mais desempenho.</span>
                </h2>

                <p>
                    Gerencie clientes, veículos, serviços, atendimentos
                    e movimentações financeiras em um só lugar.
                </p>

                <div class="welcome-features">
                    <span>
                        <i class="fa-solid fa-check"></i>
                        Gestão centralizada
                    </span>

                    <span>
                        <i class="fa-solid fa-check"></i>
                        Controle operacional
                    </span>

                    <span>
                        <i class="fa-solid fa-check"></i>
                        Organização financeira
                    </span>
                </div>

            </div>

            <div class="welcome-decoration">
                <i class="fa-solid fa-gauge-high"></i>
                <span>V8</span>
            </div>
        </section>

        <footer class="footer">
            <span>GARAGE V8</span>
            <span>Gestão automotiva</span>
        </footer>

    </main>
</div>
</body>
</html>
