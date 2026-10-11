<?php
session_start();
require_once '../includes/config.php';

// Jeśli użytkownik jest już zalogowany, przekieruj go do panelu
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_panel.php');
    } elseif ($_SESSION['role'] === 'employee') {
        header('Location: employee_panel.php');
    } else {
        header('Location: client_panel.php');
    }
    exit;
}

$error = '';
$success = '';

$name = '';
$surname = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $surname = trim($_POST['surname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_repeat = $_POST['password_repeat'] ?? '';

    // 1. Walidacja danych
    if (empty($name) || empty($surname) || empty($email) || empty($password) || empty($password_repeat)) {
        $error = 'Wypełnij wszystkie wymagane pola.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 50) {
        $error = 'Imię musi zawierać od 2 do 50 znaków.';
    } elseif (mb_strlen($surname) < 2 || mb_strlen($surname) > 50) {
        $error = 'Nazwisko musi zawierać od 2 do 50 znaków.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Podano niepoprawny adres e-mail.';
    } elseif (!empty($phone) && !preg_match('/^[0-9+\s\-]{7,20}$/', $phone)) {
        $error = 'Numer telefonu zawiera nieprawidłowe znaki.';
    } elseif (strlen($password) < 8) {
        $error = 'Hasło musi mieć co najmniej 8 znaków.';
    } elseif ($password !== $password_repeat) {
        $error = 'Podane hasła nie są identyczne.';
    } else {
        try {
            // 2. Sprawdzenie, czy e-mail już istnieje w bazie
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            
            if ($stmt->fetch()) {
                $error = 'Konto z podanym adresem e-mail już istnieje.';
            } else {
                // 3. Szyfrowanie hasła
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // 4. Zapis użytkownika do bazy (domyślnie rola client)
                $insertStmt = $pdo->prepare('
                    INSERT INTO users (name, surname, email, phone, password, role, active) 
                    VALUES (:name, :surname, :email, :phone, :password, "client", 1)
                ');
                
                $result = $insertStmt->execute([
                    'name'     => $name,
                    'surname'  => $surname,
                    'email'    => $email,
                    'phone'    => $phone ?: null,
                    'password' => $hashed_password
                ]);

                if ($result) {
                    $success = 'Rejestracja zakończona sukcesem! Możesz się teraz zalogować.';
                    // Czyszczenie pól formularza po udanej rejestracji
                    $name = $surname = $email = $phone = '';
                } else {
                    $error = 'Wystąpił błąd podczas rejestracji. Spróbuj ponownie.';
                }
            }
        } catch (\PDOException $e) {
            $error = 'Błąd serwera podczas zapisywania danych. Spróbuj ponownie później.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejestracja - Gabinet Fizjoterapii</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="card auth-card">
            <div class="auth-header">
                <span class="auth-icon">⚕</span>
                <h2>Rejestracja Pacjenta</h2>
                <p>Załóż konto w Gabinecie Fizjoterapii</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                    <p class="mt-2"><a href="login.php" class="btn btn-primary btn-sm">Przejdź do logowania</a></p>
                </div>
            <?php endif; ?>

            <?php if (empty($success)): ?>
            <form method="POST" action="register.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Imię *</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" required placeholder="np. Jan">
                    </div>
                    <div class="form-group">
                        <label for="surname">Nazwisko *</label>
                        <input type="text" id="surname" name="surname" class="form-control" value="<?php echo htmlspecialchars($surname); ?>" required placeholder="np. Kowalski">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Adres e-mail *</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required placeholder="np. jan@example.pl">
                </div>

                <div class="form-group">
                    <label for="phone">Telefon</label>
                    <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" placeholder="np. 500 600 700">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Hasło * (min. 8 znaków)</label>
                        <input type="password" id="password" name="password" class="form-control" required minlength="8" placeholder="Hasło">
                    </div>
                    <div class="form-group">
                        <label for="password_repeat">Powtórz hasło *</label>
                        <input type="password" id="password_repeat" name="password_repeat" class="form-control" required minlength="8" placeholder="Powtórz hasło">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Zarejestruj się</button>
            </form>
            <?php endif; ?>

            <div class="auth-footer">
                <p>Masz już konto? <a href="login.php">Zaloguj się</a></p>
                <p class="mt-2"><a href="index.php" class="back-link">&larr; Wróć do strony głównej</a></p>
            </div>
        </div>
    </div>
</body>
</html>