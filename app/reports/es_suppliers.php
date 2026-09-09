<?php
/** Report — supplier register. */
require __DIR__ . '/inc/layout.php';
rpt_require();

if (!rpt_has_table('es_supplier')) { rpt_head('Supplier register', 'es_sup'); echo '<div class="f-state"><p>Supplier tables not present.</p></div>'; rpt_foot(); exit; }

$total    = (int) db_scalar("SELECT COUNT(*) FROM es_supplier");
$active   = (int) db_scalar("SELECT COUNT(*) FROM es_supplier WHERE status = 'active'");
$expired  = (int) db_scalar("SELECT COUNT(*) FROM es_supplier WHERE status = 'expired' OR (expire_date IS NOT NULL AND expire_date < CURDATE())");
$exp30    = (int) db_scalar("SELECT COUNT(*) FROM es_supplier WHERE expire_date BETWEEN CURDATE() AND (CURDATE() + INTERVAL 30 DAY)");
$exp90    = (int) db_scalar("SELECT COUNT(*) FROM es_supplier WHERE expire_date BETWEEN CURDATE() AND (CURDATE() + INTERVAL 90 DAY)");

$byStatus = db_all("SELECT status, COUNT(*) n FROM es_supplier GROUP BY status ORDER BY n DESC");
$bySource = db_all("SELECT source, COUNT(*) n FROM es_supplier GROUP BY source ORDER BY n DESC");
$byYear   = db_all("SELECT YEAR(date_registered) y, COUNT(*) n FROM es_supplier WHERE date_registered IS NOT NULL GROUP BY y ORDER BY y");

$byCountry = db_all(
    "SELECT COALESCE(c.name,'(unknown)') country, COUNT(*) n,
            SUM(s.status='active') active,
            SUM(s.expire_date < CURDATE()) expired
       FROM es_supplier s LEFT JOIN es_country c ON c.id = s.country_id
      GROUP BY country ORDER BY n DESC LIMIT 25"
);
$cols = [
    'country' => 'Country',
    'n'       => ['label' => 'Suppliers', 'align' => 'end'],
    'active'  => ['label' => 'Active', 'align' => 'end'],
    'expired' => ['label' => 'Expired', 'align' => 'end'],
];
rpt_maybe_csv('es-supplier-register-by-country', $cols, $byCountry);

$byCat = rpt_has_table('es_supplier_category') ? db_all(
    "SELECT c.type, COUNT(DISTINCT sc.supplier_id) n
       FROM es_supplier_category sc JOIN es_category c ON c.id = sc.category_id
      GROUP BY c.type ORDER BY n DESC"
) : [];

rpt_head('Supplier register', 'es_sup', 'Composition of the supplier register, and who is about to lapse');
?>
<p class="text-muted small mb-3">This report is a live snapshot — it is not date-filtered.</p>
<?php
rpt_kpis([
    [$total,   'Suppliers on register', 's-sky',   'bi-building'],
    [$active,  'Active',                's-green', 'bi-building-check'],
    [$expired, 'Expired',               's-rose',  'bi-building-x'],
    [$exp30,   'Expiring within 30 days', 's-amber', 'bi-calendar-x'],
    [$exp90,   'Expiring within 90 days', 's-violet', 'bi-calendar-week'],
]);
echo '<div class="row g-3 mt-1"><div class="col-lg-6">';
rpt_bars(array_map(fn($x) => [ucfirst((string) $x['status']), $x['n']], $byStatus), 'By status', 'bi-list-check');
echo '</div><div class="col-lg-6">';
rpt_bars(array_map(fn($x) => [ucfirst((string) $x['source']), $x['n']], $bySource), 'By source', 'bi-database');
echo '</div>';
if ($byCat) { echo '<div class="col-lg-6">'; rpt_bars(array_map(fn($x) => [ucfirst((string) $x['type']), $x['n']], $byCat), 'Suppliers registered in each category type', 'bi-tags'); echo '</div>'; }
if ($byYear) { echo '<div class="col-lg-6">'; rpt_bars(array_map(fn($x) => [(string) $x['y'], $x['n']], $byYear), 'Registered per year', 'bi-calendar3'); echo '</div>'; }
echo '</div><div class="mt-3">';
rpt_table($cols, $byCountry, 'By country (top 25)', 'bi-globe');
echo '</div>';
rpt_foot();
