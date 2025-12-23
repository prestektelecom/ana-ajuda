<?php
// acesso-feedback.php (login do painel de feedbacks)
// Autenticação via SQLite (data/feedback.db)

declare(strict_types=1);

session_start();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

// Se já estiver logado, vai direto para o painel
if (is_logged_in()) {
    header('Location: listar_feedback.php');
    exit;
}

$loginError = '';
$needsSetup = false;

try {
    $pdo = db();
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins WHERE is_active = 1");
    $activeAdmins = (int)$stmt->fetchColumn();
    if ($activeAdmins === 0) {
        $needsSetup = true;
    }
} catch (Throwable $e) {
    $needsSetup = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = (string)($_POST['user'] ?? '');
    $pass = (string)($_POST['pass'] ?? '');

    if (attempt_login($user, $pass)) {
        header('Location: listar_feedback.php');
        exit;
    }

    $loginError = 'Usuário ou senha inválidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <title>Login • Feedbacks PRESTEK</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex, nofollow" />
  <meta name="theme-color" content="#FFF7ED" />

  <style>
    :root {
      color-scheme: light;

      /* Base (Opção C modernizada) */
      --bg: #fff7ed;          /* amber-50 */
      --bg2: #fef3c7;         /* amber-100 */
      --card: rgba(255,255,255,0.78);
      --card-solid: #ffffff;
      --text: #111827;        /* gray-900 */
      --muted: #6b7280;       /* gray-500 */
      --border: rgba(17,24,39,0.12);

      --primary: #f97316;     /* orange-500 */
      --primary2: #ea580c;    /* orange-600 */
      --accent: #0ea5e9;      /* sky-500 */
      --ok: #16a34a;
      --danger: #dc2626;

      --shadow: 0 22px 60px rgba(17, 24, 39, 0.16);
      --shadow-soft: 0 10px 28px rgba(17, 24, 39, 0.12);
      --radius: 24px;
    }

    * { box-sizing: border-box; }
    html, body { height: 100%; }

    body {
      margin: 0;
      font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, "Noto Sans", "Apple Color Emoji", "Segoe UI Emoji";
      color: var(--text);
      background:
        radial-gradient(900px 520px at 14% 20%, rgba(14,165,233,0.18), transparent 60%),
        radial-gradient(820px 500px at 78% 16%, rgba(249,115,22,0.20), transparent 58%),
        linear-gradient(180deg, var(--bg) 0%, #ffffff 55%, var(--bg2) 130%);
      padding: 20px;
      display: grid;
      place-items: center;
    }

    /* “Noise” leve para dar textura (sem imagens externas) */
    body::before {
      content: "";
      position: fixed;
      inset: 0;
      pointer-events: none;
      background-image:
        radial-gradient(rgba(17,24,39,0.05) 1px, transparent 1px);
      background-size: 24px 24px;
      opacity: 0.22;
      mix-blend-mode: multiply;
    }

    .shell {
      width: min(1020px, 100%);
      position: relative;
    }

    .card {
      border-radius: var(--radius);
      overflow: hidden;
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      background: var(--card);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      min-height: 520px;
      position: relative;
    }

    /* brilho suave */
    .card::after {
      content: "";
      position: absolute;
      inset: 0;
      pointer-events: none;
      background:
        radial-gradient(560px 240px at 18% 8%, rgba(255,255,255,0.80), transparent 60%),
        radial-gradient(420px 200px at 90% 10%, rgba(255,255,255,0.55), transparent 62%);
      opacity: 0.55;
    }

    .visual {
      position: relative;
      padding: 30px 32px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 18px;
      background:
        radial-gradient(700px 460px at 8% 18%, rgba(14,165,233,0.18), transparent 62%),
        radial-gradient(760px 520px at 70% 0%, rgba(249,115,22,0.20), transparent 60%),
        linear-gradient(180deg, rgba(255,255,255,0.55) 0%, rgba(255,255,255,0.22) 100%);
      border-right: 1px solid rgba(17,24,39,0.10);
      z-index: 1;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .mark {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      font-weight: 900;
      letter-spacing: 0.02em;
      color: #fff;
      background: linear-gradient(135deg, var(--primary), var(--accent));
      box-shadow: 0 14px 32px rgba(249,115,22,0.25);
      border: 1px solid rgba(255,255,255,0.65);
    }

    .brand .kicker {
      font-size: 12px;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: rgba(17,24,39,0.58);
      font-weight: 700;
      margin-bottom: 2px;
    }

    .brand .name {
      font-size: 16px;
      font-weight: 800;
      letter-spacing: 0.02em;
    }

    .headline {
      max-width: 420px;
    }

    .headline h1 {
      margin: 0 0 8px 0;
      font-size: 30px;
      line-height: 1.1;
      letter-spacing: -0.02em;
    }

    .headline p {
      margin: 0;
      color: rgba(17,24,39,0.70);
      font-size: 15px;
      line-height: 1.5;
    }

    .chips {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 16px;
    }

    .chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 999px;
      background: rgba(255,255,255,0.65);
      border: 1px solid rgba(17,24,39,0.10);
      box-shadow: var(--shadow-soft);
      font-size: 12.5px;
      color: rgba(17,24,39,0.82);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }

    .dot {
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: var(--ok);
      box-shadow: 0 0 0 4px rgba(22,163,74,0.12);
      flex: 0 0 auto;
    }

    .bullets {
      margin: 18px 0 0 0;
      padding: 0;
      list-style: none;
      display: grid;
      gap: 10px;
    }

    .bullets li {
      display: flex;
      gap: 10px;
      align-items: flex-start;
      color: rgba(17,24,39,0.76);
      font-size: 13.5px;
      line-height: 1.45;
    }

    .bullets svg {
      margin-top: 2px;
      flex: 0 0 auto;
      opacity: 0.95;
    }

    .visual .foot {
      display: flex;
      justify-content: space-between;
      gap: 14px;
      align-items: center;
      color: rgba(17,24,39,0.60);
      font-size: 12.5px;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 999px;
      border: 1px dashed rgba(17,24,39,0.22);
      background: rgba(255,255,255,0.40);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }

    .form {
      padding: 30px 28px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 16px;
      position: relative;
      z-index: 1;
    }

    .form h2 {
      margin: 0;
      font-size: 22px;
      letter-spacing: -0.02em;
    }

    .form .sub {
      margin: 0;
      color: rgba(17,24,39,0.65);
      font-size: 14px;
      line-height: 1.5;
    }

    .alert {
      border: 1px solid rgba(220,38,38,0.22);
      background: rgba(220,38,38,0.08);
      color: #7f1d1d;
      border-radius: 14px;
      padding: 10px 12px;
      font-size: 13px;
      display: flex;
      gap: 10px;
      align-items: flex-start;
      animation: pop 180ms ease-out;
    }

    @keyframes pop {
      from { transform: translateY(-4px); opacity: 0.0; }
      to { transform: translateY(0); opacity: 1.0; }
    }

    .field {
      display: grid;
      gap: 8px;
      margin-top: 8px;
    }

    label {
      font-size: 13px;
      font-weight: 700;
      color: rgba(17,24,39,0.74);
    }

    .input {
      display: flex;
      align-items: center;
      gap: 10px;
      border-radius: 16px;
      padding: 12px 12px;
      background: rgba(255,255,255,0.90);
      border: 1px solid rgba(17,24,39,0.12);
      box-shadow: 0 10px 26px rgba(17,24,39,0.08);
      transition: transform 120ms ease, box-shadow 180ms ease, border-color 180ms ease;
    }

    .input:focus-within {
      border-color: rgba(249,115,22,0.60);
      box-shadow: 0 14px 32px rgba(249,115,22,0.16);
      transform: translateY(-1px);
    }

    .input svg {
      opacity: 0.75;
      flex: 0 0 auto;
    }

    input[type="text"],
    input[type="password"] {
      width: 100%;
      border: 0;
      outline: 0;
      background: transparent;
      font-size: 15px;
      color: var(--text);
    }

    input::placeholder { color: rgba(107,114,128,0.90); }

    .reveal {
      border: 0;
      background: transparent;
      cursor: pointer;
      padding: 6px;
      border-radius: 12px;
      display: grid;
      place-items: center;
      color: rgba(17,24,39,0.65);
      transition: background 120ms ease, transform 120ms ease;
    }

    .reveal:hover { background: rgba(17,24,39,0.06); transform: translateY(-1px); }

    .hint {
      display: flex;
      gap: 10px;
      align-items: center;
      margin-top: 8px;
      color: rgba(17,24,39,0.58);
      font-size: 12.5px;
    }

    .btn {
      margin-top: 12px;
      width: 100%;
      border: 0;
      cursor: pointer;
      border-radius: 16px;
      padding: 12px 14px;
      font-weight: 900;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #fff;
      background: linear-gradient(135deg, var(--primary), var(--primary2));
      box-shadow: 0 18px 40px rgba(249,115,22,0.28);
      transition: transform 120ms ease, box-shadow 180ms ease, filter 180ms ease;
      position: relative;
      overflow: hidden;
    }

    .btn::after {
      content: "";
      position: absolute;
      inset: 0;
      background: radial-gradient(120px 60px at 25% 0%, rgba(255,255,255,0.55), transparent 60%);
      opacity: 0.55;
      transform: translateY(-30%);
      pointer-events: none;
    }

    .btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 22px 54px rgba(249,115,22,0.34);
      filter: brightness(1.03);
    }

    .btn:active { transform: translateY(0px); }

    .row {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      align-items: center;
      margin-top: 12px;
      flex-wrap: wrap;
    }

    .link {
      color: rgba(17,24,39,0.72);
      text-decoration: none;
      font-weight: 800;
      font-size: 13px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 12px;
      border-radius: 14px;
      border: 1px solid rgba(17,24,39,0.10);
      background: rgba(255,255,255,0.55);
      box-shadow: 0 10px 24px rgba(17,24,39,0.08);
      transition: transform 120ms ease, box-shadow 180ms ease, border-color 180ms ease;
    }

    .link:hover {
      transform: translateY(-1px);
      border-color: rgba(249,115,22,0.35);
      box-shadow: 0 14px 32px rgba(17,24,39,0.10);
    }

    .footer {
      margin-top: 14px;
      color: rgba(17,24,39,0.58);
      font-size: 12.5px;
      line-height: 1.45;
    }

    .caps {
      display: none;
      margin-top: 10px;
      border: 1px solid rgba(245,158,11,0.35);
      background: rgba(245,158,11,0.10);
      color: rgba(120,53,15,0.95);
      border-radius: 14px;
      padding: 10px 12px;
      font-size: 12.5px;
      gap: 10px;
      align-items: flex-start;
    }

    .caps.show { display: flex; }

    /* Responsivo */
    @media (max-width: 880px) {
      .card { grid-template-columns: 1fr; }
      .visual { border-right: 0; border-bottom: 1px solid rgba(17,24,39,0.10); }
      .headline h1 { font-size: 26px; }
      .card { min-height: unset; }
    }

    @media (max-width: 420px) {
      body { padding: 12px; }
      .visual, .form { padding: 22px 18px; }
      .headline h1 { font-size: 24px; }
    }

    /* Respeita usuários sensíveis a animações */
    @media (prefers-reduced-motion: reduce) {
      * { scroll-behavior: auto !important; }
      .btn, .link, .input { transition: none !important; }
      .alert { animation: none !important; }
    }
  </style>
</head>
<body>
  <div class="shell">
    <div class="card" role="region" aria-label="Login de acesso aos feedbacks">

      <aside class="visual" aria-hidden="false">
        <div>
          <div class="brand">
            <div class="mark" aria-hidden="true">PT</div>
            <div>
              <div class="kicker">Portal interno</div>
              <div class="name">PRESTEK TELECOM</div>
            </div>
          </div>

          <div class="headline" style="margin-top: 18px;">
            <h1>Feedbacks da Central de Ajuda</h1>
            <p>Veja o que está funcionando, onde estão as dúvidas e quais artigos precisam de melhoria — sem perder tempo.</p>

            <div class="chips" aria-label="Recursos do painel">
              <span class="chip"><span class="dot" aria-hidden="true"></span> Acesso restrito</span>
              <span class="chip">Filtro por período</span>
              <span class="chip">Exportação CSV</span>
            </div>

            <ul class="bullets" aria-label="Dicas rápidas">
              <li>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path d="M20 7L10 17L4 11" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Identifique rapidamente os temas mais citados nos comentários.
              </li>
              <li>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="2.2"/>
                </svg>
                Acompanhe tendência por horário/dia e priorize o que dói mais.
              </li>
              <li>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path d="M12 3v12" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                  <path d="M8 11l4 4 4-4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M5 21h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
                Exporte e compartilhe com os responsáveis por cada área.
              </li>
            </ul>
          </div>
        </div>

        <div class="foot">
          <span>Somente colaboradores autorizados.</span>
          <span class="pill">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M12 1.5l8 4.5v6c0 5.25-3.5 10-8 11.25C7.5 22 4 17.25 4 12V6l8-4.5Z" stroke="currentColor" stroke-width="2"/>
              <path d="M9.5 12l1.8 1.8L15.8 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Sessão segura
          </span>
        </div>
      </aside>

      <section class="form">
        <div>
          <h2>Entrar</h2>
          <p class="sub">Use o usuário interno definido pela TI para acessar o painel de feedbacks.</p>
        </div>



        <?php if ($needsSetup): ?>
          <div class="alert" role="alert" aria-live="polite" style="border-color: rgba(234, 88, 12, .35); background: rgba(251, 146, 60, .16); color: #7c2d12;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M12 9v4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
              <path d="M12 17h.01" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"/>
              <path d="M10.29 3.86h3.42a2 2 0 0 1 1.8 1.1l6.02 12.04A2 2 0 0 1 19.74 20H4.26a2 2 0 0 1-1.79-2.99L8.5 4.96a2 2 0 0 1 1.79-1.1Z" stroke="currentColor" stroke-width="2"/>
            </svg>
            <div>
              <strong>Primeiro acesso:</strong> nenhum admin ativo foi cadastrado no banco.
              <div style="margin-top:6px">
                Rode <code>setup_admin.php</code> uma vez para criar o usuário e depois <strong>apague</strong> esse arquivo do servidor.
              </div>
              <div style="margin-top:10px">
                <a href="setup_admin.php" style="display:inline-flex; align-items:center; gap:.5rem; padding:.55rem .85rem; border-radius:999px; border:1px solid rgba(234,88,12,.25); background: rgba(255,255,255,.65); color:#7c2d12; text-decoration:none; font-weight:800;">
                  Abrir setup
                  <span aria-hidden="true">↗</span>
                </a>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($loginError): ?>
          <div class="alert" role="alert" aria-live="polite">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M12 9v4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
              <path d="M12 17h.01" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"/>
              <path d="M10.3 3.5h3.4l8 14.3a2 2 0 0 1-1.74 3H4.04a2 2 0 0 1-1.74-3L10.3 3.5Z" stroke="currentColor" stroke-width="2"/>
            </svg>
            <span><?= htmlspecialchars($loginError); ?></span>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>
          <div class="field">
            <label for="user">Usuário</label>
            <div class="input">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M20 21a8 8 0 0 0-16 0" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                <path d="M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="2.2"/>
              </svg>
              <input type="text" id="user" name="user" autocomplete="username" required placeholder="ex: prestek" />
            </div>
          </div>

          <div class="field">
            <label for="pass">Senha</label>
            <div class="input">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M7 11V8a5 5 0 0 1 10 0v3" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                <path d="M6 11h12a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2.2"/>
              </svg>
              <input type="password" id="pass" name="pass" autocomplete="current-password" required placeholder="Informe sua senha" />
              <button class="reveal" type="button" id="togglePass" aria-label="Mostrar senha">
                <svg id="eye" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke="currentColor" stroke-width="2"/>
                  <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2"/>
                </svg>
              </button>
            </div>

            <div class="caps" id="capsWarn" role="status" aria-live="polite">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M12 2l7 7H5l7-7Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                <path d="M12 9v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M9 20h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
              <span><strong>Caps Lock</strong> parece estar ativado.</span>
            </div>

            <div class="hint">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z" stroke="currentColor" stroke-width="2"/>
                <path d="M12 16v-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M12 8h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
              </svg>
              Dica: se não lembrar o acesso, fale com a TI PRESTEK.
            </div>
          </div>

          <button type="submit" class="btn">Entrar</button>
        </form>

        <div class="row">
          <a class="link" href="index.html" aria-label="Voltar para a Central">
            <span aria-hidden="true">←</span> Voltar para a Central
          </a>
          <a class="link" href="listar_feedback.php" aria-label="Ir para o painel (se já tiver acesso)">
            Painel <span aria-hidden="true">↗</span>
          </a>
        </div>

        <div class="footer">
          Em caso de dúvidas sobre acesso, contate o time de <strong>TI PRESTEK</strong>.
        </div>
      </section>
    </div>
  </div>

  <script>
    (function () {
      const pass = document.getElementById('pass');
      const toggle = document.getElementById('togglePass');
      const caps = document.getElementById('capsWarn');
      const user = document.getElementById('user');

      // foco inicial
      if (user && !user.value) user.focus();

      // Mostrar/ocultar senha
      if (toggle && pass) {
        toggle.addEventListener('click', () => {
          const isPwd = pass.type === 'password';
          pass.type = isPwd ? 'text' : 'password';
          toggle.setAttribute('aria-label', isPwd ? 'Ocultar senha' : 'Mostrar senha');
        });
      }

      // Aviso de Caps Lock
      const updateCaps = (e) => {
        if (!caps) return;
        const on = e.getModifierState && e.getModifierState('CapsLock');
        caps.classList.toggle('show', !!on);
      };
      if (pass) {
        pass.addEventListener('keyup', updateCaps);
        pass.addEventListener('keydown', updateCaps);
      }
    })();
  </script>
</body>
</html>
