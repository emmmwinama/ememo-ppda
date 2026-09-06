<?php
/**
 * Legacy e-Services database connection for the importer.
 *
 *   cp import/config.sample.php import/config.php
 *   # then edit import/config.php with the real credentials
 *
 * import/config.php is git-ignored.  This can point at:
 *   - the live legacy DB, or
 *   - a local restore of the legacy dump (recommended for the first run).
 *
 * Restore the legacy dump locally, e.g.:
 *   mysql -u root -e "CREATE DATABASE ppda_e_services_legacy CHARACTER SET utf8mb4"
 *   mysql -u root ppda_e_services_legacy < /path/to/legacy_data_dump.sql
 */

return [
    'host'    => 'localhost',
    'user'    => 'root',
    'pass'    => '',
    'name'    => 'ppda_e_services_legacy',   // legacy database name
    'port'    => 3306,
    'charset' => 'utf8mb4',                  // MySQL converts each legacy table (latin1/utf8) on read
];
