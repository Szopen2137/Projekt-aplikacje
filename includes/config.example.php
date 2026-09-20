<?php
$host = 'localhost';
$db   = ''; // Twoja nazwa bazy danych
$user = 'root'; // Domyślny użytkownik 
$pass = ''; // Twoje hasło!
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Zgłaszanie błędów
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Zwraca dane jako tablicę asocjacyjną
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Wymusza prawdziwe prepared statements
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Wystąpił błąd podczas łączenia z bazą danych.");
}
?>