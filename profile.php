<?php
/*
 * profile.php
 * Displays and manages the logged-in user's profile.
 */
include_once 'config.php'; // Handles session_start(), $conn, TMDB_API_KEY

// Check if user is logged in; if not, redirect to login page
if (!isset($_SESSION['user_id'])) {
    $_SESSION['login_required_message'] = "Vous devez être connecté pour voir votre profil.";
    header('Location: login.php?redirect=profile.php'); // Redirect back to profile after login
    exit;
}

$loggedInUserId = (int)$_SESSION['user_id'];
$user = null; // Will hold user's profile data
$watchlist = [];
$pendingRequests = [];
$friends = [];
$pageTitle = "Mon Profil - Eiganights"; // Default

// --- Fetch User's Core Profile Data ---
$stmtUser = $conn->prepare("SELECT id, username, bio, created_at, profile_visibility FROM users WHERE id = ?");
if (!$stmtUser) {
    error_log("Prepare failed (PROF_USER_SEL): " . $conn->error);
    $_SESSION['error'] = "Erreur lors du chargement de votre profil. (P01)";
    // Redirect to a safe page or show error on current page if header/footer can still load
    header('Location: index.php');
    exit;
}
$stmtUser->bind_param("i", $loggedInUserId);
if (!$stmtUser->execute()) {
    error_log("Execute failed (PROF_USER_SEL): " . $stmtUser->error);
    $_SESSION['error'] = "Erreur lors du chargement de votre profil. (P02)";
    $stmtUser->close();
    header('Location: index.php');
    exit;
}
$resultUser = $stmtUser->get_result();
if ($user = $resultUser->fetch_assoc()) {
    $pageTitle = "Profil de " . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . " - Eiganights";
} else {
    // Should not happen if user_id in session is valid, but handle defensively
    error_log("User with ID $loggedInUserId not found in database, but session exists.");
    session_destroy(); // Destroy potentially corrupt session
    $_SESSION['error'] = "Erreur de session. Veuillez vous reconnecter.";
    header('Location: login.php');
    exit;
}
$stmtUser->close();


// --- Handle Profile Update (Bio and Visibility) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updatePerformed = false;

    // Update Bio
    if (isset($_POST['bio'])) { // Presence of 'bio' key indicates intent to update it (even if empty)
        $newBio = trim($_POST['bio']); // Allow empty bio

        $stmtUpdateBio = $conn->prepare("UPDATE users SET bio = ? WHERE id = ?");
        if (!$stmtUpdateBio) {
            error_log("Prepare failed (PROF_BIO_UPD): " . $conn->error);
            $_SESSION['error'] = (isset($_SESSION['error']) ? $_SESSION['error'] . " " : "") . "Erreur système (Bio). (P03)";
        } else {
            $stmtUpdateBio->bind_param("si", $newBio, $loggedInUserId);
            if ($stmtUpdateBio->execute()) {
                $_SESSION['message'] = (isset($_SESSION['message']) ? $_SESSION['message'] . " " : "") . "Bio mise à jour.";
                $user['bio'] = $newBio; // Update local $user array
                $updatePerformed = true;
            } else {
                error_log("Execute failed (PROF_BIO_UPD): " . $stmtUpdateBio->error);
                $_SESSION['error'] = (isset($_SESSION['error']) ? $_SESSION['error'] . " " : "") . "Erreur lors de la mise à jour de la bio. (P04)";
            }
            $stmtUpdateBio->close();
        }
    }

    // Update Profile Visibility
    if (isset($_POST['profile_visibility'])) {
        $newVisibility = trim($_POST['profile_visibility']);
        $allowedVisibilities = ['public', 'friends_only', 'private'];

        if (in_array($newVisibility, $allowedVisibilities)) {
            $stmtUpdateVis = $conn->prepare("UPDATE users SET profile_visibility = ? WHERE id = ?");
            if (!$stmtUpdateVis) {
                error_log("Prepare failed (PROF_VIS_UPD): " . $conn->error);
                $_SESSION['error'] = (isset($_SESSION['error']) ? $_SESSION['error'] . " " : "") . "Erreur système (Visibilité). (P05)";
            } else {
                $stmtUpdateVis->bind_param("si", $newVisibility, $loggedInUserId);
                if ($stmtUpdateVis->execute()) {
                    $_SESSION['message'] = (isset($_SESSION['message']) ? $_SESSION['message'] . " " : "") . "Visibilité du profil mise à jour.";
                    $user['profile_visibility'] = $newVisibility; // Update local $user array
                    $updatePerformed = true;
                } else {
                    error_log("Execute failed (PROF_VIS_UPD): " . $stmtUpdateVis->error);
                    $_SESSION['error'] = (isset($_SESSION['error']) ? $_SESSION['error'] . " " : "") . "Erreur lors de la mise à jour de la visibilité. (P06)";
                }
                $stmtUpdateVis->close();
            }
        } else {
            $_SESSION['error'] = (isset($_SESSION['error']) ? $_SESSION['error'] . " " : "") . "Option de visibilité invalide sélectionnée.";
        }
    }

    if ($updatePerformed) {
        // Redirect after POST to prevent form resubmission (Post/Redirect/Get pattern)
        header('Location: profile.php');
        exit;
    }
    // If only errors occurred, no redirect here, errors will be shown on page reload.
}


