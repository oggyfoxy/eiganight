<?php
/*
 * login.php
 * Handles user login and authentication using reCAPTCHA v3.
 */
include_once 'config.php'; // Includes session_start(), $conn, and RECAPTCHA keys

if (isset($_SESSION['user_id'])) {
    header('Location: profile.php');
    exit;
}

$pageTitle = "Connexion - Eiganights";
$error_message = '';
$username_value = '';

$redirectAfterLogin = 'profile.php';
// ... (logique de redirection existante) ...
if (isset($_GET['redirect']) && !empty(trim($_GET['redirect']))) {
    $postedRedirectUrl = trim($_GET['redirect']);
    $urlComponents = parse_url($postedRedirectUrl);
    if (empty($urlComponents['host']) || $urlComponents['host'] === $_SERVER['HTTP_HOST']) {
        if (!preg_match('/(logout|register|login)\.php/i', $postedRedirectUrl)) {
            $redirectAfterLogin = $postedRedirectUrl;
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- reCAPTCHA v3 Verification ---
    $recaptcha_token = $_POST['g-recaptcha-response'] ?? null; // reCAPTCHA v3 token
    $recaptcha_valid = false;
    $recaptcha_action = 'login'; // L'action que vous définirez côté client

    // Utiliser les clés V3
    $recaptchaConfigured = defined('RECAPTCHA_SITE_KEY_V3') && RECAPTCHA_SITE_KEY_V3 &&
                           defined('RECAPTCHA_SECRET_KEY_V3') && RECAPTCHA_SECRET_KEY_V3;

    if ($recaptchaConfigured) {
        if (!empty($recaptcha_token)) {
            $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
            $recaptcha_secret = RECAPTCHA_SECRET_KEY_V3; // Utiliser la clé secrète V3
            $recaptcha_remote_ip = $_SERVER['REMOTE_ADDR'];

            $recaptcha_data = [
                'secret' => $recaptcha_secret,
                'response' => $recaptcha_token, // c'est le token de reCAPTCHA v3
                'remoteip' => $recaptcha_remote_ip
            ];

            $options = [ /* ... (options HTTP comme avant) ... */ 
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($recaptcha_data),
                ],
            ];
            $context = stream_context_create($options);
            $verify_response_json = @file_get_contents($recaptcha_url, false, $context);

            if ($verify_response_json === false) {
                $error_message = "Impossible de vérifier le reCAPTCHA v3. Veuillez réessayer. (LGN-RC3-00)";
                error_log("reCAPTCHA v3 verification failed (login.php): Could not connect.");
            } else {
                $verify_response_data = json_decode($verify_response_json);
                // Pour v3, vérifier success, score et action
                if ($verify_response_data && $verify_response_data->success &&
                    isset($verify_response_data->score) && $verify_response_data->score >= 0.5 && // Seuil de score, ajustez si besoin
                    isset($verify_response_data->action) && $verify_response_data->action == $recaptcha_action) {
                    $recaptcha_valid = true;
                } else {
                    $error_message = "Vérification reCAPTCHA v3 échouée ou score trop bas. Veuillez réessayer.";
                    error_log("reCAPTCHA v3 verification failed (login.php): Score: " . ($verify_response_data->score ?? 'N/A') . " Action: " . ($verify_response_data->action ?? 'N/A') . " Errors: " . implode(', ', $verify_response_data->{'error-codes'} ?? []));
                }
            }
        } else {
            $error_message = "La vérification reCAPTCHA v3 est requise mais le token est manquant.";
        }
    } else {
        error_log("AVERTISSEMENT (login.php): Les clés reCAPTCHA v3 ne sont pas configurées. Vérification ignorée.");
        $recaptcha_valid = true; // Bypass pour dev. En PROD, mettre à false.
    }
    // --- End reCAPTCHA v3 Verification ---

    if ($recaptcha_valid) {
        // ... (votre logique de connexion existante : vérification username/password) ...
        if (!isset($_POST['username'], $_POST['password']) || empty(trim($_POST['username'])) || empty($_POST['password']) ) {
            $error_message = "Nom d'utilisateur et mot de passe requis.";
        } else {
            // ... (le reste de votre code de connexion)
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $username_value = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

            $sql = "SELECT id, username, password, role, is_banned FROM users WHERE username = ?";
            $stmt = $conn->prepare($sql);

            if (!$stmt) { /* ... gestion erreur ... */ $error_message = "Erreur système (L01)"; }
            else {
                $stmt->bind_param("s", $username);
                if (!$stmt->execute()) { /* ... gestion erreur ... */ $error_message = "Erreur système (L02)";}
                else {
                    $result = $stmt->get_result();
                    if ($result->num_rows === 1) {
                        $user = $result->fetch_assoc();
                        if ($user['is_banned'] == 1) {
                           $error_message = "Votre compte a été suspendu.";
                        } elseif (password_verify($password, $user['password'])) {
                           // Connexion réussie
                           $_SESSION['user_id'] = $user['id'];
                           // ... (autres variables de session)
                           header('Location: ' . $redirectAfterLogin);
                           exit;
                        } else {
                            $error_message = "Nom d'utilisateur ou mot de passe incorrect.";
                        }
                    } else {
                        $error_message = "Nom d'utilisateur ou mot de passe incorrect.";
                    }
                }
                $stmt->close();
            }
        }
    }
     // Repopulation si reCAPTCHA échoue
    if (!$recaptcha_valid && isset($_POST['username'])) {
        $username_value = htmlspecialchars(trim($_POST['username']), ENT_QUOTES, 'UTF-8');
    }
}

$siteNameForTitle = defined('SITE_NAME') ? htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') : 'Eiganights';
$fullPageTitle = htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . ' - ' . $siteNameForTitle;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Connectez-vous à Eiganights.">
    <title><?php echo $fullPageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL . 'assets/style.css', ENT_QUOTES, 'UTF-8'); ?>" />
    <?php
    // Script reCAPTCHA v3 pour la page de connexion
    if (defined('RECAPTCHA_SITE_KEY_V3') && RECAPTCHA_SITE_KEY_V3):
    ?>
        <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY_V3, ENT_QUOTES, 'UTF-8'); ?>"></script>
        <script>
        function onSubmitLoginForm(event) {
            event.preventDefault(); // Empêche la soumission normale du formulaire
            grecaptcha.ready(function() {
                grecaptcha.execute('<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY_V3, ENT_QUOTES, 'UTF-8'); ?>', {action: 'login'}).then(function(token) {
                    // Ajoute le token au formulaire comme un champ caché
                    var form = document.getElementById('loginForm');
                    var hiddenInput = document.createElement('input');
                    hiddenInput.setAttribute('type', 'hidden');
                    hiddenInput.setAttribute('name', 'g-recaptcha-response');
                    hiddenInput.setAttribute('value', token);
                    form.appendChild(hiddenInput);
                    
                    // Soumet le formulaire
                    form.submit();
                });
            });
        }
        </script>
    <?php endif; ?>
