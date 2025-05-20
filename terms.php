<?php
/*
 * terms.php
 * Affiche les Conditions Générales d'Utilisation.
 * Utilise le contenu d'un fichier si disponible, sinon affiche un exemple codé en dur.
 */
require_once 'config.php';

$siteName = defined('SITE_NAME') ? SITE_NAME : "EigaNights";
$pageTitle = "Conditions Générales d'Utilisation - " . $siteName;

$termsContent = null; // Variable qui contiendra le HTML final à afficher
$contentFilePath = __DIR__ . '/content/terms_content.html';
$usingFileContent = false; // Indicateur pour savoir si on utilise le contenu du fichier

// Essayer de lire le contenu du fichier
if (file_exists($contentFilePath) && is_readable($contentFilePath)) {
    $fileContent = @file_get_contents($contentFilePath);
    if ($fileContent !== false && !empty(trim($fileContent))) {
        $termsContent = $fileContent;
        $usingFileContent = true;
    } else {
        // Le fichier existe mais est vide ou illisible (erreur mineure, on utilisera l'exemple)
        error_log("Fichier terms_content.html trouvé mais vide ou illisible. Utilisation du contenu d'exemple. Path: {$contentFilePath}");
    }
} else {
    // Le fichier n'existe pas (erreur mineure, on utilisera l'exemple)
    error_log("Fichier terms_content.html non trouvé. Utilisation du contenu d'exemple. Path: {$contentFilePath}");
}

// Si le contenu n'a pas été chargé depuis le fichier, utiliser l'exemple codé en dur.
if ($termsContent === null) {
    $termsDisplayTitle = "Conditions Générales d'Utilisation"; // Titre pour l'affichage
    // Contenu d'exemple HTML (celui que nous avions précédemment)
    $termsContent = '
        <p><strong>Dernière mise à jour :</strong> '.date('d F Y').' </em></p>
          <hr>

        <p>Bienvenue sur '.htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8').' ! En accédant à notre site web et en utilisant nos services, vous acceptez d\'être lié par les présentes Conditions Générales d\'Utilisation ("CGU"). Veuillez les lire attentivement.</p>

        <h2>Article 1 : Objet et Acceptation des CGU</h2>
        <p>Les présentes CGU ont pour objet de définir les modalités et conditions dans lesquelles le site '.htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8').' (ci-après "le Site") met à la disposition de ses utilisateurs (ci-après "l\'Utilisateur" ou "les Utilisateurs") les services disponibles. L\'utilisation du Site implique l\'acceptation pleine et entière des présentes CGU. Si vous n\'acceptez pas ces conditions, veuillez ne pas utiliser le Site.</p>
        <p>'.htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8').' se réserve le droit de modifier ou de mettre à jour ces CGU à tout moment. Les modifications prendront effet dès leur publication sur le Site. Il est de votre responsabilité de consulter régulièrement cette page.</p>

        <h2>Article 2 : Description des Services</h2>
        <p>'.htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8').' est une plateforme communautaire dédiée au cinéma permettant aux Utilisateurs de :</p>
        <ul>
            <li>Découvrir et rechercher des informations sur des films.</li>
            <li>Gérer une "Watchlist" personnelle.</li>
            <li>Noter les films et publier des commentaires.</li>
            <li>Participer à un forum de discussion et à des annotations de scènes.</li>
            <li>Interagir avec d\'autres Utilisateurs.</li>
        </ul>
        <p>Les informations sur les films sont principalement fournies via l\'API de The Movie Database (TMDB).</p>

        <h2>Article 3 : Accès au Site et Inscription</h2>
        <p>L\'accès au contenu public du Site est libre. Certaines fonctionnalités nécessitent la création d\'un compte Utilisateur. Lors de l\'inscription, l\'Utilisateur s\'engage à fournir des informations exactes et à maintenir la confidentialité de son mot de passe.</p>

        <h2>Article 4 : Propriété Intellectuelle</h2>
        <p>La structure générale du Site et ses contenus originaux (textes, graphismes, logo '.htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8').') sont la propriété de '.htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8').' ou de ses partenaires. Toute reproduction non autorisée est interdite. Les données des films (affiches, etc.) sont la propriété de leurs détenteurs respectifs (ex: TMDB).</p>

        <h2>Article 5 : Contenu Utilisateur et Conduite</h2>
        <p>L\'Utilisateur est responsable de tout contenu qu\'il publie et s\'engage à respecter les lois, les bonnes mœurs et les droits des tiers. '.htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8').' se réserve le droit de modérer ou supprimer tout contenu non conforme.</p>
        <p>En publiant du contenu, l\'Utilisateur accorde à '.htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8').' une licence d\'utilisation pour ce contenu dans le cadre du Site.</p>

        <h2>Article 6 : Responsabilité</h2>
        <p>'.htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8').' s\'efforce de fournir des informations exactes mais ne peut garantir leur exhaustivité. L\'utilisation des informations du Site se fait sous la seule responsabilité de l\'Utilisateur. La responsabilité de '.htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8').' ne saurait être engagée pour les contenus de sites tiers accessibles via des liens hypertextes.</p>
        <p>Les fonctionnalités de monétisation (publicités, liens de streaming) sont simulées dans le cadre de ce projet scolaire.</p>

        <h2>Article 7 : Données Personnelles et Vie Privée</h2>
        <p>La collecte et le traitement des données personnelles sont décrits dans notre Politique de Confidentialité, accessible sur le Site.</p>
        
        <h2>Article 8 : Droit Applicable</h2>
        <p>Les présentes CGU sont régies par le droit français. Tout litige sera soumis à la compétence des tribunaux de [Ville Fictive pour le Tribunal, ex: Lyon].</p>

        <h2>Article 9 : Contact</h2>
        <p>Pour toute question relative aux présentes CGU, veuillez nous contacter via la page "Contactez-nous".</p>
        
        <hr>
        <p style="font-size:0.8em; text-align:center; color: #777;"><em>Ce document est un exemple de Conditions Générales d\'Utilisation fourni dans le cadre d\'un projet scolaire. Pour un site réel, consultez un professionnel du droit.</em></p>
    ';
} else {
    // Si le contenu vient du fichier, on pourrait vouloir extraire le titre du fichier
    // ou simplement utiliser le titre de page par défaut. Pour l'instant, on garde le titre de page global.
    $termsDisplayTitle = "Conditions Générales d'Utilisation";
}


include_once 'includes/header.php';
?>

<main class="container static-page terms-page">
    <h1><?php echo htmlspecialchars($termsDisplayTitle); ?></h1>

    <?php if (!$usingFileContent && !empty($termsContent)): // Afficher un petit avertissement si on utilise le contenu d'exemple ?>
        
    <?php endif; ?>

    <article class="terms-content-container card">
        <?php
        // $termsContent contient soit le contenu du fichier, soit l'exemple codé en dur.
        // Il est supposé être du HTML sûr.
        echo $termsContent;
        ?>
    </article>
</main>

<?php
include_once 'includes/footer.php';
?>