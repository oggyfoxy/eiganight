<?php
include_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Vous devez être connecté pour créer une discussion.";
    header('Location: login.php?redirect=forum_create_thread.php');
    exit;
}

$pageTitle = "Créer une Nouvelle Discussion - Eiganights";
$loggedInUserId = (int)$_SESSION['user_id'];

// Form data and errors
$form_data = ['movie_id' => '', 'movie_title_display' => '', 'thread_title' => '', 'initial_post' => '', 'scene_start_time' => '', 'scene_end_time' => '', 'scene_description_short' => ''];
$error_message = '';
// Inside forum_create_thread.php, in the POST handling block
// Pre-fill from GET parameters if available (coming from movie_details page)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['movie_id']) && is_numeric($_GET['movie_id'])) {
        $form_data['movie_id'] = (int)$_GET['movie_id'];
        // Fetch movie title from TMDB API to ensure it's correct and to display it
        // Or, trust the GET param for movie_title for MVP if passed
        if (isset($_GET['movie_title'])) {
             $form_data['movie_title_display'] = trim(urldecode($_GET['movie_title']));
        } else if ($form_data['movie_id'] > 0) {
            // Minimal: Could fetch from TMDB API here to get the title if not passed.
            // For MVP, we'll rely on it being passed or user search.
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $movieId = filter_input(INPUT_POST, 'movie_id', FILTER_VALIDATE_INT);
    $movieTitle = trim($_POST['movie_title_hidden'] ?? '');
    $threadTitle = trim($_POST['thread_title'] ?? '');
    $initialPost = trim($_POST['initial_post'] ?? ''); // This is the annotation

    // >> NEW: Get scene data <<
    $sceneStartTime = trim($_POST['scene_start_time'] ?? '');
    $sceneEndTime = trim($_POST['scene_end_time'] ?? '');
    $sceneDescriptionShort = trim($_POST['scene_description_short'] ?? '');

    $form_data['movie_id'] = $movieId;
    $form_data['movie_title_display'] = $movieTitle;
    $form_data['thread_title'] = $threadTitle;
    $form_data['initial_post'] = $initialPost;
    // >> NEW: Repopulate scene data <<
    $form_data['scene_start_time'] = $sceneStartTime;
    $form_data['scene_end_time'] = $sceneEndTime;
    $form_data['scene_description_short'] = $sceneDescriptionShort;

    // --- Validation (keep existing, can add validation for time format if needed) ---
    if (!$movieId || $movieId <= 0) { /* ... */ }
    // ... other validations ...
    // For MVP, we won't strictly validate time format, but you could add regex checks.
    // A title is still required for the discussion.
    elseif (empty($threadTitle)) {
        $error_message = "Le titre de votre annotation/discussion est requis.";
    } elseif (empty($initialPost)) {
        $error_message = "Le contenu de l'annotation/discussion est requis.";
    }
    // If scene_start_time is provided, a scene_description_short might be nice
    elseif (!empty($sceneStartTime) && empty($sceneDescriptionShort)) {
         // $error_message = "Si vous spécifiez un début de scène, veuillez aussi la décrire brièvement.";
         // Making scene_description_short optional even if time is set for MVP
    }
    else {
        // All good, insert into database
        // >> UPDATE SQL AND BIND_PARAM <<
        $sql = "INSERT INTO forum_threads (user_id, movie_id, movie_title, title, 
                                         scene_start_time, scene_end_time, scene_description_short, 
                                         initial_post_content, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"; // 8 placeholders
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            // Ensure scene times are NULL if empty, not empty strings
            $sceneStartTimeDb = !empty($sceneStartTime) ? $sceneStartTime : null;
            $sceneEndTimeDb = !empty($sceneEndTime) ? $sceneEndTime : null;
            $sceneDescriptionShortDb = !empty($sceneDescriptionShort) ? $sceneDescriptionShort : null;

            $stmt->bind_param("iissssss", $loggedInUserId, $movieId, $movieTitle, $threadTitle,
                                          $sceneStartTimeDb, $sceneEndTimeDb, $sceneDescriptionShortDb,
                                          $initialPost);
            if ($stmt->execute()) {
                // ... (rest of success logic) ...
            } else {
                error_log("Execute failed (CREATE_THREAD_SCENE_INS): " . $stmt->error);
                $error_message = "Erreur lors de la création de l'annotation/discussion. (CTS01)";
            }
            $stmt->close();
        } else {
            error_log("Prepare failed (CREATE_THREAD_SCENE_INS): " . $conn->error);
            $error_message = "Erreur système. (CTS02)";
        }
    }
}

