<?php
/**
 * Activité : nombre de contrats du jour, de la semaine, du mois et de l'année,
 * prévision de fin de période et comparaison avec l'an dernier.
 * Fonctions pures (aucune base de données), compatibles PHP 5.6 / 7.2.
 *
 * Méthode :
 *  - Jour de référence d'une date = les ACT_REF mêmes jours de semaine précédents (avant aujourd'hui).
 *  - Aujourd'hui : réalisé + reste habituel d'un tel jour à partir de cette heure,
 *    ajusté par le rythme du jour (réalisé / habituel à la même heure, borné entre 0,5 et 2).
 *  - Jours à venir : moyenne de leurs jours de référence.
 *  - L'an dernier : même jour de semaine il y a 52 semaines (jour, semaine), même mois civil (mois, année).
 */
if (isset($_SERVER['SCRIPT_FILENAME']) && basename($_SERVER['SCRIPT_FILENAME']) === 'activite.php') { http_response_code(403); exit; }

define('ACT_REF', 6);

/** Décale une date de $jours jours calendaires (sûr aux changements d'heure), heure conservée. */
function actDecale($ts, $jours) {
    return mktime((int) date('G', $ts), (int) date('i', $ts), (int) date('s', $ts),
                  (int) date('n', $ts), (int) date('j', $ts) + $jours, (int) date('Y', $ts));
}
function actJour($ts) { return date('Y-m-d', $ts); }

/** Début de la plage à charger : couvre l'an dernier entier, sa semaine alignée et les jours de référence. */
function activiteDebut($now) {
    $debutN1 = mktime(0, 0, 0, 1, 1, (int) date('Y', $now) - 1);
    $lundiN1 = actDecale($now, -364 - ((int) date('N', $now) - 1));
    $refs = actDecale($now, -7 * ACT_REF);
    return date('Y-m-d 00:00:00', min($debutN1, $lundiN1, $refs));
}

/**
 * $stamps : horodatages (timestamps) des contrats ; $now : maintenant.
 * Renvoie les quatre périodes (réalisé, prévision, an dernier) et les séries des deux graphiques.
 */
