
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

    $obj = DateTimeImmutable::createFromFormat('!Y-m-d', $data);

    return $obj && $obj->format('Y-m-d') === $data;
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

$formasPagamento = [
    'Dinheiro',
    'Pix',
    'Cartão',
    'Transferência',
    'Outro'
];

$statusValidos = ['Paga', 'Pendente', 'Cancelada'];

/*
|--------------------------------------------------------------------------
| CADASTRAR E ALTERAR DESPESAS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirAdmin();

    $acao = $_POST['acao'] ?? '';

    if ($acao === 'cadastrar') {
        $descricao = trim($_POST['descricao'] ?? '');
        $categoria = $_POST['categoria'] ?? '';
        $valorTexto = trim($_POST['valor'] ?? '');
        $dataDespesa = $_POST['data_despesa'] ?? '';
        $dataPagamento = trim($_POST['data_pagamento'] ?? '');
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
            $status === 'Paga' &&
            !dataValida($dataPagamento)
        ) {
            $erro = 'Informe a data do pagamento para uma despesa paga.';
        } elseif (
            $status === 'Paga' &&
            $dataPagamento < $dataDespesa
        ) {
            $erro = 'A data de pagamento não pode ser anterior à despesa.';
        } elseif (!in_array($forma, $formasPagamento, true)) {
            $erro = 'Selecione uma forma de pagamento válida.';
        } elseif (!in_array($status, $statusValidos, true)) {
            $erro = 'Selecione um status válido.';
        } elseif (strlen($fornecedor) > 100) {
            $erro = 'O fornecedor deve ter até 100 caracteres.';
        } elseif (strlen($observacoes) > 50000) {
            $erro = 'As observações são muito longas.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO despesas (
                        descricao,
                        categoria,
                        valor,
                        data_despesa,
                        data_pagamento,
                        forma_pagamento,
                        status,
                        fornecedor,
                        observacoes,
                        cadastrado_por
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
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
                $erro = 'Não foi possível cadastrar a despesa. Confira o banco de dados.';
            }
        }

    } elseif ($acao === 'status') {
        $id = filter_input(
            INPUT_POST,
            'id_despesa',
            FILTER_VALIDATE_INT
        );

        $novoStatus = $_POST['novo_status'] ?? '';

        if (
            !$id ||
            !in_array($novoStatus, $statusValidos, true)
        ) {
            $erro = 'Solicitação de alteração inválida.';
        } else {
            $dataPagamento = trim(
                $_POST['data_pagamento_status'] ?? ''
            );

            if (
                $novoStatus === 'Paga' &&
                !dataValida($dataPagamento)
            ) {
                $erro = 'Informe uma data de pagamento válida.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        'SELECT data_despesa
                         FROM despesas
                         WHERE id_despesa = ?
                         FOR UPDATE'
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
                    $erro = 'Não foi possível alterar o status da despesa.';
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| MENSAGENS
|--------------------------------------------------------------------------
*/

if (($_GET['sucesso'] ?? '') === 'cadastrada') {
    $sucesso = 'Despesa cadastrada com sucesso!';
} elseif (($_GET['sucesso'] ?? '') === 'status') {
    $sucesso = 'Status da despesa atualizado com sucesso!';
}

/*
|--------------------------------------------------------------------------
| PESQUISA
|--------------------------------------------------------------------------
*/

$busca = trim($_GET['busca'] ?? '');

if ($busca !== '') {
    $stmt = $pdo->prepare(
        'SELECT *
         FROM despesas
         WHERE descricao LIKE ?
            OR categoria LIKE ?
            OR fornecedor LIKE ?
         ORDER BY id_despesa DESC'
    );

    $termo = '%' . $busca . '%';
    $stmt->execute([$termo, $termo, $termo]);
} else {
    $stmt = $pdo->query(
        'SELECT *
         FROM despesas
         ORDER BY id_despesa DESC'
    );
}

$despesas = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| INDICADORES FINANCEIROS
|--------------------------------------------------------------------------
*/

