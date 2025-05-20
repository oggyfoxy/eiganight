<?php
/*
 * forum_create_thread.php
 * Handles creation of new forum threads / scene annotations.
 */
include_once 'config.php'; // Includes session_start(), $conn, TMDB_API_KEY, BASE_URL
// Assumes functions.php (with CSRF functions) is included via config.php or directly

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour créer une discussion.";
    // Try to redirect back to this page after login
    $redirectQuery = http_build_query($_GET); // Preserve any GET params like movie_id
    header('Location: ' . BASE_URL . 'login.php?redirect=' . urlencode(basename(__FILE__) . ($redirectQuery ? '?' . $redirectQuery : '')));
    exit;
}

$pageTitle = "Créer une Nouvelle Discussion - Eiganights";
$loggedInUserId = (int)$_SESSION['user_id'];

// Initialize form data and error message
$form_data = [
    'movie_id' => '', 
    'movie_title_display' => '', // Title to display after selection, comes from JS or GET
    'thread_title' => '', 
    'initial_post' => '', 
    'scene_start_time' => '', 
    'scene_end_time' => '', 
    'scene_description_short' => ''
];
$error_message = '';

// Pre-fill form if movie_id and movie_title are passed via GET (e.g., from movie_details page)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['movie_id']) && is_numeric($_GET['movie_id'])) {
        $form_data['movie_id'] = (int)$_GET['movie_id'];
        if (isset($_GET['movie_title'])) {
             $form_data['movie_title_display'] = trim(urldecode($_GET['movie_title']));
        }
        // If only movie_id is passed, the JS search will allow user to confirm/search,
        // or they can proceed if they know the movie.
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !function_exists('validate_csrf_token') || !validate_csrf_token($_POST['csrf_token'])) {
        $error_message = "Erreur de sécurité (jeton invalide). Veuillez rafraîchir la page et réessayer.";
    } else {
        // Sanitize and retrieve form data
        $movieId = filter_input(INPUT_POST, 'movie_id', FILTER_VALIDATE_INT);
        // movie_title_hidden is populated by JS or pre-filled by GET
        $movieTitleFromForm = trim($_POST['movie_title_hidden'] ?? ''); 
        $threadTitle = trim($_POST['thread_title'] ?? '');
        $initialPost = trim($_POST['initial_post'] ?? '');
        $sceneStartTime = trim($_POST['scene_start_time'] ?? '');
        $sceneEndTime = trim($_POST['scene_end_time'] ?? '');
        $sceneDescriptionShort = trim($_POST['scene_description_short'] ?? '');

        // Update $form_data for sticky form fields in case of error
        $form_data['movie_id'] = $movieId;
        $form_data['movie_title_display'] = $movieTitleFromForm;
        $form_data['thread_title'] = $threadTitle;
        $form_data['initial_post'] = $initialPost;
        $form_data['scene_start_time'] = $sceneStartTime;
        $form_data['scene_end_time'] = $sceneEndTime;
        $form_data['scene_description_short'] = $sceneDescriptionShort;

        // --- Validation ---
        if (!$movieId || $movieId <= 0 || empty($movieTitleFromForm)) {
            $error_message = "Veuillez rechercher et sélectionner un film valide pour associer à la discussion.";
        } elseif (empty($threadTitle)) {
            $error_message = "Le titre de votre discussion est requis.";
        } elseif (mb_strlen($threadTitle) > 255) { // Check max length
            $error_message = "Le titre de la discussion ne doit pas dépasser 255 caractères.";
        } elseif (empty($initialPost)) {
            $error_message = "Le contenu de la discussion (votre premier message ou annotation) est requis.";
        } 
        // Optional: Add validation for scene time formats if strict format is required
        // Example: Check if scene_start_time and scene_end_time match HH:MM:SS or Xs pattern
        // elseif (!empty($sceneStartTime) && !preg_match('/^(\d{1,2}:[0-5]\d:[0-5]\d|\d+s?)$/i', $sceneStartTime)) {
        //     $error_message = "Format invalide pour 'Début de la scène'. Utilisez HH:MM:SS ou secondes (ex: 123s).";
        // }

        if (empty($error_message)) {
            // All validations passed, proceed to insert into database
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
                // Ensure scene times are NULL if empty, not empty strings for the database
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
                    
                    // Consume/regenerate CSRF token
                    if (function_exists('generate_csrf_token')) {
                        unset($_SESSION['csrf_token']); // Unset current token
                    }
                    
                    header("Location: " . BASE_URL . "forum.php"); // Redirect to forum list page
                    exit;
                } else {
                    error_log("Execute failed (CREATE_THREAD_INS): " . $stmt->error);
                    $error_message = "Erreur lors de la création de la discussion. (FCT02)";
                }
                $stmt->close();
            }
        }
    }
    // If there was an error (CSRF or validation), regenerate CSRF token for the next form display
    if (!empty($error_message) && function_exists('generate_csrf_token')) {
        unset($_SESSION['csrf_token']);
    }
}

