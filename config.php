<?php
$TMDB_API_KEY = 'cf536f66b460a5cf45e5e4bc648f5e81';

$conn = new mysqli("localhost", "root", "", "eiganights");
if ($conn->connect_error) {
    die("Erreur MySQL : " . $conn->connect_error);
}
?>
