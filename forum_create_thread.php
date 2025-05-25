<?php
/*
 * forum_create_thread.php
 * Handles creation of new forum threads / scene annotations.
 */
include_once 'config.php'; // Includes session_start(), $conn, TMDB_API_KEY, BASE_URL
include_once 'includes/functions.php'; // <<< INCLUDE FUNCTIONS.PHP HERE

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour créer une discussion.";
    $redirectQuery = http_build_query($_GET);
    header('Location: ' . BASE_URL . 'login.php?redirect=' . urlencode(basename(__FILE__) . ($redirectQuery ? '?' . $redirectQuery : '')));
    exit;
}

$pageTitle = "Créer une Nouvelle Discussion - Eiganights";
$loggedInUserId = (int)$_SESSION['user_id'];

$form_data = [
    'movie_id' => '', 
    'movie_title_display' => '',
    'thread_title' => '', 
    'initial_post' => '', 
    'scene_start_time' => '', 
    'scene_end_time' => '', 
    'scene_description_short' => ''
];
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['movie_id']) && is_numeric($_GET['movie_id'])) {
        $form_data['movie_id'] = (int)$_GET['movie_id'];
        if (isset($_GET['movie_title'])) {
             $form_data['movie_title_display'] = trim(urldecode($_GET['movie_title']));
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) { // validate_csrf_token() should now exist
        $error_message = "Erreur de sécurité (jeton invalide). Veuillez rafraîchir la page et réessayer.";
    } else {
        $movieId = filter_input(INPUT_POST, 'movie_id', FILTER_VALIDATE_INT);
        $movieTitleFromForm = trim($_POST['movie_title_hidden'] ?? ''); 
        $threadTitle = trim($_POST['thread_title'] ?? '');
        $initialPost = trim($_POST['initial_post'] ?? '');
        $sceneStartTime = trim($_POST['scene_start_time'] ?? '');
        $sceneEndTime = trim($_POST['scene_end_time'] ?? '');
        $sceneDescriptionShort = trim($_POST['scene_description_short'] ?? '');

        $form_data['movie_id'] = $movieId;
        $form_data['movie_title_display'] = $movieTitleFromForm;
        $form_data['thread_title'] = $threadTitle;
        $form_data['initial_post'] = $initialPost;
        $form_data['scene_start_time'] = $sceneStartTime;
        $form_data['scene_end_time'] = $sceneEndTime;
        $form_data['scene_description_short'] = $sceneDescriptionShort;

        if (!$movieId || $movieId <= 0 || empty($movieTitleFromForm)) {
            $error_message = "Veuillez rechercher et sélectionner un film valide pour associer à la discussion.";
        } elseif (empty($threadTitle)) {
            $error_message = "Le titre de votre discussion est requis.";
        } elseif (mb_strlen($threadTitle) > 255) {
            $error_message = "Le titre de la discussion ne doit pas dépasser 255 caractères.";
        } elseif (empty($initialPost)) {
            $error_message = "Le contenu de la discussion (votre premier message ou annotation) est requis.";
        } 

        if (empty($error_message)) {
            $sql = "INSERT INTO forum_threads 
                        (user_id, movie_id, movie_title, title, 
                         scene_start_time, scene_end_time, scene_description_short, 
                         initial_post_content, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("Prepare failed (CREATE_THREAD_INS): " . $conn->error);
                $error_message = "Erreur système lors de la préparation de la création de la discussion. (FCT01)";
            } else {
                $sceneStartTimeDb = !empty($sceneStartTime) ? $sceneStartTime : null;
                $sceneEndTimeDb = !empty($sceneEndTime) ? $sceneEndTime : null;
                $sceneDescriptionShortDb = !empty($sceneDescriptionShort) ? $sceneDescriptionShort : null;

                $stmt->bind_param("iissssss", 
                    $loggedInUserId, $movieId, $movieTitleFromForm, $threadTitle,
                    $sceneStartTimeDb, $sceneEndTimeDb, $sceneDescriptionShortDb,
                    $initialPost
                );

                if ($stmt->execute()) {
                    $new_thread_id = $conn->insert_id;
                    $_SESSION['forum_message'] = "Discussion '" . htmlspecialchars($threadTitle, ENT_QUOTES, 'UTF-8') . "' créée avec succès !";
                    
                    // Consume/regenerate CSRF token by unsetting it.
                    // generate_csrf_token() will create a new one on the next page load or form display.
                    unset($_SESSION['csrf_token']);
                    
                    header("Location: " . BASE_URL . "forum_view_thread.php?id=" . $new_thread_id); // Redirect to the new thread
                    exit;
                } else {
                    error_log("Execute failed (CREATE_THREAD_INS): " . $stmt->error);
                    $error_message = "Erreur lors de la création de la discussion. (FCT02)";
                }
                $stmt->close();
            }
        }
    }
    // If there was an error (CSRF or validation), the token in the session might still be the old one.
    // For a robust system, if a CSRF token fails, you might want to invalidate it and force a new one
    // to be generated when the form is re-displayed to prevent replay of the same failed token.
    // The current generate_csrf_token() will re-issue the same token if it's still in session,
    // or generate a new one if it was unset (e.g., after successful submission or if you unset it on error).
    // For simplicity, if there's an error, we'll let the form re-render with a new token.
    if (!empty($error_message)) {
        unset($_SESSION['csrf_token']); // Force new token generation on form redisplay after error
    }
}

