<?php
/*
 * forum_view_thread.php
 * Displays a single forum thread and its posts, allows replies, and thread actions (edit/delete) for authorized users.
 */
include_once 'config.php'; // Includes session_start(), $conn, TMDB_API_KEY, BASE_URL
// Assumes functions.php (with CSRF functions) is included via config.php or directly

// Validate thread ID from GET parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || (int)$_GET['id'] <= 0) {
    $_SESSION['forum_error'] = "ID de discussion invalide ou manquant.";
    header('Location: ' . BASE_URL . 'forum.php');
    exit;
}
$thread_id = (int)$_GET['id'];
$loggedInUserId = $_SESSION['user_id'] ?? null;

// --- Fetch Thread Details ---
$thread = null;
$sql_thread = "SELECT ft.id, ft.title, ft.initial_post_content, ft.movie_id, ft.movie_title, 
                      ft.created_at as thread_created_at, ft.user_id as thread_author_actual_id,
                      u.username as thread_author_username, 
                      ft.scene_start_time, ft.scene_end_time, ft.scene_description_short
               FROM forum_threads ft
               JOIN users u ON ft.user_id = u.id
               WHERE ft.id = ?";
$stmt_thread = $conn->prepare($sql_thread);

if (!$stmt_thread) {
    error_log("Prepare failed (VIEW_THREAD_SEL): " . $conn->error);
    $_SESSION['forum_error'] = "Erreur système lors du chargement de la discussion. (FVT01)";
    header('Location: ' . BASE_URL . 'forum.php'); exit;
}

$stmt_thread->bind_param("i", $thread_id);
if (!$stmt_thread->execute()) {
    error_log("Execute failed (VIEW_THREAD_SEL): " . $stmt_thread->error);
    $_SESSION['forum_error'] = "Erreur lors du chargement de la discussion. (FVT02)";
    $stmt_thread->close();
    header('Location: ' . BASE_URL . 'forum.php'); exit;
}

$result_thread = $stmt_thread->get_result();
if (!($thread = $result_thread->fetch_assoc())) {
    $_SESSION['forum_error'] = "Discussion non trouvée ou inaccessible.";
    $stmt_thread->close();
    header('Location: ' . BASE_URL . 'forum.php');
    exit;
}
$stmt_thread->close();

$pageTitle = htmlspecialchars($thread['title'], ENT_QUOTES, 'UTF-8') . " - Forum Eiganights";

// --- Handle New Post/Reply Submission ---
$post_error_message = ''; // Specific error for post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_content']) && $loggedInUserId) {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !function_exists('validate_csrf_token') || !validate_csrf_token($_POST['csrf_token'])) {
        $post_error_message = "Erreur de sécurité (jeton invalide). Veuillez rafraîchir la page et réessayer.";
    } else {
        $post_content = trim($_POST['post_content']);
        // $parent_post_id = isset($_POST['parent_post_id']) && is_numeric($_POST['parent_post_id']) ? (int)$_POST['parent_post_id'] : null; // For threaded replies

        if (empty($post_content)) {
            $post_error_message = "Le contenu de la réponse ne peut pas être vide.";
        } else {
            $sql_insert_post = "INSERT INTO forum_posts (thread_id, user_id, content) VALUES (?, ?, ?)";
            $stmt_insert_post = $conn->prepare($sql_insert_post);
            if (!$stmt_insert_post) {
                error_log("Prepare failed (INSERT_POST): " . $conn->error);
                $post_error_message = "Erreur système lors de l'ajout de la réponse. (FVT03)";
            } else {
                $stmt_insert_post->bind_param("iis", $thread_id, $loggedInUserId, $post_content);
                if ($stmt_insert_post->execute()) {
                    $newPostId = $conn->insert_id;
                    // Update thread's updated_at timestamp
                    $updateThreadTimestampSql = "UPDATE forum_threads SET updated_at = NOW() WHERE id = ?";
                    $stmtUpdateThread = $conn->prepare($updateThreadTimestampSql);
                    if($stmtUpdateThread) {
                        $stmtUpdateThread->bind_param("i", $thread_id);
                        $stmtUpdateThread->execute(); // Errors here are less critical for user but should be logged
                        if (!$stmtUpdateThread->execute()) error_log("Execute failed (UPDATE_THREAD_TS_ON_POST): " . $stmtUpdateThread->error);
                        $stmtUpdateThread->close();
                    } else {
                         error_log("Prepare failed (UPDATE_THREAD_TS_ON_POST): " . $conn->error);
                    }
                    
                    $_SESSION['forum_message'] = "Réponse ajoutée avec succès.";
                    if (function_exists('generate_csrf_token')) unset($_SESSION['csrf_token']); // Consume CSRF token on success
                    header("Location: " . BASE_URL . "forum_view_thread.php?id=" . $thread_id . "#post-" . $newPostId);
                    exit;
                } else {
                    error_log("Execute failed (INSERT_POST): " . $stmt_insert_post->error);
                    $post_error_message = "Erreur lors de l'enregistrement de la réponse. (FVT04)";
                }
                $stmt_insert_post->close();
            }
        }
    }
    // Regenerate CSRF if form submission failed to prevent reuse on next attempt
    if (!empty($post_error_message) && function_exists('generate_csrf_token')) unset($_SESSION['csrf_token']);
}

