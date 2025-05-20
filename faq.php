<?php
/*
 * faq.php
 * Affiche les Questions Fréquemment Posées.
 */
require_once 'config.php';

$pageTitle = "FAQ - " . (defined('SITE_NAME') ? SITE_NAME : "EigaNights");
$faqs = [];
$fetch_error = null;

$sql = "SELECT question, answer FROM faq_items ORDER BY sort_order ASC, id ASC";
$stmt = $conn->prepare($sql);

if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $faqs[] = $row;
        }
    } else {
        error_log("Execute failed (FAQ_SEL): " . $stmt->error);
        $fetch_error = "Erreur lors de l'exécution de la requête pour charger les FAQs.";
    }
    $stmt->close();
} else {
    error_log("Prepare failed (FAQ_SEL): " . $conn->error);
    $fetch_error = "Erreur de préparation de la requête pour charger les FAQs.";
}

if (empty($faqs) && !$fetch_error && $conn->error) {
    $fetch_error = "Erreur de connexion à la base de données lors du chargement des FAQs.";
}

// --- Contenu d'exemple générique si la base de données est vide ---
if (empty($faqs) && !$fetch_error) {
    $faqs = [
        [
            'question' => "Comment puis-je noter un film ?",
            'answer' => "Pour noter un film, rendez-vous sur la page de détails du film concerné. Si vous êtes connecté, vous verrez une section vous permettant de donner une note (généralement sur 10 étoiles) et de laisser un commentaire. Votre note sera alors visible par les autres utilisateurs (selon les paramètres de confidentialité du commentaire/de la note si applicable)."
        ],
        [
            'question' => "Qu'est-ce que la 'Watchlist' ?",
            'answer' => "La Watchlist (ou 'Liste de films à voir') est votre liste personnelle de films que vous avez l'intention de regarder. Vous pouvez ajouter des films à votre watchlist depuis leur page de détails ou directement depuis les listes de films. C'est un excellent moyen de ne pas oublier les films qui vous intéressent !"
        ],
        [
            'question' => "Comment puis-je trouver des films spécifiques ?",
            'answer' => "Utilisez la barre de recherche située en haut de chaque page. Entrez le titre du film, un acteur, un réalisateur ou des mots-clés. Les résultats les plus pertinents vous seront présentés."
        ],
        [
            'question' => "Les informations sur les films (acteurs, date de sortie, etc.) sont-elles fiables ?",
            'answer' => "Nous nous efforçons de fournir les informations les plus précises et à jour possibles. Nos données proviennent principalement de The Movie Database (TMDB), une base de données collaborative très complète et régulièrement mise à jour par une large communauté."
        ],
        [
            'question' => "Puis-je créer mes propres listes de films (par genre, par acteur, thématiques...) ?",
            'answer' => "Actuellement, la fonctionnalité principale est la Watchlist personnelle. La création de listes thématiques personnalisées plus avancées pourrait être une fonctionnalité future. Restez à l'écoute !"
        ],
        [
            'question' => "Comment fonctionne le système d'annotations de scènes ?",
            'answer' => "Sur la page de détail d'un film, les utilisateurs connectés peuvent initier une discussion spécifique à une scène. Vous pouvez indiquer un timecode (si disponible pour la source que vous regardez) et une brève description de la scène pour lancer un débat, poser une question ou partager une analyse. C'est le cœur de l'aspect collaboratif d'EigaNights !"
        ],
        [
            'question' => "Comment puis-je interagir avec d'autres utilisateurs ?",
            'answer' => "Vous pouvez commenter les films, répondre aux annotations de scènes dans le forum, et ajouter d'autres utilisateurs comme amis. Consulter les profils des autres utilisateurs peut aussi vous donner des idées de films à découvrir."
        ],
        [
            'question' => "Comment puis-je modifier les informations de mon profil (bio, visibilité) ?",
            'answer' => "Accédez à votre page 'Mon Profil' (via le menu de navigation une fois connecté). Vous y trouverez des options pour modifier votre biographie et ajuster les paramètres de confidentialité de votre profil (qui peut voir votre activité, votre watchlist, etc.)."
        ],
        [
            'question' => "Que faire si je trouve une erreur dans les informations d'un film ?",
            'answer' => "Les données des films proviennent de TMDB. Si vous constatez une erreur, la meilleure façon de la corriger pour tout le monde est de contribuer directement à la base de données TMDB. Pour des problèmes spécifiques à notre site, vous pouvez utiliser notre page 'Contactez-nous'."
        ],
        [
            'question' => "Le site est-il gratuit ?",
            'answer' => "Oui, l'utilisation d'EigaNights (inscription, notation, watchlist, participation au forum) est actuellement gratuite. Nous affichons occasionnellement des publicités simulées et des liens vers des plateformes de streaming (qui sont des simulations dans le cadre de ce projet scolaire) pour illustrer un modèle de fonctionnement potentiel."
        ]
    ];
}


include_once 'includes/header.php';
?>

<main class="container static-page faq-page">
    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

    <?php if ($fetch_error): ?>
        <div class="alert alert-danger" role="alert">
            <p>Nous rencontrons des difficultés pour charger les FAQs actuellement.</p>
            <?php if (ini_get('display_errors') && $fetch_error !== "Erreur de connexion à la base de données lors du chargement des FAQs."): // N'afficher que les erreurs de requête si display_errors est activé ?>
                <p><small>Détail pour l'administrateur : <?php echo htmlspecialchars($fetch_error); ?></small></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($faqs)): ?>
        <div class="faq-list-container">
            <?php foreach ($faqs as $index => $faq): ?>
                <details class="faq-item" <?php echo ($index === 0 && empty($fetch_error)) ? 'open' : ''; ?> >
                    <summary class="faq-question">
                        <?php echo nl2br(htmlspecialchars($faq['question'], ENT_QUOTES, 'UTF-8')); ?>
                    </summary>
                    <div class="faq-answer">
                        <?php echo nl2br(htmlspecialchars($faq['answer'], ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php elseif (!$fetch_error): ?>
        <div class="alert alert-info" role="alert">
            Aucune question fréquemment posée n'est disponible pour le moment. Nous travaillons à enrichir cette section. Revenez bientôt !
        </div>
    <?php endif; ?>
</main>

<?php
include_once 'includes/footer.php';
?>