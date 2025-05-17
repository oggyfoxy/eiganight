<?php
// create_admin_temp.php
$password = 'password';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
echo "Username: admin<br>";
echo "Hashed Password: " . $hashedPassword;
// Example output: Hashed Password: $2y$10$xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
?>
