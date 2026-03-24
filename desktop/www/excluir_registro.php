<?php
require 'config.php';

if (!isset($_SESSION['usuario']) || !isset($_SESSION['projeto'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false]);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false]);
    exit;
}

$usuario = (string)$_SESSION['usuario'];
$projeto = (string)$_SESSION['projeto'];

$stmt = $db->prepare('DELETE FROM registros WHERE id = :id AND colaborador = :u AND projeto = :p');
$stmt->execute([':id' => $id, ':u' => $usuario, ':p' => $projeto]);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);
