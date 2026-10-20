
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function exigirLogin(): void
{
    if (empty($_SESSION['id_usuario'])) {
        header('Location: login.php');
        exit;
    }
}

function exigirAdmin(): void
{
    exigirLogin();

    if (($_SESSION['nivel_acesso'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Acesso negado. Você não tem permissão para esta operação.');
    }
}
