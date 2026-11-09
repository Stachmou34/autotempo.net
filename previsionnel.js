/*
 * MCJ-Courtage — Prévisionnel dynamique et bilan potentiel au 31/12.
 * Les données réelles (rétro JLASSURE, Qonto) arrivent dans window.PV (voir index.php, vue "previsionnel").
 * Tout le calcul se fait ici, dans le navigateur, pour réagir instantanément aux réglages.
 * projeter() est aussi exporté pour les tests (Node).
 */
(function (root) {
  'use strict';

  var MOIS = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
  var SCENARIOS = {
    prudent:   { croissance: -2, depenses: 10 },
    realiste:  { croissance: 0,  depenses: 0 },
    ambitieux: { croissance: 3,  depenses: 0 }
  };
  var DEFAUTS = {
    scenario: 'realiste', croissance: 0, depenses: 0, exceptionnelles: 0, depManuelle: 0,
    annuelles: 0, commAnnuelle: 234, panne: 0, commPanne: 100,
    objectif: 0, saisonnalite: true, impot: true
  };
  var IS_SEUIL = 42500; // taux réduit PME 15 % jusqu'à ce bénéfice, 25 % au-delà (indicatif)

  function somme(a) { var s = 0; for (var i = 0; i < a.length; i++) { s += a[i]; } return s; }
  function moyenne(a) { return a.length ? somme(a) / a.length : 0; }
  function borne(v, lo, hi) { return Math.min(hi, Math.max(lo, v)); }
  function impotPME(r) { return r <= 0 ? 0 : 0.15 * Math.min(r, IS_SEUIL) + 0.25 * Math.max(0, r - IS_SEUIL); }

  /**
   * Projection de l'année. D = données réelles, P = réglages.
   * Mois passés : réel. Mois en cours : rythme actuel (comme l'onglet Production). Mois à venir : modèle.
   */
  function projeter(D, P) {
    var mc = D.mc - 1, i, m;
    // Fenêtre de référence : les 3 derniers mois complets (moins en début d'année).
    var fen = [];
    for (i = mc - 1; i >= 0 && fen.length < 3; i--) { fen.push(i); }
    var rythmeRetro = D.jc > 0 ? D.retroN[mc] / D.jc * D.jm : 0;
    var baseRetro = fen.length ? moyenne(fen.map(function (k) { return D.retroN[k]; })) : rythmeRetro;

    // Saisonnalité : profil de N-1, rapporté à la même fenêtre. Seulement si N-1 est complet.
    var saisonDispo = fen.length > 0 && D.retroP.every(function (v) { return v > 0; });
    var refP = saisonDispo ? moyenne(fen.map(function (k) { return D.retroP[k]; })) : 0;
    var sais = [];
    for (m = 0; m < 12; m++) { sais.push(P.saisonnalite && refP > 0 ? borne(D.retroP[m] / refP, 0.5, 2) : 1); }

    var g = P.croissance / 100;
    var leviersMois = P.annuelles * P.commAnnuelle + P.panne * P.commPanne;
    var depFen = fen.map(function (k) { return D.depN[k]; });
    var depBase = D.qonto ? (depFen.length ? moyenne(depFen) : (D.jc > 0 ? D.depN[mc] / D.jc * D.jm : 0)) : P.depManuelle;
    var depFutur = depBase * (1 + P.depenses / 100);
    var resteMois = (D.jm - D.jc) / D.jm;

    var retro = [], leviers = [], dep = [], statut = [];
    for (m = 0; m < 12; m++) {
      if (m < mc) {
        statut.push('reel');
        retro.push(D.retroN[m]);
        dep.push(D.qonto ? D.depN[m] : P.depManuelle);
        leviers.push(0);
      } else if (m === mc) {
        statut.push('encours');
        retro.push(Math.max(D.retroN[mc], rythmeRetro));
        dep.push(D.qonto ? D.depN[mc] + depFutur * resteMois : P.depManuelle);
        leviers.push(0);
      } else {
        statut.push('proj');
        retro.push(baseRetro * sais[m] * Math.pow(1 + g, m - mc));
        dep.push(depFutur);
        leviers.push(leviersMois);
      }
    }

    var retroReel = somme(retro.slice(0, mc)) + D.retroN[mc];
    var retroTot = somme(retro);
    var levTot = somme(leviers);
    var depTot = somme(dep);
    var depReel = D.qonto ? somme(dep.slice(0, mc)) + D.depN[mc] : 0;
    var exc = P.exceptionnelles;
    var ca = retroTot + levTot;
    var charges = depTot + exc;
    var resultat = ca - charges;
    var impot = P.impot ? impotPME(resultat) : 0;

    // Trésorerie : fins de mois passées reconstruites à partir de Qonto, puis projection.
    var treso = null, tresoFin = null;
    if (D.solde !== null && D.solde !== undefined) {
      treso = D.soldeFin.slice(0, mc + 1); // [début d'année, fin Jan, ..., fin du mois précédent]
      var t = D.solde;
      for (m = mc; m < 12; m++) {
        if (m === mc) { t += (retro[mc] - D.retroN[mc]) - (dep[mc] - (D.qonto ? D.depN[mc] : 0)); }
        else { t += retro[m] + leviers[m] - dep[m] - (m === 11 ? exc : 0); }
        treso.push(t);
      }
      tresoFin = t;
    }

    // Objectif : rétro + leviers nécessaires par mois restant.
    var acquis = retroReel + (retro[mc] - D.retroN[mc]);
    var moisRestants = 11 - mc;
    var besoinMois = P.objectif > 0 && moisRestants > 0 ? Math.max(0, (P.objectif - acquis) / moisRestants) : 0;
    var prevuMois = moisRestants > 0 ? (somme(retro.slice(mc + 1)) + somme(leviers.slice(mc + 1))) / moisRestants : 0;

    var retroP = somme(D.retroP);
    var depP = somme(D.depP || []);
    return {
      retro: retro, leviers: leviers, dep: dep, statut: statut, treso: treso,
      retroReel: retroReel, retroProj: retroTot - retroReel, levTot: levTot,
      depReel: depReel, depProj: depTot - depReel, exc: exc,
      ca: ca, charges: charges, resultat: resultat, impot: impot, net: resultat - impot, tresoFin: tresoFin,
      saisonDispo: saisonDispo, baseRetro: baseRetro, depBase: depBase,
      besoinMois: besoinMois, prevuMois: prevuMois, moisRestants: moisRestants,
      retroP: retroP, resultatP: D.qonto && depP > 0 ? retroP - depP : null
    };
  }

  /** CA cumulé en fin de mois, précédé du point de départ (0) : 13 valeurs. */
  function cumul(r) {
    var c = [0], s = 0;
    for (var m = 0; m < 12; m++) { s += r.retro[m] + r.leviers[m]; c.push(s); }
    return c;
  }

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = { projeter: projeter, cumul: cumul, impotPME: impotPME, SCENARIOS: SCENARIOS, DEFAUTS: DEFAUTS };
  }
  if (typeof document === 'undefined' || !root.PV) { return; }

  // ════════════════════════════════ Interface ════════════════════════════════
  var D = root.PV;
  var CLE = 'mcj_previsionnel_v1';
  var C = { bleu: '#2a78d6', orange: '#eb6834', aqua: '#1baf7a', gris: '#a8a69e', encre: '#0b0b0b',
            encre2: '#52514e', muet: '#898781', grille: '#e1e0d9', axe: '#c3c2b7', bon: '#006300', mauvais: '#d03b3b' };
  var reduitMouvement = root.matchMedia && root.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var eur0 = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
  function euros(v) { return eur0.format(Math.round(v)); }
  function compact(v) {
    var a = Math.abs(v);
    if (a >= 1e6) { return (v / 1e6).toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' M€'; }
    if (a >= 1e4) { return Math.round(v / 1e3).toLocaleString('fr-FR') + ' k€'; }
    return Math.round(v).toLocaleString('fr-FR') + ' €';
  }
  function $(id) { return document.getElementById(id); }
  function texte(id, t) { var el = $(id); if (el) { el.textContent = t; } }

  // Réglages : défauts → réglages enregistrés dans ce navigateur.
  var P = {};
  Object.keys(DEFAUTS).forEach(function (k) { P[k] = DEFAUTS[k]; });
  P.objectif = Math.round((somme(D.retroP) > 0 ? somme(D.retroP) * 1.2 : 0) / 1000) * 1000;
  try { var sauve = JSON.parse(root.localStorage.getItem(CLE) || 'null'); if (sauve) { Object.keys(DEFAUTS).forEach(function (k) { if (k in sauve) { P[k] = sauve[k]; } }); } } catch (e) { /* stockage indisponible */ }
  function enregistrer() { try { root.localStorage.setItem(CLE, JSON.stringify(P)); } catch (e) { /* ignore */ } }

  // ── Compteurs animés ──
  var affiches = {};
  function animer(id, cible, fmt) {
    var el = $(id); if (!el) { return; }
    var depart = id in affiches ? affiches[id] : 0; // premier affichage : on compte depuis 0
    affiches[id] = cible;
    if (reduitMouvement || depart === cible) { el.textContent = fmt(cible); return; }
    var t0 = null, duree = 450;
    function pas(t) {
      if (t0 === null) { t0 = t; }
      var k = Math.min(1, (t - t0) / duree), e = 1 - Math.pow(1 - k, 3);
      el.textContent = fmt(depart + (cible - depart) * e);
      if (k < 1 && affiches[id] === cible) { root.requestAnimationFrame(pas); }
    }
    root.requestAnimationFrame(pas);
  }
  function delta(id, valeur, reference, libelle) {
    var el = $(id); if (!el) { return; }
    if (reference === null || reference === undefined || reference === 0) { el.textContent = ''; el.className = 'pv-delta'; return; }
    var p = (valeur - reference) / Math.abs(reference) * 100, haut = p >= 0;
    el.className = 'pv-delta ' + (haut ? 'pv-bon' : 'pv-mauvais');
    el.textContent = (haut ? '▲ +' : '▼ ') + p.toLocaleString('fr-FR', { maximumFractionDigits: 1 }) + ' % ' + libelle;
  }

  // ── Chart.js : réglages communs (grille fine, encre discrète) ──
  Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", sans-serif';
  Chart.defaults.color = C.muet;
  Chart.defaults.borderColor = C.grille;
  var tooltip = {
    backgroundColor: '#fff', titleColor: C.encre2, bodyColor: C.encre, borderColor: 'rgba(11,11,11,.12)', borderWidth: 1,
    padding: 10, boxPadding: 4, usePointStyle: true, titleFont: { weight: '500' }, bodyFont: { weight: '600' }
  };
  var axeEuro = { grid: { color: C.grille, drawTicks: false }, border: { display: false },
                  ticks: { padding: 8, callback: function (v) { return compact(v); } } };
  var axeX = { grid: { display: false }, border: { color: C.axe } };

  // Étiquette directe à la fin d'une série (une seule, jamais sur chaque point).
  var etiquetteFin = {
    id: 'etiquetteFin',
    afterDatasetsDraw: function (chart) {
      var o = chart.options.plugins.etiquetteFin; if (!o || typeof o.dataset !== 'number') { return; } // Chart.js donne {} aux graphiques sans réglage
      var meta = chart.getDatasetMeta(o.dataset); if (meta.hidden) { return; }
      var pts = meta.data, val = chart.data.datasets[o.dataset].data, k = val.length - 1;
      while (k >= 0 && (val[k] === null || val[k] === undefined)) { k--; }
      if (k < 0) { return; }
      var v = Array.isArray(val[k]) ? val[k][1] : val[k], ctx = chart.ctx, p = pts[k];
      ctx.save();
      ctx.font = '600 12px ' + Chart.defaults.font.family;
      ctx.fillStyle = C.encre;
      ctx.textAlign = o.align || 'right';
      ctx.fillText(o.prefixe + compact(v), p.x + (o.dx || 0), Math.min(p.y, p.base === undefined ? p.y : p.base) - 8);
      ctx.restore();
    }
  };
  Chart.register(etiquetteFin);

  var labelsCumul = ['Début'].concat(MOIS);
  var gTraj = new Chart($('pvTrajectoire'), {
    type: 'line',
    data: { labels: labelsCumul, datasets: [
      { label: 'Fourchette prudent – ambitieux (haut)', data: [], borderWidth: 0, pointRadius: 0, fill: false, backgroundColor: 'rgba(42,120,214,.10)' },
      { label: 'Fourchette prudent – ambitieux', data: [], borderWidth: 0, pointRadius: 0, fill: '-1', backgroundColor: 'rgba(42,120,214,.10)' },
      { label: 'Réalisé', data: [], borderColor: C.bleu, backgroundColor: C.bleu, borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 5, tension: 0.25 },
      { label: 'Projection', data: [], borderColor: C.bleu, backgroundColor: C.bleu, borderWidth: 2, borderDash: [6, 5], pointRadius: 0, pointHoverRadius: 5, tension: 0.25 },
      { label: String(D.annee - 1), data: [], borderColor: C.gris, backgroundColor: C.gris, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, tension: 0.25 },
      { label: 'Objectif', data: [], borderColor: C.encre2, backgroundColor: C.encre2, borderWidth: 1, pointRadius: 0, pointHoverRadius: 0 }
    ] },
    options: {
      responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 18,
          generateLabels: function (ch) {
            return ch.data.datasets.map(function (ds, i) {
              var bande = i === 1;
              return { text: ds.label, datasetIndex: i, hidden: !ch.isDatasetVisible(i), pointStyle: bande ? 'rect' : 'line',
                       fillStyle: bande ? 'rgba(42,120,214,.25)' : ds.borderColor, strokeStyle: bande ? 'transparent' : ds.borderColor,
                       lineWidth: bande ? 0 : 2, fontColor: C.encre2 };
            }).filter(function (it) { return it.datasetIndex !== 0; });
          } } },
        tooltip: Object.assign({}, tooltip, { filter: function (it) { return it.datasetIndex > 1 && it.raw !== null; },
          callbacks: { label: function (c) { return ' ' + euros(c.raw) + '  ' + c.dataset.label; } } }),
        etiquetteFin: { dataset: 3, prefixe: '31/12 : ', align: 'right', dx: -4 }
      },
      scales: { x: axeX, y: Object.assign({}, axeEuro, { beginAtZero: true }) }
    }
  });

  var gMois = new Chart($('pvMois'), {
    type: 'bar',
    data: { labels: MOIS, datasets: [
      { label: 'Rétro', data: [], stack: 'ca', backgroundColor: [], borderRadius: 4, borderSkipped: 'start', maxBarThickness: 22 },
      { label: 'Nouveaux leviers', data: [], stack: 'ca', backgroundColor: [], borderRadius: 4, borderSkipped: 'start', maxBarThickness: 22 },
      { label: 'Dépenses', data: [], stack: 'dep', backgroundColor: [], borderRadius: 4, borderSkipped: 'start', maxBarThickness: 22 }
    ] },
    options: {
      responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
      datasets: { bar: { borderColor: '#fff', borderWidth: { top: 2 } } },
      plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 12,
          generateLabels: function (ch) {
            return ch.data.datasets.map(function (ds, i) {
              return { text: ds.label, fillStyle: [C.bleu, C.aqua, C.orange][i], strokeStyle: 'transparent', pointStyle: 'rectRounded', datasetIndex: i, hidden: !ch.isDatasetVisible(i) };
            });
          } } },
        tooltip: Object.assign({}, tooltip, {
          callbacks: {
            title: function (it) { var s = dernier.statut[it[0].dataIndex]; return it[0].label + (s === 'reel' ? ' — réel' : s === 'encours' ? ' — en cours' : ' — projeté'); },
            label: function (c) { return ' ' + euros(c.raw) + '  ' + c.dataset.label; },
            footer: function (it) { var i = it[0].dataIndex; return 'Résultat : ' + euros(dernier.retro[i] + dernier.leviers[i] - dernier.dep[i]); }
          } })
      },
      scales: { x: Object.assign({ stacked: true }, axeX), y: Object.assign({ stacked: true, beginAtZero: true }, axeEuro) }
    }
  });

  var gCascade = new Chart($('pvCascade'), {
    type: 'bar',
    data: { labels: [], datasets: [{ label: 'Bilan', data: [], backgroundColor: [], borderRadius: 4, borderSkipped: false, maxBarThickness: 40 }] },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: Object.assign({}, tooltip, { callbacks: {
          label: function (c) { var s = cascadeSignes[c.dataIndex]; return ' ' + (s > 0 ? '+ ' : s < 0 ? '− ' : '= ') + euros(Math.abs(c.raw[1] - c.raw[0])); } } }),
        etiquetteFin: { dataset: 0, prefixe: '', align: 'center' }
      },
      scales: { x: Object.assign({}, axeX, { ticks: { autoSkip: false, maxRotation: 0, font: { size: 11 } } }), y: axeEuro }
    }
  });

  var gTreso = $('pvTreso') ? new Chart($('pvTreso'), {
    type: 'line',
    data: { labels: labelsCumul, datasets: [
      { label: 'Réel', data: [], borderColor: C.bleu, backgroundColor: 'rgba(42,120,214,.10)', fill: 'origin', borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 5, tension: 0.25 },
      { label: 'Projeté', data: [], borderColor: C.bleu, borderDash: [6, 5], borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, tension: 0.25 }
    ] },
    options: {
      responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'line', boxWidth: 18 } },
        tooltip: Object.assign({}, tooltip, { filter: function (it) { return it.raw !== null; },
          callbacks: { title: function (it) { return it[0].dataIndex === 0 ? '1er janvier' : 'Fin ' + it[0].label; },
                       label: function (c) { return ' ' + euros(c.raw) + '  ' + c.dataset.label; } } }),
        etiquetteFin: { dataset: 1, prefixe: '31/12 : ', align: 'right', dx: -4 }
      },
      scales: { x: axeX, y: axeEuro }
    }
  }) : null;

  var cascadeSignes = [];
  var dernier = null;

  /** Coupe un libellé long en deux lignes, à l'espace le plus proche du milieu. */
  function deuxLignes(t) {
    if (t.length <= 10 || t.indexOf(' ') < 0) { return t; }
    var mil = t.length / 2, best = -1;
    for (var i = 0; i < t.length; i++) { if (t[i] === ' ' && (best < 0 || Math.abs(i - mil) < Math.abs(best - mil))) { best = i; } }
    return [t.slice(0, best), t.slice(best + 1)];
  }
  function alpha(hex, statut) { return statut === 'proj' ? hex + '66' : statut === 'encours' ? hex + 'b3' : hex; }

  function rendre() {
    var r = projeter(D, P); dernier = r;
    var pr = projeter(D, Object.assign({}, P, SCENARIOS.prudent));
    var am = projeter(D, Object.assign({}, P, SCENARIOS.ambitieux));
    var mc = D.mc - 1, k;

    // ── Héros et tuiles ──
    animer('pvNet', r.net, euros);
    texte('pvNetSous', 'Chiffre d\'affaires ' + euros(r.ca) + ' − charges ' + euros(r.charges)
      + (r.impot ? ' − impôt ' + euros(r.impot) : '') + (r.ca > 0 ? ' · marge nette ' + Math.round(r.net / r.ca * 100) + ' %' : ''));
    delta('pvNetDelta', r.net, r.resultatP, 'vs résultat ' + (D.annee - 1));
    animer('pvCa', r.ca, euros);
    delta('pvCaDelta', r.ca, r.retroP, 'vs ' + (D.annee - 1));
    texte('pvCaSous', 'dont ' + euros(r.retroReel) + ' déjà réalisés' + (r.levTot ? ' · leviers ' + euros(r.levTot) : ''));
    animer('pvCharges', r.charges, euros);
    texte('pvChargesSous', D.qonto ? 'dont ' + euros(r.depReel) + ' déjà dépensés' : 'estimation (Qonto non connecté)');
    if (r.tresoFin !== null) { animer('pvTresoFin', r.tresoFin, euros); texte('pvTresoSous', 'solde actuel ' + euros(D.solde)); }

    // Objectif
    var pct = P.objectif > 0 ? r.ca / P.objectif * 100 : 0;
    $('pvJauge').style.width = Math.min(100, pct) + '%';
    var st = $('pvObjStatut');
    if (P.objectif > 0) {
      var ok = r.ca >= P.objectif;
      st.className = 'pv-statut ' + (ok ? 'pv-bon' : 'pv-mauvais');
      st.textContent = (ok ? '✔ En avance' : '⚠ En retard') + ' · ' + Math.round(pct) + ' % de ' + euros(P.objectif);
      var manque = P.objectif - r.ca;
      texte('pvObjSous', ok
        ? (r.besoinMois <= 0 ? 'Objectif déjà couvert par le réalisé' : 'Marge de ' + euros(-manque) + ' sur l\'objectif')
        : r.moisRestants > 0
          ? 'Il manque ' + euros(manque) + ' : viser ' + euros(r.besoinMois) + '/mois (prévu ' + euros(r.prevuMois) + ')'
          : 'Il manque ' + euros(manque) + ' sur l\'objectif');
    } else { st.className = 'pv-statut'; st.textContent = 'Fixe un objectif dans les réglages'; texte('pvObjSous', ''); }

    // ── Trajectoire ──
    var cR = cumul(r), cPr = cumul(pr), cAm = cumul(am), cP = [0], s = 0;
    for (k = 0; k < 12; k++) { s += D.retroP[k]; cP.push(s); }
    var reel = [], proj = [], bh = [], bb = [];
    for (k = 0; k <= 12; k++) {
      reel.push(k <= mc ? cR[k] : null);
      proj.push(k >= mc ? cR[k] : null);
      bh.push(k >= mc ? Math.max(cPr[k], cAm[k]) : null);
      bb.push(k >= mc ? Math.min(cPr[k], cAm[k]) : null);
    }
    var dsT = gTraj.data.datasets;
    dsT[0].data = bh; dsT[1].data = bb; dsT[2].data = reel; dsT[3].data = proj; dsT[4].data = cP;
    dsT[5].data = P.objectif > 0 ? labelsCumul.map(function () { return P.objectif; }) : [];
    gTraj.update();
    texte('pvFourchette', 'Fourchette au 31/12 : ' + euros(Math.min(cPr[12], cAm[12])) + ' – ' + euros(Math.max(cPr[12], cAm[12])));

    // ── Mois ──
    var dsM = gMois.data.datasets;
    dsM[0].data = r.retro.map(Math.round); dsM[1].data = r.leviers.map(Math.round); dsM[2].data = r.dep.map(Math.round);
    dsM[0].backgroundColor = r.statut.map(function (x) { return alpha(C.bleu, x); });
    dsM[1].backgroundColor = r.statut.map(function (x) { return alpha(C.aqua, x); });
    dsM[2].backgroundColor = r.statut.map(function (x) { return alpha(C.orange, x); });
    gMois.update();

    // ── Cascade du bilan ──
    var etapes = [['Rétro réalisée', r.retroReel, 1, C.bleu], ['Rétro à venir', r.retroProj, 1, C.bleu],
                  ['Nouveaux leviers', r.levTot, 1, C.aqua], [D.qonto ? 'Dépenses réalisées' : 'Dépenses estimées', r.depReel || r.depProj, -1, C.orange],
                  ['Dépenses à venir', D.qonto ? r.depProj : 0, -1, C.orange], ['Exceptionnelles', r.exc, -1, C.orange],
                  ['Impôt (IS)', r.impot, -1, C.orange]];
    var lab = [], val = [], col = [], niveau = 0;
    cascadeSignes = [];
    etapes.forEach(function (e) {
      if (!e[1]) { return; }
      var bas = niveau, haut = niveau + e[2] * e[1];
      lab.push(deuxLignes(e[0])); val.push([bas, haut]); col.push(e[3]); cascadeSignes.push(e[2]); niveau = haut;
    });
    lab.push('Résultat net'); val.push([0, niveau]); col.push(niveau >= 0 ? C.encre2 : C.mauvais); cascadeSignes.push(0);
    gCascade.data.labels = lab; gCascade.data.datasets[0].data = val; gCascade.data.datasets[0].backgroundColor = col;
    gCascade.update();

    // ── Trésorerie ──
    if (gTreso && r.treso) {
      gTreso.data.datasets[0].data = r.treso.map(function (v, i) { return i <= mc ? v : null; });
      gTreso.data.datasets[1].data = r.treso.map(function (v, i) { return i >= mc ? v : null; });
      gTreso.update();
    }

    // ── Tableau ──
    var tb = $('pvTable'); tb.textContent = '';
    for (k = 0; k < 12; k++) {
      var tr = document.createElement('tr');
      if (r.statut[k] !== 'reel') { tr.className = r.statut[k] === 'encours' ? 'pv-encours' : 'pv-proj'; }
      [MOIS[k] + ' ' + D.annee, r.statut[k] === 'reel' ? 'Réel' : r.statut[k] === 'encours' ? 'En cours' : 'Projeté',
       euros(r.retro[k]), r.leviers[k] ? euros(r.leviers[k]) : '—', euros(r.dep[k]),
       euros(r.retro[k] + r.leviers[k] - r.dep[k]), r.treso ? euros(r.treso[k + 1]) : '—'].forEach(function (t, j) {
        var td = document.createElement('td'); td.textContent = t; if (j >= 2) { td.className = 'num'; } tr.appendChild(td);
      });
      tb.appendChild(tr);
    }

    // ── Hypothèses ──
    texte('pvHypBase', euros(r.baseRetro) + '/mois');
    texte('pvHypDep', euros(r.depBase) + '/mois');
    texte('pvHypSais', r.saisonDispo ? (P.saisonnalite ? 'appliquée' : 'désactivée') : 'indisponible (' + (D.annee - 1) + ' incomplet)');
    $('pvSais').disabled = !r.saisonDispo;
    enregistrer();
  }

  // ── Réglages ──
  var champs = ['croissance', 'depenses', 'exceptionnelles', 'depManuelle', 'annuelles', 'commAnnuelle', 'panne', 'commPanne', 'objectif'];
  function sortie(id) {
    var o = $('pvOut_' + id); if (!o) { return; }
    if (id === 'croissance') { o.textContent = (P[id] > 0 ? '+' : '') + P[id].toLocaleString('fr-FR') + ' %/mois'; }
    else if (id === 'depenses') { o.textContent = (P[id] > 0 ? '+' : '') + P[id] + ' %'; }
    else if (id === 'annuelles' || id === 'panne') { o.textContent = P[id] + '/mois'; }
  }
  function marquerScenario() {
    var actif = 'perso';
    Object.keys(SCENARIOS).forEach(function (n) { if (SCENARIOS[n].croissance === P.croissance && SCENARIOS[n].depenses === P.depenses) { actif = n; } });
    P.scenario = actif;
    Array.prototype.forEach.call(document.querySelectorAll('[data-scenario]'), function (b) {
      b.setAttribute('aria-pressed', b.getAttribute('data-scenario') === actif ? 'true' : 'false');
    });
  }
  function remplir() {
    champs.forEach(function (id) { var el = $('pv_' + id); if (el) { el.value = P[id]; sortie(id); } });
    $('pvSais').checked = !!P.saisonnalite; $('pvImpot').checked = !!P.impot;
    marquerScenario();
  }
  champs.forEach(function (id) {
    var el = $('pv_' + id); if (!el) { return; }
    el.addEventListener('input', function () { var v = parseFloat(el.value); P[id] = isNaN(v) ? 0 : v; sortie(id); marquerScenario(); rendre(); });
  });
  $('pvSais').addEventListener('change', function (e) { P.saisonnalite = e.target.checked; rendre(); });
  $('pvImpot').addEventListener('change', function (e) { P.impot = e.target.checked; rendre(); });
  Array.prototype.forEach.call(document.querySelectorAll('[data-scenario]'), function (b) {
    b.addEventListener('click', function () {
      var sc = SCENARIOS[b.getAttribute('data-scenario')]; P.croissance = sc.croissance; P.depenses = sc.depenses;
      remplir(); rendre();
    });
  });
  $('pvReset').addEventListener('click', function () {
    var obj = P.objectif;
    Object.keys(DEFAUTS).forEach(function (k) { P[k] = DEFAUTS[k]; });
    P.objectif = obj; remplir(); rendre();
  });

  remplir();
  rendre();
})(typeof window !== 'undefined' ? window : this);