include_once 'includes/header.php';
?>
    <main class="container create-thread-page">
        <h1>Créer une Nouvelle Discussion</h1>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="forum_create_thread.php" id="createThreadForm" class="card">
            <div class="form-group">
                <label for="movie_search">Film Associé:</label>
                <input type="text" id="movie_search_input" placeholder="Rechercher un film (Ex: Inception)...">
                <div id="movie_search_results" class="movie-search-results-dropdown"></div>

                <!-- V V V V V   NEW UPDATED BLOCK STARTS HERE   V V V V V -->
                <input type="hidden" name="movie_id" id="selected_movie_id" value="<?php echo htmlspecialchars($form_data['movie_id']); ?>">
                <input type="hidden" name="movie_title_hidden" id="selected_movie_title_hidden" value="<?php echo htmlspecialchars($form_data['movie_title_display']); ?>">
                <p id="selected_movie_display" class="selected-movie-text">
                    <?php if(!empty($form_data['movie_id']) && !empty($form_data['movie_title_display'])): ?>
                        Film sélectionné : <strong><?php echo htmlspecialchars($form_data['movie_title_display']); ?></strong>
                    <?php else: ?>
                        Aucun film sélectionné.
                    <?php endif; ?>
                </p>
                <!-- ^ ^ ^ ^ ^   NEW UPDATED BLOCK ENDS HERE   ^ ^ ^ ^ ^ -->

            </div>

            <fieldset class="scene-details-fieldset card">
        <legend>Détails de la Scène (Optionnel mais recommandé)</legend>
        <div class="form-group">
            <label for="scene_start_time">Début de la scène (ex: 00:45:12 ou 2712s):</label>
            <input type="text" name="scene_start_time" id="scene_start_time" value="<?php echo htmlspecialchars($form_data['scene_start_time']); ?>" placeholder="HH:MM:SS ou secondes">
        </div>
        <div class="form-group">
            <label for="scene_end_time">Fin de la scène (ex: 00:46:00 ou 2760s):</label>
            <input type="text" name="scene_end_time" id="scene_end_time" value="<?php echo htmlspecialchars($form_data['scene_end_time']); ?>" placeholder="HH:MM:SS ou secondes (optionnel)">
        </div>
        <div class="form-group">
            <label for="scene_description_short">Brève description de la scène:</label>
            <input type="text" name="scene_description_short" id="scene_description_short" value="<?php echo htmlspecialchars($form_data['scene_description_short']); ?>" maxlength="200" placeholder="Ex: Confrontation dans l'entrepôt">
            <small>Ce sera affiché avec votre annotation.</small>
        </div>
    </fieldset>

    <div class="form-group"> <!-- Existing Thread Title -->
        <label for="thread_title">Titre de votre Annotation/Discussion:</label>
        <input type="text" name="thread_title" id="thread_title" value="<?php echo htmlspecialchars($form_data['thread_title']); ?>" required maxlength="255">
        <small>Ex: "Symbolisme des couleurs dans cette scène" ou "Question sur la motivation du personnage ici"</small>
    </div>

        <div class="form-group">
            <label for="thread_title">Titre de la Discussion:</label>
            <input type="text" name="thread_title" id="thread_title" value="<?php echo htmlspecialchars($form_data['thread_title']); ?>" required maxlength="255">
            <small>Ex: "Analyse de la scène du rêve dans Inception (01:15:30)"</small>
        </div>

        <div class="form-group">
            <label for="initial_post">Votre premier message / description de la scène :</label>
            <textarea name="initial_post" id="initial_post" rows="8" required><?php echo htmlspecialchars($form_data['initial_post']); ?></textarea>
        </div>

        <button type="submit" class="button-primary">Créer la Discussion</button>
    </form>
</main>

<script>
// Basic JavaScript for TMDB Movie Search
const searchInput = document.getElementById('movie_search_input');
const resultsContainer = document.getElementById('movie_search_results');
const selectedMovieIdInput = document.getElementById('selected_movie_id');
const selectedMovieTitleInput = document.getElementById('selected_movie_title_hidden');
const selectedMovieDisplay = document.getElementById('selected_movie_display');
const tmdbApiKey = '<?php echo TMDB_API_KEY; ?>'; // Get API key from PHP

let searchTimeout;

if (searchInput) {
    searchInput.addEventListener('keyup', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length < 3) {
            resultsContainer.innerHTML = '';
            resultsContainer.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch(`https://api.themoviedb.org/3/search/movie?api_key=${tmdbApiKey}&language=fr-FR&query=${encodeURIComponent(query)}&page=1&include_adult=false`)
                .then(response => response.json())
                .then(data => {
                    resultsContainer.innerHTML = '';
                    if (data.results && data.results.length > 0) {
                        const ul = document.createElement('ul');
                        data.results.slice(0, 5).forEach(movie => { // Show top 5 results
                            const li = document.createElement('li');
                            const year = movie.release_date ? ` (${movie.release_date.substring(0,4)})` : '';
                            li.textContent = `${movie.title}${year}`;
                            li.dataset.movieId = movie.id;
                            li.dataset.movieTitle = movie.title;
                            li.addEventListener('click', function() {
                                selectedMovieIdInput.value = this.dataset.movieId;
                                selectedMovieTitleInput.value = this.dataset.movieTitle; // For submission
                                selectedMovieDisplay.innerHTML = `Film sélectionné : <strong>${this.dataset.movieTitle}${year}</strong>`;
                                searchInput.value = `${this.dataset.movieTitle}${year}`; // Update input to show selection
                                resultsContainer.innerHTML = '';
                                resultsContainer.style.display = 'none';
                            });
                            ul.appendChild(li);
                        });
                        resultsContainer.appendChild(ul);
                        resultsContainer.style.display = 'block';
                    } else {
                        resultsContainer.innerHTML = '<li>Aucun film trouvé.</li>';
                        resultsContainer.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error fetching movies:', error);
                    resultsContainer.innerHTML = '<li>Erreur de recherche.</li>';
                    resultsContainer.style.display = 'block';
                });
        }, 300); // Debounce API calls
    });

    // Hide results if clicked outside
    document.addEventListener('click', function(event) {
        if (resultsContainer && !resultsContainer.contains(event.target) && event.target !== searchInput) {
            resultsContainer.style.display = 'none';
        }
    });
}
</script>

<?php include_once 'includes/footer.php'; ?>