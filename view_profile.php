<?php
/*
 * view_profile.php
 * Displays the profile of a specified user.
 * (Version without 'blocked' status consideration)
 */
include_once 'config.php'; 

$profileUser = null;
$profileUserId = null; 
$loggedInUserId = $_SESSION['user_id'] ?? null; 

$identifier = null;
$identifierType = null;

if (isset($_GET['id']) && is_numeric($_GET['id']) && (int)$_GET['id'] > 0) {
    $identifier = (int)$_GET['id'];
    $identifierType = 'id';
} elseif (isset($_GET['username']) && !empty(trim($_GET['username']))) {
    $identifier = trim($_GET['username']);
    $identifierType = 'username';
}

if ($identifier === null) {
    $_SESSION['error'] = "Aucun utilisateur spécifié pour afficher le profil.";
    header('Location: users_list.php'); 
    exit;
}

if ($identifierType === 'id') {
    $sqlUser = "SELECT id, username, bio, created_at, profile_visibility FROM users WHERE id = ?";
    $stmtUser = $conn->prepare($sqlUser);
    if ($stmtUser) $stmtUser->bind_param("i", $identifier);
} else { 
    $sqlUser = "SELECT id, username, bio, created_at, profile_visibility FROM users WHERE username = ?";
    $stmtUser = $conn->prepare($sqlUser);
    if ($stmtUser) $stmtUser->bind_param("s", $identifier);
}

if (!$stmtUser) {
    error_log("Prepare failed (VP_USER_SEL): " . $conn->error);
    $_SESSION['error'] = "Erreur lors du chargement du profil. (VP01)";
    header('Location: users_list.php');
    exit;
}

if (!$stmtUser->execute()) {
    error_log("Execute failed (VP_USER_SEL): " . $stmtUser->error);
    $_SESSION['error'] = "Erreur lors du chargement du profil. (VP02)";
    $stmtUser->close();
    header('Location: users_list.php');
    exit;
}

$resultUser = $stmtUser->get_result();
$profileUser = $resultUser->fetch_assoc();
$stmtUser->close();

if (!$profileUser) {
    $_SESSION['error'] = "Utilisateur non trouvé.";
    header('Location: users_list.php');
    exit;
}
$profileUserId = (int)$profileUser['id']; 

if ($loggedInUserId && $loggedInUserId === $profileUserId) {
    header('Location: profile.php');
    exit;
}

$canViewFullProfile = false; 
$friendshipDbStatus = null; 
$friendAction = 'none';     

if ($loggedInUserId && $loggedInUserId !== $profileUserId) {
    $u1 = min($loggedInUserId, $profileUserId);
    $u2 = max($loggedInUserId, $profileUserId);

    $sqlFriendship = "SELECT status, action_user_id FROM friendships WHERE user_one_id = ? AND user_two_id = ?";
    $stmtFriendship = $conn->prepare($sqlFriendship);
    if ($stmtFriendship) {
        $stmtFriendship->bind_param("ii", $u1, $u2);
        if ($stmtFriendship->execute()) {
            $resultFriendship = $stmtFriendship->get_result();
            if ($row = $resultFriendship->fetch_assoc()) {
                $friendshipDbStatus = $row['status'];
                $actionUserIdFs = (int)$row['action_user_id'];

                if ($friendshipDbStatus === 'accepted') {
                    $friendAction = 'friends';
                } elseif ($friendshipDbStatus === 'pending') {
                    $friendAction = ($actionUserIdFs === $loggedInUserId) ? 'pending_them' : 'pending_me';
                } elseif ($friendshipDbStatus === 'declined') {
                    $friendAction = 'add_friend'; 
                }
                // 'blocked' status is no longer part of the ENUM or this logic
            } else { 
                $friendAction = 'add_friend';
            }
        } else { error_log("Execute failed (VP_FR_STATUS): " . $stmtFriendship->error); }
        $stmtFriendship->close();
    } else { error_log("Prepare failed (VP_FR_STATUS): " . $conn->error); }
}

if ($profileUser['profile_visibility'] === 'public') {
    $canViewFullProfile = true;
} elseif ($profileUser['profile_visibility'] === 'friends_only' && $friendAction === 'friends') {
    $canViewFullProfile = true;
}

