<?php
session_start();
if (empty($_SESSION['brewery_admin'])) {
    header('Location: index.php');
    exit;
}

$dataFile = __DIR__ . '/../live-data/data/beers.json';
$beers = json_decode((string)@file_get_contents($dataFile), true);
if (!is_array($beers)) $beers = [];

$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : null;
$beer = [
    'name' => '',
    'description' => '',
    'status' => 'On Tap',
    'image' => '',
    'alt' => ''
];

if ($id !== null && isset($beers[$id]) && is_array($beers[$id])) {
    $beer = array_merge($beer, $beers[$id]);
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?><!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $id === null ? 'Add Ale' : 'Edit Ale' ?> - Brewery Admin</title>
<style>
body{font-family:Arial,sans-serif;background:#c82020;margin:0;color:#211b15}.wrap{max-width:620px;margin:auto;padding:18px}.panel{background:#fff2c7;border-radius:20px;padding:20px}.field{margin:0 0 16px}.field label{display:block;font-weight:700;margin-bottom:6px}.field input,.field textarea,.field select{width:100%;box-sizing:border-box;padding:12px;border:1px solid #d7b85e;border-radius:9px;background:#fffdf5;font:inherit}.field textarea{min-height:100px;resize:vertical}.button{display:inline-block;border:0;border-radius:9px;padding:11px 15px;background:#c82020;color:#fff;font-weight:700;text-decoration:none;cursor:pointer}.secondary{background:#5c5144}.preview{margin:10px 0}.preview img{width:92px;height:106px;object-fit:cover;border-radius:9px;border:1px solid #d7b85e;background:#fff}.note{font-size:.85rem;color:#65594c;line-height:1.4}.actions{display:flex;gap:8px;flex-wrap:wrap}
</style>
</head>
<body><main class="wrap"><section class="panel">
<h1><?= $id === null ? 'Add Ale' : 'Edit Ale' ?></h1>
<form action="save_beer.php" method="post" enctype="multipart/form-data">
<?php if ($id !== null): ?><input type="hidden" name="id" value="<?=$id?>"><?php endif; ?>
<input type="hidden" name="existing_image" value="<?=e((string)$beer['image'])?>">
<div class="field"><label for="name">Ale name</label><input id="name" name="name" required maxlength="80" value="<?=e((string)$beer['name'])?>"></div>
<div class="field"><label for="description">Description</label><textarea id="description" name="description" maxlength="240"><?=e((string)$beer['description'])?></textarea></div>
<div class="field"><label for="status">Status</label><select id="status" name="status"><option value="On Tap" <?=($beer['status'] ?? '') === 'On Tap' ? 'selected' : ''?>>On Tap</option><option value="Coming Soon" <?=($beer['status'] ?? '') === 'Coming Soon' ? 'selected' : ''?>>Coming Soon</option><option value="Off Tap" <?=($beer['status'] ?? '') === 'Off Tap' ? 'selected' : ''?>>Off Tap</option></select></div>
<?php if (!empty($beer['image'])): ?><div class="preview"><p class="note">Current artwork</p><img src="../<?=e((string)$beer['image'])?>" alt=""></div><?php endif; ?>
<div class="field"><label for="artwork">Artwork <?= $id === null ? '' : '(leave blank to keep current image)' ?></label><input id="artwork" type="file" name="artwork" accept="image/jpeg,image/png,image/webp,image/svg+xml"><p class="note">JPG, PNG, WebP or SVG. Maximum 2 MB.</p></div>
<div class="actions"><button class="button" type="submit">Save Ale</button><a class="button secondary" href="index.php">Cancel</a></div>
</form>
</section></main></body></html>
