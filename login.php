<?php
session_start();
include('config.php');

if (isset($_SESSION['user_id'])) {
    header('Location: profile.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $query = "SELECT id, password FROM users WHERE username = '$username'";
    $result = $conn->query($query);

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            header('Location: profile.php');
            exit;
        } else {
            $error = "Mot de passe incorrect.";
        }
    } else {
        $error = "Utilisateur non trouvé.";
    }
}

include('includes/header.php');
?>

<h1>Connexion</h1>
<?php if ($error): ?>
    <p style="color:red;"><?php echo $error; ?></p>
<?php endif; ?>

<form method="POST" action="">
    <input type="text" name="username" placeholder="Nom d'utilisateur" required />
    <input type="password" name="password" placeholder="Mot de passe" required />
    <input type="submit" value="Se connecter" />
</form>

<?php include('includes/footer.php'); ?>
