<?php
require 'config.php';

$tz = new DateTimeZone('America/Sao_Paulo');

$inicio = new DateTime($_POST['inicio'], $tz);
$fim    = new DateTime($_POST['fim'], $tz);

$intervalo = $inicio->diff($fim);
$tempo_segundos = max(0, $fim->getTimestamp() - $inicio->getTimestamp());
$total_geral = $intervalo->format('%H:%I:%S');

$manha = $inicio->format('H') < 12;

// Data para banco (ideal)
$data = (new DateTime('now', $tz))->format('Y-m-d');

$colaborador = $_SESSION['usuario'];
$projeto     = $_SESSION['projeto'] ?? '';
$atividade   = $_POST['atividade'];

$entrada_manha = $saida_manha = $total_manha = "00:00:00";
$entrada_tarde = $saida_tarde = $total_tarde = "00:00:00";

if ($manha) {
    $entrada_manha = $inicio->format('H:i:s');
    $saida_manha   = $fim->format('H:i:s');
    $total_manha   = $total_geral;
} else {
    $entrada_tarde = $inicio->format('H:i:s');
    $saida_tarde   = $fim->format('H:i:s');
    $total_tarde   = $total_geral;
}

$stmt = $db->prepare("
INSERT INTO registros (
    id, data, colaborador, projeto, atividade,
    entrada_manha, saida_manha, total_manha,
    entrada_tarde, saida_tarde, total_tarde,
    total_geral, inicio_iso, fim_iso, tempo_segundos, exportada
) VALUES (
    NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
)");
$stmt->execute([
    $data,
    $colaborador,
    $projeto,
    $atividade,
    $entrada_manha,
    $saida_manha,
    $total_manha,
    $entrada_tarde,
    $saida_tarde,
    $total_tarde,
    $total_geral,
    $_POST['inicio_iso'] ?? $inicio->format(DateTime::ATOM),
    $_POST['fim_iso'] ?? $fim->format(DateTime::ATOM),
    $tempo_segundos,
    0
]);

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || isset($_POST['ajax']);

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'id' => (int)$db->lastInsertId()]);
    exit;
}

header('Location: dashboard.php');
