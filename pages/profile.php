<?php
require_once __DIR__ . '/../init.php';
if (empty($_SESSION['ss_user'])) { http_response_code(401); exit; }
require_once __DIR__ . '/_partials/helpers.php';

$uid = $_SESSION['ss_user']['id'];
$u = Db::fetch(
    "SELECT u.*, f.nom AS fonction_nom
     FROM users u LEFT JOIN fonctions f ON f.id = u.fonction_id
     WHERE u.id = ? AND u.is_active = 1",
    [$uid]
);

if (!$u) {
    echo '<div class="prof-wrap"><div class="alert alert-danger">Profil introuvable.</div></div>';
    exit;
}

unset($u['password'], $u['reset_token'], $u['reset_expires']);

$modules = Db::fetchAll(
    "SELECT m.id, m.nom, m.code, um.is_principal
     FROM user_modules um JOIN modules m ON m.id = um.module_id
     WHERE um.user_id = ? ORDER BY um.is_principal DESC, m.ordre",
    [$uid]
);

$initials = strtoupper(mb_substr($u['prenom'] ?? '', 0, 1) . mb_substr($u['nom'] ?? '', 0, 1));
$ROLES = [
    'admin' => 'Administrateur', 'direction' => 'Direction',
    'responsable' => 'Responsable', 'collaborateur' => 'Collaborateur',
    'stagiaire' => 'Stagiaire',
];
$roleLabel = $ROLES[$u['role']] ?? $u['role'];
?>
<div class="prof-wrap">
    <div class="mb-3">
        <h2 class="page-title mb-0"><i class="bi bi-person"></i> Mon Profil</h2>
        <p class="text-muted small mb-0">Vos informations personnelles et paramètres</p>
    </div>

    <!-- Hero card -->
    <div class="card prof-hero-card mb-4">
        <div class="card-body prof-hero-body">
            <?php if (!empty($u['photo'])): ?>
                <img src="<?= h($u['photo']) ?>?t=<?= time() ?>" class="prof-avatar-img" id="profAvatarImg" title="Cliquer pour changer">
            <?php else: ?>
                <div class="prof-avatar-initials" id="profAvatarImg" title="Cliquer pour ajouter une photo">
                    <?= h($initials) ?>
                    <i class="bi bi-camera-fill prof-avatar-camera"></i>
                </div>
            <?php endif ?>
            <input type="file" id="profAvatarInput" accept="image/*" class="d-none">

            <div class="flex-grow-1 min-width-0">
                <h3 class="prof-name"><?= h($u['prenom'] . ' ' . $u['nom']) ?></h3>
                <div class="prof-meta">
                    <span class="prof-role-badge prof-role-<?= h($u['role']) ?>">
                        <i class="bi bi-shield-check"></i> <?= h($roleLabel) ?>
                    </span>
                    <?php if ($u['fonction_nom']): ?>
                        <span class="prof-meta-item"><i class="bi bi-person-badge"></i> <?= h($u['fonction_nom']) ?></span>
                    <?php endif ?>
                    <span class="prof-meta-item"><i class="bi bi-speedometer2"></i> <?= (int) round($u['taux']) ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Infos -->
        <div class="col-12 col-md-7">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-info-circle text-muted"></i>
                    <h5 class="mb-0">Informations</h5>
                </div>
                <div class="card-body">
                    <div class="prof-info-row">
                        <div class="prof-info-icon"><i class="bi bi-envelope"></i></div>
                        <div class="flex-grow-1">
                            <div class="prof-info-label">Email</div>
                            <div><?= h($u['email']) ?></div>
                        </div>
                    </div>
                    <div class="prof-info-row">
                        <div class="prof-info-icon"><i class="bi bi-telephone"></i></div>
                        <div class="flex-grow-1">
                            <div class="prof-info-label">Téléphone</div>
                            <div><?= h($u['telephone'] ?: '—') ?></div>
                        </div>
                    </div>
                    <div class="prof-info-row">
                        <div class="prof-info-icon"><i class="bi bi-briefcase"></i></div>
                        <div class="flex-grow-1">
                            <div class="prof-info-label">Fonction</div>
                            <div><?= h($u['fonction_nom'] ?: $roleLabel) ?></div>
                        </div>
                    </div>
                    <div class="prof-info-row">
                        <div class="prof-info-icon"><i class="bi bi-file-text"></i></div>
                        <div class="flex-grow-1">
                            <div class="prof-info-label">Type de contrat</div>
                            <div><?= h($u['type_contrat'] ?: '—') ?></div>
                        </div>
                    </div>
                    <div class="prof-info-row">
                        <div class="prof-info-icon"><i class="bi bi-speedometer2"></i></div>
                        <div class="flex-grow-1">
                            <div class="prof-info-label">Taux d'activité</div>
                            <div class="prof-taux"><?= (int) round($u['taux']) ?>%</div>
                        </div>
                    </div>
                    <div class="prof-info-row">
                        <div class="prof-info-icon"><i class="bi bi-building"></i></div>
                        <div class="flex-grow-1">
                            <div class="prof-info-label">Module(s)</div>
                            <div class="prof-modules">
                                <?php if (!$modules): ?>
                                    <span class="text-muted">—</span>
                                <?php else: foreach ($modules as $m): ?>
                                    <span class="prof-module-chip"><?= h($m['nom']) ?></span>
                                <?php endforeach; endif ?>
                            </div>
                        </div>
                    </div>
                    <div class="prof-info-row">
                        <div class="prof-info-icon"><i class="bi bi-sun"></i></div>
                        <div class="flex-grow-1">
                            <div class="prof-info-label">Solde vacances</div>
                            <div><strong><?= (int) ($u['solde_vacances'] ?? 0) ?></strong> jours</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Adresse personnelle (utilisée pour calculs d'itinéraire formations) -->
            <div class="card mt-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-geo-alt text-muted"></i>
                        <h5 class="mb-0">Mon adresse personnelle</h5>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" id="editAdresseBtn" type="button">
                        <i class="bi bi-pencil"></i> Modifier
                    </button>
                </div>
                <div class="card-body">
                    <div id="adresseDisplay">
                        <?php if ($u['adresse_rue']): ?>
                            <div><?= h($u['adresse_rue']) ?></div>
                            <?php if ($u['adresse_complement']): ?>
                                <div class="text-muted small"><?= h($u['adresse_complement']) ?></div>
                            <?php endif ?>
                            <div><?= h($u['adresse_cp']) ?> <?= h($u['adresse_ville']) ?></div>
                        <?php else: ?>
                            <div class="text-muted small">
                                <i class="bi bi-info-circle"></i> Aucune adresse renseignée.
                                Ajoutez-la pour calculer l'itinéraire depuis chez vous vers vos formations.
                            </div>
                        <?php endif ?>
                    </div>
                    <form id="adresseForm" class="d-none">
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Rue et numéro</label>
                            <input type="text" class="form-control form-control-sm" id="adrRue" value="<?= h($u['adresse_rue']) ?>" placeholder="Ex: Rue de Lausanne 14">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Complément <span class="text-muted">(optionnel)</span></label>
                            <input type="text" class="form-control form-control-sm" id="adrComplement" value="<?= h($u['adresse_complement']) ?>" placeholder="Étage, app, c/o…">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <label class="form-label small fw-semibold">CP</label>
                                <input type="text" class="form-control form-control-sm" id="adrCp" value="<?= h($u['adresse_cp']) ?>" placeholder="1207">
                            </div>
                            <div class="col-8">
                                <label class="form-label small fw-semibold">Ville</label>
                                <input type="text" class="form-control form-control-sm" id="adrVille" value="<?= h($u['adresse_ville']) ?>" placeholder="Genève">
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                <i class="bi bi-check-lg"></i> Enregistrer
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelAdresseBtn">
                                Annuler
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Password -->
        <div class="col-12 col-md-5">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-lock text-muted"></i>
                    <h5 class="mb-0">Changer le mot de passe</h5>
                </div>
                <div class="card-body">
                    <form id="passwordForm">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Mot de passe actuel</label>
                            <div class="pwd-field-wrap">
                                <input type="password" class="form-control form-control-sm" id="currentPassword" required autocomplete="current-password">
                                <span class="pwd-eye" data-target="currentPassword"><i class="bi bi-eye"></i></span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nouveau mot de passe</label>
                            <div class="pwd-field-wrap">
                                <input type="password" class="form-control form-control-sm" id="newPassword" required minlength="8" autocomplete="new-password">
                                <span class="pwd-eye" data-target="newPassword"><i class="bi bi-eye"></i></span>
                            </div>
                            <div id="pwdStrength" class="pwd-strength"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Confirmer</label>
                            <div class="pwd-field-wrap">
                                <input type="password" class="form-control form-control-sm" id="confirmPassword" required autocomplete="new-password">
                                <span class="pwd-eye" data-target="confirmPassword"><i class="bi bi-eye"></i></span>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-2">
                            <i class="bi bi-lock"></i> Modifier le mot de passe
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
