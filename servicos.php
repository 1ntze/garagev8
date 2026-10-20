<?php

require_once __DIR__ . '/auth.php';
exigirLogin();

require_once __DIR__ . '/config/conexao.php';

$erro = '';
$sucesso = '';

function e($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// Cadastrar ou alterar o status de um serviço
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Serviços | Garage V8</title>
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
            padding: 10px 15px;
            border-radius: 7px;
            cursor: pointer;
        }

        .btn-status {
            background: #30313a;
            color: #fff;
            border: 1px solid #484955;
            padding: 8px 10px;
            border-radius: 7px;
            cursor: pointer;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 780px;
            border-collapse: collapse;
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

        .badge-status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 15px;
            font-size: 12px;
        }

        .ativo {
            background: #163421;
            color: #9de5b1;
        }

        .inativo {
            background: #3c3030;
            color: #ffb5b5;
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
            <a href="veiculos.php">🚘 Veículos</a>
            <a href="servicos.php" class="active">🔧 Serviços</a>
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
                <h1>Serviços</h1>
                <div class="muted">
                    Gerencie o catálogo de serviços da oficina.
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
            <h2>Novo serviço</h2>

            <form method="post">
                <input type="hidden" name="acao" value="cadastrar">

                <div class="form-grid">
                    <label class="field">
                        Nome do serviço *
                        <input name="nome" maxlength="100" required
                               value="<?= e($_POST['nome'] ?? '') ?>"
                               placeholder="Ex.: Polimento técnico">
                    </label>

                    <label class="field">
                        Categoria
                        <input name="categoria" maxlength="50"
                               value="<?= e($_POST['categoria'] ?? '') ?>"
                               placeholder="Ex.: Estética automotiva">
                    </label>

                    <label class="field">
                        Valor de referência (R$) *
                        <input type="number" name="valor_referencia"
                               min="0" max="99999999.99" step="0.01"
                               required
                               value="<?= e($_POST['valor_referencia'] ?? '') ?>"
                               placeholder="Ex.: 250.00">
                    </label>

                    <label class="field">
                        Duração estimada (minutos)
                        <input type="number" name="duracao_estimada"
                               min="1" step="1"
                               value="<?= e($_POST['duracao_estimada'] ?? '') ?>"
                               placeholder="Ex.: 120">
                    </label>

                    <label class="field full">
                        Descrição
                        <textarea name="descricao"
                                  placeholder="Descreva o serviço oferecido"><?= e($_POST['descricao'] ?? '') ?></textarea>
                    </label>

                    <div class="full">
                        <button class="btn" type="submit">
                            Cadastrar serviço
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <section class="card" style="margin-top: 22px;">
            <h2>Catálogo de serviços</h2>

            <form method="get"
                  style="display:flex; gap:10px; margin-bottom:18px;">
                <input class="search-input"
                       name="busca"
                       placeholder="Pesquisar por nome ou categoria"
                       value="<?= e($busca) ?>">
                <button class="btn" type="submit">Pesquisar</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Nome</th>
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
                            <td colspan="6">Nenhum serviço encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($servicos as $servico): ?>
                            <tr>
                                <td>
                                    <strong><?= e($servico['nome']) ?></strong>
                                    <?php if ($servico['descricao']): ?>
                                        <br>
                                        <small class="muted">
                                            <?= e($servico['descricao']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($servico['categoria']) ?></td>
                                <td>
                                    R$ <?= number_format(
                                        (float) $servico['valor_referencia'],
                                        2, ',', '.'
                                    ) ?>
                                </td>
                                <td>
                                    <?= $servico['duracao_estimada'] !== null
                                        ? (int) $servico['duracao_estimada'] . ' min'
                                        : '—' ?>
                                </td>
                                <td>
                                    <span class="badge-status <?= $servico['ativo'] ? 'ativo' : 'inativo' ?>">
                                        <?= $servico['ativo'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="acao" value="status">
                                        <input type="hidden" name="id_servico"
                                               value="<?= (int) $servico['id_servico'] ?>">
                                        <input type="hidden" name="novo_status"
                                               value="<?= $servico['ativo'] ? '0' : '1' ?>">
                                        <button class="btn-status" type="submit">
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

            <p class="muted">
                Total exibido: <?= count($servicos) ?> serviço(s).
            </p>
        </section>
    </main>
</div>
</body>
</html>