</head>
<body>

<?php
// Réplication manuelle du header
$logoutText = "Déconnexion";
$headerSearchQuery = isset($_GET['search']) ? htmlspecialchars(trim($_GET['search']), ENT_QUOTES, 'UTF-8') : '';
$logoPath = BASE_URL . 'assets/images/eiganights_logov2.png';
?>
<header class="site-header">
    <div class="header-container container">
        <div class="site-branding">
             <a href="<?php echo BASE_URL; ?>index.php" class="site-logo-link" aria-label="Page d'accueil <?php echo $siteNameForTitle; ?>">
                <img src="<?php echo htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo icône <?php echo $siteNameForTitle; ?>" class="site-logo-image">
                <span class="site-title-header"><?php echo $siteNameForTitle; ?></span>
             </a>
        </div>
        <nav class="main-navigation" aria-label="Navigation principale">
            <ul>
                <li><a href="<?php echo BASE_URL; ?>index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Accueil</a></li>
                <li><a href="<?php echo BASE_URL; ?>forum.php" class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['forum.php', 'forum_view_thread.php', 'forum_create_thread.php']) ? 'active' : ''; ?>">Forum</a></li>
                <li><a href="<?php echo BASE_URL; ?>users_list.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users_list.php' ? 'active' : ''; ?>">Utilisateurs</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php
                        $messages_active_pages = ['messages.php', 'message_start_conversation.php', 'message_view_conversation.php'];
                        $is_messages_active = in_array(basename($_SERVER['PHP_SELF']), $messages_active_pages);
                    ?>
                    <li><a href="<?php echo BASE_URL; ?>messages.php" class="nav-link <?php echo $is_messages_active ? 'active' : ''; ?>">Messages</a></li>
                     <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <li><a href="<?php echo BASE_URL; ?>admin_panel.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_panel.php' ? 'active' : ''; ?>">Admin</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo BASE_URL; ?>profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">Mon Profil</a></li>
                    <li><a href="<?php echo BASE_URL; ?>logout.php" class="nav-link"><?php echo htmlspecialchars($logoutText, ENT_QUOTES, 'UTF-8'); ?></a></li>
                <?php else: ?>
                    <li><a href="<?php echo BASE_URL; ?>login.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'active' : ''; ?>">Connexion</a></li>
                    <li><a href="<?php echo BASE_URL; ?>register.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'register.php' ? 'active' : ''; ?>">Inscription</a></li>
                <?php endif; ?>
            </ul>
        </nav>
         <div class="header-search-bar">
            <form method="GET" action="<?php echo BASE_URL; ?>index.php" class="search-form-header" role="search">
                <label for="header-search-input" class="visually-hidden">Rechercher un film</label>
                <input type="text" id="header-search-input" name="search" placeholder="Rechercher un film..." value="<?php echo $headerSearchQuery; ?>" aria-label="Champ de recherche de film" />
                <button type="submit" class="search-button-header" aria-label="Lancer la recherche">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                        <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</header>

