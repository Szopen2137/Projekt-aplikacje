<?php
require_once '../includes/admin_auth.php';
require_once '../includes/config.php';

$successMsg = '';
$errorMsg = '';

// Obsługa akcji POST (dodawanie pracownika, edycja opisu, przypisywanie usług N:M, grafik dostępności)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_employee') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if ($userId <= 0) {
            $errorMsg = 'Wybierz użytkownika z listy.';
        } else {
            try {
                $pdo->beginTransaction();
                // Ustawienie roli użytkownika na 'employee'
                $uStmt = $pdo->prepare("UPDATE users SET role = 'employee' WHERE id = :id");
                $uStmt->execute(['id' => $userId]);

                // Sprawdzenie czy już ma wpis w tabeli employees
                $checkStmt = $pdo->prepare('SELECT id FROM employees WHERE user_id = :user_id');
                $checkStmt->execute(['user_id' => $userId]);
                if (!$checkStmt->fetch()) {
                    $eStmt = $pdo->prepare('INSERT INTO employees (user_id, description, active) VALUES (:user_id, :description, 1)');
                    $eStmt->execute(['user_id' => $userId, 'description' => $description]);
                }
                $pdo->commit();
                $successMsg = 'Nowy pracownik został dodany.';
            } catch (\PDOException $e) {
                $pdo->rollBack();
                $errorMsg = 'Wystąpił błąd podczas dodawania pracownika.';
            }
        }
    } elseif ($action === 'update_employee') {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $selectedServices = $_POST['services'] ?? [];

        try {
            $pdo->beginTransaction();

            // Aktualizacja opisu pracownika
            $upStmt = $pdo->prepare('UPDATE employees SET description = :description WHERE id = :id');
            $upStmt->execute(['description' => $description, 'id' => $employeeId]);

            // Aktualizacja przypisanych usług (Relacja N:M w employee_services)
            $delSrv = $pdo->prepare('DELETE FROM employee_services WHERE employee_id = :emp_id');
            $delSrv->execute(['emp_id' => $employeeId]);

            if (!empty($selectedServices) && is_array($selectedServices)) {
                $insSrv = $pdo->prepare('INSERT INTO employee_services (employee_id, service_id) VALUES (:emp_id, :srv_id)');
                foreach ($selectedServices as $srvId) {
                    $insSrv->execute([
                        'emp_id' => $employeeId,
                        'srv_id' => (int)$srvId
                    ]);
                }
            }

            $pdo->commit();
            $successMsg = 'Dane pracownika oraz przypisane usługi zostały zapisane.';
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $errorMsg = 'Błąd podczas zapisywania zmian u pracownika.';
        }
    } elseif ($action === 'toggle_active') {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        try {
            $stmt = $pdo->prepare('UPDATE employees SET active = NOT active WHERE id = :id');
            $stmt->execute(['id' => $employeeId]);
            $successMsg = 'Status aktywności pracownika został zmieniony.';
        } catch (\PDOException $e) {
            $errorMsg = 'Nie udało się zmienić statusu pracownika.';
        }
    } elseif ($action === 'add_availability') {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $dayOfWeek = trim($_POST['day_of_week'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');

        if ($employeeId <= 0 || empty($dayOfWeek) || empty($startTime) || empty($endTime)) {
            $errorMsg = 'Wypełnij wszystkie pola grafiku pracy.';
        } elseif ($startTime >= $endTime) {
            $errorMsg = 'Godzina zakończenia pracy musi być późniejsza niż godzina rozpoczęcia.';
        } else {
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time)
                    VALUES (:emp_id, :day, :start, :end)
                ');
                $stmt->execute([
                    'emp_id' => $employeeId,
                    'day'    => $dayOfWeek,
                    'start'  => $startTime,
                    'end'    => $endTime
                ]);
                $successMsg = 'Dodano nowy termin dostępności pracownika.';
            } catch (\PDOException $e) {
                $errorMsg = 'Wystąpił błąd podczas dodawania godzin pracy.';
            }
        }
    } elseif ($action === 'delete_availability') {
        $availId = (int)($_POST['avail_id'] ?? 0);
        try {
            $stmt = $pdo->prepare('DELETE FROM employee_availability WHERE id = :id');
            $stmt->execute(['id' => $availId]);
            $successMsg = 'Termin dostępności został usunięty z grafiku.';
        } catch (\PDOException $e) {
            $errorMsg = 'Nie udało się usunąć terminu.';
        }
    }
}

