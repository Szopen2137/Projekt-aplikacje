<?php
session_start();
require_once '../includes/config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? null;
$userName = $_SESSION['name'] ?? null;

$panelUrl = 'login.php';
if ($isLoggedIn) {
    if ($userRole === 'admin') $panelUrl = 'admin_panel.php';
    elseif ($userRole === 'employee') $panelUrl = 'employee_panel.php';
    else $panelUrl = 'client_panel.php';
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gabinet Fizjoterapii - FizjoMed</title>
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
                <?php if ($isLoggedIn): ?>
                    <span class="user-greeting">Witaj, <strong><?php echo htmlspecialchars($userName); ?></strong>!</span>
                    <a href="<?php echo htmlspecialchars($panelUrl); ?>" class="btn btn-primary btn-sm">Mój Panel</a>
                    <a href="logout.php" class="btn btn-outline btn-sm">Wyloguj</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline btn-sm">Logowanie</a>
                    <a href="register.php" class="btn btn-primary btn-sm">Rejestracja</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="panel-container">
        <!-- Baner powitalny Hero -->
        <div class="hero-banner">
            <h1>Profesjonalna Rehabilitacja i Fizjoterapia</h1>
            <p>Witamy w internetowym systemie gabinetu FizjoMed. Zarejestruj się, aby wygodnie zarządzać swoimi wizytami i zabiegami.</p>
            <div class="hero-buttons">
                <?php if ($isLoggedIn): ?>
                    <a href="<?php echo htmlspecialchars($panelUrl); ?>" class="btn btn-primary">Przejdź do panelu &rarr;</a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary">Zarejestruj się jako pacjent</a>
                    <a href="login.php" class="btn btn-outline">Zaloguj się do systemu</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Karty oferty gabinetu -->
        <div class="stats-grid mt-4">
            <div class="card">
                <div class="card-body">
                    <h3>👐 Terapia Manualna</h3>
                    <p class="text-muted">Kompleksowa diagnoza i terapia stawów, kręgosłupa i tkanek miękkich.</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h3>⚡ Fizykoterapia</h3>
                    <p class="text-muted">Zabiegi elektroterapii, prądy TENS i ultradźwięki przyspieszające regenerację.</p>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h3>📅 Rezerwacja Online</h3>
                    <p class="text-muted">Szybki dostęp do kalendarza fizjoterapeutów i planowania wizyt.</p>
                </div>
            </div>
        </div>

    </main>
</body>
</html>