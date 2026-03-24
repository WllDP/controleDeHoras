<?php
require 'config.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] === '') {
    header('Location: index.php');
    exit;
}

$usuario = (string)$_SESSION['usuario'];
$nome = trim((string)($_POST['projeto'] ?? ''));
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) || (string)($_POST['ajax'] ?? '') === '1';

if ($nome === '') {
    header('Location: projetos.php');
    exit;
}

$stmt = $db->prepare('SELECT 1 FROM projetos WHERE usuario = :u AND nome = :n LIMIT 1');
$stmt->execute([':u' => $usuario, ':n' => $nome]);
if ($stmt->fetchColumn()) {
    if ($isAjax) {
        http_response_code(409);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Nome de projeto em uso']);
        exit;
    }
    header('Location: projetos.php');
    exit;
}

$stmt = $db->prepare('INSERT OR IGNORE INTO projetos (usuario, nome, created_at) VALUES (:u, :n, :c)');
$stmt->execute([
    ':u' => $usuario,
    ':n' => $nome,
    ':c' => (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s')
]);

$_SESSION['projeto'] = $nome;

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'redirect' => 'dashboard.php']);
    exit;
}

header('Location: dashboard.php');
exit;
