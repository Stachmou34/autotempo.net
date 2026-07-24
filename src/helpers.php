<?php
/**
 * Fonctions utilitaires. Compatible PHP 5.6+.
 */

/** Échappement HTML sûr. */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formate un montant en euros. */
function euros($value)
{
    if ($value === null || $value === '') {
        return '—';
    }
    return number_format((float) $value, 2, ',', ' ') . ' €';
}

/** Formate une date (YYYY-MM-DD ou datetime) en JJ/MM/AAAA. */
function dateFr($value)
{
    if (empty($value)) {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('d/m/Y', $ts) : e($value);
}

/** Tronque une chaîne (compatible sans mbstring). */
function truncate($value, $len)
{
    $value = (string) $value;
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $len, '…', 'UTF-8');
    }
    return strlen($value) > $len ? substr($value, 0, $len - 1) . '…' : $value;
}

/** Libellés lisibles pour les statuts de gestion. */
function statutLabel($statut)
{
    static $labels = array(
        'nouveau'    => 'Nouveau',
        'en_cours'   => 'En cours',
        'valide'     => 'Validé',
        'en_attente' => 'En attente',
        'impaye'     => 'Impayé',
        'resilie'    => 'Résilié',
        'annule'     => 'Annulé',
    );
    return isset($labels[$statut]) ? $labels[$statut] : ucfirst($statut);
}

/** Liste des statuts disponibles. */
function statutsDisponibles()
{
    return array('nouveau', 'en_cours', 'valide', 'en_attente', 'impaye', 'resilie', 'annule');
}
