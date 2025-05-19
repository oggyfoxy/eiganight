<?php
/*
 * forum_edit_thread.php
 * Handles editing an existing forum thread by its author or an admin.
 */
include_once 'config.php'; // Includes session_start(), $conn, TMDB_API_KEY, BASE_URL

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour modifier une discussion.";
    header('Location: ' . BASE_URL . 'login.php'); // Consider redirecting back to the thread later
    exit;
}

$loggedInUserId = (int)$_SESSION['user_id'];
$thread_id_to_edit = null;
$existing_thread_data = null;

// Get thread ID from GET parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['forum_error'] = "ID de discussion invalide pour l'édition.";
    header('Location: ' . BASE_URL . 'forum.php');
    exit;
}
$thread_id_to_edit = (int)$_GET['id'];

// Fetch existing thread data to pre-fill form and check authorship
$sql_fetch = "SELECT user_id, movie_id, movie_title, title, initial_post_content, 
                     scene_start_time, scene_end_time, scene_description_short 
              FROM forum_threads WHERE id = ?";
$stmt_fetch = $conn->prepare($sql_fetch);
if (!$stmt_fetch) {
    error_log("Prepare failed (EDIT_THREAD_FETCH): " . $conn->error);
    $_SESSION['forum_error'] = "Erreur système lors du chargement de la discussion. (FET01)";
    header('Location: ' . BASE_URL . 'forum.php');
    exit;
}
$stmt_fetch->bind_param("i", $thread_id_to_edit);
if (!$stmt_fetch->execute()) {
    error_log("Execute failed (EDIT_THREAD_FETCH): " . $stmt_fetch->error);
    $_SESSION['forum_error'] = "Erreur lors du chargement des données de la discussion. (FET02)";
    $stmt_fetch->close();
    header('Location: ' . BASE_URL . 'forum.php');
    exit;
}
$result_fetch = $stmt_fetch->get_result();
if (!($existing_thread_data = $result_fetch->fetch_assoc())) {
    $_SESSION['forum_error'] = "Discussion non trouvée ou inaccessible pour l'édition.";
    $stmt_fetch->close();
    header('Location: ' . BASE_URL . 'forum.php');
    exit;
}
$stmt_fetch->close();

// Permission Check: User must be author OR admin
$isAuthor = ($loggedInUserId === (int)$existing_thread_data['user_id']);
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

if (!$isAuthor && !$isAdmin) {
    $_SESSION['forum_error'] = "Action non autorisée. Vous ne pouvez modifier que vos propres discussions.";
    header('Location: ' . BASE_URL . 'forum_view_thread.php?id=' . $thread_id_to_edit);
    exit;
}

$pageTitle = "Modifier la Discussion - Eiganights";

// Initialize form data with existing thread data
$form_data = [
    'movie_id' => $existing_thread_data['movie_id'],
    'movie_title_display' => $existing_thread_data['movie_title'], // Already stored, no need to re-fetch unless you want fresh TMDB data
    'thread_title' => $existing_thread_data['title'],
    'initial_post' => $existing_thread_data['initial_post_content'],
    'scene_start_time' => $existing_thread_data['scene_start_time'] ?? '',
    'scene_end_time' => $existing_thread_data['scene_end_time'] ?? '',
    'scene_description_short' => $existing_thread_data['scene_description_short'] ?? ''
];
$error_message = '';

