<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Eiganights</title>
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
<nav>
    <a href="index.php">Accueil</a> |
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="profile.php">Profil</a> |
        <a href="logout.php">Déconnexion</a>
    <?php else: ?>
        <a href="login.php">Connexion</a> |
        <a href="register.php">Inscription</a>
    <?php endif; ?>
</nav>
<hr />
