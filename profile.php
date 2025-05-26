<?php
include_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['login_required_message'] = "Vous devez être connecté pour voir votre profil.";
    header('Location: login.php?redirect=profile.php');
    exit;
}

$loggedInUserId = (int)$_SESSION['user_id'];
$user = null;
$watchlist = [];
$pendingRequests = [];
$friends = [];
$pageTitle = "Mon Profil - Eiganights";

$stmtUser = $conn->prepare("SELECT id, username, bio, created_at, profile_visibility FROM users WHERE id = ?");
if (!$stmtUser) {
    error_log("Prepare failed (PROF_USER_SEL): " . $conn->error);
    $_SESSION['error'] = "Erreur lors du chargement de votre profil. (P01)";
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
    error_log("User with ID $loggedInUserId not found in database, but session exists.");
    session_destroy();
    $_SESSION['error'] = "Erreur de session. Veuillez vous reconnecter.";
    header('Location: login.php');
    exit;
}
$stmtUser->close();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updatePerformed = false;

    if (isset($_POST['bio'])) {
        $newBio = trim($_POST['bio']);

        $stmtUpdateBio = $conn->prepare("UPDATE users SET bio = ? WHERE id = ?");
        if (!$stmtUpdateBio) {
            error_log("Prepare failed (PROF_BIO_UPD): " . $conn->error);
            $_SESSION['error'] = (isset($_SESSION['error']) ? $_SESSION['error'] . " " : "") . "Erreur système (Bio). (P03)";
        } else {
            $stmtUpdateBio->bind_param("si", $newBio, $loggedInUserId);
            if ($stmtUpdateBio->execute()) {
                $_SESSION['message'] = (isset($_SESSION['message']) ? $_SESSION['message'] . " " : "") . "Bio mise à jour.";
                $user['bio'] = $newBio;
                $updatePerformed = true;
            } else {
                error_log("Execute failed (PROF_BIO_UPD): " . $stmtUpdateBio->error);
                $_SESSION['error'] = (isset($_SESSION['error']) ? $_SESSION['error'] . " " : "") . "Erreur lors de la mise à jour de la bio. (P04)";
            }
            $stmtUpdateBio->close();
        }
    }

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
                    $user['profile_visibility'] = $newVisibility;
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
        header('Location: profile.php');
        exit;
    }
}


$stmtWatchlist = $conn->prepare("SELECT movie_id, movie_title, poster_path FROM watchlist WHERE user_id = ? ORDER BY added_at DESC");
if (!$stmtWatchlist) {
    error_log("Prepare failed (PROF_WL_SEL): " . $conn->error);
    $_SESSION['error_watchlist'] = "Erreur lors du chargement de la watchlist. (P07)";
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
    ORDER BY u.username";

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


$sqlFriends = "
    SELECT u.id, u.username 
    FROM friendships f
    JOIN users u ON (CASE 
                        WHEN f.user_one_id = ? THEN f.user_two_id 
                        ELSE f.user_one_id 
                    END) = u.id
    WHERE (f.user_one_id = ? OR f.user_two_id = ?) 
      AND f.status = 'accepted'
    ORDER BY u.username";

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

$mySceneAnnotations = [];
$sqlMyAnnotations = "SELECT id, title, movie_title, movie_id, scene_start_time, scene_description_short, created_at 
                     FROM forum_threads 
                     WHERE user_id = ? AND (scene_start_time IS NOT NULL OR scene_description_short IS NOT NULL)
                     ORDER BY created_at DESC";
$stmtMyAnnotations = $conn->prepare($sqlMyAnnotations);
if (!$stmtMyAnnotations) {
    error_log("Prepare failed (PROF_MY_ANNOT_SEL): " . $conn->error);
    $_SESSION['error_annotations'] = "Erreur chargement annotations. (P13)";
} else {
    $stmtMyAnnotations->bind_param("i", $loggedInUserId);
    if ($stmtMyAnnotations->execute()) {
        $resultMyAnnotations = $stmtMyAnnotations->get_result();
        while ($row = $resultMyAnnotations->fetch_assoc()) {
            $mySceneAnnotations[] = $row;
        }
    } else {
        error_log("Execute failed (PROF_MY_ANNOT_SEL): " . $stmtMyAnnotations->error);
        $_SESSION['error_annotations'] = "Erreur chargement annotations. (P14)";
    }
    $stmtMyAnnotations->close();
}

include_once 'includes/header.php';
?>

<main class="container profile-page">
    <h1>Profil de <?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></h1>

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
         <?php if (isset($_SESSION['error_friends'])) unset($_SESSION['error_friends']);?>
    </section>

    <hr>

    <section class="watchlist-section card">
        <h2>Ma Watchlist (<?php echo count($watchlist); ?>)</h2>
        <?php if (!empty($_SESSION['error_watchlist'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error_watchlist']); unset($_SESSION['error_watchlist']); ?></div>
        <?php endif; ?>
        <?php if (!empty($watchlist)): ?>
            <div class="watchlist-grid movies-grid"> <?php?>
                <?php foreach ($watchlist as $movie): ?>
                    <article class="movie-in-watchlist movie"> <?php?>
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
                        <div class="movie-info-watchlist movie-info"> <?php?>
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


<hr>

<section class="my-annotations-section card">
    <h2>Mes Annotations de Scènes (<?php echo count($mySceneAnnotations); ?>)</h2>
    <?php if (!empty($_SESSION['error_annotations'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error_annotations']); unset($_SESSION['error_annotations']); ?></div>
    <?php endif; ?>
    <?php if (!empty($mySceneAnnotations)): ?>
        <ul class="annotations-list profile-annotations-list">
            <?php foreach ($mySceneAnnotations as $annotation): ?>
                <li class="annotation-item">
                    <a href="forum_view_thread.php?id=<?php echo (int)$annotation['id']; ?>" class="annotation-title">
                        <strong><?php echo htmlspecialchars($annotation['title']); ?></strong>
                    </a>
                    <p class="movie-link">Pour le film: <a href="movie_details.php?id=<?php echo (int)$annotation['movie_id']; ?>"><?php echo htmlspecialchars($annotation['movie_title']); ?></a></p>
                    <?php if (!empty($annotation['scene_description_short'])): ?>
                        <p class="scene-desc-preview"><em>Scène : <?php echo htmlspecialchars($annotation['scene_description_short']); ?></em></p>
                    <?php endif; ?>
                    <?php if (!empty($annotation['scene_start_time'])): ?>
                        <p class="scene-time-preview">Temps : <?php echo htmlspecialchars($annotation['scene_start_time']); ?></p>
                    <?php endif; ?>
                    <p class="annotation-meta">
                        Créé le <?php echo date('d/m/Y', strtotime($annotation['created_at'])); ?>
                    </p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php elseif (empty($_SESSION['error_annotations'])): ?>
        <p>Vous n'avez pas encore créé d'annotations de scènes. <a href="index.php">Trouvez un film et lancez-vous !</a></p>
    <?php endif; ?>
</section>

<?php
include_once 'includes/footer.php';
?>