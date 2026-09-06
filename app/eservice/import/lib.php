<?php
/**
 * Legacy → es_* import engine.  Used by import/run.php (CLI) and import/index.php (web).
 *
 * It opens TWO connections:
 *   - $dst : the ememo / target database  (from config/database.php -> $conn)
 *   - $src : the legacy e-Services database (from import/config.php)
 *
 * Every importable es_ table has a UNIQUE `legacy_id`, so every phase is
 * idempotent: re-running updates existing rows instead of duplicating them.
 * Pass --dry-run to read + report without writing.
 */

class EsImport
{
    public ?mysqli $src = null;                 // legacy DB (null in dump mode)
    public mysqli $dst;
    public bool $dry;
    public ?array $dump = null;                 // [table => [rows]] when reading a .sql file
    public array $counts = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
    /** @var string[] */
    public array $warnings = [];

    private array $cache = [];

    public function __construct(mysqli $dst, ?array $srcCfg, bool $dry = false)
    {
        $this->dst = $dst;
        $this->dry = $dry;
        mysqli_report(MYSQLI_REPORT_OFF);
        // Lenient for the migration: legacy free-text can exceed our VARCHAR limits.
        @$this->dst->query("SET SESSION sql_mode = ''");

        if ($srcCfg === null) {
            return;  // dump mode — call setDump() next
        }
        $this->src = @new mysqli(
            $srcCfg['host'] ?? 'localhost',
            $srcCfg['user'] ?? '',
            $srcCfg['pass'] ?? '',
            $srcCfg['name'] ?? '',
            (int) ($srcCfg['port'] ?? 3306)
        );
        if ($this->src->connect_error) {
            throw new RuntimeException('Cannot connect to the legacy database: ' . $this->src->connect_error);
        }
        $this->src->set_charset($srcCfg['charset'] ?? 'utf8mb4');
    }

    /** Feed rows parsed from a .sql dump instead of a live source DB. */
    public function setDump(array $parsed): void { $this->dump = $parsed; }

    public function warn(string $m): void { $this->warnings[] = $m; }

    // ---- source helpers (live DB or parsed dump) -----------------------
    public function srcHas(string $table): bool
    {
        if ($this->dump !== null) return isset($this->dump[$table]);
        $t = $this->src->real_escape_string($table);
        return (bool) $this->src->query("SHOW TABLES LIKE '$t'")->num_rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function srcAll(string $sql): array
    {
        if ($this->dump !== null) {
            // dump mode only understands "SELECT * FROM `table`"
            if (preg_match('/FROM\s+`([^`]+)`/i', $sql, $m)) {
                return $this->dump[$m[1]] ?? [];
            }
            return [];   // aggregate / diagnostic queries — nothing to serve
        }
        $r = $this->src->query($sql);
        if (!$r) { $this->warn("source query failed: " . $this->src->error); return []; }
        return $r->fetch_all(MYSQLI_ASSOC);
    }

    public function srcCount(string $table): int
    {
        if ($this->dump !== null) return count($this->dump[$table] ?? []);
        if (!$this->srcHas($table)) return 0;
        $r = $this->src->query("SELECT COUNT(*) FROM `$table`");
        return $r ? (int) $r->fetch_row()[0] : 0;
    }

    // ---- normalising ---------------------------------------------------
    public function nz($v)          { return ($v === '' || $v === null) ? null : $v; }

    /** First non-empty trimmed value among the given candidate columns. */
    public function firstNonEmpty(array $row, array $cols): ?string
    {
        foreach ($cols as $c) {
            if (isset($row[$c]) && trim((string) $row[$c]) !== '') return trim((string) $row[$c]);
        }
        return null;
    }
    public function d(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '' || str_starts_with($v, '0000-00-00')) return null;
        $ts = strtotime($v);
        return $ts ? date('Y-m-d', $ts) : null;
    }
    public function dt(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '' || str_starts_with($v, '0000-00-00')) return null;
        $ts = strtotime($v);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
    /** legacy yes/no-ish integer -> 0/1 (1 == yes; everything else no) */
    public function yn($v): int { return ((string) $v === '1') ? 1 : 0; }

    // ---- ememo user resolution --------------------------------------
    public function userByUsername(?string $u): ?int
    {
        $u = trim((string) $u);
        if ($u === '') return null;
        $key = 'u:' . strtolower($u);
        if (array_key_exists($key, $this->cache)) return $this->cache[$key];
        $st = $this->dst->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $st->bind_param('s', $u);
        $st->execute();
        $row = $st->get_result()->fetch_row();
        $st->close();
        return $this->cache[$key] = ($row ? (int) $row[0] : null);
    }
    public function userByEmail(?string $e): ?int
    {
        $e = trim((string) $e);
        if ($e === '') return null;
        $key = 'e:' . strtolower($e);
        if (array_key_exists($key, $this->cache)) return $this->cache[$key];
        $st = $this->dst->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $st->bind_param('s', $e);
        $st->execute();
        $row = $st->get_result()->fetch_row();
        $st->close();
        return $this->cache[$key] = ($row ? (int) $row[0] : null);
    }

