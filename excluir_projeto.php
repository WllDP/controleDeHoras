<?php
require 'config.php';

if (!isset($_SESSION['usuario']) || !isset($_SESSION['projeto'])) {
    header('Location: index.php');
    exit;
}

$usuario = (string)$_SESSION['usuario'];
$projeto = (string)$_SESSION['projeto'];

// Remove cache do Excel do servidor (usuário + projeto)
$cacheDir = __DIR__ . '/excel/cache';
$hash = sha1($usuario . '|' . $projeto);

$arquivoCache = $cacheDir . '/' . $hash . '.xlsx';
$tmpCache     = $arquivoCache . '.tmp';

if (file_exists($tmpCache)) {
    @unlink($tmpCache);
}
if (file_exists($arquivoCache)) {
    @unlink($arquivoCache);
}

// Remove registros do projeto
$stmt = $db->prepare('DELETE FROM registros WHERE colaborador = :u AND projeto = :p');
$stmt->execute([':u' => $usuario, ':p' => $projeto]);

// Remove o projeto da lista do usuário
$stmt = $db->prepare('DELETE FROM projetos WHERE usuario = :u AND nome = :n');
$stmt->execute([':u' => $usuario, ':n' => $projeto]);

// Mantém usuário logado, mas sai do projeto atual
unset($_SESSION['projeto']);

header('Location: projetos.php');
exit;


