<?php
// Page shell for member pages: SB Admin 2 styles, top bar, flash messages.

function layout_start($title, $member = null)
{
    $flashes = take_flashes();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= e($title) ?> · Farm Survey Data</title>
    <?= csrf_meta() ?>
    <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:400,600,700,800" rel="stylesheet">
    <link href="../css/sb-admin-2.min.css" rel="stylesheet">
    <link href="assets/members.css" rel="stylesheet">
</head>
<body class="members-body">
<nav class="navbar navbar-light bg-white shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand members-brand" href="index.php">
            <i class="fas fa-seedling text-success mr-1"></i> Farm Survey <span class="text-gray-500 font-weight-normal">Data</span>
        </a>
        <?php if ($member) { ?>
            <div class="d-flex align-items-center">
                <span class="small text-gray-700 mr-3 d-none d-sm-inline">
                    <?= e($member['first_name'] . ' ' . $member['last_name']) ?>
                    · <?= $member['role'] === 'buyer' ? 'Buyer' : 'Collector' ?>
                </span>
                <form method="post" action="logout.php" class="m-0">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-secondary">Log out</button>
                </form>
            </div>
        <?php } ?>
    </div>
</nav>
<main class="container pb-5">
    <?php foreach ($flashes as $f) { ?>
        <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : ($f['type'] === 'dev' ? 'warning' : 'success') ?> members-alert" role="alert">
            <?php if ($f['type'] === 'dev') { ?><strong>Development mode:</strong> <?php } ?>
            <?= $f['type'] === 'dev' ? $f['message'] : e($f['message']) ?>
        </div>
    <?php } ?>
<?php
}

function layout_end($scripts = [])
{
    ?>
</main>
<footer class="text-center small text-gray-500 pb-4">
    Your personal details are used only to verify your identity and are never published.
    <a href="privacy.php">Privacy notice</a>
</footer>
<script src="../vendor/jquery/jquery.min.js"></script>
<script src="../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<?php foreach ($scripts as $s) { ?>
    <script src="<?= e($s) ?>"></script>
<?php } ?>
</body>
</html>
<?php
}

/** Bootstrap invalid-feedback helpers. */
function field_error(array $errors, $name)
{
    return isset($errors[$name]) ? '<div class="invalid-feedback d-block">' . e($errors[$name]) . '</div>' : '';
}

function invalid(array $errors, $name)
{
    return isset($errors[$name]) ? ' is-invalid' : '';
}

/** Step tracker shown on the dashboard and verification pages. */
function progress_steps(array $member, $active = null)
{
    $p = member_progress($member);
    $steps = [
        'account' => ['Account', 'fa-user'],
        'phone' => ['Phone', 'fa-mobile-alt'],
        'email' => ['Email', 'fa-envelope'],
        'identity' => ['ID check', 'fa-id-card'],
    ];
    echo '<ol class="members-steps">';
    foreach ($steps as $key => [$label, $icon]) {
        $state = $p[$key] ? 'done' : ($key === $active ? 'active' : '');
        if ($key === 'identity' && $member['id_status'] === 'manual_review') {
            $state = 'review';
        }
        echo '<li class="' . $state . '"><span class="dot"><i class="fas ' . ($p[$key] ? 'fa-check' : $icon) . '"></i></span>' . e($label) . '</li>';
    }
    echo '</ol>';
}
