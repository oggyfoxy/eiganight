<?php
include_once 'config.php';
include_once 'includes/functions.php';

$loggedInUserId = $_SESSION['user_id'] ?? null;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['forum_error'] = "ID de discussion invalide.";
    header('Location: ' . BASE_URL . 'forum.php');
    exit;
}
$thread_id = (int)$_GET['id'];

$thread = null;
$sql_thread = "SELECT ft.id, ft.title, ft.initial_post_content, ft.movie_id, ft.movie_title,
                      ft.created_at as thread_created_at,
                      u.username as thread_author_username, u.id as thread_author_id,
                      ft.scene_start_time, ft.scene_end_time, ft.scene_description_short
               FROM forum_threads ft
               JOIN users u ON ft.user_id = u.id
               WHERE ft.id = ?";
$stmt_thread = $conn->prepare($sql_thread);
if ($stmt_thread) {
    $stmt_thread->bind_param("i", $thread_id);
    if ($stmt_thread->execute()) {
        $result_thread = $stmt_thread->get_result();
        if (!($thread = $result_thread->fetch_assoc())) {
            $_SESSION['forum_error'] = "Discussion non trouvée.";
            header('Location: ' . BASE_URL . 'forum.php');
            exit;
        }
    } else {
        error_log("Execute failed (VIEW_THREAD_SEL): " . $stmt_thread->error);
        $_SESSION['forum_error'] = "Erreur chargement discussion.";
        header('Location: ' . BASE_URL . 'forum.php'); exit;
    }
    $stmt_thread->close();
} else {
    error_log("Prepare failed (VIEW_THREAD_SEL): " . $conn->error);
    $_SESSION['forum_error'] = "Erreur système (discussion).";
    header('Location: ' . BASE_URL . 'forum.php'); exit;
}

$pageTitle = htmlspecialchars($thread['title'], ENT_QUOTES, 'UTF-8') . " - Forum Eiganights";

$post_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_content']) && $loggedInUserId) {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $post_error = "Erreur de sécurité (jeton invalide). Veuillez rafraîchir la page et réessayer.";
    } else {
        $post_content = trim($_POST['post_content']);
        if (empty($post_content)) {
            $post_error = "Le contenu de la réponse ne peut pas être vide.";
        } else {
            $sql_insert_post = "INSERT INTO forum_posts (thread_id, user_id, content) VALUES (?, ?, ?)";
            $stmt_insert_post = $conn->prepare($sql_insert_post);
            if ($stmt_insert_post) {
                $stmt_insert_post->bind_param("iis", $thread_id, $loggedInUserId, $post_content);
                if ($stmt_insert_post->execute()) {
                    $newPostId = $conn->insert_id;
                    $conn->query("UPDATE forum_threads SET updated_at = NOW() WHERE id = " . $thread_id);
                    unset($_SESSION['csrf_token']);
                    $_SESSION['forum_message'] = "Réponse ajoutée.";
                    header("Location: " . BASE_URL . "forum_view_thread.php?id=" . $thread_id . "#post-" . $newPostId);
                    exit;
                } else {
                    error_log("Execute failed (INSERT_POST): " . $stmt_insert_post->error);
                    $post_error = "Erreur lors de l'ajout de la réponse.";
                }
                $stmt_insert_post->close();
            } else {
                error_log("Prepare failed (INSERT_POST): " . $conn->error);
                $post_error = "Erreur système (réponse).";
            }
        }
    }
    if (!empty($post_error)) {
        unset($_SESSION['csrf_token']);
    }
}

$posts = [];
$sql_posts = "SELECT fp.id, fp.content, fp.created_at, u.username as post_author_username, u.id as post_author_id
              FROM forum_posts fp
              JOIN users u ON fp.user_id = u.id
              WHERE fp.thread_id = ?
              ORDER BY fp.created_at ASC";