$resumo = $pdo->query(
    "SELECT
        COALESCE(SUM(
            CASE WHEN status = 'Paga'
            THEN valor ELSE 0 END
        ), 0) AS total_pago,

        COALESCE(SUM(
            CASE WHEN status = 'Pendente'
            THEN valor ELSE 0 END
        ), 0) AS total_pendente,

        COALESCE(SUM(
            CASE WHEN status = 'Cancelada'
            THEN valor ELSE 0 END
        ), 0) AS total_cancelado,

        COUNT(
            CASE WHEN status = 'Paga' THEN 1 END
        ) AS quantidade_pagas,

        COUNT(
            CASE WHEN status = 'Pendente' THEN 1 END
        ) AS quantidade_pendentes,

        COUNT(
            CASE WHEN status = 'Cancelada' THEN 1 END
        ) AS quantidade_canceladas

     FROM despesas"
)->fetch();

/*
|--------------------------------------------------------------------------
| GRÁFICO MENSAL - ÚLTIMOS 12 MESES
|--------------------------------------------------------------------------
|
| O gráfico agrupa as despesas pela data de registro.
| As despesas canceladas ficam fora das séries de pagas e pendentes.
*/

$inicioGrafico = (new DateTimeImmutable('first day of this month'))
    ->modify('-11 months');

$fimGrafico = new DateTimeImmutable('first day of next month');

$stmtGrafico = $pdo->prepare(
    "SELECT
        DATE_FORMAT(data_despesa, '%Y-%m') AS mes,

        COALESCE(SUM(
            CASE WHEN status = 'Paga'
            THEN valor ELSE 0 END
        ), 0) AS pago,

        COALESCE(SUM(
            CASE WHEN status = 'Pendente'
            THEN valor ELSE 0 END
        ), 0) AS pendente

     FROM despesas

     WHERE data_despesa >= ?
       AND data_despesa < ?

     GROUP BY DATE_FORMAT(data_despesa, '%Y-%m')
     ORDER BY mes"
);

$stmtGrafico->execute([
    $inicioGrafico->format('Y-m-d'),
    $fimGrafico->format('Y-m-d')
]);

$dadosBanco = $stmtGrafico->fetchAll();
$dadosPorMes = [];

foreach ($dadosBanco as $linha) {
    $dadosPorMes[$linha['mes']] = $linha;
}

$nomesMeses = [
    1 => 'Jan',
    2 => 'Fev',
    3 => 'Mar',
    4 => 'Abr',
    5 => 'Mai',
    6 => 'Jun',
    7 => 'Jul',
    8 => 'Ago',
    9 => 'Set',
    10 => 'Out',
    11 => 'Nov',
    12 => 'Dez'
];

$labelsGrafico = [];
$pagasGrafico = [];
$pendentesGrafico = [];

for ($i = 0; $i < 12; $i++) {
    $dataMes = $inicioGrafico->modify("+{$i} months");
    $chave = $dataMes->format('Y-m');

    $labelsGrafico[] =
        $nomesMeses[(int) $dataMes->format('n')] .
        '/' .
        $dataMes->format('y');

    $pagasGrafico[] = isset($dadosPorMes[$chave])
        ? (float) $dadosPorMes[$chave]['pago']
        : 0;

    $pendentesGrafico[] = isset($dadosPorMes[$chave])
        ? (float) $dadosPorMes[$chave]['pendente']
        : 0;
}

$mesAtual = (new DateTimeImmutable('first day of this month'))
    ->format('Y-m');

$pagasMesAtual = isset($dadosPorMes[$mesAtual])
    ? (float) $dadosPorMes[$mesAtual]['pago']
    : 0;

$pendentesMesAtual = isset($dadosPorMes[$mesAtual])
    ? (float) $dadosPorMes[$mesAtual]['pendente']
    : 0;

$totalPeriodoPago = array_sum($pagasGrafico);
$totalPeriodoPendente = array_sum($pendentesGrafico);

