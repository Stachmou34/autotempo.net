<?php
require __DIR__ . '/src/bootstrap.php';
Auth::requireLogin();

$repo = new ContractRepository($CONFIG);
$ref  = isset($_GET['ref']) ? $_GET['ref'] : '';
$flash = '';

// Enregistrement de la gestion (statut, commission, notes).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfValidate(isset($_POST['csrf']) ? $_POST['csrf'] : null)) {
        $flash = 'Session expirée, modification non enregistrée.';
    } else {
        $statut = isset($_POST['statut']) ? $_POST['statut'] : 'nouveau';
        if (!in_array($statut, statutsDisponibles(), true)) {
            $statut = 'nouveau';
        }
        $repo->saveManagement($ref, array(
            'statut'           => $statut,
            'commission'       => trim(isset($_POST['commission']) ? $_POST['commission'] : ''),
            'commission_payee' => isset($_POST['commission_payee']),
            'notes'            => trim(isset($_POST['notes']) ? $_POST['notes'] : ''),
        ), Auth::user());
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
$pageTitle = 'Contrat ' . (isset($contract['reference']) ? $contract['reference'] : $ref);
require __DIR__ . '/partials/header.php';

$nomClient = trim((isset($contract['client_nom']) ? $contract['client_nom'] : '') . ' ' . (isset($contract['client_prenom']) ? $contract['client_prenom'] : ''));
?>

<p><a href="index.php">← Retour à la liste</a></p>

<?php if ($flash): ?><p class="alert success"><?= e($flash) ?></p><?php endif; ?>

<h1>Contrat <?= e(isset($contract['reference']) ? $contract['reference'] : $ref) ?></h1>

<div class="detail-grid">
    <section class="card">
        <h2>Informations (JLASSURE — lecture seule)</h2>
        <dl>
            <dt>Référence</dt><dd><?= e(isset($contract['reference']) ? $contract['reference'] : $ref) ?></dd>
            <dt>Client</dt><dd><?= $nomClient !== '' ? e($nomClient) : '—' ?></dd>
            <dt>Produit</dt><dd><?= isset($contract['produit']) && $contract['produit'] !== '' ? e($contract['produit']) : '—' ?></dd>
            <dt>Compagnie</dt><dd><?= isset($contract['compagnie']) && $contract['compagnie'] !== '' ? e($contract['compagnie']) : '—' ?></dd>
            <dt>Date d'effet</dt><dd><?= e(dateFr(isset($contract['date_effet']) ? $contract['date_effet'] : null)) ?></dd>
            <dt>Date de souscription</dt><dd><?= e(dateFr(isset($contract['date_souscription']) ? $contract['date_souscription'] : null)) ?></dd>
            <dt>Prime</dt><dd><?= e(euros(isset($contract['prime']) ? $contract['prime'] : null)) ?></dd>
            <dt>Apporteur</dt><dd><?= e(isset($contract['apporteur']) ? $contract['apporteur'] : '') ?></dd>
            <dt>Statut source</dt><dd><?= isset($contract['statut_source']) && $contract['statut_source'] !== '' ? e($contract['statut_source']) : '—' ?></dd>
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
                <input type="number" step="0.01" name="commission" value="<?= e((string) (isset($g['commission']) ? $g['commission'] : '')) ?>">
            </label>
            <label class="check">
                <input type="checkbox" name="commission_payee" value="1" <?= !empty($g['commission_payee']) ? 'checked' : '' ?>>
                Commission payée
            </label>
            <label>Notes
                <textarea name="notes" rows="5"><?= e(isset($g['notes']) ? $g['notes'] : '') ?></textarea>
            </label>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
    </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
