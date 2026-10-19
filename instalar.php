<?php
// Instalação única: cria o banco e define o usuário e a senha de acesso.
// Depois de instalado, esta página não faz mais nada.
define('CARLA', 1);
require __DIR__ . '/lib.php';
seguranca_headers();
header('Content-Type: text/html; charset=utf-8');
iniciar_sessao();

function pagina(string $corpo): void {
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Instalação</title>'
       . '<style>body{font-family:system-ui,sans-serif;background:#F3F5F2;color:#18282A;margin:0;padding:24px}main{max-width:420px;margin:8vh auto;background:#fff;border:1px solid #DDE3E0;border-radius:12px;padding:24px}'
       . 'h1{font-size:20px;margin:0 0 8px}label{display:block;font-size:14px;margin:14px 0 4px}input{width:100%;box-sizing:border-box;padding:10px;font-size:16px;border:1px solid #ccd5d1;border-radius:8px}'
       . 'button{margin-top:18px;width:100%;padding:12px;font-size:16px;background:#0E6B5B;color:#fff;border:0;border-radius:8px;cursor:pointer}.e{color:#B03A2E;font-size:14px;margin-top:10px}p{font-size:14px;color:#5E6F70}a{color:#0E6B5B}</style></head><body><main>'
       . $corpo . '</main></body></html>';
    exit;
}

if (ajuste('admin_hash') !== null) pagina('<h1>Já instalado</h1><p>O sistema já está configurado. <a href="./">Abrir o sistema</a></p>');

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['usuario'] ?? '');
    $s = $_POST['senha'] ?? '';
    $s2 = $_POST['senha2'] ?? '';
    if (!hash_equals($_SESSION['csrf'], $_POST['t'] ?? '')) $erro = 'Atualize a página e tente de novo.';
    elseif (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $u)) $erro = 'Usuário: 3 a 32 caracteres (letras, números, ponto, traço).';
    elseif (mb_strlen($s) < 10) $erro = 'A senha precisa ter pelo menos 10 caracteres.';
    elseif ($s !== $s2) $erro = 'As senhas não conferem.';
    else {
        set_ajuste('admin_user', $u);
        set_ajuste('admin_hash', password_hash($s, PASSWORD_DEFAULT));
        pagina('<h1>Pronto</h1><p>Instalação concluída. <a href="./">Entrar no sistema</a></p>');
    }
}
db(); // cria o banco e a pasta de dados
pagina('<h1>Instalar o Sistema da Carla</h1><p>Escolha o usuário e uma senha forte. Esta tela só funciona uma vez.</p>'
    . '<form method="post" autocomplete="off"><input type="hidden" name="t" value="' . htmlspecialchars($_SESSION['csrf']) . '">'
    . '<label>Usuário</label><input name="usuario" required autocapitalize="off">'
    . '<label>Senha (mínimo 10 caracteres)</label><input name="senha" type="password" required minlength="10">'
    . '<label>Repita a senha</label><input name="senha2" type="password" required minlength="10">'
    . ($erro ? '<div class="e">' . htmlspecialchars($erro) . '</div>' : '')
    . '<button type="submit">Instalar</button></form>');
