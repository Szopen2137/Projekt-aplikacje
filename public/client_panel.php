<?php
session_start();
require_once '../includes/config.php';

// Ochrona dostępu po stronie serwera
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['role'] !== 'client') {
    // Jeśli próbuje wejść pracownik lub admin, przekieruj do właściwego panelu
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin_panel.php' : 'employee_panel.php'));
    exit;
}

$userId = (int)$_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';

// Obsługa edycji profilu (wymóg etapu 2 na ocenę 4 i 5)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name'] ?? '');
        $surname = trim($_POST['surname'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($name) || empty($surname)) {
            $errorMsg = 'Imię i nazwisko nie mogą być puste.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($surname) < 2) {
            $errorMsg = 'Imię i nazwisko muszą mieć co najmniej 2 znaki.';
        } elseif (!empty($phone) && !preg_match('/^[0-9+\s\-]{7,20}$/', $phone)) {
            $errorMsg = 'Podano nieprawidłowy format numeru telefonu.';
        } else {
            try {
                $updateStmt = $pdo->prepare('UPDATE users SET name = :name, surname = :surname, phone = :phone WHERE id = :id');
                $updateStmt->execute([
                    'name'    => $name,
                    'surname' => $surname,
                    'phone'   => $phone ?: null,
                    'id'      => $userId
                ]);

                $_SESSION['name'] = $name;
                $successMsg = 'Dane profilu zostały zaktualizowane.';
            } catch (\PDOException $e) {
                $errorMsg = 'Błąd podczas aktualizacji profilu.';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $newPassRepeat = $_POST['new_password_repeat'] ?? '';

        if (empty($currentPass) || empty($newPass) || empty($newPassRepeat)) {
            $errorMsg = 'Wypełnij wszystkie pola zmiany hasła.';
        } elseif (strlen($newPass) < 8) {
            $errorMsg = 'Nowe hasło musi mieć co najmniej 8 znaków.';
        } elseif ($newPass !== $newPassRepeat) {
            $errorMsg = 'Nowe hasła nie są identyczne.';
        } else {
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id');
            $stmt->execute(['id' => $userId]);
            $currentHash = $stmt->fetchColumn();

            if (!$currentHash || !password_verify($currentPass, $currentHash)) {
                $errorMsg = 'Aktualne hasło jest niepoprawne.';
            } else {
                try {
                    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                    $upPass = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
                    $upPass->execute(['password' => $newHash, 'id' => $userId]);
                    $successMsg = 'Hasło zostało pomyślnie zmienione.';
                } catch (\PDOException $e) {
                    $errorMsg = 'Nie udało się zmienić hasła.';
                }
            }
        }
    }
}

// Pobranie aktualnych danych pacjenta z bazy
$userStmt = $pdo->prepare('SELECT name, surname, email, phone, role, created_at FROM users WHERE id = :id');
$userStmt->execute(['id' => $userId]);
$user = $userStmt->fetch();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Pacjenta - Gabinet Fizjoterapii</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <header class="app-header">
        <div class="header-container">
            <div class="header-brand">
                <span class="brand-icon">⚕</span>
                <span>Fizjo<strong>Med</strong></span>
            </div>
            <nav class="header-nav">
                <span class="user-greeting">Zalogowany: <strong><?php echo htmlspecialchars($user['name']); ?></strong> (Pacjent)</span>
                <a href="index.php" class="btn btn-outline btn-sm">Strona główna</a>
                <a href="logout.php" class="btn btn-danger btn-sm">Wyloguj się</a>
            </nav>
        </div>
    </header>

    <main class="panel-container">
        <div class="panel-welcome">
            <h1>Panel Pacjenta</h1>
            <p>Witaj, <?php echo htmlspecialchars($user['name'] . ' ' . $user['surname']); ?>! Tutaj możesz zarządzać swoimi danymi.</p>
        </div>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($errorMsg); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($successMsg); ?>
            </div>
        <?php endif; ?>

        <div class="panel-grid">
            <!-- Karta 1: Edycja danych profilu (Wymóg etapu 2) -->
            <div class="card">
                <div class="card-header">
                    <h2>Twoje Dane Profilu</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="client_panel.php">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-group">
                            <label>Adres e-mail (login)</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Imię *</label>
                                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="surname">Nazwisko *</label>
                                <input type="text" id="surname" name="surname" class="form-control" value="<?php echo htmlspecialchars($user['surname']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="phone">Numer telefonu</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="np. 500 600 700">
                        </div>

                        <button type="submit" class="btn btn-primary">Zapisz zmiany w profilu</button>
                    </form>
                </div>
            </div>

            <!-- Karta 2: Zmiana hasła (Bezpieczeństwo konta) -->
            <div class="card">
                <div class="card-header">
                    <h2>Bezpieczeństwo - Zmiana Hasła</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="client_panel.php">
                        <input type="hidden" name="change_password" value="1">

                        <div class="form-group">
                            <label for="current_password">Aktualne hasło *</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">Nowe hasło * (min. 8 znaków)</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
                        </div>

                        <div class="form-group">
                            <label for="new_password_repeat">Powtórz nowe hasło *</label>
                            <input type="password" id="new_password_repeat" name="new_password_repeat" class="form-control" required minlength="8">
                        </div>

                        <button type="submit" class="btn btn-secondary">Zmień hasło</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Informacja o kolejnych etapach projektu -->
        <div class="card mt-4 info-card">
            <div class="card-body">
                <h3>🗓️ Rezerwacja Zabiegów Fizjoterapii</h3>
                <p>Funkcjonalność wyboru fizjoterapeuty, sprawdzania wolnych terminów i składania rezerwacji wizyt zostanie wdrożona w kolejnym etapie projektu (Etap 4).</p>
            </div>
        </div>
    </main>
</body>
</html>