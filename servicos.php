
<?php
require_once __DIR__ . '/auth.php';
exigirLogin();

require_once __DIR__ . '/config/conexao.php';

$erro = '';
$sucesso = '';

function e($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// Cadastrar ou alterar status do serviço
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'cadastrar') {
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $categoria = trim($_POST['categoria'] ?? '');
        $valorTexto = trim($_POST['valor_referencia'] ?? '');
        $duracaoTexto = trim($_POST['duracao_estimada'] ?? '');

        if ($nome === '') {
            $erro = 'Informe o nome do serviço.';
        } elseif (strlen($nome) > 100 || strlen($categoria) > 50) {
            $erro = 'Confira o tamanho do nome ou da categoria.';
        } elseif (
            $valorTexto === '' ||
            !is_numeric($valorTexto) ||
            (float) $valorTexto < 0 ||
            (float) $valorTexto > 99999999.99
        ) {
            $erro = 'Informe um valor de referência válido.';
        } elseif (
            $duracaoTexto !== '' &&
            (!ctype_digit($duracaoTexto) || (int) $duracaoTexto < 1)
        ) {
            $erro = 'A duração deve ser um número de minutos maior que zero.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO servicos
                    (nome, descricao, categoria, valor_referencia,
                     duracao_estimada, ativo)
                    VALUES (?, ?, ?, ?, ?, 1)"
                );

                $stmt->execute([
                    $nome,
                    $descricao !== '' ? $descricao : null,
                    $categoria !== '' ? $categoria : null,
                    number_format((float) $valorTexto, 2, '.', ''),
                    $duracaoTexto !== '' ? (int) $duracaoTexto : null
                ]);

                header('Location: servicos.php?sucesso=cadastrado');
                exit;
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $erro = 'Não foi possível cadastrar o serviço.';
            }
        }
    } elseif ($acao === 'status') {
        $id = filter_input(INPUT_POST, 'id_servico', FILTER_VALIDATE_INT);
        $novoStatus = $_POST['novo_status'] ?? '';

        if ($id && in_array($novoStatus, ['0', '1'], true)) {
            try {
                $stmt = $pdo->prepare(
                    'UPDATE servicos SET ativo = ? WHERE id_servico = ?'
                );

                $stmt->execute([(int) $novoStatus, $id]);

                header('Location: servicos.php?sucesso=status');
                exit;
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $erro = 'Não foi possível alterar o status do serviço.';
            }
        } else {
            $erro = 'Solicitação de alteração inválida.';
        }
    }
}

if (($_GET['sucesso'] ?? '') === 'cadastrado') {
    $sucesso = 'Serviço cadastrado com sucesso!';
} elseif (($_GET['sucesso'] ?? '') === 'status') {
    $sucesso = 'Status do serviço atualizado!';
}

// Pesquisa
$busca = trim($_GET['busca'] ?? '');

$sql = "SELECT id_servico, nome, descricao, categoria,
               valor_referencia, duracao_estimada, ativo
        FROM servicos";

if ($busca !== '') {
    $sql .= ' WHERE nome LIKE ? OR categoria LIKE ?';
    $stmt = $pdo->prepare($sql . ' ORDER BY nome');
    $termo = '%' . $busca . '%';
    $stmt->execute([$termo, $termo]);
} else {
    $stmt = $pdo->query($sql . ' ORDER BY nome');
}

$servicos = $stmt->fetchAll();

