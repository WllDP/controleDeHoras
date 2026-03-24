<?php
date_default_timezone_set('America/Sao_Paulo');

session_start();

$db = new PDO('sqlite:db.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("
CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome TEXT,
    email TEXT,
    senha TEXT
);

CREATE TABLE IF NOT EXISTS registros (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    data TEXT,
    colaborador TEXT,
    projeto TEXT,
    atividade TEXT,
    entrada_manha TEXT,
    saida_manha TEXT,
    total_manha TEXT,
    entrada_tarde TEXT,
    saida_tarde TEXT,
    total_tarde TEXT,
    total_geral TEXT,
    inicio_iso TEXT,
    fim_iso TEXT,
    tempo_segundos INTEGER
);

CREATE TABLE IF NOT EXISTS projetos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario TEXT NOT NULL,
    nome TEXT NOT NULL,
    created_at TEXT NOT NULL,
    UNIQUE(usuario, nome)
);

CREATE TABLE IF NOT EXISTS atividades_pausadas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    app_id INTEGER NOT NULL,
    colaborador TEXT NOT NULL,
    projeto TEXT NOT NULL,
    atividade TEXT NOT NULL,
    inicio_iso TEXT,
    tempo_segundos INTEGER DEFAULT 0,
    updated_at TEXT NOT NULL,
    UNIQUE(app_id, colaborador, projeto)
);

CREATE TABLE IF NOT EXISTS app_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario TEXT NOT NULL,
    chave TEXT NOT NULL,
    valor TEXT,
    UNIQUE(usuario, chave)
);
");

function getTableColumns(PDO $db, string $table): array
{
    $stmt = $db->query("PRAGMA table_info($table)");
    $cols = [];
    if ($stmt) {
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($row['name'])) {
                $cols[] = $row['name'];
            }
        }
    }
    return $cols;
}

function ensureColumn(PDO $db, string $table, string $column, string $type): void
{
    $cols = getTableColumns($db, $table);
    if (!in_array($column, $cols, true)) {
        $db->exec("ALTER TABLE $table ADD COLUMN $column $type");
    }
}

ensureColumn($db, 'registros', 'projeto', 'TEXT');
ensureColumn($db, 'registros', 'total_geral', 'TEXT');
ensureColumn($db, 'registros', 'inicio_iso', 'TEXT');
ensureColumn($db, 'registros', 'fim_iso', 'TEXT');
ensureColumn($db, 'registros', 'tempo_segundos', 'INTEGER');
ensureColumn($db, 'registros', 'exportada', 'INTEGER DEFAULT 0');

$cols = getTableColumns($db, 'registros');
if (in_array('total', $cols, true) && in_array('total_geral', $cols, true)) {
    $db->exec("UPDATE registros SET total_geral = COALESCE(total_geral, total) WHERE total_geral IS NULL OR total_geral = ''");
}
