<?php
// listar_feedback.php

session_start();

require_once __DIR__ . '/auth.php';

// Logout via botão "Sair"
if (isset($_GET['logout'])) {
    do_logout('acesso-feedback.php');
}

// Protege a página
require_login('acesso-feedback.php');

$dataDir = __DIR__ . '/data';
$dbPath  = $dataDir . '/feedback.db';

if (!file_exists($dbPath)) {
    die('Nenhum feedback encontrado ainda. O arquivo data/feedback.db não foi encontrado.');
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $filterUtil   = $_GET['util']   ?? '';
    $filterPage   = trim($_GET['pagina'] ?? '');
    $filterPeriod = $_GET['period'] ?? 'all';

    $sql = "
        SELECT id, util, mensagem, pagina, user_agent, ip, created_at
        FROM feedback
        WHERE 1 = 1
    ";
    $params = [];

    if ($filterUtil === 'yes' || $filterUtil === 'no') {
        $sql .= " AND util = :util";
        $params[':util'] = $filterUtil;
    }

    if ($filterPage !== '') {
        $sql .= " AND pagina LIKE :pagina";
        $params[':pagina'] = '%' . $filterPage . '%';
    }

    $startDate = null;
    if (in_array($filterPeriod, ['7', '15', '30'], true)) {
        $days = (int) $filterPeriod;

        $agoraBr   = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
        $inicioBr  = $agoraBr->modify("-{$days} days");
        $inicioUtc = $inicioBr->setTimezone(new DateTimeZone('UTC'));

        $startDate = $inicioUtc->format('Y-m-d H:i:s');
    }

    if ($startDate !== null) {
        $sql .= " AND created_at >= :startDate";
        $params[':startDate'] = $startDate;
    }

    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    die('Erro ao ler o banco de dados: ' . htmlspecialchars($e->getMessage()));
}

