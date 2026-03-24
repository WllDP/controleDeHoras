<?php
require 'config.php';

if (!isset($_SESSION['usuario']) || !isset($_SESSION['projeto'])) {
    http_response_code(401);
    exit('Não autenticado');
}

$appId = (int)($_POST['app_id'] ?? 0);
if ($appId <= 0) {
    http_response_code(400);
    exit('Dados inválidos');
}

$stmt = $db->prepare("
    DELETE FROM atividades_pausadas
    WHERE app_id = ? AND colaborador = ? AND projeto = ?
");
$stmt->execute([
    $appId,
    (string)$_SESSION['usuario'],
    (string)$_SESSION['projeto'],
]);

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || isset($_POST['ajax']);

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
    exit;
}

echo 'OK';
