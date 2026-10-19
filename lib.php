<?php
// Funções comuns do Sistema da Carla. Não é acessível diretamente pelo navegador.
if (!defined('CARLA')) { http_response_code(404); exit; }

const COLECOES = ['clientes', 'leads', 'receitas', 'despesas', 'servicos', 'documentos', 'config'];

function pasta_dados(): string {
    static $d = null;
    if ($d !== null) return $d;
    $env = getenv('CARLA_DATA_DIR');
    $raiz = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    // Preferência: pasta FORA do site (acima de public_html), onde ninguém acessa pela web.
    $cands = $env ? [$env] : [dirname($raiz) . '/carla-dados', __DIR__ . '/dados'];
    foreach ($cands as $c) {
        if (!is_dir($c)) @mkdir($c, 0700, true);
        if (is_dir($c) && is_writable($c)) {
            $real = realpath($c);
            $rr = $raiz !== '' ? realpath($raiz) : false;
            if ($rr && $real && strpos($real . '/', $rr . '/') === 0) {
                @file_put_contents($c . '/.htaccess', "Require all denied\nDeny from all\n");
                @file_put_contents($c . '/index.html', '');
            }
            return $d = $c;
        }
    }
    json_out(['ok' => false, 'erro' => 'Não foi possível criar a pasta de dados no servidor.'], 500);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!extension_loaded('pdo_sqlite')) json_out(['ok' => false, 'erro' => 'O servidor não tem SQLite (pdo_sqlite).'], 500);
    $arq = pasta_dados() . '/carla.sqlite';
    $novo = !file_exists($arq);
    $pdo = new PDO('sqlite:' . $arq, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    if ($novo) @chmod($arq, 0600);
    $pdo->exec('PRAGMA busy_timeout=5000');
    $pdo->exec('CREATE TABLE IF NOT EXISTS docs (c TEXT NOT NULL, id TEXT NOT NULL, d TEXT NOT NULL, PRIMARY KEY (c, id))');
    $pdo->exec('CREATE TABLE IF NOT EXISTS ajustes (k TEXT PRIMARY KEY, v TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS tentativas (ip TEXT NOT NULL, ts INTEGER NOT NULL)');
    return $pdo;
}

function ajuste(string $k): ?string {
    $s = db()->prepare('SELECT v FROM ajustes WHERE k = ?');
    $s->execute([$k]);
    $r = $s->fetch();
    return $r ? $r['v'] : null;
}

function set_ajuste(string $k, string $v): void {
    db()->prepare('INSERT OR REPLACE INTO ajustes (k, v) VALUES (?, ?)')->execute([$k, $v]);
}

function json_out(array $a, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function seguranca_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store');
}

function https_ativo(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function iniciar_sessao(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/';
    session_name('carla_sid');
    session_set_cookie_params(['lifetime' => 0, 'path' => $path, 'httponly' => true, 'secure' => https_ativo(), 'samesite' => 'Strict']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
    $agora = time();
    if (isset($_SESSION['ultimo']) && ($agora - $_SESSION['ultimo'] > 8 * 3600 || $agora - ($_SESSION['inicio'] ?? $agora) > 24 * 3600)) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['ultimo'] = $agora;
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
}

function logado(): bool {
    return !empty($_SESSION['user']);
}

function exigir_login(): void {
    if (!logado()) json_out(['ok' => false, 'erro' => 'Sessão expirada. Entre novamente.'], 401);
}

function exigir_csrf(): void {
    $t = $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!is_string($t) || $t === '' || !hash_equals($_SESSION['csrf'] ?? '', $t)) json_out(['ok' => false, 'erro' => 'Requisição recusada. Atualize a página.'], 403);
}

function corpo_json(): array {
    $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($tipo, 'application/json') !== 0) json_out(['ok' => false, 'erro' => 'Formato inválido.'], 415);
    $raw = file_get_contents('php://input', false, null, 0, 8 * 1024 * 1024 + 1);
    if ($raw === false || strlen($raw) > 8 * 1024 * 1024) json_out(['ok' => false, 'erro' => 'Dados grandes demais.'], 413);
    $j = json_decode($raw, true);
    if (!is_array($j)) json_out(['ok' => false, 'erro' => 'JSON inválido.'], 400);
    return $j;
}

function id_valido($id): bool {
    return is_string($id) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) === 1;
}

function doc_valido($d): bool {
    return is_array($d) && strlen(json_encode($d)) <= 65536;
}
