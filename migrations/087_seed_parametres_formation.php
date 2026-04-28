<?php
/**
 * Seed des paramètres formation — valeurs par défaut.
 * Idempotent : INSERT IGNORE sur la clé primaire.
 */
require_once __DIR__ . '/../init.php';

$params = [
    // ── Évaluation ────────────────────────────────────────────
    ['eval.auto_update_niveau_apres_formation', '1', 'bool', 'evaluation', 1,
     'Auto-update du niveau après formation',
     'Quand un participant valide une formation liée à une thématique, monter automatiquement son niveau actuel.', '1'],
    ['eval.validation_manager_requise', '1', 'bool', 'evaluation', 2,
     'Validation manager requise',
     'Si actif, l\'auto-update est proposé en mode "à valider par le N+1" plutôt qu\'appliqué directement.', '1'],
    ['eval.evaluation_continue', '0', 'bool', 'evaluation', 3,
     'Évaluation continue',
     'Permettre au manager d\'éditer un niveau hors entretien annuel.', '0'],
    ['eval.seuil_priorite_haute', '2', 'int', 'evaluation', 4,
     'Seuil priorité haute (écart)',
     'Écart (niveau requis − niveau actuel) ≥ X = priorité haute.', '2', 1, 4],
    ['eval.seuil_priorite_moyenne', '1', 'int', 'evaluation', 5,
     'Seuil priorité moyenne (écart)',
     'Écart = X = priorité moyenne.', '1', 1, 3],

    // ── Expirations ───────────────────────────────────────────
    ['exp.delai_alerte_expiration_jours', '60', 'int', 'expirations', 10,
     'Anticipation alerte expiration (jours)',
     'Combien de jours avant l\'expiration commencer à alerter.', '60', 7, 365],
    ['exp.delai_relance_inc_jours', '30', 'int', 'expirations', 11,
     'Délai relance INC (jours)',
     'Alerter si un nouveau collaborateur n\'a pas suivi son INC après X jours.', '30', 7, 180],
    ['exp.delai_validite_bls_aed_mois', '24', 'int', 'expirations', 12,
     'Validité BLS-AED (mois)',
     'Renouvellement biennal FEGEMS.', '24', 1, 60],
    ['exp.delai_validite_hpci_mois', '12', 'int', 'expirations', 13,
     'Validité HPCI (mois)',
     'Anticipation du renouvellement HPCI.', '12', 1, 60],

    // ── Inscriptions FEGEMS ───────────────────────────────────
    ['insc.auto_proposer', '1', 'bool', 'inscriptions', 20,
     'Suggestions automatiques',
     'Le scan auto crée des suggestions d\'inscription depuis la cartographie d\'écarts.', '1'],
    ['insc.frequence_scan', 'quotidien', 'string', 'inscriptions', 21,
     'Fréquence du scan',
     'Quand exécuter le scan automatique des suggestions.', 'quotidien', null, null,
     '["quotidien","hebdomadaire","manuel"]'],
    ['insc.email_destinataire_fegems', 'inscription@fegems.ch', 'string', 'inscriptions', 22,
     'Destinataire FEGEMS',
     'Adresse email FEGEMS pour les inscriptions groupées.', 'inscription@fegems.ch'],
    ['insc.email_cc_interne', '', 'string', 'inscriptions', 23,
     'CC interne (RH/Formation)',
     'Mettre en copie une adresse interne (ex: formation@ems.ch).', ''],
    ['insc.email_signature_html', '', 'string', 'inscriptions', 24,
     'Signature email HTML',
     'Signature pré-remplie en bas des emails d\'inscription.', ''],
    ['insc.template_email_subject', 'Inscription {{formation}} · {{date}} · {{nb}} collaborateurs · {{ems}}', 'string', 'inscriptions', 25,
     'Template objet email',
     'Variables disponibles : {{formation}}, {{date}}, {{nb}}, {{ems}}.',
     'Inscription {{formation}} · {{date}} · {{nb}} collaborateurs · {{ems}}'],
    ['insc.cotisation_fegems_active', '1', 'bool', 'inscriptions', 26,
     'Cotisation FEGEMS active',
     'Si membre, le coût des formations FEGEMS est CHF 0 par défaut.', '1'],
    ['insc.numero_membre_fegems', '', 'string', 'inscriptions', 27,
     'Numéro membre FEGEMS',
     'À mentionner dans les emails d\'inscription.', ''],

    // ── Entretiens ────────────────────────────────────────────
    ['entr.frequence_mois', '12', 'int', 'entretiens', 30,
     'Fréquence des entretiens (mois)',
     'Espacement entre 2 entretiens annuels.', '12', 6, 24],
    ['entr.auto_creer_a_echeance', '1', 'bool', 'entretiens', 31,
     'Création auto à l\'échéance',
     'Créer automatiquement la fiche d\'entretien à la date d\'échéance.', '1'],
    ['entr.notification_collab_avant_jours', '14', 'int', 'entretiens', 32,
     'Notification collaborateur (jours avant)',
     'Notifier le collaborateur X jours avant son entretien.', '14', 1, 60],
    ['entr.auto_creer_objectif_si_ecart_haute', '1', 'bool', 'entretiens', 33,
     'Créer objectif auto si écart haute',
     'Pour chaque écart priorité haute, créer un objectif annuel à la prochaine cartographie.', '1'],

    // ── Budget ────────────────────────────────────────────────
    ['bud.annuel_total_chf', '0', 'decimal', 'budget', 40,
     'Budget formation annuel (CHF)',
     'Enveloppe annuelle pour le suivi de consommation.', '0', 0, null],
    ['bud.seuil_alerte_pct', '80', 'int', 'budget', 41,
     'Seuil alerte budget (%)',
     'Alerter quand X % du budget annuel est consommé.', '80', 50, 100],
    ['bud.devise', 'CHF', 'string', 'budget', 42,
     'Devise',
     'Devise affichée dans le module formations.', 'CHF', null, null,
     '["CHF","EUR","USD"]'],

    // ── Référentiel ───────────────────────────────────────────
    ['ref.niveau_max', '4', 'int', 'referentiel', 50,
     'Niveau maximum',
     'Échelle FEGEMS officielle = 1 à 4.', '4', 3, 5],
    ['ref.libelles_niveaux', '{"1":"Connaît / débute","2":"Applique avec supervision","3":"Autonome","4":"Référent / transmet"}', 'json', 'referentiel', 51,
     'Libellés des niveaux',
     'Texte affiché pour chaque niveau (1 à niveau_max).',
     '{"1":"Connaît / débute","2":"Applique avec supervision","3":"Autonome","4":"Référent / transmet"}'],
    ['ref.secteurs_locaux_actifs', '0', 'bool', 'referentiel', 52,
     'Secteurs locaux additionnels',
     'Activer la table de mapping secteurs locaux → FEGEMS (ex: "Animation", "Intendance" en plus des 6 officiels).', '0'],
];

$inserted = 0;
$skipped = 0;
foreach ($params as $p) {
    [$cle, $valeur, $type, $categorie, $ordre, $libelle, $description, $defaut] = $p;
    $minVal     = $p[8]  ?? null;
    $maxVal     = $p[9]  ?? null;
    $optionsJson = $p[10] ?? null;

    $exists = Db::fetch("SELECT cle FROM parametres_formation WHERE cle = ?", [$cle]);
    if ($exists) { $skipped++; continue; }

    Db::exec(
        "INSERT INTO parametres_formation (cle, valeur, type, categorie, ordre, libelle, description, valeur_defaut, min_val, max_val, options_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$cle, $valeur, $type, $categorie, $ordre, $libelle, $description, $defaut, $minVal, $maxVal, $optionsJson]
    );
    $inserted++;
}

echo "Paramètres formation — insérés: $inserted · ignorés (déjà présents): $skipped\n";