// --- Fetch User's Watchlist ---
$stmtWatchlist = $conn->prepare("SELECT movie_id, movie_title, poster_path FROM watchlist WHERE user_id = ? ORDER BY added_at DESC");
if (!$stmtWatchlist) {
    error_log("Prepare failed (PROF_WL_SEL): " . $conn->error);
    $_SESSION['error_watchlist'] = "Erreur lors du chargement de la watchlist. (P07)"; // Use specific error key
} else {
    $stmtWatchlist->bind_param("i", $loggedInUserId);
    if ($stmtWatchlist->execute()) {
        $resultWatchlist = $stmtWatchlist->get_result();
        while ($row = $resultWatchlist->fetch_assoc()) {
            $watchlist[] = $row;
        }
    } else {
        error_log("Execute failed (PROF_WL_SEL): " . $stmtWatchlist->error);
        $_SESSION['error_watchlist'] = "Erreur lors du chargement de la watchlist. (P08)";
    }
    $stmtWatchlist->close();
}


// --- Fetch Pending Friend Requests (Requests sent TO the logged-in user) ---
$sqlPending = "
    SELECT u.id, u.username 
    FROM friendships f
    JOIN users u ON f.user_one_id = u.id  -- User who sent the request
    WHERE f.user_two_id = ?               -- LoggedInUser is user_two_id (receiver)
      AND f.status = 'pending' 
      AND f.action_user_id = f.user_one_id -- Action user is the sender
    UNION ALL
    SELECT u.id, u.username
    FROM friendships f
    JOIN users u ON f.user_two_id = u.id -- User who sent the request
    WHERE f.user_one_id = ?              -- LoggedInUser is user_one_id (receiver)
      AND f.status = 'pending'
      AND f.action_user_id = f.user_two_id -- Action user is the sender
";
// This complex query is due to the (user_one_id < user_two_id) constraint.
// A simpler way might be to query friendships where loggedInUserId is user_one or user_two, status is pending,
// AND action_user_id is NOT loggedInUserId.
$sqlPendingSimplified = "
    SELECT u.id, u.username, f.action_user_id
    FROM friendships f
    JOIN users u ON (CASE 
                        WHEN f.user_one_id = ? THEN f.user_two_id 
                        ELSE f.user_one_id 
                    END) = u.id
    WHERE (f.user_one_id = ? OR f.user_two_id = ?) 
      AND f.status = 'pending' 
      AND f.action_user_id != ? 
    ORDER BY u.username"; // Order for consistent display

$stmtPending = $conn->prepare($sqlPendingSimplified);
if (!$stmtPending) {
    error_log("Prepare failed (PROF_FR_PEND_SEL): " . $conn->error);
    $_SESSION['error_friends'] = "Erreur (demandes d'amis). (P09)";
} else {
    $stmtPending->bind_param("iiii", $loggedInUserId, $loggedInUserId, $loggedInUserId, $loggedInUserId);
    if ($stmtPending->execute()) {
        $resultPending = $stmtPending->get_result();
        while ($row = $resultPending->fetch_assoc()) {
            // Double check if the action_user_id is indeed the other user
            if ($row['action_user_id'] == $row['id']) { // $row['id'] is the ID of the other user
                $pendingRequests[] = $row;
            }
        }
    } else {
        error_log("Execute failed (PROF_FR_PEND_SEL): " . $stmtPending->error);
        $_SESSION['error_friends'] = "Erreur (demandes d'amis). (P10)";
    }
    $stmtPending->close();
}


