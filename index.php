<?php
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>Test PHP — autotempo.net</title>
<style>body{font-family:system-ui,Arial,sans-serif;max-width:640px;margin:40px auto;padding:0 16px;line-height:1.5}
h1{color:#1a9c5b}code{background:#eef;padding:1px 5px;border-radius:4px}
li.ok{color:#1a7d49}li.no{color:#c02b2b;font-weight:bold}</style></head>
<body>
<h1>✅ Test PHP OK</h1>
<p>Si tu vois cette page, <strong>PHP fonctionne</strong> sur le serveur.</p>
<ul>
<li>Version PHP : <code><?php echo phpversion(); ?></code></li>
<li>Date serveur : <code><?php echo date('Y-m-d H:i:s'); ?></code></li>
<li>Serveur : <code><?php echo isset($_SERVER['SERVER_SOFTWARE']) ? htmlspecialchars($_SERVER['SERVER_SOFTWARE']) : '?'; ?></code></li>
</ul>

<h2>Extensions nécessaires à l'application</h2>
<ul>
<?php
foreach (array('pdo', 'pdo_mysql', 'mysqli', 'mbstring', 'openssl', 'session') as $ext) {
    $ok = extension_loaded($ext);
    echo '<li class="' . ($ok ? 'ok' : 'no') . '">' . $ext . ' : ' . ($ok ? 'OK' : 'MANQUANT') . "</li>\n";
}
?>
</ul>
<p style="color:#888;font-size:13px">Page de test temporaire — sera remplacée par l'application MCJ-Courtage une fois le serveur validé.</p>
</body>
</html>
