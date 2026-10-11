<?php
session_start();
require_once '../includes/config.php';

// Ochrona dostępu - tylko administrator
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    // Klient lub pracownik nie ma prawa wstępu do panelu administratora
    header('Location: ' . ($_SESSION['role'] === 'employee' ? 'employee_panel.php' : 'client_panel.php'));
    exit;
}

// Pobranie statystyk użytkowników
$clientsCount = 0;
$employeesCount = 0;
$usersList = [];

try {
    $countStmt = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
    while ($row = $countStmt->fetch()) {
        if ($row['role'] === 'client') $clientsCount = (int)$row['cnt'];
        if ($row['role'] === 'employee') $employeesCount = (int)$row['cnt'];
    }

    // Pobranie użytkowników zarejestrowanych w systemie
    $usersStmt = $pdo->query("SELECT id, name, surname, email, phone, role, active, created_at FROM users ORDER BY id DESC LIMIT 10");
    $usersList = $usersStmt->fetchAll();
} catch (\PDOException $e) {
    // Bezpieczna obsługa błędu
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administratora - Gabinet Fizjoterapii</title>
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
                <span class="user-greeting">Administrator: <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong></span>
                <a href="index.php" class="btn btn-outline btn-sm">Strona główna</a>
                <a href="logout.php" class="btn btn-danger btn-sm">Wyloguj się</a>
            </nav>
        </div>
    </header>

    <main class="panel-container">
        <div class="panel-welcome">
            <div class="badge-role badge-admin">Uprawnienia Administratora</div>
            <h1>Panel Zarządzania Placówką</h1>
            <p>Zarządzanie kontami użytkowników, personelem oraz bazą zabiegów gabinetu.</p>
        </div>

        <!-- Statystyki -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $clientsCount; ?></div>
                <div class="stat-title">Zarejestrowani Pacjenci</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $employeesCount; ?></div>
                <div class="stat-title">Fizjoterapeuci</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($usersList); ?></div>
                <div class="stat-title">Wszystkie konta w bazie</div>
            </div>
        </div>

        <!-- Tabela użytkowników w systemie -->
        <div class="card mt-4">
            <div class="card-header">
                <h2>Użytkownicy w Bazie Danych (Role i Statusy)</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Imię i Nazwisko</th>
                                <th>Adres e-mail</th>
                                <th>Telefon</th>
                                <th>Rola</th>
                                <th>Status</th>
                                <th>Data rejestracji</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($usersList)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">Brak rekordów.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($usersList as $u): ?>
                                    <tr>
                                        <td>#<?php echo htmlspecialchars($u['id']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($u['name'] . ' ' . $u['surname']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                                        <td><?php echo htmlspecialchars($u['phone'] ?? '—'); ?></td>
                                        <td>
                                            <span class="role-pill role-<?php echo htmlspecialchars($u['role']); ?>">
                                                <?php echo htmlspecialchars($u['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo ((int)$u['active'] === 1) ? 'status-active' : 'status-blocked'; ?>">
                                                <?php echo ((int)$u['active'] === 1) ? 'Aktywne' : 'Zablokowane'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>