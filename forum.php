<?php
include_once 'config.php';

$pageTitle = "Forum des discussions - Eiganights";
$threads = [];

$sql = "SELECT ft.id, ft.title, ft.movie_title, ft.movie_id, ft.created_at, ft.updated_at, 
               u.username as author_username, u.id as author_user_id, -- Added author_user_id
               ft.scene_start_time, ft.scene_description_short, -- Added scene details
               (SELECT COUNT(*) FROM forum_posts fp WHERE fp.thread_id = ft.id) as reply_count,
               (SELECT MAX(fp.created_at) FROM forum_posts fp WHERE fp.thread_id = ft.id) as last_reply_time
        FROM forum_threads ft
        JOIN users u ON ft.user_id = u.id
        ORDER BY ft.updated_at DESC, ft.created_at DESC
        LIMIT 50";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $threads[] = $row;
        }
    } else {
        error_log("Execute failed (FORUM_LIST_SEL): " . $stmt->error);
        $_SESSION['forum_error'] = "Erreur lors du chargement des discussions.";
    }
    $stmt->close();
} else {
    error_log("Prepare failed (FORUM_LIST_SEL): " . $conn->error);
    $_SESSION['forum_error'] = "Erreur système lors du chargement du forum.";
}

include_once 'includes/header.php';
?>

<main class="container forum-page">
    <h1>Forum des Discussions</h1>

    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="create-thread-link">
            <a href="forum_create_thread.php" class="button-primary">Créer une Nouvelle Discussion</a>
        </div>
    <?php else: ?>
        <p><a href="login.php?redirect=forum.php">Connectez-vous</a> pour créer une discussion.</p>
    <?php endif; ?>

    <?php if (!empty($_SESSION['forum_message'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['forum_message']); unset($_SESSION['forum_message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['forum_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['forum_error']); unset($_SESSION['forum_error']); ?></div>
    <?php endif; ?>

    <?php if (!empty($threads)): ?>
        <table class="forum-threads-table">
            <thead>
                <tr>
                    <th>Sujet de Discussion</th>
                    <th>Film Associé</th>
                    <th>Auteur</th>
                    <th>Réponses</th>
                    <th>Dernière Activité</th>
                </tr>
            </thead>
                            <!-- Updated <tbody> in forum.php -->
                <tbody>
                    <?php foreach ($threads as $thread): ?>
                        <tr>
                            <td>
                                <a href="forum_view_thread.php?id=<?php echo (int)$thread['id']; ?>">
                                    <?php echo htmlspecialchars($thread['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                                <?php if (!empty($thread['scene_start_time']) || !empty($thread['scene_description_short'])): ?>
                                    <span class="scene-annotation-tag">[Annotation de Scène]</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="movie_details.php?id=<?php echo (int)$thread['movie_id']; ?>">
                                    <?php echo htmlspecialchars($thread['movie_title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td>
                                <a href="view_profile.php?id=<?php echo (int)$thread['author_user_id']; ?>"> {/* Use author_user_id */}
                                    <?php echo htmlspecialchars($thread['author_username'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td><?php echo (int)$thread['reply_count']; ?></td>
                            <td>
                                <?php
                                $last_active = $thread['last_reply_time'] ? $thread['last_reply_time'] : $thread['created_at'];
                                echo date('d/m/Y H:i', strtotime($last_active));
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
        </table>
    <?php elseif (empty($_SESSION['forum_error'])): ?>
        <p>Aucune discussion n'a encore été créée. Soyez le premier !</p>
    <?php endif; ?>
</main>

<?php include_once 'includes/footer.php'; ?>