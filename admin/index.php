<?php
session_start();

// Temporary admin password for the first working version.
// We will move this out of the web root before production.
const ADMIN_PASSWORD = 'brewery-admin';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (hash_equals(ADMIN_PASSWORD, (string)$_POST['password'])) {
        $_SESSION['brewery_admin'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Incorrect password.';
}

if (empty($_SESSION['brewery_admin'])) {
?><!doctype html>
<html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Brewery Admin</title>
<style>body{font-family:Arial,sans-serif;background:#c82020;margin:0;padding:24px;color:#211b15}.box{max-width:420px;margin:10vh auto;background:#fff2c7;padding:24px;border-radius:20px}h1{margin-top:0}input,button{width:100%;padding:12px;margin-top:10px;box-sizing:border-box;border-radius:9px;border:1px solid #c9a34a}button{background:#c82020;color:white;font-weight:700;border:0}.error{color:#a00}</style></head><body><div class="box"><h1>Brewery Admin</h1><p>Sign in to manage ales and events.</p><?php if($error): ?><p class="error"><?=htmlspecialchars($error)?></p><?php endif; ?><form method="post"><label>Password<input type="password" name="password" required autofocus></label><button type="submit">Sign in</button></form></div></body></html><?php
    exit;
}

$beers = json_decode((string)@file_get_contents(__DIR__.'/../live-data/data/beers.json'), true) ?: [];
$events = json_decode((string)@file_get_contents(__DIR__.'/../live-data/data/events.json'), true) ?: [];
?><!doctype html>
<html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Brewery Admin</title>
<style>body{font-family:Arial,sans-serif;background:#c82020;margin:0;color:#211b15}.wrap{max-width:760px;margin:auto;padding:18px}.panel{background:#fff2c7;border-radius:20px;padding:20px;margin-bottom:16px}h1,h2{margin-top:0}.row{background:#fff9e7;border:1px solid #e2c982;border-radius:12px;padding:14px;margin:10px 0}.row img{width:60px;height:78px;object-fit:cover;float:right;border-radius:8px}.actions a,.button{display:inline-block;background:#c82020;color:#fff;text-decoration:none;padding:9px 12px;border-radius:8px;font-weight:700;margin:4px 4px 0 0}.top{display:flex;justify-content:space-between;align-items:center;gap:12px}.muted{color:#65594c;font-size:.9rem}</style></head><body><main class="wrap"><section class="panel"><div class="top"><div><h1>Clevedon Brewery Admin</h1><p class="muted">Simple management for the public PWA.</p></div><a class="button" href="?logout=1">Log out</a></div></section>
<section class="panel"><div class="top"><h2>Ales</h2><a class="button" href="beer.php">+ Add ale</a></div><?php foreach($beers as $i=>$beer): ?><div class="row"><?php if(!empty($beer['image'])): ?><img src="../<?=htmlspecialchars($beer['image'])?>" alt=""><?php endif; ?><strong><?=htmlspecialchars($beer['name'] ?? '')?></strong><br><span class="muted"><?=htmlspecialchars($beer['status'] ?? '')?> · <?=htmlspecialchars($beer['description'] ?? '')?></span><div class="actions"><a href="beer.php?id=<?=$i?>">Edit</a></div><div style="clear:both"></div></div><?php endforeach; ?></section>
<section class="panel"><div class="top"><h2>Events</h2><a class="button" href="event.php">+ Add event</a></div><?php foreach($events as $i=>$event): ?><div class="row"><?php if(!empty($event['image'])): ?><img src="../<?=htmlspecialchars($event['image'])?>" alt=""><?php endif; ?><strong><?=htmlspecialchars($event['title'] ?? '')?></strong><br><span class="muted"><?=htmlspecialchars($event['status'] ?? '')?> · <?=htmlspecialchars($event['description'] ?? '')?></span><div class="actions"><a href="event.php?id=<?=$i?>">Edit</a></div><div style="clear:both"></div></div><?php endforeach; ?></section>
</main></body></html>