// Pobranie listy pracowników (połączenie employees + users)
$employees = $pdo->query('
    SELECT e.id AS employee_id, e.user_id, e.description, e.active, u.name, u.surname, u.email, u.phone
    FROM employees e
    JOIN users u ON e.user_id = u.id
    ORDER BY e.id ASC
')->fetchAll();

// Pobranie wszystkich usług do checkboxów N:M
$allServices = $pdo->query('SELECT id, name, duration, price FROM services ORDER BY name ASC')->fetchAll();

// Pobranie przypisań N:M (employee_services)
$empServicesRaw = $pdo->query('SELECT employee_id, service_id FROM employee_services')->fetchAll();
$empServicesMap = [];
foreach ($empServicesRaw as $row) {
    $empServicesMap[(int)$row['employee_id']][] = (int)$row['service_id'];
}

// Pobranie dostępności pracowników (employee_availability)
$availabilities = $pdo->query('
    SELECT ea.*, u.name, u.surname
    FROM employee_availability ea
    JOIN employees e ON ea.employee_id = e.id
    JOIN users u ON e.user_id = u.id
    ORDER BY ea.day_of_week DESC, ea.start_time ASC
')->fetchAll();

// Użytkownicy, którzy nie są jeszcze w tabeli employees (do awansowania na pracownika)
$availableUsers = $pdo->query('
    SELECT id, name, surname, email
    FROM users
    WHERE id NOT IN (SELECT user_id FROM employees)
    ORDER BY surname ASC
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pracownicy i Grafik - Panel Administratora</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php require_once '../includes/admin_header.php'; ?>

    <main class="panel-container">
        <div class="panel-welcome">
            <div class="badge-role badge-admin">Moduł Zarządzania</div>
            <h1>Pracownicy, Usługi (N:M) i Dostępność</h1>
            <p>Zarządzaj kadrą fizjoterapeutów, przypisuj im wykonywane zabiegi oraz określaj grafik godzin pracy.</p>
        </div>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>

        <!-- Sekcja 1: Lista pracowników i przypisywanie usług N:M -->
        <div class="card">
            <div class="card-header">
                <h2>Kadra Fizjoterapeutów i Przypisane Zabiegi</h2>
            </div>
            <div class="card-body">
                <?php if (empty($employees)): ?>
                    <p class="text-muted">Brak pracowników w bazie danych.</p>
                <?php else: ?>
                    <?php foreach ($employees as $emp): ?>
                        <?php $assignedIds = $empServicesMap[(int)$emp['employee_id']] ?? []; ?>
                        <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem;">
                                <div>
                                    <h3 style="font-size:1.15rem;">
                                        #<?php echo (int)$emp['employee_id']; ?>
                                        <?php echo htmlspecialchars($emp['name'] . ' ' . $emp['surname']); ?>
                                    </h3>
                                    <span class="text-muted" style="font-size:0.88rem;">
                                        E-mail: <?php echo htmlspecialchars($emp['email']); ?> |
                                        Tel: <?php echo htmlspecialchars($emp['phone'] ?? '—'); ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="status-badge <?php echo ((int)$emp['active'] === 1) ? 'status-active' : 'status-blocked'; ?>">
                                        <?php echo ((int)$emp['active'] === 1) ? 'Aktywny' : 'Nieaktywny'; ?>
                                    </span>
                                    <form method="POST" action="admin_employees.php" style="display:inline; margin-left:0.5rem;">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="employee_id" value="<?php echo (int)$emp['employee_id']; ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm">
                                            <?php echo ((int)$emp['active'] === 1) ? 'Dezaktywuj' : 'Aktywuj'; ?>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <form method="POST" action="admin_employees.php">
                                <input type="hidden" name="action" value="update_employee">
                                <input type="hidden" name="employee_id" value="<?php echo (int)$emp['employee_id']; ?>">

                                <div class="form-group">
                                    <label>Specjalizacja / Opis fizjoterapeuty:</label>
                                    <input type="text" name="description" class="form-control"
                                           value="<?php echo htmlspecialchars($emp['description'] ?? ''); ?>"
                                           placeholder="np. Magister fizjoterapii, terapia manualna">
                                </div>

                                <div class="form-group">
                                    <label>Wykonywane zabiegi (Relacja N:M – zaznacz usługi):</label>
                                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap:0.5rem; margin-top:0.4rem;">
                                        <?php foreach ($allServices as $srv): ?>
                                            <label style="font-weight:normal; display:flex; align-items:center; gap:0.4rem; background:#f8fafc; padding:0.45rem 0.65rem; border-radius:6px; border:1px solid #e2e8f0;">
                                                <input type="checkbox" name="services[]" value="<?php echo (int)$srv['id']; ?>"
                                                    <?php echo in_array((int)$srv['id'], $assignedIds, true) ? 'checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars($srv['name']); ?> (<?php echo (int)$srv['duration']; ?> min)</span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-sm">Zapisz opis i przypisane zabiegi</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel-grid mt-4">
            <!-- Sekcja 2: Dodawanie godzin pracy (Dostępność) -->
            <div class="card">
                <div class="card-header">
                    <h2>Dodaj Godziny Pracy (Grafik)</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="admin_employees.php">
                        <input type="hidden" name="action" value="add_availability">

                        <div class="form-group">
                            <label for="emp_avail">Pracownik *</label>
                            <select id="emp_avail" name="employee_id" class="form-control" required>
                                <option value="">-- Wybierz fizjoterapeutę --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo (int)$emp['employee_id']; ?>">
                                        <?php echo htmlspecialchars($emp['name'] . ' ' . $emp['surname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="day_of_week">Data dyżuru *</label>
                            <input type="date" id="day_of_week" name="day_of_week" class="form-control" required
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_time">Godzina od *</label>
                                <input type="time" id="start_time" name="start_time" class="form-control" required value="08:00">
                            </div>
                            <div class="form-group">
                                <label for="end_time">Godzina do *</label>
                                <input type="time" id="end_time" name="end_time" class="form-control" required value="16:00">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Dodaj do grafiku</button>
                    </form>
                </div>
            </div>

            <!-- Sekcja 3: Mianowanie użytkownika pracownikiem -->
            <div class="card">
                <div class="card-header">
                    <h2>Dodaj Nowego Pracownika</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($availableUsers)): ?>
                        <p class="text-muted">Wszyscy zarejestrowani użytkownicy posiadają już profil lub brak wolnych kont do przypisania.</p>
                    <?php else: ?>
                        <form method="POST" action="admin_employees.php">
                            <input type="hidden" name="action" value="add_employee">

                            <div class="form-group">
                                <label for="user_id">Wybierz konto użytkownika *</label>
                                <select id="user_id" name="user_id" class="form-control" required>
                                    <option value="">-- Wybierz użytkownika --</option>
                                    <?php foreach ($availableUsers as $u): ?>
                                        <option value="<?php echo (int)$u['id']; ?>">
                                            <?php echo htmlspecialchars($u['name'] . ' ' . $u['surname'] . ' (' . $u['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="emp_desc">Specjalizacja i opis</label>
                                <textarea id="emp_desc" name="description" class="form-control" rows="3"
                                          placeholder="np. Fizjoterapeuta sportowy i ortopedyczny..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">Utwórz profil pracownika</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tabela aktualnego grafiku dostępności -->
        <div class="card mt-4">
            <div class="card-header">
                <h2>Zdefiniowany Grafik Dostępności Pracowników</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fizjoterapeuta</th>
                                <th>Data</th>
                                <th>Godziny pracy</th>
                                <th>Akcja</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($availabilities)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">Brak wpisów w grafiku dostępności.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($availabilities as $av): ?>
                                    <tr>
                                        <td>#<?php echo (int)$av['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($av['name'] . ' ' . $av['surname']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($av['day_of_week']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($av['start_time'], 0, 5) . ' – ' . substr($av['end_time'], 0, 5)); ?></td>
                                        <td>
                                            <form method="POST" action="admin_employees.php" style="display:inline;" onsubmit="return confirm('Usunąć ten termin z grafiku?');">
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
    </main>
</body>
</html>
