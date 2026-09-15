<?php
declare(strict_types=1);

$info = require __DIR__ . '/config/barangay_info.php';

function icon(string $name): string
{
    $icons = [
        'clipboard' => '<path d="M9 3h6a1 1 0 0 1 1 1v1h1a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h1V4a1 1 0 0 1 1-1Z"/><line x1="8" y1="11" x2="16" y2="11"/><line x1="8" y1="15" x2="16" y2="15"/><line x1="8" y1="19" x2="13" y2="19"/>',
        'syringe'   => '<line x1="18" y1="2" x2="22" y2="6"/><line x1="17" y1="7" x2="19" y2="5"/><path d="M3 21l4.5-1.5L18 9l-3-3L4.5 16.5 3 21Z"/><line x1="12" y1="9" x2="15" y2="12"/>',
        'baby'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/><path d="M9 8c0 1.7 1.3 3 3 3s3-1.3 3-3"/>',
        'elder'     => '<circle cx="12" cy="6" r="3"/><path d="M12 9v6l-3 3M12 15l3 3"/><path d="M9 12h6"/>',
        'heart'     => '<path d="M12 21s-7.5-4.6-10-9.3C.4 8.1 2.2 4.5 5.8 4c2-.3 3.8.7 6.2 3.3C14.4 4.7 16.2 3.7 18.2 4c3.6.5 5.4 4.1 3.8 7.7C19.5 16.4 12 21 12 21Z"/>',
        'tooth'     => '<path d="M8 3c-2.5 0-4 2-4 5 0 3 1 5 1.5 8 .3 1.7 1 3 2 3s1.5-2.5 1.7-4.5c.1-1.3.4-2 .8-2s.7.7.8 2C11 16.5 11.5 19 12.5 19s1.7-1.3 2-3c.5-3 1.5-5 1.5-8 0-3-1.5-5-4-5-1 0-1.6.3-2 .7C9.6 3.3 9 3 8 3Z"/>',
    ];
    $body = $icons[$name] ?? $icons['clipboard'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
}

function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($info['name']) ?> Health Center — Home</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,500;0,6..72,600;1,6..72,500&family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/landing.css">
</head>
<body>

<div id="top"></div>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="#top">
            <span class="brand-seal"><img src="<?= h($info['logo_image']) ?>" alt="<?= h($info['name']) ?> seal"></span>
            <span class="brand-text">
                <strong><?= h($info['name']) ?> Health Center</strong>
                <small>Resident Profiling &amp; Survey Management System</small>
            </span>
        </a>

        <button class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="primaryNav" aria-label="Toggle navigation">
            <span></span><span></span><span></span>
        </button>

        <nav class="primary-nav" id="primaryNav">
            <a href="#top">Home</a>
            <a href="#what-you-can-do">What You Can Do</a>
            <a href="#how-it-works">How It Works</a>
            <div class="nav-login">
                <a class="btn btn-outline" href="resident/login.php">Log In</a>
            </div>
        </nav>
    </div>
</header>

<main>

    <section class="hero" style="background-image:linear-gradient(160deg, rgba(10,20,50,.78) 0%, rgba(15,30,74,.62) 55%, rgba(10,20,50,.85) 100%), url('<?= h($info['hero_image']) ?>')">
        <div class="hero-inner">
            <div class="hero-copy">
                <h1><?= h($info['name']) ?><br>Health Center</h1>
                <p class="hero-subtitle"><?= h($info['hero_subtitle']) ?></p>
                <p class="hero-tagline"><?= h($info['tagline']) ?></p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="resident/login.php">Open Resident Portal</a>
                    <a class="btn btn-secondary" href="#what-you-can-do">Explore the portal</a>
                </div>
            </div>
        </div>
    </section>

    <section class="platform-overview" id="what-you-can-do">
        <div class="section-inner">
            <p class="eyebrow eyebrow-dark">One secure resident portal</p>
            <h2>More than a survey link</h2>
            <p class="section-lead">The Barangay Longos Health Center portal connects your household record with the community health work it supports.</p>
            <div class="platform-grid">
                <article class="platform-card platform-card-blue">
                    <div class="platform-icon"><?= icon('clipboard') ?></div>
                    <div><h3>Resident Profiling</h3><p>Review and update your household, personal, and family information so health workers have a clearer community profile.</p><a href="resident/login.php" class="text-link">Manage your profile <span aria-hidden="true">&rarr;</span></a></div>
                </article>
                <article class="platform-card platform-card-gold">
                    <div class="platform-icon"><?= icon('heart') ?></div>
                    <div><h3>Community Health Surveys</h3><p>Answer official surveys, submit your responses securely, and help the health center understand what residents need.</p><a href="resident/login.php" class="text-link">Answer a survey <span aria-hidden="true">&rarr;</span></a></div>
                </article>
            </div>
        </div>
    </section>

    <section class="how-it-works" id="how-it-works">
        <div class="section-inner">
            <p class="eyebrow eyebrow-dark">Resident portal flow</p>
            <h2>Keep your record useful and your voice counted</h2>
            <div class="steps-grid">
                <?php foreach ($info['how_it_works'] as $step): ?>
                <div class="step-card">
                    <div class="step-number"><?= h($step['step']) ?></div>
                    <h3><?= h($step['title']) ?></h3>
                    <p><?= h($step['desc']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

</main>

<footer class="site-footer">
    <div class="footer-inner">
        <div>
            <strong><?= h($info['name']) ?> Health Center</strong>
            <p><?= h($info['address']) ?></p>
        </div>
    </div>
    <p class="footer-copy">© <?= date('Y') ?> <?= h($info['name']) ?>. All rights reserved.</p>
</footer>

<script src="assets/js/landing.js"></script>
</body>
</html>