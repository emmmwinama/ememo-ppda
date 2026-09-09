<?php
/**
 * Supplier registration certificate — shared helpers for
 * supplier_certificate.php (PDF) and supplier_verify.php (public QR target).
 */

// Who the certificate is issued "for" — matches the sample PPDA certificate.
if (!defined('ES_CERT_SIGNATORY'))       define('ES_CERT_SIGNATORY', 'Dr. Edington Chilapondwa');
if (!defined('ES_CERT_SIGNATORY_TITLE')) define('ES_CERT_SIGNATORY_TITLE', 'DIRECTOR GENERAL');

// Salt for the tamper-check token on a verification link. Not a security
// boundary (the verify page only shows public registry facts) — it just stops
// someone hand-editing ?code= in the URL.
if (!defined('ES_CERT_TOKEN_SALT'))      define('ES_CERT_TOKEN_SALT', 'PPDA-eServices-cert-v1');

/** Short check token bound to a supplier code. */
function es_cert_token(string $code): string
{
    return substr(hash_hmac('sha256', 'cert|' . $code, ES_CERT_TOKEN_SALT), 0, 12);
}

/** True when $token matches $code. */
function es_cert_token_ok(string $code, string $token): bool
{
    return $token !== '' && hash_equals(es_cert_token($code), $token);
}

/**
 * Absolute URL to the public verify page for a supplier code.
 * Built from the current request so it works on any deployment path.
 */
function es_cert_verify_url(string $code): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/app/eservice/x')), '/');
    return $scheme . '://' . $host . $dir . '/supplier_verify.php'
         . '?code=' . rawurlencode($code) . '&c=' . es_cert_token($code);
}

/**
 * The certificate's field set for a supplier row (already fetched with
 * country_name), plus its categories. Returns [fields=>[label=>value], cert=>row|null].
 */
function es_cert_data(mysqli $conn, array $sup): array
{
    $cats = ['goods' => [], 'services' => [], 'works' => []];
    if ($res = $conn->query(
        "SELECT c.type, c.name FROM es_supplier_category sc
           JOIN es_category c ON c.id = sc.category_id
          WHERE sc.supplier_id = " . (int) $sup['id'] . " ORDER BY c.type, c.name"
    )) {
        while ($r = $res->fetch_assoc()) $cats[$r['type']][] = $r['name'];
        $res->free();
    }

    $cert = null;
    if ($res = $conn->query(
        "SELECT certificate_no, issue_date, expire_date FROM es_supplier_certificate
          WHERE supplier_id = " . (int) $sup['id'] . " ORDER BY COALESCE(issue_date,'1900-01-01') DESC, id DESC LIMIT 1"
    )) {
        $cert = $res->fetch_assoc() ?: null;
        $res->free();
    }

    $expire = $cert['expire_date'] ?? ($sup['expire_date'] ?? null);
    $catLine = fn(array $a) => $a ? implode('; ', $a) : 'None';

    return [
        'cert'   => $cert,
        'expire' => $expire,
        'fields' => [
            'Supplier Code'            => $sup['supplier_code'] ?: '—',
            'Supplier Name'            => $sup['name'],
            'Postal Address'           => $sup['postal_address'] ?: '—',
            'Supplier Location'        => $sup['city'] ?: ($sup['physical_address'] ?: '—'),
            'Website'                  => $sup['website'] ?: '',
            'Country of Establishment' => $sup['country_name'] ?: '—',
            'Goods Category'           => $catLine($cats['goods']),
            'Services Category'        => $catLine($cats['services'] ?: $cats['works']),
            'Expire Date'              => $expire ? date('d/m/Y', strtotime($expire)) : '—',
        ],
    ];
}
