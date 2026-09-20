<?php
session_start();
require_once '../includes/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $surname = trim($_POST['surname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $password_repeat = $_POST['password_repeat'];

    // 1. Walidacja danych
    if (empty($name) || empty($surname) || empty($email) || empty($password)) {
        $error = 'Wypełnij wszystkie wymagane pola.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Podano niepoprawny adres e-mail.';
    } elseif ($password !== $password_repeat) {
        $error = 'Podane hasła nie są identyczne.';
    } else {
        // 2. Sprawdzenie, czy e-mail już istnieje w bazie
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        
        if ($stmt->fetch()) {
            $error = 'Konto z podanym adresem e-mail już istnieje.';
        } else {
            // 3. Szyfrowanie hasła
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // 4. Zapis użytkownika do bazy
            $insertStmt = $pdo->prepare('
                INSERT INTO users (name, surname, email, phone, password, role) 
                VALUES (:name, :surname, :email, :phone, :password, "client")
            ');
            
            if ($insertStmt->execute([
                'name' => $name,
                'surname' => $surname,
                'email' => $email,
                'phone' => $phone,
                'password' => $hashed_password
            ])) {
                $success = 'Rejestracja zakończona pomyślnie! Możesz się teraz zalogować.';
            } else {
                $error = 'Wystąpił błąd serwera. Spróbuj ponownie później.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Rejestracja - Gabinet Fizjoterapii</title>
    <!-- Tutaj później podepniesz swój plik CSS -->
</head>
<body>
    <h2>Rejestracja Pacjenta</h2>

    <?php if ($error): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p style="color: green;"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>

    <form method="POST" action="register.php">
        <label>Imię: <input type="text" name="name" required></label><br><br>
        <label>Nazwisko: <input type="text" name="surname" required></label><br><br>
        <label>E-mail: <input type="email" name="email" required></label><br><br>
        <label>Telefon: <input type="text" name="phone"></label><br><br>
        <label>Hasło: <input type="password" name="password" required></label><br><br>
        <label>Powtórz hasło: <input type="password" name="password_repeat" required></label><br><br>
        <button type="submit">Zarejestruj się</button>
    </form>
</body>
</html>