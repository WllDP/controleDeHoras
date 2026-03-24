<?php
require 'config.php';

// Login simples (sem senha): apenas nome do usuário
$usuario = trim((string)($_POST['nome'] ?? ''));
$remember = (string)($_POST['remember'] ?? '');
$theme = (string)($_POST['theme'] ?? '');

if ($usuario === '') {
    header('Location: index.php');
    exit;
}

// Regenera sessão por segurança básica
session_regenerate_id(true);

$_SESSION['usuario'] = $usuario;
// Garante que não fica "preso" em um projeto antigo
unset($_SESSION['projeto']);

// Salva/remover "lembrar usuário"
try {
    if ($remember !== '') {
        $stmt = $db->prepare('INSERT INTO app_settings (usuario, chave, valor) VALUES (:u, :k, :v)
            ON CONFLICT(usuario, chave) DO UPDATE SET valor = excluded.valor');
        $stmt->execute([':u' => '', ':k' => 'remember_user', ':v' => $usuario]);
    } else {
        $stmt = $db->prepare('DELETE FROM app_settings WHERE usuario = :u AND chave = :k');
        $stmt->execute([':u' => '', ':k' => 'remember_user']);
    }
} catch (Throwable $e) {
    // ignora se não conseguir salvar
}

// Salva tema escolhido no login (usuário e global)
if ($theme === 'dark' || $theme === 'light') {
    try {
        $stmt = $db->prepare('INSERT INTO app_settings (usuario, chave, valor) VALUES (:u, :k, :v)
            ON CONFLICT(usuario, chave) DO UPDATE SET valor = excluded.valor');
        $stmt->execute([':u' => $usuario, ':k' => 'theme', ':v' => $theme]);
        $stmt->execute([':u' => '', ':k' => 'theme', ':v' => $theme]);
    } catch (Throwable $e) {
        // ignora se não conseguir salvar
    }
}

header('Location: projetos.php');
exit;
