<?php
// login.php — PPDA Single Sign-On.
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in · PPDA Digital Hub</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/../assets/css/theme.css') ?: time() ?>" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    html, body { height: 100%; }
    body {
      margin: 0;
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      color: var(--text, #24292b);
      background: var(--bg, #f7f9fa);
    }
    .lg { display: flex; min-height: 100vh; }

    /* ── Left: colourful brand panel ─────────────────────────────── */
    .lg-brand {
      flex: 1 1 56%;
      position: relative;
      overflow: hidden;
      display: flex; flex-direction: column;
      padding: 3rem 3.4rem;
      color: #fff;
      background: linear-gradient(140deg, #1b7a33 0%, #2f9e44 48%, #1f6b3a 100%);
    }
    .lg-brand .photo {
      position: absolute; inset: 0; width: 100%; height: 100%;
      object-fit: cover; opacity: .28; mix-blend-mode: soft-light;
    }
    .lg-brand .blob {
      position: absolute; border-radius: 50%; filter: blur(2px);
      background: radial-gradient(circle at 30% 30%, rgba(255,255,255,.35), rgba(255,255,255,0) 70%);
      pointer-events: none;
    }
    .lg-brand .blob.b1 { width: 340px; height: 340px; top: -120px; right: -80px; }
    .lg-brand .blob.b2 { width: 220px; height: 220px; bottom: -60px; left: 22%; opacity: .7; }

    .lg-brand-top { position: relative; z-index: 2; display: flex; align-items: center; gap: .7rem; }
    .lg-brand-top img { height: 40px; border-radius: 8px; }
    .lg-brand-top span { font-weight: 800; letter-spacing: .01em; font-size: 1.05rem; }

    .lg-brand-mid { position: relative; z-index: 2; margin-top: auto; }
    .lg-brand-mid h1 { font-size: 2.1rem; font-weight: 800; line-height: 1.15; margin: 0 0 .6rem; letter-spacing: -.01em; }
    .lg-brand-mid p  { margin: 0; max-width: 42ch; opacity: .92; font-size: 1rem; }

    .lg-art { position: relative; z-index: 2; margin-top: 1.8rem; max-width: 440px; }
    .lg-art svg { width: 100%; height: auto; display: block; }

    .lg-brand-foot { position: relative; z-index: 2; margin-top: 2rem; font-size: .8rem; opacity: .8; }

    /* ── Right: form ─────────────────────────────────────────────── */
    .lg-form {
      flex: 1 1 44%;
      display: flex; align-items: center; justify-content: center;
      padding: 2.5rem 1.5rem;
      background: var(--surface, #fff);
    }
    .lg-card { width: 100%; max-width: 380px; }
    .lg-card .eyebrow { font-size: .78rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--brand, #2a8f2e); }
    .lg-card h2 { margin: .4rem 0 .3rem; font-size: 1.6rem; font-weight: 800; }
    .lg-card .sub { color: var(--muted, #6b7280); font-size: .92rem; margin: 0 0 1.6rem; }

    .fld { margin-bottom: 1rem; }
    .fld label { display: block; font-size: .82rem; font-weight: 600; color: var(--muted, #6b7280); margin-bottom: .35rem; }
    .fld .box { position: relative; }
    .fld .box i { position: absolute; left: .9rem; top: 50%; transform: translateY(-50%); color: var(--muted, #6b7280); font-size: 1rem; }
    .fld input {
      width: 100%; height: 46px; border: 1px solid var(--border, #e3e7ea);
      border-radius: 11px; padding: 0 1rem 0 2.5rem; font-size: .95rem;
      color: var(--text, #24292b); background: var(--bg, #f7f9fa); outline: none;
      transition: border-color .15s, box-shadow .15s, background .15s;
    }
    .fld input:focus { border-color: var(--brand, #2a8f2e); background: #fff; box-shadow: 0 0 0 3px var(--brand-light, #e6f4e6); }
    .fld .peek { position: absolute; right: .6rem; top: 50%; transform: translateY(-50%); border: 0; background: none; color: var(--muted, #6b7280); cursor: pointer; font-size: 1rem; padding: .3rem; }

    .lg-btn {
      width: 100%; height: 48px; border: 0; border-radius: 11px; cursor: pointer;
      font-size: .98rem; font-weight: 700; color: #fff;
      background: linear-gradient(135deg, #2f9e44, #1c7430);
      box-shadow: 0 12px 24px -12px rgba(28,116,48,.7);
      display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
      transition: transform .12s, box-shadow .12s, opacity .12s;
    }
    .lg-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 16px 30px -12px rgba(28,116,48,.75); }
    .lg-btn:disabled { opacity: .65; cursor: default; }

    .lg-error {
      display: none; margin-bottom: 1rem; padding: .7rem .9rem; border-radius: 10px;
      background: #fdecee; color: #be123c; font-size: .87rem;
      border: 1px solid #f6c9cf;
    }
    .lg-error.show { display: block; }

    .lg-foot { margin-top: 1.6rem; font-size: .8rem; color: var(--muted, #6b7280); text-align: center; }

    .spin { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,.45); border-top-color: #fff; border-radius: 50%; animation: sp .7s linear infinite; }
    @keyframes sp { to { transform: rotate(360deg); } }

    @media (max-width: 880px) {
      .lg { flex-direction: column; }
      .lg-brand { flex: 0 0 auto; padding: 2rem 1.6rem; }
      .lg-brand-mid { margin-top: 1.2rem; }
      .lg-brand-mid h1 { font-size: 1.5rem; }
      .lg-art, .lg-brand-foot { display: none; }
      .lg-form { flex: 1 1 auto; }
    }
  </style>
</head>
<body>
<div class="lg">
  <section class="lg-brand">
    <img class="photo" src="assets/login.jpg" alt="" onerror="this.remove()">
    <span class="blob b1"></span><span class="blob b2"></span>

    <div class="lg-brand-top">
      <img src="logo.jpg" alt="PPDA">
      <span>PPDA Digital Hub</span>
    </div>

    <div class="lg-brand-mid">
      <h1>Public procurement,<br>done in the open.</h1>
      <p>One secure sign-in for e-Memo, e-Services and reporting — used every day by PPDA staff and procuring entities.</p>
    </div>

    <div class="lg-art" aria-hidden="true">
      <svg viewBox="0 0 460 300" xmlns="http://www.w3.org/2000/svg" fill="none">
        <!-- board / document -->
        <rect x="196" y="34" width="210" height="150" rx="12" fill="#ffffff" opacity=".14"/>
        <rect x="196" y="34" width="210" height="150" rx="12" stroke="#ffffff" stroke-opacity=".55" stroke-width="2"/>
        <rect x="214" y="58" width="120" height="10" rx="5" fill="#ffffff" opacity=".55"/>
        <rect x="214" y="82" width="174" height="8" rx="4" fill="#ffffff" opacity=".32"/>
        <rect x="214" y="102" width="150" height="8" rx="4" fill="#ffffff" opacity=".32"/>
        <rect x="214" y="128" width="70" height="26" rx="6" fill="#ffffff" opacity=".7"/>
        <path d="M226 141l7 7 13-15" stroke="#1c7430" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        <!-- ground -->
        <path d="M40 262h380" stroke="#ffffff" stroke-opacity=".4" stroke-width="2" stroke-linecap="round"/>
        <!-- plant -->
        <path d="M74 262c0-24 10-40 22-46" stroke="#ffffff" stroke-opacity=".5" stroke-width="3" stroke-linecap="round"/>
        <path d="M96 216c14-2 24-14 24-30-16 0-27 12-24 30z" fill="#ffffff" opacity=".4"/>
        <path d="M96 224c-13-4-27 2-33 16 15 6 30-1 33-16z" fill="#ffffff" opacity=".28"/>
        <rect x="82" y="256" width="28" height="10" rx="3" fill="#ffffff" opacity=".55"/>
        <!-- person A -->
        <circle cx="150" cy="120" r="20" fill="#ffffff" opacity=".92"/>
        <path d="M120 262v-40a30 30 0 0160 0v40" fill="#ffffff" opacity=".78"/>
        <path d="M172 168l34 22" stroke="#ffffff" stroke-width="12" stroke-linecap="round" opacity=".78"/>
        <!-- person B -->
        <circle cx="300" cy="210" r="17" fill="#ffffff" opacity=".72"/>
        <path d="M276 300v-40a24 24 0 0148 0v40" fill="#ffffff" opacity=".5"/>
        <path d="M292 246l-30 12" stroke="#ffffff" stroke-width="10" stroke-linecap="round" opacity=".5"/>
      </svg>
    </div>

    <div class="lg-brand-foot">&copy; <?= date('Y') ?> Public Procurement &amp; Disposal of Assets Authority</div>
  </section>

  <section class="lg-form">
    <div class="lg-card">
      <div class="eyebrow">Single sign-on</div>
      <h2>Welcome back</h2>
      <p class="sub">Sign in with your PPDA account to continue.</p>

      <div id="errorBox" class="lg-error"></div>

      <form id="loginForm" autocomplete="off">
        <div class="fld">
          <label for="username">Username</label>
          <div class="box">
            <i class="bi bi-person"></i>
            <input id="username" name="username" type="text" required autofocus placeholder="firstname.lastname">
          </div>
        </div>
        <div class="fld">
          <label for="password">Password</label>
          <div class="box">
            <i class="bi bi-lock"></i>
            <input id="password" name="password" type="password" required placeholder="••••••••">
            <button type="button" class="peek" id="peekBtn" aria-label="Show password"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <button id="loginBtn" type="submit" class="lg-btn">
          <span id="btnLabel"><i class="bi bi-box-arrow-in-right"></i> Sign in</span>
        </button>
      </form>

      <div class="lg-foot">Trouble signing in? Contact your PPDA administrator.</div>
    </div>
  </section>
</div>

<script>
  var form = document.getElementById('loginForm');
  var btn = document.getElementById('loginBtn');
  var btnLabel = document.getElementById('btnLabel');
  var errorBox = document.getElementById('errorBox');

  document.getElementById('peekBtn').addEventListener('click', function () {
    var p = document.getElementById('password');
    var show = p.type === 'password';
    p.type = show ? 'text' : 'password';
    this.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
  });

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    errorBox.classList.remove('show');
    btn.disabled = true;
    btnLabel.innerHTML = '<span class="spin"></span> Signing in…';

    var body = new URLSearchParams();
    body.append('username', form.username.value.trim());
    body.append('password', form.password.value.trim());

    try {
      var res = await fetch('login_handler.php', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      });
      var data = await res.json();
      if (data.success) {
        window.location.href = data.redirect || 'index.php';
        return;
      }
      throw new Error(data.message || 'Sign-in failed.');
    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.classList.add('show');
      btn.disabled = false;
      btnLabel.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Sign in';
    }
  });
</script>
</body>
</html>
