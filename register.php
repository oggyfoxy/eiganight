<?php
session_start();
include('config.php');

if (isset($_SESSION['user_id'])) {
    header('Location: profile.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if ($password !== $password_confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        $username = $conn->real_escape_string($username);
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Vérifier si username existe déjà
        $checkQuery = "SELECT id FROM users WHERE username = '$username'";
        $result = $conn->query($checkQuery);

        if ($result && $result->num_rows > 0) {
            $error = "Nom d'utilisateur déjà pris.";
        } else {
            $insertQuery = "INSERT INTO users (username, password) VALUES ('$username', '$hashedPassword')";
            if ($conn->query($insertQuery)) {
                $_SESSION['user_id'] = $conn->insert_id;
                header('Location: profile.php');
                exit;
            } else {
                $error = "Erreur lors de l'inscription.";
            }
        }
    }
}

include('includes/header.php');
?>

<h1>Inscription</h1>

<?php if ($error): ?>
    <p style="color:red;"><?php echo $error; ?></p>
<?php endif; ?>

<form method="POST" action="">
    <input type="text" name="username" placeholder="Nom d'utilisateur" required />
    <input type="password" name="password" placeholder="Mot de passe" required />
    <input type="password" name="password_confirm" placeholder="Confirmer le mot de passe" required />
    <input type="submit" value="S'inscrire" />
</form>

<?php include('includes/footer.php'); ?>