$stmt_posts = $conn->prepare($sql_posts);
if ($stmt_posts) {
    $stmt_posts->bind_param("i", $thread_id);
    if ($stmt_posts->execute()) {
        $result_posts = $stmt_posts->get_result();
        while ($row = $result_posts->fetch_assoc()) {
            $posts[] = $row;
        }
    } else {
        error_log("Execute failed (VIEW_POSTS_SEL): " . $stmt_posts->error);
        $_SESSION['message_error_on_page'] = "Erreur chargement réponses.";
    }
    $stmt_posts->close();
} else {
    error_log("Prepare failed (VIEW_POSTS_SEL): " . $conn->error);
    $_SESSION['message_error_on_page'] = "Erreur système (V04).";
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

    <h1><?php echo htmlspecialchars($thread['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="thread-meta">
        Discussion pour le film: <a href="<?php echo BASE_URL; ?>movie_details.php?id=<?php echo (int)$thread['movie_id']; ?>"><?php echo htmlspecialchars($thread['movie_title'], ENT_QUOTES, 'UTF-8'); ?></a>
    </p>

    <?php if (!empty($_SESSION['forum_message'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['forum_message']); unset($_SESSION['forum_message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['message_error_on_page'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['message_error_on_page']); unset($_SESSION['message_error_on_page']); ?></div>
    <?php endif; ?>
    <?php if (!empty($post_error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($post_error); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($thread['scene_start_time']) || !empty($thread['scene_description_short'])): ?>
        <section class="scene-annotation-info card">
            <h3>Scène Annotée :</h3>
            <?php if (!empty($thread['scene_description_short'])): ?>
                <p class="scene-desc"><strong>Description de la scène :</strong> <?php echo htmlspecialchars($thread['scene_description_short'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if (!empty($thread['scene_start_time'])): ?>
                <p class="scene-time">
                    <strong>Temps :</strong> <?php echo htmlspecialchars($thread['scene_start_time'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($thread['scene_end_time'])): ?>
                        - <?php echo htmlspecialchars($thread['scene_end_time'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <article class="forum-post original-post card" id="thread-op">
        <header class="post-header">
            <a href="<?php echo BASE_URL; ?>view_profile.php?id=<?php echo (int)$thread['thread_author_id']; ?>">
                <strong><?php echo htmlspecialchars($thread['thread_author_username'], ENT_QUOTES, 'UTF-8'); ?></strong>
            </a>
            <span class="post-date">a posté le <?php echo date('d/m/Y à H:i', strtotime($thread['thread_created_at'])); ?></span>
            <?php if ($loggedInUserId === (int)$thread['thread_author_id'] || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')): ?>
                <a href="<?php echo BASE_URL; ?>forum_edit_thread.php?id=<?php echo $thread_id; ?>" class="edit-post-link button-small button-secondary">Modifier</a>
                <form method="POST" action="<?php echo BASE_URL; ?>forum_delete_thread.php" class="inline-form">
                    <input type="hidden" name="thread_id" value="<?php echo $thread_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="button-small button-danger">Supprimer</button>
                </form>
            <?php endif; ?>
        </header>
        <div class="post-content">
            <?php echo nl2br(htmlspecialchars($thread['initial_post_content'], ENT_QUOTES, 'UTF-8')); ?>
        </div>
    </article>

    <section class="forum-replies">
        <h2>Réponses (<?php echo count($posts); ?>)</h2>
        <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
                <article class="forum-post card" id="post-<?php echo (int)$post['id']; ?>">
                    <header class="post-header">
                         <a href="<?php echo BASE_URL; ?>view_profile.php?id=<?php echo (int)$post['post_author_id']; ?>">
                            <strong><?php echo htmlspecialchars($post['post_author_username'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        </a>
                        <span class="post-date">a répondu le <?php echo date('d/m/Y à H:i', strtotime($post['created_at'])); ?></span>
                    </header>
                    <div class="post-content">
                        <?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Aucune réponse pour le moment. Soyez le premier à répondre !</p>
        <?php endif; ?>
    </section>

    <?php if ($loggedInUserId): ?>
        <section class="reply-form-section card" id="reply-form">
            <h2>Laisser une Réponse</h2>
            <form method="POST" action="<?php echo BASE_URL; ?>forum_view_thread.php?id=<?php echo $thread_id; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="post_content" class="visually-hidden">Votre réponse</label>
                    <textarea name="post_content" id="post_content" rows="6" placeholder="Écrivez votre réponse ici..." required></textarea>
                </div>
                <button type="submit" class="button-primary">Envoyer la Réponse</button>
            </form>
        </section>
    <?php else: ?>
        <p><a href="<?php echo BASE_URL; ?>login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Connectez-vous</a> pour répondre à cette discussion.</p>
    <?php endif; ?>
    <div id="latest_message"></div>
</main>
<?php include_once 'includes/footer.php'; ?>