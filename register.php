<?php
/*
 * register.php
 * Handles new user registration.
 */
include_once 'config.php'; // Includes session_start(), $conn, and RECAPTCHA keys

// If user is already logged in, redirect them to their profile page
if (isset($_SESSION['user_id'])) {
    header('Location: profile.php');
    exit;
}

$pageTitle = "Inscription - Eiganights";
$error_message = '';
$username_value = '';
$email_value = '';

$redirectAfterRegister = 'profile.php';
if (isset($_GET['redirect']) && !empty(trim($_GET['redirect']))) {
    $postedRedirectUrl = trim($_GET['redirect']);
    $urlComponents = parse_url($postedRedirectUrl);
    if ((empty($urlComponents['host']) || $urlComponents['host'] === $_SERVER['HTTP_HOST']) &&
        !preg_match('/(logout|register|login)\.php/i', $postedRedirectUrl)) {
        $redirectAfterRegister = $postedRedirectUrl;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- reCAPTCHA v2 Verification ---
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? null;
    $recaptcha_valid = false;

    // Utiliser les clés V2
    $recaptchaConfigured = defined('RECAPTCHA_SITE_KEY_V2') && RECAPTCHA_SITE_KEY_V2 &&
                           defined('RECAPTCHA_SECRET_KEY_V2') && RECAPTCHA_SECRET_KEY_V2;

    if ($recaptchaConfigured) {
        if (!empty($recaptcha_response)) {
            $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
            $recaptcha_secret = RECAPTCHA_SECRET_KEY_V2; // Utiliser la clé secrète V2
            // ... (le reste de la logique de vérification reCAPTCHA v2 reste la même) ...
            $recaptcha_remote_ip = $_SERVER['REMOTE_ADDR'];
            $recaptcha_data = ['secret' => $recaptcha_secret, 'response' => $recaptcha_response, 'remoteip' => $recaptcha_remote_ip];
            $options = ['http' => ['header' => "Content-type: application/x-www-form-urlencoded\r\n", 'method' => 'POST', 'content' => http_build_query($recaptcha_data)]];
            $context = stream_context_create($options);
            $verify_response_json = @file_get_contents($recaptcha_url, false, $context);

            if ($verify_response_json === false) {
                $error_message = "Impossible de vérifier le reCAPTCHA. Veuillez réessayer. (REG-RC00)";
                error_log("reCAPTCHA v2 verification failed (register.php): Could not connect to Google service.");
            } else {
                $verify_response_data = json_decode($verify_response_json);
                if ($verify_response_data && $verify_response_data->success) {
                    $recaptcha_valid = true;
                } else {
                    $error_message = "Vérification reCAPTCHA v2 échouée. Veuillez réessayer.";
                    $error_codes = $verify_response_data->{'error-codes'} ?? [];
                    error_log("reCAPTCHA v2 verification failed (register.php): " . implode(', ', $error_codes));
                }
            }
        } else {
            $error_message = "Veuillez compléter la vérification reCAPTCHA v2.";
        }
    } else {
        error_log("AVERTISSEMENT (register.php): Les clés reCAPTCHA v2 ne sont pas configurées. Vérification ignorée.");
        $recaptcha_valid = true; // Bypass pour dev. En PROD, mettre à false et gérer l'erreur.
    }
    // --- End reCAPTCHA v2 Verification ---

    if ($recaptcha_valid) {
        // ... (votre logique d'inscription existante à partir d'ici) ...
        // S'assurer que la repopulation des champs $username_value et $email_value est correcte
        if (!isset($_POST['username'], $_POST['email'], $_POST['password'], $_POST['password_confirm']) ||
            empty(trim($_POST['username'])) || empty(trim($_POST['email'])) ||
            empty($_POST['password']) || empty($_POST['password_confirm'])) {
            $error_message = "Tous les champs sont requis.";
        } else {
            // ... (le reste de votre code de validation et d'insertion utilisateur)
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];

            $username_value = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
            $email_value = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');

            if (strlen($username) < 3 || strlen($username) > 50) {
                $error_message = "Le nom d'utilisateur doit contenir entre 3 et 50 caractères.";
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                $error_message = "Le nom d'utilisateur ne peut contenir que des lettres, des chiffres et des underscores (_).";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = "Veuillez fournir une adresse e-mail valide.";
            } elseif (strlen($password) < 6) {
                $error_message = "Le mot de passe doit contenir au moins 6 caractères.";
            } elseif ($password !== $password_confirm) {
                $error_message = "Les mots de passe ne correspondent pas.";
            }

            if (empty($error_message)) {
                // Vérification utilisateur existant
                $sqlCheckUser = "SELECT id FROM users WHERE username = ?";
                $stmtCheckUser = $conn->prepare($sqlCheckUser);
                if ($stmtCheckUser) {
                    $stmtCheckUser->bind_param("s", $username);
                    if ($stmtCheckUser->execute()) {
                        $stmtCheckUser->store_result();
                        if ($stmtCheckUser->num_rows > 0) {
                            $error_message = "Ce nom d'utilisateur est déjà pris.";
                        }
                    } else { $error_message = "Erreur système (R02U)."; error_log("Execute user check failed: ".$stmtCheckUser->error); }
                    $stmtCheckUser->close();
                } else { $error_message = "Erreur système (R01U)."; error_log("Prepare user check failed: ".$conn->error); }

                // Vérification email existant (si pas d'erreur précédente)
                if (empty($error_message)) {
                    $sqlCheckEmail = "SELECT id FROM users WHERE email = ?";
                    $stmtCheckEmail = $conn->prepare($sqlCheckEmail);
                    if ($stmtCheckEmail) {
                        $stmtCheckEmail->bind_param("s", $email);
                        if ($stmtCheckEmail->execute()) {
                            $stmtCheckEmail->store_result();
                            if ($stmtCheckEmail->num_rows > 0) {
                                $error_message = "Cette adresse e-mail est déjà utilisée.";
                            }
                        } else { $error_message = "Erreur système (R02E)."; error_log("Execute email check failed: ".$stmtCheckEmail->error); }
                        $stmtCheckEmail->close();
                    } else { $error_message = "Erreur système (R01E)."; error_log("Prepare email check failed: ".$conn->error); }
                }
                
                // Insertion si tout est OK
                if (empty($error_message)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    if ($hashedPassword === false) {
                         $error_message = "Erreur critique (R03)."; error_log("password_hash() failed.");
                    } else {
                        $sqlInsert = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
                        $stmtInsert = $conn->prepare($sqlInsert);
                        if ($stmtInsert) {
                            $stmtInsert->bind_param("sss", $username, $email, $hashedPassword);
                            if ($stmtInsert->execute()) {
                                $newUserId = $conn->insert_id;
                                $_SESSION['user_id'] = $newUserId;
                                $_SESSION['username'] = $username;
                                $_SESSION['role'] = 'user';
                                session_regenerate_id(true);
                                $_SESSION['message'] = "Inscription réussie ! Bienvenue, " . htmlspecialchars($username) . ".";
                                header('Location: ' . $redirectAfterRegister);
                                exit;
                            } else { $error_message = "Erreur création compte (R05)."; error_log("Execute insert failed: ".$stmtInsert->error); }
                            $stmtInsert->close();
                        } else { $error_message = "Erreur système (R04)."; error_log("Prepare insert failed: ".$conn->error); }
                    }
                }
            }
        }
    }
    // Repopulation des champs si reCAPTCHA échoue ou autre erreur POST
    $username_value = isset($_POST['username']) ? htmlspecialchars(trim($_POST['username']), ENT_QUOTES, 'UTF-8') : $username_value;
    $email_value = isset($_POST['email']) ? htmlspecialchars(trim($_POST['email']), ENT_QUOTES, 'UTF-8') : $email_value;
}

