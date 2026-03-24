<?php
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

$theme = 'light';
try {
    $user = isset($_SESSION['usuario']) ? (string)$_SESSION['usuario'] : '';
    if ($user !== '') {
        $stmt = $db->prepare('SELECT valor FROM app_settings WHERE usuario = :u AND chave = :k LIMIT 1');
        $stmt->execute([':u' => $user, ':k' => 'theme']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && ($row['valor'] === 'dark' || $row['valor'] === 'light')) {
            $theme = (string)$row['valor'];
        }
    }
    if ($theme === 'light') {
        $stmt = $db->prepare('SELECT valor FROM app_settings WHERE usuario = :u AND chave = :k LIMIT 1');
        $stmt->execute([':u' => '', ':k' => 'theme']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && ($row['valor'] === 'dark' || $row['valor'] === 'light')) {
            $theme = (string)$row['valor'];
        }
    }
} catch (Throwable $e) {
    // mantém light como fallback
}

echo json_encode(['theme' => $theme]);
