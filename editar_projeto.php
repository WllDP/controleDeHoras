<?php
require 'config.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] === '') {
    http_response_code(401);
    exit('Não autenticado');
}

$id = (int)($_POST['id'] ?? 0);
$nome = trim((string)($_POST['nome'] ?? ''));
$usuario = (string)$_SESSION['usuario'];

if ($id <= 0 || $nome === '') {
    http_response_code(400);
    exit('Dados inválidos');
}

try {
    $db->beginTransaction();

    $stmt = $db->prepare('SELECT id FROM projetos WHERE usuario = :u AND nome = :n AND id != :id');
    $stmt->execute([':u' => $usuario, ':n' => $nome, ':id' => $id]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        $db->rollBack();
        http_response_code(409);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Nome de projeto em uso']);
        exit;
    }

    $stmt = $db->prepare('SELECT nome FROM projetos WHERE id = :id AND usuario = :u');
    $stmt->execute([':id' => $id, ':u' => $usuario]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['nome'])) {
        $db->rollBack();
        http_response_code(404);
        exit('Projeto não encontrado');
    }
    $oldName = (string)$row['nome'];

    $stmt = $db->prepare('UPDATE projetos SET nome = :n WHERE id = :id AND usuario = :u');
    $stmt->execute([':n' => $nome, ':id' => $id, ':u' => $usuario]);

    $stmt = $db->prepare('UPDATE registros SET projeto = :n WHERE projeto = :o AND colaborador = :u');
    $stmt->execute([':n' => $nome, ':o' => $oldName, ':u' => $usuario]);

    $stmt = $db->prepare('UPDATE atividades_pausadas SET projeto = :n WHERE projeto = :o AND colaborador = :u');
    $stmt->execute([':n' => $nome, ':o' => $oldName, ':u' => $usuario]);

    $db->commit();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    $msg = 'Não foi possível salvar o projeto.';
    $raw = strtolower((string)$e->getMessage());
    if (strpos($raw, 'unique') !== false) {
        $msg = 'Nome de projeto em uso';
    }
    echo json_encode(['ok' => false, 'message' => $msg]);
}
