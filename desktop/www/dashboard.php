<?php
require 'config.php';
if (!isset($_SESSION['usuario']) || !isset($_SESSION['projeto'])) {
    header('Location: index.php');
    exit;
}

$usuario = (string)$_SESSION['usuario'];
$projeto = (string)$_SESSION['projeto'];

function timeToSeconds($time): int
{
    if (!is_string($time) || $time === '') {
        return 0;
    }
    $parts = explode(':', $time);
    if (count($parts) !== 3) {
        return 0;
    }
    return ((int)$parts[0] * 3600) + ((int)$parts[1] * 60) + (int)$parts[2];
}

function buildInicioIso(array $row, DateTimeZone $tz): ?string
{
    if (!empty($row['inicio_iso'])) {
        return $row['inicio_iso'];
    }
    $data = $row['data'] ?? '';
    $hora = $row['entrada_manha'] ?? '';
    if ($hora === '' || $hora === '00:00:00') {
        $hora = $row['entrada_tarde'] ?? '';
    }
    if ($data === '' || $hora === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', "{$data} {$hora}", $tz);
    if (!$dt) {
        return null;
    }
    return $dt->format(DateTime::ATOM);
}

$atividadesDb = [];
$stmt = $db->prepare("
    SELECT id, atividade, data, entrada_manha, entrada_tarde,
           total_manha, total_tarde, total_geral, inicio_iso, tempo_segundos, exportada
    FROM registros
    WHERE colaborador = :u AND projeto = :p
    ORDER BY id ASC
");
$stmt->execute([':u' => $usuario, ':p' => $projeto]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$tz = new DateTimeZone('America/Sao_Paulo');
foreach ($rows as $row) {
  $inicioIso = buildInicioIso($row, $tz);
    $tempo = 0;
    if (isset($row['tempo_segundos']) && $row['tempo_segundos'] !== null && $row['tempo_segundos'] !== '') {
        $tempo = (int)$row['tempo_segundos'];
    } elseif (!empty($row['total_geral'])) {
        $tempo = timeToSeconds($row['total_geral']);
    } else {
        $tempo = timeToSeconds($row['total_manha'] ?? '') + timeToSeconds($row['total_tarde'] ?? '');
    }
    if ($inicioIso) {
        $atividadesDb[] = [
            'id' => (int)$row['id'],
            'nome' => (string)($row['atividade'] ?? ''),
            'inicio' => $inicioIso,
            'tempo' => $tempo,
            'rodando' => false,
            'pausada' => false,
            'lastTick' => null,
            'exportada' => (int)($row['exportada'] ?? 0) === 1,
        ];
    }
}

$stmt = $db->prepare("
    SELECT id, app_id, atividade, inicio_iso, tempo_segundos
    FROM atividades_pausadas
    WHERE colaborador = :u AND projeto = :p
    ORDER BY id ASC
");
$stmt->execute([':u' => $usuario, ':p' => $projeto]);
$pausedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($pausedRows as $row) {
    $inicioIso = $row['inicio_iso'] ?? null;
    if (!$inicioIso) {
        continue;
    }
    $appId = (int)($row['app_id'] ?? 0);
    if ($appId <= 0) {
        $appId = (int)($row['id'] ?? 0);
    }
    if ($appId <= 0) {
        continue;
    }
    $atividadesDb[] = [
        'id' => $appId,
        'dbId' => null,
        'nome' => (string)($row['atividade'] ?? ''),
        'inicio' => $inicioIso,
        'tempo' => (int)($row['tempo_segundos'] ?? 0),
        'rodando' => false,
        'pausada' => true,
        'lastTick' => null,
        'exportada' => false,
    ];
}

ob_start();
?>

<div class="pt-2 px-8 pb-0 space-y-6 w-full max-w-[820px] min-w-[400px] min-h-[470px] mx-auto">
    <a
        href="projetos.php"
        id="backLink"
        class="absolute top-2 left-2 h-8 w-8 inline-flex items-center justify-center rounded-lg no-drag
               text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition select-none"
        title="Voltar"
        aria-label="Voltar"
    ><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>

    <div class="flex justify-between items-center gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-800">
                Olá,
                <span class="inline-block max-w-[240px] truncate align-bottom"
                      title="<?= htmlspecialchars($_SESSION['usuario']) ?>">
                    <?= htmlspecialchars($_SESSION['usuario']) ?>
                </span>
            </h2>
            <p class="text-sm text-slate-500">
                Projeto:
                <span class="font-semibold inline-block max-w-[220px] truncate align-bottom"
                      title="<?php echo htmlspecialchars($_SESSION['projeto']); ?>">
                    <?php echo htmlspecialchars($_SESSION['projeto']); ?>
                </span>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button
                type="button"
                onclick="excluirProjeto()"
                class="group inline-flex items-center justify-center text-sm text-red-600 hover:text-red-700 transition p-1"
                title="Excluir projeto"
                aria-label="Excluir projeto"
            >
                <i class="fa-solid fa-trash-can text-xl transition-transform duration-150 group-hover:scale-110" aria-hidden="true"></i>
            </button>
            <a
                href="logout.php"
                id="logoutLink"
                class="group inline-flex items-center justify-center text-slate-600 hover:text-slate-800 transition p-1"
                title="Sair"
                aria-label="Sair"
            >
                <i class="fa-solid fa-right-from-bracket text-xl transition-transform duration-150 group-hover:scale-110" aria-hidden="true"></i>
            </a>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-600 mb-1">
            Atividade
        </label>
        <input
            type="text"
            id="atividade"
            class="w-full px-4 py-2 border rounded focus:ring focus:ring-blue-300"
        >
    </div>

    <div class="relative w-full h-10">
        <button
            id="playBtn"
            onclick="play()"
            class="absolute inset-0 w-full bg-green-600 text-white py-2 rounded hover:bg-green-700 transition-all duration-150"
            aria-label="Play"
        >
            <i class="fa-solid fa-play" aria-hidden="true"></i>
        </button>

        <div
            id="runningControls"
            class="absolute inset-0 flex w-full gap-3 transition-all duration-150 opacity-0 pointer-events-none scale-95"
            aria-hidden="true"
        >
            <div class="relative flex-1">
                <button
                    id="pauseBtn"
                    onclick="pausar()"
                    class="absolute inset-0 bg-amber-500 text-white py-2 rounded hover:bg-amber-600 transition-all duration-150"
                    aria-label="Pausar"
                >
                    <i class="fa-solid fa-pause" aria-hidden="true"></i>
                </button>
                <button
                    id="resumeBtn"
                    onclick="retomar()"
                    class="absolute inset-0 bg-green-600 text-white py-2 rounded hover:bg-green-700 transition-all duration-150
                           opacity-0 pointer-events-none scale-95"
                    aria-label="Retomar"
                >
                    <i class="fa-solid fa-play" aria-hidden="true"></i>
                </button>
            </div>
            <button
                id="stopBtn"
                onclick="stop()"
                class="flex-1 bg-red-600 text-white py-2 rounded hover:bg-red-700 transition-all duration-150"
                aria-label="Stop"
            >
                <i class="fa-solid fa-stop" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="activity-table-container mt-6 max-h-[140px] overflow-y-auto overflow-x-hidden border rounded">
        <table class="w-full text-sm">
            <thead class="bg-slate-100 sticky top-0 z-10">
                <tr>
                    <th class="p-2 text-left">Atividade</th>
                    <th class="p-2 text-left">Tempo</th>
                    <th class="p-2"></th>
                </tr>
            </thead>
            <tbody id="listaAtividades"></tbody>
        </table>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <button
            type="button"
            onclick="atualizarPlanilha()"
            class="w-full bg-[#2E3A4A] hover:bg-[#4D607A] text-white py-2 rounded transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-[#2E3A4A]/40"
            title="Adiciona atividades ainda não exportadas na planilha"
        >
            Atualizar planilha
        </button>

        <button
            type="button"
            onclick="novaPlanilha()"
            class="w-full bg-[#0b2a5b] hover:bg-[#1f5a96] text-white py-2 rounded transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-[#0b2a5b]/40"
            title="Gera uma nova planilha do zero (não acumulativa)"
        >
            Nova planilha
        </button>
    </div>

    <div id="modalOverlay"
         class="absolute -top-10 left-0 right-0 bottom-0 flex items-center justify-center bg-slate-900/50 rounded-2xl hidden z-50">
        <div id="modalBox"
             class="bg-white px-6 py-4 rounded-xl shadow-lg text-slate-800
                    opacity-0 scale-95 transition-all"
             style="transition-duration: 140ms;">
            Atividade iniciada
        </div>
    </div>

</div>

<form id="formRegistro" action="salvar_registro.php" method="POST" class="hidden">
    <input type="hidden" name="atividade" id="f_atividade">
    <input type="hidden" name="inicio" id="f_inicio">
    <input type="hidden" name="fim" id="f_fim">
</form>

<script>
    // Contexto para cache por "usuário + projeto"
    window.APP_CONTEXT = {
        usuario: <?= json_encode($_SESSION['usuario']) ?>,
        projeto: <?= json_encode($_SESSION['projeto']) ?>
    };
    window.DB_ATIVIDADES = <?= json_encode($atividadesDb) ?>;
</script>

<script src="assets/script.js"></script>

<?php
$content = ob_get_clean();
$title = 'Dashboard';
require 'layout.php';
