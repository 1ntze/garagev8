<?php
require_once __DIR__ . '/auth.php';
exigirLogin();
require_once __DIR__ . '/config/conexao.php';

function e($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'cadastrar') {
        $idCliente = filter_input(
            INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT
        );
        $idVeiculo = filter_input(
            INPUT_POST, 'id_veiculo', FILTER_VALIDATE_INT
        );
        $tipo = $_POST['tipo_atendimento'] ?? 'Orçamento';
        $dataAtendimento = trim($_POST['data_atendimento'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');
        $servicosPost = $_POST['servicos'] ?? [];
        $quantidadesPost = $_POST['quantidades'] ?? [];

        $tiposValidos = ['Orçamento', 'Agendamento', 'Realizado'];

        if (!$idCliente || !$idVeiculo) {
            $erro = 'Selecione o cliente e o veículo.';
        } elseif (!in_array($tipo, $tiposValidos, true)) {
            $erro = 'Selecione um tipo de atendimento válido.';
        } elseif (!is_array($servicosPost) || !is_array($quantidadesPost)) {
            $erro = 'Os serviços informados são inválidos.';
        } elseif (count($servicosPost) === 0 || count($servicosPost) > 50) {
            $erro = 'Adicione pelo menos um serviço (máximo de 50).';
        } elseif (strlen($observacoes) > 50000) {
            $erro = 'As observações são muito longas.';
        } else {
            try {
                // Confirma que o veículo pertence ao cliente escolhido.
                $stmt = $pdo->prepare(
                    'SELECT id_veiculo
                     FROM veiculos
                     WHERE id_veiculo = ? AND id_cliente = ?'
                );
                $stmt->execute([$idVeiculo, $idCliente]);

                if (!$stmt->fetch()) {
                    throw new DomainException(
                        'O veículo não pertence ao cliente selecionado.'
                    );
                }

                // Valida e carrega os serviços ativos.
                $itens = [];
                $vistos = [];
                $total = 0.0;

                foreach ($servicosPost as $indice => $idServicoPost) {
                    $idServico = filter_var(
                        $idServicoPost, FILTER_VALIDATE_INT
                    );

                    $quantidadePost = $quantidadesPost[$indice] ?? null;
                    $quantidade = filter_var(
                        $quantidadePost, FILTER_VALIDATE_INT
                    );

                    if (
                        !$idServico ||
                        !$quantidade ||
                        $quantidade < 1 ||
                        $quantidade > 1000 ||
                        isset($vistos[$idServico])
                    ) {
                        throw new DomainException(
                            'Confira os serviços e suas quantidades. Não repita serviços.'
                        );
                    }

                    $vistos[$idServico] = true;

                    $stmt = $pdo->prepare(
                        'SELECT id_servico, nome, valor_referencia
                         FROM servicos
                         WHERE id_servico = ? AND ativo = 1'
                    );
                    $stmt->execute([$idServico]);
                    $servico = $stmt->fetch();

                    if (!$servico) {
                        throw new DomainException(
                            'Um dos serviços não existe ou está inativo.'
                        );
                    }

                    $valorUnitario = (float) $servico['valor_referencia'];
                    $valorItem = round($valorUnitario * $quantidade, 2);
                    $total = round($total + $valorItem, 2);

                    $itens[] = [
                        'id_servico' => $idServico,
                        'quantidade' => $quantidade,
                        'valor_unitario' => $valorUnitario,
                        'valor_total' => $valorItem
                    ];
                }

                // Data recebida do formulário ou data atual.
                if ($dataAtendimento === '') {
                    $dataAtendimento = date('Y-m-d\TH:i');
                }

                $dataObj = DateTimeImmutable::createFromFormat(
                    '!Y-m-d\TH:i', $dataAtendimento
                );

                if (
                    !$dataObj ||
                    $dataObj->format('Y-m-d\TH:i') !== $dataAtendimento
                ) {
                    throw new DomainException(
                        'Informe uma data e hora válidas.'
                    );
                }

                $dataBanco = $dataObj->format('Y-m-d H:i:s');

                // Grava o atendimento e todos os itens em uma transação.
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "INSERT INTO atendimentos
                    (id_cliente, id_veiculo, data_atendimento,
                     tipo_atendimento, status, observacoes,
                     valor_total, cadastrado_por)
                    VALUES (?, ?, ?, ?, 'Pendente', ?, ?, ?)"
                );

                $stmt->execute([
                    $idCliente,
                    $idVeiculo,
                    $dataBanco,
                    $tipo,
                    $observacoes !== '' ? $observacoes : null,
                    number_format($total, 2, '.', ''),
                    1
                ]);

                $idAtendimento = (int) $pdo->lastInsertId();

                $stmtItem = $pdo->prepare(
                    'INSERT INTO itens_atendimento
                    (id_atendimento, id_servico, quantidade,
                     valor_unitario, valor_total, custo_produto,
                     custo_material)
                    VALUES (?, ?, ?, ?, ?, 0, 0)'
                );

                foreach ($itens as $item) {
                    $stmtItem->execute([
                        $idAtendimento,
                        $item['id_servico'],
                        $item['quantidade'],
                        number_format($item['valor_unitario'], 2, '.', ''),
                        number_format($item['valor_total'], 2, '.', '')
                    ]);
                }

                $pdo->commit();

                header(
                    'Location: atendimentos.php?sucesso=1&id=' .
                    $idAtendimento
                );
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
                $erro = 'Não foi possível salvar o atendimento. Confira os dados e tente novamente.';
            }
        }
    }
}

