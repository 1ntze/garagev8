<?php
require_once __DIR__ . '/auth.php';
exigirLogin();

require_once __DIR__ . '/config/conexao.php';

$erro = '';
$sucesso = '';

function e($valor) {
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
        // Confirma que o cliente existe
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Veículos | Garage V8</title>
    <link rel="stylesheet" href="assets/style.css">

    <style>
        input, select, textarea, button {
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

        .field input, .field select, .field textarea,
        .search-input {
            width: 100%;
            background: #101116;
            color: #f5f5f5;
            border: 1px solid #383945;
            border-radius: 7px;
            padding: 12px;
        }

        .field select option {
            background: #101116;
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
            min-width: 720px;
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

        .ok {
            background: #163421;
            color: #9de5b1;
        }

        .error {
            background: #421a20;
            color: #ffadb5;
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .full {
                grid-column: auto;
            }
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
                <a href="veiculos.php" class="active">🚘 Veículos</a>
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
                <h1>Veículos</h1>
                <div class="muted">
                    Cadastre veículos e associe-os aos clientes.
                </div>
            </div>
        </header>

        <?php if ($sucesso): ?>
            <div class="message ok"><?= e($sucesso) ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="message error"><?= e($erro) ?></div>
        <?php endif; ?>

        <section class="card">
            <h2>Novo veículo</h2>

            <?php if (!$clientes): ?>
                <p class="muted">
                    Cadastre um cliente antes de adicionar um veículo.
                </p>
                <a href="clientes.php">Ir para cadastro de clientes</a>
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
                            <input name="marca" maxlength="50"
                                   value="<?= e($_POST['marca'] ?? '') ?>"
                                   placeholder="Ex.: Chevrolet" required>
                        </label>

                        <label class="field">
                            Modelo *
                            <input name="modelo" maxlength="50"
                                   value="<?= e($_POST['modelo'] ?? '') ?>"
                                   placeholder="Ex.: Onix" required>
                        </label>

                        <label class="field">
                            Ano
                            <input type="number" name="ano"
                                   min="1900"
                                   max="<?= (int) date('Y') + 1 ?>"
                                   value="<?= e($_POST['ano'] ?? '') ?>"
                                   placeholder="Ex.: 2022">
                        </label>

                        <label class="field">
                            Placa
                            <input name="placa" maxlength="10"
                                   value="<?= e($_POST['placa'] ?? '') ?>"
                                   placeholder="Ex.: ABC1D23">
                        </label>

                        <label class="field">
                            Cor
                            <input name="cor" maxlength="30"
                                   value="<?= e($_POST['cor'] ?? '') ?>"
                                   placeholder="Ex.: Preto">
                        </label>

                        <label class="field full">
                            Observações
                            <textarea name="observacoes"><?= e($_POST['observacoes'] ?? '') ?></textarea>
                        </label>

                        <div class="full">
                            <button class="btn" type="submit">
                                Cadastrar veículo
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="card" style="margin-top: 22px;">
            <h2>Veículos cadastrados</h2>

            <form method="get"
                  style="display:flex; gap:10px; margin-bottom:18px;">
                <input class="search-input"
                       name="busca"
                       placeholder="Pesquisar por marca, modelo, placa ou proprietário"
                       value="<?= e($busca) ?>">
                <button class="btn" type="submit">Pesquisar</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Proprietário</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Ano</th>
                        <th>Placa</th>
                        <th>Cor</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$veiculos): ?>
                        <tr>
                            <td colspan="7">Nenhum veículo encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($veiculos as $veiculo): ?>
                            <tr>
                                <td><?= (int) $veiculo['id_veiculo'] ?></td>
                                <td><?= e($veiculo['nome_cliente']) ?></td>
                                <td><?= e($veiculo['marca']) ?></td>
                                <td><?= e($veiculo['modelo']) ?></td>
                                <td><?= e($veiculo['ano'] ?? '') ?></td>
                                <td><?= e($veiculo['placa'] ?? '') ?></td>
                                <td><?= e($veiculo['cor'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <p class="muted">
                Total exibido: <?= count($veiculos) ?> veículo(s).
            </p>
        </section>
    </main>
</div>
</body>
</html>