$siteNameForTitle = defined('SITE_NAME') ? htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') : 'Eiganights';
$fullPageTitle = htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . ' - ' . $siteNameForTitle;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Inscrivez-vous sur Eiganights pour découvrir, noter et discuter de films.">
    <title><?php echo $fullPageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(BASE_URL . 'assets/style.css', ENT_QUOTES, 'UTF-8'); ?>" />
    <?php
    // Script reCAPTCHA v2 pour la page d'inscription
    if (defined('RECAPTCHA_SITE_KEY_V2') && RECAPTCHA_SITE_KEY_V2):
    ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
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
        <h1>Inscription</h1>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php<?php echo $redirectAfterRegister !== 'profile.php' ? '?redirect=' . urlencode($redirectAfterRegister) : ''; ?>" novalidate>
            <div class="form-group">
                <label for="username">Nom d'utilisateur:</label>
                <input type="text" id="username" name="username" placeholder="Choisissez un nom d'utilisateur" 
                       value="<?php echo $username_value; ?>" required 
                       pattern="^[a-zA-Z0-9_]{3,50}$" 
                       title="3-50 caractères. Lettres, chiffres, et underscores uniquement." 
                       autofocus />
                <small class="form-text">3-50 caractères. Lettres, chiffres, et underscores (_) uniquement.</small>
            </div>
            
            <div class="form-group">
                <label for="email">E-mail:</label>
                <input type="email" id="email" name="email" placeholder="Votre adresse e-mail" 
                    value="<?php echo $email_value; ?>" required />
            </div>

            <div class="form-group">
                <label for="password">Mot de passe:</label>
                <input type="password" id="password" name="password" placeholder="Créez un mot de passe" required 
                       minlength="6" title="Au moins 6 caractères." />
                <small class="form-text">Au moins 6 caractères.</small>
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirmer le mot de passe:</label>
                <input type="password" id="password_confirm" name="password_confirm" placeholder="Retapez votre mot de passe" required />
            </div>

            <?php
            // Widget reCAPTCHA v2 pour la page d'inscription
            if (defined('RECAPTCHA_SITE_KEY_V2') && RECAPTCHA_SITE_KEY_V2):
            ?>
            <div class="form-group" style="display: flex; justify-content: center; margin-top: var(--spacing-md); margin-bottom: var(--spacing-md);">
                <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY_V2, ENT_QUOTES, 'UTF-8'); ?>"></div>
            </div>
            <?php elseif (!defined('RECAPTCHA_SITE_KEY_V2') || !RECAPTCHA_SITE_KEY_V2): ?>
            <div class="form-group">
                <p class="alert alert-warning">reCAPTCHA v2 n'est pas configuré. Veuillez vérifier les clés API dans `config.php`.</p>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <input type="submit" value="S'inscrire" class="button-primary" />
            </div>
        </form>
        <p class="auth-links">
            Déjà un compte ? <a href="login.php<?php echo $redirectAfterRegister !== 'profile.php' ? '?redirect=' . urlencode($redirectAfterRegister) : ''; ?>">Connectez-vous ici</a>.
        </p>
    </main>

</div>
<?php
include_once 'includes/footer.php';
?>
</body>
</html>