function activiteCalcul($stamps, $now) {
    $jours = array(); $avecHeure = false;
    foreach ($stamps as $t) {
        if ($t > $now) { continue; }
        $m = (int) date('G', $t) * 60 + (int) date('i', $t);
        $jours[actJour($t)][] = $m;
        if ($m > 0) { $avecHeure = true; }
    }
    foreach ($jours as $d => $l) { sort($l); $jours[$d] = $l; }

    // Nombre de contrats d'une date (Y-m-d), éventuellement jusqu'à une minute de la journée incluse.
    $nb = function ($d, $jusqua = 1440) use ($jours) {
        if (!isset($jours[$d])) { return 0; }
        if ($jusqua >= 1440) { return count($jours[$d]); }
        $c = 0;
        foreach ($jours[$d] as $m) { if ($m > $jusqua) { break; } $c++; }
        return $c;
    };
    $M = (int) date('G', $now) * 60 + (int) date('i', $now);
    $auj = actJour($now);
    $minuit = mktime(0, 0, 0, (int) date('n', $now), (int) date('j', $now), (int) date('Y', $now));

    // Jours de référence d'une date : mêmes jours de semaine, strictement avant aujourd'hui.
    $refs = function ($ts) use ($minuit) {
        $r = array();
        for ($k = 1; count($r) < ACT_REF && $k < 60; $k++) {
            $x = actDecale($ts, -7 * $k);
            if ($x < $minuit) { $r[] = actJour($x); }
        }
        return $r;
    };
    $moyenne = function ($dates, $jusqua = 1440) use ($nb) {
        if (!$dates) { return 0.0; }
        $s = 0; foreach ($dates as $d) { $s += $nb($d, $jusqua); }
        return $s / count($dates);
    };
    $attendu = function ($ts) use ($refs, $moyenne) { return $moyenne($refs($ts)); };

    // ── Aujourd'hui ──
    $refAuj = $refs($minuit);
    $reel = $nb($auj);
    $typAvant = $moyenne($refAuj, $M);
    $typTotal = $moyenne($refAuj);
    $rythme = 1.0;
    if ($avecHeure) {
        if ($typAvant >= 2) { $rythme = max(0.5, min(2.0, $reel / $typAvant)); }
        $prevJour = $reel + max(0, $typTotal - $typAvant) * $rythme;
    } else {
        $prevJour = max($reel, $typTotal);
    }
    $resteJour = $prevJour - $reel;

    // Somme des contrats entre deux minuits (inclus), et prévision des jours restants jusqu'à $fin.
    $somme = function ($deb, $fin) use ($nb) {
        $s = 0; for ($x = $deb; $x <= $fin; $x = actDecale($x, 1)) { $s += $nb(actJour($x)); } return $s;
    };
    $aVenir = function ($fin) use ($minuit, $attendu) {
        $s = 0.0; for ($x = actDecale($minuit, 1); $x <= $fin; $x = actDecale($x, 1)) { $s += $attendu($x); } return $s;
    };
    // L'an dernier "au même moment" : jours complets avant $ref, plus le jour $ref jusqu'à la minute actuelle.
    $aDate = function ($deb, $ref) use ($somme, $nb, $M) {
        return ($ref > $deb ? $somme($deb, actDecale($ref, -1)) : 0) + $nb(actJour($ref), $M);
    };

    $jourN1 = actDecale($minuit, -364);
    $lundi = actDecale($minuit, -((int) date('N', $now) - 1)); $dimanche = actDecale($lundi, 6);
    $lundiN1 = actDecale($lundi, -364);
    $debMois = mktime(0, 0, 0, (int) date('n', $now), 1, (int) date('Y', $now));
    $finMois = mktime(0, 0, 0, (int) date('n', $now), (int) date('t', $now), (int) date('Y', $now));
    $an = (int) date('Y', $now); $mois = (int) date('n', $now);
    $debMoisN1 = mktime(0, 0, 0, $mois, 1, $an - 1);
    $dimN1 = (int) date('t', $debMoisN1);
    $memeJourN1 = mktime(0, 0, 0, $mois, min((int) date('j', $now), $dimN1), $an - 1);
    $debAn = mktime(0, 0, 0, 1, 1, $an); $debAnN1 = mktime(0, 0, 0, 1, 1, $an - 1);

    $reelSem = $somme($lundi, $minuit);
    $reelMois = $somme($debMois, $minuit);
    $periodes = array(
        'jour' => array('reel' => $reel, 'prevision' => $prevJour,
            'n1Total' => $nb(actJour($jourN1)), 'n1Moment' => $nb(actJour($jourN1), $M), 'n1Date' => $jourN1),
        'semaine' => array('reel' => $reelSem, 'prevision' => $reelSem + $resteJour + $aVenir($dimanche),
            'n1Total' => $somme($lundiN1, actDecale($lundiN1, 6)), 'n1Moment' => $aDate($lundiN1, $jourN1), 'n1Date' => $lundiN1),
        'mois' => array('reel' => $reelMois, 'prevision' => $reelMois + $resteJour + $aVenir($finMois),
            'n1Total' => $somme($debMoisN1, mktime(0, 0, 0, $mois, $dimN1, $an - 1)), 'n1Moment' => $aDate($debMoisN1, $memeJourN1), 'n1Date' => $debMoisN1),
        'annee' => array('reel' => $somme($debAn, $minuit), 'prevision' => null,
            'n1Total' => $somme($debAnN1, mktime(0, 0, 0, 12, 31, $an - 1)), 'n1Moment' => $aDate($debAnN1, mktime(0, 0, 0, $mois, min((int) date('j', $now), $dimN1), $an - 1)), 'n1Date' => $debAnN1),
    );

    // ── Graphique « aujourd'hui heure par heure » (cumul, axe en heures décimales) ──
    $hNow = $M / 60;
    $heures = array('reel' => array(), 'prevision' => array(), 'habituel' => array(), 'n1' => array());
    for ($h = 0; $h <= 24; $h++) {
        $lim = $h * 60 - 1; // contrats passés avant h:00
        $heures['habituel'][] = array('x' => $h, 'y' => round($moyenne($refAuj, $lim), 1));
        $heures['n1'][] = array('x' => $h, 'y' => $nb(actJour($jourN1), $lim));
        if ($h <= $hNow) { $heures['reel'][] = array('x' => $h, 'y' => $nb($auj, $lim)); }
        elseif ($avecHeure) { $heures['prevision'][] = array('x' => $h, 'y' => round($reel + max(0, $moyenne($refAuj, $lim) - $typAvant) * $rythme, 1)); }
    }
    $heures['reel'][] = array('x' => round($hNow, 2), 'y' => $reel);
    array_unshift($heures['prevision'], array('x' => round($hNow, 2), 'y' => $reel));

    // ── Graphique « ce mois-ci jour par jour » (cumul) ──
    $jNow = (int) date('j', $now); $dim = (int) date('t', $now);
    $moisG = array('labels' => array(), 'reel' => array(), 'prevision' => array(), 'n1' => array());
    $cum = 0; $cumN1 = 0; $cumPrev = null;
    for ($j = 1; $j <= max($dim, $dimN1); $j++) {
        $moisG['labels'][] = $j;
        if ($j <= $dim) {
            $ts = mktime(0, 0, 0, $mois, $j, $an);
            if ($j < $jNow) { $cum += $nb(actJour($ts)); $moisG['reel'][] = $cum; $moisG['prevision'][] = ($j === $jNow - 1) ? $cum : null; }
            elseif ($j === $jNow) { $moisG['reel'][] = $cum + $reel; $cumPrev = $cum + $prevJour; $moisG['prevision'][] = round($cumPrev, 1); }
            else { $moisG['reel'][] = null; $cumPrev += $attendu($ts); $moisG['prevision'][] = round($cumPrev, 1); }
        } else { $moisG['reel'][] = null; $moisG['prevision'][] = null; }
        if ($j <= $dimN1) { $cumN1 += $nb(actJour(mktime(0, 0, 0, $mois, $j, $an - 1))); $moisG['n1'][] = $cumN1; }
        else { $moisG['n1'][] = null; }
    }

    return array('periodes' => $periodes, 'avecHeure' => $avecHeure, 'rythme' => $rythme,
                 'typTotal' => $typTotal, 'refsAuj' => $refAuj, 'heures' => $heures, 'mois' => $moisG);
}
