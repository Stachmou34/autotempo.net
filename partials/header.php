<?php
/** @var array $CONFIG */
if (!isset($pageTitle)) {
    $pageTitle = isset($CONFIG['app']['name']) ? $CONFIG['app']['name'] : 'MCJ-Courtage';
}
$apporteur = isset($CONFIG['app']['apporteur']) ? $CONFIG['app']['apporteur'] : '';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($pageTitle); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="brand">
        <span class="logo">MCJ</span>
        <div>
            <strong>MCJ-Courtage</strong>
            <small>Contrats apporteur <em><?php echo e($apporteur); ?></em></small>
        </div>
    </div>
    <?php if (Auth::isLoggedIn()): ?>
    <nav class="mainnav">
        <a href="index.php">Contrats</a>
        <a href="export.php?format=csv">Export CSV</a>
        <span class="user">👤 <?php echo e(Auth::user()); ?></span>
        <a class="btn-logout" href="logout.php">Déconnexion</a>
    </nav>
    <?php endif; ?>
</header>
<main class="container">
