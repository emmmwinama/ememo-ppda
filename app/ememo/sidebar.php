<!-- sidebar.php -->
<?php
  // Bootstrap Icons are already loaded once in header.php.
  $mod = $_GET['module'] ?? '';
?>
<div class="col-auto p-0 sidebar-col" id="sidebarCol">
<div class="sidebar d-flex flex-column" id="appSidebar">
  <style>
    /* ── Collapsible rail (Farmis pattern, PPDA-green brand) ──────────── */
    .sidebar-col { position: relative; z-index: 1020; }
    .sidebar {
      --rail-w: 240px;
      width: var(--rail-w);
      height: 100vh;
      position: sticky;
      top: 0;
      background: var(--surface, #fff);
      border-right: 1px solid var(--border, #e3e7ea);
      transition: width .22s ease;
    }
    .sidebar.collapsed { --rail-w: 68px; }

    .sidebar .nav-scroll { flex: 1 1 auto; overflow: hidden; padding: .35rem 0; }
    /* Escape hatch: only allow scrolling on very short viewports */
    @media (max-height: 680px) {
      .sidebar .nav-scroll { overflow-y: auto; overflow-x: hidden; }
      .sidebar .nav-scroll::-webkit-scrollbar { width: 5px; }
      .sidebar .nav-scroll::-webkit-scrollbar-thumb { background: var(--border-strong, #cbd5e1); border-radius: 3px; }
    }

    .sidebar .nav-link {
      position: relative;
      display: flex;
      align-items: center;
      gap: .7rem;
      white-space: nowrap;
      margin: 1px .5rem;
      padding: .4rem .7rem;
      border-radius: var(--radius-md, 10px);
      color: var(--text, #24292b);
      font-size: .82rem;
      font-weight: 600;
      line-height: 1.1;
    }
    .sidebar .nav-link:hover:not(.active) { background: var(--brand-light, #e6f4e6); }
    .sidebar .nav-link.active {
      background: var(--brand-light, #e6f4e6);
      color: var(--brand, #2a8f2e);
      box-shadow: inset 0 0 0 1px rgba(42,143,46,.18);
    }
    .sidebar .nav-link i {
      font-size: 1.05rem;
      width: 1.25rem;
      text-align: center;
      flex-shrink: 0;
    }
    .sidebar .nav-link .badge { margin-left: auto; font-size: .62rem; }

    .sidebar-section { margin-top: .5rem; }
    .sidebar-section:first-child { margin-top: .2rem; }
    .sidebar-section h6 {
      margin: 0 0 .1rem;
      padding: 0 1.2rem;
      font-size: .66rem;
      font-weight: 800;
      letter-spacing: .09em;
      text-transform: uppercase;
      color: var(--muted, #6b7280);
    }
    /* Account group sits last — separated by a rule, not a huge flex gap */
    .sidebar-section.sidebar-account {
      margin-top: .75rem;
      padding-top: .5rem;
      border-top: 1px solid var(--border, #e3e7ea);
    }

    /* Collapsed state */
    .sidebar.collapsed .nav-link { justify-content: center; gap: 0; padding-left: 0; padding-right: 0; margin-inline: .4rem; }
    .sidebar.collapsed .nav-link .label { display: none; }
    .sidebar.collapsed .sidebar-section h6 { display: none; }
    .sidebar.collapsed .sidebar-section { margin-top: .5rem; border-top: 1px solid var(--border, #e3e7ea); padding-top: .5rem; }
    .sidebar.collapsed .sidebar-section:first-child { border-top: 0; padding-top: 0; }
    .sidebar.collapsed .nav-link .badge {
      position: absolute; top: 5px; right: 7px;
      min-width: 8px; height: 8px; padding: 0;
      font-size: 0; overflow: hidden;
    }
    .sidebar.collapsed .brand-text { display: none; }

    /* Brand row */
    .sidebar .sb-brand {
      display: flex; align-items: center; gap: .6rem;
      height: 56px; flex-shrink: 0;
      padding: 0 1rem;
      border-bottom: 1px solid var(--border, #e3e7ea);
      text-decoration: none;
      color: var(--text, #24292b);
      font-weight: 700;
      overflow: hidden;
      white-space: nowrap;
    }
    .sidebar.collapsed .sb-brand { justify-content: center; padding: 0; }
    .sidebar .sb-brand img { height: 30px; width: 30px; object-fit: cover; border-radius: var(--radius-sm, 6px); flex-shrink: 0; }

    /* Floating collapse toggle */
    .sidebar-toggle {
      position: absolute; top: 68px; right: -13px; z-index: 1040;
      width: 26px; height: 26px;
      display: flex; align-items: center; justify-content: center;
      padding: 0;
      border: 2px solid var(--surface, #fff);
      border-radius: 50%;
      background: var(--brand, #2a8f2e);
      color: #fff;
      font-size: .8rem;
      box-shadow: 0 2px 6px rgba(16,24,32,.18);
      cursor: pointer;
    }
    .sidebar-toggle:hover { background: var(--brand-dark, #1f6b23); }
  </style>

  <a class="sb-brand" href="index.php" title="e-Memo">
    <img src="logo.jpg" alt="PPDA">
    <span class="brand-text">e&#8209;Memo</span>
  </a>

  <button type="button" class="sidebar-toggle" id="sidebarToggle" title="Collapse sidebar" aria-label="Toggle sidebar">
    <i class="bi bi-chevron-left"></i>
  </button>

  <nav class="nav flex-column nav-scroll">
    <div class="sidebar-section">
      <a href="index.php" title="Dashboard"
         class="nav-link <?= $mod === '' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i><span class="label">Dashboard</span>
      </a>
    </div>

    <div class="sidebar-section">
      <h6>Memos</h6>
      <a href="index.php?module=create_memo" title="New Memo"
         class="nav-link <?= $mod === 'create_memo' ? 'active' : '' ?>">
        <i class="bi bi-file-earmark-plus"></i><span class="label">New Memo</span>
      </a>
      <a href="index.php?module=my_memos" title="My Memos"
         class="nav-link <?= $mod === 'my_memos' ? 'active' : '' ?>">
        <i class="bi bi-folder2-open"></i><span class="label">My Memos</span>
        <span id="myMemosBadge" class="badge bg-danger" style="display:none;">0</span>
      </a>
      <a href="index.php?module=archive" title="Archive"
         class="nav-link <?= $mod === 'archive' ? 'active' : '' ?>">
        <i class="bi bi-archive"></i><span class="label">Archive</span>
      </a>
    </div>

    <div class="sidebar-section">
      <h6>Actions</h6>
      <a href="index.php?module=inbox" title="Inbox"
         class="nav-link <?= $mod === 'inbox' ? 'active' : '' ?>">
        <i class="bi bi-inbox"></i><span class="label">Inbox</span>
        <span id="directCountBadge"    class="badge bg-danger"  style="display:none;" title="Direct">D: 0</span>
        <span id="workflowCountBadge"  class="badge bg-warning" style="display:none;" title="Workflow">W: 0</span>
        <span id="unclosedIssuesBadge" class="badge bg-primary" style="display:none;" title="Unclosed Issues">U: 0</span>
      </a>
      <a href="index.php?module=endorsements" title="Endorsements"
         class="nav-link <?= $mod === 'endorsements' ? 'active' : '' ?>">
        <i class="bi bi-hand-thumbs-up"></i><span class="label">Endorsements</span>
        <span id="endorsementCountBadge" class="badge bg-danger" style="display:none;">0</span>
      </a>
    </div>

    <div class="sidebar-section">
      <h6>Communications</h6>
      <a href="index.php?module=dg_memos" title="DG Broadcasts"
         class="nav-link <?= $mod === 'dg_memos' ? 'active' : '' ?>">
        <i class="bi bi-megaphone"></i><span class="label">DG Broadcasts</span>
        <span id="dgMemosBadge" class="badge bg-danger" style="display:none;">0</span>
      </a>
    </div>

    <div class="sidebar-section">
      <h6>External</h6>
      <a href="index.php?module=upload_letter" title="Upload Files"
         class="nav-link <?= $mod === 'upload_letter' ? 'active' : '' ?>">
        <i class="bi bi-cloud-upload"></i><span class="label">Upload Files</span>
      </a>
      <a href="index.php?module=letter_reception" title="DG In-Tray"
         class="nav-link <?= $mod === 'letter_reception' ? 'active' : '' ?>">
        <i class="bi bi-journal-arrow-down"></i><span class="label">DG In-Tray</span>
        <span id="letterReceptionBadge" class="badge bg-danger" style="display:none;" title="DG Inbox">D: 0</span>
      </a>
      <a href="index.php?module=my_letters" title="My Assignments"
         class="nav-link <?= $mod === 'my_letters' ? 'active' : '' ?>">
        <i class="bi bi-briefcase"></i><span class="label">My Assignments</span>
        <span id="myLettersBadge" class="badge bg-danger" style="display:none;" title="Assignments">A: 0</span>
      </a>
      <a href="index.php?module=closed_letters" title="Closed Files"
         class="nav-link <?= $mod === 'closed_letters' ? 'active' : '' ?>">
        <i class="bi bi-folder-check"></i><span class="label">Closed Files</span>
      </a>
    </div>

    <div class="sidebar-section sidebar-account">
      <h6>Account</h6>
      <a href="index.php?module=profile" title="My Profile"
         class="nav-link <?= $mod === 'profile' ? 'active' : '' ?>">
        <i class="bi bi-person"></i><span class="label">My Profile</span>
      </a>
      <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
      <a href="../../hub/admin/index.php" title="Administration console"
         class="nav-link">
        <i class="bi bi-sliders"></i><span class="label">Administration</span>
      </a>
      <?php endif; ?>
      <a href="logout.php" title="Logout" class="nav-link text-danger">
        <i class="bi bi-box-arrow-right"></i><span class="label">Logout</span>
      </a>
    </div>
  </nav>
</div>
</div>

<script>
(function () {
  var KEY = 'ememo.sidebarCollapsed';
  var sb = document.getElementById('appSidebar');
  var btn = document.getElementById('sidebarToggle');
  if (!sb || !btn) return;

  function apply(collapsed) {
    sb.classList.toggle('collapsed', collapsed);
    btn.querySelector('i').className = 'bi bi-chevron-' + (collapsed ? 'right' : 'left');
    btn.title = (collapsed ? 'Expand' : 'Collapse') + ' sidebar';
  }

  apply(localStorage.getItem(KEY) === '1');

  btn.addEventListener('click', function () {
    var collapsed = !sb.classList.contains('collapsed');
    try { localStorage.setItem(KEY, collapsed ? '1' : '0'); } catch (e) {}
    apply(collapsed);
  });
})();

// Live-update sidebar badges
function updateSidebarCounts() {
  fetch('sidebar_counts.php')
    .then(r => r.json())
    .then(d => {
      const map = {
        myMemosBadge:          d.my_memos,
        directCountBadge:      d.direct,
        workflowCountBadge:    d.workflow,
        unclosedIssuesBadge:   d.unclosed_issues,
        endorsementCountBadge: d.endorsements,
        dgMemosBadge:          d.dg_memos,
        letterReceptionBadge:  d.letter_reception,
        myLettersBadge:        d.my_letters
      };
      Object.entries(map).forEach(([id, cnt]) => {
        const b = document.getElementById(id);
        if (!b) return;
        b.style.display = cnt > 0 ? 'inline-block' : 'none';
        b.textContent = b.title ? `${b.title.charAt(0)}: ${cnt}` : cnt;
        b.classList.toggle('badge-pulse', cnt > 0);
      });
    })
    .catch(console.error);
}
updateSidebarCounts();
setInterval(updateSidebarCounts, 60000);
</script>

<style>
@keyframes pulse {
  0%   { transform: scale(1);   opacity: 1; }
  50%  { transform: scale(1.2); opacity: 0.8; }
  100% { transform: scale(1);   opacity: 1; }
}
.badge-pulse { animation: pulse 1.5s infinite; }
</style>