function formatDataHoraBrasil(?string $ts): string
{
    if (!$ts) {
        return '';
    }

    try {
        $dt = new DateTime($ts, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        return $dt->format('d/m/Y H:i');
    } catch (Exception $e) {
        return $ts;
    }
}

// Estatísticas rápidas
$total   = count($rows);
$totYes  = 0;
$totNo   = 0;
$totNone = 0;

foreach ($rows as $r) {
    if (($r['util'] ?? '') === 'yes') {
        $totYes++;
    } elseif (($r['util'] ?? '') === 'no') {
        $totNo++;
    } else {
        $totNone++;
    }
}

$percYes  = $total ? round(($totYes / $total) * 100, 1) : 0;
$percNo   = $total ? round(($totNo / $total) * 100, 1) : 0;
$percNone = $total ? max(0, round(100 - $percYes - $percNo, 1)) : 0;
$p1 = $percYes;
$p2 = $percYes + $percNo;

$answered      = $totYes + $totNo;
$scoreUseful   = $answered ? round(($totYes / $answered) * 100) : 0; // Úteis / respondidos
$responseRate  = $total ? round(($answered / $total) * 100) : 0;     // Respondidos / total

// Top páginas (do conjunto já filtrado)
$pageCounts = [];
foreach ($rows as $r) {
    $pg = trim((string)($r['pagina'] ?? ''));
    if ($pg === '') $pg = '(sem página)';
    if (!isset($pageCounts[$pg])) $pageCounts[$pg] = 0;
    $pageCounts[$pg]++;
}
arsort($pageCounts);
$topPages = array_slice($pageCounts, 0, 6, true);

// EXPORT CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'feedbacks-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');

    // BOM UTF-8
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    $delimitador = ';';

    // Mantém o formato original (Útil, Data/Hora, Mensagem)
    fputcsv($out, ['Útil', 'Data/Hora', 'Mensagem'], $delimitador);

    foreach ($rows as $r) {
        if (($r['util'] ?? '') === 'yes') {
            $utilLabel = 'Sim';
        } elseif (($r['util'] ?? '') === 'no') {
            $utilLabel = 'Não';
        } else {
            $utilLabel = '—';
        }

        fputcsv(
            $out,
            [
                $utilLabel,
                formatDataHoraBrasil($r['created_at'] ?? ''),
                $r['mensagem'] ?? '',
            ],
            $delimitador
        );
    }

    fclose($out);
    exit;
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Para usar em data-* (evita quebras de linha/tab que podem atrapalhar atributos)
function h_data(?string $s): string {
    $v = (string)$s;
    $v = str_replace(["\r", "\n", "\t"], ' ', $v);
    $v = preg_replace('/\s{2,}/', ' ', $v) ?? $v;
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


function self_path(): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $base = basename((string)$path);
    return $base !== '' ? $base : 'listar_feedback.php';
}

function utilLabel(?string $util): string {
    if ($util === 'yes') return 'Sim';
    if ($util === 'no') return 'Não';
    return '—';
}

function utilClass(?string $util): string {
    if ($util === 'yes') return 'badge badge-yes';
    if ($util === 'no')  return 'badge badge-no';
    return 'badge badge-na';
}


function self_file(): string {
    return basename(parse_url($_SERVER['PHP_SELF'], PHP_URL_PATH));
}

/**
 * Monta URL da própria página mesclando/alterando querystring.
 * - $keepCurrent=true mantém os filtros atuais
 * - passe null ou '' para remover a chave
 */
function url_with(array $override = [], bool $keepCurrent = true): string {
    $self  = self_file();
    $query = $keepCurrent ? $_GET : [];

    foreach ($override as $k => $v) {
        if ($v === null || $v === '') {
            unset($query[$k]);
        } else {
            $query[$k] = $v;
        }
    }

    $qs = http_build_query($query);
    return $self . ($qs ? ('?' . $qs) : '');
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <title>Painel de Feedbacks • PRESTEK</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="theme-color" content="#fb923c" />
  <style>
    :root {
      color-scheme: light dark;

      /* Light (default) */
      --bg: #fff7ed;
      --bg-2: #fffbf5;
      --card: rgba(255,255,255,0.86);
      --card-strong: rgba(255,255,255,0.98);
      --border: rgba(148, 163, 184, 0.35);
      --shadow: 0 22px 55px rgba(17, 24, 39, 0.10);
      --text: #0f172a;
      --muted: rgba(15, 23, 42, 0.62);
      --muted-2: rgba(15, 23, 42, 0.45);

      --accent: #f97316;
      --accent-2: #0ea5e9;
      --good: #16a34a;
      --bad: #ef4444;
      --na: #64748b;

      --ring: rgba(249, 115, 22, 0.28);
      --radius: 22px;
    }

    html[data-theme="dark"] {
      --bg: #070a12;
      --bg-2: #0b1020;
      --card: rgba(17, 24, 39, 0.62);
      --card-strong: rgba(17, 24, 39, 0.92);
      --border: rgba(148, 163, 184, 0.28);
      --shadow: 0 22px 55px rgba(0, 0, 0, 0.55);
      --text: #e5e7eb;
      --muted: rgba(229, 231, 235, 0.68);
      --muted-2: rgba(229, 231, 235, 0.48);
      --ring: rgba(56, 189, 248, 0.28);
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
      color: var(--text);
      background:
        radial-gradient(1200px 800px at 10% 0%, rgba(249, 115, 22, 0.22), transparent 60%),
        radial-gradient(900px 600px at 95% 15%, rgba(14, 165, 233, 0.18), transparent 55%),
        radial-gradient(800px 600px at 60% 105%, rgba(22, 163, 74, 0.10), transparent 55%),
        linear-gradient(180deg, var(--bg) 0%, var(--bg-2) 100%);
      padding: 18px;
      min-height: 100vh;
    }

    .wrap {
      max-width: 1200px;
      margin: 0 auto;
      border-radius: calc(var(--radius) + 8px);
      border: 1px solid var(--border);
      background: rgba(255,255,255,0.12);
      backdrop-filter: blur(14px);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    /* Topbar */
    .topbar {
      position: sticky;
      top: 0;
      z-index: 20;
      display: flex;
      gap: 12px;
      align-items: center;
      justify-content: space-between;
      padding: 14px 16px;
      background: linear-gradient(90deg, var(--card-strong), rgba(255,255,255,0.72));
      border-bottom: 1px solid var(--border);
    }
    html[data-theme="dark"] .topbar {
      background: linear-gradient(90deg, var(--card-strong), rgba(17,24,39,0.70));
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      min-width: 220px;
    }

    .logo {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      background: conic-gradient(from 180deg, var(--accent), var(--accent-2), var(--accent));
      color: rgba(15, 23, 42, 0.92);
      font-weight: 900;
      letter-spacing: 0.02em;
      box-shadow: 0 10px 30px rgba(249,115,22,0.28);
    }

    .logo svg { width: 22px; height: 22px; }

    html[data-theme="dark"] .logo {
      color: #020617;
      box-shadow: 0 10px 30px rgba(14,165,233,0.22);
    }

    .brand h1 {
      font-size: 1.02rem;
      margin: 0;
      line-height: 1.15;
      letter-spacing: -0.01em;
    }

    .brand p {
      margin: 2px 0 0;
      font-size: 0.78rem;
      color: var(--muted);
    }

    .actions {
      display: flex;
      align-items: center;
      gap: 10px;
      flex: 1;
      justify-content: flex-end;
      flex-wrap: wrap;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 9px 12px;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: var(--card);
      font-size: 0.82rem;
      color: var(--muted);
    }

    .pill strong { color: var(--text); }

    .search {
      position: relative;
      max-width: 420px;
      width: 100%;
      flex: 1;
    }

    .search input {
      width: 100%;
      padding: 10px 12px 10px 38px;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: var(--card);
      outline: none;
      color: var(--text);
      font-size: 0.92rem;
      transition: box-shadow 160ms ease, border-color 160ms ease, transform 120ms ease;
    }

    .search input:focus {
      border-color: rgba(249, 115, 22, 0.6);
      box-shadow: 0 0 0 4px var(--ring);
      transform: translateY(-1px);
    }

    .search .icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted-2);
      font-size: 0.98rem;
      pointer-events: none;
    }

    .btn {
      border: 1px solid var(--border);
      background: var(--card);
      color: var(--text);
      border-radius: 999px;
      padding: 9px 12px;
      font-weight: 700;
      letter-spacing: 0.02em;
      font-size: 0.78rem;
      text-transform: uppercase;
      text-decoration: none;
      cursor: pointer;
      transition: transform 120ms ease, box-shadow 160ms ease, border-color 160ms ease, background 160ms ease;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
    }

    .btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 14px 30px rgba(15, 23, 42, 0.14);
      border-color: rgba(249, 115, 22, 0.55);
    }

    html[data-theme="dark"] .btn:hover {
      box-shadow: 0 18px 40px rgba(0, 0, 0, 0.5);
      border-color: rgba(14, 165, 233, 0.55);
    }

    .btn-primary {
      border-color: rgba(249, 115, 22, 0.55);
      background: linear-gradient(135deg, rgba(249,115,22,0.95), rgba(251,146,60,0.95));
      color: #0b1220;
      box-shadow: 0 16px 38px rgba(249,115,22,0.25);
    }

    html[data-theme="dark"] .btn-primary {
      color: #020617;
    }

    .btn-primary:hover {
      box-shadow: 0 20px 46px rgba(249,115,22,0.32);
    }

    .btn-ghost {
      background: transparent;
    }

    /* Layout */
    .grid {
      display: grid;
      grid-template-columns: 360px 1fr;
      gap: 14px;
      padding: 14px;
    }

    .panel {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 14px;
      box-shadow: 0 18px 40px rgba(15,23,42,0.08);
    }

    html[data-theme="dark"] .panel {
      box-shadow: 0 18px 40px rgba(0,0,0,0.35);
    }

    .panel h2 {
      margin: 0 0 10px;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--muted);
    }

    .stats {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
      align-items: start;
    }

    /* Distribuição (menos confuso que donut) */
    .dist {
      display: flex;
      flex-direction: column;
      gap: 10px;
      width: 100%;
      min-width: 160px;
    }

    .dist-head {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .dist-total .num {
      font-size: 1.9rem;
      line-height: 1;
      font-weight: 900;
      letter-spacing: -0.03em;
    }

    .dist-total .sub {
      margin-top: 4px;
      font-size: 0.72rem;
      color: var(--muted);
      font-weight: 800;
      letter-spacing: 0.03em;
      text-transform: uppercase;
    }

    .dist-metrics {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: stretch;
      justify-content: flex-end;
      max-width: 100%;
    }

    .metric {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      justify-content: center;
      padding: 8px 10px;
      border-radius: 16px;
      border: 1px solid var(--border);
      background: rgba(255, 255, 255, 0.66);
      flex: 1 1 120px;
      min-width: 120px;
      max-width: 100%;
    }
    html[data-theme="dark"] .metric { background: rgba(17, 24, 39, 0.55); }

    .metric .k {
      font-size: 0.62rem;
      color: var(--muted);
      font-weight: 900;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .metric .v {
      font-size: 0.95rem;
      font-weight: 900;
      letter-spacing: -0.01em;
    }

    .stacked {
      height: 12px;
      border-radius: 999px;
      overflow: hidden;
      display: flex;
      background: rgba(15, 23, 42, 0.08);
      border: 1px solid var(--border);
    }
    html[data-theme="dark"] .stacked { background: rgba(229, 231, 235, 0.14); }

    .seg { width: var(--w); }
    .seg.yes { background: var(--good); }
    .seg.no  { background: var(--bad); }
    .seg.na  { background: var(--na); }

    .dist-foot {
      display: flex;
      gap: 12px;
      justify-content: space-between;
      font-size: 0.72rem;
      color: var(--muted);
      font-weight: 800;
    }


    .donut {
      width: 132px;
      height: 132px;
      border-radius: 50%;
      background: conic-gradient(
        var(--good) 0 var(--p1),
        var(--bad) var(--p1) var(--p2),
        var(--na) var(--p2) 100%
      );
      position: relative;
      box-shadow: 0 18px 40px rgba(15,23,42,0.12);
      border: 10px solid rgba(255,255,255,0.55);
    }

    html[data-theme="dark"] .donut {
      border-color: rgba(17,24,39,0.70);
    }

    .donut::after {
      content: "";
      position: absolute;
      inset: 18px;
      border-radius: 50%;
      background: var(--card-strong);
      border: 1px solid var(--border);
    }

    .donut-center {
      position: absolute;
      inset: 0;
      display: grid;
      place-items: center;
      text-align: center;
      z-index: 1;
      font-weight: 900;
      letter-spacing: -0.02em;
    }

    .donut-center small {
      display: block;
      font-size: 0.70rem;
      color: var(--muted);
      font-weight: 700;
      letter-spacing: 0.02em;
      margin-top: 2px;
    }

    .kpis {
      display: grid;
      gap: 8px;
    }

    .kpi {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 8px;
      padding: 10px 12px;
      border-radius: 16px;
      border: 1px solid var(--border);
      background: rgba(255,255,255,0.55);
    }

    html[data-theme="dark"] .kpi {
      background: rgba(17,24,39,0.60);
    }

    .kpi .label {
      color: var(--muted);
      font-size: 0.82rem;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .dot {
      width: 10px;
      height: 10px;
      border-radius: 999px;
      display: inline-block;
    }

    .dot.good { background: var(--good); }
    .dot.bad  { background: var(--bad); }
    .dot.na   { background: var(--na); }

    .kpi .value {
      font-weight: 900;
      font-size: 0.98rem;
    }

    /* Visão geral: anti-overflow */
    .dist { min-width: 0; }
    .kpis { min-width: 0; }

    @media (max-width: 420px) {
      .dist-metrics { justify-content: flex-start; }
      .metric { flex: 1 1 100%; min-width: 0; align-items: flex-start; }
    }


    /* Filters */
    form.filters {
      display: grid;
      gap: 10px;
    }

    .field label {
      display: block;
      font-size: 0.78rem;
      color: var(--muted);
      margin: 0 0 6px;
    }

    select, input[type="text"] {
      width: 100%;
      padding: 10px 12px;
      border-radius: 16px;
      border: 1px solid var(--border);
      background: var(--card-strong);
      color: var(--text);
      outline: none;
      transition: box-shadow 160ms ease, border-color 160ms ease, transform 120ms ease;
    }

    select:focus, input[type="text"]:focus {
      border-color: rgba(249, 115, 22, 0.60);
      box-shadow: 0 0 0 4px var(--ring);
      transform: translateY(-1px);
    }

    .row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }

    .filters-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
      margin-top: 2px;
    }

    .hint {
      font-size: 0.78rem;
      color: var(--muted);
      line-height: 1.35;
    }

    /* Main list */
    .main {
      display: grid;
      gap: 14px;
      align-content: start;
    }

    .list-head {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: 10px;
    }

    .list-head h2 {
      margin: 0;
      font-size: 1.02rem;
      letter-spacing: -0.01em;
    }

    .list-head .sub {
      color: var(--muted);
      font-size: 0.86rem;
    }

    .list {
      display: grid;
      gap: 10px;
    }

    .card-item {
      border-radius: var(--radius);
      border: 1px solid var(--border);
      background: var(--card-strong);
      padding: 12px 12px;
      transition: transform 120ms ease, box-shadow 160ms ease, border-color 160ms ease;
      box-shadow: 0 14px 28px rgba(15,23,42,0.08);
    }

    html[data-theme="dark"] .card-item {
      background: rgba(17,24,39,0.92);
      box-shadow: 0 18px 35px rgba(0,0,0,0.40);
    }

    .card-item:hover {
      transform: translateY(-2px);
      border-color: rgba(249, 115, 22, 0.40);
      box-shadow: 0 22px 44px rgba(15,23,42,0.12);
    }

    .meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 8px;
    }

    .meta-left {
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: 0;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 5px 10px;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 900;
      letter-spacing: 0.02em;
      border: 1px solid var(--border);
      white-space: nowrap;
    }

    .badge-yes { background: rgba(22, 163, 74, 0.12); border-color: rgba(22, 163, 74, 0.28); color: var(--good); }
    .badge-no  { background: rgba(239, 68, 68, 0.10); border-color: rgba(239, 68, 68, 0.26); color: var(--bad); }
    .badge-na  { background: rgba(100, 116, 139, 0.10); border-color: rgba(100, 116, 139, 0.25); color: var(--na); }

    .id {
      font-size: 0.78rem;
      color: var(--muted);
      font-weight: 700;
      white-space: nowrap;
    }

    .page {
      font-size: 0.82rem;
      color: var(--muted);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-width: 520px;
    }

    .date {
      font-size: 0.82rem;
      color: var(--muted);
      white-space: nowrap;
    }

    .msg {
      margin: 0;
      color: var(--text);
      line-height: 1.45;
      white-space: pre-line;
      overflow-wrap: anywhere;
    }

    details {
      margin-top: 10px;
      border-top: 1px dashed rgba(148, 163, 184, 0.45);
      padding-top: 10px;
    }

    summary {
      cursor: pointer;
      list-style: none;
      color: var(--muted);
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      font-size: 0.72rem;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      user-select: none;
    }

    summary::-webkit-details-marker { display: none; }

    .details-grid {
      display: grid;
      gap: 8px;
      margin-top: 10px;
      color: var(--muted);
      font-size: 0.86rem;
    }

    .kv {
      display: grid;
      grid-template-columns: 120px 1fr;
      gap: 8px;
      padding: 8px 10px;
      border-radius: 16px;
      background: rgba(255,255,255,0.55);
      border: 1px solid var(--border);
    }

    html[data-theme="dark"] .kv {
      background: rgba(17,24,39,0.60);
    }

    .kv b {
      color: var(--text);
      font-size: 0.82rem;
      align-self: start;
    }

    .kv code {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      font-size: 0.82rem;
      color: var(--text);
      background: rgba(15,23,42,0.06);
      padding: 2px 6px;
      border-radius: 10px;
      border: 1px solid rgba(148,163,184,0.22);
      overflow-wrap: anywhere;
    }

    html[data-theme="dark"] .kv code {
      background: rgba(2,6,23,0.45);
    }

    .tools {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 10px;
    }

    .mini {
      padding: 8px 10px;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: var(--card);
      font-size: 0.78rem;
      font-weight: 800;
      cursor: pointer;
    }

    .mini:hover {
      border-color: rgba(249, 115, 22, 0.45);
      transform: translateY(-1px);
    }

    .empty {
      padding: 18px;
      border-radius: var(--radius);
      border: 1px dashed rgba(148, 163, 184, 0.55);
      background: rgba(255,255,255,0.65);
      color: var(--muted);
      text-align: center;
    }

    html[data-theme="dark"] .empty {
      background: rgba(17,24,39,0.65);
    }

    /* Top pages */
    .chips {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 10px;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: rgba(255,255,255,0.62);
      text-decoration: none;
      color: var(--text);
      font-size: 0.82rem;
      max-width: 100%;
    }

    html[data-theme="dark"] .chip {
      background: rgba(17,24,39,0.62);
    }

    .chip:hover {
      border-color: rgba(14,165,233,0.45);
      transform: translateY(-1px);
    }

    .chip .count {
      font-weight: 900;
      color: var(--muted);
      background: rgba(15,23,42,0.06);
      border: 1px solid rgba(148,163,184,0.22);
      padding: 2px 8px;
      border-radius: 999px;
      white-space: nowrap;
    }

    html[data-theme="dark"] .chip .count {
      background: rgba(2,6,23,0.45);
    }

    /* Footer */
    .footer {
      padding: 12px 14px;
      border-top: 1px solid var(--border);
      background: rgba(255,255,255,0.45);
      color: var(--muted);
      display: flex;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }

    html[data-theme="dark"] .footer {
      background: rgba(17,24,39,0.55);
    }

    .footer a {
      color: inherit;
      text-decoration: none;
      font-weight: 800;
    }

    .footer a:hover { text-decoration: underline; }

    /* Responsive */
    @media (max-width: 980px) {
      .grid { grid-template-columns: 1fr; }
      .brand { min-width: auto; }
      .page { max-width: 360px; }
    }

    @media (max-width: 640px) {
      body { padding: 10px; }
      .topbar { align-items: stretch; flex-direction: column; }
      .actions { justify-content: flex-start; }
      .row { grid-template-columns: 1fr; }
      .page { max-width: 260px; }
      .kv { grid-template-columns: 1fr; }
    }

    /* Toast */
    .toast {
      position: fixed;
      left: 50%;
      bottom: 18px;
      transform: translateX(-50%);
      background: rgba(15,23,42,0.90);
      color: #fff;
      padding: 10px 14px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,0.20);
      box-shadow: 0 18px 50px rgba(0,0,0,0.35);
      opacity: 0;
      pointer-events: none;
      transition: opacity 160ms ease, transform 160ms ease;
      z-index: 999;
      font-weight: 800;
      letter-spacing: 0.01em;
      font-size: 0.86rem;
    }

    .toast.show {
      opacity: 1;
      transform: translateX(-50%) translateY(-6px);
    }
  </style>
