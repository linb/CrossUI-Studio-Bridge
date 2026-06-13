<?php
declare(strict_types=1);

/**
 * 👑 Hyper-Industrial CDN Proxy v3.6 (Self-Healing, Hardened)
 * * Improvements:
 * - Protocol Healer: Automatically fixes 'https:/' to 'https://'.
 * - Regex Fail-safe: Extracts host even when parse_url fails.
 * - Deep Debugging: Returns exactly what the parser sees.
 * * v3.6 SECURITY (audit fix):
 * - Whitelist is now an exact-host / dot-boundary suffix match. The old
 *   stripos() substring check allowed "esm.sh.evil.com" → SSRF/open proxy.
 * - Scheme is restricted to http/https, both for the request and for any
 *   redirects curl follows (no file://, gopher://, etc.).
 * - Host is normalized (userinfo/port stripped) before matching.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');

$requestId = 'cdn-p-' . bin2hex(random_bytes(8));

// --- [1] Raw URL Extraction ---
$rawQuery = $_SERVER['QUERY_STRING'] ?? '';
$targetUrl = '';
if (preg_match('/url=([^&]+)/i', $rawQuery, $matches)) {
    $targetUrl = urldecode($matches[1]);
} else {
    $targetUrl = $_GET['url'] ?? '';
}

if (empty($targetUrl)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Empty URL parameter']);
    exit;
}

// --- [2] Self-Healing & Normalization ---
// Fix common typo: 'https:/' or 'http:/' -> 'https://'
$targetUrl = preg_replace('/^(https?):\/([^\/])/i', '$1://$2', $targetUrl);

// --- [3] Robust Host Extraction ---
$parsed = parse_url($targetUrl);
$host = $parsed['host'] ?? '';
$scheme = $parsed['scheme'] ?? '';

// Fallback: If parse_url fails due to complex characters, use Regex
if (empty($host)) {
    // Matches anything between :// and the next / or ?
    if (preg_match('/(?:https?:\/\/|^\/\/)?(?<host>[^\/\?#]+)/i', $targetUrl, $m)) {
        $host = $m['host'];
    }
}

// --- [4] Security & Whitelist (hardened, v3.6) ---

// 4a. Scheme guard: only http/https may pass (the frontend always sends
// full https URLs; anything else is an attack, e.g. file:// or gopher://).
if (!preg_match('/^https?:\/\//i', $targetUrl)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'requestId' => $requestId,
        'error' => 'Forbidden Scheme (http/https only)'
    ]);
    exit;
}

// 4b. Host normalization: the regex fallback can leave userinfo
// ("user:pass@host") or a port ("host:8443") in $host — strip both,
// then lowercase and drop a trailing dot ("esm.sh." === "esm.sh").
if (($atPos = strrpos($host, '@')) !== false) {
    $host = substr($host, $atPos + 1);
}
if (($colonPos = strpos($host, ':')) !== false) {
    $host = substr($host, 0, $colonPos);
}
$host = strtolower(rtrim($host, '.'));

// 4c. Whitelist: EXACT match or true subdomain (dot-boundary suffix).
// NOTE: substring matching (the old stripos check) is forbidden here —
// "esm.sh.evil.com" contains "esm.sh" but must NOT be allowed.
$allowed = ['esm.sh', 'cdn.jsdelivr.net', 'unpkg.com'];
$isAllowed = false;
foreach ($allowed as $domain) {
    if ($host === $domain) {
        $isAllowed = true;
        break;
    }
    $suffix = '.' . $domain;
    $suffixLen = strlen($suffix);
    if (strlen($host) > $suffixLen && substr($host, -$suffixLen) === $suffix) {
        $isAllowed = true; // e.g. "fastly.jsdelivr.net" is NOT matched here; only "*.cdn.jsdelivr.net"
        break;
    }
}

if (!$isAllowed) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'requestId' => $requestId,
        'error' => 'Forbidden Domain',
        'debug' => [
            'original_url' => $targetUrl,
            'extracted_host' => $host,
            'parse_url_result' => $parsed
        ]
    ]);
    exit;
}

// --- [5] cURL Execution ---
$method = (isset($_GET['method']) && strtoupper($_GET['method']) === 'HEAD') ? 'HEAD' : 'GET';
$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_NOBODY => ($method === 'HEAD'),
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 10,
    // v3.6: never follow redirects into exotic schemes (file://, gopher://, ...)
    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_USERAGENT => 'CrossUI-Studio-Proxy/3.6'
]);

$response = curl_exec($ch);
$info = curl_getinfo($ch);
$curlError = curl_error($ch);
curl_close($ch);

// --- [6] Final Response ---
if ($response === false) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $curlError]);
    exit;
}

echo json_encode([
    'ok' => ($info['http_code'] < 400),
    'status' => $info['http_code'],
    'contentType' => $info['content_type'] ?? 'unknown',
    'size' => (int) $info['download_content_length'],
    'requestId' => $requestId,
    'healedUrl' => $targetUrl // Show the fixed URL
]);