$watchlist = [];
$watchlistError = null;
if ($canViewFullProfile) {
    $sqlWatchlist = "SELECT movie_id, movie_title, poster_path FROM watchlist WHERE user_id = ? ORDER BY added_at DESC";
    $stmtWatchlist = $conn->prepare($sqlWatchlist);
    if ($stmtWatchlist) {
        $stmtWatchlist->bind_param("i", $profileUserId);
        if ($stmtWatchlist->execute()) {
            $resultWatchlist = $stmtWatchlist->get_result();
            while ($row = $resultWatchlist->fetch_assoc()) {
                $watchlist[] = $row;
            }
        } else {
            error_log("Execute failed (VP_WL_SEL): " . $stmtWatchlist->error);
            $watchlistError = "Erreur lors du chargement de la watchlist de l'utilisateur. (VP03)";
        }
        $stmtWatchlist->close();
    } else {
        error_log("Prepare failed (VP_WL_SEL): " . $conn->error);
        $watchlistError = "Erreur système lors du chargement de la watchlist. (VP04)";
    }
}

        // >> NEW: Fetch Profile User's Scene Annotations <<
        $profileUserSceneAnnotations = [];
        $annotationsError = null;
        $sqlProfileUserAnnotations = "SELECT id, title, movie_title, movie_id, scene_start_time, scene_description_short, created_at 
                                 FROM forum_threads 
                                 WHERE user_id = ? AND (scene_start_time IS NOT NULL OR scene_description_short IS NOT NULL)
                                 ORDER BY created_at DESC";
        $stmtProfileUserAnnotations = $conn->prepare($sqlProfileUserAnnotations);
        if (!$stmtProfileUserAnnotations) {
            error_log("Prepare failed (VP_USER_ANNOT_SEL): " . $conn->error);
            $annotationsError = "Erreur lors du chargement des annotations de l'utilisateur. (VP05)";
        } else {
            $stmtProfileUserAnnotations->bind_param("i", $profileUserId); // Use $profileUserId here
            if ($stmtProfileUserAnnotations->execute()) {
                $resultProfileUserAnnotations = $stmtProfileUserAnnotations->get_result();
                while ($row = $resultProfileUserAnnotations->fetch_assoc()) {
                    $profileUserSceneAnnotations[] = $row;
                }
            } else {
                error_log("Execute failed (VP_USER_ANNOT_SEL): " . $stmtProfileUserAnnotations->error);
                $annotationsError = "Erreur lors du chargement des annotations de l'utilisateur. (VP06)";
            }
            $stmtProfileUserAnnotations->close();
        }
    // The if ($canViewFullProfile) block continues or ends after this.

$pageTitle = "Profil de " . htmlspecialchars($profileUser['username'], ENT_QUOTES, 'UTF-8') . " - Eiganights";
include_once 'includes/header.php';
?>

