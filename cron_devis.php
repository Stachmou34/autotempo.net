<?php
/**
 * Alerte "nouveau devis" — à lancer fréquemment (ex. toutes les 10 min) par cron.
 * Détecte les nouvelles garanties (devis) de l'apporteur REYNARD depuis le
 * dernier passage et envoie un email récapitulatif.
 *
 * Cron (cPanel) :  * / 10 * * * *   php /home/autotemnet/public_html/cron_devis.php
 */
require __DIR__ . '/cron_common.php';
if (!$ids) { fwrite(STDERR, "Aucun apporteur REYNARD\n"); exit(0); }

$stateFile = __DIR__ . '/.last_devis';
$last = is_file($stateFile) ? (int) trim(file_get_contents($stateFile)) : 0;
$in = implode(',', array_fill(0, count($ids), '?'));

// Premier passage : on mémorise l'id max sans envoyer d'email (évite le spam initial).
if ($last === 0) {
    $s = $pdo->prepare("SELECT COALESCE(MAX(id),0) AS m FROM jl_garantie WHERE id_app IN ($in)");
    $s->execute($ids);
    $row = $s->fetch();
    file_put_contents($stateFile, (int) $row['m']);
    echo "Initialisé au dernier id = " . (int) $row['m'] . "\n";
    exit(0);
}

// Nouvelles garanties depuis le dernier id vu.
$sql = "SELECT g.id, g.num_garantie, g.num_contrat, g.type_contrat, g.formule, g.date_demande, g.prix_formule,
               cl.nom AS cnom, cl.prenom AS cprenom, cl.ville AS cville, cl.mobile AS cmobile, cl.mail AS cmail,
               v.immatriculation AS immat, v.marque AS marque, v.modele AS modele
        FROM jl_garantie g
        LEFT JOIN jl_client cl ON cl.id = g.id_cli
        LEFT JOIN jl_vehicule v ON v.id = g.id_vehi
        WHERE g.id_app IN ($in) AND g.id > ? ORDER BY g.id";
$st = $pdo->prepare($sql);
$params = $ids; $params[] = $last;
$st->execute($params);
$news = $st->fetchAll();

if (!$news) { echo "Aucun nouveau devis.\n"; exit(0); }

$lignes = '';
foreach ($news as $d) {
    $estContrat = ($d['num_contrat'] !== '' && $d['num_contrat'] !== null);
    $type = $estContrat ? 'Contrat' : 'Devis';
    $badge = $estContrat ? '#1a7d49' : '#d98a00';
    $veh = trim($d['marque'] . ' ' . $d['modele']);
    $lignes .= '<tr>'
        . '<td style="border:1px solid #ddd;padding:6px"><b style="color:' . $badge . '">' . $type . '</b></td>'
        . '<td style="border:1px solid #ddd;padding:6px">' . hh(dfr($d['date_demande'])) . '</td>'
        . '<td style="border:1px solid #ddd;padding:6px">' . hh(trim($d['cnom'] . ' ' . $d['cprenom'])) . '<br><span style="color:#666">' . hh($d['cville']) . '</span></td>'
        . '<td style="border:1px solid #ddd;padding:6px">' . hh($d['cmobile']) . '<br>' . hh($d['cmail']) . '</td>'
        . '<td style="border:1px solid #ddd;padding:6px">' . hh($d['immat']) . ($veh !== '' ? '<br><span style="color:#666">' . hh($veh) . '</span>' : '') . '</td>'
        . '<td style="border:1px solid #ddd;padding:6px">' . hh($d['type_contrat']) . ' ' . hh($d['formule']) . '</td>'
        . '<td style="border:1px solid #ddd;padding:6px;text-align:right">' . eur($d['prix_formule']) . '</td>'
        . '</tr>';
}

$corps = '<p>' . count($news) . ' nouvelle(s) demande(s) pour MCJ-Courtage :</p>'
    . '<table style="border-collapse:collapse;font-size:13px">'
    . '<tr style="background:#f7f9fd">'
    . '<th style="border:1px solid #ddd;padding:6px">Type</th><th style="border:1px solid #ddd;padding:6px">Date</th>'
    . '<th style="border:1px solid #ddd;padding:6px">Client</th><th style="border:1px solid #ddd;padding:6px">Contact</th>'
    . '<th style="border:1px solid #ddd;padding:6px">Véhicule</th><th style="border:1px solid #ddd;padding:6px">Produit</th>'
    . '<th style="border:1px solid #ddd;padding:6px">Prime</th></tr>'
    . $lignes . '</table>';

$sujet = count($news) . ' nouveau(x) devis MCJ-Courtage';
$ok = envoyerMail($EMAIL_TO, $EMAIL_FROM, $sujet, gabaritMail('Nouveaux devis', $corps));

// Mémorise le dernier id traité.
$dernier = (int) $news[count($news) - 1]['id'];
file_put_contents($stateFile, $dernier);
echo ($ok ? 'Email envoyé' : 'Échec envoi') . ' — ' . count($news) . " devis, dernier id = $dernier\n";
