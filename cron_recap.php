<?php
/**
 * Récapitulatif de la veille — à lancer chaque jour à 9h par cron.
 * Résume l'activité (devis + contrats) de l'apporteur REYNARD la veille.
 *
 * Cron (cPanel) :  0 9 * * *   php /home/autotemnet/public_html/cron_recap.php
 */
require __DIR__ . '/cron_common.php';
if (!$ids) { fwrite(STDERR, "Aucun apporteur REYNARD\n"); exit(0); }

$hier = date('Y-m-d', strtotime('-1 day'));
$in = implode(',', array_fill(0, count($ids), '?'));

$sql = "SELECT g.id, g.id_app, g.num_garantie, g.num_contrat, g.type_contrat, g.formule,
               g.prix_assitance, g.prix_pj, g.id_lb2,
               r.note3, r.pa, r.marge AS r_marge, r.honoraire AS r_hono, r.etat,
               cl.nom AS cnom, cl.prenom AS cprenom, cl.ville AS cville,
               v.immatriculation AS immat, v.marque AS marque, v.modele AS modele
        FROM jl_garantie g
        LEFT JOIN jl_client cl ON cl.id = g.id_cli
        LEFT JOIN jl_vehicule v ON v.id = g.id_vehi
        LEFT JOIN jl_reglement r ON r.id = g.id_reglement
        WHERE g.id_app IN ($in) AND DATE(g.date_demande) = ? ORDER BY g.id";
$st = $pdo->prepare($sql);
$params = $ids; $params[] = $hier;
$st->execute($params);
$rows = $st->fetchAll();

$nbDevis = 0; $nbContrats = 0; $totRetro = 0; $lignes = '';
foreach ($rows as $d) {
    $estContrat = ($d['num_contrat'] !== '' && $d['num_contrat'] !== null);
    if ($estContrat) { $nbContrats++; } else { $nbDevis++; }
    $mb = isset($apInfo[(int) $d['id_app']]) ? $apInfo[(int) $d['id_app']]['mb'] : 0;
    $soc = isset($apInfo[(int) $d['id_app']]) ? $apInfo[(int) $d['id_app']]['societe'] : '';
    $retro = retroContrat($d, $mb);
    $totRetro += $retro;
    $veh = trim($d['marque'] . ' ' . $d['modele']);
    $lignes .= '<tr>'
        . '<td style="border:1px solid #ddd;padding:6px">' . ($estContrat ? '<b style="color:#1a7d49">Contrat</b>' : '<b style="color:#d98a00">Devis</b>') . '</td>'
        . '<td style="border:1px solid #ddd;padding:6px">' . hh($soc) . '</td>'
        . '<td style="border:1px solid #ddd;padding:6px">' . hh(trim($d['cnom'] . ' ' . $d['cprenom'])) . ' <span style="color:#666">' . hh($d['cville']) . '</span></td>'
        . '<td style="border:1px solid #ddd;padding:6px">' . hh($d['immat']) . ($veh !== '' ? ' — ' . hh($veh) : '') . '</td>'
        . '<td style="border:1px solid #ddd;padding:6px">' . hh($d['type_contrat']) . ' ' . hh($d['formule']) . '</td>'
        . '<td style="border:1px solid #ddd;padding:6px;text-align:right">' . eur($retro) . '</td>'
        . '</tr>';
}

$corps = '<p>Activité MCJ-Courtage du <strong>' . date('d/m/Y', strtotime($hier)) . '</strong> :</p>'
    . '<ul>'
    . '<li><b>' . count($rows) . '</b> demande(s) au total</li>'
    . '<li><b>' . $nbContrats . '</b> contrat(s) — <b>' . $nbDevis . '</b> devis</li>'
    . '<li>Rétro globale cumulée : <b>' . eur($totRetro) . '</b></li>'
    . '</ul>';

if ($rows) {
    $corps .= '<table style="border-collapse:collapse;font-size:13px;margin-top:10px">'
        . '<tr style="background:#f7f9fd"><th style="border:1px solid #ddd;padding:6px">Type</th>'
        . '<th style="border:1px solid #ddd;padding:6px">Société</th>'
        . '<th style="border:1px solid #ddd;padding:6px">Client</th><th style="border:1px solid #ddd;padding:6px">Véhicule</th>'
        . '<th style="border:1px solid #ddd;padding:6px">Produit</th><th style="border:1px solid #ddd;padding:6px">Rétro globale</th></tr>'
        . $lignes . '</table>';
} else {
    $corps .= '<p style="color:#888">Aucune activité la veille.</p>';
}

$sujet = 'Récap MCJ-Courtage du ' . date('d/m/Y', strtotime($hier)) . ' — ' . count($rows) . ' demande(s)';
$ok = envoyerMail($EMAIL_TO, $EMAIL_FROM, $sujet, gabaritMail('Récapitulatif de la veille', $corps));
logCron('cron_recap', ($ok ? 'Email envoyé' : 'ECHEC envoi mail') . ' à ' . $EMAIL_TO . ' — ' . count($rows) . ' demande(s) le ' . $hier . ' (' . $nbContrats . ' contrats, ' . $nbDevis . ' devis)');
