<?php
session_start();
if (empty($_SESSION['brewery_admin'])) { header('Location: index.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }

$file = __DIR__ . '/../live-data/data/events.json';
$uploadsDir = __DIR__ . '/../live-data/uploads';
if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) { http_response_code(500); exit('Persistent uploads folder not found.'); }

$events = json_decode((string)@file_get_contents($file), true) ?: [];
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$existing = ($id !== null && isset($events[$id])) ? $events[$id] : [];

$title = trim((string)($_POST['title'] ?? ''));
$date = trim((string)($_POST['date'] ?? ''));
$time = trim((string)($_POST['time'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$status = (string)($_POST['status'] ?? 'Coming Up');
if (!in_array($status, ['Coming Up','Hidden'], true)) $status = 'Coming Up';
if ($title === '') { http_response_code(400); exit('Event title is required.'); }

$image = (string)($existing['image'] ?? '');
if (isset($_FILES['artwork']) && $_FILES['artwork']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['artwork']['error'] !== UPLOAD_ERR_OK) { http_response_code(400); exit('Artwork upload failed.'); }
    if ((int)$_FILES['artwork']['size'] > 2 * 1024 * 1024) { http_response_code(400); exit('Artwork must be 2 MB or smaller.'); }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg'
    ];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['artwork']['tmp_name']);
    if (!isset($allowed[$mime])) { http_response_code(400); exit('Artwork must be JPG, PNG, WebP or SVG.'); }
    $base = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
    $base = trim($base, '-') ?: 'event';
    $filename = 'event-' . $base . '-' . time() . '.' . $allowed[$mime];
    $target = $uploadsDir . '/' . $filename;
    if (!move_uploaded_file($_FILES['artwork']['tmp_name'], $target)) { http_response_code(500); exit('Unable to save artwork.'); }
    $image = 'live-data/uploads/' . $filename;
}

$record = [
    'title' => $title,
    'date' => $date,
    'time' => $time,
    'description' => $description,
    'status' => $status,
    'image' => $image
];

if ($id === null) $events[] = $record;
else $events[$id] = $record;

$json = json_encode(array_values($events), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false || file_put_contents($file, $json . PHP_EOL, LOCK_EX) === false) { http_response_code(500); exit('Unable to save event data. Check write permissions for live-data/data.'); }

header('Location: index.php');
exit;