// Handle Form Submission (POST) for update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!isset($_POST['csrf_token']) || !function_exists('validate_csrf_token') || !validate_csrf_token($_POST['csrf_token'])) {
        $error_message = "Erreur de sécurité (jeton invalide). Veuillez réessayer.";
    } else {
        // Note: movie_id and movie_title are generally not editable for an existing thread.
        // If you allow changing the associated movie, you'd need to re-implement the movie search here.
        // For simplicity, we'll assume these are fixed once a thread is created.
        $threadTitle = trim($_POST['thread_title'] ?? '');
        $initialPost = trim($_POST['initial_post'] ?? '');
        $sceneStartTime = trim($_POST['scene_start_time'] ?? '');
        $sceneEndTime = trim($_POST['scene_end_time'] ?? '');
        $sceneDescriptionShort = trim($_POST['scene_description_short'] ?? '');

        // Update form_data for repopulation if error
        $form_data['thread_title'] = $threadTitle;
        $form_data['initial_post'] = $initialPost;
        $form_data['scene_start_time'] = $sceneStartTime;
        $form_data['scene_end_time'] = $sceneEndTime;
        $form_data['scene_description_short'] = $sceneDescriptionShort;

        // Basic Validation
        if (empty($threadTitle)) {
            $error_message = "Le titre de la discussion est requis.";
        } elseif (empty($initialPost)) {
            $error_message = "Le contenu de la discussion est requis.";
        } else {
            // Prepare for UPDATE
            $sql_update = "UPDATE forum_threads SET 
                            title = ?, 
                            initial_post_content = ?, 
                            scene_start_time = ?, 
                            scene_end_time = ?, 
                            scene_description_short = ?,
                            updated_at = NOW()
                          WHERE id = ? AND user_id = ?"; // Ensure user can only update their own (unless admin)
            
            if ($isAdmin && !$isAuthor) { // Admin editing someone else's post
                 $sql_update = "UPDATE forum_threads SET 
                                title = ?, 
                                initial_post_content = ?, 
                                scene_start_time = ?, 
                                scene_end_time = ?, 
                                scene_description_short = ?,
                                updated_at = NOW()
                              WHERE id = ?";
            }


            $stmt_update = $conn->prepare($sql_update);
            if (!$stmt_update) {
                error_log("Prepare failed (EDIT_THREAD_UPD): " . $conn->error);
                $error_message = "Erreur système. (FET03)";
            } else {
                $sceneStartTimeDb = !empty($sceneStartTime) ? $sceneStartTime : null;
                $sceneEndTimeDb = !empty($sceneEndTime) ? $sceneEndTime : null;
                $sceneDescriptionShortDb = !empty($sceneDescriptionShort) ? $sceneDescriptionShort : null;

                if ($isAdmin && !$isAuthor) {
                    $stmt_update->bind_param("sssssi", $threadTitle, $initialPost, 
                                                   $sceneStartTimeDb, $sceneEndTimeDb, $sceneDescriptionShortDb, 
                                                   $thread_id_to_edit);
                } else {
                     $stmt_update->bind_param("sssssii", $threadTitle, $initialPost, 
                                                   $sceneStartTimeDb, $sceneEndTimeDb, $sceneDescriptionShortDb, 
                                                   $thread_id_to_edit, $loggedInUserId);
                }


                if ($stmt_update->execute()) {
                    if ($stmt_update->affected_rows > 0 || ($threadTitle === $existing_thread_data['title'] && $initialPost === $existing_thread_data['initial_post_content'] && $sceneStartTimeDb === $existing_thread_data['scene_start_time'] && $sceneEndTimeDb === $existing_thread_data['scene_end_time'] && $sceneDescriptionShortDb === $existing_thread_data['scene_description_short'])) {
                        $_SESSION['forum_message'] = "Discussion mise à jour avec succès.";
                        // Regenerate CSRF token after successful POST
                        if (function_exists('generate_csrf_token')) unset($_SESSION['csrf_token']);
                        header('Location: ' . BASE_URL . 'forum_view_thread.php?id=' . $thread_id_to_edit);
                        exit;
                    } else {
                         $error_message = "Aucune modification détectée ou permission refusée pour la mise à jour.";
                    }
                } else {
                    error_log("Execute failed (EDIT_THREAD_UPD): " . $stmt_update->error);
                    $error_message = "Erreur lors de la mise à jour de la discussion. (FET04)";
                }
                $stmt_update->close();
            }
        }
    }
    // Regenerate CSRF token if form submission failed to prevent reuse on next attempt
    if (function_exists('generate_csrf_token')) unset($_SESSION['csrf_token']);
}


include_once 'includes/header.php';
?>
<main class="container edit-thread-page">
    <h1>Modifier la Discussion</h1>
    <p><a href="<?php echo BASE_URL; ?>forum_view_thread.php?id=<?php echo $thread_id_to_edit; ?>">« Retour à la discussion</a></p>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <form method="POST" action="<?php echo BASE_URL; ?>forum_edit_thread.php?id=<?php echo $thread_id_to_edit; ?>" class="card">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        
        <div class="form-group">
            <label>Film Associé:</label>
            <p><strong><?php echo htmlspecialchars($form_data['movie_title_display'], ENT_QUOTES, 'UTF-8'); ?></strong> (Non modifiable)</p>
            <?php /* 
            If you wanted to allow changing movie, you'd re-add the movie search JS here.
            <input type="hidden" name="movie_id" value="<?php echo (int)$form_data['movie_id']; ?>">
            <input type="hidden" name="movie_title_hidden" value="<?php echo htmlspecialchars($form_data['movie_title_display'], ENT_QUOTES, 'UTF-8'); ?>">
            */ ?>
        </div>

        <fieldset class="scene-details-fieldset card">
            <legend>Détails de la Scène (Optionnel)</legend>
            <div class="form-group">
                <label for="scene_start_time">Début de la scène (ex: 00:45:12 ou 2712s):</label>
                <input type="text" name="scene_start_time" id="scene_start_time" value="<?php echo htmlspecialchars($form_data['scene_start_time'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="HH:MM:SS ou secondes">
            </div>
            <div class="form-group">
                <label for="scene_end_time">Fin de la scène (ex: 00:46:00 ou 2760s):</label>
                <input type="text" name="scene_end_time" id="scene_end_time" value="<?php echo htmlspecialchars($form_data['scene_end_time'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="HH:MM:SS ou secondes (optionnel)">
            </div>
            <div class="form-group">
                <label for="scene_description_short">Brève description de la scène:</label>
                <input type="text" name="scene_description_short" id="scene_description_short" value="<?php echo htmlspecialchars($form_data['scene_description_short'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" maxlength="200" placeholder="Ex: Confrontation dans l'entrepôt">
                <small>Ce sera affiché avec votre annotation.</small>
            </div>
        </fieldset>

        <div class="form-group">
            <label for="thread_title">Titre de la Discussion:</label>
            <input type="text" name="thread_title" id="thread_title" value="<?php echo htmlspecialchars($form_data['thread_title'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
        </div>

        <div class="form-group">
            <label for="initial_post">Contenu Principal de la Discussion:</label>
            <textarea name="initial_post" id="initial_post" rows="10" required><?php echo htmlspecialchars($form_data['initial_post'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <button type="submit" class="button-primary">Mettre à Jour la Discussion</button>
    </form>
</main>

<?php include_once 'includes/footer.php'; ?>