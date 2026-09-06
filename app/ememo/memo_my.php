<?php
// memo_my.php — rendered inside index.php (header.php already loads Bootstrap, Icons, theme.css)
?>
<style>
  /* ── My Memos (Farmis-style clean surface) ─────────────────────── */
  .mm { --gap: 1rem; }

  .mm-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 1rem; flex-wrap: wrap; margin-bottom: 1.3rem;
  }
  .mm-title { font-size: 1.4rem; font-weight: 800; margin: 0; color: var(--text); }
  .mm-subtitle { color: var(--muted); font-size: .9rem; margin: .2rem 0 0; }
  .mm-new { font-weight: 600; }

  .mm-toolbar { display: flex; gap: .75rem; flex-wrap: wrap; margin-bottom: 1rem; }
  .mm-search {
    position: relative; flex: 1 1 280px;
  }
  .mm-search i {
    position: absolute; left: .85rem; top: 50%; transform: translateY(-50%);
    color: var(--muted); font-size: .95rem; pointer-events: none;
  }
  .mm-search input {
    width: 100%; height: 40px; padding: 0 .9rem 0 2.3rem;
    background: var(--surface);
    border: 1px solid var(--border); border-radius: var(--radius-md);
    font-size: .9rem; color: var(--text); outline: none;
    transition: border-color .15s, box-shadow .15s;
  }
  .mm-search input:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px var(--brand-light);
  }
  .mm-sort { height: 40px; width: auto; border-radius: var(--radius-md); border-color: var(--border); }

  .mm-chips {
    display: flex; gap: .5rem; flex-wrap: wrap;
    margin-bottom: 1.3rem;
  }
  .chip {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .35rem .8rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-pill);
    font-size: .82rem; font-weight: 600; color: var(--muted);
    cursor: pointer; user-select: none;
    transition: background .15s, color .15s, border-color .15s;
  }
  .chip:hover { border-color: var(--border-strong, #cbd5e1); color: var(--text); }
  .chip.active {
    background: var(--brand); border-color: var(--brand); color: #fff;
  }
  .chip .chip-count {
    display: inline-block; min-width: 1.25rem; text-align: center;
    padding: 0 .35rem; border-radius: var(--radius-pill);
    background: rgba(0,0,0,.06); font-size: .74rem; font-weight: 700;
  }
  .chip.active .chip-count { background: rgba(255,255,255,.25); }

  .mm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--gap);
  }

  .memo-card {
    display: flex; flex-direction: column;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-flat);
    padding: 1.1rem;
    transition: box-shadow .18s ease, transform .18s ease;
  }
  .memo-card:hover { box-shadow: var(--shadow-flat-hover); transform: translateY(-1px); }
  .memo-card-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: .6rem; }
  .memo-date { font-size: .76rem; color: var(--muted); }
  .memo-subject {
    font-size: 1rem; font-weight: 700; color: var(--text);
    margin: 0 0 .4rem; line-height: 1.35;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
  }
  .memo-meta { display: flex; flex-wrap: wrap; gap: .25rem 1rem; margin-bottom: .3rem; }
  .memo-meta span { font-size: .8rem; color: var(--muted); display: inline-flex; align-items: center; gap: .35rem; }
  .memo-meta .bi { font-size: .85rem; }

  .memo-callout {
    margin-top: .8rem; padding: .7rem .85rem;
    background: #fdecee; border: 1px solid #f6d4d9;
    border-radius: var(--radius-md); font-size: .82rem;
  }
  .memo-callout-head { font-weight: 700; color: #be123c; display: flex; align-items: center; gap: .4rem; }
  .memo-callout p { margin: .35rem 0 0; color: var(--text); }

  .memo-actions { display: flex; gap: .5rem; margin-top: auto; padding-top: .9rem; }
  .memo-actions .btn { font-size: .8rem; font-weight: 600; }


  .pill {
    display: inline-block; padding: .15rem .6rem;
    border-radius: var(--radius-pill); font-size: .74rem; font-weight: 600;
  }
  .t-dark    { background: #e8ebee; color: #1f2937; }
  .t-neutral { background: #eef1f4; color: #475569; }
  .t-slate   { background: #eef1f4; color: #475569; }
  .t-green   { background: var(--brand-light); color: var(--brand-dark); }
  .t-amber   { background: #fdefda; color: #b45309; }
  .t-rose    { background: #fdecee; color: #be123c; }
  .t-sky     { background: #e6f4fb; color: #0369a1; }
</style>

<div class="mm">

  <div class="mm-head">
    <div>
      <h1 class="mm-title">My Memos</h1>
      <p class="mm-subtitle">Track your memos through every stage of the workflow</p>
    </div>
    <a href="index.php?module=create_memo" class="btn btn-success btn-sm mm-new">
      <i class="bi bi-plus-lg me-1"></i>New memo
    </a>
  </div>

  <div class="mm-toolbar">
    <div class="mm-search">
      <i class="bi bi-search"></i>
      <input type="text" id="memoSearch" placeholder="Search by subject or reference…" autocomplete="off">
    </div>
    <select id="sortSelect" class="form-select form-select-sm mm-sort">
      <option value="newest" selected>Newest first</option>
      <option value="oldest">Oldest first</option>
    </select>
  </div>

  <div class="mm-chips" id="statusChips">
    <button class="chip active" data-status="All">All <span class="chip-count" data-count="All">0</span></button>
    <button class="chip" data-status="Draft">Draft <span class="chip-count" data-count="Draft">0</span></button>
    <button class="chip" data-status="Submitted">Submitted <span class="chip-count" data-count="Submitted">0</span></button>
    <button class="chip" data-status="Under Review">Under Review <span class="chip-count" data-count="Under Review">0</span></button>
    <button class="chip" data-status="Endorsed">Endorsed <span class="chip-count" data-count="Endorsed">0</span></button>
    <button class="chip" data-status="Approved">Approved <span class="chip-count" data-count="Approved">0</span></button>
    <button class="chip" data-status="Rejected">Rejected <span class="chip-count" data-count="Rejected">0</span></button>
    <button class="chip" data-status="Returned">Returned <span class="chip-count" data-count="Returned">0</span></button>
    <button class="chip" data-status="Finalized">Finalized <span class="chip-count" data-count="Finalized">0</span></button>
  </div>

  <div class="mm-grid" id="memoGrid"></div>
  <div class="f-state" id="memoEmpty" hidden>
    <i class="bi bi-inbox"></i>
    <p>No memos match this view.</p>
  </div>

</div>

<script>
(() => {
  const STATUS_TINT = {
    'Draft': 't-neutral', 'Submitted': 't-amber', 'Under Review': 't-amber',
    'Endorsed': 't-sky', 'Approved': 't-green', 'Rejected': 't-rose',
    'Returned': 't-rose', 'Finalized': 't-dark',
  };
  const STATUSES = Object.keys(STATUS_TINT);

  const state = { status: 'All', q: '', sort: 'newest' };
  let allMemos = [];

  const grid  = document.getElementById('memoGrid');
  const empty = document.getElementById('memoEmpty');

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => (
    { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]
  ));

  const fmtDate = (d) => {
    if (!d) return '';
    const dt = new Date(d.replace(' ', 'T'));
    return isNaN(dt) ? '' : dt.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  };

  function matchesSearch(m) {
    if (!state.q) return true;
    const q = state.q.toLowerCase();
    return (m.subject || '').toLowerCase().includes(q)
        || (m.memo_id || '').toLowerCase().includes(q);
  }

  function cardHTML(m) {
    const tint = STATUS_TINT[m.status] || 't-neutral';
    const editable = m.status === 'Draft' || m.status === 'Returned';

    const callout = (m.status === 'Returned' && m.return_comment) ? `
      <div class="memo-callout">
        <div class="memo-callout-head">
          <i class="bi bi-arrow-counterclockwise"></i>
          Returned by ${esc(m.returned_by || 'Unknown')}${m.return_time ? ' · ' + esc(new Date(m.return_time.replace(' ','T')).toLocaleString()) : ''}
        </div>
        <p>${esc(m.return_comment)}</p>
      </div>` : '';

    return `
      <article class="memo-card">
        <div class="memo-card-top">
          <span class="pill ${tint}">${esc(m.status)}</span>
          <span class="memo-date">${esc(fmtDate(m.created_at))}</span>
        </div>
        <h3 class="memo-subject">${esc(m.subject || 'Untitled memo')}</h3>
        <div class="memo-meta">
          <span><i class="bi bi-hash"></i>${esc(m.memo_id || '—')}</span>
          <span><i class="bi bi-diagram-3"></i>${esc(m.stage || 'N/A')}</span>
        </div>
        ${callout}
        <div class="memo-actions">
          ${editable ? `<a href="edit_memo.php?memo_id=${encodeURIComponent(m.id)}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>` : ''}
          <a href="view_memo.php?memo_id=${encodeURIComponent(m.id)}" class="btn btn-sm btn-outline-success"><i class="bi bi-eye me-1"></i>View</a>
        </div>
      </article>`;
  }

  function updateChipCounts() {
    const searchable = allMemos.filter(matchesSearch);
    document.querySelector('[data-count="All"]').textContent = searchable.length;
    STATUSES.forEach(s => {
      const el = document.querySelector(`[data-count="${CSS.escape(s)}"]`);
      if (el) el.textContent = searchable.filter(m => m.status === s).length;
    });
  }

  function render() {
    let list = allMemos.filter(matchesSearch);
    if (state.status !== 'All') list = list.filter(m => m.status === state.status);

    list.sort((a, b) => {
      const da = new Date((a.created_at || '').replace(' ', 'T')).getTime() || 0;
      const db = new Date((b.created_at || '').replace(' ', 'T')).getTime() || 0;
      return state.sort === 'oldest' ? da - db : db - da;
    });

    grid.innerHTML = list.map(cardHTML).join('');
    empty.hidden = list.length > 0;
    updateChipCounts();
  }

  // Wiring
  document.getElementById('statusChips').addEventListener('click', (e) => {
    const chip = e.target.closest('.chip');
    if (!chip) return;
    document.querySelectorAll('.chip').forEach(c => c.classList.toggle('active', c === chip));
    state.status = chip.dataset.status;
    render();
  });

  let t;
  document.getElementById('memoSearch').addEventListener('input', (e) => {
    clearTimeout(t);
    t = setTimeout(() => { state.q = e.target.value.trim(); render(); }, 150);
  });

  document.getElementById('sortSelect').addEventListener('change', (e) => {
    state.sort = e.target.value;
    render();
  });

  fetch('get_my_memos.php')
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') { allMemos = data.data || []; render(); }
      else { grid.innerHTML = ''; empty.hidden = false; }
    })
    .catch(err => {
      console.error('Failed to load memos', err);
      grid.innerHTML = '';
      empty.hidden = false;
    });
})();
</script>
