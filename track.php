<?php
// Clevedon Brewery PWA - anonymous usage counter endpoint.
// This counter stores totals only; it does not add visitor details to the statistics file.

require_once __DIR__ . '/lib/statistics.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function tracking_response(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    tracking_response(405, ['success' => false, 'error' => 'Method not allowed']);
}

$fetchSite = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'none'], true)) {
    tracking_response(403, ['success' => false, 'error' => 'Cross-site request denied']);
}

$contentType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
$mediaType = trim(explode(';', $contentType, 2)[0]);
if ($mediaType !== 'application/json') {
    tracking_response(415, ['success' => false, 'error' => 'JSON request required']);
}

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 1024) {
    tracking_response(413, ['success' => false, 'error' => 'Request too large']);
}

$rawInput = file_get_contents('php://input');
if ($rawInput === false || strlen($rawInput) > 1024) {
    tracking_response(413, ['success' => false, 'error' => 'Request too large']);
}

$input = json_decode($rawInput, true);
if (!is_array($input) || json_last_error() !== JSON_ERROR_NONE) {
    tracking_response(400, ['success' => false, 'error' => 'Invalid JSON']);
}

$event = (string)($input['event'] ?? '');
if (!in_array($event, brewery_stats_event_keys(), true)) {
    tracking_response(400, ['success' => false, 'error' => 'Invalid event']);
}

$storageError = null;
if (!brewery_stats_increment($event, $storageError)) {
    error_log('Brewery statistics error: ' . ($storageError ?? 'Unknown storage error'));
    tracking_response(500, ['success' => false, 'error' => 'Unable to save statistics']);
}

tracking_response(200, ['success' => true]);
