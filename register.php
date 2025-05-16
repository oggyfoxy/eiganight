<?php
session_start();
include('config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!$username || !$password) {
        $error = "Tous les champs sont obligatoires.";
    } else {
        // Vérifier si username existe déjà
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Nom d'utilisateur déjà pris.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hash);
            if ($stmt->execute()) {
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['username'] = $username;
                header("Location: profile.php");
                exit;
            } else {
                $error = "Erreur lors de l'inscription.";
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Inscription – Eiganights</title></head>
<body>
<h2>Inscription</h2>
<?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
<form method="post" action="">
    <input type="text" name="username" placeholder="Nom d'utilisateur" required><br>
    <input type="password" name="password" placeholder="Mot de passe" required><br>
    <button type="submit">S'inscrire</button>
</form>
<p>Tu as déjà un compte ? <a href="login.php">Connecte-toi ici</a></p>
</body>
</html>
