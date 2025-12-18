<?php
// listar_feedback.php

session_start();

// Logout (derruba a sessão e volta para o login)
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: acesso-feedback.php');
    exit;
}

// Protege a página: quem não estiver logado vai para o login "oculto"
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: acesso-feedback.php');
    exit;
}

// AQUI COMEÇA A PÁGINA LOGADA
// ================================

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
    if ($r['util'] === 'yes') {
        $totYes++;
    } elseif ($r['util'] === 'no') {
        $totNo++;
    } else {
        $totNone++;
    }
}

// EXPORT CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'feedbacks-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');

    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    $delimitador = ';';

    fputcsv($out, ['Útil', 'Data/Hora', 'Mensagem'], $delimitador);

    foreach ($rows as $r) {
        if ($r['util'] === 'yes') {
            $utilLabel = 'Sim';
        } elseif ($r['util'] === 'no') {
            $utilLabel = 'Não';
        } else {
            $utilLabel = '—';
        }

        fputcsv(
            $out,
            [
                $utilLabel,
                formatDataHoraBrasil($r['created_at'] ?? ''),
                $r['mensagem']  ?? '',
            ],
            $delimitador
        );
    }

    fclose($out);
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <title>Feedbacks • PRESTEK TELECOM</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root {
      color-scheme: dark;

      --bg-page: #020617;
      --bg-card: rgba(15, 23, 42, 0.96);

      --navy-900: #020617;
      --navy-800: #020617;
      --navy-600: #1f2937;

      --accent-blue: #0ea5e9;
      --accent-teal: #06b6d4;
      --accent-orange: #f97316;

      --border-soft: rgba(148, 163, 184, 0.45);
      --border-strong: rgba(51, 65, 85, 0.9);

      --text-main: #e5e7eb;
      --text-muted: #9ca3af;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background:
        radial-gradient(circle at top left, #1e293b 0%, #020617 40%, #020617 100%);
      color: var(--text-main);
      padding: 16px;
    }

    .app-frame {
      max-width: 1200px;
      margin: 0 auto;
      border-radius: 24px;
      background:
        radial-gradient(circle at top left, rgba(15, 23, 42, 0.98), rgba(15, 23, 42, 0.94));
      box-shadow: 0 30px 80px rgba(15, 23, 42, 0.9);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      min-height: calc(100vh - 32px);
      border: 1px solid rgba(148, 163, 184, 0.45);
      backdrop-filter: blur(18px);
    }

    /* HEADER SUPERIOR */

    .app-header {
      background: linear-gradient(120deg, #020617, #020617 45%, #0f172a 100%);
      color: #f9fafb;
      padding: 14px 22px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }

    .header-left {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .header-logo {
      width: 36px;
      height: 36px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--accent-teal), var(--accent-blue));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      font-weight: 700;
      color: #0f172a;
      box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.9);
    }

    .header-text {
      display: flex;
      flex-direction: column;
      gap: 1px;
    }

    .header-title {
      font-size: 1.1rem;
      font-weight: 600;
    }

    .header-subtitle {
      font-size: 0.78rem;
      opacity: 0.85;
    }

    .header-subtitle span {
      opacity: 0.95;
      font-weight: 500;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .header-pill {
      padding: 5px 12px;
      border-radius: 999px;
      font-size: 0.78rem;
      border: 1px solid rgba(148, 163, 184, 0.6);
      background: rgba(15, 23, 42, 0.85);
      white-space: nowrap;
      color: var(--text-muted);
    }

    .header-pill strong {
      color: var(--accent-teal);
    }

    .btn-logout {
      border-radius: 999px;
      padding: 7px 14px;
      border: 1px solid rgba(148, 163, 184, 0.7);
      background: #020617;
      color: #e5e7eb;
      font-size: 0.8rem;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      transition:
        background 0.15s ease,
        box-shadow 0.15s ease,
        transform 0.08s ease,
        border-color 0.15s ease;
    }

    .btn-logout span {
      font-size: 0.8rem;
    }

    .btn-logout:hover {
      background: #020617;
      border-color: var(--accent-blue);
      box-shadow: 0 14px 36px rgba(15, 23, 42, 0.9);
      transform: translateY(-1px);
    }

    /* CONTEÚDO PRINCIPAL */

    .app-main {
      padding: 18px 22px 20px;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    /* LINHA DE CARDS SUPERIORES */

    .top-row {
      display: grid;
      grid-template-columns: minmax(0, 1.4fr) minmax(0, 2fr);
      gap: 14px;
    }

    .card {
      background: var(--bg-card);
      border-radius: 16px;
      border: 1px solid var(--border-soft);
      padding: 12px 14px 12px;
      font-size: 0.8rem;
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.7);
    }

    .card-title {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-muted);
      margin-bottom: 6px;
    }

    /* RESUMO */

    .stats-row {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
    }

    .stat-box {
      padding: 8px 10px;
      border-radius: 12px;
      background: radial-gradient(circle at top left, rgba(56, 189, 248, 0.25), rgba(15, 23, 42, 0.96));
      border: 1px solid rgba(56, 189, 248, 0.6);
      display: flex;
      flex-direction: column;
      gap: 2px;
    }

    .stat-box:nth-child(2) {
      background: radial-gradient(circle at top left, rgba(129, 140, 248, 0.32), rgba(15, 23, 42, 0.96));
      border-color: rgba(129, 140, 248, 0.75);
    }

    .stat-box:nth-child(3) {
      background: radial-gradient(circle at top left, rgba(148, 163, 184, 0.3), rgba(15, 23, 42, 0.96));
      border-color: rgba(148, 163, 184, 0.7);
    }

    .stat-label {
      font-size: 0.76rem;
      color: var(--text-muted);
    }

    .stat-value {
      font-size: 1.05rem;
      font-weight: 600;
      color: #e5e7eb;
    }

    /* FILTROS */

    .filters-header {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: 8px;
      margin-bottom: 6px;
    }

    .filters-sub {
      font-size: 0.74rem;
      color: var(--text-muted);
    }

    .filters form {
      display: flex;
      flex-wrap: wrap;
      gap: 10px 12px;
      align-items: flex-end;
    }

    .filters label {
      display: flex;
      flex-direction: column;
      gap: 4px;
      font-size: 0.76rem;
      color: var(--text-muted);
      min-width: 150px;
    }

    .filters select,
    .filters input {
      background: #020617;
      border-radius: 999px;
      border: 1px solid var(--border-soft);
      color: var(--text-main);
      padding: 6px 10px;
      font-size: 0.78rem;
      outline: none;
      transition:
        border 0.15s ease,
        box-shadow 0.15s ease,
        background 0.15s ease,
        transform 0.08s ease;
    }

    .filters input::placeholder {
      color: #6b7280;
    }

    .filters select:focus,
    .filters input:focus {
      border-color: var(--accent-blue);
      background: #020617;
      box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.3);
      transform: translateY(-1px);
    }

    .filters button {
      padding: 7px 12px;
      border-radius: 999px;
      border: 1px solid var(--border-soft);
      background: #020617;
      color: var(--text-main);
      font-size: 0.78rem;
      cursor: pointer;
      transition:
        background 0.15s ease,
        transform 0.1s ease,
        box-shadow 0.15s ease,
        border-color 0.15s ease;
      white-space: nowrap;
    }

    .filters button:hover {
      background: #020617;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.9);
      transform: translateY(-1px);
      border-color: var(--accent-blue);
    }

    .filters .btn-export {
      background: linear-gradient(135deg, var(--accent-teal), var(--accent-blue));
      border-color: var(--accent-blue);
      color: #f9fafb;
      box-shadow: 0 14px 32px rgba(8, 47, 73, 0.9);
    }

    .filters .btn-export:hover {
      background: linear-gradient(135deg, var(--accent-blue), var(--accent-teal));
    }

    .filters .btn-clear {
      border-color: var(--border-strong);
    }

    /* TABELA */

    .table-card {
      border-radius: 16px;
      background: var(--bg-card);
      border: 1px solid var(--border-soft);
      box-shadow: 0 24px 50px rgba(15, 23, 42, 0.9);
      overflow: hidden;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.8rem;
    }

    thead {
      background: rgba(15, 23, 42, 0.98);
    }

    th, td {
      padding: 8px 11px;
      border-bottom: 1px solid rgba(30, 41, 59, 0.95);
      text-align: left;
      vertical-align: top;
    }

    th {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-muted);
    }

    tbody tr:nth-child(odd) {
      background: rgba(15, 23, 42, 0.96);
    }

    tbody tr:nth-child(even) {
      background: rgba(15, 23, 42, 0.9);
    }

    tbody tr:hover {
      background: rgba(30, 64, 175, 0.35);
    }

    .col-id    { width: 50px; }
    .col-util  { width: 110px; white-space: nowrap; }
    .col-data  { width: 160px; white-space: nowrap; }

    .util-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 3px 10px;
      border-radius: 999px;
      font-size: 0.74rem;
      border: 1px solid transparent;
      min-width: 64px;
    }

    .util-sim {
      border-color: rgba(34, 197, 94, 0.7);
      background: rgba(22, 163, 74, 0.22);
      color: #bbf7d0;
      font-weight: 500;
    }

    .util-nao {
      border-color: rgba(248, 113, 113, 0.8);
      background: rgba(127, 29, 29, 0.35);
      color: #fecaca;
      font-weight: 500;
    }

    .util-nd {
      border-color: rgba(148, 163, 184, 0.8);
      background: rgba(30, 41, 59, 0.9);
      color: var(--text-muted);
    }

    .mensagem {
      white-space: pre-line;
      color: var(--text-main);
      line-height: 1.5;
    }

    .empty {
      padding: 14px;
      text-align: center;
      color: var(--text-muted);
      font-size: 0.82rem;
      background: rgba(15, 23, 42, 0.96);
    }

    /* FOOTER */

    .app-footer {
      padding: 10px 18px 14px;
      font-size: 0.78rem;
      color: var(--text-muted);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 8px;
      border-top: 1px solid rgba(30, 41, 59, 0.95);
      background: linear-gradient(
        to right,
        rgba(15, 23, 42, 0.98),
        rgba(30, 64, 175, 0.55)
      );
    }

    .app-footer a {
      color: var(--accent-blue);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-weight: 500;
    }

    .app-footer a:hover {
      text-decoration: underline;
    }

    code {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      font-size: 0.78rem;
    }

    @media (max-width: 900px) {
      .top-row {
        grid-template-columns: minmax(0, 1fr);
      }
    }

    @media (max-width: 720px) {
      body {
        padding: 8px;
      }
      .app-frame {
        min-height: calc(100vh - 16px);
      }
      .app-header {
        flex-direction: column;
        align-items: flex-start;
      }
      .header-right {
        width: 100%;
        justify-content: space-between;
      }
    }

    @media (max-width: 600px) {
      .filters label {
        min-width: 100%;
      }
      .app-main {
        padding: 14px 12px 14px;
      }
      .app-footer {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
</head>
<body>
  <div class="app-frame">
    <header class="app-header">
      <div class="header-left">
        <div class="header-logo">FB</div>
        <div class="header-text">
          <span class="header-title">Feedbacks dos tutoriais</span>
          <span class="header-subtitle">
            Painel interno • <span>PRESTEK TELECOM</span>
          </span>
        </div>
      </div>

      <div class="header-right">
        <div class="header-pill">
          <?= $total === 1 ? '1 registro recebido' : $total . ' registros recebidos'; ?>
        </div>
        <a href="listar_feedback.php?logout=1" class="btn-logout">
          <span>⭠</span> Sair
        </a>
      </div>
    </header>

    <main class="app-main">
      <section class="top-row">
        <div class="card">
          <div class="card-title">Visão geral</div>
          <div class="stats-row">
            <div class="stat-box">
              <div class="stat-label">Úteis</div>
              <div class="stat-value"><?= $totYes; ?></div>
            </div>
            <div class="stat-box">
              <div class="stat-label">Não úteis</div>
              <div class="stat-value"><?= $totNo; ?></div>
            </div>
            <div class="stat-box">
              <div class="stat-label">Sem resposta</div>
              <div class="stat-value"><?= $totNone; ?></div>
            </div>
          </div>
        </div>

        <div class="card filters">
          <div class="filters-header">
            <div class="card-title">Filtros do painel</div>
            <div class="filters-sub">Combine utilidade, página e período para refinar a análise.</div>
          </div>

          <form method="get">
            <label>
              Útil?
              <select name="util">
                <option value="">Todos</option>
                <option value="yes" <?= $filterUtil === 'yes' ? 'selected' : '' ?>>Sim</option>
                <option value="no"  <?= $filterUtil === 'no'  ? 'selected' : '' ?>>Não</option>
              </select>
            </label>

            <label>
              Página
              <input
                type="text"
                name="pagina"
                placeholder="ex: index.html ou atendimento-whatsapp.html"
                value="<?= htmlspecialchars($filterPage); ?>"
              />
            </label>

            <label>
              Período
              <select name="period">
                <option value="all" <?= $filterPeriod === 'all' ? 'selected' : '' ?>>Total</option>
                <option value="7"   <?= $filterPeriod === '7'   ? 'selected' : '' ?>>Últimos 7 dias</option>
                <option value="15"  <?= $filterPeriod === '15'  ? 'selected' : '' ?>>Últimos 15 dias</option>
                <option value="30"  <?= $filterPeriod === '30'  ? 'selected' : '' ?>>Últimos 30 dias</option>
              </select>
            </label>

            <button type="submit">Aplicar filtros</button>

            <button type="submit" name="export" value="csv" class="btn-export">
              <span>⬇</span> Exportar CSV
            </button>

            <button
              type="button"
              class="btn-clear"
              onclick="window.location.href='listar_feedback.php'">
              <span>✕</span> Limpar
            </button>
          </form>
        </div>
      </section>

      <section class="table-card">
        <?php if (!$total): ?>
          <div class="empty">
            Nenhum feedback encontrado com os filtros atuais.
          </div>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Útil?</th>
                <th>Mensagem</th>
                <th>Data/Hora</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td class="col-id"><?= htmlspecialchars($r['id']); ?></td>

                  <td class="col-util">
                    <?php
                      if ($r['util'] === 'yes') {
                        echo '<span class="util-pill util-sim">Sim</span>';
                      } elseif ($r['util'] === 'no') {
                        echo '<span class="util-pill util-nao">Não</span>';
                      } else {
                        echo '<span class="util-pill util-nd">—</span>';
                      }
                    ?>
                  </td>

                  <td>
                    <div class="mensagem">
                      <?= nl2br(htmlspecialchars($r['mensagem'] ?? '')); ?>
                    </div>
                  </td>

                  <td class="col-data">
                    <?= htmlspecialchars(formatDataHoraBrasil($r['created_at'] ?? '')); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>
    </main>

    <footer class="app-footer">
      <span>
        <a href="index.html">⬅ Voltar para a Central de tutoriais</a>
      </span>
      <span>
        Banco: <code>data/feedback.db</code> • Tabela: <code>feedback</code>
      </span>
    </footer>
  </div>
</body>
</html>
