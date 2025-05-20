<?php
// api_tmdb_proxy.php

include 'config.php'; // Doit définir TMDB_API_KEY

header('Content-Type: application/json');

// Vérification de la requête GET
if (!isset($_GET['query']) || empty(trim($_GET['query']))) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètre "query" manquant ou vide.']);
    exit;
}

$query = trim($_GET['query']);
$url = "https://api.themoviedb.org/3/search/movie?api_key=" . urlencode(TMDB_API_KEY) . "&language=fr-FR&query=" . urlencode($query);

// Configuration du contexte HTTP (timeout, etc.)
$context = stream_context_create([
    'http' => [
        'timeout' => 5 // Timeout de 5 secondes
    ]
]);

$response = file_get_contents($url, false, $context);

// Facultatif : pour les appels JS cross-domain
header("Access-Control-Allow-Origin: *");

if ($response !== false) {
    echo $response;
} else {
    http_response_code(502); // Bad Gateway
    echo json_encode(['error' => 'Service TMDB temporairement indisponible.']);
}
