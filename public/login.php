<?php
session_start();
require_once '../includes/config.php';

// Jeśli użytkownik jest już zalogowany, przekieruj go do odpowiedniego panelu
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
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Wypełnij wszystkie pola.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, name, surname, password, role, active FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ((int)$user['active'] !== 1) {
                    $error = 'Twoje konto jest zablokowane. Skontaktuj się z administratorem.';
                } else {
                    // Ochrona przed session fixation
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['name'];

                    if ($user['role'] === 'admin') {
                        header('Location: admin_panel.php');
                    } elseif ($user['role'] === 'employee') {
                        header('Location: employee_panel.php');
                    } else {
                        header('Location: client_panel.php');
                    }
                    exit;
                }
            } else {
                $error = 'Nieprawidłowy adres e-mail lub hasło.';
            }
        } catch (\PDOException $e) {
            $error = 'Wystąpił błąd podczas logowania. Spróbuj ponownie później.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie - Gabinet Fizjoterapii</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="card auth-card">
            <div class="auth-header">
                <span class="auth-icon">⚕</span>
                <h2>Logowanie</h2>
                <p>Gabinet Fizjoterapii</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="email">Adres e-mail</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required autofocus placeholder="np. jan@example.pl">
                </div>

                <div class="form-group">
                    <label for="password">Hasło</label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Twoje hasło">
                </div>

                <button type="submit" class="btn btn-primary btn-block">Zaloguj się</button>
            </form>

            <div class="auth-footer">
                <p>Nie masz jeszcze konta? <a href="register.php">Zarejestruj się</a></p>
                <p class="mt-2"><a href="index.php" class="back-link">&larr; Wróć do strony głównej</a></p>
            </div>
        </div>
    </div>
</body>
</html>