/*
 * MCJ-Courtage — Prévisionnel simple : où finit l'année ?
 * Données réelles dans window.PV (index.php, vue "previsionnel"). Un seul réglage : le scénario.
 * projeter() est exporté pour les tests (Node).
 */
(function (root) {
  'use strict';

  var MOIS = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
  var SCENARIOS = {
    prudent:   { croissance: -2, depenses: 10 },
    realiste:  { croissance: 0,  depenses: 0 },
    ambitieux: { croissance: 3,  depenses: 0 }
  };

  function somme(a) { var s = 0; for (var i = 0; i < a.length; i++) { s += a[i]; } return s; }
  function moyenne(a) { return a.length ? somme(a) / a.length : 0; }
  function impotPME(r) { return r <= 0 ? 0 : 0.15 * Math.min(r, 42500) + 0.25 * Math.max(0, r - 42500); }

  /**
   * Mois passés : réel. Mois en cours : rythme actuel (comme l'onglet Production).
   * Mois à venir : moyenne des 3 derniers mois complets × saisonnalité de N-1 (si complète) × croissance du scénario.
   * sc = { croissance: %/mois, depenses: % } ; depManuelle sert seulement sans Qonto.
   */
  function projeter(D, sc, depManuelle) {
    var mc = D.mc - 1, m, fen = [];
    for (m = mc - 1; m >= 0 && fen.length < 3; m--) { fen.push(m); }
    var pris = function (t) { return fen.map(function (k) { return t[k]; }); };
    var rythme = D.jc > 0 ? D.retroN[mc] / D.jc * D.jm : 0;
    var baseRetro = fen.length ? moyenne(pris(D.retroN)) : rythme;
    var saisonDispo = fen.length > 0 && D.retroP.every(function (v) { return v > 0; });
    var refP = saisonDispo ? moyenne(pris(D.retroP)) : 0;
    var depBase = D.qonto ? (fen.length ? moyenne(pris(D.depN)) : (D.jc > 0 ? D.depN[mc] / D.jc * D.jm : 0)) : (depManuelle || 0);
    var depFutur = depBase * (1 + sc.depenses / 100);

    var retro = [], dep = [], statut = [];
    for (m = 0; m < 12; m++) {
      if (m < mc) {
        statut.push('reel'); retro.push(D.retroN[m]); dep.push(D.qonto ? D.depN[m] : depBase);
      } else if (m === mc) {
        statut.push('encours'); retro.push(Math.max(D.retroN[mc], rythme));
        dep.push(D.qonto ? D.depN[mc] + depFutur * (D.jm - D.jc) / D.jm : depBase);
      } else {
        var sais = refP > 0 ? Math.min(2, Math.max(0.5, D.retroP[m] / refP)) : 1;
        statut.push('proj'); retro.push(baseRetro * sais * Math.pow(1 + sc.croissance / 100, m - mc)); dep.push(depFutur);
      }
    }

    var retroReel = somme(retro.slice(0, mc)) + D.retroN[mc];
    var depReel = D.qonto ? somme(dep.slice(0, mc)) + D.depN[mc] : 0;
    var ca = somme(retro), charges = somme(dep);
    var resultat = ca - charges, impot = impotPME(resultat);
    var depP = somme(D.depP || []), retroP = somme(D.retroP);
    return {
      retro: retro, dep: dep, statut: statut,
      retroReel: retroReel, retroProj: ca - retroReel, depReel: depReel, depProj: charges - depReel,
      ca: ca, charges: charges, resultat: resultat, impot: impot, net: resultat - impot,
      tresoFin: D.qonto && D.solde !== null ? D.solde + (ca - retroReel) - (charges - depReel) : null,
      retroP: retroP, resultatP: D.qonto && depP > 0 ? (retroP - depP) - impotPME(retroP - depP) : null, // N-1 aussi après impôt
      saisonDispo: saisonDispo, baseRetro: baseRetro, depBase: depBase
    };
  }

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = { projeter: projeter, impotPME: impotPME, SCENARIOS: SCENARIOS };
  }
  if (typeof document === 'undefined' || !root.PV) { return; }

  // ═══════════════════════════════ Interface ═══════════════════════════════
  var D = root.PV, CLE = 'mcj_previsionnel_scenario';
  var BLEU = '#2a78d6', ORANGE = '#eb6834';
  var eur = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
  function euros(v) { return eur.format(Math.round(v)); }
  function $(id) { return document.getElementById(id); }
  function texte(id, t) { var el = $(id); if (el) { el.textContent = t; } }

  var scenario = 'realiste';
  try { var s = root.localStorage.getItem(CLE); if (SCENARIOS[s]) { scenario = s; } } catch (e) { /* stockage indisponible */ }

  // Chiffres qui défilent jusqu'à leur nouvelle valeur.
  var affiches = {};
  var calme = root.matchMedia && root.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function animer(id, cible) {
    var el = $(id); if (!el) { return; }
    var depart = id in affiches ? affiches[id] : 0; affiches[id] = cible;
    if (calme) { el.textContent = euros(cible); return; }
    var t0 = null;
    (function pas(t) {
      if (t0 === null) { t0 = t; }
      var k = Math.min(1, (t - t0) / 450);
      el.textContent = euros(depart + (cible - depart) * (1 - Math.pow(1 - k, 3)));
      if (k < 1 && affiches[id] === cible) { root.requestAnimationFrame(pas); }
    })(performance.now());
  }
  function evolution(id, valeur, reference) {
    var el = $(id); if (!el) { return; }
    if (!reference) { el.textContent = ''; return; }
    var p = (valeur - reference) / Math.abs(reference) * 100;
    el.className = 'pv-evol ' + (p >= 0 ? 'pv-bon' : 'pv-mauvais');
    el.textContent = (p >= 0 ? '▲ +' : '▼ ') + p.toLocaleString('fr-FR', { maximumFractionDigits: 0 }) + ' % vs ' + (D.annee - 1);
  }

  Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", sans-serif';
  Chart.defaults.color = '#898781';
  var dernier = null;
  var graphique = new Chart($('pvMois'), {
    type: 'bar',
    data: { labels: MOIS, datasets: [
      { label: 'Rétro', data: [], backgroundColor: [], borderRadius: 4, borderSkipped: 'start', maxBarThickness: 22 },
      { label: 'Dépenses', data: [], backgroundColor: [], borderRadius: 4, borderSkipped: 'start', maxBarThickness: 22 }
    ] },
    options: {
      responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 12,
          generateLabels: function (ch) { return ch.data.datasets.map(function (ds, i) {
            return { text: ds.label, fillStyle: i ? ORANGE : BLEU, strokeStyle: 'transparent', pointStyle: 'rectRounded', datasetIndex: i, hidden: !ch.isDatasetVisible(i) };
          }); } } },
        tooltip: { backgroundColor: '#fff', titleColor: '#52514e', bodyColor: '#0b0b0b', borderColor: 'rgba(11,11,11,.12)', borderWidth: 1, padding: 10,
          callbacks: {
            title: function (it) { var st = dernier.statut[it[0].dataIndex]; return it[0].label + (st === 'reel' ? ' — réel' : st === 'encours' ? ' — en cours' : ' — estimé'); },
            label: function (c) { return ' ' + c.dataset.label + ' : ' + euros(c.raw); },
            footer: function (it) { var i = it[0].dataIndex; return 'Résultat du mois : ' + euros(dernier.retro[i] - dernier.dep[i]); }
          } }
      },
      scales: {
        x: { grid: { display: false }, border: { color: '#c3c2b7' } },
        y: { beginAtZero: true, border: { display: false }, grid: { color: '#e1e0d9' },
             ticks: { callback: function (v) { return v >= 10000 ? Math.round(v / 1000) + ' k€' : v.toLocaleString('fr-FR') + ' €'; } } }
      }
    }
  });

  function teinte(hex, st) { return st === 'proj' ? hex + '59' : st === 'encours' ? hex + 'a6' : hex; }

  function rendre() {
    var champ = $('pvDepManuelle');
    var r = projeter(D, SCENARIOS[scenario], champ ? parseFloat(champ.value) || 0 : 0);
    dernier = r;

    animer('pvNet', r.net);
    evolution('pvNetEvol', r.net, r.resultatP);
    animer('pvCa', r.ca); evolution('pvCaEvol', r.ca, r.retroP);
    animer('pvCharges', r.charges);
    if (r.tresoFin !== null) { animer('pvTreso', r.tresoFin); }

    graphique.data.datasets[0].data = r.retro.map(Math.round);
    graphique.data.datasets[1].data = r.dep.map(Math.round);
    graphique.data.datasets[0].backgroundColor = r.statut.map(function (st) { return teinte(BLEU, st); });
    graphique.data.datasets[1].backgroundColor = r.statut.map(function (st) { return teinte(ORANGE, st); });
    graphique.update();

    // Bilan au 31/12, ligne par ligne.
    var lignes = [['Rétro réalisée', r.retroReel], ['Rétro estimée d\'ici fin décembre', r.retroProj]];
    if (D.qonto) { lignes.push(['Dépenses réalisées', -r.depReel], ['Dépenses estimées d\'ici fin décembre', -r.depProj]); }
    else { lignes.push(['Dépenses de l\'année (estimation)', -r.charges]); }
    lignes.push(['=Résultat avant impôt', r.resultat], ['Impôt sur les sociétés (estimation)', -r.impot], ['=Résultat net', r.net]);
    var tb = $('pvBilan'); tb.textContent = '';
    lignes.forEach(function (l) {
      var total = l[0].charAt(0) === '=', tr = document.createElement('tr');
      var a = document.createElement('td'), b = document.createElement('td');
      a.textContent = total ? l[0].slice(1) : l[0];
      b.textContent = (!total && l[1] > 0 && l !== lignes[0] ? '+ ' : '') + (l[1] < 0 ? '− ' + euros(-l[1]) : euros(l[1]));
      b.className = 'num';
      if (total) { tr.className = 'pv-total'; }
      tr.appendChild(a); tr.appendChild(b); tb.appendChild(tr);
    });

    texte('pvHyp', 'Projection : moyenne de tes 3 derniers mois (rétro ' + euros(r.baseRetro) + ', dépenses ' + euros(r.depBase) + ' par mois)'
      + (r.saisonDispo ? ', ajustée selon la saisonnalité de ' + (D.annee - 1) : '')
      + '. Prudent : rétro −2 %/mois et dépenses +10 %. Ambitieux : rétro +3 %/mois. Impôt : estimation PME indicative.');

    Array.prototype.forEach.call(document.querySelectorAll('[data-scenario]'), function (b) {
      b.setAttribute('aria-pressed', b.getAttribute('data-scenario') === scenario ? 'true' : 'false');
    });
    try { root.localStorage.setItem(CLE, scenario); } catch (e) { /* ignore */ }
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-scenario]'), function (b) {
    b.addEventListener('click', function () { scenario = b.getAttribute('data-scenario'); rendre(); });
  });
  if ($('pvDepManuelle')) { $('pvDepManuelle').addEventListener('input', rendre); }
  rendre();
})(typeof window !== 'undefined' ? window : this);
