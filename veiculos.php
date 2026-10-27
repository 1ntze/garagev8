
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

// Cadastrar veículo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idCliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $anoTexto = trim($_POST['ano'] ?? '');
    $placa = strtoupper(trim($_POST['placa'] ?? ''));
    $cor = trim($_POST['cor'] ?? '');
    $observacoes = trim($_POST['observacoes'] ?? '');

    $ano = null;

    if ($anoTexto !== '') {
        $ano = filter_var($anoTexto, FILTER_VALIDATE_INT);
    }

    if (!$idCliente || $marca === '' || $modelo === '') {
        $erro = 'Selecione um cliente e informe a marca e o modelo.';
    } elseif (
        $anoTexto !== '' &&
        ($ano === false || $ano < 1900 || $ano > ((int) date('Y') + 1))
    ) {
        $erro = 'Informe um ano válido para o veículo.';
    } elseif (strlen($marca) > 50 || strlen($modelo) > 50) {
        $erro = 'A marca e o modelo devem ter no máximo 50 caracteres.';
    } elseif (strlen($placa) > 10 || strlen($cor) > 30) {
        $erro = 'Confira o tamanho da placa ou da cor informada.';
    } else {
        $verifica = $pdo->prepare(
            'SELECT id_cliente FROM clientes WHERE id_cliente = ?'
        );
        $verifica->execute([$idCliente]);

        if (!$verifica->fetch()) {
            $erro = 'O cliente selecionado não foi encontrado.';
        } else {
            try {
                $sql = "INSERT INTO veiculos
                    (id_cliente, marca, modelo, ano, placa, cor, observacoes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $idCliente,
                    $marca,
                    $modelo,
                    $ano ?: null,
                    $placa !== '' ? $placa : null,
                    $cor !== '' ? $cor : null,
                    $observacoes !== '' ? $observacoes : null
                ]);

                header('Location: veiculos.php?sucesso=1');
                exit;
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $erro = 'Não foi possível cadastrar o veículo.';
            }
        }
    }
}

if (isset($_GET['sucesso'])) {
    $sucesso = 'Veículo cadastrado com sucesso!';
}

// Clientes para o campo de seleção
$stmtClientes = $pdo->query(
    'SELECT id_cliente, nome FROM clientes ORDER BY nome'
);
$clientes = $stmtClientes->fetchAll();

// Pesquisa de veículos
$busca = trim($_GET['busca'] ?? '');

$sql = "SELECT
            v.id_veiculo,
            v.marca,
            v.modelo,
            v.ano,
            v.placa,
            v.cor,
            c.nome AS nome_cliente
        FROM veiculos v
        INNER JOIN clientes c ON c.id_cliente = v.id_cliente";

if ($busca !== '') {
    $sql .= " WHERE v.marca LIKE ?
              OR v.modelo LIKE ?
              OR v.placa LIKE ?
              OR c.nome LIKE ?";

    $stmt = $pdo->prepare($sql . ' ORDER BY v.id_veiculo DESC');
    $termo = '%' . $busca . '%';
    $stmt->execute([$termo, $termo, $termo, $termo]);
} else {
    $stmt = $pdo->query($sql . ' ORDER BY v.id_veiculo DESC');
}