// --- Fetch Accepted Friends ---
$sqlFriends = "
    SELECT u.id, u.username 
    FROM friendships f
    JOIN users u ON (CASE 
                        WHEN f.user_one_id = ? THEN f.user_two_id 
                        ELSE f.user_one_id 
                    END) = u.id
    WHERE (f.user_one_id = ? OR f.user_two_id = ?) 
      AND f.status = 'accepted'
    ORDER BY u.username"; // Order for consistent display

$stmtFriends = $conn->prepare($sqlFriends);
if (!$stmtFriends) {
    error_log("Prepare failed (PROF_FR_ACC_SEL): " . $conn->error);
     $_SESSION['error_friends'] = (isset($_SESSION['error_friends']) ? $_SESSION['error_friends'] . " " : "") . "Erreur (liste d'amis). (P11)";
} else {
    $stmtFriends->bind_param("iii", $loggedInUserId, $loggedInUserId, $loggedInUserId);
    if ($stmtFriends->execute()) {
        $resultFriends = $stmtFriends->get_result();
        while ($row = $resultFriends->fetch_assoc()) {
            $friends[] = $row;
        }
    } else {
        error_log("Execute failed (PROF_FR_ACC_SEL): " . $stmtFriends->error);
        $_SESSION['error_friends'] = (isset($_SESSION['error_friends']) ? $_SESSION['error_friends'] . " " : "") . "Erreur (liste d'amis). (P12)";
    }
    $stmtFriends->close();
}

include_once 'includes/header.php';
?>

