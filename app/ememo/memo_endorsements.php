<?php
// memo_endorsements.php — rendered inside index.php (header.php loads Bootstrap, Icons, theme.css)
?>
<style>
  .endo-list { display: flex; flex-direction: column; gap: .75rem; }
  .endo-card { text-decoration: none; color: inherit; }
  .endo-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: .4rem; }
  .endo-stage { font-size: .78rem; color: var(--muted); }
  .endo-subject { font-weight: 700; color: var(--text); line-height: 1.35; }
  .endo-meta { font-size: .82rem; color: var(--muted); margin-top: .25rem; display: flex; flex-wrap: wrap; gap: .15rem .6rem; }
  .endo-meta .bi { margin-right: .2rem; }
  .endo-callout {
    margin-top: .7rem; padding: .6rem .8rem;
    background: #fdefda; border: 1px solid #f3ddb6; border-radius: var(--radius-md);
    font-size: .82rem; color: var(--text);
  }
  .endo-callout strong { color: #b45309; }
</style>

<div class="f-head">
  <h1 class="f-title">Endorsements</h1>
  <p class="f-subtitle">Memos routed to you for endorsement</p>
</div>

<div class="f-toolbar">
  <div class="f-search">
    <i class="bi bi-search"></i>
    <input type="text" id="endorsementSearch" placeholder="Search by subject, reference or sender…" autocomplete="off">
  </div>
  <div class="spinner-border spinner-border-sm text-secondary d-none align-self-center" id="loadingSpinner"></div>
</div>

<div class="f-chips" id="endoChips">
  <button class="chip active" data-status="pending">New requests <span class="chip-count" data-c="pending">0</span></button>
  <button class="chip" data-status="endorsed">Endorsed <span class="chip-count" data-c="endorsed">0</span></button>
  <button class="chip" data-status="returned">Returned <span class="chip-count" data-c="returned">0</span></button>
  <button class="chip" data-status="escalated">Escalations <span class="chip-count" data-c="escalated">0</span></button>
  <button class="chip" data-status="approved">Approved <span class="chip-count" data-c="approved">0</span></button>
  <button class="chip" data-status="rejected">Rejected <span class="chip-count" data-c="rejected">0</span></button>
</div>

<div class="endo-list" id="endoList"></div>
<div class="f-state" id="endoEmpty" hidden><i class="bi bi-hand-thumbs-up"></i><p>Nothing in this view.</p></div>

<script>
(() => {
  const TINT = {
    pending: 't-neutral', endorsed: 't-sky', returned: 't-rose',
    escalated: 't-amber', approved: 't-green', rejected: 't-rose',
  };
  const STATUSES = Object.keys(TINT);
  const state = { status: 'pending', q: '' };
  let all = [];

  const list  = document.getElementById('endoList');
  const empty = document.getElementById('endoEmpty');
  const spin  = document.getElementById('loadingSpinner');

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => (
    { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
  const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);

  function statusOf(item) {
    let s = (item.endorsement_status || 'pending').trim().toLowerCase();
    if ((item.memo_status || '').toLowerCase() === 'returned') s = 'returned';
    return STATUSES.includes(s) ? s : 'pending';
  }

  function matchesSearch(item) {
    if (!state.q) return true;
    const q = state.q.toLowerCase();
    return (item.subject || '').toLowerCase().includes(q)
        || (item.memo_id || '').toLowerCase().includes(q)
        || (item.sender_name || '').toLowerCase().includes(q);
  }

  function cardHTML(item, s) {
    const callout = (s === 'returned' && item.return_comment)
      ? `<div class="endo-callout"><strong>Returned:</strong> ${esc(item.return_comment)}</div>` : '';
    return `
      <a href="view_memo.php?memo_id=${encodeURIComponent(item.id)}" class="f-card endo-card">
        <div class="endo-top">
          <span class="pill ${TINT[s]}">${esc(cap(s))}</span>
          <span class="endo-stage">${esc(item.stage || 'N/A')}</span>
        </div>
        <div class="endo-subject">${esc(item.subject || 'Untitled memo')}</div>
        <div class="endo-meta">
          <span><i class="bi bi-hash"></i>${esc(item.memo_id || '—')}</span>
          <span><i class="bi bi-person"></i>${esc(item.sender_name || 'Unknown')}</span>
        </div>
        ${callout}
      </a>`;
  }

  function render() {
    const counts = Object.fromEntries(STATUSES.map(s => [s, 0]));
    const searchable = all.filter(matchesSearch);
    searchable.forEach(i => { counts[statusOf(i)]++; });
    STATUSES.forEach(s => {
      const el = document.querySelector(`[data-c="${s}"]`);
      if (el) el.textContent = counts[s];
    });

    const rows = searchable.filter(i => statusOf(i) === state.status);
    list.innerHTML = rows.map(i => cardHTML(i, state.status)).join('');
    empty.hidden = rows.length > 0;
  }

  function load() {
    spin.classList.remove('d-none');
    fetch('get_endorsements.php')
      .then(r => r.json())
      .then(d => {
        spin.classList.add('d-none');
        if (d.status !== 'success') return;
        if (JSON.stringify(all) !== JSON.stringify(d.data)) { all = d.data || []; render(); }
      })
      .catch(err => { spin.classList.add('d-none'); console.error('endorsements:', err); });
  }

  document.getElementById('endoChips').addEventListener('click', (e) => {
    const chip = e.target.closest('.chip');
    if (!chip) return;
    document.querySelectorAll('#endoChips .chip').forEach(c => c.classList.toggle('active', c === chip));
    state.status = chip.dataset.status;
    render();
  });

  let t;
  document.getElementById('endorsementSearch').addEventListener('input', (e) => {
    clearTimeout(t);
    t = setTimeout(() => { state.q = e.target.value.trim(); render(); }, 150);
  });

  setInterval(() => { if (state.q === '') load(); }, 30000);
  load();
})();
</script>
