<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');

// ⚠️ evita timeout no endpoint de exportação
set_time_limit(0);
ini_set('max_execution_time', '0');

// LIMPA QUALQUER BUFFER (evita corromper XLSX)
while (ob_get_level() > 0) {
    ob_end_clean();
}

require 'config.php';

$vendorCandidates = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];
$vendorPath = null;
foreach ($vendorCandidates as $candidate) {
    if (file_exists($candidate)) {
        $vendorPath = $candidate;
        break;
    }
}
if ($vendorPath === null) {
    http_response_code(500);
    exit('Dependências não encontradas.');
}
require $vendorPath;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

if (!isset($_SESSION['usuario']) || !isset($_SESSION['projeto'])) {
    http_response_code(401);
    exit('Não autenticado');
}

$modo = ($_POST['modo'] ?? 'nova');
if (!in_array($modo, ['nova', 'atualizar'], true)) {
    $modo = 'nova';
}

/* =========================
   LÊ DADOS (JSON ou POST)
========================= */
$dados = [];
if (!empty($_POST['dados'])) {
    $dados = json_decode($_POST['dados'], true);
} else {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    $dados = $json['dados'] ?? $json ?? [];
}

if (!is_array($dados) || empty($dados)) {
    http_response_code(400);
    exit('Nenhuma atividade recebida');
}

/* =========================
   CONTEXTO
========================= */
$tz = new DateTimeZone('America/Sao_Paulo');
$usuario = (string)$_SESSION['usuario'];
$projeto = (string)$_SESSION['projeto'];

$cacheDir = getenv('CONTROLE_HORAS_CACHE');
if (!$cacheDir) {
    $appData = getenv('APPDATA') ?: getenv('LOCALAPPDATA') ?: sys_get_temp_dir();
    $cacheDir = rtrim($appData, '\\/') . '/ControleHoras/excel-cache';
}
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}

$hash = sha1($usuario . '|' . $projeto);
$arquivoCache = "$cacheDir/$hash.xlsx";

/* =========================
   CRIA PLANILHA (SE NOVA)
========================= */
if ($modo === 'nova' || !file_exists($arquivoCache)) {
    $ok = copy(__DIR__ . '/excel/modelo.xlsx', $arquivoCache);
    if (!$ok) {
        http_response_code(500);
        exit('Falha ao copiar modelo.xlsx');
    }
}

/* =========================
   ABRE PLANILHA
========================= */
$spreadsheet = IOFactory::load($arquivoCache);
$sheet = $spreadsheet->getActiveSheet();

/* =========================
   HELPERS
========================= */
function acharLinhaTotal($sheet): int
{
    // Procura "Total:" na coluna I
    $max = max(100, (int)$sheet->getHighestRow() + 50);
    for ($i = 5; $i <= $max; $i++) {
        $v = $sheet->getCell("I{$i}")->getValue();
        if (is_string($v) && trim($v) === 'Total:') {
            return $i;
        }
    }
    // fallback (no seu modelo, costuma ser 5)
    return 5;
}

function parseInicio(array $a, DateTimeZone $tz): DateTime
{
    if (!empty($a['inicio_iso'])) {
        $d = new DateTime($a['inicio_iso']);
        $d->setTimezone($tz);
        return $d;
    }
    // fallback seguro
    return new DateTime('now', $tz);
}

