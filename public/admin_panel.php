<?php
require_once '../includes/admin_auth.php';
require_once '../includes/config.php';

// 5 liczników wymaganych na Dashboardzie w Etapie 3
$clientsCount = 0;
$employeesCount = 0;
$servicesCount = 0;
$reservationsCount = 0;
$todayReservationsCount = 0;
$usersList = [];

try {
    $clientsCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn();
    $employeesCount = (int)$pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    $servicesCount = (int)$pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
    $reservationsCount = (int)$pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn();
    $todayReservationsCount = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE reservation_date = CURDATE()")->fetchColumn();

    // Pobranie ostatnich użytkowników zarejestrowanych w systemie
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
    <title>Dashboard Administratora - Gabinet Fizjoterapii</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php require_once '../includes/admin_header.php'; ?>

    <main class="panel-container">
        <div class="panel-welcome">
            <div class="badge-role badge-admin">Uprawnienia Administratora</div>
            <h1>Panel Zarządzania Placówką (Dashboard)</h1>
            <p>Podsumowanie statystyk gabinetu oraz szybki dostęp do modułów zarządzania.</p>
        </div>

        <!-- 5 wymaganych liczników z rzeczywistymi danymi z bazy -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $clientsCount; ?></div>
                <div class="stat-title">Zarejestrowani Klienci</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $employeesCount; ?></div>
                <div class="stat-title">Pracownicy</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $servicesCount; ?></div>
                <div class="stat-title">Usługi w ofercie</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $reservationsCount; ?></div>
                <div class="stat-title">Wszystkie rezerwacje</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $todayReservationsCount; ?></div>
                <div class="stat-title">Rezerwacje na dziś</div>
            </div>
        </div>

        <!-- Tabela ostatnich użytkowników w systemie -->
        <div class="card mt-4">
            <div class="card-header">
                <h2>Ostatnio zarejestrowani użytkownicy</h2>
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
