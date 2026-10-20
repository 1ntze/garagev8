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

$categorias = [
    'Produtos e materiais',
    'Equipamentos',
    'Aluguel',
    'Energia elétrica',
    'Água',
    'Internet e telefone',
    'Manutenção',
    'Marketing',
    'Impostos e taxas',
    'Outros'
];

$formas = ['Dinheiro', 'Pix', 'Cartão', 'Transferência', 'Outro'];
$statuses = ['Paga', 'Pendente', 'Cancelada'];

function dataValida($data) {
    if ($data === '') {
        return false;
    }

    $obj = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

    return $obj && $obj->format('Y-m-d') === $data;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirAdmin();

    $acao = $_POST['acao'] ?? '';

    if ($acao === 'cadastrar') {
        $descricao = trim($_POST['descricao'] ?? '');
        $categoria = $_POST['categoria'] ?? '';
        $valorTexto = trim($_POST['valor'] ?? '');
        $dataDespesa = $_POST['data_despesa'] ?? '';
        $dataPagamento = $_POST['data_pagamento'] ?? '';
        $forma = $_POST['forma_pagamento'] ?? '';
        $status = $_POST['status'] ?? '';
        $fornecedor = trim($_POST['fornecedor'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');

        $valorValido = preg_match(
            '/^\d{1,8}(\.\d{1,2})?$/',
            $valorTexto
        );

        if ($descricao === '') {
            $erro = 'Informe a descrição da despesa.';
        } elseif (strlen($descricao) > 100) {
            $erro = 'A descrição deve ter até 100 caracteres.';
        } elseif (!in_array($categoria, $categorias, true)) {
            $erro = 'Selecione uma categoria válida.';
        } elseif (!$valorValido || (float) $valorTexto <= 0) {
            $erro = 'Informe um valor positivo, com até duas casas decimais.';
        } elseif (!dataValida($dataDespesa)) {
            $erro = 'Informe uma data de despesa válida.';
        } elseif (
            $dataPagamento !== '' &&
            !dataValida($dataPagamento)
        ) {
            $erro = 'Informe uma data de pagamento válida.';
        } elseif (
            $status === 'Paga' && $dataPagamento === ''
        ) {
            $erro = 'Informe a data do pagamento para uma despesa paga.';
        } elseif (
            $dataPagamento !== '' &&
            $dataPagamento < $dataDespesa
        ) {
            $erro = 'A data de pagamento não pode ser anterior à despesa.';
        } elseif (!in_array($forma, $formas, true)) {
            $erro = 'Selecione uma forma de pagamento válida.';
        } elseif (!in_array($status, $statuses, true)) {
            $erro = 'Selecione um status válido.';
        } elseif (strlen($fornecedor) > 100) {
            $erro = 'O fornecedor deve ter até 100 caracteres.';
        } elseif (strlen($observacoes) > 50000) {
            $erro = 'As observações são muito longas.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO despesas
                    (descricao, categoria, valor, data_despesa,
                     data_pagamento, forma_pagamento, status,
                     fornecedor, observacoes, cadastrado_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $descricao,
                    $categoria,
                    number_format((float) $valorTexto, 2, '.', ''),
                    $dataDespesa,
                    $status === 'Paga' ? $dataPagamento : null,
                    $forma,
                    $status,
                    $fornecedor !== '' ? $fornecedor : null,
                    $observacoes !== '' ? $observacoes : null,
                    $_SESSION['id_usuario']
                ]);

                header('Location: despesas.php?sucesso=cadastrada');
                exit;
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $erro = 'Não foi possível cadastrar a despesa. Verifique o banco de dados.';
            }
        }
    } elseif ($acao === 'status') {
        $id = filter_input(
            INPUT_POST, 'id_despesa', FILTER_VALIDATE_INT
        );
        $novoStatus = $_POST['novo_status'] ?? '';

        if (
            !$id ||
            !in_array($novoStatus, ['Paga', 'Pendente', 'Cancelada'], true)
        ) {
            $erro = 'Solicitação de alteração inválida.';
        } else {
            $dataPagamento = trim($_POST['data_pagamento_status'] ?? '');

            if (
                $novoStatus === 'Paga' &&
                !dataValida($dataPagamento)
            ) {
                $erro = 'Informe a data do pagamento para marcar a despesa como paga.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        'SELECT data_despesa FROM despesas
                         WHERE id_despesa = ? FOR UPDATE'
                    );
                    $stmt->execute([$id]);
                    $despesa = $stmt->fetch();

                    if (!$despesa) {
                        throw new DomainException(
                            'Despesa não encontrada.'
                        );
                    }

                    if (
                        $novoStatus === 'Paga' &&
                        $dataPagamento < $despesa['data_despesa']
                    ) {
                        throw new DomainException(
                            'A data de pagamento não pode ser anterior à despesa.'
                        );
                    }

                    $stmt = $pdo->prepare(
                        'UPDATE despesas
                         SET status = ?, data_pagamento = ?
                         WHERE id_despesa = ?'
                    );

                    $stmt->execute([
                        $novoStatus,
                        $novoStatus === 'Paga' ? $dataPagamento : null,
                        $id
                    ]);

                    $pdo->commit();

                    header('Location: despesas.php?sucesso=status');
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
                    $erro = 'Não foi possível alterar o status.';
                }
            }
        }
    }
}

