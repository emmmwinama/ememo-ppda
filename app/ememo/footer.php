<?php // footer.php — closes the shared .es-shell layout opened by header.php. ?>
    </main>
  </div>
  <div class="es-toasts" id="esToasts" aria-live="polite" aria-atomic="true"></div>
</div><!-- /.es-shell -->

<script src="../../assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/app.js') ?: time() ?>"></script>

<script>
/* Notification polling + toast (e-Memo). */
(() => {
  const seen = new Set(), queue = [];
  const humanTitle = t => t.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
  const markRead = id => fetch('mark_notification_read.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id })
  }).catch(() => {});

  function showNext() {
    if (!queue.length) return;
    const cont = document.getElementById('toastContainer');
    if (!cont || !window.bootstrap) return;
    const n = queue.shift();
    const tpl = document.createElement('div');
    tpl.innerHTML = `
      <div class="toast whatsapp mb-2" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
          <i class="bi bi-bell-fill me-2 text-white"></i>
          <div class="me-auto"><strong class="text-white">${humanTitle(n.event_type)}</strong><br/>
            <small class="text-white">From: ${n.from_name} &bull; To: ${n.to_name}</small></div>
          <small class="text-light ms-2">${new Date(n.created_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}</small>
          <button type="button" class="btn-close ms-2" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">${n.message}</div>
      </div>`;
    const el = tpl.firstElementChild;
    cont.appendChild(el);
    el.addEventListener('hidden.bs.toast', () => { markRead(n.id); el.remove(); showNext(); });
    new bootstrap.Toast(el, { autohide: false }).show();
  }

  async function refresh() {
    await fetch('populate_notifications.php', { method: 'POST' }).catch(() => {});
    const res = await fetch('get_notifications.php').then(r => r.json()).catch(() => ({ success: false }));
    if (!res.success) return;
    const notes = res.notifications || [];
    const badge = document.getElementById('notifBadge');
    if (badge) { badge.textContent = notes.length; badge.style.display = notes.length ? 'inline-block' : 'none'; }
    const menu = document.getElementById('notifDropdownMenu');
    if (menu) {
      menu.innerHTML = notes.length ? '' : '<li><span class="dropdown-item-text text-muted">No new notifications</span></li>';
      notes.forEach(n => {
        if (!seen.has(n.id)) { seen.add(n.id); queue.push(n); }
        const li = document.createElement('li');
        li.innerHTML = `<a class="dropdown-item" href="${n.url}">
            <div class="notif-title">${humanTitle(n.event_type)}</div><div>${n.message}</div>
            <div class="notif-time">${new Date(n.created_at).toLocaleString()}</div></a>`;
        menu.appendChild(li);
      });
    }
    if (!document.querySelector('#toastContainer .toast.show')) showNext();
  }
  refresh();
  setInterval(refresh, 10000);
})();

/* Sidebar badge counts. */
(() => {
  function update() {
    fetch('sidebar_counts.php').then(r => r.json()).then(d => {
      const map = {
        myMemosBadge: d.my_memos, directCountBadge: d.direct, workflowCountBadge: d.workflow,
        unclosedIssuesBadge: d.unclosed_issues, endorsementCountBadge: d.endorsements,
        dgMemosBadge: d.dg_memos, letterReceptionBadge: d.letter_reception, myLettersBadge: d.my_letters,
      };
      Object.entries(map).forEach(([id, cnt]) => {
        const b = document.getElementById(id);
        if (!b) return;
        const n = Number(cnt) || 0;
        b.style.display = n > 0 ? 'inline-block' : 'none';
        b.textContent = b.title ? `${b.title.charAt(0)}: ${n}` : n;
        b.classList.toggle('badge-pulse', n > 0);
      });
    }).catch(() => {});
  }
  update();
  setInterval(update, 60000);
})();
</script>
</body>
</html>