// No need for the fallback generate_csrf_token() here anymore if functions.php is included correctly.

include_once 'includes/header.php';
?>
<main class="container create-thread-page">
    <h1>Créer une Nouvelle Discussion / Annotation de Scène</h1>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['forum_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['forum_error']); unset($_SESSION['forum_error']); ?></div>
    <?php endif; ?>

    <form method="POST" action="<?php echo BASE_URL; ?>forum_create_thread.php<?php echo isset($_GET['movie_id']) ? '?movie_id='.(int)$_GET['movie_id'].'&movie_title='.urlencode($_GET['movie_title'] ?? '') : ''; // Preserve GET params in action for refresh ?>" id="createThreadForm" class="card" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); // generate_csrf_token() now comes from functions.php ?>">

        <div class="form-group">
            <label for="movie_search_input">Film Associé:</label>
            <input type="text" id="movie_search_input" 
                   placeholder="Rechercher un film (Ex: Inception)..." 
                   value="<?php echo (!empty($form_data['movie_id']) && !empty($form_data['movie_title_display'])) ? htmlspecialchars($form_data['movie_title_display'], ENT_QUOTES, 'UTF-8') : ''; ?>" 
                   aria-describedby="movie_search_help">
            <div id="movie_search_results" class="movie-search-results-dropdown" role="listbox"></div>
            <input type="hidden" name="movie_id" id="selected_movie_id" value="<?php echo htmlspecialchars($form_data['movie_id'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="movie_title_hidden" id="selected_movie_title_hidden" value="<?php echo htmlspecialchars($form_data['movie_title_display'], ENT_QUOTES, 'UTF-8'); ?>">
            <p id="selected_movie_display" class="selected-movie-text" aria-live="polite">
                <?php if(!empty($form_data['movie_id']) && !empty($form_data['movie_title_display'])): ?>
                    Film sélectionné : <strong><?php echo htmlspecialchars($form_data['movie_title_display'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <?php else: ?>
                    Aucun film sélectionné.
                <?php endif; ?>
            </p>
            <small id="movie_search_help" class="form-text">Commencez à taper pour rechercher un film. Sélectionnez dans la liste.</small>
        </div>

        <fieldset class="scene-details-fieldset card">
            <legend>Détails de la Scène (Optionnel)</legend>
            <div class="form-group">
                <label for="scene_start_time">Début de la scène (ex: 00:45:12 ou 2712s):</label>
                <input type="text" name="scene_start_time" id="scene_start_time" value="<?php echo htmlspecialchars($form_data['scene_start_time'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="HH:MM:SS ou secondes">
            </div>
            <div class="form-group">
                <label for="scene_end_time">Fin de la scène (ex: 00:46:00 ou 2760s):</label>
                <input type="text" name="scene_end_time" id="scene_end_time" value="<?php echo htmlspecialchars($form_data['scene_end_time'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="HH:MM:SS ou secondes (optionnel)">
            </div>
            <div class="form-group">
                <label for="scene_description_short">Brève description de la scène:</label>
                <input type="text" name="scene_description_short" id="scene_description_short" value="<?php echo htmlspecialchars($form_data['scene_description_short'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="200" placeholder="Ex: Confrontation dans l'entrepôt">
                <small class="form-text">Max 200 caractères. S'affichera avec votre discussion.</small>
            </div>
        </fieldset>

        <div class="form-group">
            <label for="thread_title">Titre de votre Discussion/Annotation:</label>
            <input type="text" name="thread_title" id="thread_title" value="<?php echo htmlspecialchars($form_data['thread_title'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
            <small class="form-text">Ex: "Symbolisme des couleurs dans cette scène" ou "Question sur la motivation du personnage ici".</small>
        </div>

        <div class="form-group">
            <label for="initial_post">Votre premier message / description détaillée de la scène / analyse :</label>
            <textarea name="initial_post" id="initial_post" rows="8" required><?php echo htmlspecialchars($form_data['initial_post'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <button type="submit" class="button-primary">Créer la Discussion</button>
    </form>
</main>

<script>
// Basic JavaScript for TMDB Movie Search (Same as before)
// ... your existing JavaScript for movie search ...
const searchInput = document.getElementById('movie_search_input');
const resultsContainer = document.getElementById('movie_search_results');
const selectedMovieIdInput = document.getElementById('selected_movie_id');
const selectedMovieTitleInput = document.getElementById('selected_movie_title_hidden');
const selectedMovieDisplay = document.getElementById('selected_movie_display');
const tmdbApiKey = '<?php echo defined('TMDB_API_KEY') ? TMDB_API_KEY : ""; ?>';

let searchTimeout;

if (searchInput && tmdbApiKey) {
    searchInput.addEventListener('keyup', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length < 2) { 
            resultsContainer.innerHTML = '';
            resultsContainer.style.display = 'none';
            if (query.length === 0) {
                selectedMovieIdInput.value = '';
                selectedMovieTitleInput.value = '';
                selectedMovieDisplay.innerHTML = 'Aucun film sélectionné.';
            }
            return;
        }

        searchTimeout = setTimeout(() => {
            const apiUrl = `https://api.themoviedb.org/3/search/movie?api_key=${tmdbApiKey}&language=fr-FR&query=${encodeURIComponent(query)}&page=1&include_adult=false`;
            
            fetch(apiUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Erreur réseau ou API: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    resultsContainer.innerHTML = ''; 
                    if (data.results && data.results.length > 0) {
                        const ul = document.createElement('ul');
                        ul.setAttribute('role', 'listbox'); 
                        data.results.slice(0, 5).forEach(movie => { 
                            const li = document.createElement('li');
                            li.setAttribute('role', 'option');
                            li.setAttribute('tabindex', '0'); 
                            const year = movie.release_date ? ` (${movie.release_date.substring(0,4)})` : '';
                            li.textContent = `${movie.title}${year}`;
                            li.dataset.movieId = movie.id;
                            li.dataset.movieTitle = movie.title;
                            
                            const selectMovieAction = () => {
                                selectedMovieIdInput.value = movie.id;
                                selectedMovieTitleInput.value = movie.title; 
                                selectedMovieDisplay.innerHTML = `Film sélectionné : <strong>${movie.title}${year}</strong>`;
                                searchInput.value = `${movie.title}${year}`; 
                                resultsContainer.innerHTML = '';
                                resultsContainer.style.display = 'none';
                            };

                            li.addEventListener('click', selectMovieAction);
                            li.addEventListener('keydown', (e) => { 
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    selectMovieAction();
                                    searchInput.focus(); 
                                }
                            });
                            ul.appendChild(li);
                        });
                        resultsContainer.appendChild(ul);
                        resultsContainer.style.display = 'block';
                    } else {
                        const liNoResult = document.createElement('li'); // Renamed variable to avoid conflict
                        liNoResult.textContent = 'Aucun film trouvé.';
                        resultsContainer.appendChild(liNoResult); // Append the new li
                        resultsContainer.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la recherche de films TMDB:', error);
                    resultsContainer.innerHTML = '<li>Erreur de recherche. Veuillez réessayer.</li>';
                    resultsContainer.style.display = 'block';
                });
        }, 350); 
    });

    document.addEventListener('click', function(event) {
        if (resultsContainer && !resultsContainer.contains(event.target) && event.target !== searchInput) {
            resultsContainer.style.display = 'none';
        }
    });

    searchInput.addEventListener('input', function() {
        if (this.value.trim() === '') {
            selectedMovieIdInput.value = '';
            selectedMovieTitleInput.value = '';
            selectedMovieDisplay.innerHTML = 'Aucun film sélectionné.';
            resultsContainer.innerHTML = '';
            resultsContainer.style.display = 'none';
        }
    });
} else if (!tmdbApiKey) {
    console.warn("Clé API TMDB non disponible pour la recherche de films côté client.");
    if(searchInput) searchInput.placeholder = "Recherche de film indisponible (config API manquante)";
    if(resultsContainer) resultsContainer.innerHTML = "<li>Configuration API TMDB manquante.</li>";
}
</script>

<?php include_once 'includes/footer.php'; ?>