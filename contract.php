<?php
require __DIR__ . '/src/bootstrap.php';
Auth::require();

$repo = new ContractRepository($CONFIG);
$ref  = (string) ($_GET['ref'] ?? '');
$flash = '';

// Enregistrement de la gestion (statut, commission, notes).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfValidate($_POST['csrf'] ?? null)) {
        $flash = 'Session expirée, modification non enregistrée.';
    } else {
        $statut = (string) ($_POST['statut'] ?? 'nouveau');
        if (!in_array($statut, statutsDisponibles(), true)) {
            $statut = 'nouveau';
        }
        $repo->saveManagement($ref, [
            'statut'           => $statut,
            'commission'       => trim((string) ($_POST['commission'] ?? '')),
            'commission_payee' => isset($_POST['commission_payee']),
            'notes'            => trim((string) ($_POST['notes'] ?? '')),
        ], Auth::user());
        header('Location: contract.php?ref=' . urlencode($ref) . '&saved=1');
        exit;
    }
}

$contract = $repo->find($ref);
if (!$contract) {
    http_response_code(404);
    $pageTitle = 'Contrat introuvable';
    require __DIR__ . '/partials/header.php';
    echo '<div class="alert error">Contrat introuvable. <a href="index.php">Retour à la liste</a></div>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

if (isset($_GET['saved'])) {
    $flash = 'Modifications enregistrées.';
}
$g = $contract['gestion'];
$pageTitle = 'Contrat ' . ($contract['reference'] ?? $ref);
require __DIR__ . '/partials/header.php';
?>

<p><a href="index.php">← Retour à la liste</a></p>

<?php if ($flash): ?><p class="alert success"><?= e($flash) ?></p><?php endif; ?>

<h1>Contrat <?= e($contract['reference'] ?? $ref) ?></h1>

<div class="detail-grid">
    <section class="card">
        <h2>Informations (JLASSURE — lecture seule)</h2>
        <dl>
            <dt>Référence</dt><dd><?= e($contract['reference'] ?? $ref) ?></dd>
            <dt>Client</dt><dd><?= e(trim(($contract['client_nom'] ?? '') . ' ' . ($contract['client_prenom'] ?? ''))) ?: '—' ?></dd>
            <dt>Produit</dt><dd><?= e($contract['produit'] ?? '') ?: '—' ?></dd>
            <dt>Compagnie</dt><dd><?= e($contract['compagnie'] ?? '') ?: '—' ?></dd>
            <dt>Date d'effet</dt><dd><?= e(dateFr($contract['date_effet'] ?? null)) ?></dd>
            <dt>Date de souscription</dt><dd><?= e(dateFr($contract['date_souscription'] ?? null)) ?></dd>
            <dt>Prime</dt><dd><?= e(euros($contract['prime'] ?? null)) ?></dd>
            <dt>Apporteur</dt><dd><?= e($contract['apporteur'] ?? '') ?></dd>
            <dt>Statut source</dt><dd><?= e($contract['statut_source'] ?? '') ?: '—' ?></dd>
        </dl>
    </section>

    <section class="card">
        <h2>Gestion MCJ</h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <label>Statut
                <select name="statut">
                    <?php foreach (statutsDisponibles() as $s): ?>
                        <option value="<?= e($s) ?>" <?= $g['statut'] === $s ? 'selected' : '' ?>><?= e(statutLabel($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Commission apporteur (€)
                <input type="number" step="0.01" name="commission" value="<?= e((string) ($g['commission'] ?? '')) ?>">
            </label>
            <label class="check">
                <input type="checkbox" name="commission_payee" value="1" <?= !empty($g['commission_payee']) ? 'checked' : '' ?>>
                Commission payée
            </label>
            <label>Notes
                <textarea name="notes" rows="5"><?= e($g['notes'] ?? '') ?></textarea>
            </label>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
    </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