if (($_GET['sucesso'] ?? '') === 'cadastrada') {
    $sucesso = 'Despesa cadastrada com sucesso!';
} elseif (($_GET['sucesso'] ?? '') === 'status') {
    $sucesso = 'Status da despesa atualizado!';
}

// Pesquisa por descrição, categoria ou fornecedor.
$busca = trim($_GET['busca'] ?? '');

$sql = 'SELECT * FROM despesas';

if ($busca !== '') {
    $sql .= ' WHERE descricao LIKE ?
              OR categoria LIKE ?
              OR fornecedor LIKE ?';

    $stmt = $pdo->prepare($sql . ' ORDER BY id_despesa DESC');
    $termo = '%' . $busca . '%';
    $stmt->execute([$termo, $termo, $termo]);
} else {
    $stmt = $pdo->query($sql . ' ORDER BY id_despesa DESC');
}

$despesas = $stmt->fetchAll();

// Indicadores: valores efetivamente pagos e pendentes.
$resumo = $pdo->query(
    "SELECT
        COALESCE(SUM(
            CASE WHEN status = 'Paga' THEN valor ELSE 0 END
        ), 0) AS total_pago,
        COALESCE(SUM(
            CASE WHEN status = 'Pendente' THEN valor ELSE 0 END
        ), 0) AS total_pendente,
        COALESCE(SUM(
            CASE WHEN status = 'Cancelada' THEN valor ELSE 0 END
        ), 0) AS total_cancelado
     FROM despesas"
)->fetch();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Despesas | Garage V8</title>
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

        .field input, .field select, .field textarea,
        .search-input {
            width: 100%;
            background: #101116;
            color: #f5f5f5;
            border: 1px solid #383945;
            border-radius: 7px;
            padding: 12px;
        }

        .field select option { background: #101116; }
        .field textarea { min-height: 85px; resize: vertical; }
        .full { grid-column: 1 / -1; }

        .btn {
            background: #e51b2b;
            color: white;
            border: 0;
            padding: 10px 15px;
            border-radius: 7px;
            cursor: pointer;
        }

        .btn-secondary {
            background: #30313a;
            color: white;
            border: 1px solid #484955;
            padding: 7px 10px;
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
            min-width: 1050px;
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

        .paga { background: #163421; color: #9de5b1; }
        .pendente { background: #44391d; color: #f6d98b; }
        .cancelada { background: #3c3030; color: #ffb5b5; }

        .status-form {
            display: flex;
            flex-direction: column;
            gap: 7px;
            min-width: 150px;
        }

        @media (max-width: 750px) {
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
            <a href="pagamentos.php">▣ Pagamentos</a>
            <a href="despesas.php" class="active">↗ Despesas</a>
            <a href="financeiro.php">▥ Financeiro</a>
        </nav>
        <a href="logout.php" class="logout-link">↪ Sair do sistema</a>
    </aside>

    <main class="main">
        <header class="header">
            <div>
                <h1>Despesas</h1>
                <div class="muted">
                    Controle os gastos da oficina.
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
                <div class="muted">Despesas pagas</div>
                <div class="valor">
                    R$ <?= dinheiro($resumo['total_pago']) ?>
                </div>
            </div>

            <div class="finance-card">
                <div class="muted">Despesas pendentes</div>
                <div class="valor">
                    R$ <?= dinheiro($resumo['total_pendente']) ?>
                </div>
            </div>

            <div class="finance-card">
                <div class="muted">Despesas canceladas</div>
                <div class="valor">
                    R$ <?= dinheiro($resumo['total_cancelado']) ?>
                </div>
            </div>
        </div>

        <section class="card">
            <h2>Nova despesa</h2>

            <form method="post">
                <input type="hidden" name="acao" value="cadastrar">

                <div class="form-grid">
                    <label class="field">
                        Descrição *
                        <input name="descricao" maxlength="100" required
                               placeholder="Ex.: Compra de produtos">
                    </label>

                    <label class="field">
                        Categoria *
                        <select name="categoria" required>
                            <option value="">Selecione</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= e($categoria) ?>">
                                    <?= e($categoria) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="field">
                        Valor (R$) *
                        <input type="number" name="valor"
                               min="0.01" max="99999999.99"
                               step="0.01" placeholder="Ex.: 150.00" required>
                    </label>

                    <label class="field">
                        Fornecedor
                        <input name="fornecedor" maxlength="100"
                               placeholder="Nome do fornecedor">
                    </label>

                    <label class="field">
                        Data da despesa *
                        <input type="date" name="data_despesa"
                               value="<?= date('Y-m-d') ?>" required>
                    </label>

                    <label class="field">
                        Forma de pagamento *
                        <select name="forma_pagamento" required>
                            <option value="">Selecione</option>
                            <?php foreach ($formas as $forma): ?>
                                <option value="<?= e($forma) ?>">
                                    <?= e($forma) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="field">
                        Status *
                        <select name="status" id="status-despesa" required>
                            <option value="Pendente">Pendente</option>
                            <option value="Paga">Paga</option>
                            <option value="Cancelada">Cancelada</option>
                        </select>
                    </label>

                    <label class="field" id="campo-data-pagamento">
                        Data do pagamento
                        <input type="date" name="data_pagamento"
                               value="<?= date('Y-m-d') ?>">
                    </label>

                    <label class="field full">
                        Observações
                        <textarea name="observacoes"
                                  placeholder="Detalhes adicionais"></textarea>
                    </label>

                    <div class="full">
                        <button class="btn" type="submit">
                            Cadastrar despesa
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <section class="card" style="margin-top:22px;">
            <h2>Despesas cadastradas</h2>

            <form method="get"
                  style="display:flex; gap:10px; margin-bottom:18px;">
                <input class="search-input" name="busca"
                       placeholder="Pesquisar descrição, categoria ou fornecedor"
                       value="<?= e($busca) ?>">
                <button class="btn" type="submit">Pesquisar</button>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th>Fornecedor</th>
                        <th>Valor</th>
                        <th>Data da despesa</th>
                        <th>Pagamento</th>
                        <th>Status</th>
                        <th>Ação</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$despesas): ?>
                        <tr>
                            <td colspan="9">Nenhuma despesa encontrada.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($despesas as $d): ?>
                            <tr>
                                <td>#<?= (int) $d['id_despesa'] ?></td>
                                <td>
                                    <strong><?= e($d['descricao']) ?></strong>
                                    <?php if (!empty($d['observacoes'])): ?>
                                        <br><small class="muted">
                                            <?= e($d['observacoes']) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($d['categoria']) ?></td>
                                <td><?= e($d['fornecedor'] ?? '') ?></td>
                                <td>R$ <?= dinheiro($d['valor']) ?></td>
                                <td><?= e(date('d/m/Y', strtotime($d['data_despesa']))) ?></td>
                                <td>
                                    <?= !empty($d['data_pagamento'])
                                        ? e(date('d/m/Y', strtotime($d['data_pagamento'])))
                                        : '—' ?>
                                    <br><small class="muted">
                                        <?= e($d['forma_pagamento']) ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge-status <?= strtolower(
                                        iconv('UTF-8', 'ASCII//TRANSLIT', $d['status'])
                                    ) ?>">
                                        <?= e($d['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($d['status'] !== 'Cancelada'): ?>
                                        <form method="post" class="status-form">
                                            <input type="hidden" name="acao" value="status">
                                            <input type="hidden" name="id_despesa"
                                                   value="<?= (int) $d['id_despesa'] ?>">

                                            <?php if ($d['status'] === 'Pendente'): ?>
                                                <input type="hidden" name="novo_status" value="Paga">
                                                <label class="muted">
                                                    Data do pagamento
                                                    <input type="date"
                                                           name="data_pagamento_status"
                                                           value="<?= date('Y-m-d') ?>"
                                                           required>
                                                </label>
                                                <button class="btn-secondary" type="submit">
                                                    Marcar como paga
                                                </button>
                                                <button class="btn-secondary"
                                                        type="submit"
                                                        name="novo_status"
                                                        value="Cancelada"
                                                        onclick="return confirm('Deseja cancelar esta despesa?')">
                                                    Cancelar despesa
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="novo_status"
                                                       value="Pendente">
                                                <button class="btn-secondary"
                                                        type="submit"
                                                        onclick="return confirm('Voltar esta despesa para pendente?')">
                                                    Reabrir
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted">Sem ações</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <p class="muted">
                Total exibido: <?= count($despesas) ?> despesa(s).
            </p>
        </section>
    </main>
</div>

<script>
const statusDespesa = document.getElementById('status-despesa');
const campoData = document.getElementById('campo-data-pagamento');

function atualizarCampoData() {
    if (!statusDespesa || !campoData) return;

    const input = campoData.querySelector('input');
    const paga = statusDespesa.value === 'Paga';

    campoData.style.display = paga ? 'flex' : 'none';
    input.required = paga;
}

statusDespesa?.addEventListener('change', atualizarCampoData);
atualizarCampoData();
</script>
</body>
</html>
