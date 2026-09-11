<?php
/**
 * Shared .es-topbar chrome for every app shell (e-Memo, e-Services, Reports,
 * Admin console) — the bar with the page name and the user avatar dropdown
 * that sits above <main class="es-content"> inside .es-shell/.es-main.
 *
 * Included by app/ememo/header.php, app/eservice/inc/layout.php,
 * app/reports/inc/layout.php and hub/inc/layout.php.
 */

if (!function_exists('e')) {
    function e($s): string {
        return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/**
 * @param string $dropdownHtml Raw <li> markup for the user dropdown-menu body.
 * @param string $extraHtml    Raw markup rendered before the user dropdown
 *                             (e.g. e-Memo's notification bell button).
 */
function render_es_topbar(string $title, string $initials, string $userName, string $dropdownHtml, string $extraHtml = ''): void {
    ?>
    <header class="es-topbar">
      <div class="es-topbar-inner">
        <span class="es-page-name"><?= e($title) ?></span>
        <div class="es-user d-flex align-items-center gap-1">
          <button type="button" class="es-theme-toggle" id="esThemeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
            <i class="bi bi-moon-stars-fill es-theme-icon-dark"></i>
            <i class="bi bi-sun-fill es-theme-icon-light"></i>
          </button>
          <?= $extraHtml ?>
          <div class="dropdown">
            <button type="button" class="es-user-btn" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="es-avatar"><?= e($initials ?: 'U') ?></span>
              <span class="es-user-name d-none d-md-inline"><?= e($userName) ?></span>
              <i class="bi bi-chevron-down small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end mt-2">
              <?= $dropdownHtml ?>
            </ul>
          </div>
        </div>
      </div>
    </header>
    <?php
}
