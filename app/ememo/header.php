<?php
// header.php — opens the shared .es-shell layout (assets/css/app.css).
// Standalone pages: require 'auth.php'; include 'header.php'; ...body...; include 'footer.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$navUsername = $_SESSION['username'] ?? '';
$navParts    = preg_split('/[.\s_-]+/', $navUsername, -1, PREG_SPLIT_NO_EMPTY);
$navInitials = strtoupper(substr($navParts[0] ?? 'U', 0, 1) . substr($navParts[1] ?? '', 0, 1));
$mod         = $_GET['module'] ?? '';
$__isAdmin   = (($_SESSION['role'] ?? '') === 'admin');

/** e-Memo sidebar model — [group => [ [key, href, icon, label, badgeId?], ... ] ]. */
$__nav = [
    '' => [
        ['', 'index.php', 'bi-speedometer2', 'Dashboard'],
    ],
    'Memos' => [
        ['create_memo', 'index.php?module=create_memo', 'bi-file-earmark-plus', 'New memo'],
        ['my_memos',    'index.php?module=my_memos',    'bi-folder2-open',      'My memos', 'myMemosBadge'],
        ['archive',     'index.php?module=archive',     'bi-archive',           'Archive'],
    ],
    'Actions' => [
        ['inbox',        'index.php?module=inbox',        'bi-inbox',          'Inbox', ['directCountBadge', 'workflowCountBadge', 'unclosedIssuesBadge']],
        ['endorsements', 'index.php?module=endorsements', 'bi-hand-thumbs-up', 'Endorsements', 'endorsementCountBadge'],
    ],
    'Communications' => [
        ['dg_memos', 'index.php?module=dg_memos', 'bi-megaphone', 'DG broadcasts', 'dgMemosBadge'],
    ],
    'External letters' => [
        ['upload_letter',    'index.php?module=upload_letter',    'bi-cloud-upload',       'Upload files'],
        ['letter_reception', 'index.php?module=letter_reception', 'bi-journal-arrow-down', 'DG in-tray', 'letterReceptionBadge'],
        ['my_letters',       'index.php?module=my_letters',       'bi-briefcase',          'My assignments', 'myLettersBadge'],
        ['closed_letters',   'index.php?module=closed_letters',   'bi-folder-check',       'Closed files'],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PPDA e-Memo</title>
  <?php if (function_exists('csrf_token')): ?>
  <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
  <script>
  (() => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const originalFetch = window.fetch;
    window.fetch = function (input, init = {}) {
      const method = (init.method || (input instanceof Request ? input.method : 'GET') || 'GET').toUpperCase();
      if (token && method !== 'GET' && method !== 'HEAD') {
        init = { ...init, headers: { ...(init.headers || {}), 'X-CSRF-Token': token } };
      }
      return originalFetch(input, init);
    };
    document.addEventListener('DOMContentLoaded', function () {
      if (window.jQuery && token) {
        window.jQuery(document).ajaxSend(function (e, xhr, settings) {
          const method = (settings.type || 'GET').toUpperCase();
          if (method !== 'GET' && method !== 'HEAD') xhr.setRequestHeader('X-CSRF-Token', token);
        });
      }
    });
  })();
  </script>
  <?php endif; ?>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link href="../../assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/theme.css') ?: time() ?>" rel="stylesheet">
  <link href="../../assets/css/app.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/app.css') ?: time() ?>" rel="stylesheet">
  <style>
    /* e-Memo-only chrome: notification bell + toast + badge pulse */
    .em-bell { position: relative; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
      border-radius: 50%; border: none; background: none; color: var(--muted); font-size: 1.1rem; }
    .em-bell:hover { background: var(--brand-light); color: var(--brand); }
    #notifBadge { position: absolute; top: 1px; right: 1px; font-size: .58rem; }
    #notifDropdownMenu { width: 320px; max-height: 400px; overflow-y: auto; }
    .notif-title { font-weight: 600; color: var(--brand); }
    .notif-time { font-size: .75rem; color: var(--muted); }
    #toastContainer { position: fixed; top: 1rem; right: 1rem; z-index: 2000; }
    .toast.whatsapp { border: 1px solid var(--brand); overflow: hidden; width: 300px; }
    .toast.whatsapp .toast-header { background: var(--brand); color: #fff; border-bottom: none; padding: .5rem .75rem; }
    .toast.whatsapp .toast-header .btn-close { filter: invert(1); opacity: .8; }
    .toast.whatsapp .toast-body { background: var(--brand-light); padding: .75rem; color: var(--text); }
    .es-nav a .badge { margin-left: auto; font-size: .6rem; }
    @keyframes empulse { 0%,100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.18); opacity: .8; } }
    .badge-pulse { animation: empulse 1.5s infinite; }
  </style>
