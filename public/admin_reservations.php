<?php
require_once '../includes/admin_auth.php';
require_once '../includes/config.php';

$successMsg = '';
$errorMsg = '';

$allowedStatuses = ['oczekująca', 'potwierdzona', 'zrealizowana', 'anulowana'];

// Zmiana statusu rezerwacji przez administratora
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservationId = (int)($_POST['reservation_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');

    if ($reservationId > 0 && in_array($newStatus, $allowedStatuses, true)) {
        try {
            $stmt = $pdo->prepare('UPDATE reservations SET status = :status WHERE id = :id');
            $stmt->execute(['status' => $newStatus, 'id' => $reservationId]);
            $successMsg = "Status rezerwacji #$reservationId został zmieniony na: $newStatus.";
        } catch (\PDOException $e) {
            $errorMsg = 'Nie udało się zmienić statusu rezerwacji.';
        }
    } else {
        $errorMsg = 'Nieprawidłowy status lub identyfikator rezerwacji.';
    }
}

// Parametry filtrowania GET (data, status, pracownik)
$filterDate = trim($_GET['date'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterEmployee = (int)($_GET['employee_id'] ?? 0);

// Budowanie zapytania z dynamicznymi filtrami (Prepared Statements)
$sql = '
    SELECT r.*,
           u_client.name AS client_name, u_client.surname AS client_surname, u_client.phone AS client_phone,
           u_emp.name AS emp_name, u_emp.surname AS emp_surname,
           s.name AS service_name, s.price AS service_price
    FROM reservations r
    JOIN users u_client ON r.user_id = u_client.id
    JOIN employees e ON r.employee_id = e.id
    JOIN users u_emp ON e.user_id = u_emp.id
    JOIN services s ON r.service_id = s.id
    WHERE 1=1
';
$params = [];

if ($filterDate !== '') {
    $sql .= ' AND r.reservation_date = :r_date';
    $params['r_date'] = $filterDate;
}
if ($filterStatus !== '' && in_array($filterStatus, $allowedStatuses, true)) {
    $sql .= ' AND r.status = :r_status';
    $params['r_status'] = $filterStatus;
}
if ($filterEmployee > 0) {
    $sql .= ' AND r.employee_id = :r_emp';
    $params['r_emp'] = $filterEmployee;
}

$sql .= ' ORDER BY r.reservation_date DESC, r.start_time DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

// Lista pracowników do filtra <select>
$employeesList = $pdo->query('
    SELECT e.id, u.name, u.surname
    FROM employees e
    JOIN users u ON e.user_id = u.id
    ORDER BY u.surname ASC
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wszystkie Rezerwacje - Panel Administratora</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php require_once '../includes/admin_header.php'; ?>

    <main class="panel-container">
        <div class="panel-welcome">
            <div class="badge-role badge-admin">Moduł Zarządzania</div>
            <h1>Wszystkie Rezerwacje Wizyt</h1>
            <p>Przeglądaj, filtruj oraz zmieniaj statusy wizyt wszystkich pacjentów w gabinecie.</p>
        </div>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>

        <!-- Formularz filtrowania według daty, statusu i pracownika -->
        <div class="card">
            <div class="card-header">
                <h2>Filtrowanie rezerwacji</h2>
            </div>
            <div class="card-body">
                <form method="GET" action="admin_reservations.php" style="display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-end;">
                    <div style="flex:1; min-width:180px;">
                        <label for="date">Data wizyty:</label>
                        <input type="date" id="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filterDate); ?>">
                    </div>

                    <div style="flex:1; min-width:180px;">
                        <label for="status">Status rezerwacji:</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">-- Wszystkie statusy --</option>
                            <?php foreach ($allowedStatuses as $st): ?>
                                <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($filterStatus === $st) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(htmlspecialchars($st)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="flex:1; min-width:200px;">
                        <label for="employee_id">Fizjoterapeuta:</label>
                        <select id="employee_id" name="employee_id" class="form-control">
                            <option value="0">-- Wszyscy pracownicy --</option>
                            <?php foreach ($employeesList as $emp): ?>
                                <option value="<?php echo (int)$emp['id']; ?>" <?php echo ($filterEmployee === (int)$emp['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['name'] . ' ' . $emp['surname']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary">Filtruj</button>
                        <a href="admin_reservations.php" class="btn btn-outline">Resetuj</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabela rezerwacji -->
        <div class="card mt-4">
            <div class="card-header">
                <h2>Lista rezerwacji (<?php echo count($reservations); ?>)</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Data i Godzina</th>
                                <th>Pacjent</th>
                                <th>Fizjoterapeuta</th>
                                <th>Usługa / Zabieg</th>
                                <th>Status</th>
                                <th>Zmiana statusu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reservations)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">Brak rezerwacji spełniających podane kryteria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reservations as $res): ?>
                                    <tr>
                                        <td>#<?php echo (int)$res['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($res['reservation_date']); ?></strong><br>
                                            <span class="text-muted"><?php echo htmlspecialchars(substr($res['start_time'], 0, 5) . ' – ' . substr($res['end_time'], 0, 5)); ?></span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($res['client_name'] . ' ' . $res['client_surname']); ?></strong><br>
                                            <span class="text-muted" style="font-size:0.82rem;">Tel: <?php echo htmlspecialchars($res['client_phone'] ?? '—'); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($res['emp_name'] . ' ' . $res['emp_surname']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($res['service_name']); ?><br>
                                            <span class="text-muted" style="font-size:0.82rem;"><?php echo number_format((float)$res['service_price'], 2, ',', ' '); ?> zł</span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo ($res['status'] === 'anulowana') ? 'status-blocked' : 'status-active'; ?>">
                                                <?php echo htmlspecialchars($res['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" action="admin_reservations.php" style="display:flex; gap:0.4rem; align-items:center;">
                                                <input type="hidden" name="reservation_id" value="<?php echo (int)$res['id']; ?>">
                                                <select name="status" class="form-control" style="padding:0.3rem 0.5rem; font-size:0.85rem; width:auto;">
                                                    <?php foreach ($allowedStatuses as $st): ?>
                                                        <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($res['status'] === $st) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($st); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-primary btn-sm">Zapisz</button>
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
    </main>
</body>
</html>
