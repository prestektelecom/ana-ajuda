<?php
// listar_feedback.php

session_start();

/**
 * CONFIGURAÇÃO DE ACESSO
 * Troque pelo usuário/senha que você quiser
 */
const ADMIN_USER = 'prestek';
const ADMIN_PASS = 'AnaAjuda2024!'; // troque por uma senha forte

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: listar_feedback.php');
    exit;
}

// Se ainda não está logado, mostra tela de login
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $loginError = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = $_POST['user'] ?? '';
        $pass = $_POST['pass'] ?? '';

        if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
            $_SESSION['logged_in'] = true;
            header('Location: listar_feedback.php');
            exit;
        } else {
            $loginError = 'Usuário ou senha inválidos.';
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
      <meta charset="utf-8" />
      <title>Login • Feedbacks PRESTEK</title>
      <meta name="viewport" content="width=device-width, initial-scale=1" />
      <style>
        :root {
          color-scheme: dark;
          --bg: #020617;
          --card: #020617;
          --border: #1f2937;
          --text: #e5e7eb;
          --text-soft: #9ca3af;
          --accent-blue: #22d3ee;
          --accent-gold: #facc6b;
        }

        * {
          box-sizing: border-box;
          margin: 0;
          padding: 0;
        }

        body {
          font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
          background: radial-gradient(circle at top, #020617 0, #020617 55%, #000 100%);
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
          color: var(--text);
        }

        .card {
          width: 100%;
          max-width: 360px;
          padding: 22px 20px 20px;
          border-radius: 18px;
          background: rgba(15, 23, 42, 0.96);
          border: 1px solid var(--border);
          box-shadow: 0 18px 40px rgba(0, 0, 0, 0.9);
        }

        h1 {
          font-size: 1.15rem;
          margin-bottom: 6px;
          display: flex;
          align-items: center;
          gap: 8px;
        }

        h1 span {
          font-size: 1.4rem;
        }

        p {
          font-size: 0.85rem;
          color: var(--text-soft);
          margin-bottom: 14px;
        }

        label {
          display: block;
          font-size: 0.8rem;
          margin-bottom: 4px;
          color: var(--text-soft);
        }

        input {
          width: 100%;
          padding: 8px 10px;
          border-radius: 999px;
          border: 1px solid var(--border);
          background: #020617;
          color: var(--text);
          font-size: 0.85rem;
          outline: none;
          margin-bottom: 10px;
        }

        input:focus {
          border-color: var(--accent-blue);
          box-shadow: 0 0 0 2px rgba(34, 211, 238, 0.35);
        }

        .btn {
          width: 100%;
          margin-top: 4px;
          padding: 8px 12px;
          border-radius: 999px;
          border: 1px solid rgba(148, 163, 184, 0.8);
          background: linear-gradient(135deg, var(--accent-gold), #fbbf24);
          color: #111827;
          font-weight: 600;
          font-size: 0.85rem;
          cursor: pointer;
          transition: transform 0.1s ease, box-shadow 0.15s ease;
        }

        .btn:hover {
          transform: translateY(-1px);
          box-shadow: 0 12px 26px rgba(250, 204, 107, 0.5);
        }

        .error {
          font-size: 0.78rem;
          color: #f97316;
          margin-bottom: 6px;
        }

        .hint {
          margin-top: 10px;
          font-size: 0.75rem;
          color: var(--text-soft);
        }
      </style>
    </head>
    <body>
      <div class="card">
        <h1><span>🔐</span>Área restrita</h1>
        <p>Entre com as credenciais internas para visualizar os feedbacks dos tutoriais.</p>

        <?php if ($loginError): ?>
          <div class="error"><?= htmlspecialchars($loginError); ?></div>
        <?php endif; ?>

        <form method="post">
          <label for="user">Usuário</label>
          <input type="text" id="user" name="user" autocomplete="off" required />

          <label for="pass">Senha</label>
          <input type="password" id="pass" name="pass" autocomplete="off" required />

          <button type="submit" class="btn">Entrar</button>
        </form>

        <div class="hint">
          Compartilhe este acesso apenas com colaboradores da PRESTEK.
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

/* ================================
   AQUI COMEÇA A PÁGINA LOGADA
   ================================ */

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

    // filtro útil? (sim/não)
    if ($filterUtil === 'yes' || $filterUtil === 'no') {
        $sql .= " AND util = :util";
        $params[':util'] = $filterUtil;
    }

    // filtro por página
    if ($filterPage !== '') {
        $sql .= " AND pagina LIKE :pagina";
        $params[':pagina'] = '%' . $filterPage . '%';
    }

    // filtro por período (7 / 15 / 30 dias) - baseando em agora (Brasília)
    $startDate = null;
    if (in_array($filterPeriod, ['7', '15', '30'], true)) {
        $days = (int) $filterPeriod;

        // "Agora" em horário de Brasília
        $agoraBr = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
        $inicioBr = $agoraBr->modify("-{$days} days");

        // Como o created_at está em UTC, convertemos esse início para UTC
        $inicioUtc = $inicioBr->setTimezone(new DateTimeZone('UTC'));

        $startDate = $inicioUtc->format('Y-m-d H:i:s'); // formato do banco
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

/**
 * Converte timestamp salvo em UTC para horário de Brasília
 * e formata em dd/mm/aaaa HH:MM.
 */
function formatDataHoraBrasil(?string $ts): string
{
    if (!$ts) {
        return '';
    }

    try {
        // valor vindo do SQLite (CURRENT_TIMESTAMP → UTC, via salvar_feedback.php)
        $dt = new DateTime($ts, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));

        return $dt->format('d/m/Y H:i');
    } catch (Exception $e) {
        // se algo vier fora de formato, devolve cru
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

// EXPORT CSV (usa os MESMOS filtros já aplicados na tela)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'feedbacks-simples-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');

    // BOM para o Excel reconhecer UTF-8
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // No Brasil o Excel costuma usar ponto e vírgula
    $delimitador = ';';

    // Cabeçalho: só Útil, Data/Hora e Mensagem
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
                $utilLabel,                                   // Útil? (Sim / Não / —)
                formatDataHoraBrasil($r['created_at'] ?? ''), // Data/Hora em horário de Brasília
                $r['mensagem']  ?? '',                        // Mensagem
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
      --bg: #020617;
      --bg-soft: #020617;
      --card-bg: #020617;
      --border: #1f2937;
      --text: #e5e7eb;
      --text-soft: #9ca3af;
      --accent-blue: #22d3ee;
      --accent-gold: #facc6b;
      --accent-green: #22c55e;
      --accent-orange: #fb923c;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: radial-gradient(circle at top, #020617 0, #020617 55%, #000 100%);
      color: var(--text);
      min-height: 100vh;
      padding: 24px 16px 32px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .page {
      width: 100%;
      max-width: 1120px;
    }

    header {
      margin-bottom: 18px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
    }

    .title-block {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .logo-icon {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      background: conic-gradient(from 210deg, #22d3ee, #4ade80, #facc6b, #22d3ee);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 12px 30px rgba(15, 23, 42, 0.9);
    }

    .logo-icon span {
      font-size: 1.4rem;
    }

    header h1 {
      font-size: 1.35rem;
      font-weight: 600;
    }

    .subtitle {
      font-size: 0.78rem;
      color: var(--text-soft);
    }

    .subtitle a {
      color: var(--accent-blue);
      text-decoration: none;
      margin-left: 6px;
    }
    .subtitle a:hover {
      color: var(--accent-gold);
      text-decoration: underline;
    }

    .badge-count {
      padding: 6px 14px;
      border-radius: 999px;
      background: rgba(15, 23, 42, 0.96);
      border: 1px solid rgba(34, 211, 238, 0.7);
      font-size: 0.78rem;
      color: var(--accent-blue);
      white-space: nowrap;
    }

    .stats-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 10px;
      font-size: 0.78rem;
    }

    .stat-pill {
      padding: 6px 10px;
      border-radius: 999px;
      background: rgba(15, 23, 42, 0.96);
      border: 1px solid var(--border);
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: var(--text-soft);
    }

    .stat-dot {
      width: 8px;
      height: 8px;
      border-radius: 999px;
    }

    .dot-yes  { background: var(--accent-green); }
    .dot-no   { background: var(--accent-orange); }
    .dot-none { background: #64748b; }

    .filters {
      margin-bottom: 16px;
      padding: 12px 14px;
      border-radius: 18px;
      background: rgba(15, 23, 42, 0.96);
      border: 1px solid var(--border);
      display: flex;
      flex-wrap: wrap;
      gap: 10px 14px;
      align-items: center;
      font-size: 0.85rem;
    }

    .filters label {
      display: flex;
      align-items: center;
      gap: 6px;
      color: var(--text-soft);
    }

    .filters select,
    .filters input {
      background: #020617;
      border-radius: 999px;
      border: 1px solid var(--border);
      color: var(--text);
      padding: 7px 12px;
      font-size: 0.8rem;
      outline: none;
      min-width: 160px;
    }

    .filters input::placeholder {
      color: #64748b;
    }

    .filters button {
      padding: 7px 14px;
      border-radius: 999px;
      border: 1px solid rgba(148, 163, 184, 0.8);
      background: rgba(15, 23, 42, 0.95);
      color: var(--text);
      font-size: 0.8rem;
      cursor: pointer;
      transition: background 0.15s ease, transform 0.1s ease, box-shadow 0.15s ease;
    }

    .filters button:hover {
      background: rgba(34, 211, 238, 0.15);
      box-shadow: 0 8px 18px rgba(0, 0, 0, 0.9);
      transform: translateY(-1px);
    }

    .filters .btn-clear {
      border-color: rgba(148, 163, 184, 0.6);
      background: rgba(15, 23, 42, 0.9);
    }

    .filters .btn-export {
      border-color: rgba(250, 204, 107, 0.9);
      background: linear-gradient(135deg, var(--accent-gold), #fbbf24);
      color: #0b1120;
    }

    .filters .btn-export:hover {
      box-shadow: 0 10px 24px rgba(250, 204, 107, 0.55);
    }

    .table-wrapper {
      border-radius: 20px;
      border: 1px solid var(--border);
      background: rgba(15, 23, 42, 0.98);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.9);
      overflow: hidden;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.8rem;
    }

    thead {
      background: #020617;
    }

    th, td {
      padding: 8px 10px;
      border-bottom: 1px solid #111827;
      vertical-align: top;
      text-align: left;
    }

    th {
      font-weight: 600;
      color: var(--text-soft);
      font-size: 0.78rem;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    tbody tr:nth-child(even) {
      background: rgba(15, 23, 42, 0.98);
    }

    tbody tr:nth-child(odd) {
      background: rgba(15, 23, 42, 0.94);
    }

    tbody tr:hover {
      background: rgba(15, 23, 42, 0.9);
    }

    .col-id    { width: 40px;  }
    .col-util  { width: 80px;  white-space: nowrap; }
    .col-pag   { width: 130px; }
    .col-data  { width: 150px; white-space: nowrap; }
    .col-ip    { width: 110px; white-space: nowrap; }
    .col-ua    { width: 260px; }

    .util-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 3px 8px;
      border-radius: 999px;
      font-size: 0.75rem;
      border: 1px solid transparent;
    }

    .util-sim {
      border-color: rgba(34, 197, 94, 0.7);
      color: #bbf7d0;
      background: rgba(22, 163, 74, 0.15);
    }

    .util-nao {
      border-color: rgba(249, 115, 22, 0.7);
      color: #fed7aa;
      background: rgba(248, 113, 22, 0.1);
    }

    .util-nd {
      border-color: rgba(148, 163, 184, 0.5);
      color: var(--text-soft);
      background: rgba(15, 23, 42, 0.8);
    }

    .mensagem {
      white-space: pre-line;
      color: var(--text);
    }

    .muted {
      color: var(--text-soft);
      font-size: 0.75rem;
    }

    .ua-text {
      font-size: 0.72rem;
      color: var(--text-soft);
      word-break: break-all;
      line-height: 1.35;
    }

    .empty {
      padding: 16px;
      text-align: center;
      color: var(--text-soft);
    }

    .top-links {
      margin-top: 12px;
      font-size: 0.8rem;
      color: var(--text-soft);
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 6px;
      align-items: center;
    }

    .top-links a {
      color: var(--accent-blue);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .top-links a:hover {
      text-decoration: underline;
      color: var(--accent-gold);
    }

    @media (max-width: 800px) {
      .table-wrapper {
        overflow-x: auto;
      }
    }

    @media (max-width: 640px) {
      header {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
</head>
<body>
  <div class="page">
    <header>
      <div>
        <div class="title-block">
          <div class="logo-icon">
            <span>📊</span>
          </div>
          <div>
            <h1>Feedbacks dos tutoriais</h1>
            <div class="subtitle">
              Acesso interno PRESTEK
              <a href="listar_feedback.php?logout=1">Sair</a>
            </div>
          </div>
        </div>
      </div>
      <span class="badge-count">
        <?= $total === 1 ? '1 registro' : $total . ' registros'; ?>
      </span>
    </header>

    <div class="stats-bar">
      <div class="stat-pill">
        <span class="stat-dot dot-yes"></span>
        Úteis (Sim): <strong><?= $totYes; ?></strong>
      </div>
      <div class="stat-pill">
        <span class="stat-dot dot-no"></span>
        Não úteis: <strong><?= $totNo; ?></strong>
      </div>
      <div class="stat-pill">
        <span class="stat-dot dot-none"></span>
        Sem resposta: <strong><?= $totNone; ?></strong>
      </div>
    </div>

    <div class="filters">
      <form method="get" style="display:flex; flex-wrap:wrap; gap:10px 14px; align-items:center;">
        <label>
          Útil?
          <select name="util">
            <option value="">Todos</option>
            <option value="yes" <?= $filterUtil === 'yes' ? 'selected' : '' ?>>Sim</option>
            <option value="no"  <?= $filterUtil === 'no'  ? 'selected' : '' ?>>Não</option>
          </select>
        </label>

        <label>
          Página:
          <input
            type="text"
            name="pagina"
            placeholder="ex: index.html ou atendimento-whatsapp.html"
            value="<?= htmlspecialchars($filterPage); ?>"
          />
        </label>

        <label>
          Período:
          <select name="period">
            <option value="all" <?= $filterPeriod === 'all' ? 'selected' : '' ?>>Total</option>
            <option value="7"   <?= $filterPeriod === '7'   ? 'selected' : '' ?>>Últimos 7 dias</option>
            <option value="15"  <?= $filterPeriod === '15'  ? 'selected' : '' ?>>Últimos 15 dias</option>
            <option value="30"  <?= $filterPeriod === '30'  ? 'selected' : '' ?>>Últimos 30 dias</option>
          </select>
        </label>

        <button type="submit">Aplicar filtros</button>
        <button type="submit" name="export" value="csv" class="btn-export">
          Exportar CSV
        </button>
        <button type="button" class="btn-clear" onclick="window.location.href='listar_feedback.php'">
          Limpar
        </button>
      </form>
    </div>

    <div class="table-wrapper">
      <?php if (!$total): ?>
        <div class="empty">
          Nenhum feedback encontrado com os filtros atuais.
        </div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th class="col-id">ID</th>
              <th class="col-util">Útil?</th>
              <th>Mensagem</th>
              <th class="col-pag">Página</th>
              <th class="col-data">Data/Hora</th>
              <th class="col-ip">IP</th>
              <th class="col-ua">User Agent</th>
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
                <td class="col-pag"><?= htmlspecialchars($r['pagina'] ?? ''); ?></td>
                <td class="col-data"><?= htmlspecialchars(formatDataHoraBrasil($r['created_at'] ?? '')); ?></td>
                <td class="col-ip"><?= htmlspecialchars($r['ip'] ?? ''); ?></td>
                <td class="col-ua">
                  <div class="ua-text"><?= htmlspecialchars($r['user_agent'] ?? ''); ?></div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="top-links">
      <span>
        <a href="index.html">
          ⬅ Voltar para a Central de tutoriais
        </a>
      </span>
      <span class="muted">
        Banco: <code>data/feedback.db</code> • Tabela: <code>feedback</code>
      </span>
    </div>
  </div>
</body>
</html>
