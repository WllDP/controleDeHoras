<?php
require 'config.php';

if (!isset($_SESSION['usuario']) || !isset($_SESSION['projeto'])) {
    http_response_code(401);
    exit('Não autenticado');
}

$appId = (int)($_POST['app_id'] ?? 0);
$atividade = trim((string)($_POST['atividade'] ?? ''));
$inicioIso = (string)($_POST['inicio_iso'] ?? '');
$tempoSegundos = (int)($_POST['tempo_segundos'] ?? 0);

if ($appId <= 0 || $atividade === '') {
    http_response_code(400);
    exit('Dados inválidos');
}

$now = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format(DateTime::ATOM);

$stmt = $db->prepare("
    INSERT INTO atividades_pausadas (
        app_id, colaborador, projeto, atividade, inicio_iso, tempo_segundos, updated_at
    ) VALUES (
        ?,?,?,?,?,?,?
    )
    ON CONFLICT(app_id, colaborador, projeto) DO UPDATE SET
        atividade = excluded.atividade,
        inicio_iso = excluded.inicio_iso,
        tempo_segundos = excluded.tempo_segundos,
        updated_at = excluded.updated_at
");
$stmt->execute([
    $appId,
    (string)$_SESSION['usuario'],
    (string)$_SESSION['projeto'],
    $atividade,
    $inicioIso !== '' ? $inicioIso : null,
    $tempoSegundos,
    $now,
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