$totalDespesas = count($despesas);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Despesas | Garage V8</title>

    <link rel="icon" type="image/png" href="img/logogarage.png">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

        input, select, textarea, button {
            font: inherit;
        }

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
            object-fit: contain;
            flex-shrink: 0;
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

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 23px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            min-width: 0;
            padding: 20px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 13px;
        }

        .stat-card::after {
            position: absolute;
            top: -25px;
            right: -20px;
            width: 85px;
            height: 85px;
            background: rgba(217, 4, 41, .07);
            border-radius: 50%;
            content: "";
        }

        .stat-card.pending::after {
            background: rgba(242, 189, 80, .07);
        }

        .stat-card.cancelled::after {
            background: rgba(150, 150, 160, .07);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 15px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
        }

        .stat-icon {
            display: grid;
            width: 36px;
            height: 36px;
            flex-shrink: 0;
            place-items: center;
            color: var(--red-light);
            background: rgba(217, 4, 41, .12);
            border-radius: 9px;
        }

        .pending .stat-icon {
            color: var(--yellow);
            background: rgba(242, 189, 80, .11);
        }

        .cancelled .stat-icon {
            color: #b4b4bf;
            background: rgba(150, 150, 160, .1);
        }

        .stat-value {
            font-size: clamp(21px, 2vw, 26px);
            font-weight: 700;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .stat-note {
            margin-top: 5px;
            color: var(--muted);
            font-size: 11px;
        }

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
            margin-bottom: 23px;
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

        .chart-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 23px;
        }

        .chart-summary-item {
            padding: 15px 17px;
            background: #0f0f14;
            border: 1px solid var(--border);
            border-radius: 9px;
        }

        .chart-summary-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 11px;
        }

        .chart-dot {
            width: 8px;
            height: 8px;
            flex-shrink: 0;
            border-radius: 50%;
        }

        .chart-summary-value {
            display: block;
            margin-top: 9px;
            font-size: 22px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .chart-container {
            position: relative;
            width: 100%;
            height: 330px;
        }

        .chart-note {
            margin-top: 17px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.8;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 19px;
        }

        .field {
            display: flex;
            min-width: 0;
            flex-direction: column;
            gap: 8px;
            color: #d9d9e1;
            font-size: 12px;
            font-weight: 500;
        }

        .required {
            color: var(--red-light);
        }

        .field input,
        .field select,
        .field textarea,
        .search-input {
            width: 100%;
            min-width: 0;
            padding: 12px 13px;
            color: var(--text);
            background: #0f0f14;
            border: 1px solid #34343e;
            border-radius: 8px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus,
        .search-input:focus {
            border-color: var(--red-light);
            box-shadow: 0 0 0 3px rgba(239, 35, 60, .1);
        }

        .field select option {
            color: var(--text);
            background: #101014;
        }

        .field textarea {
            min-height: 100px;
            resize: vertical;
        }

        .full {
            grid-column: 1 / -1;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            padding: 11px 16px;
            color: white;
            background: var(--red);
            border: 1px solid var(--red);
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: background .2s, transform .2s;
        }

        .btn:hover {
            background: var(--red-light);
            transform: translateY(-1px);
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 34px;
            padding: 8px 10px;
            color: #dedee5;
            background: #202027;
            border: 1px solid #363640;
            border-radius: 7px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 500;
            transition: background .2s;
        }

        .btn-secondary:hover {
            background: #30303a;
        }

        .message {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 20px;
            padding: 14px 16px;
            border: 1px solid transparent;
            border-radius: 9px;
            font-size: 12px;
        }

        .message i {
            margin-top: 2px;
        }

        .message.ok {
            color: #a4edbd;
            background: rgba(34, 139, 76, .13);
            border-color: rgba(80, 216, 144, .25);
        }

        .message.error {
            color: #ffb0b9;
            background: rgba(217, 4, 41, .12);
            border-color: rgba(239, 35, 60, .28);
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-input {
            flex: 1;
            font-size: 12px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        table {
            width: 100%;
            min-width: 1100px;
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

        tbody tr {
            transition: background .2s;
        }

        tbody tr:hover {
            background: #19191f;
        }

        .id-badge {
            display: inline-block;
            padding: 5px 8px;
            color: #ff7888;
            background: rgba(217, 4, 41, .12);
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-paga {
            color: #81e6ab;
            background: rgba(80, 216, 144, .11);
        }

        .status-pendente {
            color: #f2ce7a;
            background: rgba(242, 189, 80, .11);
        }

        .status-cancelada {
            color: #ff9ba6;
            background: rgba(239, 35, 60, .11);
        }

        .status-form {
            display: flex;
            min-width: 155px;
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
        }

        .status-form label {
            display: flex;
            flex-direction: column;
            gap: 5px;
            color: var(--muted);
            font-size: 10px;
        }

        .status-form input[type="date"] {
            width: 100%;
            min-width: 0;
            padding: 8px;
            color: white;
            background: #0f0f14;
            border: 1px solid #34343e;
            border-radius: 7px;
            font-size: 11px;
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

            .stats {
                gap: 11px;
            }

            .stat-card {
                padding: 16px;
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

            .stats {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 17px;
            }

            .panel {
                padding: 17px 14px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .full {
                grid-column: auto;
            }

            .form-grid .full {
                grid-column: 1 / -1;
            }

            .chart-summary {
                grid-template-columns: 1fr;
            }

            .chart-container {
                height: 280px;
            }

            .search-form {
                flex-direction: column;
            }

            .search-form .btn {
                width: 100%;
            }

            .menu {
                grid-template-columns: 1fr 1fr;
            }

            .menu a {
                gap: 8px;
                font-size: 11px;
            }
        }
    </style>
</head>

<body>
<div class="layout">

    <aside class="sidebar">
        <a href="index.php" class="brand">
            <img
                src="img/logogarage.png"
                alt="Logo Garage V8"
                class="brand-logo"
            >

            <div>
                <div class="brand-name">GARAGE <strong>V8</strong></div>
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

            <a href="despesas.php" class="active">
                <i class="fa-solid fa-arrow-trend-down"></i>
                <span>Despesas</span>
            </a>

            <a href="financeiro.php">
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
                <div class="eyebrow">Controle financeiro</div>
                <h1>Despesas</h1>
                <p class="muted">
                    Acompanhe os custos, pagamentos e gastos mensais da oficina.
                </p>
            </div>

            <div class="header-date">
                <i class="fa-regular fa-calendar"></i>
                <?= e(date('d/m/Y')) ?>
            </div>
        </header>

        <?php if ($sucesso): ?>
            <div class="message ok">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= e($sucesso) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="message error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= e($erro) ?></span>
            </div>
        <?php endif; ?>

        <!-- INDICADORES -->

        <section class="stats">

            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Despesas pagas</span>
                    <span class="stat-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </span>
                </div>

                <div class="stat-value">
                    R$ <?= dinheiro($resumo['total_pago']) ?>
                </div>

                <div class="stat-note">
                    <?= (int) $resumo['quantidade_pagas'] ?>
                    despesa(s) paga(s)
                </div>
            </article>

            <article class="stat-card pending">
                <div class="stat-top">
                    <span class="stat-label">Despesas pendentes</span>
                    <span class="stat-icon">
                        <i class="fa-regular fa-clock"></i>
                    </span>
                </div>

                <div class="stat-value">
                    R$ <?= dinheiro($resumo['total_pendente']) ?>
                </div>

                <div class="stat-note">
                    <?= (int) $resumo['quantidade_pendentes'] ?>
                    despesa(s) aguardando pagamento
                </div>
            </article>

            <article class="stat-card cancelled">
                <div class="stat-top">
                    <span class="stat-label">Despesas canceladas</span>
                    <span class="stat-icon">
                        <i class="fa-solid fa-ban"></i>
                    </span>
                </div>

                <div class="stat-value">
                    R$ <?= dinheiro($resumo['total_cancelado']) ?>
                </div>

                <div class="stat-note">
                    <?= (int) $resumo['quantidade_canceladas'] ?>
                    despesa(s) cancelada(s)
                </div>
            </article>

        </section>

        <!-- GRÁFICO MENSAL -->

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2 class="panel-title">
                        <i class="fa-solid fa-chart-column"></i>
                        Análise mensal de despesas
                    </h2>

                    <p class="panel-description">
                        Compare os valores pagos e pendentes ao longo dos últimos 12 meses.
                    </p>
                </div>
            </div>

            <div class="chart-summary">

                <div class="chart-summary-item">
                    <div class="chart-summary-label">
                        <span class="chart-dot" style="background:#d90429"></span>
                        Pagas no mês atual
                    </div>

                    <strong class="chart-summary-value">
                        R$ <?= dinheiro($pagasMesAtual) ?>
                    </strong>
                </div>

                <div class="chart-summary-item">
                    <div class="chart-summary-label">
                        <span class="chart-dot" style="background:#f2bd50"></span>
                        Pendentes no mês atual
                    </div>

                    <strong class="chart-summary-value">
                        R$ <?= dinheiro($pendentesMesAtual) ?>
                    </strong>
                </div>

            </div>

            <div class="chart-container">
                <canvas
                    id="graficoDespesas"
                    role="img"
                    aria-label="Gráfico mensal de despesas pagas e pendentes"
                ></canvas>
            </div>

            <p class="chart-note">
                <i class="fa-solid fa-circle-info"></i>
                O gráfico utiliza a data da despesa para agrupar os valores por mês.
                Despesas canceladas não entram nas séries de pagas e pendentes.
                Os meses sem registros são exibidos com valor zero.
            </p>
        </section>

        <!-- CADASTRO -->

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2 class="panel-title">
                        <i class="fa-solid fa-plus-circle"></i>
                        Cadastrar despesa
                    </h2>

                    <p class="panel-description">
                        Registre os custos e compromissos financeiros da oficina.
                    </p>
                </div>
            </div>

            <form method="post">
                <input type="hidden" name="acao" value="cadastrar">

                <div class="form-grid">

                    <label class="field">
                        Descrição <span class="required">*</span>
                        <input
                            type="text"
                            name="descricao"
                            maxlength="100"
                            placeholder="Ex.: Compra de produtos"
                            required
                        >
                    </label>

                    <label class="field">
                        Categoria <span class="required">*</span>
                        <select name="categoria" required>
                            <option value="">Selecione uma categoria</option>

                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= e($categoria) ?>">
                                    <?= e($categoria) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="field">
                        Valor (R$) <span class="required">*</span>
                        <input
                            type="number"
                            name="valor"
                            min="0.01"
                            max="99999999.99"
                            step="0.01"
                            placeholder="Ex.: 150.00"
                            required
                        >
                    </label>

                    <label class="field">
                        Fornecedor
                        <input
                            type="text"
                            name="fornecedor"
                            maxlength="100"
                            placeholder="Nome do fornecedor"
                        >
                    </label>

                    <label class="field">
                        Data da despesa <span class="required">*</span>
                        <input
                            type="date"
                            name="data_despesa"
                            value="<?= e(date('Y-m-d')) ?>"
                            required
                        >
                    </label>

                    <label class="field">
                        Forma de pagamento <span class="required">*</span>
                        <select name="forma_pagamento" required>
                            <option value="">Selecione a forma</option>

                            <?php foreach ($formasPagamento as $forma): ?>
                                <option value="<?= e($forma) ?>">
                                    <?= e($forma) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="field">
                        Status <span class="required">*</span>
                        <select name="status" id="status-despesa" required>
                            <option value="Pendente">Pendente</option>
                            <option value="Paga">Paga</option>
                            <option value="Cancelada">Cancelada</option>
                        </select>
                    </label>

                    <label class="field" id="campo-data-pagamento" style="display:none">
                        Data do pagamento <span class="required">*</span>
                        <input
                            type="date"
                            name="data_pagamento"
                            value="<?= e(date('Y-m-d')) ?>"
                        >
                    </label>

                    <label class="field full">
                        Observações
                        <textarea
                            name="observacoes"
                            maxlength="50000"
                            placeholder="Detalhes adicionais sobre a despesa..."
                        ></textarea>
                    </label>

                    <div class="full">
                        <button class="btn" type="submit">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Cadastrar despesa
                        </button>
                    </div>

                </div>
            </form>
        </section>

        <!-- LISTAGEM -->

        <section class="panel">
            <div class="panel-heading">
                <div>
                    <h2 class="panel-title">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        Despesas cadastradas
                    </h2>

                    <p class="panel-description">
                        Consulte os lançamentos e gerencie os respectivos status.
                    </p>
                </div>
            </div>

            <form method="get" class="search-form">
                <input
                    class="search-input"
                    type="search"
                    name="busca"
                    placeholder="Pesquisar descrição, categoria ou fornecedor..."
                    value="<?= e($busca) ?>"
                >

                <button class="btn" type="submit">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Pesquisar
                </button>

                <?php if ($busca !== ''): ?>
                    <a href="despesas.php" class="btn-secondary">
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
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th>Fornecedor</th>
                        <th>Valor</th>
                        <th>Data da despesa</th>
                        <th>Pagamento</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if (!$despesas): ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <i class="fa-solid fa-receipt"></i>

                                    <?= $busca !== ''
                                        ? 'Nenhuma despesa encontrada para essa pesquisa.'
                                        : 'Nenhuma despesa cadastrada até o momento.' ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>

                        <?php foreach ($despesas as $d): ?>
                            <?php
                            $statusClasse = match ($d['status']) {
                                'Paga' => 'status-paga',
                                'Pendente' => 'status-pendente',
                                'Cancelada' => 'status-cancelada',
                                default => ''
                            };
                            ?>

                            <tr>
                                <td>
                                    <span class="id-badge">
                                        #<?= (int) $d['id_despesa'] ?>
                                    </span>
                                </td>

                                <td>
                                    <strong><?= e($d['descricao']) ?></strong>

                                    <?php if (!empty($d['observacoes'])): ?>
                                        <br>
                                        <span class="muted">
                                            <?= e($d['observacoes']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td><?= e($d['categoria']) ?></td>

                                <td><?= e($d['fornecedor'] ?? '—') ?></td>

                                <td>
                                    <strong>
                                        R$ <?= dinheiro($d['valor']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= e(date(
                                        'd/m/Y',
                                        strtotime($d['data_despesa'])
                                    )) ?>
                                </td>

                                <td>
                                    <?= !empty($d['data_pagamento'])
                                        ? e(date(
                                            'd/m/Y',
                                            strtotime($d['data_pagamento'])
                                        ))
                                        : '—' ?>

                                    <br>

                                    <span class="muted">
                                        <?= e($d['forma_pagamento']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="status <?= e($statusClasse) ?>">
                                        <?php if ($d['status'] === 'Paga'): ?>
                                            <i class="fa-solid fa-circle-check"></i>
                                        <?php elseif ($d['status'] === 'Pendente'): ?>
                                            <i class="fa-regular fa-clock"></i>
                                        <?php else: ?>
                                            <i class="fa-solid fa-circle-xmark"></i>
                                        <?php endif; ?>

                                        <?= e($d['status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($d['status'] !== 'Cancelada'): ?>
                                        <form method="post" class="status-form">
                                            <input
                                                type="hidden"
                                                name="acao"
                                                value="status"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_despesa"
                                                value="<?= (int) $d['id_despesa'] ?>"
                                            >

                                            <?php if ($d['status'] === 'Pendente'): ?>
                                                <label>
                                                    Data do pagamento
                                                    <input
                                                        type="date"
                                                        name="data_pagamento_status"
                                                        value="<?= e(date('Y-m-d')) ?>"
                                                        required
                                                    >
                                                </label>

                                                <button
                                                    class="btn-secondary"
                                                    type="submit"
                                                    name="novo_status"
                                                    value="Paga"
                                                >
                                                    <i class="fa-solid fa-check"></i>
                                                    Marcar como paga
                                                </button>

                                                <button
                                                    class="btn-secondary"
                                                    type="submit"
                                                    name="novo_status"
                                                    value="Cancelada"
                                                    onclick="return confirm('Deseja cancelar esta despesa?')"
                                                >
                                                    <i class="fa-solid fa-ban"></i>
                                                    Cancelar despesa
                                                </button>

                                            <?php else: ?>

                                                <button
                                                    class="btn-secondary"
                                                    type="submit"
                                                    name="novo_status"
                                                    value="Pendente"
                                                    onclick="return confirm('Deseja voltar esta despesa para pendente?')"
                                                >
                                                    <i class="fa-solid fa-rotate-left"></i>
                                                    Reabrir despesa
                                                </button>

                                            <?php endif; ?>
                                        </form>

                                    <?php else: ?>
                                        <span class="muted">
                                            Sem ações disponíveis
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span>
                    Total exibido: <strong><?= $totalDespesas ?></strong>
                    despesa(s)
                </span>

                <?php if ($busca !== ''): ?>
                    <span>Pesquisa: "<?= e($busca) ?>"</span>
                <?php endif; ?>
            </div>
        </section>

    </main>
</div>

<script>
/*
|--------------------------------------------------------------------------
| CAMPO DE DATA DE PAGAMENTO
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| GRÁFICO DE DESPESAS MENSAIS
|--------------------------------------------------------------------------
*/

const labelsDespesas = <?= json_encode(
    $labelsGrafico,
    JSON_UNESCAPED_UNICODE |
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
) ?>;

const valoresPagos = <?= json_encode(
    $pagasGrafico,
    JSON_NUMERIC_CHECK
) ?>;

const valoresPendentes = <?= json_encode(
    $pendentesGrafico,
    JSON_NUMERIC_CHECK
) ?>;

const canvasDespesas = document.getElementById('graficoDespesas');

if (canvasDespesas && typeof Chart !== 'undefined') {
    new Chart(canvasDespesas.getContext('2d'), {
        type: 'bar',

        data: {
            labels: labelsDespesas,

            datasets: [
                {
                    label: 'Despesas pagas',
                    data: valoresPagos,

                    backgroundColor: 'rgba(217, 4, 41, 0.82)',
                    hoverBackgroundColor: '#ef233c',

                    borderColor: '#d90429',
                    borderWidth: 1,
                    borderRadius: 5,
                    borderSkipped: false,
                    maxBarThickness: 30
                },

                {
                    label: 'Despesas pendentes',
                    data: valoresPendentes,

                    backgroundColor: 'rgba(242, 189, 80, 0.78)',
                    hoverBackgroundColor: '#f2bd50',

                    borderColor: '#f2bd50',
                    borderWidth: 1,
                    borderRadius: 5,
                    borderSkipped: false,
                    maxBarThickness: 30
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
                    position: 'bottom',

                    labels: {
                        color: '#b7b7c3',
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 22,
                        boxWidth: 8,
                        boxHeight: 8,

                        font: {
                            family: 'Poppins',
                            size: 11
                        }
                    }
                },

                tooltip: {
                    backgroundColor: '#1b1b22',
                    titleColor: '#ffffff',
                    bodyColor: '#ededf2',
                    borderColor: '#34343e',
                    borderWidth: 1,
                    padding: 12,

                    callbacks: {
                        label: function(context) {
                            const valor = Number(context.raw || 0);

                            return context.dataset.label + ': ' +
                                valor.toLocaleString('pt-BR', {
                                    style: 'currency',
                                    currency: 'BRL'
                                });
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
                        color: '#92929e',
                        maxRotation: 0,
                        autoSkip: false,

                        font: {
                            family: 'Poppins',
                            size: 10
                        }
                    },

                    border: {
                        color: '#2b2b34'
                    }
                },

                y: {
                    beginAtZero: true,

                    grid: {
                        color: 'rgba(255,255,255,0.055)'
                    },

                    ticks: {
                        color: '#92929e',
                        padding: 10,

                        callback: function(value) {
                            return Number(value).toLocaleString('pt-BR', {
                                style: 'currency',
                                currency: 'BRL',
                                maximumFractionDigits: 0
                            });
                        },

                        font: {
                            family: 'Poppins',
                            size: 10
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

</body>
</html>
