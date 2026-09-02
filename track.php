<?php
// Clevedon Brewery PWA - anonymous usage counter endpoint.
// Stores totals only. No names, IP addresses or other visitor details are retained.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$allowed = [
    'welcome_view',
    'guide_view',
    'install_click',
    'android_install',
    'ios_instructions'
];

$input = json_decode((string)file_get_contents('php://input'), true);
$event = is_array($input) ? (string)($input['event'] ?? '') : '';

if (!in_array($event, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid event']);
    exit;
}

$dataDir = __DIR__ . '/live-data/data';
$statsFile = $dataDir . '/stats.json';

if (!is_dir($dataDir) && !mkdir($dataDir, 0755, true) && !is_dir($dataDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to create data directory']);
    exit;
}

$handle = fopen($statsFile, 'c+');
if ($handle === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to open statistics file']);
    exit;
}

if (!flock($handle, LOCK_EX)) {
    fclose($handle);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to lock statistics file']);
    exit;
}

$existing = stream_get_contents($handle);
$stats = json_decode($existing ?: '{}', true);
if (!is_array($stats)) {
    $stats = [];
}

foreach ($allowed as $key) {
    if (!isset($stats[$key]) || !is_numeric($stats[$key])) {
        $stats[$key] = 0;
    }
}

$stats[$event]++;
$stats['last_updated'] = gmdate('c');

rewind($handle);
ftruncate($handle, 0);
$written = fwrite($handle, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
fflush($handle);
flock($handle, LOCK_UN);
fclose($handle);

if ($written === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to save statistics']);
    exit;
}

echo json_encode(['success' => true]);
