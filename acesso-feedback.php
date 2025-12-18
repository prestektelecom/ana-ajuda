<?php
// acesso-feedback.php (página "oculta" de login para acessar os feedbacks)

session_start();

/**
 * CONFIGURAÇÃO DE ACESSO
 */
const ADMIN_USER = 'prestek';
const ADMIN_PASS = 'AnaAjuda@2025!';

// Se já estiver logado, vai direto para o painel
if (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: listar_feedback.php');
    exit;
}

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';

    if ($user === ADMIN_USER && $pass === ADMIN_PASS) {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
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
  <style>
    :root {
      color-scheme: dark;

      --bg-page: #020617;
      --bg-card: rgba(15, 23, 42, 0.96);

      --navy-900: #020617;
      --navy-700: #020617;
      --navy-500: #1f2937;

      --accent-blue: #0ea5e9;
      --accent-teal: #06b6d4;
      --accent-orange: #f97316;

      --border-soft: rgba(148, 163, 184, 0.5);
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
      min-height: 100vh;
      background:
        radial-gradient(circle at top left, #1e293b 0%, #020617 45%, #020617 100%);
      color: var(--text-main);
      padding: 24px 16px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .login-layout {
      width: 100%;
      max-width: 980px;
      min-height: 420px;
      background:
        radial-gradient(circle at top left, rgba(15, 23, 42, 0.98), rgba(15, 23, 42, 0.94));
      border-radius: 24px;
      border: 1px solid rgba(148, 163, 184, 0.5);
      box-shadow: 0 30px 80px rgba(15, 23, 42, 0.9);
      display: grid;
      grid-template-columns: 1.1fr 1fr;
      overflow: hidden;
      backdrop-filter: blur(18px);
    }

    /* LADO BRAND / ILUSTRAÇÃO */

    .login-hero {
      position: relative;
      padding: 32px 32px 28px;
      background:
        linear-gradient(145deg, var(--navy-900) 0%, #0b1120 45%, var(--accent-blue) 120%);
      color: #f9fafb;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .hero-top-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
    }

    .hero-logo-mark {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .hero-logo-circle {
      width: 38px;
      height: 38px;
      border-radius: 12px;
      background: var(--accent-blue);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1rem;
      color: #0f172a;
      box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.8);
    }

    .hero-logo-text {
      display: flex;
      flex-direction: column;
      gap: 0;
    }

    .hero-logo-text span:nth-child(1) {
      font-size: 0.8rem;
      letter-spacing: 0.09em;
      text-transform: uppercase;
      opacity: 0.75;
    }

    .hero-logo-text span:nth-child(2) {
      font-size: 0.98rem;
      font-weight: 600;
    }

    .hero-chip {
      padding: 4px 10px;
      border-radius: 999px;
      border: 1px solid rgba(248, 250, 252, 0.22);
      font-size: 0.75rem;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(15, 23, 42, 0.4);
    }

    .hero-chip-dot {
      width: 8px;
      height: 8px;
      border-radius: 999px;
      background: #22c55e;
    }

    .hero-main {
      margin-top: 36px;
      max-width: 320px;
    }

    .hero-title {
      font-size: 1.4rem;
      font-weight: 600;
      margin-bottom: 8px;
    }

    .hero-subtitle {
      font-size: 0.9rem;
      opacity: 0.85;
    }

    .hero-tags {
      margin-top: 22px;
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      font-size: 0.75rem;
    }

    .hero-tag {
      padding: 4px 10px;
      border-radius: 999px;
      background: rgba(15, 23, 42, 0.8);
      border: 1px solid rgba(249, 250, 251, 0.08);
    }

    .hero-footer {
      margin-top: 36px;
      font-size: 0.76rem;
      opacity: 0.8;
    }

    .hero-footer strong {
      color: var(--accent-teal);
    }

    .hero-shape {
      position: absolute;
      inset: auto -40px -70px auto;
      width: 210px;
      height: 210px;
      border-radius: 40% 60% 65% 35%;
      background: radial-gradient(circle at 20% 20%, #38bdf8 0%, #0ea5e9 45%, transparent 70%);
      opacity: 0.35;
      pointer-events: none;
    }

    /* LADO FORMULÁRIO */

    .login-panel {
      padding: 28px 28px 24px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 16px;
      background: rgba(15, 23, 42, 0.96);
      border-left: 1px solid rgba(15, 23, 42, 0.9);
    }

    .panel-header {
      margin-bottom: 10px;
    }

    .panel-kicker {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.14em;
      color: var(--accent-teal);
      font-weight: 600;
      margin-bottom: 4px;
    }

    .panel-title {
      font-size: 1.3rem;
      font-weight: 600;
      color: #f9fafb;
      margin-bottom: 4px;
    }

    .panel-subtitle {
      font-size: 0.88rem;
      color: var(--text-muted);
    }

    .login-body {
      margin-top: 4px;
    }

    .field-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 12px;
    }

    label {
      font-size: 0.82rem;
      color: var(--text-muted);
    }

    input[type="text"],
    input[type="password"] {
      width: 100%;
      padding: 10px 13px;
      border-radius: 999px;
      border: 1px solid var(--border-soft);
      background: #020617;
      color: var(--text-main);
      font-size: 0.9rem;
      outline: none;
      transition:
        border 0.15s ease,
        box-shadow 0.15s ease,
        background 0.15s ease,
        transform 0.08s ease;
    }

    input::placeholder {
      color: #6b7280;
    }

    input:focus {
      border-color: var(--accent-blue);
      background: #020617;
      box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.28);
      transform: translateY(-1px);
    }

    .btn-primary {
      width: 100%;
      margin-top: 6px;
      padding: 11px 14px;
      border-radius: 999px;
      border: none;
      background: linear-gradient(135deg, var(--accent-teal), var(--accent-blue));
      color: #ffffff;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      transition:
        transform 0.1s ease,
        box-shadow 0.15s ease,
        filter 0.15s ease;
      box-shadow: 0 16px 40px rgba(8, 47, 73, 0.7);
    }

    .btn-primary:hover {
      filter: brightness(1.05);
      box-shadow: 0 20px 48px rgba(8, 47, 73, 0.9);
      transform: translateY(-1px);
    }

    

        .btn-secondary {
          width: 100%;
          margin-top: 10px;
          padding: 11px 14px;
          border-radius: 999px;
          border: 1px solid rgba(148, 163, 184, 0.55);
          background: transparent;
          color: var(--text-main);
          font-weight: 600;
          font-size: 0.9rem;
          cursor: pointer;
          letter-spacing: 0.02em;
          text-transform: uppercase;
          text-decoration: none;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          transition:
            transform 0.1s ease,
            box-shadow 0.15s ease,
            border-color 0.15s ease;
        }

        .btn-secondary:hover {
          border-color: var(--accent-blue);
          box-shadow: 0 14px 34px rgba(8, 47, 73, 0.35);
          transform: translateY(-1px);
        }
.login-footer {
      margin-top: 14px;
      font-size: 0.78rem;
      color: var(--text-muted);
    }

    .login-footer strong {
      color: var(--accent-blue);
    }

    .error {
      margin-bottom: 8px;
      font-size: 0.8rem;
      color: #fecaca;
      background: rgba(127, 29, 29, 0.3);
      border: 1px solid rgba(248, 113, 113, 0.65);
      border-radius: 999px;
      padding: 6px 10px;
    }

    @media (max-width: 860px) {
      .login-layout {
        grid-template-columns: 1fr;
        max-width: 520px;
      }
      .login-hero {
        min-height: 180px;
        padding: 24px 22px 22px;
      }
      .hero-main {
        margin-top: 18px;
      }
      .login-panel {
        padding: 22px 20px 20px;
      }
    }

    @media (max-width: 480px) {
      body {
        padding: 12px;
      }
      .login-layout {
        border-radius: 18px;
      }
      .hero-title {
        font-size: 1.2rem;
      }
    }
  </style>
</head>
<body>
  <div class="login-layout">
    <aside class="login-hero">
      <div class="hero-top-row">
        <div class="hero-logo-mark">
          <div class="hero-logo-circle">PT</div>
          <div class="hero-logo-text">
            <span>Portal interno</span>
            <span>PRESTEK TELECOM</span>
          </div>
        </div>
        <div class="hero-chip">
          <span class="hero-chip-dot"></span>
          <span>Feedbacks em tempo real</span>
        </div>
      </div>

      <div class="hero-main">
        <div class="hero-title">Entenda como os tutoriais estão ajudando o time.</div>
        <div class="hero-subtitle">
          Acompanhe os comentários, identifique oportunidades de melhoria
          e fortaleça o conhecimento interno.
        </div>

        <div class="hero-tags">
          <span class="hero-tag">Análises rápidas</span>
          <span class="hero-tag">Exportação CSV</span>
          <span class="hero-tag">Visão por período</span>
        </div>
      </div>

      <div class="hero-footer">
        Acesso exclusivo para colaboradores autorizados da <strong>PRESTEK</strong>.
      </div>

      <div class="hero-shape"></div>
    </aside>

    <section class="login-panel">
      <div class="panel-header">
        <div class="panel-kicker">Área restrita</div>
        <div class="panel-title">Login para ver feedbacks</div>
        <div class="panel-subtitle">
          Utilize o usuário interno definido pela TI para acessar o painel de feedbacks.
        </div>
      </div>

      <?php if ($loginError): ?>
        <div class="error"><?= htmlspecialchars($loginError); ?></div>
      <?php endif; ?>

      <form method="post" class="login-body" action="acesso-feedback.php">
        <div class="field-group">
          <label for="user">Usuário</label>
          <input type="text" id="user" name="user" autocomplete="username" required placeholder="ex: prestek" />
        </div>

        <div class="field-group">
          <label for="pass">Senha</label>
          <input type="password" id="pass" name="pass" autocomplete="current-password" required placeholder="Informe sua senha" />
        </div>

        <button type="submit" class="btn-primary">Entrar</button>
      </form>

      <a href="index.html" class="btn-secondary">Voltar para a Central</a>


      <div class="login-footer">
        Em caso de dúvidas sobre acesso, contate o time de <strong>TI PRESTEK</strong>.
      </div>
    </section>
  </div>
</body>
</html>