$veiculos = $stmt->fetchAll();
$totalVeiculos = count($veiculos);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Veículos | Garage V8</title>

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
            --panel-light: #19191f;
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

        input, select, textarea, button {
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
            margin-top: 30px;
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

        .card-heading i {
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
        .field select,
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
        .field select:focus,
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

        .field select option {
            background: #101014;
            color: white;
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
            padding: 12px 18px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s ease;
        }

        .btn:hover {
            background: var(--red-light);
            transform: translateY(-1px);
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
            border-collapse: collapse;
            min-width: 750px;
        }

        th, td {
            padding: 15px 14px;
            border-bottom: 1px solid #292931;
            text-align: left;
            font-size: 12px;
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

        .vehicle-name {
            font-weight: 600;
        }

        .vehicle-id {
            color: var(--muted);
        }

        .plate {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 5px;
            border: 1px solid #42424d;
            background: #202027;
            color: #f4f4f7;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .6px;
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

            .stats-grid {
                grid-template-columns: 1fr;
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

            <a href="veiculos.php" class="active">
                <i class="fa-solid fa-car-side"></i>
                Veículos
            </a>

            <a href="servicos.php">
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
                <div class="eyebrow">CADASTRO E CONTROLE</div>
                <h1>Veículos</h1>
                <div class="muted">
                    Gerencie os veículos e mantenha cada um vinculado ao seu proprietário.
                </div>
            </div>

            <div class="header-icon">
                <i class="fa-solid fa-car-side"></i>
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
                    <i class="fa-solid fa-car"></i>
                </div>
                <div>
                    <div class="stat-number"><?= $totalVeiculos ?></div>
                    <div class="stat-label">
                        Veículo(s) encontrado(s)
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fa-solid fa-user-group"></i>
                </div>
                <div>
                    <div class="stat-number"><?= count($clientes) ?></div>
                    <div class="stat-label">
                        Cliente(s) disponível(is) para vínculo
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <div>
                    <div class="stat-number">
                        <?= $busca !== '' ? 'Ativa' : 'Livre' ?>
                    </div>
                    <div class="stat-label">
                        Situação da pesquisa
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-heading">
                <i class="fa-solid fa-circle-plus"></i>
                <div>
                    <h2>Novo veículo</h2>
                    <div class="muted">
                        Preencha os dados e selecione o proprietário.
                    </div>
                </div>
            </div>

            <?php if (!$clientes): ?>
                <div class="message error">
                    <i class="fa-solid fa-circle-info"></i>
                    Cadastre um cliente antes de adicionar um veículo.
                </div>

                <a href="clientes.php" class="btn">
                    <i class="fa-solid fa-user-plus"></i>
                    Ir para cadastro de clientes
                </a>
            <?php else: ?>

                <form method="post">
                    <div class="form-grid">

                        <label class="field full">
                            Proprietário *
                            <select name="id_cliente" required>
                                <option value="">Selecione um cliente</option>

                                <?php foreach ($clientes as $cliente): ?>
                                    <option
                                        value="<?= (int) $cliente['id_cliente'] ?>"
                                        <?= (string) ($_POST['id_cliente'] ?? '') ===
                                            (string) $cliente['id_cliente'] ? 'selected' : '' ?>
                                    >
                                        <?= e($cliente['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="field">
                            Marca *
                            <input
                                name="marca"
                                maxlength="50"
                                value="<?= e($_POST['marca'] ?? '') ?>"
                                placeholder="Ex.: Chevrolet"
                                required
                            >
                        </label>

                        <label class="field">
                            Modelo *
                            <input
                                name="modelo"
                                maxlength="50"
                                value="<?= e($_POST['modelo'] ?? '') ?>"
                                placeholder="Ex.: Onix"
                                required
                            >
                        </label>

                        <label class="field">
                            Ano
                            <input
                                type="number"
                                name="ano"
                                min="1900"
                                max="<?= (int) date('Y') + 1 ?>"
                                value="<?= e($_POST['ano'] ?? '') ?>"
                                placeholder="Ex.: 2022"
                            >
                        </label>

                        <label class="field">
                            Placa
                            <input
                                name="placa"
                                maxlength="10"
                                value="<?= e($_POST['placa'] ?? '') ?>"
                                placeholder="Ex.: ABC1D23"
                            >
                        </label>

                        <label class="field">
                            Cor
                            <input
                                name="cor"
                                maxlength="30"
                                value="<?= e($_POST['cor'] ?? '') ?>"
                                placeholder="Ex.: Preto"
                            >
                        </label>

                        <label class="field full">
                            Observações
                            <textarea
                                name="observacoes"
                                placeholder="Informações adicionais sobre o veículo..."
                            ><?= e($_POST['observacoes'] ?? '') ?></textarea>
                        </label>

                        <div class="full">
                            <button class="btn" type="submit">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Cadastrar veículo
                            </button>
                        </div>

                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card-heading">
                <i class="fa-solid fa-list"></i>
                <div>
                    <h2>Veículos cadastrados</h2>
                    <div class="muted">
                        Consulte os veículos registrados e seus respectivos proprietários.
                    </div>
                </div>
            </div>

            <form method="get" class="search-form">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input
                        class="search-input"
                        name="busca"
                        placeholder="Pesquisar por marca, modelo, placa ou proprietário..."
                        value="<?= e($busca) ?>"
                    >
                </div>

                <button class="btn" type="submit">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Pesquisar
                </button>

                <?php if ($busca !== ''): ?>
                    <a href="veiculos.php" class="btn">
                        <i class="fa-solid fa-xmark"></i>
                        Limpar
                    </a>
                <?php endif; ?>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Proprietário</th>
                            <th>Veículo</th>
                            <th>Ano</th>
                            <th>Placa</th>
                            <th>Cor</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$veiculos): ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i class="fa-solid fa-car-side"></i>
                                    Nenhum veículo encontrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($veiculos as $veiculo): ?>
                                <tr>
                                    <td class="vehicle-id">
                                        #<?= (int) $veiculo['id_veiculo'] ?>
                                    </td>

                                    <td>
                                        <?= e($veiculo['nome_cliente']) ?>
                                    </td>

                                    <td class="vehicle-name">
                                        <?= e($veiculo['marca']) ?>
                                        <?= e($veiculo['modelo']) ?>
                                    </td>

                                    <td>
                                        <?= e($veiculo['ano'] ?? '') ?: '—' ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($veiculo['placa'])): ?>
                                            <span class="plate">
                                                <?= e($veiculo['placa']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="muted">Não informada</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?= e($veiculo['cor'] ?? '') ?: '—' ?>
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
                    <?= $totalVeiculos ?>
                    <?= $totalVeiculos === 1 ? 'VEÍCULO' : 'VEÍCULOS' ?>
                </span>
            </div>
        </section>

    </main>
</div>
</body>
</html>
