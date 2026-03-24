<?php
require 'config.php';

// Remove lembrar usuário
try {
    $stmt = $db->prepare('DELETE FROM app_settings WHERE usuario = :u AND chave = :k');
    $stmt->execute([':u' => '', ':k' => 'remember_user']);
} catch (Throwable $e) {
    // ignora se não conseguir remover
}

$_SESSION = [];
if (session_id() !== '' || isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}
session_destroy();

header('Location: index.php');
exit;
