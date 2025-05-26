<?php
include_once 'config.php';
include_once 'includes/functions.php';

$pageTitle = "Forum des discussions - eiganights";
$loggedInUserId = $_SESSION['user_id'] ?? null;
$threads = [];

$sql = "SELECT
            ft.id, ft.title, ft.movie_title, ft.movie_id, ft.created_at, ft.updated_at,
            u.username as author_username,
            u.id as author_user_id,
            ft.scene_start_time, ft.scene_description_short,
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
            $row['is_unread_placeholder'] = ($loggedInUserId && isset($row['last_reply_time']) && !empty($row['last_reply_time']));
            $threads[] = $row;
        }
    } else {
        error_log("Execute failed (FORUM_LIST_THREADS): " . $stmt->error);
        $_SESSION['forum_error'] = "Erreur lors du chargement des discussions.";
    }
    $stmt->close();
} else {
    error_log("Prepare failed (FORUM_LIST_THREADS): " . $conn->error . " SQL: " . $sql);
    $_SESSION['forum_error'] = "Erreur système lors du chargement du forum.";
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
        <p><a href="<?php echo BASE_URL; ?>login.php?redirect=forum.php">Connectez-vous</a> pour créer une discussion.</p>
    <?php endif; ?>

    <?php if (!empty($_SESSION['forum_message'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['forum_message']); unset($_SESSION['forum_message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['forum_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['forum_error']); unset($_SESSION['forum_error']); ?></div>
    <?php endif; ?>

    <?php if (!empty($threads)): ?>
        <div class="inbox-table-container card">
            <table class="inbox-table forum-threads-table">
                <thead>
                    <tr>
                        <th>Sujet / Annotation</th>
                        <th class="message-partner-col">Film Associé</th>
                        <th class="message-author-col">Auteur</th>
                        <th>Réponses</th>
                        <th class="message-date-col">Dernière Activité</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($threads as $thread): ?>
                        <?php
                            $can_edit_delete = ($loggedInUserId && ($loggedInUserId === (int)$thread['author_user_id'] || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')));
                        ?>
                        <tr class="<?php echo ($thread['is_unread_placeholder']) ? 'unread' : ''; ?>">
                            <td class="message-subject">
                                <a href="<?php echo BASE_URL; ?>forum_view_thread.php?id=<?php echo (int)$thread['id']; ?>">
                                    <?php echo htmlspecialchars($thread['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                                <?php if (!empty($thread['scene_start_time']) || !empty($thread['scene_description_short'])): ?>
                                    <span class="scene-annotation-tag">[Annotation de Scène]</span>
                                <?php endif; ?>
                            </td>
                            <td class="message-partner-col">
                                <a href="<?php echo BASE_URL; ?>movie_details.php?id=<?php echo (int)$thread['movie_id']; ?>">
                                    <?php echo htmlspecialchars($thread['movie_title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td class="message-author-col">
                                <a href="<?php echo BASE_URL; ?>view_profile.php?id=<?php echo (int)$thread['author_user_id']; ?>">
                                    <?php echo htmlspecialchars($thread['author_username'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td><?php echo (int)$thread['reply_count']; ?></td>
                            <td class="message-date-col">
                                <?php
                                $last_active = !empty($thread['last_reply_time']) ? $thread['last_reply_time'] : $thread['created_at'];
                                echo date('d/m/Y H:i', strtotime($last_active));
                                ?>
                            </td>
                            <td class="actions-col">
                                <?php if ($can_edit_delete): ?>
                                    <a href="<?php echo BASE_URL; ?>forum_edit_thread.php?id=<?php echo (int)$thread['id']; ?>" class="button-small button-secondary">Éditer</a>
                                    <form method="POST" action="<?php echo BASE_URL; ?>forum_delete_thread.php" class="inline-form delete-thread-form" data-thread-id="<?php echo (int)$thread['id']; ?>">
                                        <input type="hidden" name="thread_id" value="<?php echo (int)$thread['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="button-small button-danger">Suppr.</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif (empty($_SESSION['forum_error'])): ?>
        <p class="card" style="padding: 20px; text-align: center;">
            Aucune discussion n'a encore été créée. 
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="<?php echo BASE_URL; ?>forum_create_thread.php">Soyez le premier !</a>
            <?php endif; ?>
        </p>
    <?php endif; ?>

</main>

<script>
// Handle delete confirmation with JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const deleteForms = document.querySelectorAll('.delete-thread-form');
    
    deleteForms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            const confirmed = confirm('Êtes-vous sûr de vouloir supprimer cette discussion et tous ses messages ? Cette action est irréversible.');
            
            if (!confirmed) {
                event.preventDefault();
            }
        });
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>