<?php
session_start();
if (empty($_SESSION['brewery_admin'])) { header('Location: index.php'); exit; }

$file = __DIR__ . '/../live-data/data/events.json';
$events = json_decode((string)@file_get_contents($file), true) ?: [];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$event = ($id !== null && isset($events[$id])) ? $events[$id] : [
    'title' => '',
    'date' => '',
    'time' => '',
    'description' => '',
    'status' => 'Coming Up',
    'image' => ''
];
?>
<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $id === null ? 'Add Event' : 'Edit Event' ?></title>
<style>
body{font-family:Arial,sans-serif;background:#c82020;margin:0;color:#211b15}.wrap{max-width:620px;margin:auto;padding:18px}.panel{background:#fff2c7;border-radius:20px;padding:20px}.field{margin:14px 0}label{display:block;font-weight:700;margin-bottom:6px}input,textarea,select{width:100%;box-sizing:border-box;padding:12px;border:1px solid #c9a34a;border-radius:9px;background:#fff9e7;font:inherit}textarea{min-height:110px;resize:vertical}.button{display:inline-block;background:#c82020;color:#fff;border:0;text-decoration:none;padding:11px 15px;border-radius:9px;font-weight:700;cursor:pointer}.cancel{background:#6d6257}.current{margin-top:8px;color:#65594c;font-size:.9rem}.current img{display:block;width:110px;max-height:140px;object-fit:contain;border-radius:8px;margin-top:8px;background:#fff}
</style>
</head>
<body>
<main class="wrap"><section class="panel">
<h1><?= $id === null ? 'Add Event' : 'Edit Event' ?></h1>
<form action="save_event.php" method="post" enctype="multipart/form-data">
<?php if ($id !== null): ?><input type="hidden" name="id" value="<?=$id?>"><?php endif; ?>
<div class="field"><label>Event title</label><input type="text" name="title" required value="<?=htmlspecialchars($event['title'] ?? '')?>"></div>
<div class="field"><label>Date</label><input type="date" name="date" value="<?=htmlspecialchars($event['date'] ?? '')?>"></div>
<div class="field"><label>Time</label><input type="time" name="time" value="<?=htmlspecialchars($event['time'] ?? '')?>"></div>
<div class="field"><label>Description</label><textarea name="description"><?=htmlspecialchars($event['description'] ?? '')?></textarea></div>
<div class="field"><label>Status</label><select name="status"><option value="Coming Up" <?=($event['status'] ?? '')==='Coming Up'?'selected':''?>>Coming Up</option><option value="Hidden" <?=($event['status'] ?? '')==='Hidden'?'selected':''?>>Hidden</option></select></div>
<div class="field"><label>Optional event image</label><input type="file" name="artwork" accept="image/jpeg,image/png,image/webp,image/svg+xml">
<?php if (!empty($event['image'])): ?><div class="current">Current image:<img src="../<?=htmlspecialchars($event['image'])?>" alt=""></div><?php endif; ?></div>
<button class="button" type="submit">Save Event</button> <a class="button cancel" href="index.php">Cancel</a>
</form>
</section></main>
</body>
</html>
