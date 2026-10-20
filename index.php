<?php
require_once __DIR__ . '/auth.php';
exigirLogin();

require_once __DIR__ . '/config/conexao.php';

$stmt = $pdo->query(
    "SELECT COUNT(*) AS total FROM usuarios"
);
$totalUsuarios = (int) $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Garage V8 | Painel</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="layout">

    <aside class="sidebar">
        <div class="logo">GARAGE V8</div>
        <div class="subtitle">GESTÃO AUTOMOTIVA</div>

       <nav class="menu">
            <a href="index.php" class="active">⌂ Painel inicial</a>
    <a href="clientes.php">♙ Clientes</a>
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
                <h1>Painel inicial</h1>
                <div class="muted">
                    Bem-vindo ao sistema Garage V8.
                </div>
            </div>

            <div class="accent">● Sistema conectado</div>
        </header>

        <section class="cards">
            <div class="card">
                <h3>Usuários cadastrados</h3>
                <div class="number">
                    <?= $totalUsuarios ?>
                </div>
                <div class="status">Banco de dados conectado</div>
            </div>

            <div class="card">
                <h3>Clientes cadastrados</h3>
                <div class="number">—</div>
                <div class="muted">Próximo módulo</div>
            </div>

            <div class="card">
                <h3>Atendimentos</h3>
                <div class="number">—</div>
                <div class="muted">Próximo módulo</div>
            </div>
        </section>

        <section class="card" style="margin-top: 22px;">
            <h2>Garage V8</h2>
            <p class="muted">
                Seu sistema de atendimento e controle financeiro
                está sendo desenvolvido.
            </p>
        </section>
    </main>

</div>
</body>
</html>
