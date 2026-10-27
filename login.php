
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
    <meta name="theme-color" content="#101010">

    <title>Login | Garage V8</title>

    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/png" href="img/logogarage.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #101010;
            color: #f5f5f5;
            font-family: 'Poppins', sans-serif;
        }

        .login-page {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
        }

        /* Painel visual */

        .login-visual {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 48px;
            overflow: hidden;
            background:
                linear-gradient(
                    0deg,
                    rgba(0, 0, 0, .95) 0%,
                    rgba(0, 0, 0, 0.81) 55%,
                    rgba(0, 0, 0, 0.65) 100%
                ),
                url('img/index/maverick.png') center / cover no-repeat,
                #171717;
        }

        .login-visual::after {
            content: "";
            position: absolute;
            inset: 0;
            border-right: 1px solid #292929;
            pointer-events: none;
        }

        .brand {
            position: relative;
            z-index: 1;
        }

        .brand-name {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #fff;
        }

        .brand-name span {
            color: #d90429;
        }

        .brand-caption {
            margin: 5px 0 0;
            color: #b5b5b5;
            font-size: 11px;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .visual-content {
            position: relative;
            z-index: 1;
            max-width: 460px;
            padding-bottom: 25px;
        }

        .visual-tag {
            display: inline-block;
            margin-bottom: 16px;
            color: #ef233c;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 2px;
        }

        .visual-content h1 {
            margin: 0 0 16px;
            font-size: clamp(32px, 4vw, 54px);
            line-height: 1.12;
            font-weight: 700;
        }

        .visual-content h1 span {
            color: #ef233c;
        }

        .visual-content p {
            max-width: 380px;
            margin: 0;
            color: #bdbdbd;
            font-size: 14px;
            line-height: 1.9;
        }

        .visual-footer {
            position: relative;
            z-index: 1;
            color: #777;
            font-size: 11px;
        }

        /* Painel do formulário */

        .login-content {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: #101010;
        }

        .login-box {
            width: 100%;
            max-width: 390px;
        }

        .mobile-brand {
            display: none;
        }

        .login-heading {
            margin-bottom: 32px;
        }

        .login-heading .eyebrow {
            display: block;
            margin-bottom: 12px;
            color: #ef233c;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .login-heading h2 {
            margin: 0 0 10px;
            color: #fff;
            font-size: 30px;
            font-weight: 600;
            letter-spacing: -1px;
        }

        .login-heading p {
            margin: 0;
            color: #929292;
            font-size: 13px;
            line-height: 1.8;
        }

        .erro {
            margin-bottom: 22px;
            padding: 13px 15px;
            border: 1px solid #76212d;
            border-radius: 8px;
            background: #281519;
            color: #ffadb5;
            font-size: 12px;
            line-height: 1.6;
        }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 9px;
            margin-bottom: 22px;
            color: #dedede;
            font-size: 12px;
            font-weight: 500;
        }

        .campo input {
            width: 100%;
            min-height: 49px;
            padding: 0 15px;
            border: 1px solid #333;
            border-radius: 7px;
            outline: none;
            background: #171717;
            color: #fff;
            font: inherit;
            font-size: 13px;
            transition: border-color .2s, background .2s;
        }

        .campo input::placeholder {
            color: #666;
        }

        .campo input:focus {
            border-color: #d90429;
            background: #1b1b1b;
        }

        .botao {
            width: 100%;
            min-height: 50px;
            margin-top: 5px;
            border: 1px solid #d90429;
            border-radius: 7px;
            background: #d90429;
            color: #fff;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s, border-color .2s;
        }

        .botao:hover {
            background: #b90322;
            border-color: #b90322;
        }

        .botao:focus-visible {
            outline: 2px solid #fff;
            outline-offset: 3px;
        }

        .login-security {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 25px;
            color: #777;
            font-size: 11px;
            text-align: center;
        }

        .security-dot {
            width: 6px;
            height: 6px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #d90429;
        }

        @media (max-width: 800px) {
            .login-page {
                grid-template-columns: 1fr;
            }

            .login-visual {
                display: none;
            }

            .login-content {
                padding: 35px 24px;
            }

            .login-box {
                max-width: 420px;
            }

            .mobile-brand {
                display: block;
                margin-bottom: 55px;
            }

            .login-heading h2 {
                font-size: 28px;
            }
        }

        @media (max-width: 380px) {
            .login-content {
                padding: 28px 19px;
            }

            .mobile-brand {
                margin-bottom: 42px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition: none !important;
            }
        }

        
.botao-voltar {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    margin-top: 12px;
    padding: 12px 18px;
    color: #f5f5f7;
    background: transparent;
    border: 1px solid #2b2b34;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.25s ease;
}

.botao-voltar:hover {
    color: #fff;
    background: #1a1a21;
    border-color: #d90429;
}

.botao-voltar i {
    color: #ef233c;
}

    </style>
</head>

<body>
    <main class="login-page">

        <section class="login-visual" aria-label="Garage V8">
            <div class="brand">
                <p class="brand-name">GARAGE <span>V8</span></p>
                <p class="brand-caption">Estética automotiva</p>
            </div>

            <div class="visual-content">
                <span class="visual-tag">SISTEMA DE GESTÃO</span>

                <h1>
                    Controle total.<br>
                    <span>Mais desempenho.</span>
                </h1>

                <p>
                    Gerencie atendimentos, clientes, veículos e despesas
                    em um só lugar. Mais organização para o dia a dia
                    da Garage V8.
                </p>
            </div>

            <div class="visual-footer">
                GARAGE V8 &copy; <?= date('Y') ?> — TAUBATÉ, SP
            </div>
        </section>

        <section class="login-content">
            <div class="login-box">

                <div class="mobile-brand">
                    <p class="brand-name">GARAGE <span>V8</span></p>
                    <p class="brand-caption">Estética automotiva</p>
                </div>

                <header class="login-heading">
                    <span class="eyebrow">Acesso administrativo</span>
                    <h2>Bem-vindo de volta.</h2>
                    <p>
                        Entre com suas credenciais para acessar
                        o painel de gestão.
                    </p>
                </header>

                <?php if ($erro !== ''): ?>
                    <div class="erro" role="alert">
                        <?= htmlspecialchars(
                            $erro,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="login.php">
                    <label class="campo">
                        Usuário

                        <input
                            type="text"
                            name="usuario"
                            maxlength="50"
                            placeholder="Digite seu usuário"
                            autocomplete="username"
                            autocapitalize="none"
                            spellcheck="false"
                            required
                            autofocus
                        >
                    </label>

                    <label class="campo">
                        Senha

                        <input
                            type="password"
                            name="senha"
                            placeholder="Digite sua senha"
                            autocomplete="current-password"
                            required
                        >
                    </label>

                            <button class="botao" type="submit">
                         Entrar no sistema
                    </button>
                </form>

                

                    <div class="login-security">
              <span class="security-dot"></span>
          Acesso restrito ao administrador
            </div>
                <a href="inicio.html" class="botao-voltar">
                 <i class="fa-solid fa-arrow-left"></i>
                         Voltar para a página inicial
                    </a>

            </div>
        </section>

    </main>
</body>
</html>

