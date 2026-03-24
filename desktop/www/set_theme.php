<?php
require 'config.php';

$theme = (string)($_POST['theme'] ?? '');
if ($theme !== 'dark' && $theme !== 'light') {
    http_response_code(400);
    exit('Tema inválido');
}

$user = isset($_SESSION['usuario']) ? (string)$_SESSION['usuario'] : '';

try {
    $stmt = $db->prepare('INSERT INTO app_settings (usuario, chave, valor) VALUES (:u, :k, :v)
        ON CONFLICT(usuario, chave) DO UPDATE SET valor = excluded.valor');
    if ($user !== '') {
        $stmt->execute([':u' => $user, ':k' => 'theme', ':v' => $theme]);
    }
    $stmt->execute([':u' => '', ':k' => 'theme', ':v' => $theme]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false]);
}
