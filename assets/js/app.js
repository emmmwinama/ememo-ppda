/* ==========================================================================
   PPDA platform — shared shell behaviour.
   Loaded at the end of <body> by app/eservice/inc/layout.php and
   hub/inc/layout.php. Pairs with assets/css/app.css.
   Provides: right-side slide-over drawer ([data-drawer]), sidebar collapse
   toggle (#esNavToggle, persisted), scroll-position restore across POST
   redirects, and window.esToast(msg, type) + window.__esFlash draining.
   ========================================================================== */
(function () {
  var dw = document.getElementById('esDrawer');
  if (dw) {
    var body  = dw.querySelector('.es-drawer-body');
    var title = dw.querySelector('.es-drawer-title');

    var open = function (url, label) {
      title.textContent = label || 'Details';
      body.innerHTML = '<div class="es-drawer-loading"><span class="spinner-border spinner-border-sm"></span></div>';
      dw.hidden = false;
      requestAnimationFrame(function () { dw.classList.add('is-open'); });
      document.body.style.overflow = 'hidden';
      fetch(url, { headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.text(); })
        .then(function (html) { body.innerHTML = html; })
        .catch(function () { body.innerHTML = '<div class="f-state is-error" style="padding:3rem 1rem;"><p>Could not load the form.</p></div>'; });
    };
    var close = function () {
      dw.classList.remove('is-open');
      document.body.style.overflow = '';
      setTimeout(function () { dw.hidden = true; body.innerHTML = ''; }, 240);
    };

    document.addEventListener('click', function (e) {
      var t = e.target.closest('[data-drawer]');
      if (t) { e.preventDefault(); open(t.getAttribute('data-drawer'), t.getAttribute('data-drawer-title')); return; }
      if (e.target.closest('[data-drawer-close]')) close();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !dw.hidden) close(); });
  }
})();

(function () {
  var shell = document.getElementById('esShell');
  var btn = document.getElementById('esNavToggle');
  if (!shell || !btn) return;
  btn.addEventListener('click', function () {
    var on = shell.classList.toggle('nav-collapsed');
    try { localStorage.setItem('esNav', on ? 'collapsed' : 'expanded'); } catch (e) {}
    btn.title = on ? 'Expand menu' : 'Collapse menu';
  });
})();

/* Dark/light toggle — the icon swap is pure CSS (theme.css), driven off the
   data-theme attribute this sets. The same attribute is applied on first
   paint by an inline script in each app's <head> (reads localStorage.esTheme)
   so there's no flash before this script runs. */
(function () {
  var btn = document.getElementById('esThemeToggle');
  if (!btn) return;
  var root = document.documentElement;
  var mql = window.matchMedia('(prefers-color-scheme: dark)');
  btn.addEventListener('click', function () {
    var current = root.getAttribute('data-theme') || (mql.matches ? 'dark' : 'light');
    var next = current === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('esTheme', next); } catch (e) {}
  });
})();

/* Keep the scroll position across a form POST + redirect that lands back on the
   same page (workflow actions, inline row editors, checklists, votes, …). */
(function () {
  var KEY = 'esScroll';
  var path = location.pathname + location.search.replace(/[?&]page=\d+/, '');
  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

  try {
    var raw = sessionStorage.getItem(KEY);
    sessionStorage.removeItem(KEY);
    if (raw) {
      var s = JSON.parse(raw);
      if (s && s.p === path && typeof s.y === 'number') {
        var go = function () { window.scrollTo(0, s.y); };
        go();
        requestAnimationFrame(go);
        window.addEventListener('load', function () { requestAnimationFrame(go); });
        setTimeout(go, 120);
      }
    }
  } catch (e) {}

  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!f || (f.method && f.method.toLowerCase() !== 'post')) return;
    if (!f.getAttribute('action')) return;      // AJAX / JS-handled forms have no action
    if (f.hasAttribute('data-no-restore')) return;
    try { sessionStorage.setItem(KEY, JSON.stringify({ p: path, y: window.pageYOffset })); } catch (e) {}
  }, true);
})();

/* Floating toast notifications (bottom-right) — window.esToast(msg, type) */
(function () {
  var wrap = document.getElementById('esToasts');
  window.esToast = function (msg, type) {
    if (!wrap || !msg) return;
    var kind = (type === 'error' || type === 'danger' || type === 'warning') ? 'error' : 'success';
    var el = document.createElement('div');
    el.className = 'es-toast t-' + kind;
    el.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    el.innerHTML = '<i class="bi bi-' + (kind === 'error' ? 'exclamation-triangle-fill' : 'check-circle-fill')
      + '"></i><div class="msg"></div><button class="x" type="button" aria-label="Dismiss">×</button>';
    el.querySelector('.msg').textContent = String(msg);
    wrap.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('is-in'); });
    var t = setTimeout(close, kind === 'error' ? 7000 : 4000);
    function close() { clearTimeout(t); el.classList.remove('is-in'); el.classList.add('is-out');
      setTimeout(function () { el.remove(); }, 320); }
    el.querySelector('.x').addEventListener('click', close);
    el.addEventListener('mouseenter', function () { clearTimeout(t); });
    el.addEventListener('mouseleave', function () { t = setTimeout(close, 2500); });
  };
  (window.__esFlash || []).forEach(function (f) { window.esToast(f.msg, f.type); });
  window.__esFlash = [];
})();
