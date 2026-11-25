<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/statistics.php';

brewery_admin_start_session();

if (isset($_GET['logout'])) {
    brewery_admin_log_out();
    header('Location: index.php');
    exit;
}

$externalUsername = brewery_admin_external_username();
$externallyAuthenticated = $externalUsername !== '';
$adminConfigured = $externallyAuthenticated || brewery_admin_password_is_configured();
$error = $adminConfigured ? '' : 'Admin login has not been configured on this server.';

if (!$externallyAuthenticated && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['password'])) {
    if (!$adminConfigured) {
        $error = 'Admin login has not been configured on this server.';
    } elseif (brewery_admin_verify_password((string)$_POST['password'])) {
        session_regenerate_id(true);
        $_SESSION['brewery_admin'] = true;
        $_SESSION['brewery_admin_password_fingerprint'] = hash('sha256', brewery_admin_password_hash());
        header('Location: index.php');
        exit;
    } else {
        $error = 'Incorrect password.';
    }
}

if (!brewery_admin_is_authenticated()) {
?><!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Brewery Admin</title>
    <style>
        body{font-family:Arial,sans-serif;background:#c82020;margin:0;padding:24px;color:#211b15}
        .box{max-width:420px;margin:10vh auto;background:#fff2c7;padding:24px;border-radius:20px}
        h1{margin-top:0}
        input,button{width:100%;padding:12px;margin-top:10px;box-sizing:border-box;border-radius:9px;border:1px solid #c9a34a}
        button{background:#c82020;color:#fff;font-weight:700;border:0;cursor:pointer}
        .error{color:#a00}
    </style>
</head>
<body>
<div class="box">
    <h1>Brewery Admin</h1>
    <p>Sign in to manage ales, events and app statistics.</p>
    <?php if ($error): ?><p class="error"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></p><?php endif; ?>
    <form method="post">
        <label>Password<input type="password" name="password" required autofocus autocomplete="current-password"></label>
        <button type="submit">Sign in</button>
    </form>
</div>
</body>
</html><?php
    exit;
}

$beers = json_decode((string)@file_get_contents(__DIR__ . '/../live-data/data/beers.json'), true);
if (!is_array($beers)) {
    $beers = [];
}

$events = json_decode((string)@file_get_contents(__DIR__ . '/../live-data/data/events.json'), true);
if (!is_array($events)) {
    $events = [];
}

$statsError = null;
$stats = brewery_stats_read($statsError);
$statLabels = [
    'welcome_view' => 'Welcome views',
    'guide_view' => 'Guide views',
    'install_click' => 'Install button taps',
    'android_install' => 'Confirmed Android installs',
    'ios_instructions' => 'iOS instruction views'
];

$lastUpdated = 'No activity recorded yet';
if (!empty($stats['last_updated'])) {
    try {
        $updated = new DateTimeImmutable((string)$stats['last_updated']);
        $lastUpdated = $updated
            ->setTimezone(new DateTimeZone('Europe/London'))
            ->format('j M Y, H:i T');
    } catch (Exception $exception) {
        $statsError = $statsError ?: 'The last statistics update time could not be read.';
    }
}
?><!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Brewery Admin</title>
    <style>
        body{font-family:Arial,sans-serif;background:#c82020;margin:0;color:#211b15}
        .wrap{max-width:760px;margin:auto;padding:18px}
        .panel{background:#fff2c7;border-radius:20px;padding:20px;margin-bottom:16px}
        h1,h2{margin-top:0}
        .row{background:#fff9e7;border:1px solid #e2c982;border-radius:12px;padding:14px;margin:10px 0}
        .row img{width:60px;height:78px;object-fit:cover;float:right;border-radius:8px}
        .actions a,.button{display:inline-block;background:#c82020;color:#fff;text-decoration:none;padding:9px 12px;border-radius:8px;font-weight:700;margin:4px 4px 0 0}
        .top{display:flex;justify-content:space-between;align-items:center;gap:12px}
        .muted{color:#65594c;font-size:.9rem}
        .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(125px,1fr));gap:10px;margin-top:14px}
        .stat{background:#fff9e7;border:1px solid #e2c982;border-radius:12px;padding:14px}
        .stat strong{display:block;color:#c82020;font-size:1.8rem;line-height:1.1}
        .stat span{display:block;margin-top:5px;font-size:.9rem}
        .warning{background:#fff0e8;border:1px solid #d87b58;border-radius:10px;padding:10px;color:#7d260d}
        @media(max-width:520px){.top{align-items:flex-start;flex-wrap:wrap}}
    </style>
</head>
<body>
<main class="wrap">
    <section class="panel">
        <div class="top">
            <div><h1>Clevedon Brewery Admin</h1><p class="muted">Simple management for the public PWA.</p></div>
            <?php if ($externallyAuthenticated): ?>
                <span class="muted">Protected by Plesk · <?=htmlspecialchars($externalUsername, ENT_QUOTES, 'UTF-8')?></span>
            <?php else: ?>
                <a class="button" href="?logout=1">Log out</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <h2>App statistics</h2>
        <p class="muted">The counter stores anonymous totals only — it does not add names, IP addresses or customer details to the statistics file.</p>
        <?php if ($statsError): ?>
            <p class="warning"><?=htmlspecialchars($statsError, ENT_QUOTES, 'UTF-8')?> Tracking is paused until the storage problem is resolved.</p>
        <?php endif; ?>
        <div class="stats">
            <?php foreach ($statLabels as $key => $label): ?>
                <div class="stat">
                    <strong><?=number_format((int)($stats[$key] ?? 0))?></strong>
                    <span><?=htmlspecialchars($label, ENT_QUOTES, 'UTF-8')?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="muted">Last activity: <?=htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8')?></p>
    </section>

    <section class="panel">
        <div class="top"><h2>Ales</h2><a class="button" href="beer.php">+ Add ale</a></div>
        <?php foreach ($beers as $i => $beer): ?>
            <div class="row">
                <?php if (!empty($beer['image'])): ?><img src="../<?=htmlspecialchars((string)$beer['image'], ENT_QUOTES, 'UTF-8')?>" alt=""><?php endif; ?>
                <strong><?=htmlspecialchars((string)($beer['name'] ?? ''), ENT_QUOTES, 'UTF-8')?></strong><br>
                <span class="muted"><?=htmlspecialchars((string)($beer['status'] ?? ''), ENT_QUOTES, 'UTF-8')?> · <?=htmlspecialchars((string)($beer['description'] ?? ''), ENT_QUOTES, 'UTF-8')?></span>
                <div class="actions"><a href="beer.php?id=<?=(int)$i?>">Edit</a></div>
                <div style="clear:both"></div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="panel">
        <div class="top"><h2>Events</h2><a class="button" href="event.php">+ Add event</a></div>
        <?php foreach ($events as $i => $event): ?>
            <div class="row">
                <?php if (!empty($event['image'])): ?><img src="../<?=htmlspecialchars((string)$event['image'], ENT_QUOTES, 'UTF-8')?>" alt=""><?php endif; ?>
                <strong><?=htmlspecialchars((string)($event['title'] ?? ''), ENT_QUOTES, 'UTF-8')?></strong><br>
                <span class="muted"><?=htmlspecialchars((string)($event['status'] ?? ''), ENT_QUOTES, 'UTF-8')?> · <?=htmlspecialchars((string)($event['description'] ?? ''), ENT_QUOTES, 'UTF-8')?></span>
                <div class="actions"><a href="event.php?id=<?=(int)$i?>">Edit</a></div>
                <div style="clear:both"></div>
            </div>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>
