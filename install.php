<?php
/**
 * zerdaTime — Installation Wizard
 *
 * Steps:
 *   1. Prerequisites check (PHP version, extensions, writable dirs)
 *   2. Database configuration + connection test
 *   3. Run migrations (create all tables)
 *   4. EMS info (name, address, type)
 *   5. Create admin account
 *   6. Lock installer
 */

// Prevent access if already installed
$lockFile = __DIR__ . '/storage/.installed';
if (file_exists($lockFile)) {
    header('Location: /zerdatime/login');
    exit;
}

$step = intval($_GET['step'] ?? 1);
$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'test_db') {
        $host = trim($_POST['db_host'] ?? '');
        $name = trim($_POST['db_name'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = $_POST['db_pass'] ?? '';
        $port = intval($_POST['db_port'] ?? 3306);

        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $version = $pdo->query("SELECT VERSION()")->fetchColumn();

            // Save config to .env.local
            $envContent = "DB_HOST=$host\nDB_PORT=$port\nDB_NAME=$name\nDB_USER=$user\nDB_PASS=$pass\n";
            file_put_contents(__DIR__ . '/.env.local', $envContent);
            chmod(__DIR__ . '/.env.local', 0600);

            $success = "Connexion réussie (MySQL $version). Configuration sauvegardée.";
            $step = 3;
        } catch (PDOException $e) {
            $error = 'Erreur de connexion : ' . $e->getMessage();
            $step = 2;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => empty($error), 'error' => $error, 'message' => $success, 'step' => $step]);
        exit;
    }

    if ($action === 'run_migrations') {
        try {
            // Load config to connect
            if (file_exists(__DIR__ . '/.env.local')) {
                $lines = file(__DIR__ . '/.env.local', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false) putenv(trim($line));
                }
            }

            $dsn = "mysql:host=" . getenv('DB_HOST') . ";port=" . (getenv('DB_PORT') ?: 3306) . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4";
            $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $migDir = __DIR__ . '/migrations/';
            $files = glob($migDir . '*.sql');
            sort($files);
            $ran = 0;

            foreach ($files as $file) {
                $sql = file_get_contents($file);
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt)) {
                        $pdo->exec($stmt);
                    }
                }
                $ran++;
            }

            // Run PHP migrations
            $phpFiles = glob($migDir . '*.php');
            sort($phpFiles);
            foreach ($phpFiles as $file) {
                include $file;
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => "$ran migration(s) exécutée(s)"]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'save_ems') {
        try {
            require_once __DIR__ . '/init_minimal.php';

            $fields = [
                'ems_nom' => trim($_POST['ems_nom'] ?? ''),
                'ems_adresse' => trim($_POST['ems_adresse'] ?? ''),
                'ems_npa' => trim($_POST['ems_npa'] ?? ''),
                'ems_ville' => trim($_POST['ems_ville'] ?? ''),
                'ems_canton' => trim($_POST['ems_canton'] ?? ''),
                'ems_telephone' => trim($_POST['ems_telephone'] ?? ''),
                'ems_email' => trim($_POST['ems_email'] ?? ''),
                'ems_type' => trim($_POST['ems_type'] ?? ''),
                'ems_nb_lits' => intval($_POST['ems_nb_lits'] ?? 0),
            ];

            foreach ($fields as $key => $value) {
                if (empty($value) && $key === 'ems_nom') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Nom de l\'établissement requis']);
                    exit;
                }
                Db::exec(
                    "INSERT INTO ems_config (config_key, config_value) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)",
                    [$key, (string)$value]
                );
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'create_admin') {
        try {
            require_once __DIR__ . '/init_minimal.php';

            $prenom = trim($_POST['admin_prenom'] ?? '');
            $nom = trim($_POST['admin_nom'] ?? '');
            $email = trim($_POST['admin_email'] ?? '');
            $password = $_POST['admin_password'] ?? '';

            if (!$prenom || !$nom || !$email || !$password) {
                throw new Exception('Tous les champs sont requis');
            }
            if (strlen($password) < 8) {
                throw new Exception('Le mot de passe doit faire au moins 8 caractères');
            }

            $id = Uuid::v4();
            $hash = password_hash($password, PASSWORD_DEFAULT);

            Db::exec(
                "INSERT INTO users (id, prenom, nom, email, password, role, is_active) VALUES (?, ?, ?, ?, ?, 'admin', 1)",
                [$id, $prenom, $nom, $email, $hash]
            );

            // Lock installation
            file_put_contents($lockFile, date('Y-m-d H:i:s') . "\nInstalled by: $email\n");
            chmod($lockFile, 0600);

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Prerequisites check
$checks = [
    'PHP ≥ 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'OpenSSL' => extension_loaded('openssl'),
    'GD (images)' => extension_loaded('gd'),
    'cURL' => extension_loaded('curl'),
    'mbstring' => extension_loaded('mbstring'),
    'JSON' => extension_loaded('json'),
    'storage/ writable' => is_writable(__DIR__ . '/storage') || @mkdir(__DIR__ . '/storage', 0755, true),
    'config/ writable' => is_writable(__DIR__ . '/config'),
];
$allPassed = !in_array(false, $checks, true);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>zerdaTime — Installation</title>
<link rel="stylesheet" href="/zerdatime/admin/assets/css/vendor/bootstrap-icons.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:#F7F5F2; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem; }
.installer { background:#fff; border-radius:2rem; box-shadow:0 4px 24px rgba(0,0,0,.06); max-width:620px; width:100%; overflow:hidden; border:1px solid #E8E5E0; }
.installer-header { background:#1A1A1A; color:#fff; padding:2rem 2rem 2.5rem; text-align:center; }
.installer-header img { height:40px; margin-bottom:10px; }
.installer-header h1 { font-size:1.4rem; font-weight:700; margin-bottom:0.2rem; letter-spacing:-.02em; }
.installer-header p { opacity:.6; font-size:.82rem; }
.steps { display:flex; gap:0; padding:0 2rem; margin-top:-18px; justify-content:center; }
.step-dot { width:36px; height:36px; border-radius:50%; background:#E8E5E0; color:#9B9B9B; display:flex; align-items:center; justify-content:center; font-weight:600; font-size:.8rem; transition:all .3s; border:2px solid #fff; }
.step-dot.active { background:#1A1A1A; color:#fff; transform:scale(1.15); }
.step-dot.done { background:#bcd2cb; color:#2d4a43; }
.step-line { width:40px; height:2px; background:#E8E5E0; align-self:center; }
.installer-body { padding:2rem; }
.form-group { margin-bottom:1rem; }
.form-group label { display:block; font-weight:500; font-size:.85rem; margin-bottom:4px; color:#1A1A1A; }
.form-group input, .form-group select { width:100%; padding:0.55rem 0.75rem; border:1px solid #E8E5E0; border-radius:10px; font-size:.9rem; font-family:inherit; background:#FAFAF8; transition:all .2s; }
.form-group input:focus, .form-group select:focus { outline:none; border-color:#1A1A1A; box-shadow:0 0 0 3px rgba(26,26,26,.06); background:#fff; }
.btn { padding:0.6rem 1.5rem; border:none; border-radius:10px; font-weight:600; font-size:.9rem; cursor:pointer; transition:all .15s; display:inline-flex; align-items:center; gap:0.4rem; }
.btn-primary { background:#1A1A1A; color:#fff; }
.btn-primary:hover { background:#000; }
.btn-secondary { background:#F0EDE8; color:#1A1A1A; }
.btn-secondary:hover { background:#E8E5E0; }
.check-list { list-style:none; margin:1rem 0; }
.check-list li { padding:0.5rem 0.75rem; display:flex; align-items:center; gap:0.6rem; font-size:.9rem; border-radius:8px; }
.check-list li:nth-child(odd) { background:#FAFAF8; }
.check-list .bi-check-circle-fill { color:#2d4a43; }
.check-list .bi-x-circle-fill { color:#7B3B2C; }
.alert { padding:0.75rem 1rem; border-radius:10px; font-size:.85rem; margin-bottom:1rem; }
.alert-danger { background:#E2B8AE; color:#7B3B2C; }
.alert-success { background:#bcd2cb; color:#2d4a43; }
.row { display:flex; gap:1rem; }
.row > * { flex:1; }
.installer-footer { padding:1rem 2rem; display:flex; justify-content:space-between; border-top:1px solid #F0EDE8; }
#stepContent { min-height:200px; }
#stepContent h5 { font-weight:700; color:#1A1A1A; }
</style>
</head>
<body>

<div class="installer">
  <div class="installer-header">
    <img src="/zerdatime/logo.png" alt="zerdaTime">
    <h1>zerdaTime</h1>
    <p>Assistant d'installation</p>
  </div>

  <div style="padding:1.5rem 2rem 0">
    <div class="steps">
      <div class="step-dot" data-step="1">1</div>
      <div class="step-line"></div>
      <div class="step-dot" data-step="2">2</div>
      <div class="step-line"></div>
      <div class="step-dot" data-step="3">3</div>
      <div class="step-line"></div>
      <div class="step-dot" data-step="4">4</div>
      <div class="step-line"></div>
      <div class="step-dot" data-step="5">5</div>
    </div>
  </div>

  <div class="installer-body" id="stepContent"></div>

  <div class="installer-footer">
    <button class="btn btn-secondary" id="prevBtn" style="visibility:hidden"><i class="bi bi-arrow-left"></i> Précédent</button>
    <button class="btn btn-primary" id="nextBtn">Suivant <i class="bi bi-arrow-right"></i></button>
  </div>
</div>

<script>
let currentStep = 1;
const checks = <?= json_encode($checks) ?>;
const allPassed = <?= $allPassed ? 'true' : 'false' ?>;

function showStep(step) {
    currentStep = step;
    const content = document.getElementById('stepContent');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    // Update dots
    document.querySelectorAll('.step-dot').forEach(d => {
        const s = parseInt(d.dataset.step);
        d.className = 'step-dot' + (s === step ? ' active' : s < step ? ' done' : '');
    });

    prevBtn.style.visibility = step > 1 ? 'visible' : 'hidden';

    if (step === 1) {
        content.innerHTML = `
            <h5 style="margin-bottom:1rem"><i class="bi bi-gear-wide-connected"></i> Prérequis système</h5>
            <ul class="check-list">
                ${Object.entries(checks).map(([label, ok]) =>
                    `<li><i class="bi ${ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill'}"></i> ${label}</li>`
                ).join('')}
            </ul>
            ${!allPassed ? '<div class="alert alert-danger">Certains prérequis ne sont pas remplis. Corrigez-les avant de continuer.</div>' : '<div class="alert alert-success">Tous les prérequis sont remplis !</div>'}
        `;
        nextBtn.disabled = !allPassed;
        nextBtn.textContent = 'Suivant';
    }

    if (step === 2) {
        content.innerHTML = `
            <h5 style="margin-bottom:1rem"><i class="bi bi-database"></i> Base de données</h5>
            <div class="row">
                <div class="form-group"><label>Hôte</label><input id="dbHost" value="localhost"></div>
                <div class="form-group"><label>Port</label><input id="dbPort" value="3306" type="number"></div>
            </div>
            <div class="form-group"><label>Nom de la base</label><input id="dbName" placeholder="zerdatime_ems"></div>
            <div class="form-group"><label>Utilisateur</label><input id="dbUser"></div>
            <div class="form-group"><label>Mot de passe</label><input id="dbPass" type="password"></div>
            <div id="dbResult"></div>
        `;
        nextBtn.innerHTML = '<i class="bi bi-plug"></i> Tester la connexion';
    }

    if (step === 3) {
        content.innerHTML = `
            <h5 style="margin-bottom:1rem"><i class="bi bi-database-gear"></i> Création des tables</h5>
            <p style="margin-bottom:1rem;color:#666;font-size:.9rem">Cliquez sur "Installer" pour créer toutes les tables nécessaires.</p>
            <div id="migResult"></div>
        `;
        nextBtn.innerHTML = '<i class="bi bi-lightning"></i> Installer les tables';
    }

    if (step === 4) {
        content.innerHTML = `
            <h5 style="margin-bottom:1rem"><i class="bi bi-hospital"></i> Informations de l'établissement</h5>
            <div class="form-group"><label>Nom de l'établissement *</label><input id="emsNom" placeholder="EMS La Résidence"></div>
            <div class="row">
                <div class="form-group"><label>Adresse</label><input id="emsAdresse"></div>
            </div>
            <div class="row">
                <div class="form-group"><label>NPA</label><input id="emsNpa" placeholder="1200"></div>
                <div class="form-group"><label>Ville</label><input id="emsVille" placeholder="Genève"></div>
                <div class="form-group"><label>Canton</label><input id="emsCanton" placeholder="GE"></div>
            </div>
            <div class="row">
                <div class="form-group"><label>Téléphone</label><input id="emsTel"></div>
                <div class="form-group"><label>Email</label><input id="emsEmail" type="email"></div>
            </div>
            <div class="row">
                <div class="form-group">
                    <label>Type d'établissement</label>
                    <select id="emsType">
                        <option value="ems">EMS</option>
                        <option value="hopital">Hôpital</option>
                        <option value="clinique">Clinique</option>
                        <option value="centre_soin">Centre de soins</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
                <div class="form-group"><label>Nombre de lits</label><input id="emsLits" type="number" min="0"></div>
            </div>
            <div id="emsResult"></div>
        `;
        nextBtn.innerHTML = 'Suivant <i class="bi bi-arrow-right"></i>';
    }

    if (step === 5) {
        content.innerHTML = `
            <h5 style="margin-bottom:1rem"><i class="bi bi-person-badge"></i> Compte administrateur</h5>
            <div class="row">
                <div class="form-group"><label>Prénom *</label><input id="adminPrenom"></div>
                <div class="form-group"><label>Nom *</label><input id="adminNom"></div>
            </div>
            <div class="form-group"><label>Email *</label><input id="adminEmail" type="email"></div>
            <div class="form-group"><label>Mot de passe * (min 8 caractères)</label><input id="adminPass" type="password"></div>
            <div id="adminResult"></div>
        `;
        nextBtn.innerHTML = '<i class="bi bi-check-lg"></i> Terminer l\'installation';
    }

    if (step === 6) {
        content.innerHTML = `
            <div style="text-align:center;padding:2rem 0">
                <div style="width:64px;height:64px;border-radius:50%;background:#bcd2cb;color:#2d4a43;display:inline-flex;align-items:center;justify-content:center;font-size:1.8rem;margin-bottom:1rem"><i class="bi bi-check-lg"></i></div>
                <h4 style="margin-bottom:0.5rem;font-weight:700">Installation terminée</h4>
                <p style="color:#6B6B6B;margin-bottom:1.5rem">zerdaTime est prêt à être utilisé.</p>
                <a href="/zerdatime/login" class="btn btn-primary" style="text-decoration:none"><i class="bi bi-box-arrow-in-right"></i> Se connecter</a>
            </div>
        `;
        prevBtn.style.visibility = 'hidden';
        nextBtn.style.display = 'none';
    }
}

document.getElementById('nextBtn').addEventListener('click', async () => {
    const btn = document.getElementById('nextBtn');

    if (currentStep === 1) { showStep(2); return; }

    if (currentStep === 2) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:14px;height:14px"></span> Test...';
        const fd = new FormData();
        fd.append('action', 'test_db');
        fd.append('db_host', document.getElementById('dbHost').value);
        fd.append('db_port', document.getElementById('dbPort').value);
        fd.append('db_name', document.getElementById('dbName').value);
        fd.append('db_user', document.getElementById('dbUser').value);
        fd.append('db_pass', document.getElementById('dbPass').value);
        const res = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
        btn.disabled = false;
        const div = document.getElementById('dbResult');
        if (res.success) {
            div.innerHTML = `<div class="alert alert-success">${res.message}</div>`;
            setTimeout(() => showStep(3), 800);
        } else {
            div.innerHTML = `<div class="alert alert-danger">${res.error}</div>`;
            btn.innerHTML = '<i class="bi bi-plug"></i> Tester la connexion';
        }
        return;
    }

    if (currentStep === 3) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:14px;height:14px"></span> Installation...';
        const fd = new FormData();
        fd.append('action', 'run_migrations');
        const res = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
        btn.disabled = false;
        const div = document.getElementById('migResult');
        if (res.success) {
            div.innerHTML = `<div class="alert alert-success">${res.message}</div>`;
            setTimeout(() => showStep(4), 800);
        } else {
            div.innerHTML = `<div class="alert alert-danger">${res.error}</div>`;
            btn.innerHTML = '<i class="bi bi-lightning"></i> Installer les tables';
        }
        return;
    }

    if (currentStep === 4) {
        const nom = document.getElementById('emsNom').value.trim();
        if (!nom) { document.getElementById('emsResult').innerHTML = '<div class="alert alert-danger">Nom requis</div>'; return; }
        btn.disabled = true;
        const fd = new FormData();
        fd.append('action', 'save_ems');
        fd.append('ems_nom', nom);
        fd.append('ems_adresse', document.getElementById('emsAdresse').value);
        fd.append('ems_npa', document.getElementById('emsNpa').value);
        fd.append('ems_ville', document.getElementById('emsVille').value);
        fd.append('ems_canton', document.getElementById('emsCanton').value);
        fd.append('ems_telephone', document.getElementById('emsTel').value);
        fd.append('ems_email', document.getElementById('emsEmail').value);
        fd.append('ems_type', document.getElementById('emsType').value);
        fd.append('ems_nb_lits', document.getElementById('emsLits').value);
        const res = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
        btn.disabled = false;
        if (res.success) { showStep(5); }
        else { document.getElementById('emsResult').innerHTML = `<div class="alert alert-danger">${res.error}</div>`; }
        return;
    }

    if (currentStep === 5) {
        const prenom = document.getElementById('adminPrenom').value.trim();
        const nom = document.getElementById('adminNom').value.trim();
        const email = document.getElementById('adminEmail').value.trim();
        const pass = document.getElementById('adminPass').value;
        if (!prenom || !nom || !email || !pass) {
            document.getElementById('adminResult').innerHTML = '<div class="alert alert-danger">Tous les champs sont requis</div>';
            return;
        }
        if (pass.length < 8) {
            document.getElementById('adminResult').innerHTML = '<div class="alert alert-danger">Mot de passe trop court (min 8)</div>';
            return;
        }
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:14px;height:14px"></span> Finalisation...';
        const fd = new FormData();
        fd.append('action', 'create_admin');
        fd.append('admin_prenom', prenom);
        fd.append('admin_nom', nom);
        fd.append('admin_email', email);
        fd.append('admin_password', pass);
        const res = await fetch('', { method: 'POST', body: fd }).then(r => r.json());
        btn.disabled = false;
        if (res.success) { showStep(6); }
        else {
            document.getElementById('adminResult').innerHTML = `<div class="alert alert-danger">${res.error}</div>`;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Terminer l\'installation';
        }
        return;
    }
});

document.getElementById('prevBtn').addEventListener('click', () => {
    if (currentStep > 1) showStep(currentStep - 1);
});

showStep(1);
</script>
</body>
</html>