<main class="container profile-page">
    <h1>Profil de <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></h1>

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

    <section class="profile-management card">
        <h2>Modifier mes informations</h2>
        <form method="POST" action="profile.php" novalidate>
            <div class="form-group">
                <label for="bio">Ma Bio:</label>
                <textarea name="bio" id="bio" rows="4" placeholder="Parlez un peu de vous..."><?php echo htmlspecialchars($user['bio'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="profile_visibility">Visibilité du profil:</label>
                <select name="profile_visibility" id="profile_visibility">
                    <option value="public" <?php echo (($user['profile_visibility'] ?? 'public') === 'public') ? 'selected' : ''; ?>>Public (Tout le monde peut voir)</option>
                    <option value="friends_only" <?php echo (($user['profile_visibility'] ?? 'public') === 'friends_only') ? 'selected' : ''; ?>>Amis Seulement</option>
                    <option value="private" <?php echo (($user['profile_visibility'] ?? 'public') === 'private') ? 'selected' : ''; ?>>Privé (Seulement moi)</option>
                </select>
            </div>
            <button type="submit" class="button-primary">Mettre à jour le profil</button>
        </form>
    </section>

    <hr>

    <section class="friend-requests card">
        <h2>Demandes d'ami en attente (<?php echo count($pendingRequests); ?>)</h2>
        <?php if (!empty($_SESSION['error_friends']) && strpos($_SESSION['error_friends'], "(demandes d'amis)") !== false) : ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error_friends']); unset($_SESSION['error_friends']); ?></div>
        <?php endif; ?>
        <?php if (!empty($pendingRequests)): ?>
            <ul class="friend-request-list">
                <?php foreach ($pendingRequests as $requestUser): ?>
                    <li>
                        <span>
                            <a href="view_profile.php?id=<?php echo (int)$requestUser['id']; ?>">
                                <?php echo htmlspecialchars($requestUser['username'], ENT_QUOTES, 'UTF-8'); ?>
                            </a> vous a envoyé une demande.
                        </span>
                        <div class="actions">
                            <form action="friend_action.php" method="POST" class="inline-form">
                                <input type="hidden" name="action" value="accept_request">
                                <input type="hidden" name="profile_user_id" value="<?php echo (int)$requestUser['id']; ?>">
                                <input type="hidden" name="redirect_url" value="profile.php">
                                <button type="submit" class="button-success">Accepter</button>
                            </form>
                            <form action="friend_action.php" method="POST" class="inline-form">
                                <input type="hidden" name="action" value="decline_request">
                                <input type="hidden" name="profile_user_id" value="<?php echo (int)$requestUser['id']; ?>">
                                <input type="hidden" name="redirect_url" value="profile.php">
                                <button type="submit" class="button-danger">Refuser</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php elseif (empty($_SESSION['error_friends']) || strpos($_SESSION['error_friends'], "(demandes d'amis)") === false) : ?>
            <p>Aucune nouvelle demande d'ami.</p>
        <?php endif; ?>
    </section>

    <hr>

    <section class="friends-list card">
        <h2>Mes Amis (<?php echo count($friends); ?>)</h2>
        <?php if (!empty($_SESSION['error_friends']) && strpos($_SESSION['error_friends'], "(liste d'amis)") !== false) : ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error_friends']); unset($_SESSION['error_friends']); ?></div>
        <?php endif; ?>
        <?php if (!empty($friends)): ?>
            <ul class="friend-list">
                <?php foreach ($friends as $friend): ?>
                    <li>
                        <a href="view_profile.php?id=<?php echo (int)$friend['id']; ?>">
                            <?php echo htmlspecialchars($friend['username'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <form action="friend_action.php" method="POST" class="inline-form">
                            <input type="hidden" name="action" value="unfriend">
                            <input type="hidden" name="profile_user_id" value="<?php echo (int)$friend['id']; ?>">
                            <input type="hidden" name="redirect_url" value="profile.php">
                            <button type="submit" class="button-danger button-small">Retirer</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php elseif (empty($_SESSION['error_friends']) || strpos($_SESSION['error_friends'], "(liste d'amis)") === false) : ?>
            <p>Vous n'avez pas encore d'amis. <a href="users_list.php">Trouvez des utilisateurs !</a></p>
        <?php endif; ?>
         <?php if (isset($_SESSION['error_friends'])) unset($_SESSION['error_friends']); // Clear if partially displayed ?>
    </section>

    <hr>

    <section class="watchlist-section card">
        <h2>Ma Watchlist (<?php echo count($watchlist); ?>)</h2>
        <?php if (!empty($_SESSION['error_watchlist'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error_watchlist']); unset($_SESSION['error_watchlist']); ?></div>
        <?php endif; ?>
        <?php if (!empty($watchlist)): ?>
            <div class="watchlist-grid movies-grid"> <?php // Re-use movies-grid for consistency ?>
                <?php foreach ($watchlist as $movie): ?>
                    <article class="movie-in-watchlist movie"> <?php // Re-use movie class ?>
                        <a href="movie_details.php?id=<?php echo (int)$movie['movie_id']; ?>">
                            <?php if (!empty($movie['poster_path'])): ?>
                                <img src="https://image.tmdb.org/t/p/w300<?php echo htmlspecialchars($movie['poster_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                                     alt="Affiche de <?php echo htmlspecialchars($movie['movie_title'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" />
                            <?php else: ?>
                                <img src="assets/images/no_poster_available.png" 
                                     alt="Pas d'affiche pour <?php echo htmlspecialchars($movie['movie_title'], ENT_QUOTES, 'UTF-8'); ?>" 
                                     class="movie-poster-placeholder" loading="lazy" />
                            <?php endif; ?>
                        </a>
                        <div class="movie-info-watchlist movie-info"> <?php // Re-use movie-info ?>
                            <h3 class="movie-title-watchlist movie-title">
                                 <a href="movie_details.php?id=<?php echo (int)$movie['movie_id']; ?>"><?php echo htmlspecialchars($movie['movie_title'], ENT_QUOTES, 'UTF-8'); ?></a>
                            </h3>
                            <form method="POST" action="remove_from_watchlist.php" class="remove-watchlist-form">
                                <input type="hidden" name="movie_id" value="<?php echo (int)$movie['movie_id']; ?>">
                                <input type="hidden" name="redirect_url" value="profile.php">
                                <button type="submit" class="button-danger">Supprimer</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php elseif (empty($_SESSION['error_watchlist'])): ?>
            <p>Votre watchlist est vide. <a href="index.php">Parcourez les films et ajoutez-en !</a></p>
        <?php endif; ?>
    </section>
</main>

<?php
// $conn->close(); // Optional.
include_once 'includes/footer.php';
?>