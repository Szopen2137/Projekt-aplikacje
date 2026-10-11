<?php
session_start();
require_once '../includes/config.php';

// Ochrona dostępu - tylko pracownik (fizjoterapeuta)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['role'] !== 'employee') {
    // Klient nie może wejść do panelu pracownika
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin_panel.php' : 'client_panel.php'));
    exit;
}

$userId = (int)$_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';

// Obsługa edycji profilu pracownika
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
                $successMsg = 'Dane pracownika zostały zaktualizowane.';
            } catch (\PDOException $e) {
                $errorMsg = 'Błąd podczas aktualizacji profilu.';
            }
        }
    }
}

// Pobranie danych pracownika z bazy
$stmt = $pdo->prepare('SELECT name, surname, email, phone, role, created_at FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Pracownika - Gabinet Fizjoterapii</title>
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
                <span class="user-greeting">Fizjoterapeuta: <strong><?php echo htmlspecialchars($user['name']); ?></strong></span>
                <a href="index.php" class="btn btn-outline btn-sm">Strona główna</a>
                <a href="logout.php" class="btn btn-danger btn-sm">Wyloguj się</a>
            </nav>
        </div>
    </header>

    <main class="panel-container">
        <div class="panel-welcome">
            <div class="badge-role badge-employee">Strefa Fizjoterapeuty</div>
            <h1>Panel Pracownika</h1>
            <p>Witaj w systemie obsługi gabinetu, <?php echo htmlspecialchars($user['name'] . ' ' . $user['surname']); ?>.</p>
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
            <!-- Dane pracownika -->
            <div class="card">
                <div class="card-header">
                    <h2>Twoje Dane Pracownicze</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="employee_panel.php">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-group">
                            <label>Służbowy adres e-mail</label>
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
                            <label for="phone">Telefon kontaktowy</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>

                        <button type="submit" class="btn btn-primary">Zapisz dane</button>
                    </form>
                </div>
            </div>

            <!-- Informacje o wizytach i grafiku -->
            <div class="card">
                <div class="card-header">
                    <h2>Harmonogram i Wizyty Pacjentów</h2>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">W kolejnych etapach projektu tutaj pojawią się:</p>
                    <ul class="feature-list">
                        <li>📋 Lista pacjentów zapisanych na dzisiejsze zabiegi</li>
                        <li>🔄 Możliwość zmiany statusu wizyty (np. zrealizowana, odwołana)</li>
                        <li>⏱️ Podgląd Twojego grafiku i godzin dostępności</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
</body>
</html>