    // ---- target lookup by legacy_id --------------------------------
    /** @return int|null  new PK for a legacy row, or null if not imported */
    public function newId(string $table, $legacyId, string $col = 'legacy_id'): ?int
    {
        if ($legacyId === null || $legacyId === '') return null;
        $key = "id:$table:$col:$legacyId";
        if (array_key_exists($key, $this->cache)) return $this->cache[$key];
        $st = $this->dst->prepare("SELECT id FROM `$table` WHERE `$col` = ? LIMIT 1");
        $st->bind_param('s', $legacyId);
        $st->execute();
        $row = $st->get_result()->fetch_row();
        $st->close();
        return $this->cache[$key] = ($row ? (int) $row[0] : null);
    }
    public function forgetCache(): void { $this->cache = []; }

    // ---- the workhorse -----------------------------------------------
    /**
     * Insert or update one row, keyed by $where (defaults to legacy_id).
     * @param array<string,mixed> $data   column => value  (PK excluded)
     * @param array<string,mixed> $where  column => value  used to find an existing row
     * @return int  the row's PK (0 in dry-run inserts)
     */
    public function upsert(string $table, array $data, array $where = []): int
    {
        if (!$where && array_key_exists('legacy_id', $data)) {
            $where = ['legacy_id' => $data['legacy_id']];
        }

        // helper: find the existing row's id via the natural key
        $findId = function () use ($table, $where): ?int {
            if (!$where) return null;
            $conds = []; $wt = ''; $wa = [];
            foreach ($where as $c => $v) {
                if ($v === null) { $conds[] = "`$c` IS NULL"; continue; }
                $conds[] = "`$c` = ?"; $wt .= $this->bindType($v); $wa[] = $v;
            }
            $st = $this->dst->prepare("SELECT id FROM `$table` WHERE " . implode(' AND ', $conds) . " LIMIT 1");
            if ($wt !== '') $st->bind_param($wt, ...$wa);
            $st->execute();
            $row = $st->get_result()->fetch_row();
            $st->close();
            return $row ? (int) $row[0] : null;
        };

        if ($this->dry) {
            $existing = $findId();
            $existing ? $this->counts['updated']++ : $this->counts['inserted']++;
            return $existing ?? 0;
        }

        // INSERT ... ON DUPLICATE KEY UPDATE — one round-trip on a fresh import,
        // relies on the UNIQUE key every importable table carries.
        $cols  = array_keys($data);
        $ph    = implode(',', array_fill(0, count($cols), '?'));
        $types = '';
        $args  = [];
        foreach ($data as $v) { $types .= $this->bindType($v); $args[] = $v; }
        $set = implode(', ', array_map(fn($c) => "`$c`=VALUES(`$c`)", $cols));

        $st = $this->dst->prepare(
            "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES ($ph) ON DUPLICATE KEY UPDATE $set"
        );
        $st->bind_param($types, ...$args);
        if (!$st->execute()) {
            $this->warn("upsert $table: " . $this->dst->error);
            $st->close();
            $this->counts['skipped']++;
            return 0;
        }
        $aff = $this->dst->affected_rows;   // 1 = inserted, 2 = updated, 0 = unchanged
        $id  = (int) $this->dst->insert_id;
        $st->close();

        if ($aff === 1) { $this->counts['inserted']++; return $id ?: ($findId() ?? 0); }
        $this->counts['updated']++;
        return $id ?: ($findId() ?? 0);
    }

    private function bindType($v): string
    {
        if (is_int($v) || is_bool($v)) return 'i';
        if (is_float($v)) return 'd';
        return 's';   // strings and NULL
    }

    // ---- summary -----------------------------------------------------
    public function record(int $ranBy = null, string $phase = 'all'): void
    {
        if ($this->dry) return;
        $w = $this->warnings ? implode("\n", array_slice($this->warnings, 0, 400)) : null;
        $st = $this->dst->prepare(
            "INSERT INTO es_import_log (phase, dry_run, inserted, updated, skipped, warnings, ran_by)
             VALUES (?,?,?,?,?,?,?)"
        );
        $dry = 0;
        $st->bind_param('siiiisi', $phase, $dry, $this->counts['inserted'], $this->counts['updated'], $this->counts['skipped'], $w, $ranBy);
        $st->execute();
        $st->close();
    }

    public function summaryLine(): string
    {
        return sprintf(
            "%s: +%d inserted, ~%d updated, %d skipped, %d warning(s)%s",
            $this->dry ? 'DRY-RUN' : 'DONE',
            $this->counts['inserted'], $this->counts['updated'], $this->counts['skipped'],
            count($this->warnings),
            $this->warnings ? "\n  - " . implode("\n  - ", array_slice($this->warnings, 0, 25)) : ''
        );
    }
}

/** Load import/config.php (copied from config.sample.php). */
function es_import_src_config(): array
{
    $f = __DIR__ . '/config.php';
    if (!is_file($f)) {
        throw new RuntimeException('import/config.php not found — copy import/config.sample.php to import/config.php and set the legacy DB credentials.');
    }
    $cfg = require $f;
    if (!is_array($cfg) || empty($cfg['name'])) {
        throw new RuntimeException('import/config.php must return an array with at least "name" (legacy database name).');
    }
    return $cfg;
}

/** Target ($conn) without pulling in the auth/session bootstrap. */
function es_import_target(): mysqli
{
    require __DIR__ . '/../../../config/database.php';   // -> $conn
    /** @var mysqli $conn */
    $conn->set_charset('utf8mb4');
    return $conn;
}
