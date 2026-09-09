<?php
// index.php — PPDA Digital Hub landing (app picker).
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user    = htmlspecialchars($_SESSION['username'] ?? '');
$isAdmin = (($_SESSION['role'] ?? '') === 'admin');

$apps = [
    ['key' => 'ememo',    'launch' => "launchApp('ememo')",      'name' => 'e-Memo',        'desc' => 'Internal memos, minutes &amp; approvals',       'icon' => 'bi-file-earmark-text-fill', 'tone' => 'memo'],
    ['key' => 'eservice', 'launch' => "launchApp('eservice')",   'name' => 'e-Services',    'desc' => 'Supplier register &amp; bid analysis',           'icon' => 'bi-diagram-3-fill',        'tone' => 'svc'],
    ['key' => 'reports',  'launch' => "launchApp('reports')",    'name' => 'Reports',       'desc' => 'Operational &amp; compliance analytics',         'icon' => 'bi-graph-up-arrow',        'tone' => 'rpt'],
];
if ($isAdmin) {
    $apps[] = ['key' => 'admin', 'launch' => "location.href='admin/index.php'", 'name' => 'Administration', 'desc' => 'Users, roles, reference data &amp; security', 'icon' => 'bi-sliders', 'tone' => 'adm'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PPDA Digital Hub</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/../assets/css/theme.css') ?: time() ?>" rel="stylesheet">
  <style>
    :root {
      --hub-memo-1:#2f9e44; --hub-memo-2:#1c7430;
      --hub-svc-1:#3b5bdb;  --hub-svc-2:#2b3fa8;
      --hub-rpt-1:#7c3aed;  --hub-rpt-2:#5b21b6;
      --hub-adm-1:#e8590c;  --hub-adm-2:#b64405;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      background:
        radial-gradient(900px 380px at 12% -8%, #e9f6ec 0%, rgba(233,246,236,0) 70%),
        radial-gradient(760px 360px at 100% 0%, #eaf0ff 0%, rgba(234,240,255,0) 65%),
        var(--bg, #f7f9fa);
      color: var(--text, #24292b);
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    }

    .hub-nav {
      display: flex; align-items: center; gap: 1rem;
      padding: .8rem 1.6rem;
      background: rgba(255,255,255,.72);
      backdrop-filter: saturate(180%) blur(8px);
      border-bottom: 1px solid var(--border, #e3e7ea);
      position: sticky; top: 0; z-index: 20;
    }
    .hub-nav img { height: 34px; }
    .hub-nav .who { margin-left: auto; color: var(--muted, #6b7280); font-size: .9rem; }
    .hub-nav .who strong { color: var(--text, #24292b); }
    .hub-logout {
      border: 1px solid var(--border, #e3e7ea); background: #fff; color: var(--text, #24292b);
      border-radius: 999px; padding: .4rem .9rem; font-size: .85rem; font-weight: 600; cursor: pointer;
      display: inline-flex; align-items: center; gap: .4rem;
    }
    .hub-logout:hover { border-color: #c0392b; color: #c0392b; }

    .hub-wrap { max-width: 1080px; margin: 0 auto; padding: 2.4rem 1.5rem 3.5rem; }

    .hub-hero {
      position: relative; overflow: hidden;
      border-radius: 22px;
      padding: 2.4rem 2.2rem;
      color: #fff;
      background: linear-gradient(125deg, #2f9e44 0%, #1c7430 55%, #185f28 100%);
      box-shadow: 0 18px 40px -18px rgba(28,116,48,.55);
      margin-bottom: 2rem;
    }
    .hub-hero::after {
      content: ""; position: absolute; inset: 0;
      background:
        radial-gradient(280px 280px at 88% -30%, rgba(255,255,255,.22), transparent 70%),
        radial-gradient(240px 240px at 108% 120%, rgba(255,255,255,.12), transparent 70%);
      pointer-events: none;
    }
    .hub-hero h1 { margin: 0 0 .35rem; font-size: 1.85rem; font-weight: 800; letter-spacing: -.01em; }
    .hub-hero p  { margin: 0 0 1.4rem; opacity: .92; font-size: 1rem; max-width: 46ch; }
    .hub-search {
      position: relative; max-width: 420px;
    }
    .hub-search i {
      position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #1c7430; font-size: 1rem;
    }
    .hub-search input {
      width: 100%; height: 46px; border: 0; border-radius: 12px;
      padding: 0 1rem 0 2.6rem; font-size: .95rem; color: var(--text, #24292b);
      background: #fff; box-shadow: 0 8px 24px -10px rgba(0,0,0,.28); outline: none;
    }

    .hub-grid {
      display: grid; gap: 1.15rem;
      grid-template-columns: repeat(auto-fill, minmax(248px, 1fr));
    }
    .hub-app {
      --c1: var(--hub-memo-1); --c2: var(--hub-memo-2);
      display: flex; flex-direction: column; gap: .55rem;
      position: relative; overflow: hidden;
      padding: 1.35rem 1.35rem 1.2rem;
      background: var(--surface, #fff);
      border: 1px solid var(--border, #e3e7ea);
      border-radius: 18px;
      text-decoration: none; color: var(--text, #24292b);
      box-shadow: 0 1px 2px rgba(16,24,32,.05);
      transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
      cursor: pointer;
    }
    .hub-app::before {
      content: ""; position: absolute; left: 0; top: 0; right: 0; height: 4px;
      background: linear-gradient(90deg, var(--c1), var(--c2));
    }
    .hub-app:hover {
      transform: translateY(-4px);
      border-color: transparent;
      box-shadow: 0 22px 40px -20px color-mix(in srgb, var(--c1) 55%, transparent);
    }
    .hub-app .ico {
      width: 46px; height: 46px; border-radius: 13px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.5rem; color: #fff;
      background: linear-gradient(135deg, var(--c1), var(--c2));
      box-shadow: 0 8px 18px -8px color-mix(in srgb, var(--c1) 70%, transparent);
    }
    .hub-app h3 { margin: .25rem 0 0; font-size: 1.08rem; font-weight: 750; }
    .hub-app p  { margin: 0; color: var(--muted, #6b7280); font-size: .88rem; line-height: 1.4; flex: 1; }
    .hub-app .go {
      margin-top: .4rem; font-weight: 700; font-size: .84rem;
      color: var(--c2); display: inline-flex; align-items: center; gap: .35rem;
    }
    .hub-app:hover .go i { transform: translateX(3px); }
    .hub-app .go i { transition: transform .16s ease; }

    .hub-app.t-memo { --c1: var(--hub-memo-1); --c2: var(--hub-memo-2); }
    .hub-app.t-svc  { --c1: var(--hub-svc-1);  --c2: var(--hub-svc-2); }
    .hub-app.t-rpt  { --c1: var(--hub-rpt-1);  --c2: var(--hub-rpt-2); }
    .hub-app.t-adm  { --c1: var(--hub-adm-1);  --c2: var(--hub-adm-2); }

    .hub-empty { grid-column: 1/-1; color: var(--muted, #6b7280); font-size: .9rem; padding: 1rem 0; }

    @media (max-width: 560px) {
      .hub-hero { padding: 1.8rem 1.4rem; }
      .hub-hero h1 { font-size: 1.5rem; }
    }
  </style>
</head>
<body>
  <nav class="hub-nav">
    <img src="logo.jpg" alt="PPDA">
    <span class="who">Signed in as <strong><?= $user ?></strong></span>
    <button id="logoutBtn" class="hub-logout"><i class="bi bi-box-arrow-right"></i>Sign out</button>
  </nav>

  <div class="hub-wrap">
    <header class="hub-hero">
      <h1>PPDA Digital Hub</h1>
      <p>One sign-in for every PPDA system — memos, procurement services, reporting and administration.</p>
      <div class="hub-search">
        <i class="bi bi-search"></i>
        <input id="searchApp" type="text" placeholder="Search applications…" autocomplete="off">
      </div>
    </header>

    <div id="appsGrid" class="hub-grid">
      <?php foreach ($apps as $a): ?>
        <a class="hub-app t-<?= $a['tone'] ?> app-item" role="button" tabindex="0"
           data-name="<?= htmlspecialchars(strtolower($a['name'])) ?>"
           onclick="<?= $a['launch'] ?>"
           onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();<?= $a['launch'] ?>;}">
          <span class="ico"><i class="bi <?= $a['icon'] ?>"></i></span>
          <h3><?= htmlspecialchars($a['name']) ?></h3>
          <p><?= $a['desc'] ?></p>
          <span class="go">Open <i class="bi bi-arrow-right"></i></span>
        </a>
      <?php endforeach; ?>
      <div class="hub-empty" id="noMatch" style="display:none;">No application matches your search.</div>
    </div>
  </div>

  <script>
    function hubBase() {
      var b = window.location.pathname;
      b = b.replace(/\/(hub\/)?[^\/?#]*\.[^\/?#]*$/, '');
      b = b.replace(/\/hub\/?$/, '');
      return b.replace(/\/$/, '');
    }
    function launchApp(app) {
      window.location.href = hubBase() + '/app/' + encodeURIComponent(app) + '/index.php';
    }

    document.getElementById('logoutBtn').addEventListener('click', function () {
      fetch('logout.php', { method: 'POST', credentials: 'include', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { window.location.href = (d && d.redirect) || 'login.php'; })
        .catch(function () { window.location.href = 'login.php'; });
    });

    var search = document.getElementById('searchApp');
    var noMatch = document.getElementById('noMatch');
    search.addEventListener('input', function (e) {
      var term = e.target.value.trim().toLowerCase();
      var shown = 0;
      document.querySelectorAll('.app-item').forEach(function (card) {
        var hit = card.dataset.name.indexOf(term) !== -1;
        card.style.display = hit ? '' : 'none';
        if (hit) shown++;
      });
      noMatch.style.display = shown ? 'none' : '';
    });
  </script>
</body>
</html>