<main class="container view-profile-page">
    <header class="profile-header card">
        <h1>Profil de <?php echo htmlspecialchars($profileUser['username'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="meta-info">
            Membre depuis: <?php echo date('d F Y', strtotime($profileUser['created_at'])); ?><br>
            Visibilité du profil: 
            <?php
                switch ($profileUser['profile_visibility']) {
                    case 'public': echo 'Public'; break;
                    case 'friends_only': echo 'Amis Seulement'; break;
                    case 'private': echo 'Privé'; break;
                    default: echo htmlspecialchars(ucfirst($profileUser['profile_visibility'] ?? ''), ENT_QUOTES, 'UTF-8');
                }
            ?>
        </p>

        <?php if (!empty($_SESSION['message'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if ($loggedInUserId && $loggedInUserId !== $profileUserId && $friendAction !== 'none'): ?>
            <div class="friend-actions">
                <?php if ($friendAction === 'add_friend'): ?>
                    <form action="friend_action.php" method="POST" class="inline-form">
                        <input type="hidden" name="action" value="send_request">
                        <input type="hidden" name="profile_user_id" value="<?php echo $profileUserId; ?>">
                        <button type="submit" class="button-primary">Ajouter en ami</button>
                    </form>
                <?php elseif ($friendAction === 'pending_them'): ?>
                    <p>Demande d'ami envoyée.</p>
                    <form action="friend_action.php" method="POST" class="inline-form">
                        <input type="hidden" name="action" value="cancel_request">
                        <input type="hidden" name="profile_user_id" value="<?php echo $profileUserId; ?>">
                        <button type="submit" class="button-warning">Annuler la demande</button>
                    </form>
                <?php elseif ($friendAction === 'pending_me'): ?>
                    <p><?php echo htmlspecialchars($profileUser['username'], ENT_QUOTES, 'UTF-8'); ?> vous a envoyé une demande d'ami.</p>
                    <form action="friend_action.php" method="POST" class="inline-form">
                        <input type="hidden" name="action" value="accept_request">
                        <input type="hidden" name="profile_user_id" value="<?php echo $profileUserId; ?>">
                        <button type="submit" class="button-success">Accepter</button>
                    </form>
                    <form action="friend_action.php" method="POST" class="inline-form">
                        <input type="hidden" name="action" value="decline_request">
                        <input type="hidden" name="profile_user_id" value="<?php echo $profileUserId; ?>">
                        <button type="submit" class="button-danger">Refuser</button>
                    </form>
                <?php elseif ($friendAction === 'friends'): ?>
                    <p>Vous êtes amis avec <?php echo htmlspecialchars($profileUser['username'], ENT_QUOTES, 'UTF-8'); ?>.</p>
                    <form action="friend_action.php" method="POST" class="inline-form">
                        <input type="hidden" name="action" value="unfriend">
                        <input type="hidden" name="profile_user_id" value="<?php echo $profileUserId; ?>">
                        <button type="submit" class="button-danger">Retirer cet ami</button>
                    </form>

                    <a href="message_start_conversation.php?with_user_id=<?php echo $profileUserId; ?>" class="button button-secondary button-small">
                        Envoyer un Message
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </header>

    <section class="profile-bio card">
        <h2>Bio</h2>
        <?php if ($canViewFullProfile || $profileUser['profile_visibility'] === 'public'): ?>
            <p><?php echo !empty($profileUser['bio']) ? nl2br(htmlspecialchars($profileUser['bio'], ENT_QUOTES, 'UTF-8')) : '<em>Cet utilisateur n\'a pas encore de bio.</em>'; ?></p>
        <?php else: ?>
            <p><em>La bio de cet utilisateur est privée ou visible uniquement par ses amis.</em></p>
        <?php endif; ?>
    </section>

    <?php if ($canViewFullProfile): ?>
        <section class="profile-watchlist card">
            <h2>Watchlist de <?php echo htmlspecialchars($profileUser['username'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo count($watchlist); ?>)</h2>
            <?php if ($watchlistError): ?>
                <div class="alert alert-warning"><?php echo htmlspecialchars($watchlistError); ?></div>
            <?php elseif (!empty($watchlist)): ?>
                <div class="watchlist-grid movies-grid">
                    <?php foreach ($watchlist as $movie): ?>
                        <article class="movie-in-watchlist movie">
                            <a href="movie_details.php?id=<?php echo (int)$movie['movie_id']; ?>" aria-label="Détails pour <?php echo htmlspecialchars($movie['movie_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if (!empty($movie['poster_path'])): ?>
                                    <img src="https://image.tmdb.org/t/p/w300<?php echo htmlspecialchars($movie['poster_path'], ENT_QUOTES, 'UTF-8'); ?>" 
                                         alt="Affiche de <?php echo htmlspecialchars($movie['movie_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" loading="lazy"/>
                                <?php else: ?>
                                    <img src="assets/images/no_poster_available.png" 
                                         alt="Pas d'affiche pour <?php echo htmlspecialchars($movie['movie_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                         class="movie-poster-placeholder" loading="lazy"/>
                                <?php endif; ?>
                            </a>
                            <div class="movie-info-watchlist movie-info">
                                <h3 class="movie-title-watchlist movie-title">
                                    <a href="movie_details.php?id=<?php echo (int)$movie['movie_id']; ?>">
                                        <?php echo htmlspecialchars($movie['movie_title'] ?? 'Titre Inconnu', ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </h3>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>La watchlist de cet utilisateur est vide.</p>
            <?php endif; ?>
        </section>
    <?php elseif ($profileUser['profile_visibility'] === 'friends_only'): ?>
        <section class="profile-watchlist-private card">
            <h2>Watchlist</h2>
            <p><em>La watchlist de cet utilisateur est visible uniquement par ses amis.</em></p>
        </section>
    <?php elseif ($profileUser['profile_visibility'] === 'private'): ?>
         <section class="profile-watchlist-private card">
            <h2>Watchlist</h2>
            <p><em>La watchlist de cet utilisateur est privée.</em></p>
        </section>
    <?php endif; ?>

    <?php if ($canViewFullProfile): ?>
    <section class="profile-annotations card">
        <h2>Annotations de Scènes par <?php echo htmlspecialchars($profileUser['username'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo count($profileUserSceneAnnotations); ?>)</h2>
        <?php if ($annotationsError): ?>
            <div class="alert alert-warning"><?php echo htmlspecialchars($annotationsError); ?></div>
        <?php elseif (!empty($profileUserSceneAnnotations)): ?>
            <ul class="annotations-list profile-annotations-list">
                <?php foreach ($profileUserSceneAnnotations as $annotation): ?>
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
        <?php else: ?>
            <p><?php echo htmlspecialchars($profileUser['username'], ENT_QUOTES, 'UTF-8'); ?> n'a pas encore créé d'annotations de scènes.</p>
        <?php endif; ?>
    </section>
<?php elseif ($profileUser['profile_visibility'] === 'friends_only' && !$canViewFullProfile): // Not friends, so can't see annotations ?>
    <section class="profile-annotations-private card">
        <h2>Annotations de Scènes</h2>
        <p><em>Les annotations de scènes de cet utilisateur sont visibles uniquement par ses amis.</em></p>
    </section>
<?php elseif ($profileUser['profile_visibility'] === 'private'): // Profile is private ?>
     <section class="profile-annotations-private card">
        <h2>Annotations de Scènes</h2>
        <p><em>Les annotations de scènes de cet utilisateur sont privées.</em></p>
    </section>
<?php endif; ?>
</main>

<?php
include_once 'includes/footer.php';
?>