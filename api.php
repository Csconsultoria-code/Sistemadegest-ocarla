<?php
// API do Sistema da Carla: login com sessão no servidor e dados em SQLite.
define('CARLA', 1);
require __DIR__ . '/lib.php';
seguranca_headers();
iniciar_sessao();

$a = $_GET['a'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'];

if ($a === 'me' && $metodo === 'GET') {
    json_out(['ok' => true, 'auth' => logado(), 'csrf' => $_SESSION['csrf'], 'instalado' => ajuste('admin_hash') !== null]);
}

if ($a === 'login' && $metodo === 'POST') {
    $j = corpo_json();
    $pdo = db();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
    $agora = time();
    $pdo->prepare('DELETE FROM tentativas WHERE ts < ?')->execute([$agora - 900]);
    $s = $pdo->prepare('SELECT COUNT(*) n FROM tentativas WHERE ip = ?');
    $s->execute([$ip]);
    $porIp = (int)$s->fetch()['n'];
    $total = (int)$pdo->query('SELECT COUNT(*) n FROM tentativas')->fetch()['n'];
    if ($porIp >= 5 || $total >= 40) json_out(['ok' => false, 'erro' => 'Muitas tentativas. Aguarde 15 minutos e tente de novo.'], 429);
    $u = is_string($j['u'] ?? null) ? trim($j['u']) : '';
    $p = is_string($j['p'] ?? null) ? $j['p'] : '';
    $hash = ajuste('admin_hash');
    $user = ajuste('admin_user');
    $ok = $hash !== null && $user !== null && hash_equals(mb_strtolower($user), mb_strtolower($u)) && password_verify($p, $hash);
    if (!$ok) {
        $pdo->prepare('INSERT INTO tentativas (ip, ts) VALUES (?, ?)')->execute([$ip, $agora]);
        usleep(500000);
        json_out(['ok' => false, 'erro' => $hash === null ? 'Sistema ainda não instalado.' : 'Usuário ou senha incorretos.'], 401);
    }
    $pdo->prepare('DELETE FROM tentativas WHERE ip = ?')->execute([$ip]);
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    $_SESSION['inicio'] = $agora;
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
    json_out(['ok' => true, 'csrf' => $_SESSION['csrf']]);
}

if ($a === 'semear' && $metodo === 'POST') {
    // TEMPORÁRIO: carga inicial. Só funciona enquanto o banco de dados está vazio.
    if (!hash_equals('04be1f5b1b4f98665c9f1fbd2b59b1312358b0b46a23f64c', $_SERVER['HTTP_X_SEMENTE'] ?? '')) json_out(['ok' => false, 'erro' => 'Não encontrado.'], 404);
    $j = corpo_json();
    $pdo = db();
    if ((int)$pdo->query('SELECT COUNT(*) n FROM docs')->fetch()['n'] > 0) json_out(['ok' => false, 'erro' => 'O banco já tem dados.'], 409);
    $dados = $j['dados'] ?? null;
    if (!is_array($dados)) json_out(['ok' => false, 'erro' => 'Arquivo inválido.'], 400);
    $pdo->beginTransaction();
    $ins = $pdo->prepare('INSERT INTO docs (c, id, d) VALUES (?, ?, ?)');
    $n = 0;
    foreach ($dados as $c => $docs) {
        if (!in_array($c, COLECOES, true) || !is_array($docs)) { $pdo->rollBack(); json_out(['ok' => false, 'erro' => 'Coleção inválida.'], 400); }
        foreach ($docs as $id => $d) {
            $id = (string)$id;
            if (!id_valido($id) || !doc_valido($d)) { $pdo->rollBack(); json_out(['ok' => false, 'erro' => 'Registro inválido.'], 400); }
            $ins->execute([$c, $id, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            $n++;
        }
    }
    $pdo->commit();
    json_out(['ok' => true, 'total' => $n]);
}

if ($a === 'logout' && $metodo === 'POST') {
    exigir_csrf();
    $_SESSION = [];
    session_regenerate_id(true);
    json_out(['ok' => true]);
}

if ($a === 'all' && $metodo === 'GET') {
    exigir_login();
    $out = [];
    foreach (db()->query('SELECT c, id, d FROM docs') as $r) {
        $out[$r['c']][$r['id']] = json_decode($r['d']);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => (object)$out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($a === 'w' && $metodo === 'POST') {
    exigir_login();
    exigir_csrf();
    $j = corpo_json();
    $op = $j['op'] ?? '';
    $c = $j['c'] ?? '';
    $id = $j['id'] ?? '';
    if (!in_array($c, COLECOES, true) || !id_valido($id)) json_out(['ok' => false, 'erro' => 'Dados inválidos.'], 400);
    $pdo = db();
    if ($op === 'delete') {
        $pdo->prepare('DELETE FROM docs WHERE c = ? AND id = ?')->execute([$c, $id]);
        json_out(['ok' => true]);
    }
    $d = $j['d'] ?? null;
    if (!doc_valido($d)) json_out(['ok' => false, 'erro' => 'Registro inválido.'], 400);
    if ($op === 'set') {
        $pdo->prepare('INSERT OR REPLACE INTO docs (c, id, d) VALUES (?, ?, ?)')->execute([$c, $id, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        json_out(['ok' => true]);
    }
    if ($op === 'add') {
        $s = $pdo->prepare('INSERT OR IGNORE INTO docs (c, id, d) VALUES (?, ?, ?)');
        $s->execute([$c, $id, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        json_out(['ok' => $s->rowCount() === 1], $s->rowCount() === 1 ? 200 : 409);
    }
    if ($op === 'update') {
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT d FROM docs WHERE c = ? AND id = ?');
        $s->execute([$c, $id]);
        $r = $s->fetch();
        if (!$r) { $pdo->rollBack(); json_out(['ok' => false, 'erro' => 'Registro não encontrado.'], 404); }
        $novo = array_merge(json_decode($r['d'], true) ?: [], $d);
        $pdo->prepare('UPDATE docs SET d = ? WHERE c = ? AND id = ?')->execute([json_encode($novo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $c, $id]);
        $pdo->commit();
        json_out(['ok' => true]);
    }
    json_out(['ok' => false, 'erro' => 'Operação inválida.'], 400);
}

if ($a === 'import' && $metodo === 'POST') {
    exigir_login();
    exigir_csrf();
    $j = corpo_json();
    $dados = $j['dados'] ?? null;
    if (!is_array($dados)) json_out(['ok' => false, 'erro' => 'Arquivo inválido.'], 400);
    $linhas = [];
    foreach ($dados as $c => $docs) {
        if (!in_array($c, COLECOES, true) || !is_array($docs)) json_out(['ok' => false, 'erro' => 'Coleção inválida: ' . (is_string($c) ? $c : '?')], 400);
        foreach ($docs as $id => $d) {
            $id = (string)$id;
            if (!id_valido($id) || !doc_valido($d)) json_out(['ok' => false, 'erro' => 'Registro inválido em ' . $c], 400);
            $linhas[] = [$c, $id, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        }
    }
    if (count($linhas) > 20000) json_out(['ok' => false, 'erro' => 'Registros demais.'], 400);
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM docs');
    $ins = $pdo->prepare('INSERT INTO docs (c, id, d) VALUES (?, ?, ?)');
    foreach ($linhas as $l) $ins->execute($l);
    $pdo->commit();
    json_out(['ok' => true, 'total' => count($linhas)]);
}

if ($a === 'senha' && $metodo === 'POST') {
    exigir_login();
    exigir_csrf();
    $j = corpo_json();
    $atual = is_string($j['atual'] ?? null) ? $j['atual'] : '';
    $nova = is_string($j['nova'] ?? null) ? $j['nova'] : '';
    if (!password_verify($atual, ajuste('admin_hash') ?? '')) { usleep(500000); json_out(['ok' => false, 'erro' => 'Senha atual incorreta.'], 403); }
    if (mb_strlen($nova) < 10) json_out(['ok' => false, 'erro' => 'A nova senha precisa ter pelo menos 10 caracteres.'], 400);
    set_ajuste('admin_hash', password_hash($nova, PASSWORD_DEFAULT));
    json_out(['ok' => true]);
}

json_out(['ok' => false, 'erro' => 'Não encontrado.'], 404);