<div class="container page-content">
    <main class="container auth-form-container">
        <h1>Connexion</h1>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php /* ... autres messages de session ... */ ?>
         <?php if (!empty($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
         <?php if (!empty($_SESSION['login_required_message'])): ?>
            <div class="alert alert-info">
                <?php echo htmlspecialchars($_SESSION['login_required_message']); unset($_SESSION['login_required_message']); ?>
            </div>
        <?php endif; ?>


        <form id="loginForm" method="POST" action="login.php<?php echo $redirectAfterLogin !== 'profile.php' ? '?redirect=' . urlencode($redirectAfterLogin) : ''; ?>" novalidate
            <?php if (defined('RECAPTCHA_SITE_KEY_V3') && RECAPTCHA_SITE_KEY_V3): ?>
                onsubmit="onSubmitLoginForm(event)"
            <?php endif; ?>
        >
            <div class="form-group">
                <label for="username">Nom d'utilisateur:</label>
                <input type="text" id="username" name="username" placeholder="Votre nom d'utilisateur" value="<?php echo $username_value; ?>" required autofocus />
            </div>
            <div class="form-group">
                <label for="password">Mot de passe:</label>
                <input type="password" id="password" name="password" placeholder="Votre mot de passe" required />
            </div>
            
            <?php if (!defined('RECAPTCHA_SITE_KEY_V3') || !RECAPTCHA_SITE_KEY_V3): ?>
            <div class="form-group">
                <p class="alert alert-warning">reCAPTCHA v3 n'est pas configuré. La soumission du formulaire pourrait ne pas être protégée.</p>
            </div>
            <?php endif; ?>
            <!-- reCAPTCHA v3 n'a pas de widget visible ici. Il est déclenché par JS. -->
            <!-- Un champ g-recaptcha-response sera ajouté dynamiquement par le JS. -->

            <div class="form-group">
                <input type="submit" value="Se connecter" class="button-primary" />
            </div>
        </form>
        <p class="auth-links">
            Pas encore de compte ? <a href="register.php<?php echo $redirectAfterLogin !== 'profile.php' ? '?redirect=' . urlencode($redirectAfterLogin) : ''; ?>">Inscrivez-vous ici</a>.<br>
            <a href="forgot_password.php">Mot de passe oublié ?</a>
        </p>
    </main>
</div>
<?php
include_once 'includes/footer.php';
?>
</body>
</html>