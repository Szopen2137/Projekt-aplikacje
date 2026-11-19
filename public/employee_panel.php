<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['role'] !== 'employee') {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin_panel.php' : 'client_panel.php'));
    exit;
}

$userId = (int)$_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';

$empStmt = $pdo->prepare('SELECT id, description, active FROM employees WHERE user_id = :u_id');
$empStmt->execute(['u_id' => $userId]);
$employee = $empStmt->fetch();

if (!$employee) {
    $insEmp = $pdo->prepare('INSERT INTO employees (user_id, description, active) VALUES (:u_id, :desc, 1)');
    $insEmp->execute(['u_id' => $userId, 'desc' => 'Fizjoterapeuta']);
    $empStmt->execute(['u_id' => $userId]);
    $employee = $empStmt->fetch();
}

$employeeId = (int)$employee['id'];
$allowedStatuses = ['oczekująca', 'potwierdzona', 'zrealizowana', 'anulowana'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_status') {
        $reservationId = (int)($_POST['reservation_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? '');

        if ($reservationId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
            $errorMsg = 'Nieprawidłowy identyfikator wizyty lub niedozwolony status.';
        } else {
            $ownStmt = $pdo->prepare('SELECT id FROM reservations WHERE id = :id AND employee_id = :emp_id');
            $ownStmt->execute(['id' => $reservationId, 'emp_id' => $employeeId]);

            if (!$ownStmt->fetch()) {
                $errorMsg = 'Brak uprawnień: nie możesz zarządzać rezerwacją przypisaną do innego pracownika.';
            } else {
                try {
                    $upStmt = $pdo->prepare('UPDATE reservations SET status = :status WHERE id = :id AND employee_id = :emp_id');
                    $upStmt->execute([
                        'status' => $newStatus,
                        'id'     => $reservationId,
                        'emp_id' => $employeeId
                    ]);
                    $successMsg = 'Status wizyty #' . $reservationId . ' został zmieniony na: ' . $newStatus . '.';
                } catch (\PDOException $e) {
                    $errorMsg = 'Wystąpił błąd podczas zmiany statusu wizyty.';
                }
            }
        }
    } elseif ($action === 'add_availability') {
        $dayOfWeek = trim($_POST['day_of_week'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');

        if (empty($dayOfWeek) || empty($startTime) || empty($endTime)) {
            $errorMsg = 'Wypełnij datę oraz godziny rozpoczęcia i zakończenia dyżuru.';
        } elseif ($startTime >= $endTime) {
            $errorMsg = 'Godzina zakończenia pracy musi być późniejsza niż godzina rozpoczęcia.';
        } else {
            try {
                $insAvail = $pdo->prepare('
                    INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time)
                    VALUES (:emp_id, :day, :start, :end)
                ');
                $insAvail->execute([
                    'emp_id' => $employeeId,
                    'day'    => $dayOfWeek,
                    'start'  => $startTime,
                    'end'    => $endTime
                ]);
                $successMsg = 'Dodano nowy termin dyżuru do Twojego grafiku.';
            } catch (\PDOException $e) {
                $errorMsg = 'Nie udało się dodać godzin pracy.';
            }
        }
    } elseif ($action === 'delete_availability') {
        $availId = (int)($_POST['avail_id'] ?? 0);
        try {
            $delAvail = $pdo->prepare('DELETE FROM employee_availability WHERE id = :id AND employee_id = :emp_id');
            $delAvail->execute(['id' => $availId, 'emp_id' => $employeeId]);
            if ($delAvail->rowCount() > 0) {
                $successMsg = 'Termin dyżuru został usunięty z grafiku.';
            } else {
                $errorMsg = 'Nie znaleziono terminu lub brak uprawnień do usunięcia.';
            }
        } catch (\PDOException $e) {
            $errorMsg = 'Nie udało się usunąć terminu z grafiku.';
        }
    } elseif (isset($_POST['update_profile'])) {
        $name = trim($_POST['name'] ?? '');
        $surname = trim($_POST['surname'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name) || empty($surname)) {
            $errorMsg = 'Imię i nazwisko nie mogą być puste.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($surname) < 2) {
            $errorMsg = 'Imię i nazwisko muszą mieć co najmniej 2 znaki.';
        } elseif (!empty($phone) && !preg_match('/^[0-9+\s\-]{7,20}$/', $phone)) {
            $errorMsg = 'Podano nieprawidłowy format numeru telefonu.';
        } else {
            try {
                $pdo->beginTransaction();
                $updateStmt = $pdo->prepare('UPDATE users SET name = :name, surname = :surname, phone = :phone WHERE id = :id');
                $updateStmt->execute([
                    'name'    => $name,
                    'surname' => $surname,
                    'phone'   => $phone ?: null,
                    'id'      => $userId
                ]);

                $upEmp = $pdo->prepare('UPDATE employees SET description = :desc WHERE id = :emp_id');
                $upEmp->execute([
                    'desc'   => $description,
                    'emp_id' => $employeeId
                ]);
                $pdo->commit();

                $_SESSION['name'] = $name;
                $employee['description'] = $description;
                $successMsg = 'Twoje dane pracownicze zostały zaktualizowane.';
            } catch (\PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errorMsg = 'Błąd podczas aktualizacji profilu.';
            }
        }
    }
}

$stmt = $pdo->prepare('SELECT name, surname, email, phone, role, created_at FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();

$selectedReservation = null;
if (isset($_GET['details_id'])) {
    $detailsId = (int)$_GET['details_id'];
    $detStmt = $pdo->prepare('
        SELECT r.*,
               u_client.name AS client_name, u_client.surname AS client_surname,
               u_client.email AS client_email, u_client.phone AS client_phone,
               s.name AS service_name, s.description AS service_desc, s.duration AS service_duration, s.price AS service_price,
               c.name AS category_name
        FROM reservations r
        JOIN users u_client ON r.user_id = u_client.id
        JOIN services s ON r.service_id = s.id
        JOIN service_categories c ON s.category_id = c.id
        WHERE r.id = :res_id
    ');
    $detStmt->execute(['res_id' => $detailsId]);
    $foundRes = $detStmt->fetch();

    if (!$foundRes) {
        $errorMsg = 'Wskazana rezerwacja nie istnieje.';
    } elseif ((int)$foundRes['employee_id'] !== $employeeId) {
        $errorMsg = 'Brak dostępu: nie możesz przeglądać szczegółów wizyty innego pracownika.';
    } else {
        $selectedReservation = $foundRes;
    }
}

$resStmt = $pdo->prepare('
    SELECT r.*,
           u_client.name AS client_name, u_client.surname AS client_surname,
           u_client.email AS client_email, u_client.phone AS client_phone,
           s.name AS service_name, s.duration AS service_duration, s.price AS service_price,
           c.name AS category_name
    FROM reservations r
    JOIN users u_client ON r.user_id = u_client.id
    JOIN services s ON r.service_id = s.id
    JOIN service_categories c ON s.category_id = c.id
    WHERE r.employee_id = :emp_id
    ORDER BY r.reservation_date ASC, r.start_time ASC
');
$resStmt->execute(['emp_id' => $employeeId]);
$allReservations = $resStmt->fetchAll();

$today = date('Y-m-d');
$todayReservations = [];
$futureReservations = [];
$historyReservations = [];
$pendingCount = 0;
$completedCount = 0;

foreach ($allReservations as $res) {
    if ($res['status'] === 'oczekująca') {
        $pendingCount++;
    }
    if ($res['status'] === 'zrealizowana') {
        $completedCount++;
    }

    if ($res['reservation_date'] === $today) {
        $todayReservations[] = $res;
    } elseif ($res['reservation_date'] > $today) {
        $futureReservations[] = $res;
    } else {
        $historyReservations[] = $res;
    }
}

usort($historyReservations, function ($a, $b) {
    $cmp = strcmp($b['reservation_date'], $a['reservation_date']);
    return $cmp !== 0 ? $cmp : strcmp($b['start_time'], $a['start_time']);
});

$assignedServicesStmt = $pdo->prepare('
    SELECT s.name, s.duration, s.price, c.name AS category_name
    FROM employee_services es
    JOIN services s ON es.service_id = s.id
    JOIN service_categories c ON s.category_id = c.id
    WHERE es.employee_id = :emp_id AND s.active = 1
    ORDER BY s.name ASC
');
$assignedServicesStmt->execute(['emp_id' => $employeeId]);
$assignedServices = $assignedServicesStmt->fetchAll();

$myAvailStmt = $pdo->prepare('
    SELECT id, day_of_week, start_time, end_time
    FROM employee_availability
    WHERE employee_id = :emp_id
    ORDER BY day_of_week DESC, start_time ASC
');
$myAvailStmt->execute(['emp_id' => $employeeId]);
$myAvailabilities = $myAvailStmt->fetchAll();

function empStatusBadgeClass(string $status): string
{
    if ($status === 'potwierdzona') return 'status-potwierdzona';
    if ($status === 'zrealizowana') return 'status-zrealizowana';
    if ($status === 'anulowana') return 'status-anulowana';
    return 'status-oczekujaca';
}
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
            <a href="index.php" class="header-brand">
                <span class="brand-icon">⚕</span>
                <span>Fizjo<strong>Med</strong></span>
            </a>
            <nav class="header-nav">
                <span class="user-greeting">Fizjoterapeuta: <strong><?php echo htmlspecialchars($user['name'] . ' ' . $user['surname']); ?></strong></span>
                <a href="#dzisiaj" class="btn btn-primary btn-sm">Wizyty dziś (<?php echo count($todayReservations); ?>)</a>
                <a href="#przyszle" class="btn btn-outline btn-sm">Przyszłe (<?php echo count($futureReservations); ?>)</a>
                <a href="#historia" class="btn btn-outline btn-sm">Historia</a>
                <a href="#grafik" class="btn btn-outline btn-sm">Mój grafik i profil</a>
                <a href="index.php" class="btn btn-outline btn-sm">Strona główna</a>
                <a href="logout.php" class="btn btn-danger btn-sm">Wyloguj się</a>
            </nav>
        </div>
    </header>

    <main class="panel-container">
        <div class="panel-welcome">
            <div class="badge-role badge-employee">Strefa Fizjoterapeuty</div>
            <h1>Panel Pracownika – Obsługa Wizyt</h1>
            <p>Witaj, <?php echo htmlspecialchars($user['name'] . ' ' . $user['surname']); ?>. Przeglądaj własny harmonogram zabiegów, sprawdzaj szczegóły pacjentów i aktualizuj statusy wizyt.</p>
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

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($todayReservations); ?></div>
                <div class="stat-title">Wizyty na dzisiaj (<?php echo date('d.m.Y'); ?>)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pendingCount; ?></div>
                <div class="stat-title">Oczekujące na potwierdzenie</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($futureReservations); ?></div>
                <div class="stat-title">Zaplanowane przyszłe wizyty</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $completedCount; ?></div>
                <div class="stat-title">Zrealizowane zabiegi</div>
            </div>
        </div>

        <?php if ($selectedReservation): ?>
            <section class="card mb-3" style="border-color: var(--primary);">
                <div class="card-header" style="background: #f0fdfa;">
                    <h2>Szczegóły Rezerwacji #<?php echo (int)$selectedReservation['id']; ?></h2>
                    <a href="employee_panel.php" class="btn btn-outline btn-sm">Zamknij podgląd &times;</a>
                </div>
                <div class="card-body">
                    <div class="detail-grid mb-3">
                        <div class="detail-block">
                            <h4>Dane Pacjenta</h4>
                            <p><strong><?php echo htmlspecialchars($selectedReservation['client_name'] . ' ' . $selectedReservation['client_surname']); ?></strong></p>
                            <p class="text-muted" style="font-size:0.88rem;">📞 Tel: <?php echo htmlspecialchars($selectedReservation['client_phone'] ?: 'Brak numeru'); ?></p>
                            <p class="text-muted" style="font-size:0.88rem;">✉️ E-mail: <?php echo htmlspecialchars($selectedReservation['client_email']); ?></p>
                        </div>

                        <div class="detail-block">
                            <h4>Informacje o Zabiegu</h4>
                            <p><strong><?php echo htmlspecialchars($selectedReservation['service_name']); ?></strong></p>
                            <p class="text-muted" style="font-size:0.88rem;">Kategoria: <?php echo htmlspecialchars($selectedReservation['category_name']); ?></p>
                            <p class="text-muted" style="font-size:0.88rem;">
                                Czas: <strong><?php echo (int)$selectedReservation['service_duration']; ?> min</strong> |
                                Koszt: <strong><?php echo number_format((float)$selectedReservation['service_price'], 2, ',', ' '); ?> zł</strong>
                            </p>
                        </div>

                        <div class="detail-block">
                            <h4>Termin i Status</h4>
                            <p>📅 Data: <strong><?php echo htmlspecialchars($selectedReservation['reservation_date']); ?></strong></p>
                            <p>🕒 Godzina: <strong><?php echo htmlspecialchars(substr($selectedReservation['start_time'], 0, 5) . ' – ' . substr($selectedReservation['end_time'], 0, 5)); ?></strong></p>
                            <p class="mt-2">
                                Status:
                                <span class="status-badge <?php echo empStatusBadgeClass($selectedReservation['status']); ?>">
                                    <?php echo htmlspecialchars($selectedReservation['status']); ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <?php if (!empty($selectedReservation['comment'])): ?>
                        <div class="alert alert-info mb-3">
                            <strong>Uwagi od pacjenta:</strong> <?php echo htmlspecialchars($selectedReservation['comment']); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="employee_panel.php?details_id=<?php echo (int)$selectedReservation['id']; ?>"
                          style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                        <input type="hidden" name="action" value="change_status">
                        <input type="hidden" name="reservation_id" value="<?php echo (int)$selectedReservation['id']; ?>">
                        <label for="det-status" style="margin-bottom:0;">Zmień status tej wizyty:</label>
                        <select id="det-status" name="status" class="form-control" style="width:auto; min-width:170px;">
                            <?php foreach ($allowedStatuses as $st): ?>
                                <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($selectedReservation['status'] === $st) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(htmlspecialchars($st)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">Zapisz status</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <section id="dzisiaj" class="card mb-3">
            <div class="card-header">
                <h2>Wizyty na Dzisiaj – <?php echo date('d.m.Y'); ?> (<?php echo count($todayReservations); ?>)</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Godzina</th>
                                <th>Pacjent i Kontakt</th>
                                <th>Zabieg</th>
                                <th>Status</th>
                                <th>Zmiana statusu / Szczegóły</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($todayReservations)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Brak wizyt zaplanowanych na dzisiejszy dzień.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($todayReservations as $res): ?>
                                    <tr>
                                        <td>#<?php echo (int)$res['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars(substr($res['start_time'], 0, 5) . ' – ' . substr($res['end_time'], 0, 5)); ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($res['client_name'] . ' ' . $res['client_surname']); ?></strong><br>
                                            <span class="text-muted" style="font-size:0.82rem;">
                                                Tel: <?php echo htmlspecialchars($res['client_phone'] ?: '—'); ?> | <?php echo htmlspecialchars($res['client_email']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($res['service_name']); ?><br>
                                            <span class="text-muted" style="font-size:0.8rem;">
                                                <?php echo (int)$res['service_duration']; ?> min | <?php echo number_format((float)$res['service_price'], 2, ',', ' '); ?> zł
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo empStatusBadgeClass($res['status']); ?>">
                                                <?php echo htmlspecialchars($res['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <form method="POST" action="employee_panel.php#dzisiaj" style="display:flex; gap:0.35rem; align-items:center;">
                                                    <input type="hidden" name="action" value="change_status">
                                                    <input type="hidden" name="reservation_id" value="<?php echo (int)$res['id']; ?>">
                                                    <select name="status" class="form-control" style="width:auto; padding:0.3rem 0.5rem; font-size:0.83rem;">
                                                        <?php foreach ($allowedStatuses as $st): ?>
                                                            <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($res['status'] === $st) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($st); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn btn-primary btn-sm">Zapisz</button>
                                                </form>
                                                <a href="employee_panel.php?details_id=<?php echo (int)$res['id']; ?>" class="btn btn-outline btn-sm">Szczegóły</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="przyszle" class="card mt-4">
            <div class="card-header">
                <h2>Przyszłe Wizyty (<?php echo count($futureReservations); ?>)</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Data i Godzina</th>
                                <th>Pacjent i Kontakt</th>
                                <th>Zabieg</th>
                                <th>Status</th>
                                <th>Zmiana statusu / Szczegóły</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($futureReservations)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Brak nadchodzących wizyt w kolejnych dniach.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($futureReservations as $res): ?>
                                    <tr>
                                        <td>#<?php echo (int)$res['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($res['reservation_date']); ?></strong><br>
                                            <span class="text-muted"><?php echo htmlspecialchars(substr($res['start_time'], 0, 5) . ' – ' . substr($res['end_time'], 0, 5)); ?></span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($res['client_name'] . ' ' . $res['client_surname']); ?></strong><br>
                                            <span class="text-muted" style="font-size:0.82rem;">
                                                Tel: <?php echo htmlspecialchars($res['client_phone'] ?: '—'); ?> | <?php echo htmlspecialchars($res['client_email']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($res['service_name']); ?><br>
                                            <span class="text-muted" style="font-size:0.8rem;">
                                                <?php echo (int)$res['service_duration']; ?> min | <?php echo number_format((float)$res['service_price'], 2, ',', ' '); ?> zł
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo empStatusBadgeClass($res['status']); ?>">
                                                <?php echo htmlspecialchars($res['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <form method="POST" action="employee_panel.php#przyszle" style="display:flex; gap:0.35rem; align-items:center;">
                                                    <input type="hidden" name="action" value="change_status">
                                                    <input type="hidden" name="reservation_id" value="<?php echo (int)$res['id']; ?>">
                                                    <select name="status" class="form-control" style="width:auto; padding:0.3rem 0.5rem; font-size:0.83rem;">
                                                        <?php foreach ($allowedStatuses as $st): ?>
                                                            <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($res['status'] === $st) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($st); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn btn-primary btn-sm">Zapisz</button>
                                                </form>
                                                <a href="employee_panel.php?details_id=<?php echo (int)$res['id']; ?>" class="btn btn-outline btn-sm">Szczegóły</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="historia" class="card mt-4">
            <div class="card-header">
                <h2>Historia Wizyt (<?php echo count($historyReservations); ?>)</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Data i Godzina</th>
                                <th>Pacjent</th>
                                <th>Zabieg</th>
                                <th>Status</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($historyReservations)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Brak archiwalnych wizyt w historii.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($historyReservations as $res): ?>
                                    <tr>
                                        <td>#<?php echo (int)$res['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($res['reservation_date']); ?></strong><br>
                                            <span class="text-muted"><?php echo htmlspecialchars(substr($res['start_time'], 0, 5) . ' – ' . substr($res['end_time'], 0, 5)); ?></span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($res['client_name'] . ' ' . $res['client_surname']); ?></strong><br>
                                            <span class="text-muted" style="font-size:0.82rem;">Tel: <?php echo htmlspecialchars($res['client_phone'] ?: '—'); ?></span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($res['service_name']); ?><br>
                                            <span class="text-muted" style="font-size:0.8rem;"><?php echo number_format((float)$res['service_price'], 2, ',', ' '); ?> zł</span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo empStatusBadgeClass($res['status']); ?>">
                                                <?php echo htmlspecialchars($res['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <form method="POST" action="employee_panel.php#historia" style="display:flex; gap:0.35rem; align-items:center;">
                                                    <input type="hidden" name="action" value="change_status">
                                                    <input type="hidden" name="reservation_id" value="<?php echo (int)$res['id']; ?>">
                                                    <select name="status" class="form-control" style="width:auto; padding:0.3rem 0.5rem; font-size:0.83rem;">
                                                        <?php foreach ($allowedStatuses as $st): ?>
                                                            <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($res['status'] === $st) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($st); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn btn-primary btn-sm">Zapisz</button>
                                                </form>
                                                <a href="employee_panel.php?details_id=<?php echo (int)$res['id']; ?>" class="btn btn-outline btn-sm">Szczegóły</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="grafik" class="panel-grid mt-4">
            <div class="card">
                <div class="card-header">
                    <h2>Mój Grafik Dostępności</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="employee_panel.php#grafik" class="mb-3">
                        <input type="hidden" name="action" value="add_availability">

                        <div class="form-group">
                            <label for="day_of_week">Data dyżuru *</label>
                            <input type="date" id="day_of_week" name="day_of_week" class="form-control"
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_time">Godzina od *</label>
                                <input type="time" id="start_time" name="start_time" class="form-control" value="08:00" required>
                            </div>
                            <div class="form-group">
                                <label for="end_time">Godzina do *</label>
                                <input type="time" id="end_time" name="end_time" class="form-control" value="16:00" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm">Dodaj dyżur do grafiku</button>
                    </form>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Data / Dzień</th>
                                    <th>Godziny pracy</th>
                                    <th>Akcja</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($myAvailabilities)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Brak wpisów w grafiku.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($myAvailabilities as $av): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($av['day_of_week']); ?></strong></td>
                                            <td><?php echo htmlspecialchars(substr($av['start_time'], 0, 5) . ' – ' . substr($av['end_time'], 0, 5)); ?></td>
                                            <td>
                                                <form method="POST" action="employee_panel.php#grafik" onsubmit="return confirm('Usunąć ten termin dyżuru?');">
                                                    <input type="hidden" name="action" value="delete_availability">
                                                    <input type="hidden" name="avail_id" value="<?php echo (int)$av['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Usuń</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Twoje Dane Pracownicze i Zabiegi</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="employee_panel.php#grafik" class="mb-3">
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

                        <div class="form-group">
                            <label for="description">Specjalizacja / Opis fizjoterapeuty</label>
                            <input type="text" id="description" name="description" class="form-control"
                                   value="<?php echo htmlspecialchars($employee['description'] ?? ''); ?>">
                        </div>

                        <button type="submit" class="btn btn-primary">Zapisz dane profilu</button>
                    </form>

                    <div style="border-top: 1px solid var(--border-color); padding-top: 1rem;">
                        <h3 style="font-size:0.95rem; margin-bottom:0.5rem;">Wykonywane przez Ciebie zabiegi (<?php echo count($assignedServices); ?>):</h3>
                        <?php if (empty($assignedServices)): ?>
                            <p class="text-muted" style="font-size:0.88rem;">Brak przypisanych zabiegów.</p>
                        <?php else: ?>
                            <ul class="feature-list">
                                <?php foreach ($assignedServices as $as): ?>
                                    <li>
                                        ✔️ <strong><?php echo htmlspecialchars($as['name']); ?></strong>
                                        <span class="text-muted">(<?php echo htmlspecialchars($as['category_name']); ?>, <?php echo (int)$as['duration']; ?> min, <?php echo number_format((float)$as['price'], 2, ',', ' '); ?> zł)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