if (isset($_GET['sucesso'])) {
    $sucesso = 'Atendimento #' .
        (int) ($_GET['id'] ?? 0) .
        ' cadastrado com sucesso!';
}

// Carrega clientes, veículos e serviços ativos.
$clientes = $pdo->query(
    'SELECT id_cliente, nome FROM clientes ORDER BY nome'
)->fetchAll();

$veiculos = $pdo->query(
    'SELECT id_veiculo, id_cliente, marca, modelo, placa
     FROM veiculos ORDER BY marca, modelo'
)->fetchAll();

$servicos = $pdo->query(
    'SELECT id_servico, nome, valor_referencia
     FROM servicos WHERE ativo = 1 ORDER BY nome'
)->fetchAll();

// Lista os atendimentos.
$atendimentos = $pdo->query(
    "SELECT a.id_atendimento, a.data_atendimento,
            a.tipo_atendimento, a.status, a.valor_total,
            c.nome AS cliente,
            v.marca, v.modelo, v.placa
     FROM atendimentos a
     INNER JOIN clientes c ON c.id_cliente = a.id_cliente
     INNER JOIN veiculos v ON v.id_veiculo = a.id_veiculo
     ORDER BY a.id_atendimento DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atendimentos | Garage V8</title>
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

        .field input, .field select, .field textarea {
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

        .field textarea { min-height: 85px; resize: vertical; }
        .full { grid-column: 1 / -1; }

        .btn {
            background: #e51b2b;
            color: white;
            border: 0;
            padding: 11px 16px;
            border-radius: 7px;
            cursor: pointer;
        }

        .item-linha {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(90px, 1fr) auto;
            gap: 10px;
            align-items: end;
            margin-bottom: 12px;
        }

        .item-linha input, .item-linha select {
            width: 100%;
            background: #101116;
            color: #f5f5f5;
            border: 1px solid #383945;
            border-radius: 7px;
            padding: 11px;
        }

        .btn-remover {
            background: #30313a;
            color: white;
            border: 1px solid #484955;
            padding: 10px;
            border-radius: 7px;
            cursor: pointer;
        }

        .total {
            font-size: 24px;
            font-weight: bold;
            color: #ff4655;
            margin: 16px 0;
        }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            min-width: 850px;
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

        @media (max-width: 650px) {
            .form-grid { grid-template-columns: 1fr; }
            .full { grid-column: auto; }
            .item-linha { grid-template-columns: 1fr 90px; }
            .item-linha .remover { grid-column: 1 / -1; }
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
            <a href="atendimentos.php" class="active">▤ Atendimentos</a>
            <a href="pagamentos.php">▣ Pagamentos</a>
            <a href="despesas.php">↗ Despesas</a>
            <a href="financeiro.php">▥ Financeiro</a>
        </nav>
        <a href="logout.php" class="logout-link">↪ Sair do sistema</a>
    </aside>

    <main class="main">
        <header class="header">
            <div>
                <h1>Atendimentos</h1>
                <div class="muted">
                    Orçamentos, agendamentos e serviços da oficina.
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
            <h2>Novo atendimento</h2>

            <?php if (!$clientes || !$veiculos || !$servicos): ?>
                <p class="muted">
                    Para criar um atendimento, cadastre pelo menos um cliente,
                    um veículo e um serviço ativo.
                </p>
            <?php else: ?>
                <form method="post" id="form-atendimento">
                    <input type="hidden" name="acao" value="cadastrar">

                    <div class="form-grid">
                        <label class="field">
                            Cliente *
                            <select name="id_cliente" id="cliente" required>
                                <option value="">Selecione</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?= (int) $cliente['id_cliente'] ?>">
                                        <?= e($cliente['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="field">
                            Veículo *
                            <select name="id_veiculo" id="veiculo" required disabled>
                                <option value="">Selecione primeiro o cliente</option>
                            </select>
                        </label>

                        <label class="field">
                            Tipo de atendimento *
                            <select name="tipo_atendimento" required>
                                <option value="Orçamento">Orçamento</option>
                                <option value="Agendamento">Agendamento</option>
                                <option value="Realizado">Realizado</option>
                            </select>
                        </label>

                        <label class="field">
                            Data e hora *
                            <input type="datetime-local" name="data_atendimento"
                                   value="<?= date('Y-m-d\TH:i') ?>" required>
                        </label>

                        <div class="field full">
                            <strong>Serviços do atendimento *</strong>
                            <div id="itens"></div>
                            <button class="btn" type="button" id="adicionar">
                                + Adicionar serviço
                            </button>
                            <div class="total">
                                Total: R$ <span id="total">0,00</span>
                            </div>
                            <div class="muted">
                                O total é calculado com os valores de referência
                                dos serviços selecionados.
                            </div>
                        </div>

                        <label class="field full">
                            Observações
                            <textarea name="observacoes"
                                      placeholder="Detalhes do atendimento"></textarea>
                        </label>

                        <div class="full">
                            <button class="btn" type="submit">
                                Salvar atendimento
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="card" style="margin-top:22px;">
            <h2>Atendimentos registrados</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Número</th>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Veículo</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$atendimentos): ?>
                        <tr>
                            <td colspan="7">Nenhum atendimento cadastrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($atendimentos as $a): ?>
                            <tr>
                                <td>#<?= (int) $a['id_atendimento'] ?></td>
                                <td><?= e(date('d/m/Y H:i', strtotime($a['data_atendimento']))) ?></td>
                                <td><?= e($a['cliente']) ?></td>
                                <td>
                                    <?= e($a['marca'] . ' ' . $a['modelo']) ?>
                                    <?= $a['placa'] ? '<br><small>' . e($a['placa']) . '</small>' : '' ?>
                                </td>
                                <td><?= e($a['tipo_atendimento']) ?></td>
                                <td><?= e($a['status']) ?></td>
                                <td>R$ <?= number_format((float) $a['valor_total'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="muted">
                Total exibido: <?= count($atendimentos) ?> atendimento(s).
            </p>
        </section>
    </main>
</div>

<script>
const clientesSelect = document.getElementById('cliente');
const veiculosSelect = document.getElementById('veiculo');
const itensContainer = document.getElementById('itens');
const totalEl = document.getElementById('total');
const botaoAdicionar = document.getElementById('adicionar');

const veiculos = <?= json_encode(
    array_map(fn($v) => [
        'id' => (int) $v['id_veiculo'],
        'cliente' => (int) $v['id_cliente'],
        'nome' => $v['marca'] . ' ' . $v['modelo'] .
            ($v['placa'] ? ' — ' . $v['placa'] : '')
    ], $veiculos),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?>;

const servicos = <?= json_encode(
    array_map(fn($s) => [
        'id' => (int) $s['id_servico'],
        'nome' => $s['nome'],
        'valor' => (float) $s['valor_referencia']
    ], $servicos),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?>;

function atualizarVeiculos() {
    const idCliente = Number(clientesSelect.value);
    veiculosSelect.replaceChildren();

    const disponiveis = veiculos.filter(v => v.cliente === idCliente);

    const inicial = document.createElement('option');
    inicial.value = '';
    inicial.textContent = disponiveis.length
        ? 'Selecione o veículo'
        : 'Nenhum veículo para este cliente';
    veiculosSelect.appendChild(inicial);
    veiculosSelect.disabled = disponiveis.length === 0;

    disponiveis.forEach(v => {
        const option = document.createElement('option');
        option.value = v.id;
        option.textContent = v.nome;
        veiculosSelect.appendChild(option);
    });
}

function atualizarTotal() {
    let total = 0;

    itensContainer.querySelectorAll('.item-linha').forEach(linha => {
        const seletor = linha.querySelector('.servico');
        const quantidade = Number(linha.querySelector('.quantidade').value) || 0;
        const servico = servicos.find(s => s.id === Number(seletor.value));

        if (servico && quantidade > 0) {
            total += servico.valor * quantidade;
        }
    });

    totalEl.textContent = total.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function atualizarOpcoes() {
    const selecionados = [...itensContainer.querySelectorAll('.servico')]
        .map(el => el.value)
        .filter(Boolean);

    itensContainer.querySelectorAll('.servico').forEach(seletor => {
        const atual = seletor.value;

        [...seletor.options].forEach(opcao => {
            if (!opcao.value) return;
            opcao.disabled = selecionados.includes(opcao.value)
                && opcao.value !== atual;
        });
    });
}

function adicionarServico() {
    if (itensContainer.querySelectorAll('.item-linha').length >= 50) {
        alert('O limite é de 50 serviços por atendimento.');
        return;
    }

    const linha = document.createElement('div');
    linha.className = 'item-linha';

    const campoServico = document.createElement('label');
    campoServico.className = 'field';
    campoServico.append('Serviço');

    const seletor = document.createElement('select');
    seletor.className = 'servico';
    seletor.name = 'servicos[]';
    seletor.required = true;

    const inicial = document.createElement('option');
    inicial.value = '';
    inicial.textContent = 'Selecione um serviço';
    seletor.appendChild(inicial);

    servicos.forEach(s => {
        const option = document.createElement('option');
        option.value = s.id;
        option.textContent = s.nome + ' — R$ ' +
            s.valor.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        seletor.appendChild(option);
    });

    campoServico.appendChild(seletor);

    const campoQuantidade = document.createElement('label');
    campoQuantidade.className = 'field';
    campoQuantidade.append('Quantidade');

    const quantidade = document.createElement('input');
    quantidade.type = 'number';
    quantidade.name = 'quantidades[]';
    quantidade.className = 'quantidade';
    quantidade.min = '1';
    quantidade.max = '1000';
    quantidade.step = '1';
    quantidade.value = '1';
    quantidade.required = true;
    campoQuantidade.appendChild(quantidade);

    const areaRemover = document.createElement('div');
    areaRemover.className = 'remover';

    const remover = document.createElement('button');
    remover.type = 'button';
    remover.className = 'btn-remover';
    remover.textContent = 'Remover';
    remover.addEventListener('click', () => {
        linha.remove();
        atualizarOpcoes();
        atualizarTotal();
    });

    areaRemover.appendChild(remover);
    linha.append(campoServico, campoQuantidade, areaRemover);
    itensContainer.appendChild(linha);

    seletor.addEventListener('change', () => {
        atualizarOpcoes();
        atualizarTotal();
    });

    quantidade.addEventListener('input', atualizarTotal);

    atualizarOpcoes();
    atualizarTotal();
}

clientesSelect?.addEventListener('change', atualizarVeiculos);
botaoAdicionar?.addEventListener('click', adicionarServico);

if (clientesSelect) atualizarVeiculos();
if (itensContainer && servicos.length > 0) adicionarServico();

document.getElementById('form-atendimento')?.addEventListener('submit', event => {
    if (itensContainer.querySelectorAll('.item-linha').length === 0) {
        event.preventDefault();
        alert('Adicione pelo menos um serviço.');
    }
});
</script>
</body>
</html>
