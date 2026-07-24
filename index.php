<?php
require __DIR__ . '/src/bootstrap.php';
Auth::require();

$repo = new ContractRepository($CONFIG);

$search       = trim((string) ($_GET['q'] ?? ''));
$statutFilter = (string) ($_GET['statut'] ?? '');

$error = '';
$rows  = [];
try {
    $rows  = $repo->list($search, $statutFilter);
    $stats = $repo->stats($rows);
} catch (Throwable $ex) {
    $error = $ex->getMessage();
    $stats = ['total' => 0, 'prime_totale' => 0, 'commission_totale' => 0, 'par_statut' => []];
}

$pageTitle = 'Contrats — MCJ-Courtage';
require __DIR__ . '/partials/header.php';
?>

<h1>Contrats de l'apporteur <em><?= e($CONFIG['app']['apporteur'] ?? '') ?></em></h1>

<?php if ($error): ?>
    <div class="alert error">
        <strong>Erreur de récupération des contrats.</strong><br>
        <?= e($error) ?><br>
        <small>Vérifiez le mapping des colonnes dans <code>config/config.php</code>.
        Utilisez <a href="tools/schema.php">l'outil de découverte du schéma</a> pour lister les tables et colonnes réelles de JLASSURE.</small>
    </div>
<?php else: ?>

<section class="stats">
    <div class="stat"><span class="num"><?= (int) $stats['total'] ?></span><span class="lbl">Contrats</span></div>
    <div class="stat"><span class="num"><?= e(euros($stats['prime_totale'])) ?></span><span class="lbl">Primes cumulées</span></div>
    <div class="stat"><span class="num"><?= e(euros($stats['commission_totale'])) ?></span><span class="lbl">Commissions</span></div>
    <div class="stat"><span class="num"><?= (int) ($stats['par_statut']['en_cours'] ?? 0) ?></span><span class="lbl">En cours</span></div>
    <div class="stat"><span class="num"><?= (int) ($stats['par_statut']['valide'] ?? 0) ?></span><span class="lbl">Validés</span></div>
</section>

<form class="filters" method="get">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Rechercher (client, référence, produit…)">
    <select name="statut">
        <option value="">Tous les statuts</option>
        <?php foreach (statutsDisponibles() as $s): ?>
            <option value="<?= e($s) ?>" <?= $statutFilter === $s ? 'selected' : '' ?>><?= e(statutLabel($s)) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn">Filtrer</button>
    <a class="btn btn-ghost" href="index.php">Réinitialiser</a>
    <a class="btn btn-ghost" href="export.php?format=csv&q=<?= urlencode($search) ?>&statut=<?= urlencode($statutFilter) ?>">⬇ Export CSV</a>
</form>

<div class="table-wrap">
<table class="grid">
    <thead>
        <tr>
            <th>Référence</th>
            <th>Client</th>
            <th>Produit</th>
            <th>Compagnie</th>
            <th>Date effet</th>
            <th class="num">Prime</th>
            <th class="num">Commission</th>
            <th>Statut</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="9" class="empty">Aucun contrat trouvé.</td></tr>
    <?php else: foreach ($rows as $r): $g = $r['gestion']; ?>
        <tr>
            <td><?= e($r['reference'] ?? $r['_key']) ?></td>
            <td><?= e(trim(($r['client_nom'] ?? '') . ' ' . ($r['client_prenom'] ?? ''))) ?: '—' ?></td>
            <td><?= e($r['produit'] ?? '') ?: '—' ?></td>
            <td><?= e($r['compagnie'] ?? '') ?: '—' ?></td>
            <td><?= e(dateFr($r['date_effet'] ?? null)) ?></td>
            <td class="num"><?= e(euros($r['prime'] ?? null)) ?></td>
            <td class="num"><?= e(euros($g['commission'])) ?></td>
            <td><span class="badge s-<?= e($g['statut']) ?>"><?= e(statutLabel($g['statut'])) ?></span></td>
            <td><a class="btn btn-sm" href="contract.php?ref=<?= urlencode((string) $r['_key']) ?>">Gérer</a></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
