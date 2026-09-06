<?php
// header.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Initials for the navbar avatar, e.g. "emmanuel.mwinama" -> "EM"
$navUsername = $_SESSION['username'] ?? '';
$navParts     = preg_split('/[.\s_-]+/', $navUsername, -1, PREG_SPLIT_NO_EMPTY);
$navInitials  = strtoupper(substr($navParts[0] ?? 'U', 0, 1) . substr($navParts[1] ?? '', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>e‑Memo System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token()); ?>">
  <script>
  (() => {
    // Auto-attach the CSRF token to every same-page fetch() POST/PUT/PATCH/DELETE
    // so individual AJAX call sites don't each need to be updated.
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const originalFetch = window.fetch;
    window.fetch = function (input, init = {}) {
      const method = (init.method || (input instanceof Request ? input.method : 'GET') || 'GET').toUpperCase();
      if (token && method !== 'GET' && method !== 'HEAD') {
        init = { ...init, headers: { ...(init.headers || {}), 'X-CSRF-Token': token } };
      }
      return originalFetch(input, init);
    };

    // Same for jQuery AJAX ($.ajax / $.post / $.get), which does not use fetch().
    // jQuery is loaded per-page after this script, so wait for it to exist.
    document.addEventListener('DOMContentLoaded', function () {
      if (window.jQuery && token) {
        window.jQuery(document).ajaxSend(function (e, xhr, settings) {
          const method = (settings.type || 'GET').toUpperCase();
          if (method !== 'GET' && method !== 'HEAD') {
            xhr.setRequestHeader('X-CSRF-Token', token);
          }
        });
      }
    });
  })();
  </script>
  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Shared flat/modern theme (colors, radius, shadows, components) -->
  <link href="../../assets/css/theme.css" rel="stylesheet"/>

  <style>
  .app-navbar {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: .6rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    position: sticky;
    top: 0;
    z-index: 1030;
  }
  .app-navbar-brand {
    display: flex;
    align-items: center;
    gap: .6rem;
    font-weight: 700;
    font-size: 1.15rem;
    color: var(--text);
    text-decoration: none;
  }
  .app-navbar-brand:hover { color: var(--text); }
  .app-navbar-brand img {
    height: 34px;
    width: 34px;
    object-fit: cover;
    border-radius: var(--radius-sm);
  }
  .hub-link {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .32rem .7rem;
    border: 1px solid var(--border);
    border-radius: var(--radius-pill);
    font-size: .82rem;
    font-weight: 600;
    color: var(--muted);
    text-decoration: none;
    transition: background .15s, color .15s, border-color .15s;
  }
  .hub-link:hover { background: var(--brand-light); color: var(--brand); border-color: var(--brand); }
  .hub-link .hub-link-label { display: none; }
  @media (min-width: 576px) { .hub-link .hub-link-label { display: inline; } }
  .app-navbar-actions { margin-left: auto; display: flex; align-items: center; gap: .5rem; }
  .icon-btn {
    position: relative;
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    border: none;
    background: none;
    color: var(--muted);
    font-size: 1.15rem;
    transition: background .15s, color .15s;
  }
  .icon-btn:hover { background: var(--brand-light); color: var(--brand); }
  #notifBadge { position: absolute; top: 2px; right: 2px; font-size: .6rem; }
  .user-menu-btn {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .3rem .7rem .3rem .3rem;
    border-radius: var(--radius-pill);
    border: 1px solid transparent;
    background: none;
  }
  .user-menu-btn:hover { background: var(--bg); border-color: var(--border); }
  .avatar-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--brand);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: .8rem;
    flex-shrink: 0;
  }
  .user-menu-name { font-size: .9rem; font-weight: 500; color: var(--text); }

  /* Dropdown styling */
  #notifDropdownMenu { width: 320px; max-height: 400px; overflow-y: auto; }
  .notif-title { font-weight: 600; color: var(--brand); }
  .notif-time  { font-size: .75rem; color: var(--muted); }

  /* Toast container */
  #toastContainer {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 2000;
  }

  /* Notification toast — same shared chat-bubble palette as the rest of the app */
  .toast.whatsapp {
    border: 1px solid var(--brand);
    overflow: hidden;
    width: 300px;
  }
  .toast.whatsapp .toast-header {
    background: var(--brand);
    color: #fff;
    border-bottom: none;
    padding: .5rem .75rem;
  }
  .toast.whatsapp .toast-header .btn-close {
    filter: invert(1);
    opacity: .8;
  }
  .toast.whatsapp .toast-body {
    background: var(--brand-light);
    padding: .75rem;
    color: var(--text);
  }
  .toast-whatsapp-open {
    background: var(--brand);
    color: #fff;
    border-radius: var(--radius-pill);
    padding: .25rem .5rem;
    font-size: .8rem;
    cursor: pointer;
  }
