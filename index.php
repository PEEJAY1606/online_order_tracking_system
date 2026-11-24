<?php
require 'db.php';
require 'functions.php';

$order = null;
$history = [];
$error = '';
$number = '';

// QR codes open the public tracking page with the order number pre-filled.
if (isset($_GET['order']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $number = trim((string)$_GET['order']);
}

function tracking_date($value, $format = 'M j, Y · g:i A') {
    if (!$value) return '';
    $time = strtotime((string)$value);
    return $time ? date($format, $time) : (string)$value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $number !== '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $number = trim($_POST['order_number'] ?? '');
    }

    if ($number === '') {
        $error = 'Please enter your order number to continue.';
    } elseif (strlen($number) > 30) {
        $error = 'That order number is too long. Please check it and try again.';
    } else {
        $stmt = $pdo->prepare('SELECT id, order_number, product_name, quantity, order_date, status, tracking_note, updated_at FROM orders WHERE order_number = ? LIMIT 1');
        $stmt->execute([$number]);
        $order = $stmt->fetch();

        if (!$order) {
            $error = 'We couldn’t find an order with that number. Please check the number and try again.';
        } else {
            try {
                $h = $pdo->prepare('SELECT status, note, changed_at FROM order_status_history WHERE order_id = ? ORDER BY changed_at ASC, id ASC');
                $h->execute([$order['id']]);
                $history = $h->fetchAll();
            } catch (Throwable $ignored) {
                // The tracking page remains usable if the optional history migration has not been applied.
                $history = [];
            }
        }
    }
}

$steps = ['Pending', 'Processing', 'Shipped', 'Out for Delivery', 'Delivered'];
$currentStep = $order ? array_search($order['status'], $steps, true) : false;
$progress = ($currentStep === false) ? 0 : (int) round(($currentStep / (count($steps) - 1)) * 100);

