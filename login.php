<?php
session_start();
include('config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->bind_result($id, $hash);
    if ($stmt->fetch() && password_verify($password, $hash)) {
        $_SESSION['user_id'] = $id;
        $_SESSION['username'] = $username;
        header("Location: profile.php");
        exit;
    } else {
        $error = "Identifiants incorrects.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Connexion – Eiganights</title></head>
<body>
<h2>Connexion</h2>
<?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
<form method="post" action="">
    <input type="text" name="username" placeholder="Nom d'utilisateur" required><br>
    <input type="password" name="password" placeholder="Mot de passe" required><br>
    <button type="submit">Se connecter</button>
</form>
<p>Pas encore de compte ? <a href="register.php">Inscris-toi ici</a></p>
</body>
</html>