</head>
<body>
<div class="es-shell" id="esShell">
  <script>try{if(localStorage.getItem('esNav')==='collapsed')document.getElementById('esShell').classList.add('nav-collapsed');}catch(e){}</script>
  <aside class="es-side">
    <div class="es-brand-row">
      <a class="es-brand" href="index.php"><i class="bi bi-file-earmark-text-fill"></i><span>PPDA e&#8209;Memo</span></a>
      <button type="button" class="es-nav-toggle" id="esNavToggle" title="Collapse menu"><i class="bi bi-chevron-bar-left"></i></button>
    </div>
    <nav class="es-nav">
      <?php foreach ($__nav as $group => $items): ?>
        <?php if ($group !== ''): ?><div class="es-nav-group"><?= htmlspecialchars($group) ?></div><?php endif; ?>
        <?php foreach ($items as $it): [$k, $href, $icon, $label] = $it; $badges = (array) ($it[4] ?? []); ?>
          <a href="<?= htmlspecialchars($href) ?>" class="<?= $mod === $k ? 'active' : '' ?>" title="<?= htmlspecialchars($label) ?>">
            <i class="bi <?= htmlspecialchars($icon) ?>"></i><span><?= htmlspecialchars($label) ?></span>
            <?php foreach ($badges as $bid): ?><span id="<?= htmlspecialchars($bid) ?>" class="badge bg-danger" style="display:none;">0</span><?php endforeach; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <div class="es-nav-group">Account</div>
      <a href="index.php?module=profile" class="<?= $mod === 'profile' ? 'active' : '' ?>" title="My profile"><i class="bi bi-person"></i><span>My profile</span></a>
      <?php if ($__isAdmin): ?>
        <a href="../../hub/admin/index.php" title="Administration console"><i class="bi bi-sliders"></i><span>Administration</span></a>
      <?php endif; ?>
      <a href="logout.php" class="text-danger" title="Logout"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
    </nav>
    <div class="es-side-foot">
      <a class="es-hub-link" href="../../hub/index.php" title="Back to Digital Hub"><i class="bi bi-grid-3x3-gap-fill"></i><span>Back to Digital Hub</span></a>
    </div>
  </aside>

  <div class="es-main">
    <header class="es-topbar">
      <div class="es-topbar-inner">
        <span class="es-page-name">e-Memo</span>
        <div class="es-user d-flex align-items-center gap-1">
          <div class="dropdown">
            <button class="em-bell" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
              <i class="bi bi-bell"></i>
              <span id="notifBadge" class="badge bg-danger rounded-pill" style="display:none;">0</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="notifDropdown" id="notifDropdownMenu">
              <li><span class="dropdown-item-text text-muted">Loading…</span></li>
            </ul>
          </div>
          <div class="dropdown">
            <button type="button" class="es-user-btn" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="es-avatar"><?= htmlspecialchars($navInitials ?: 'U') ?></span>
              <span class="es-user-name d-none d-md-inline"><?= htmlspecialchars($navUsername) ?></span>
              <i class="bi bi-chevron-down small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end mt-2">
              <li><a class="dropdown-item" href="index.php?module=profile"><i class="bi bi-person me-2"></i>My profile</a></li>
              <li><a class="dropdown-item" href="../../hub/index.php"><i class="bi bi-grid-3x3-gap-fill me-2"></i>Digital Hub</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
          </div>
        </div>
      </div>
    </header>

    <main class="es-content">
      <div id="toastContainer"></div>
