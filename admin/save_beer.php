<?php
session_start();
if (empty($_SESSION['brewery_admin'])) {
    header('Location: index.php');
    exit;
}

$dataFile = __DIR__ . '/../data/beers.json';
$assetsDir = realpath(__DIR__ . '/../assets');
if ($assetsDir === false) {
    http_response_code(500);
    exit('Assets folder not found.');
}

$beers = json_decode((string)@file_get_contents($dataFile), true);
if (!is_array($beers)) $beers = [];

$id = isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : null;
$name = trim((string)($_POST['name'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$status = (string)($_POST['status'] ?? 'On Tap');
$existingImage = trim((string)($_POST['existing_image'] ?? ''));
$allowedStatuses = ['On Tap', 'Coming Soon', 'Off Tap'];

if ($name === '') {
    http_response_code(400);
    exit('Ale name is required.');
}
if (!in_array($status, $allowedStatuses, true)) $status = 'On Tap';

$imagePath = $existingImage;

if (isset($_FILES['artwork']) && $_FILES['artwork']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['artwork']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        exit('Artwork upload failed.');
    }
    if ((int)$_FILES['artwork']['size'] > 2 * 1024 * 1024) {
        http_response_code(400);
        exit('Artwork must be 2 MB or smaller.');
    }

    $tmp = (string)$_FILES['artwork']['tmp_name'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg'
    ];
    if (!isset($extensions[$mime])) {
        http_response_code(400);
        exit('Unsupported artwork format.');
    }

    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
    if ($slug === '') $slug = 'ale';
    $filename = $slug . '-' . date('YmdHis') . '.' . $extensions[$mime];
    $target = $assetsDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $target)) {
        http_response_code(500);
        exit('Unable to save artwork.');
    }
    $imagePath = 'assets/' . $filename;
}

$beer = [
    'name' => $name,
    'description' => $description,
    'status' => $status,
    'image' => $imagePath,
    'alt' => $name . ' artwork'
];

if ($id !== null && isset($beers[$id])) {
    $beers[$id] = $beer;
} else {
    $beers[] = $beer;
}

$json = json_encode(array_values($beers), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false || file_put_contents($dataFile, $json . PHP_EOL, LOCK_EX) === false) {
    http_response_code(500);
    exit('Unable to save ale data. Check write permissions for the data folder.');
}

header('Location: index.php');
exit;