// Indicadores gerais
$totalServicos = count($servicos);
$totalAtivos = count(array_filter(
    $servicos,
    fn($servico) => (int) $servico['ativo'] === 1
));
$totalInativos = $totalServicos - $totalAtivos;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Serviços | Garage V8</title>

    <link rel="icon" type="image/png" href="img/logogarage.png">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --red: #d90429;
            --red-light: #ef233c;
            --bg: #0b0b0e;
            --panel: #141419;
            --border: #2b2b34;
            --text: #f5f5f7;
            --muted: #92929e;
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
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            min-width: 250px;
            background: #101014;
            border-right: 1px solid var(--border);
            padding: 26px 17px;
            display: flex;
            flex-direction: column;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 3px 9px 28px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 28px;
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
        }

        .brand-name {
            font-size: 19px;
            font-weight: 800;
            letter-spacing: .5px;
            line-height: 1.3;
        }

        .brand-name strong {
            color: var(--red-light);
        }

        .brand-subtitle {
            color: var(--muted);
            font-size: 9px;
            letter-spacing: 1.2px;
        }

        .menu {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .menu a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 14px;
            border-radius: 9px;
            color: #b6b6c1;
            font-size: 13px;
            transition: .2s ease;
        }

        .menu a i {
            width: 18px;
            text-align: center;
            font-size: 15px;
        }

        .menu a:hover {
            background: #1c1c23;
            color: white;
        }

        .menu a.active {
            color: white;
            background: linear-gradient(100deg, #d90429, #a90822);
            box-shadow: 0 5px 18px rgba(217, 4, 41, .15);
        }

        .logout-link {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: auto;
            padding: 14px;
            color: #ff6677;
            font-size: 13px;
            border-top: 1px solid var(--border);
        }

        .main {
            flex: 1;
            min-width: 0;
            padding: 34px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 28px;
        }

        .eyebrow {
            color: var(--red-light);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            margin-bottom: 7px;
        }

        h1 {
            font-size: clamp(24px, 3vw, 31px);
            margin: 0 0 6px;
            font-weight: 700;
        }

        h2 {
            font-size: 17px;
            font-weight: 600;
            margin: 0;
        }

        .muted {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        .header-icon {
            width: 52px;
            height: 52px;
            border: 1px solid #54202a;
            background: #241116;
            color: var(--red-light);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            font-size: 21px;
            flex-shrink: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 17px;
            margin-bottom: 24px;
        }

        .stat-card {
            padding: 21px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 13px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #291117;
            color: var(--red-light);
            border-radius: 11px;
            font-size: 18px;
            flex-shrink: 0;
        }

        .stat-number {
            font-size: 23px;
            font-weight: 700;
            line-height: 1.4;
        }

        .stat-label {
            color: var(--muted);
            font-size: 11px;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 25px;
            margin-bottom: 22px;
        }

        .card-heading {
            display: flex;
            align-items: center;
            gap: 11px;
            margin-bottom: 22px;
        }

        .card-heading > i {
            color: var(--red-light);
            font-size: 17px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            color: #d6d6df;
            font-size: 12px;
            font-weight: 500;
        }

        .field input,
        .field textarea,
        .search-input {
            width: 100%;
            min-width: 0;
            background: #0e0e12;
            color: var(--text);
            border: 1px solid #34343f;
            border-radius: 8px;
            padding: 12px 13px;
            outline: none;
            font-size: 12px;
            transition: border-color .2s, box-shadow .2s;
        }

        .field input:focus,
        .field textarea:focus,
        .search-input:focus {
            border-color: var(--red-light);
            box-shadow: 0 0 0 3px rgba(239, 35, 60, .1);
        }

        .field input::placeholder,
        .field textarea::placeholder,
        .search-input::placeholder {
            color: #696975;
        }

        .field textarea {
            min-height: 95px;
            resize: vertical;
        }

        .full {
            grid-column: 1 / -1;
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 9px;
            background: linear-gradient(100deg, var(--red), #b90826);
            color: white;
            border: 0;
            padding: 12px 17px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s ease;
            white-space: nowrap;
        }

        .btn:hover {
            background: var(--red-light);
            transform: translateY(-1px);
        }

        .btn-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            background: #222229;
            color: #eeeef2;
            border: 1px solid #41414c;
            padding: 8px 10px;
            border-radius: 7px;
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
            transition: .2s ease;
            white-space: nowrap;
        }

        .btn-status:hover {
            border-color: var(--red-light);
            color: #ff8b98;
        }

        .message {
            padding: 13px 16px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ok {
            background: #11291c;
            border: 1px solid #245737;
            color: #a4e8b7;
        }

        .error {
            background: #301217;
            border: 1px solid #69232e;
            color: #ffadb5;
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 0;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 12px;
        }

        .search-input {
            height: 42px;
            padding-left: 38px;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 9px;
        }

        table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px 14px;
            border-bottom: 1px solid #292931;
            text-align: left;
            font-size: 12px;
            vertical-align: middle;
        }

        th {
            color: #bdbdc8;
            background: #19191f;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .7px;
            font-weight: 600;
        }

        td {
            color: #e3e3e9;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        tbody tr:hover {
            background: #19191f;
        }

        .service-name {
            font-weight: 600;
            color: #fff;
        }

        .service-description {
            display: block;
            margin-top: 5px;
            max-width: 270px;
            overflow-wrap: anywhere;
        }

        .price {
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
        }

        .ativo {
            background: #163421;
            border: 1px solid #245737;
            color: #9de5b1;
        }

        .inativo {
            background: #3c3030;
            border: 1px solid #594040;
            color: #ffb5b5;
        }

        .empty-state {
            text-align: center;
            padding: 35px 15px !important;
            color: var(--muted);
        }

        .empty-state i {
            display: block;
            font-size: 25px;
            margin-bottom: 12px;
            color: #656570;
        }

        .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 17px;
        }

        .count-badge {
            color: #f1a0aa;
            background: #291117;
            border: 1px solid #51202a;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            white-space: nowrap;
        }

        @media (max-width: 1050px) {
            .sidebar {
                width: 220px;
                min-width: 220px;
            }

            .main {
                padding: 25px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
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
                padding-bottom: 15px;
                margin-bottom: 15px;
            }

            .menu {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .menu a {
                padding: 11px;
                font-size: 11px;
            }

            .logout-link {
                margin-top: 12px;
                padding-bottom: 5px;
            }

            .main {
                padding: 22px 15px;
            }

            .card {
                padding: 18px;
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

            .header-icon {
                width: 44px;
                height: 44px;
            }

            .table-footer {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
<div class="layout">

    <aside class="sidebar">
        <a href="index.php" class="brand">
            <img src="img/logogarage.png" alt="Logo Garage V8" class="brand-logo">

            <div class="brand-text">
                <span class="brand-name">GARAGE <strong>V8</strong></span>
                <span class="brand-subtitle">GESTÃO AUTOMOTIVA</span>
            </div>
        </a>

        <nav class="menu">
            <a href="index.php">
                <i class="fa-solid fa-house"></i>
                Painel inicial
            </a>

            <a href="clientes.php">
                <i class="fa-solid fa-users"></i>
                Clientes
            </a>

            <a href="veiculos.php">
                <i class="fa-solid fa-car-side"></i>
                Veículos
            </a>

            <a href="servicos.php" class="active">
                <i class="fa-solid fa-screwdriver-wrench"></i>
                Serviços
            </a>

            <a href="atendimentos.php">
                <i class="fa-solid fa-clipboard-list"></i>
                Atendimentos
            </a>

            <a href="pagamentos.php">
                <i class="fa-solid fa-credit-card"></i>
                Pagamentos
            </a>

            <a href="despesas.php">
                <i class="fa-solid fa-arrow-trend-down"></i>
                Despesas
            </a>

            <a href="financeiro.php">
                <i class="fa-solid fa-chart-pie"></i>
                Financeiro
            </a>
        </nav>

        <a href="logout.php" class="logout-link">
            <i class="fa-solid fa-right-from-bracket"></i>
            Sair do sistema
        </a>
    </aside>

    <main class="main">

        <header class="header">
            <div>
                <div class="eyebrow">CATÁLOGO AUTOMOTIVO</div>
                <h1>Serviços</h1>
                <div class="muted">
                    Organize os serviços oferecidos pela Garage V8, com valores de referência e duração estimada.
                </div>
            </div>

            <div class="header-icon">
                <i class="fa-solid fa-screwdriver-wrench"></i>
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

        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fa-solid fa-list-check"></i>
                </div>
                <div>
                    <div class="stat-number"><?= $totalServicos ?></div>
                    <div class="stat-label">Serviços encontrados</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="stat-number"><?= $totalAtivos ?></div>
                    <div class="stat-label">Serviços ativos</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fa-solid fa-circle-pause"></i>
                </div>
                <div>
                    <div class="stat-number"><?= $totalInativos ?></div>
                    <div class="stat-label">Serviços inativos</div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-heading">
                <i class="fa-solid fa-circle-plus"></i>
                <div>
                    <h2>Novo serviço</h2>
                    <div class="muted">
                        Cadastre um serviço no catálogo da empresa.
                    </div>
                </div>
            </div>

            <form method="post">
                <input type="hidden" name="acao" value="cadastrar">

                <div class="form-grid">

                    <label class="field">
                        Nome do serviço *
                        <input
                            name="nome"
                            maxlength="100"
                            required
                            value="<?= e($_POST['nome'] ?? '') ?>"
                            placeholder="Ex.: Polimento técnico"
                        >
                    </label>

                    <label class="field">
                        Categoria
                        <input
                            name="categoria"
                            maxlength="50"
                            value="<?= e($_POST['categoria'] ?? '') ?>"
                            placeholder="Ex.: Estética automotiva"
                        >
                    </label>

                    <label class="field">
                        Valor de referência (R$) *
                        <input
                            type="number"
                            name="valor_referencia"
                            min="0"
                            max="99999999.99"
                            step="0.01"
                            required
                            value="<?= e($_POST['valor_referencia'] ?? '') ?>"
                            placeholder="Ex.: 250.00"
                        >
                    </label>

                    <label class="field">
                        Duração estimada (minutos)
                        <input
                            type="number"
                            name="duracao_estimada"
                            min="1"
                            step="1"
                            value="<?= e($_POST['duracao_estimada'] ?? '') ?>"
                            placeholder="Ex.: 120"
                        >
                    </label>

                    <label class="field full">
                        Descrição
                        <textarea
                            name="descricao"
                            placeholder="Descreva o serviço oferecido..."
                        ><?= e($_POST['descricao'] ?? '') ?></textarea>
                    </label>

                    <div class="full">
                        <button class="btn" type="submit">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Cadastrar serviço
                        </button>
                    </div>

                </div>
            </form>
        </section>

        <section class="card">
            <div class="card-heading">
                <i class="fa-solid fa-list"></i>
                <div>
                    <h2>Catálogo de serviços</h2>
                    <div class="muted">
                        Consulte os serviços e controle sua disponibilidade.
                    </div>
                </div>
            </div>

            <form method="get" class="search-form">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input
                        class="search-input"
                        name="busca"
                        placeholder="Pesquisar por nome ou categoria..."
                        value="<?= e($busca) ?>"
                    >
                </div>

                <button class="btn" type="submit">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Pesquisar
                </button>

                <?php if ($busca !== ''): ?>
                    <a href="servicos.php" class="btn">
                        <i class="fa-solid fa-xmark"></i>
                        Limpar
                    </a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Serviço</th>
                            <th>Categoria</th>
                            <th>Valor</th>
                            <th>Duração</th>
                            <th>Status</th>
                            <th>Ação</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$servicos): ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i class="fa-solid fa-screwdriver-wrench"></i>
                                    Nenhum serviço encontrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($servicos as $servico): ?>
                                <tr>
                                    <td>
                                        <span class="service-name">
                                            <?= e($servico['nome']) ?>
                                        </span>

                                        <?php if (!empty($servico['descricao'])): ?>
                                            <span class="muted service-description">
                                                <?= e($servico['descricao']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?= e($servico['categoria'] ?? '') ?: '—' ?>
                                    </td>

                                    <td class="price">
                                        R$
                                        <?= number_format(
                                            (float) $servico['valor_referencia'],
                                            2,
                                            ',',
                                            '.'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= $servico['duracao_estimada'] !== null
                                            ? (int) $servico['duracao_estimada'] . ' min'
                                            : '—' ?>
                                    </td>

                                    <td>
                                        <span class="badge-status <?= $servico['ativo'] ? 'ativo' : 'inativo' ?>">
                                            <i class="fa-solid <?= $servico['ativo']
                                                ? 'fa-circle-check'
                                                : 'fa-circle-minus' ?>"></i>
                                            <?= $servico['ativo'] ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </td>

                                    <td>
                                        <form method="post">
                                            <input type="hidden" name="acao" value="status">

                                            <input
                                                type="hidden"
                                                name="id_servico"
                                                value="<?= (int) $servico['id_servico'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="novo_status"
                                                value="<?= $servico['ativo'] ? '0' : '1' ?>"
                                            >

                                            <button class="btn-status" type="submit">
                                                <i class="fa-solid <?= $servico['ativo']
                                                    ? 'fa-toggle-off'
                                                    : 'fa-toggle-on' ?>"></i>
                                                <?= $servico['ativo'] ? 'Desativar' : 'Ativar' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="muted">
                    Resultados exibidos para a pesquisa atual.
                </span>

                <span class="count-badge">
                    <?= $totalServicos ?>
                    <?= $totalServicos === 1 ? 'SERVIÇO' : 'SERVIÇOS' ?>
                </span>
            </div>
        </section>

    </main>
</div>

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
