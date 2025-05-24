<?php
/*
 * login.php
 * Handles user login and authentication.
 */
include_once 'config.php'; // Includes session_start(), $conn, TMDB_API_KEY

// If user is already logged in, redirect them to their profile page
if (isset($_SESSION['user_id'])) {
    header('Location: profile.php');
    exit;
}

$pageTitle = "Connexion - Eiganights";
$error_message = ''; // For displaying login errors to the user
$username_value = ''; // To repopulate username field on error

// Determine redirect URL after successful login
$redirectAfterLogin = 'profile.php'; // Default redirect
if (isset($_GET['redirect']) && !empty(trim($_GET['redirect']))) {
    $postedRedirectUrl = trim($_GET['redirect']);
    $urlComponents = parse_url($postedRedirectUrl);
    // Allow only relative paths or paths on the same host (simple validation)
    if (empty($urlComponents['host']) || $urlComponents['host'] === $_SERVER['HTTP_HOST']) {
        // Further validation: ensure it's not pointing to logout.php or other sensitive scripts directly
        if (!preg_match('/(logout|register|login)\.php/i', $postedRedirectUrl)) {
            $redirectAfterLogin = $postedRedirectUrl;
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate that username and password are provided
    if (!isset($_POST['username'], $_POST['password']) || 
        empty(trim($_POST['username'])) || 
        empty($_POST['password']) ) { // Password can't be just spaces
        
        $error_message = "Nom d'utilisateur et mot de passe requis.";
        // Repopulate username if submitted
        if(isset($_POST['username'])) {
            $username_value = htmlspecialchars(trim($_POST['username']), ENT_QUOTES, 'UTF-8');
        }
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password']; // Do not trim password, spaces can be part of it

        // Store username for repopulation in case of error
        $username_value = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

        // Prepare SQL to prevent SQL injection (even though we only fetch)
        $sql = "SELECT id, username, password, role, is_banned FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            error_log("Prepare failed for login: (" . $conn->errno . ") " . $conn->error . " (Code: LGN_PREP)");
            $error_message = "Une erreur système est survenue. Veuillez réessayer. (L01)";
        } else {
            $stmt->bind_param("s", $username);
            if (!$stmt->execute()) {
                error_log("Execute failed for login: (" . $stmt->errno . ") " . $stmt->error . " (Code: LGN_EXEC)");
                $error_message = "Une erreur système est survenue. Veuillez réessayer. (L02)";
            } else {
                $result = $stmt->get_result();
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    // Check if user is banned FIRST
                    if ($user['is_banned'] == 1) {
                       $error_message = "Votre compte a été suspendu. Veuillez contacter l'assistance.";
                    } elseif (password_verify($password, $user['password'])) { // If not banned, verify password
    // Password is correct, set session variables
                       $_SESSION['user_id'] = $user['id'];
                       $_SESSION['username'] = $user['username'];
                       $_SESSION['role'] = $user['role']; // Store the user's role


                        // Regenerate session ID upon successful login to prevent session fixation
                        session_regenerate_id(true); 

                        $_SESSION['message'] = "Connexion réussie ! Bienvenue, " . htmlspecialchars($user['username']) . ".";

                          if ($user['role'] === 'admin') {
                            header('Location: admin_panel.php'); 
                          } else { 
                            header('Location: ' . $redirectAfterLogin);
                          }
                        exit;
                    } else {
                        $error_message = "Nom d'utilisateur ou mot de passe incorrect.";
                    }
                } else {
                    // Username not found
                    $error_message = "Nom d'utilisateur ou mot de passe incorrect.";
                }
            }
            $stmt->close();
        }
    }
}

include_once 'includes/header.php';
?>

<main class="container auth-form-container">
    <h1>Connexion</h1>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    <?php // Display general session messages if any (e.g., from a redirect) ?>
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


    <form method="POST" action="login.php<?php echo $redirectAfterLogin !== 'profile.php' ? '?redirect=' . urlencode($redirectAfterLogin) : ''; ?>" novalidate>
        <div class="form-group">
            <label for="username">Nom d'utilisateur:</label>
            <input type="text" id="username" name="username" placeholder="Votre nom d'utilisateur" value="<?php echo $username_value; ?>" required autofocus />
        </div>
        <div class="form-group">
            <label for="password">Mot de passe:</label>
            <input type="password" id="password" name="password" placeholder="Votre mot de passe" required />
        </div>
        <div class="form-group">
            <input type="submit" value="Se connecter" class="button-primary" />
        </div>
    </form>
    <p class="auth-links">
        Pas encore de compte ? <a href="register.php<?php /* ... */ ?>">Inscrivez-vous ici</a>.<br>
        <a href="forgot_password.php">Mot de passe oublié ?</a> <!-- << NEW LINK -->
    </p>
</main>

<?php
// $conn->close(); // Optional for scripts like this.
include_once 'includes/footer.php';
?>