</style>

</head>
<body>

<nav class="app-navbar">
  <a class="app-navbar-brand" href="index.php">
    <img src="logo.jpg" alt="PPDA">
    <span>e&#8209;Memo</span>
  </a>
  <a class="hub-link" href="../../hub/index.php" title="Back to PPDA Digital Hub (e-Service, e-Memo, Reports)">
    <i class="bi bi-grid-3x3-gap-fill"></i>
    <span class="hub-link-label">Digital Hub</span>
  </a>
  <div class="app-navbar-actions">
    <div class="dropdown">
      <button class="icon-btn" type="button" id="notifDropdown"
              data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
        <i class="bi bi-bell"></i>
        <span id="notifBadge" class="badge bg-danger rounded-pill" style="display:none;">0</span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end mt-2"
          aria-labelledby="notifDropdown" id="notifDropdownMenu">
        <li><span class="dropdown-item-text text-muted">Loading…</span></li>
      </ul>
    </div>
    <div class="dropdown">
      <button class="user-menu-btn" type="button"
              data-bs-toggle="dropdown" aria-expanded="false">
        <span class="avatar-circle"><?php echo htmlspecialchars($navInitials); ?></span>
        <span class="user-menu-name d-none d-md-inline"><?php echo htmlspecialchars($navUsername); ?></span>
        <i class="bi bi-chevron-down small text-muted"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end mt-2">
        <li><a class="dropdown-item" href="index.php?module=profile"><i class="bi bi-person me-2"></i>My Profile</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Toast container -->
<div id="toastContainer"></div>

<script>
(() => {
  const seen = new Set(), queue = [];

  function humanTitle(type) {
    return type.split('_')
      .map(w => w.charAt(0).toUpperCase() + w.slice(1))
      .join(' ');
  }

  function markRead(id) {
    return fetch('mark_notification_read.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ id })
    }).catch(()=>{});
  }
function showNext() {
  if (!queue.length) return;
  const n = queue.shift();
  const tpl = document.createElement('div');
  tpl.innerHTML = `
    <div class="toast whatsapp mb-2" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="toast-header bg-success">
        <i class="bi bi-bell-fill me-2 text-white"></i>
        <div class="me-auto">
          <strong class="text-white">${humanTitle(n.event_type)}</strong><br/>
          <small class="text-white">
            From: ${n.from_name} &nbsp;&bull;&nbsp; To: ${n.to_name}
          </small>
        </div>
        <small class="text-light ms-2">
          ${new Date(n.created_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}
        </small>
        <button type="button" class="btn-close ms-2" data-bs-dismiss="toast"></button>
      </div>
      <div class="toast-body">
        ${n.message}
      </div>
    </div>`;
  
  const toastEl = tpl.firstElementChild;
  document.getElementById('toastContainer').appendChild(toastEl);

  toastEl.addEventListener('hidden.bs.toast', () => {
    markRead(n.id);
    toastEl.remove();
    showNext();
  });

  new bootstrap.Toast(toastEl, { autohide: false }).show();
}





  async function refresh() {
    // backfill DB
    await fetch('populate_notifications.php', {method:'POST'}).catch(()=>{});
    // fetch unread
    const res = await fetch('get_notifications.php')
      .then(r=>r.json()).catch(()=>({success:false}));
    if (!res.success) return;

    const notes = res.notifications;
    // badge
    const badge = document.getElementById('notifBadge');
    badge.textContent = notes.length;
    badge.style.display = notes.length ? 'inline-block' : 'none';

    // dropdown
    const menu = document.getElementById('notifDropdownMenu');
    menu.innerHTML = '';
    if (!notes.length) {
      menu.innerHTML = '<li><span class="dropdown-item-text text-muted">No new notifications</span></li>';
    } else {
      notes.forEach(n => {
        if (!seen.has(n.id)) {
          seen.add(n.id);
          queue.push(n);
        }
        const li = document.createElement('li');
        li.innerHTML = `
          <a class="dropdown-item" href="${n.url}">
            <div class="notif-title">${humanTitle(n.event_type)}</div>
            <div>${n.message}</div>
            <div class="notif-time">${new Date(n.created_at).toLocaleString()}</div>
          </a>`;
        menu.appendChild(li);
      });
    }

    // show next toast if none active
    if (!document.querySelector('#toastContainer .toast.show')) {
      showNext();
    }
  }

  // initial + every 10s
  refresh();
  setInterval(refresh, 10000);
})();
</script>
</body>
</html>
