<?php
include_once 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['forum_error'] = "ID de discussion invalide.";
    header('Location: forum.php');
    exit;
}
$thread_id = (int)$_GET['id'];
$loggedInUserId = $_SESSION['user_id'] ?? null;

// Fetch Thread Details
$thread = null;
$sql_thread = "SELECT ft.id, ft.title, ft.initial_post_content, ft.movie_id, ft.movie_title, 
                      ft.created_at as thread_created_at, 
                      u.username as thread_author_username, u.id as thread_author_id,
                      ft.scene_start_time, ft.scene_end_time, ft.scene_description_short -- << NEW
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
            header('Location: forum.php');
            exit;
        }
    } else {
        error_log("Execute failed (VIEW_THREAD_SEL): " . $stmt_thread->error);
        $_SESSION['forum_error'] = "Erreur lors du chargement de la discussion.";
        header('Location: forum.php'); exit;
    }
    $stmt_thread->close();
} else {
    error_log("Prepare failed (VIEW_THREAD_SEL): " . $conn->error);
    $_SESSION['forum_error'] = "Erreur système (discussion).";
    header('Location: forum.php'); exit;
}

$pageTitle = htmlspecialchars($thread['title']) . " - Forum Eiganights";

// Handle New Post/Comment
$post_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_content']) && $loggedInUserId) {
    $post_content = trim($_POST['post_content']);
    // $parent_post_id = isset($_POST['parent_post_id']) && is_numeric($_POST['parent_post_id']) ? (int)$_POST['parent_post_id'] : null; // For threaded replies later

    if (empty($post_content)) {
        $post_error = "Le contenu de la réponse ne peut pas être vide.";
    } else {
        $sql_insert_post = "INSERT INTO forum_posts (thread_id, user_id, content) VALUES (?, ?, ?)";
        $stmt_insert_post = $conn->prepare($sql_insert_post);
        if ($stmt_insert_post) {
            $stmt_insert_post->bind_param("iis", $thread_id, $loggedInUserId, $post_content);
            if ($stmt_insert_post->execute()) {
                // Update thread's updated_at timestamp
                $conn->query("UPDATE forum_threads SET updated_at = NOW() WHERE id = " . $thread_id);
                $_SESSION['forum_message'] = "Réponse ajoutée.";
                header("Location: forum_view_thread.php?id=" . $thread_id . "#post-" . $conn->insert_id); // Redirect to new post
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


// Fetch Posts for this Thread
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
        // Display error on page if posts fail to load but thread loaded
    }
    $stmt_posts->close();
} else {
    error_log("Prepare failed (VIEW_POSTS_SEL): " . $conn->error);
}


include_once 'includes/header.php';
?>
<main class="container view-thread-page">
    <nav aria-label="breadcrumb" class="forum-breadcrumb">
        <ol>
            <li><a href="forum.php">Forum</a></li>
            <li><?php echo htmlspecialchars($thread['title']); ?></li>
        </ol>
    </nav>

    <h1><?php echo htmlspecialchars($thread['title']); ?></h1>
    <p class="thread-meta">
        Discussion pour le film: <a href="movie_details.php?id=<?php echo (int)$thread['movie_id']; ?>"><?php echo htmlspecialchars($thread['movie_title']); ?></a>
    </p>


        <!-- In forum_view_thread.php, after $thread_meta <p> tag -->

    <?php if (!empty($thread['scene_start_time']) || !empty($thread['scene_description_short'])): ?>
        <section class="scene-annotation-info card">
            <h3>Scène Annotée :</h3>
            <?php if (!empty($thread['scene_description_short'])): ?>
                <p class="scene-desc"><strong>Description de la scène :</strong> <?php echo htmlspecialchars($thread['scene_description_short']); ?></p>
            <?php endif; ?>
            <?php if (!empty($thread['scene_start_time'])): ?>
                <p class="scene-time">
                    <strong>Temps :</strong> <?php echo htmlspecialchars($thread['scene_start_time']); ?>
                    <?php if (!empty($thread['scene_end_time'])): ?>
                        - <?php echo htmlspecialchars($thread['scene_end_time']); ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <?php /* Future: Link to jump to scene in an embedded player if possible */ ?>
        </section>
    <?php endif; ?>

    <article class="forum-post original-post card" id="thread-op"> <!-- Existing OP -->

    <?php if (!empty($_SESSION['forum_message'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['forum_message']); unset($_SESSION['forum_message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($post_error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($post_error); ?></div>
    <?php endif; ?>
    
    <article class="forum-post original-post card" id="thread-op">
        <header class="post-header">
            <a href="view_profile.php?id=<?php echo (int)$thread['thread_author_id']; ?>">
                <strong><?php echo htmlspecialchars($thread['thread_author_username']); ?></strong>
            </a>
            <span class="post-date">a posté le <?php echo date('d/m/Y à H:i', strtotime($thread['thread_created_at'])); ?></span>
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
                         <a href="view_profile.php?id=<?php echo (int)$post['post_author_id']; ?>">
                            <strong><?php echo htmlspecialchars($post['post_author_username']); ?></strong>
                        </a>
                        <span class="post-date">a répondu le <?php echo date('d/m/Y à H:i', strtotime($post['created_at'])); ?></span>
                    </header>
                    <div class="post-content">
                        <?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                    <footer>
                        <?php /* Reply to specific post link - for later threaded replies */ ?>
                        <?php /* <a href="#reply-form" class="reply-to-post-link" data-parent-id="<?php echo (int)$post['id']; ?>">Répondre</a> */ ?>
                    </footer>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Aucune réponse pour le moment. Soyez le premier à répondre !</p>
        <?php endif; ?>
    </section>

    <?php if ($loggedInUserId): ?>
        <section class="reply-form-section card" id="reply-form">
            <h2>Laisser une Réponse</h2>
            <form method="POST" action="forum_view_thread.php?id=<?php echo $thread_id; ?>">
                <?php /* <input type="hidden" name="parent_post_id" id="parent_post_id_field" value=""> */ ?>
                <div class="form-group">
                    <label for="post_content" class="visually-hidden">Votre réponse</label>
                    <textarea name="post_content" id="post_content" rows="6" placeholder="Écrivez votre réponse ici..." required></textarea>
                </div>
                <button type="submit" class="button-primary">Envoyer la Réponse</button>
            </form>
        </section>
    <?php else: ?>
        <p><a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Connectez-vous</a> pour répondre à cette discussion.</p>
    <?php endif; ?>

</main>
<?php include_once 'includes/footer.php'; ?>