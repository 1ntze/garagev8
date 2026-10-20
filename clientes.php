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

function e($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clientes | Garage V8</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        input, textarea, button {
            font: inherit;
        }

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

        .field input, .field textarea, .search-input {
            width: 100%;
            background: #101116;
            color: #f5f5f5;
            border: 1px solid #383945;
            border-radius: 7px;
            padding: 12px;
        }

        .field textarea {
            min-height: 90px;
            resize: vertical;
        }

        .full {
            grid-column: 1 / -1;
        }

        .btn {
            background: #e51b2b;
            color: white;
            border: 0;
            padding: 12px 18px;
            border-radius: 7px;
            cursor: pointer;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #30313a;
            text-align: left;
        }

        th {
            color: #ff4655;
        }

        .message {
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 16px;
        }

        .ok { background: #163421; color: #9de5b1; }
        .error { background: #421a20; color: #ffadb5; }

        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
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
            <a href="clientes.php" class="active">♙ Clientes</a>
            <a href="veiculos.php">🚘 Veículos</a>
            <a href="servicos.php">🔧 Serviços</a>
            <a href="atendimentos.php">▤ Atendimentos</a>
            <a href="pagamentos.php">▣ Pagamentos</a>
            <a href="despesas.php">↗ Despesas</a>
            <a href="financeiro.php">▥ Financeiro</a>
        </nav>
        <a href="logout.php" class="logout-link">↪ Sair do sistema</a>
    </aside>

    <main class="main">
        <header class="header">
            <div>
                <h1>Clientes</h1>
                <div class="muted">Cadastre e consulte os clientes da oficina.</div>
            </div>
        </header>

        <?php if ($sucesso): ?>
            <div class="message ok"><?= e($sucesso) ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="message error"><?= e($erro) ?></div>
        <?php endif; ?>

        <section class="card">
            <h2>Novo cliente</h2>

            <form method="post">
                <div class="form-grid">
                    <label class="field full">
                        Nome completo *
                        <input name="nome" maxlength="100" required>
                    </label>

                    <label class="field">
                        Telefone
                        <input name="telefone" maxlength="20">
                    </label>

                    <label class="field">
                        E-mail
                        <input type="email" name="email" maxlength="200">
                    </label>

                    <label class="field full">
                        Observações
                        <textarea name="observacoes"></textarea>
                    </label>

                    <div class="full">
                        <button class="btn" type="submit">Cadastrar cliente</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="card" style="margin-top: 22px;">
            <h2>Clientes cadastrados</h2>

            <form method="get" style="display:flex; gap:10px; margin-bottom:18px;">
                <input
                    class="search-input"
                    name="busca"
                    placeholder="Pesquisar por nome, telefone ou e-mail"
                    value="<?= e($busca) ?>"
                >
                <button class="btn" type="submit">Pesquisar</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>E-mail</th>
                            <th>Cadastro</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$clientes): ?>
                        <tr>
                            <td colspan="5">Nenhum cliente encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td><?= (int) $cliente['id_cliente'] ?></td>
                                <td><?= e($cliente['nome']) ?></td>
                                <td><?= e($cliente['telefone']) ?></td>
                                <td><?= e($cliente['email']) ?></td>
                                <td><?= e($cliente['data_cadastro']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <p class="muted">
                Total exibido: <?= count($clientes) ?> cliente(s).
            </p>
        </section>
    </main>
</div>
</body>
</html>
