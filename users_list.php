<?php
include_once 'config.php';

$loggedInUserId = $_SESSION['user_id'] ?? null;
$users = [];
$pageTitle = "Liste des Utilisateurs - Eiganights";

$searchUsernameParam = '';
$searchUsernameDisplay = '';

if (isset($_GET['search_user'])) {
    $searchUsernameParam = trim($_GET['search_user']);
    $searchUsernameDisplay = htmlspecialchars($searchUsernameParam, ENT_QUOTES, 'UTF-8');
    if (!empty($searchUsernameDisplay)) {
        $pageTitle = "Recherche Utilisateur: " . $searchUsernameDisplay . " - Eiganights";
    }
}

if (!empty($searchUsernameParam)) {
    $searchTermSQL = "%" . $searchUsernameParam . "%";
    if ($loggedInUserId) {
        $sql = "SELECT id, username FROM users WHERE username LIKE ? AND id != ? AND role != 'admin' AND is_banned = 0 ORDER BY username ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed (UL_SEARCH_LID): " . $conn->error);
            $_SESSION['error'] = "Erreur lors de la recherche d'utilisateurs. (UL01)";
        } else {
            $stmt->bind_param("si", $searchTermSQL, $loggedInUserId);
        }
    } else { // User not logged in, search all users
        $sql = "SELECT id, username FROM users WHERE username LIKE ? AND role != 'admin' AND is_banned = 0 ORDER BY username ASC";
// No change to bind_param needed
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed (UL_SEARCH_NOLID): " . $conn->error);
            $_SESSION['error'] = "Erreur lors de la recherche d'utilisateurs. (UL02)";
        } else {
            $stmt->bind_param("s", $searchTermSQL);
        }
    }
} else {
    $limit = 20;
    if ($loggedInUserId) {
        $sql = "SELECT id, username FROM users WHERE id != ? AND role != 'admin' AND is_banned = 0 ORDER BY created_at DESC, username ASC LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed (UL_LIST_LID): " . $conn->error);
            $_SESSION['error'] = "Erreur lors du chargement des utilisateurs. (UL03)";
        } else {
            $stmt->bind_param("ii", $loggedInUserId, $limit);
        }
    } else {
        $sql = "SELECT id, username FROM users WHERE role != 'admin' AND is_banned = 0 ORDER BY created_at DESC, username ASC LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed (UL_LIST_NOLID): " . $conn->error);
            $_SESSION['error'] = "Erreur lors du chargement des utilisateurs. (UL04)";
        } else {
            $stmt->bind_param("i", $limit);
        }
    }
}

if (isset($stmt) && $stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    } else {
        error_log("Execute failed (UL_EXEC): " . $stmt->error);
        $_SESSION['error'] = (isset($_SESSION['error']) ? $_SESSION['error'] . " " : "") . "Erreur lors de la récupération des données. (UL05)";
    }
    $stmt->close();
}

include_once 'includes/header.php';
?>

<main class="container users-list-page">
    <h1>Utilisateurs Eiganights</h1>

    <?php?>
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['warning'])): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars($_SESSION['warning']); unset($_SESSION['warning']); ?></div>
    <?php endif; ?>

    <section class="search-user-section card">
        <form method="GET" action="users_list.php" class="search-box">
            <label for="search_user_input" class="visually-hidden">Rechercher un utilisateur</label>
            <input type="text" id="search_user_input" name="search_user" placeholder="Nom d'utilisateur..." value="<?php echo $searchUsernameDisplay; ?>" />
            <button type="submit" class="button-primary">Rechercher</button>
        </form>
    </section>

    <section class="user-results-section">
        <?php if (!empty($searchUsernameDisplay)): ?>
            <h2>Résultats de recherche pour "<?php echo $searchUsernameDisplay; ?>"</h2>
        <?php elseif (empty($users) && empty($_SESSION['error'])): ?>
            <h2>Parcourir les utilisateurs</h2> <!-- Or some other appropriate heading if no search -->
        <?php endif; ?>

        <?php if (!empty($users)): ?>
            <ul class="user-list card-list">
                <?php foreach ($users as $user): ?>
                    <li>
                        <a href="view_profile.php?id=<?php echo (int)$user['id']; ?>">
                            <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <?php?>
                        <?php if ($loggedInUserId && $loggedInUserId !== (int)$user['id']): ?>
                            <a href="view_profile.php?id=<?php echo (int)$user['id']; ?>" class="button button-secondary button-small">Voir Profil</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php elseif (!empty($searchUsernameParam) && empty($_SESSION['error'])): ?>
            <p>Aucun utilisateur trouvé correspondant à votre recherche "<?php echo $searchUsernameDisplay; ?>".</p>
        <?php elseif (empty($_SESSION['error'])):
            ?>
            <p>Aucun utilisateur à afficher pour le moment. <a href="register.php">Soyez le premier à vous inscrire !</a></p>
        <?php endif; ?>
    </section>
</main>

<?php
include_once 'includes/footer.php';
?>