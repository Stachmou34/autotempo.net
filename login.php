<?php
require __DIR__ . '/src/bootstrap.php';

if (Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfValidate($_POST['csrf'] ?? null)) {
        $error = 'Session expirée, veuillez réessayer.';
    } else {
        $u = trim((string) ($_POST['username'] ?? ''));
        $p = (string) ($_POST['password'] ?? '');
        if (Auth::check($CONFIG, $u, $p)) {
            Auth::login($u);
            header('Location: index.php');
            exit;
        }
        $error = 'Identifiants incorrects.';
    }
}

$pageTitle = 'Connexion — MCJ-Courtage';
require __DIR__ . '/partials/header.php';
?>
<div class="login-card">
    <h1>Connexion</h1>
    <?php if ($error): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
        <label>Identifiant
            <input type="text" name="username" required autofocus>
        </label>
        <label>Mot de passe
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn btn-primary">Se connecter</button>
    </form>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