// Find the most recent recorded timestamp for each milestone.
$milestoneDates = [];
foreach ($history as $entry) {
    $milestoneDates[$entry['status']] = $entry['changed_at'];
}
if ($order && !isset($milestoneDates[$order['status']])) {
    $milestoneDates[$order['status']] = $order['updated_at'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10264d">
    <title>Track Your Order | OrderTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <style>
        :root{--track-blue:#176cf2;--track-navy:#10264d;--track-muted:#71809b;--track-line:#e7edf6}
        body{background:#f4f7fc}
        .public-nav{background:linear-gradient(105deg,#0c1b38 0%,#123e7c 68%,#167de2 100%);padding:16px 0;box-shadow:0 5px 22px #10264d18}
        .public-brand{display:inline-flex;align-items:center;gap:10px;color:#fff;font-weight:800;font-size:21px;letter-spacing:-.5px}
        .public-brand-mark{width:39px;height:39px;display:grid;place-items:center;border-radius:12px;background:#ffffff1c;font-size:21px}
        .tracking-wrap{max-width:940px;padding-top:52px;padding-bottom:35px}
        .tracking-heading{max-width:620px;margin:0 auto 28px;text-align:center}
        .tracking-heading .eyebrow{display:inline-flex;align-items:center;gap:7px;padding:7px 12px;border-radius:30px;background:#e8f1ff;color:#155dc9;font-size:11px;font-weight:800;letter-spacing:.8px;text-transform:uppercase;margin-bottom:14px}
        .tracking-heading h1{font-size:clamp(29px,4vw,40px);font-weight:850;letter-spacing:-1.2px;color:#13284d;margin:0 0 10px}
        .tracking-heading p{color:#73819a;font-size:15px;margin:0;line-height:1.65}
        .track-card{background:#fff;border:1px solid #e5ebf4;border-radius:20px;box-shadow:0 12px 34px #183b790d}
        .search-card{padding:25px}
        .search-label{display:block;font-size:12px;font-weight:800;color:#334563;margin-bottom:8px}
        .search-input{height:53px;border-radius:12px;padding:12px 15px;font-size:15px;font-weight:600;letter-spacing:.2px}
        .search-button{height:53px;border-radius:12px;font-weight:750;white-space:nowrap}
        .search-hint{font-size:11px;color:#8995a9;margin-top:10px}
        .result-card{margin-top:22px;overflow:hidden}
        .result-top{padding:25px 27px;background:linear-gradient(115deg,#10264d,#174b91);color:white;position:relative;overflow:hidden}
        .result-top:after{content:"";position:absolute;width:240px;height:240px;border:34px solid #ffffff0d;border-radius:50%;right:-55px;top:-155px}
        .result-top-inner{position:relative;z-index:1}
        .result-kicker{font-size:10px;font-weight:800;letter-spacing:1.2px;color:#a9c9f8;text-transform:uppercase}
        .result-number{font-size:clamp(21px,3vw,27px);font-weight:850;letter-spacing:-.5px;margin-top:4px;overflow-wrap:anywhere}
        .result-status{display:inline-flex;align-items:center;gap:7px;padding:8px 13px;border-radius:30px;background:#ffffff18;color:#fff;border:1px solid #ffffff30;font-size:12px;font-weight:750}
        .result-status:before{content:"";width:7px;height:7px;border-radius:50%;background:#79d9a6;box-shadow:0 0 0 4px #79d9a622}
        .result-body{padding:26px 27px}
        .section-title{font-size:16px;font-weight:850;color:#172b4d;letter-spacing:-.2px;margin:0}
        .section-subtitle{font-size:12px;color:#8793a8;margin-top:4px}
        .info-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:26px}
        .info-tile{background:#f7f9fd;border:1px solid #edf1f7;border-radius:13px;padding:15px;min-width:0}
        .info-tile .info-label{font-size:10px;color:#8491a7;font-weight:800;text-transform:uppercase;letter-spacing:.65px;margin-bottom:6px}
        .info-tile .info-value{font-size:13px;color:#263a5c;font-weight:750;overflow-wrap:anywhere}
        .progress-shell{height:8px;background:#eaf0f8;border-radius:20px;overflow:hidden;margin:17px 0 24px}
        .progress-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,#176cf2,#39a4ff);transition:width .7s ease}
        .milestone-list{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:5px;position:relative}
        .milestone{position:relative;text-align:center;padding:0 3px}
        .milestone-icon{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;margin:0 auto 9px;background:#edf1f7;color:#91a0b7;border:2px solid #edf1f7;font-size:14px;font-weight:900;position:relative;z-index:1}
        .milestone.done .milestone-icon{background:#e5f7ed;border-color:#d4f0df;color:#13834d}
        .milestone.current .milestone-icon{background:#e8f1ff;border:2px solid #176cf2;color:#176cf2;box-shadow:0 0 0 5px #176cf214}
        .milestone-name{font-size:11px;font-weight:800;color:#73819a;line-height:1.35}
        .milestone.done .milestone-name,.milestone.current .milestone-name{color:#20385e}
        .milestone-date{font-size:10px;color:#94a0b2;line-height:1.4;margin-top:5px;min-height:14px}
        .current-banner{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border-radius:13px;background:#edf5ff;border:1px solid #dceaff;margin-top:23px}
        .current-banner-icon{width:34px;height:34px;flex:none;border-radius:10px;background:#dceaff;color:#176cf2;display:grid;place-items:center;font-weight:900}
        .current-banner strong{display:block;font-size:12px;color:#174b91;margin-bottom:3px}
        .current-banner span{font-size:12px;color:#647894;line-height:1.5}
        .history-list{display:grid;gap:9px;margin-top:14px}
        .history-item{display:flex;gap:12px;align-items:flex-start;padding:13px 14px;background:#f7f9fd;border:1px solid #edf1f7;border-radius:12px}
        .history-dot{width:9px;height:9px;border-radius:50%;background:#2582f5;box-shadow:0 0 0 4px #e4efff;margin:5px 3px 0;flex:none}
        .history-content{min-width:0;flex:1}
        .history-status{font-size:12px;font-weight:800;color:#2b3e5d}
        .history-note{font-size:12px;color:#78869c;margin-top:4px;overflow-wrap:anywhere}
        .history-time{font-size:10px;color:#8c98aa;white-space:nowrap}
        .cancelled-panel{display:flex;gap:13px;align-items:flex-start;padding:17px;border:1px solid #f5d6da;background:#fff3f4;border-radius:14px;color:#a83242}
        .cancelled-symbol{width:35px;height:35px;border-radius:11px;background:#ffe1e5;display:grid;place-items:center;font-weight:900;flex:none}
        .cancelled-panel strong{font-size:13px}.cancelled-panel p{font-size:12px;color:#98515b;margin:4px 0 0}
        .not-found{border:1px solid #f3dfb2;background:#fff9eb;color:#8d6413;border-radius:13px;padding:14px 16px;font-size:13px}
        .footer-note{font-size:11px;color:#96a1b2}
        .track-again{font-size:12px;font-weight:750;color:#176cf2;text-decoration:none}
        .track-again:hover{color:#0b50bd;text-decoration:underline}
        @media(max-width:650px){.tracking-wrap{padding-top:32px}.search-card{padding:19px}.result-top{padding:21px}.result-body{padding:20px}.info-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.milestone-list{gap:0}.milestone-icon{width:29px;height:29px;font-size:12px}.milestone-name{font-size:9px}.milestone-date{font-size:9px}.history-time{white-space:normal;text-align:right;max-width:105px}}
        @media(max-width:390px){.milestone-name{font-size:8px}.milestone-icon{width:26px;height:26px}.info-tile{padding:12px}.result-status{font-size:10px;padding:7px 9px}}
        @media(prefers-reduced-motion:reduce){.progress-fill{transition:none}}
    </style>
</head>
<body>
<nav class="public-nav">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="public-brand" href="index.php"><span class="public-brand-mark">🚚</span><span>Order<span style="color:#72c2ff">Track</span></span></a>
        <a class="btn btn-outline-light btn-sm" style="border-radius:9px;font-weight:650" href="login.php">Admin Login</a>
    </div>
</nav>

<main class="container tracking-wrap">
    <header class="tracking-heading">
        <div class="eyebrow"><span>✦</span> Order updates, made simple</div>
        <h1>Track Your Order</h1>
        <p>Stay up to date with your delivery. Enter the order number provided by the store to see its latest status and progress.</p>
    </header>

    <section class="track-card search-card" aria-label="Find an order">
        <form method="post" action="index.php" class="row g-3 align-items-end">
            <div class="col-md-9">
                <label class="search-label" for="order_number">Your Order Number</label>
                <input id="order_number" class="form-control search-input" type="text" name="order_number" value="<?= e($number) ?>" placeholder="e.g. ORD-2026-1001" maxlength="30" autocomplete="off" required>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary search-button w-100" type="submit">⌕ &nbsp; Track Order</button>
            </div>
        </form>
        <div class="search-hint">Scan your order QR code or enter the order number provided by the store.</div>
        <?php if ($error): ?>
            <div class="not-found mt-3" role="alert">⚠ &nbsp; <?= e($error) ?></div>
        <?php endif; ?>
    </section>

    <?php if ($order): ?>
        <section class="track-card result-card" aria-label="Order tracking result">
            <div class="result-top">
                <div class="result-top-inner d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="result-kicker">Tracking details</div>
                        <div class="result-number"><?= e($order['order_number']) ?></div>
                    </div>
                    <span class="result-status"><?= e($order['status']) ?></span>
                </div>
            </div>

            <div class="result-body">
                <div class="info-grid">
                    <div class="info-tile"><div class="info-label">Product</div><div class="info-value"><?= e($order['product_name']) ?></div></div>
                    <div class="info-tile"><div class="info-label">Quantity</div><div class="info-value"><?= (int)$order['quantity'] ?></div></div>
                    <div class="info-tile"><div class="info-label">Order placed</div><div class="info-value"><?= e(tracking_date($order['order_date'], 'M j, Y')) ?></div></div>
                </div>

                <?php if ($order['status'] === 'Cancelled'): ?>
                    <div class="cancelled-panel" role="status">
                        <div class="cancelled-symbol">!</div>
                        <div><strong>This order has been cancelled</strong><p>The delivery process has stopped. Please contact the store if you need more information.</p></div>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2">
                        <div><h2 class="section-title">Delivery progress</h2><div class="section-subtitle">Follow your order through each delivery stage.</div></div>
                        <div class="small fw-bold" style="color:#176cf2"><?= $progress ?>% complete</div>
                    </div>
                    <div class="progress-shell" role="progressbar" aria-label="Delivery progress" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-fill" style="width:<?= $progress ?>%"></div></div>

                    <div class="milestone-list">
                        <?php foreach ($steps as $i => $step):
                            $done = ($currentStep !== false && $i <= $currentStep);
                            $isCurrent = ($currentStep === $i);
                            $date = $milestoneDates[$step] ?? '';
                        ?>
                            <div class="milestone <?= $done ? 'done' : '' ?> <?= $isCurrent ? 'current' : '' ?>">
                                <div class="milestone-icon"><?= $done ? '✓' : ($i + 1) ?></div>
                                <div class="milestone-name"><?= e($step) ?></div>
                                <div class="milestone-date"><?= $date ? e(tracking_date($date, 'M j')) : ($done ? 'Completed' : 'Awaiting') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="current-banner">
                        <div class="current-banner-icon">↗</div>
                        <div><strong>Current status: <?= e($order['status']) ?></strong><span><?= $order['tracking_note'] ? e($order['tracking_note']) : 'Your order status will be updated here as it moves through the delivery process.' ?></span></div>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4 mb-2">
                    <div><h2 class="section-title">Tracking history</h2><div class="section-subtitle">Updates recorded by the store.</div></div>
                    <span class="small text-muted">Last updated: <?= e(tracking_date($order['updated_at'])) ?></span>
                </div>
                <?php if ($history): ?>
                    <div class="history-list">
                        <?php foreach (array_reverse($history) as $entry): ?>
                            <div class="history-item">
                                <span class="history-dot"></span>
                                <div class="history-content">
                                    <div class="history-status"><?= e($entry['status']) ?></div>
                                    <?php if (!empty($entry['note'])): ?><div class="history-note"><?= e($entry['note']) ?></div><?php endif; ?>
                                </div>
                                <time class="history-time" datetime="<?= e(date('c', strtotime($entry['changed_at']))) ?>"><?= e(tracking_date($entry['changed_at'])) ?></time>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="history-item mt-3"><span class="history-dot"></span><div class="history-content"><div class="history-status">Current status: <?= e($order['status']) ?></div><div class="history-note">Detailed history is not available yet. The latest order status is shown above.</div></div><time class="history-time"><?= e(tracking_date($order['updated_at'])) ?></time></div>
                <?php endif; ?>

                <div class="text-end mt-4"><button type="button" class="btn btn-soft btn-sm" onclick="document.getElementById('order_number').focus();window.scrollTo({top:0,behavior:'smooth'});">Track another order ↑</button></div>
            </div>
        </section>
    <?php endif; ?>

    <footer class="text-center mt-4"><div class="footer-note">© <?= date('Y') ?> OrderTrack · Order tracking made simple</div><div class="footer-note mt-1">For questions about your delivery, please contact the store.</div></footer>
</main>
</body>
</html>