function prepararLinha($sheet, int $linhaModelo, int $linha): void
{
    if ($linha !== $linhaModelo) {
        // Duplica estilo A..J da linha 4 para a linha nova
        $sheet->duplicateStyle(
            $sheet->getStyle("A{$linhaModelo}:J{$linhaModelo}"),
            "A{$linha}:J{$linha}"
        );
    }

    // Fórmulas do modelo
    $sheet->setCellValue("F{$linha}", "=E{$linha}-D{$linha}");
    $sheet->setCellValue("I{$linha}", "=H{$linha}-G{$linha}");
    $sheet->setCellValue("J{$linha}", "=SUM(F{$linha},I{$linha})");

    // Formatos
    $sheet->getStyle("A{$linha}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');

    foreach (['D','E','G','H'] as $c) {
        $sheet->getStyle("{$c}{$linha}")->getNumberFormat()->setFormatCode('hh:mm:ss');
    }

    foreach (['F','I','J'] as $c) {
        $sheet->getStyle("{$c}{$linha}")->getNumberFormat()->setFormatCode('[h]:mm:ss');
    }

    // ✅ CENTRALIZA B e C (horizontal e vertical)
    $sheet->getStyle("B{$linha}:C{$linha}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(false);

        /* ===== NEGRITO NOS TOTAIS ===== */
// Total manhã (F)
$sheet->getStyle("F{$linha}")->getFont()->setBold(true);

// Total tarde (I)
$sheet->getStyle("I{$linha}")->getFont()->setBold(true);

// (opcional, mas recomendado) Total da linha (J)
$sheet->getStyle("J{$linha}")->getFont()->setBold(true);
}

/* =========================
   DEFINE LINHAS / TOTAL
========================= */
$linhaModelo = 4;
$totalRow = acharLinhaTotal($sheet);

// Descobre a próxima linha livre (antes do Total) olhando A
$linhaAtual = $linhaModelo;
for ($r = $linhaModelo; $r < $totalRow; $r++) {
    $v = $sheet->getCell("A{$r}")->getValue();
    if ($v === null || trim((string)$v) === '') {
        $linhaAtual = $r;
        break;
    }
    $linhaAtual = $r + 1;
}
// Se por algum motivo a próxima linha caiu em cima do total, corrige
if ($linhaAtual >= $totalRow) {
    $linhaAtual = $totalRow;
}

/* =========================
   FILTRA DADOS VÁLIDOS
   (evita inserir linhas à toa)
========================= */
$validos = [];
foreach ($dados as $a) {
    if (!is_array($a)) continue;
    $nome = $a['atividade'] ?? $a['nome'] ?? '';
    if (!is_string($nome) || trim($nome) === '') continue;
    $validos[] = $a;
}

if (empty($validos)) {
    http_response_code(400);
    exit('Nenhuma atividade válida recebida');
}

/* =========================
   INSERE LINHAS EM LOTE
   (performance: evita insert a cada atividade)
========================= */
$precisa = count($validos);
$slotsLivres = max(0, $totalRow - $linhaAtual); // quantas linhas existem antes do Total
$inserir = max(0, $precisa - $slotsLivres);

if ($inserir > 0) {
    $sheet->insertNewRowBefore($totalRow, $inserir);
    $totalRow += $inserir;
}

/* =========================
   ESCREVE ATIVIDADES (sem inserir linha por linha)
========================= */
$inicioDaEscrita = $linhaAtual;

foreach ($validos as $a) {
    prepararLinha($sheet, $linhaModelo, $linhaAtual);

    $inicio = parseInicio($a, $tz);
    $duracao = (int)($a['tempo'] ?? 0);
    if ($duracao < 0) $duracao = 0;

    $fim = clone $inicio;
    if ($duracao > 0) {
        $fim->modify("+{$duracao} seconds");
    }

    // Data (A)
    $sheet->setCellValue("A{$linhaAtual}", ExcelDate::PHPToExcel($inicio));

    // Texto (B/C)
    $sheet->setCellValue("B{$linhaAtual}", $usuario);
    $sheet->setCellValue("C{$linhaAtual}", (string)($a['atividade'] ?? $a['nome'] ?? ''));

    // Zera horários para ficar 00:00:00
    foreach (['D','E','G','H'] as $c) {
        $sheet->setCellValue("{$c}{$linhaAtual}", 0);
    }

    // Manhã ou tarde
    if ((int)$inicio->format('H') < 13) {
        $sheet->setCellValue("D{$linhaAtual}", ExcelDate::PHPToExcel($inicio));
        $sheet->setCellValue("E{$linhaAtual}", ExcelDate::PHPToExcel($fim));
    } else {
        $sheet->setCellValue("G{$linhaAtual}", ExcelDate::PHPToExcel($inicio));
        $sheet->setCellValue("H{$linhaAtual}", ExcelDate::PHPToExcel($fim));
    }

    $linhaAtual++;
}

/* =========================
   AJUSTA TOTAL
========================= */
if (trim((string)$sheet->getCell("I{$totalRow}")->getValue()) !== 'Total:') {
    $sheet->setCellValue("I{$totalRow}", 'Total:');
}

// Soma de J4 até última linha preenchida (linhaAtual-1)
$ultimaLinha = $linhaAtual - 1;
if ($ultimaLinha < $linhaModelo) $ultimaLinha = $linhaModelo;

$sheet->setCellValue("J{$totalRow}", "=SUM(J{$linhaModelo}:J{$ultimaLinha})");
$sheet->getStyle("J{$totalRow}")->getNumberFormat()->setFormatCode('[h]:mm:ss');

/* =========================
   SALVA COM SEGURANÇA
========================= */
$tmp = "{$arquivoCache}.tmp";
IOFactory::createWriter($spreadsheet, 'Xlsx')->save($tmp);

$spreadsheet->disconnectWorksheets();

// troca atômica
@unlink($arquivoCache);
rename($tmp, $arquivoCache);

/* =========================
   MARCA EXPORTADAS NO BANCO
========================= */
$dbIds = [];
foreach ($validos as $a) {
    $dbId = $a['dbId'] ?? null;
    if ($dbId !== null && is_numeric($dbId)) {
        $dbIds[] = (int)$dbId;
    }
}
$dbIds = array_values(array_unique($dbIds));
if (!empty($dbIds)) {
    $placeholders = implode(',', array_fill(0, count($dbIds), '?'));
    $sql = "UPDATE registros SET exportada = 1 WHERE id IN ($placeholders) AND colaborador = ? AND projeto = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge($dbIds, [$usuario, $projeto]));
}

/* =========================
   DOWNLOAD (rápido: readfile)
========================= */
$nome = "controle_horas_{$usuario}_{$projeto}.xlsx";
$nome = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $nome);

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"{$nome}\"");
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: public');
header('Expires: 0');

readfile($arquivoCache);
exit;
