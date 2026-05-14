<?php
declare(strict_types=1);

/**
 * 👑 Hyper-Industrial CDN Proxy v3.5 (Self-Healing)
 * * Improvements:
 * - Protocol Healer: Automatically fixes 'https:/' to 'https://'.
 * - Regex Fail-safe: Extracts host even when parse_url fails.
 * - Deep Debugging: Returns exactly what the parser sees.
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

// --- [4] Security & Whitelist ---
$allowed = ['esm.sh', 'cdn.jsdelivr.net', 'unpkg.com'];
$isAllowed = false;
foreach ($allowed as $domain) {
    if (stripos($host, $domain) !== false) {
        $isAllowed = true;
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
    CURLOPT_USERAGENT => 'CrossUI-Studio-Proxy/3.5'
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