// --- Fetch Posts for this Thread ---
$posts = [];
$posts_load_error = null;
$sql_posts = "SELECT fp.id, fp.content, fp.created_at, u.username as post_author_username, u.id as post_author_id
              FROM forum_posts fp
              JOIN users u ON fp.user_id = u.id
              WHERE fp.thread_id = ?
              ORDER BY fp.created_at ASC";
$stmt_posts = $conn->prepare($sql_posts);
if (!$stmt_posts) {
    error_log("Prepare failed (VIEW_POSTS_SEL): " . $conn->error);
    $posts_load_error = "Erreur système lors du chargement des réponses. (FVT05)";
} else {
    $stmt_posts->bind_param("i", $thread_id);
    if ($stmt_posts->execute()) {
        $result_posts = $stmt_posts->get_result();
        while ($row = $result_posts->fetch_assoc()) {
            $posts[] = $row;
        }
    } else {
        error_log("Execute failed (VIEW_POSTS_SEL): " . $stmt_posts->error);
        $posts_load_error = "Erreur lors du chargement des réponses. (FVT06)";
    }
    $stmt_posts->close();
}

// Ensure CSRF token generation function is available for forms
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() { return 'csrf_fallback_token_fvt'; } // Simple fallback
    error_log("CSRF function generate_csrf_token() not found in forum_view_thread.php context.");
}