// Ensure CSRF token generation function is available for the form
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() { return 'csrf_fallback_token_fct'; } // Simple fallback for safety
    error_log("CSRF function generate_csrf_token() not found in forum_create_thread.php context.");
}

include_once 'includes/header.php';
?>
<main class="container create-thread-page">
    <h1>Créer une Nouvelle Discussion / Annotation de Scène</h1>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['forum_error'])): /* For errors from other forum pages redirecting here */ ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['forum_error']); unset($_SESSION['forum_error']); ?></div>
    <?php endif; ?>

    <form method="POST" action="<?php echo BASE_URL; ?>forum_create_thread.php" id="createThreadForm" class="card" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

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
const searchInput = document.getElementById('movie_search_input');
const resultsContainer = document.getElementById('movie_search_results');
const selectedMovieIdInput = document.getElementById('selected_movie_id');
const selectedMovieTitleInput = document.getElementById('selected_movie_title_hidden');
const selectedMovieDisplay = document.getElementById('selected_movie_display');
const tmdbApiKey = '<?php echo defined('TMDB_API_KEY') ? TMDB_API_KEY : ""; ?>'; // Check if constant is defined

let searchTimeout;

if (searchInput && tmdbApiKey) { // Proceed only if input and API key are available
    searchInput.addEventListener('keyup', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length < 2) { // Reduced to 2 for quicker search start
            resultsContainer.innerHTML = '';
            resultsContainer.style.display = 'none';
            // Also clear selected movie if search input is cleared significantly
            if (query.length === 0) {
                selectedMovieIdInput.value = '';
                selectedMovieTitleInput.value = '';
                selectedMovieDisplay.innerHTML = 'Aucun film sélectionné.';
            }
            return;
        }

        searchTimeout = setTimeout(() => {
            // Consider using your api_tmdb_proxy.php if you want to hide API key from client-side or add caching
            // For now, direct client-side call as in your original.
            const apiUrl = `https://api.themoviedb.org/3/search/movie?api_key=${tmdbApiKey}&language=fr-FR&query=${encodeURIComponent(query)}&page=1&include_adult=false`;
            
            fetch(apiUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Erreur réseau ou API: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    resultsContainer.innerHTML = ''; // Clear previous results
                    if (data.results && data.results.length > 0) {
                        const ul = document.createElement('ul');
                        ul.setAttribute('role', 'listbox'); // Accessibility
                        data.results.slice(0, 5).forEach(movie => { // Show top 5 results
                            const li = document.createElement('li');
                            li.setAttribute('role', 'option');
                            li.setAttribute('tabindex', '0'); // Make it focusable
                            const year = movie.release_date ? ` (${movie.release_date.substring(0,4)})` : '';
                            li.textContent = `${movie.title}${year}`;
                            li.dataset.movieId = movie.id;
                            li.dataset.movieTitle = movie.title;
                            
                            const selectMovieAction = () => {
                                selectedMovieIdInput.value = movie.id;
                                selectedMovieTitleInput.value = movie.title; // For form submission
                                selectedMovieDisplay.innerHTML = `Film sélectionné : <strong>${movie.title}${year}</strong>`;
                                searchInput.value = `${movie.title}${year}`; // Update input to show selection
                                resultsContainer.innerHTML = '';
                                resultsContainer.style.display = 'none';
                            };

                            li.addEventListener('click', selectMovieAction);
                            li.addEventListener('keydown', (e) => { // Allow selection with Enter/Space
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    selectMovieAction();
                                    searchInput.focus(); // Return focus to search or next field
                                }
                            });
                            ul.appendChild(li);
                        });
                        resultsContainer.appendChild(ul);
                        resultsContainer.style.display = 'block';
                    } else {
                        const li = document.createElement('li');
                        li.textContent = 'Aucun film trouvé.';
                        resultsContainer.appendChild(li);
                        resultsContainer.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Erreur lors de la recherche de films TMDB:', error);
                    resultsContainer.innerHTML = '<li>Erreur de recherche. Veuillez réessayer.</li>';
                    resultsContainer.style.display = 'block';
                });
        }, 350); // Debounce API calls slightly more
    });

    // Hide results if clicked outside
    document.addEventListener('click', function(event) {
        if (resultsContainer && !resultsContainer.contains(event.target) && event.target !== searchInput) {
            resultsContainer.style.display = 'none';
        }
    });

    // Handle clearing selection if search input is manually cleared
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