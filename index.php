<?php
// Étape 1 — Test PHP minimal.
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>Étape 1 — Test PHP</title>
<style>
body{font-family:system-ui,Arial,sans-serif;max-width:640px;margin:40px auto;padding:0 16px;line-height:1.6;color:#222}
h1{color:#1a9c5b} code{background:#eef;padding:2px 6px;border-radius:4px}
li.ok{color:#1a7d49} li.no{color:#c02b2b;font-weight:bold}
.box{background:#f6f8fc;border:1px solid #dde3ee;border-radius:8px;padding:14px 18px;margin-top:22px}
</style></head>
<body>

<h1>✅ Étape 1 — PHP fonctionne</h1>
<p>Si tu vois cette page, le serveur exécute bien PHP.</p>

<ul>
  <li>Version PHP : <code><?php echo phpversion(); ?></code></li>
  <li>Date serveur : <code><?php echo date('Y-m-d H:i:s'); ?></code></li>
  <li>Serveur : <code><?php echo isset($_SERVER['SERVER_SOFTWARE']) ? htmlspecialchars($_SERVER['SERVER_SOFTWARE']) : '?'; ?></code></li>
  <li>Dossier : <code><?php echo htmlspecialchars(__DIR__); ?></code></li>
</ul>

<h2>Extensions requises</h2>
<ul>
<?php
foreach (array('pdo', 'pdo_mysql', 'mbstring', 'openssl', 'session') as $ext) {
    $ok = extension_loaded($ext);
    echo '<li class="' . ($ok ? 'ok' : 'no') . '">' . $ext . ' : ' . ($ok ? 'OK' : 'MANQUANT') . "</li>\n";
}
?>
</ul>

<div class="box">
  <strong>Prochaine étape :</strong> ajouter la connexion à la base de données,
  puis l'authentification, puis la liste des contrats REYNARD — une étape à la fois.
</div>

</body>
</html>
