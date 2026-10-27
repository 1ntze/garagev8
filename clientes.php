
<?php
require_once __DIR__ . '/auth.php';
exigirLogin();

require_once __DIR__ . '/config/conexao.php';

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if ($nome === '') {
        $erro = 'Informe o nome do cliente.';
    } else {
        try {
            $sql = "INSERT INTO clientes
                    (nome, telefone, email, observacoes, cadastrado_por)
                    VALUES (?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nome,
                $telefone !== '' ? $telefone : null,
                $email !== '' ? $email : null,
                $observacoes !== '' ? $observacoes : null,
                1
            ]);

            header('Location: clientes.php?sucesso=1');
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $erro = 'Não foi possível cadastrar o cliente.';
        }
    }
}

if (isset($_GET['sucesso'])) {
    $sucesso = 'Cliente cadastrado com sucesso!';
}

$busca = trim($_GET['busca'] ?? '');

$sql = "SELECT id_cliente, nome, telefone, email, data_cadastro
        FROM clientes";

if ($busca !== '') {
    $sql .= " WHERE nome LIKE ? OR telefone LIKE ? OR email LIKE ?";
    $stmt = $pdo->prepare($sql . " ORDER BY id_cliente DESC");
    $termo = '%' . $busca . '%';
    $stmt->execute([$termo, $termo, $termo]);
} else {
    $stmt = $pdo->query($sql . " ORDER BY id_cliente DESC");
}

$clientes = $stmt->fetchAll();

