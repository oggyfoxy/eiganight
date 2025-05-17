<?php
/*
 * users_list.php
 * Displays a list of users and allows searching for users.
 */
include_once 'config.php'; // Includes session_start(), $conn, TMDB_API_KEY

$loggedInUserId = $_SESSION['user_id'] ?? null; // Null if user is not logged in
$users = [];
$pageTitle = "Liste des Utilisateurs - Eiganights";

// --- Handle User Search ---
$searchUsernameParam = '';
$searchUsernameDisplay = '';

if (isset($_GET['search_user'])) {
    $searchUsernameParam = trim($_GET['search_user']);
    $searchUsernameDisplay = htmlspecialchars($searchUsernameParam, ENT_QUOTES, 'UTF-8');
    if (!empty($searchUsernameDisplay)) {
        $pageTitle = "Recherche Utilisateur: " . $searchUsernameDisplay . " - Eiganights";
    }
}

// --- Fetch Users from Database ---
if (!empty($searchUsernameParam)) {
    $searchTermSQL = "%" . $searchUsernameParam . "%"; // Wildcards for LIKE search
    // Exclude the logged-in user from search results if they are logged in
    if ($loggedInUserId) {
        $sql = "SELECT id, username FROM users WHERE username LIKE ? AND id != ? ORDER BY username ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed (UL_SEARCH_LID): " . $conn->error);
            $_SESSION['error'] = "Erreur lors de la recherche d'utilisateurs. (UL01)";
        } else {
            $stmt->bind_param("si", $searchTermSQL, $loggedInUserId);
        }
    } else { // User not logged in, search all users
        $sql = "SELECT id, username FROM users WHERE username LIKE ? ORDER BY username ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed (UL_SEARCH_NOLID): " . $conn->error);
            $_SESSION['error'] = "Erreur lors de la recherche d'utilisateurs. (UL02)";
        } else {
            $stmt->bind_param("s", $searchTermSQL);
        }
    }
} else { // No search query, display a list of recent/all users (excluding logged-in user if applicable)
    $limit = 20; // Limit the number of users displayed by default
    if ($loggedInUserId) {
        $sql = "SELECT id, username FROM users WHERE id != ? ORDER BY created_at DESC, username ASC LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed (UL_LIST_LID): " . $conn->error);
            $_SESSION['error'] = "Erreur lors du chargement des utilisateurs. (UL03)";
        } else {
            $stmt->bind_param("ii", $loggedInUserId, $limit);
        }
    } else { // User not logged in, show generic list
        $sql = "SELECT id, username FROM users ORDER BY created_at DESC, username ASC LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed (UL_LIST_NOLID): " . $conn->error);
            $_SESSION['error'] = "Erreur lors du chargement des utilisateurs. (UL04)";
        } else {
            $stmt->bind_param("i", $limit);
        }
    }
}

// Execute statement and fetch results if statement was prepared successfully
if (isset($stmt) && $stmt) { // Check if $stmt is not false (i.e., prepare was successful)
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
// If $stmt prepare failed, an error message should already be in $_SESSION['error']

include_once 'includes/header.php';
?>

<main class="container users-list-page">
    <h1>Utilisateurs Eiganights</h1>

    <?php // Display session messages ?>
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
                        <?php // Friend action buttons could be added here, but might require more complex logic/queries per user ?>
                        <?php if ($loggedInUserId && $loggedInUserId !== (int)$user['id']): ?>
                            <a href="view_profile.php?id=<?php echo (int)$user['id']; ?>" class="button button-secondary button-small">Voir Profil</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php elseif (!empty($searchUsernameParam) && empty($_SESSION['error'])): ?>
            <p>Aucun utilisateur trouvé correspondant à votre recherche "<?php echo $searchUsernameDisplay; ?>".</p>
        <?php elseif (empty($_SESSION['error'])): // No users found and no search, or no error from DB
            ?>
            <p>Aucun utilisateur à afficher pour le moment. <a href="register.php">Soyez le premier à vous inscrire !</a></p>
        <?php endif; ?>
    </section>
</main>

<?php
// $conn->close(); // Optional
include_once 'includes/footer.php';
?>