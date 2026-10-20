
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config/conexao.php';

if (!empty($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($usuario === '' || $senha === '') {
        $erro = 'Informe seu usuário e sua senha.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id_usuario, nome, usuario, senha, nivel_acesso
             FROM usuarios
             WHERE usuario = ?
             LIMIT 1'
        );

        $stmt->execute([$usuario]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dados && password_verify($senha, $dados['senha'])) {
            session_regenerate_id(true);

            $_SESSION['id_usuario'] = (int) $dados['id_usuario'];
            $_SESSION['nome_usuario'] = $dados['nome'];
            $_SESSION['usuario'] = $dados['usuario'];
            $_SESSION['nivel_acesso'] = $dados['nivel_acesso'];

            $atualizar = $pdo->prepare(
                'UPDATE usuarios
                 SET ultimo_login = NOW()
                 WHERE id_usuario = ?'
            );
            $atualizar->execute([$dados['id_usuario']]);

            header('Location: index.php');
            exit;
        }

        $erro = 'Usuário ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Garage V8</title>
    <link rel="stylesheet" href="assets/style.css">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #101116;
        }

        .login-box {
            width: 100%;
            max-width: 420px;
            padding: 32px;
            border: 1px solid #30313a;
            border-radius: 14px;
            background: #191a22;
        }

        .login-box h1 {
            color: #ef233c;
            margin-bottom: 8px;
        }

        .login-box p {
            color: #a9adba;
            margin-bottom: 24px;
        }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 18px;
        }

        .campo input {
            width: 100%;
            padding: 13px;
            border: 1px solid #383945;
            border-radius: 7px;
            background: #101116;
            color: white;
        }

        .botao {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 7px;
            background: #e51b2b;
            color: white;
            cursor: pointer;
            font-size: 16px;
        }

        .erro {
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 7px;
            background: #421a20;
            color: #ffadb5;
        }

        .marca {
            text-align: center;
            margin-bottom: 28px;
        }
    </style>
</head>
<body>
    <main class="login-box">
        <div class="marca">
            <h1>GARAGE V8</h1>
            <p>Gestão automotiva</p>
        </div>

        <h2>Entrar no sistema</h2>
        <p>Informe seus dados de acesso.</p>

        <?php if ($erro !== ''): ?>
            <div class="erro"><?= htmlspecialchars(
                $erro, ENT_QUOTES, 'UTF-8'
            ) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <label class="campo">
                Usuário
                <input
                    type="text"
                    name="usuario"
                    maxlength="50"
                    autocomplete="username"
                    required
                >
            </label>

            <label class="campo">
                Senha
                <input
                    type="password"
                    name="senha"
                    autocomplete="current-password"
                    required
                >
            </label>

            <button class="botao" type="submit">
                Entrar
            </button>
        </form>
    </main>
</body>
</html>