include_once 'includes/header.php';
?>
<main class="container view-thread-page">
    <nav aria-label="breadcrumb" class="forum-breadcrumb">
        <ol>
            <li><a href="<?php echo BASE_URL; ?>forum.php">Forum</a></li>
            <li><?php echo htmlspecialchars($thread['title'], ENT_QUOTES, 'UTF-8'); ?></li>
        </ol>
    </nav>

    <header class="thread-main-header card">
        <h1><?php echo htmlspecialchars($thread['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="thread-meta">
            Discussion pour le film: 
            <a href="<?php echo BASE_URL; ?>movie_details.php?id=<?php echo (int)$thread['movie_id']; ?>">
                <?php echo htmlspecialchars($thread['movie_title'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </p>
        <?php if ($loggedInUserId && ((int)$loggedInUserId === (int)$thread['thread_author_actual_id'] || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'))): ?>
            <div class="thread-actions">
                <a href="<?php echo BASE_URL; ?>forum_edit_thread.php?id=<?php echo (int)$thread['id']; ?>" class="button button-secondary button-small">Modifier</a>
                <form action="<?php echo BASE_URL; ?>forum_delete_thread.php" method="POST" class="inline-form" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette discussion et toutes ses réponses ? Cette action est irréversible.');">
                    <input type="hidden" name="thread_id" value="<?php echo (int)$thread['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="redirect_url" value="<?php echo BASE_URL . 'forum.php'; ?>"> <?php // Redirect to forum list on delete ?>
                    <button type="submit" class="button button-danger button-small">Supprimer</button>
                </form>
            </div>
        <?php endif; ?>
    </header>

    <?php if (!empty($thread['scene_start_time']) || !empty($thread['scene_description_short'])): ?>
        <section class="scene-annotation-info card">
            <h3>Scène Annotée :</h3>
            <?php if (!empty($thread['scene_description_short'])): ?>
                <p class="scene-desc"><strong>Description:</strong> <?php echo htmlspecialchars($thread['scene_description_short'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if (!empty($thread['scene_start_time'])): ?>
                <p class="scene-time">
                    <strong>Temps:</strong> <?php echo htmlspecialchars($thread['scene_start_time'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($thread['scene_end_time'])): ?>
                        - <?php echo htmlspecialchars($thread['scene_end_time'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php // Display session messages and form errors ?>
    <?php if (!empty($_SESSION['forum_message'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['forum_message']); unset($_SESSION['forum_message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($post_error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($post_error_message); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['forum_error'])): /* For general thread loading errors */ ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['forum_error']); unset($_SESSION['forum_error']); ?></div>
    <?php endif; ?>
    
    <article class="forum-post original-post card" id="thread-op-<?php echo (int)$thread['id']; ?>">
        <header class="post-header">
            <a href="<?php echo BASE_URL; ?>view_profile.php?id=<?php echo (int)$thread['thread_author_actual_id']; ?>" class="post-author-link">
                <strong><?php echo htmlspecialchars($thread['thread_author_username'], ENT_QUOTES, 'UTF-8'); ?></strong>
            </a>
            <time datetime="<?php echo date('c', strtotime($thread['thread_created_at'])); ?>" class="post-date">
                a posté le <?php echo date('d/m/Y à H:i', strtotime($thread['thread_created_at'])); ?>
            </time>
        </header>
        <div class="post-content user-content">
            <?php echo nl2br(htmlspecialchars($thread['initial_post_content'], ENT_QUOTES, 'UTF-8')); ?>
        </div>
    </article>

    <section class="forum-replies" aria-labelledby="replies-heading">
        <h2 id="replies-heading">Réponses (<?php echo count($posts); ?>)</h2>
        <?php if ($posts_load_error): ?>
             <div class="alert alert-warning"><?php echo htmlspecialchars($posts_load_error); ?></div>
        <?php endif; ?>

        <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
                <article class="forum-post reply-post card" id="post-<?php echo (int)$post['id']; ?>">
                    <header class="post-header">
                         <a href="<?php echo BASE_URL; ?>view_profile.php?id=<?php echo (int)$post['post_author_id']; ?>" class="post-author-link">
                            <strong><?php echo htmlspecialchars($post['post_author_username'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        </a>
                        <time datetime="<?php echo date('c', strtotime($post['created_at'])); ?>" class="post-date">
                            a répondu le <?php echo date('d/m/Y à H:i', strtotime($post['created_at'])); ?>
                        </time>
                    </header>
                    <div class="post-content user-content">
                        <?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                    <?php /* Placeholder for future actions on posts 
                    <footer class="post-actions">
                        <?php if ($loggedInUserId && ((int)$loggedInUserId === (int)$post['post_author_id'] || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'))): ?>
                            <a href="<?php echo BASE_URL; ?>forum_edit_post.php?id=<?php echo (int)$post['id']; ?>" class="button-secondary button-small">Modifier</a>
                        <?php endif; ?>
                    </footer>
                    */ ?>
                </article>
            <?php endforeach; ?>
        <?php elseif (!$posts_load_error): // Only show "no replies" if there wasn't an error loading them ?>
            <p>Aucune réponse pour le moment. <?php if ($loggedInUserId) echo "Soyez le premier à répondre !"; ?></p>
        <?php endif; ?>
    </section>

    <?php if ($loggedInUserId): ?>
        <section class="reply-form-section card" id="reply-form" aria-labelledby="reply-form-heading">
            <h2 id="reply-form-heading">Laisser une Réponse</h2>
            <form method="POST" action="<?php echo BASE_URL; ?>forum_view_thread.php?id=<?php echo $thread_id; ?>" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="post_content" class="visually-hidden">Votre réponse</label>
                    <textarea name="post_content" id="post_content" rows="6" placeholder="Écrivez votre réponse ici..." required></textarea>
                </div>
                <button type="submit" class="button-primary">Envoyer la Réponse</button>
            </form>
        </section>
    <?php else: ?>
        <p class="login-to-reply"><a href="<?php echo BASE_URL; ?>login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Connectez-vous</a> pour répondre à cette discussion.</p>
    <?php endif; ?>

</main>
<?php include_once 'includes/footer.php'; ?>