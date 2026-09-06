<?php
/**
 * Minimal MySQL-dump reader.  Parses a phpMyAdmin/mysqldump .sql file and
 * returns  [ table_name => [ [col => value, ...], ... ] ]  for every
 * `INSERT INTO` found.  String escapes (\' \\ \n \r \t \0 \") and NULL are
 * decoded; `''`-style doubled quotes handled too.  Numbers stay as strings.
 *
 * Good enough for our own bundled dumps; not a general SQL parser.
 */

function es_parse_dump(string $file): array
{
    $sql = @file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Cannot read dump file: $file");
    }

    $out = [];
    $len = strlen($sql);
    $i = 0;

    while (($p = stripos($sql, 'INSERT INTO', $i)) !== false) {
        if (!preg_match('/INSERT INTO\s+`([^`]+)`\s*\(([^)]*)\)\s*VALUES\s*/A', substr($sql, $p, 20000), $m)) {
            $i = $p + 11;
            continue;
        }
        $table = $m[1];
        $cols  = array_map(fn($c) => trim($c, " `\r\n\t"), explode(',', $m[2]));
        $nCols = count($cols);
        $pos   = $p + strlen($m[0]);

        $rows        = [];
        $row         = [];
        $buf         = '';
        $tokIsString = false;
        $inStr       = false;
        $depth       = 0;

        $flush = function () use (&$row, &$buf, &$tokIsString) {
            if ($tokIsString) {
                $row[] = $buf;
            } else {
                $t = trim($buf, " \r\n\t");
                $row[] = ($t === '' || strcasecmp($t, 'NULL') === 0) ? null : $t;
            }
            $buf = '';
            $tokIsString = false;
        };

        for (; $pos < $len; $pos++) {
            $ch = $sql[$pos];

            if ($inStr) {
                if ($ch === '\\') {
                    $next = $sql[$pos + 1] ?? '';
                    $buf .= match ($next) {
                        'n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0",
                        "'" => "'", '"' => '"', '\\' => '\\', default => $next,
                    };
                    $pos++;
                } elseif ($ch === "'") {
                    if (($sql[$pos + 1] ?? '') === "'") { $buf .= "'"; $pos++; }
                    else { $inStr = false; }
                } else {
                    $buf .= $ch;
                }
                continue;
            }

            if ($ch === "'") { $inStr = true; $tokIsString = true; $buf = ''; continue; }
            if ($ch === '(') { if (++$depth === 1) { $row = []; $buf = ''; $tokIsString = false; } continue; }
            if ($ch === ',' && $depth === 1) { $flush(); continue; }
            if ($ch === ')' && $depth === 1) {
                $flush();
                $row = array_slice($row, 0, $nCols);
                while (count($row) < $nCols) $row[] = null;
                $rows[] = array_combine($cols, $row);
                $depth--;
                continue;
            }
            if ($ch === ';' && $depth === 0) { $pos++; break; }
            // outside a string: accumulate only NULL/number chars (string content
            // is handled above); whitespace around tokens is ignored.
            if ($depth >= 1 && !$tokIsString) $buf .= $ch;
        }

        $out[$table] = array_merge($out[$table] ?? [], $rows);
        $i = $pos;
    }

    return $out;
}