function e($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Clientes | Garage V8</title>

    <link rel="icon" type="image/png" href="img/logogarage.png">
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
            --border: rgba(255,255,255,.07);
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

        input, textarea, button {
            font: inherit;
        }

        .layout {
            min-height: 100vh;
            display: flex;
        }

        /* NAVEGAÇÃO LATERAL */

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
            background: rgba(217,4,41,.12);
            border-color: rgba(217,4,41,.18);
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
            box-shadow: 0 0 10px rgba(57,200,121,.35);
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
            background: rgba(217,4,41,.1);
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
            margin-bottom: 30px;
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

        /* MENSAGENS */

        .message {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 12px;
        }

        .message.ok {
            background: rgba(22,52,33,.7);
            color: #9de5b1;
            border: 1px solid rgba(57,200,121,.18);
        }

        .message.error {
            background: rgba(66,26,32,.7);
            color: #ffadb5;
            border: 1px solid rgba(239,35,60,.2);
        }

        /* CARDS E SEÇÕES */

        .content-card {
            min-width: 0;
            padding: 27px;
            background: linear-gradient(145deg, #171719, #111113);
            border: 1px solid var(--border);
            border-radius: 13px;
        }

        .section-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .section-icon {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 11px;
            color: var(--red-light);
            background: rgba(217,4,41,.12);
        }

        .section-heading h2 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .section-heading p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 20px;
        }

        .field {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
            color: #c7c7ce;
            font-size: 11px;
            font-weight: 500;
        }

        .field input,
        .field textarea,
        .search-input {
            width: 100%;
            min-width: 0;
            background: #101012;
            color: #f5f5f5;
            border: 1px solid #303036;
            border-radius: 8px;
            padding: 13px 14px;
            outline: none;
            font-size: 12px;
            transition: border-color .2s, box-shadow .2s;
        }

        .field input::placeholder,
        .field textarea::placeholder,
        .search-input::placeholder {
            color: #65656e;
        }

        .field input:focus,
        .field textarea:focus,
        .search-input:focus {
            border-color: var(--red-light);
            box-shadow: 0 0 0 3px rgba(217,4,41,.1);
        }

        .field textarea {
            min-height: 105px;
            resize: vertical;
        }

        .full {
            grid-column: 1 / -1;
        }

        .btn {
            min-height: 43px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 12px 19px;
            border: 1px solid transparent;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--red-light), #b90022);
            color: white;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: transform .2s, box-shadow .2s;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 18px rgba(217,4,41,.2);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
        }

        /* LISTAGEM DE CLIENTES */

        .clients-section {
            margin-top: 23px;
        }

        .list-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 22px;
        }

        .client-count {
            padding: 7px 10px;
            background: rgba(217,4,41,.1);
            border: 1px solid rgba(217,4,41,.2);
            border-radius: 20px;
            color: #ff6578;
            font-size: 10px;
            white-space: nowrap;
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 22px;
        }

        .search-input {
            flex: 1;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 9px;
        }

        table {
            width: 100%;
            min-width: 650px;
            border-collapse: collapse;
            text-align: left;
        }

        thead {
            background: #1b1b1f;
        }

        th {
            padding: 15px 16px;
            color: #ff6578;
            font-size: 9px;
            font-weight: 600;
            letter-spacing: .8px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            padding: 15px 16px;
            border-top: 1px solid var(--border);
            color: #c5c5cc;
            font-size: 11px;
            vertical-align: middle;
        }

        tbody tr {
            transition: background .2s;
        }

        tbody tr:hover {
            background: rgba(255,255,255,.025);
        }

        .client-id {
            color: #777780;
            font-variant-numeric: tabular-nums;
        }

        .client-name {
            color: #f1f1f3;
            font-weight: 500;
        }

        .empty-state {
            padding: 35px 20px;
            text-align: center;
            color: var(--muted);
        }

        .empty-state i {
            display: block;
            margin-bottom: 12px;
            color: #55555e;
            font-size: 25px;
        }

        .empty-state p {
            margin: 0;
            font-size: 11px;
        }

        .table-summary {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 15px;
            color: var(--muted);
            font-size: 10px;
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

        /* RESPONSIVIDADE */

        @media (max-width: 1100px) {
            .sidebar {
                width: 225px;
                min-width: 225px;
            }

            .main {
                padding: 32px 25px 20px;
            }

            .content-card {
                padding: 22px;
            }
        }

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

            .form-grid {
                gap: 17px;
            }
        }

        @media (max-width: 520px) {
            .content-card {
                padding: 19px 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full {
                grid-column: auto;
            }

            .search-form {
                flex-direction: column;
            }

            .search-form .btn {
                width: 100%;
            }

            .list-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
            }

            .table-summary,
            .footer {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
<div class="layout">

    <!-- BARRA DE NAVEGAÇÃO -->
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
            <a href="index.php">
                <i class="fa-solid fa-house"></i>
                <span>Painel inicial</span>
            </a>

            <a href="clientes.php" class="active">
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

    <!-- CONTEÚDO PRINCIPAL -->
    <main class="main">

        <header class="header">
            <div class="page-heading">
                <span class="eyebrow">GESTÃO DE CLIENTES</span>
                <h1>Clientes<span>.</span></h1>
                <p>Cadastre, organize e consulte os clientes da oficina.</p>
            </div>

            <div class="header-status">
                <span class="status-dot"></span>
                Sistema conectado
            </div>
        </header>

        <?php if ($sucesso): ?>
            <div class="message ok">
                <i class="fa-solid fa-circle-check"></i>
                <?= e($sucesso) ?>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="message error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= e($erro) ?>
            </div>
        <?php endif; ?>

        <!-- FORMULÁRIO DE CADASTRO -->
        <section class="content-card">

            <div class="section-heading">
                <div class="section-icon">
                    <i class="fa-solid fa-user-plus"></i>
                </div>

                <div>
                    <h2>Novo cliente</h2>
                    <p>Preencha os dados para registrar um cliente.</p>
                </div>
            </div>

            <form method="post">
                <div class="form-grid">

                    <label class="field full">
                        Nome completo *
                        <input
                            type="text"
                            name="nome"
                            maxlength="100"
                            placeholder="Digite o nome completo"
                            required
                        >
                    </label>

                    <label class="field">
                        Telefone
                        <input
                            type="tel"
                            name="telefone"
                            maxlength="20"
                            placeholder="(00) 00000-0000"
                        >
                    </label>

                    <label class="field">
                        E-mail
                        <input
                            type="email"
                            name="email"
                            maxlength="200"
                            placeholder="cliente@email.com"
                        >
                    </label>

                    <label class="field full">
                        Observações
                        <textarea
                            name="observacoes"
                            placeholder="Informações adicionais sobre o cliente..."
                        ></textarea>
                    </label>

                    <div class="full form-actions">
                        <button class="btn" type="submit">
                            <i class="fa-solid fa-plus"></i>
                            Cadastrar cliente
                        </button>
                    </div>

                </div>
            </form>
        </section>

        <!-- LISTAGEM DE CLIENTES -->
        <section class="content-card clients-section">

            <div class="list-heading">
                <div class="section-heading" style="margin-bottom:0">
                    <div class="section-icon">
                        <i class="fa-solid fa-address-book"></i>
                    </div>

                    <div>
                        <h2>Clientes cadastrados</h2>
                        <p>Consulte os registros armazenados no sistema.</p>
                    </div>
                </div>

                <span class="client-count">
                    <?= count($clientes) ?> registro(s)
                </span>
            </div>

            <form method="get" class="search-form">
                <input
                    class="search-input"
                    type="search"
                    name="busca"
                    placeholder="Pesquisar por nome, telefone ou e-mail..."
                    value="<?= e($busca) ?>"
                >

                <button class="btn" type="submit">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Pesquisar
                </button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>E-mail</th>
                            <th>Data de cadastro</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (!$clientes): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fa-solid fa-users"></i>
                                    <p>Nenhum cliente encontrado.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td class="client-id">
                                    #<?= (int) $cliente['id_cliente'] ?>
                                </td>

                                <td class="client-name">
                                    <?= e($cliente['nome']) ?>
                                </td>

                                <td>
                                    <?= e($cliente['telefone'] ?: '—') ?>
                                </td>

                                <td>
                                    <?= e($cliente['email'] ?: '—') ?>
                                </td>

                                <td>
                                    <?= e($cliente['data_cadastro']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-summary">
                <span>
                    Exibindo <?= count($clientes) ?>
                    cliente(s) nesta consulta.
                </span>

                <?php if ($busca !== ''): ?>
                    <span>
                        Filtro: <?= e($busca) ?>
                        · <a href="clientes.php" style="color:#ff6578">Limpar pesquisa</a>
                    </span>
                <?php endif; ?>
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