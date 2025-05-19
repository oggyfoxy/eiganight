<?php
/*
 * forum.php
 * Displays a list of forum threads.
 */
include_once 'config.php'; // Includes session_start(), $conn, TMDB_API_KEY, BASE_URL
// Assumes functions.php (with CSRF functions) is included via config.php or directly

$pageTitle = "Forum des discussions - Eiganights";
$threads = [];
$loggedInUserId = $_SESSION['user_id'] ?? null; // Get logged-in user's ID
$forum_error_message = null; // For errors fetching threads

// SQL to fetch threads, ensuring we have the thread author's ID
$sql = "SELECT ft.id, ft.title, ft.movie_title, ft.movie_id, ft.created_at, ft.updated_at, 
               u.username as author_username, 
               ft.user_id as thread_author_id, 
               ft.scene_start_time, ft.scene_description_short,
               (SELECT COUNT(*) FROM forum_posts fp WHERE fp.thread_id = ft.id) as reply_count,
               (SELECT fp.created_at FROM forum_posts fp WHERE fp.thread_id = ft.id ORDER BY fp.created_at DESC LIMIT 1) as last_reply_time -- Get actual time of last reply
        FROM forum_threads ft
        JOIN users u ON ft.user_id = u.id
        ORDER BY ft.updated_at DESC, ft.created_at DESC
        LIMIT 50"; // Consider pagination for more threads

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed (FORUM_LIST_SEL): " . $conn->error);
    $forum_error_message = "Erreur système lors du chargement du forum. (FL01)";
} else {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $threads[] = $row;
        }
    } else {
        error_log("Execute failed (FORUM_LIST_SEL): " . $stmt->error);
        $forum_error_message = "Erreur lors du chargement des discussions. (FL02)";
    }
    $stmt->close();
}

// Ensure CSRF token function is available for forms
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() { return 'csrf_fallback_token_forum'; } 
    error_log("CSRF function generate_csrf_token() not found in forum.php context.");
}


include_once 'includes/header.php';
?>

<main class="container forum-page">
    <h1>Forum des Discussions</h1>

    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="create-thread-link">
            <a href="<?php echo BASE_URL; ?>forum_create_thread.php" class="button-primary">Créer une Nouvelle Discussion</a>
        </div>
    <?php else: ?>
        <p class="login-prompt-forum"><a href="<?php echo BASE_URL; ?>login.php?redirect=<?php echo urlencode(BASE_URL . 'forum_create_thread.php'); ?>">Connectez-vous</a> pour créer une discussion.</p>
    <?php endif; ?>

    <?php // Display session messages ?>
    <?php if (!empty($_SESSION['forum_message'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['forum_message']); unset($_SESSION['forum_message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($forum_error_message)): /* Use dedicated variable for thread list errors */ ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($forum_error_message); ?></div>
    <?php elseif (!empty($_SESSION['forum_error'])): /* Catchall for other forum related errors */ ?>
         <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['forum_error']); unset($_SESSION['forum_error']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['warning'])): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars($_SESSION['warning']); unset($_SESSION['warning']); ?></div>
    <?php endif; ?>


    <?php if (!empty($threads)): ?>
        <div class="table-responsive-container card"> <?php // Wrapper for table for better responsive handling ?>
            <table class="forum-threads-table">
                <thead>
                    <tr>
                        <th>Sujet de Discussion</th>
                        <th class="film-column">Film Associé</th>
                        <th class="author-column">Auteur</th>
                        <th class="replies-column">Réponses</th>
                        <th class="activity-column">Dernière Activité</th>
                        <?php if ($loggedInUserId): ?>
                            <th class="actions-column">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($threads as $thread): ?>
                        <tr>
                            <td>
                                <a href="<?php echo BASE_URL; ?>forum_view_thread.php?id=<?php echo (int)$thread['id']; ?>">
                                    <?php echo htmlspecialchars($thread['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                                <?php if (!empty($thread['scene_start_time']) || !empty($thread['scene_description_short'])): ?>
                                    <span class="scene-annotation-tag" title="Annotation de scène">[Scène]</span>
                                <?php endif; ?>
                            </td>
                            <td class="film-column">
                                <a href="<?php echo BASE_URL; ?>movie_details.php?id=<?php echo (int)$thread['movie_id']; ?>">
                                    <?php echo htmlspecialchars($thread['movie_title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <?php if (isset($thread['author_user_id'], $thread['author_username'])): ?>
    <a href="view_profile.php?id=<?php echo (int)$thread['author_user_id']; ?>">
        <?php echo htmlspecialchars($thread['author_username'], ENT_QUOTES, 'UTF-8'); ?>
    </a>
<?php else: ?>
    <span>Auteur inconnu</span>
<?php endif; ?>


                            <td>
                                <a href="view_profile.php?id=<?php echo (int)$thread['thread_author_id']; ?>">
    <?php echo htmlspecialchars($thread['author_username'], ENT_QUOTES, 'UTF-8'); ?>
</a>

                            </td>
                            <td class="replies-column"><?php echo (int)$thread['reply_count']; ?></td>
                            <td class="activity-column">
                                <?php
                                // If last_reply_time is null (no replies), use thread's creation time
                                $last_active = $thread['last_reply_time'] ?? $thread['created_at'];
                                echo date('d/m/Y H:i', strtotime($last_active));
                                ?>
                            </td>
                            <?php if ($loggedInUserId): ?>
                                <td class="actions-column">
                                    <div class="action-buttons-group">
                                    <?php if ($loggedInUserId === (int)$thread['thread_author_id'] || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')): ?>
                                        <a href="<?php echo BASE_URL; ?>forum_edit_thread.php?id=<?php echo (int)$thread['id']; ?>" class="button button-secondary button-small" title="Modifier">Mod.</a>
                                        <form action="<?php echo BASE_URL; ?>forum_delete_thread.php" method="POST" class="inline-form" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette discussion et toutes ses réponses ? Cette action est irréversible.');">
                                            <input type="hidden" name="thread_id" value="<?php echo (int)$thread['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars(BASE_URL . 'forum.php', ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="button button-danger button-small" title="Supprimer">Suppr.</button>
                                        </form>
                                    <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif (!$forum_error_message): // Only show "no discussions" if there wasn't an error loading them ?>
        <p>Aucune discussion n'a encore été créée.
            <?php if ($loggedInUserId): ?>
                <a href="<?php echo BASE_URL; ?>forum_create_thread.php">Soyez le premier !</a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>login.php?redirect=<?php echo urlencode(BASE_URL . 'forum_create_thread.php'); ?>">Connectez-vous</a> pour en créer une.
            <?php endif; ?>
        </p>
    <?php endif; ?>
</main>

<?php include_once 'includes/footer.php'; ?>