<?php
session_start();
require_once '../includes/config.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Wypełnij wszystkie pola.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, password, role FROM users WHERE email = :email AND active = 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            
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
        } else {
            $error = 'Nieprawidłowy adres e-mail lub hasło.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Logowanie - Gabinet Fizjoterapii</title>
</head>
<body>
    <h2>Logowanie</h2>

    <?php if ($error): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <label>E-mail: <input type="email" name="email" required></label><br><br>
        <label>Hasło: <input type="password" name="password" required></label><br><br>
        <button type="submit">Zaloguj się</button>
    </form>
    
    <p>Nie masz konta? <a href="register.php">Zarejestruj się</a></p>
</body>
</html>