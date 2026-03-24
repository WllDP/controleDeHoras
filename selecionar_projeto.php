<?php
require 'config.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] === '') {
    header('Location: index.php');
    exit;
}

$usuario = (string)$_SESSION['usuario'];
$nome = trim((string)($_GET['nome'] ?? ''));

if ($nome === '') {
    header('Location: projetos.php');
    exit;
}

// Verifica se o projeto pertence ao usuário
$stmt = $db->prepare('SELECT 1 FROM projetos WHERE usuario = :u AND nome = :n LIMIT 1');
$stmt->execute([':u' => $usuario, ':n' => $nome]);
$existe = (bool)$stmt->fetchColumn();

if (!$existe) {
    header('Location: projetos.php');
    exit;
}

$_SESSION['projeto'] = $nome;

header('Location: dashboard.php');
exit;