</head>
<body>
  <div class="wrap">
    <header class="topbar">
      <div class="brand">
        <div class="logo" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5c-1.15 0-2.25-.22-3.26-.62L3 21l1.62-6.24A8.5 8.5 0 1 1 21 11.5Z"/>
            <path d="M9.2 11.7l1.8 1.9 3.9-4.1"/>
          </svg>
        </div>
        <div>
          <h1>Painel de Feedbacks</h1>
          <p>Central de tutoriais • PRESTEK TELECOM</p>
        </div>
      </div>

      <div class="actions">
        <div class="pill" title="Total de feedbacks (com os filtros atuais)">
          <span>Registros:</span>
          <strong><?= $total === 1 ? '1' : (string)$total; ?></strong>
        </div>

        <div class="search">
          <span class="icon">⌕</span>
          <input id="q" type="text" placeholder="Buscar por mensagem, página, IP... (filtro local)" autocomplete="off" />
        </div>

        <button class="btn btn-ghost" id="themeToggle" type="button" title="Alternar tema">
          🌓 Tema
        </button>

        <a class="btn btn-primary" href="<?= h(url_with(['export' => 'csv'])); ?>" title="Exportar os resultados atuais">
          ⬇ Exportar CSV
        </a>

        <a class="btn" href="logout.php" title="Sair do painel">
          ⭠ Sair
        </a>
      </div>
    </header>

    <div class="grid">
      <aside class="side">
        <section class="panel">
          <h2>Visão geral</h2>
          <div class="stats">
            <div class="dist" aria-label="Distribuição de feedbacks">
              <div class="dist-head">
                <div class="dist-total">
                  <div class="num"><?= h((string)$total); ?></div>
                  <div class="sub">feedbacks</div>
                </div>

                <div class="dist-metrics">
                  <div class="metric" title="Respondidos / total">
                    <div class="k">Respondidos</div>
                    <div class="v"><?= h((string)$responseRate); ?>%</div>
                  </div>
                  <div class="metric" title="Úteis / (Úteis + Não úteis)">
                    <div class="k">Taxa útil</div>
                    <div class="v"><?= h((string)$scoreUseful); ?>%</div>
                  </div>
                </div>
              </div>

              <div class="stacked" role="img" aria-label="Úteis <?= h((string)$percYes); ?>%, Não úteis <?= h((string)$percNo); ?>%, Sem resposta <?= h((string)$percNone); ?>%">
                <span class="seg yes" style="--w: <?= h((string)$percYes); ?>%"></span>
                <span class="seg no" style="--w: <?= h((string)$percNo); ?>%"></span>
                <span class="seg na" style="--w: <?= h((string)$percNone); ?>%"></span>
              </div>

              <div class="dist-foot">
                <span><span class="dot good"></span> Úteis</span>
                <span><span class="dot bad"></span> Não úteis</span>
                <span><span class="dot na"></span> Sem resposta</span>
              </div>
            </div>

            <div class="kpis">
              <div class="kpi">
                <div class="label"><span class="dot good"></span> Úteis (<?= h((string)$percYes); ?>%)</div>
                <div class="value"><?= h((string)$totYes); ?></div>
              </div>
              <div class="kpi">
                <div class="label"><span class="dot bad"></span> Não úteis (<?= h((string)$percNo); ?>%)</div>
                <div class="value"><?= h((string)$totNo); ?></div>
              </div>
              <div class="kpi">
                <div class="label"><span class="dot na"></span> Sem resposta (<?= h((string)$percNone); ?>%)</div>
                <div class="value"><?= h((string)$totNone); ?></div>
              </div>
            </div>
          </div>
          <p class="hint" style="margin-top: 10px;">
            Dica: use os filtros abaixo para refinar a consulta (servidor) e a barra de busca para refinar instantaneamente (local).
          </p>
        </section>

        <section class="panel">
          <h2>Filtros (servidor)</h2>
          <form class="filters" method="get">
            <div class="row">
              <div class="field">
                <label>Útil?</label>
                <select name="util">
                  <option value="" <?= $filterUtil === '' ? 'selected' : '' ?>>Todos</option>
                  <option value="yes" <?= $filterUtil === 'yes' ? 'selected' : '' ?>>Sim</option>
                  <option value="no"  <?= $filterUtil === 'no'  ? 'selected' : '' ?>>Não</option>
                </select>
              </div>
              <div class="field">
                <label>Período</label>
                <select name="period">
                  <option value="all" <?= $filterPeriod === 'all' ? 'selected' : '' ?>>Total</option>
                  <option value="7"   <?= $filterPeriod === '7'   ? 'selected' : '' ?>>Últimos 7 dias</option>
                  <option value="15"  <?= $filterPeriod === '15'  ? 'selected' : '' ?>>Últimos 15 dias</option>
                  <option value="30"  <?= $filterPeriod === '30'  ? 'selected' : '' ?>>Últimos 30 dias</option>
                </select>
              </div>
            </div>

            <div class="field">
              <label>Página (contém)</label>
              <input type="text" name="pagina" placeholder="ex: atendimento-whatsapp.html" value="<?= h($filterPage); ?>" />
            </div>

            <div class="filters-actions">
              <button class="btn btn-primary" type="submit">✅ Aplicar</button>
              <a class="btn" href="<?= h(self_path()); ?>" role="button">✕ Limpar</a>
            </div>
          </form>
        </section>

        <section class="panel">
          <h2>Top páginas (deste recorte)</h2>
          <div class="chips">
            <?php if (empty($topPages)): ?>
              <div class="hint">Sem dados suficientes para agrupar.</div>
            <?php else: ?>
              <?php foreach ($topPages as $pg => $count): ?>
                <?php
                  $query = $_GET;
                  $query['pagina'] = ($pg === '(sem página)') ? '' : $pg;
                  $href = url_with(['pagina' => $query['pagina']]);
                ?>
                <a class="chip" href="<?= h($href); ?>" title="Filtrar por esta página">
                  <span style="min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width: 220px;">
                    <?= h($pg); ?>
                  </span>
                  <span class="count"><?= h((string)$count); ?></span>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>
      </aside>

      <main class="main">
        <section class="panel">
          <div class="list-head">
            <div>
              <h2>Feed de feedbacks</h2>
              <div class="sub">Toque em “Detalhes” para ver página, IP e user-agent.</div>
            </div>
            <div class="pill" id="visibleCount" title="Resultados visíveis após busca local">
              <span>Visíveis:</span>
              <strong><?= h((string)$total); ?></strong>
            </div>
          </div>

          <?php if (!$total): ?>
            <div class="empty" style="margin-top: 12px;">Nenhum feedback encontrado com os filtros atuais.</div>
          <?php else: ?>
            <div class="list" id="list">
              <?php foreach ($rows as $r): ?>
                <?php
                  $id = (string)($r['id'] ?? '');
                  $util = (string)($r['util'] ?? '');
                  $msg  = (string)($r['mensagem'] ?? '');
                  $pg   = trim((string)($r['pagina'] ?? ''));
                  $ua   = (string)($r['user_agent'] ?? '');
                  $ip   = (string)($r['ip'] ?? '');
                  $dtBr = formatDataHoraBrasil($r['created_at'] ?? '');
                  if ($pg === '') $pg = '(sem página)';
                ?>
                <article class="card-item fb" 
                         data-util="<?= h_data($util); ?>"
                         data-id="<?= h_data($id); ?>"
                         data-page="<?= h_data($pg); ?>"
                         data-ip="<?= h_data($ip); ?>"
                         data-ua="<?= h_data($ua); ?>"
                         data-msg="<?= h_data($msg); ?>">
                  <div class="meta">
                    <div class="meta-left">
                      <span class="<?= h(utilClass($util)); ?>" title="Útil? <?= h(utilLabel($util)); ?>"><?= h(utilLabel($util)); ?></span>
                      <span class="id">#<?= h($id); ?></span>
                      <span class="page" title="<?= h($pg); ?>"><?= h($pg); ?></span>
                    </div>
                    <div class="date" title="Data/Hora (Brasil)"><?= h($dtBr); ?></div>
                  </div>

                  <p class="msg"><?= nl2br(h($msg)); ?></p>

                  <details>
                    <summary>➕ Detalhes técnicos</summary>
                    <div class="details-grid">
                      <div class="kv"><b>Data/Hora</b><div><code><?= h($dtBr ?: '—'); ?></code></div></div>
                    </div>
                  </details>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </main>
    </div>

    <footer class="footer">
      <span><a href="index.html">⬅ Voltar para a Central de tutoriais</a></span>
      <span>Banco: <code>data/feedback.db</code> • Tabela: <code>feedback</code></span>
    </footer>
  </div>

  <div class="toast" id="toast">Copiado!</div>

  <script>
    (function() {
      // Theme toggle
      const root = document.documentElement;
      const btn = document.getElementById('themeToggle');
      const key = 'prestek_theme';
      const saved = localStorage.getItem(key);
      if (saved === 'dark' || saved === 'light') root.dataset.theme = saved;

      btn?.addEventListener('click', () => {
        const next = (root.dataset.theme === 'dark') ? 'light' : 'dark';
        root.dataset.theme = next;
        localStorage.setItem(key, next);
      });

      // Local search
      const q = document.getElementById('q');
      const list = document.getElementById('list');
      const visible = document.getElementById('visibleCount');
      const items = list ? Array.from(list.querySelectorAll('.fb')) : [];

      function norm(s) {
        return (s || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      }

      function updateCount(n) {
        if (!visible) return;
        const strong = visible.querySelector('strong');
        if (strong) strong.textContent = String(n);
      }

      function applyFilter() {
        const term = norm(q?.value || '');
        let shown = 0;
        for (const el of items) {
          const hay = norm(
            [
              el.dataset.id,
              el.dataset.util,
              el.dataset.page,
              el.dataset.ip,
              el.dataset.ua,
              el.dataset.msg,
            ].join(' | ')
          );
          const ok = term === '' || hay.includes(term);
          el.style.display = ok ? '' : 'none';
          if (ok) shown++;
        }
        updateCount(shown);
      }

      q?.addEventListener('input', applyFilter);

      // Copy buttons
      const toast = document.getElementById('toast');
      let toastT;

      function showToast(text) {
        if (!toast) return;
        toast.textContent = text;
        toast.classList.add('show');
        clearTimeout(toastT);
        toastT = setTimeout(() => toast.classList.remove('show'), 1200);
      }

      async function copyToClipboard(text) {
        try {
          await navigator.clipboard.writeText(text);
          showToast('Copiado!');
        } catch (e) {
          // fallback
          const ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
          showToast('Copiado!');
        }
      }

      document.addEventListener('click', (ev) => {
        const btn = ev.target?.closest?.('[data-copy]');
        if (!btn) return;
        const card = ev.target.closest('.fb');
        if (!card) return;

        const type = btn.getAttribute('data-copy');
        if (type === 'msg') {
          copyToClipboard(card.dataset.msg || '');
          return;
        }

        if (type === 'meta') {
          const lines = [
            `ID: ${card.dataset.id || ''}`,
            `Util: ${card.dataset.util || ''}`,
            `Pagina: ${card.dataset.page || ''}`,
            `IP: ${card.dataset.ip || ''}`,
            `User-Agent: ${card.dataset.ua || ''}`,
          ].join('\n');
          copyToClipboard(lines);
          return;
        }
      });

      // init count
      applyFilter();
    })();
  </script>
</body>
</html>
