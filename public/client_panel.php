<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SESSION['role'] !== 'client') {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin_panel.php' : 'employee_panel.php'));
    exit;
}

$userId = (int)$_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';

function getPolishWeekdayName(int $isoDay): string
{
    $names = [
        1 => 'Poniedziałek',
        2 => 'Wtorek',
        3 => 'Środa',
        4 => 'Czwartek',
        5 => 'Piątek',
        6 => 'Sobota',
        7 => 'Niedziela'
    ];
    return $names[$isoDay] ?? '';
}

function timeToMinutes(string $timeStr): int
{
    $parts = explode(':', $timeStr);
    return ((int)($parts[0] ?? 0)) * 60 + ((int)($parts[1] ?? 0));
}

function minutesToTime(int $minutes): string
{
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return sprintf('%02d:%02d', $h, $m);
}

function getEmployeeShiftsForDate(PDO $pdo, int $employeeId, string $date): array
{
    $stmt = $pdo->prepare('
        SELECT day_of_week, start_time, end_time
        FROM employee_availability
        WHERE employee_id = :emp_id
        ORDER BY day_of_week DESC, start_time ASC
    ');
    $stmt->execute(['emp_id' => $employeeId]);
    $rows = $stmt->fetchAll();

    $exactShifts = [];
    foreach ($rows as $row) {
        if ($row['day_of_week'] === $date) {
            $exactShifts[] = [
                'start_time' => substr($row['start_time'], 0, 5) . ':00',
                'end_time'   => substr($row['end_time'], 0, 5) . ':00'
            ];
        }
    }

    if (!empty($exactShifts)) {
        return $exactShifts;
    }

    $ts = strtotime($date);
    if ($ts === false) {
        return [];
    }
    $targetWeekday = (int)date('N', $ts);
    $weekdayShifts = [];
    $seen = [];

    foreach ($rows as $row) {
        $rowTs = strtotime($row['day_of_week']);
        if ($rowTs !== false && (int)date('N', $rowTs) === $targetWeekday) {
            $start = substr($row['start_time'], 0, 5) . ':00';
            $end = substr($row['end_time'], 0, 5) . ':00';
            $key = $start . '-' . $end;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $weekdayShifts[] = [
                    'start_time' => $start,
                    'end_time'   => $end
                ];
            }
        }
    }

    usort($weekdayShifts, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));
    return $weekdayShifts;
}

function getEmployeeUpcomingDates(PDO $pdo, int $employeeId, int $limit = 8): array
{
    $upcoming = [];
    $todayTs = strtotime(date('Y-m-d'));

    for ($i = 0; $i < 28 && count($upcoming) < $limit; $i++) {
        $candidateTs = strtotime("+$i days", $todayTs);
        $candidateDate = date('Y-m-d', $candidateTs);
        $shifts = getEmployeeShiftsForDate($pdo, $employeeId, $candidateDate);

        if (!empty($shifts)) {
            $hoursList = array_map(
                fn($s) => substr($s['start_time'], 0, 5) . '–' . substr($s['end_time'], 0, 5),
                $shifts
            );
            $upcoming[] = [
                'date'  => $candidateDate,
                'label' => getPolishWeekdayName((int)date('N', $candidateTs)) . ', ' . date('d.m.Y', $candidateTs),
                'hours' => implode(', ', $hoursList)
            ];
        }
    }

    return $upcoming;
}

function calculateTimeSlots(PDO $pdo, int $employeeId, string $date, int $durationMinutes, int $excludeReservationId = 0): array
{
    $shifts = getEmployeeShiftsForDate($pdo, $employeeId, $date);
    if (empty($shifts)) {
        return ['shifts' => [], 'slots' => []];
    }

    $resStmt = $pdo->prepare("
        SELECT id, start_time, end_time
        FROM reservations
        WHERE employee_id = :emp_id
          AND reservation_date = :r_date
          AND status != 'anulowana'
          AND id != :exc_id
        ORDER BY start_time ASC
    ");
    $resStmt->execute([
        'emp_id' => $employeeId,
        'r_date' => $date,
        'exc_id' => $excludeReservationId
    ]);
    $existingReservations = $resStmt->fetchAll();

    $today = date('Y-m-d');
    $nowMinutes = timeToMinutes(date('H:i'));
    $slots = [];
    $stepMinutes = 15;

    foreach ($shifts as $shift) {
        $shiftStartMin = timeToMinutes($shift['start_time']);
        $shiftEndMin = timeToMinutes($shift['end_time']);

        for ($slotStart = $shiftStartMin; $slotStart + $durationMinutes <= $shiftEndMin; $slotStart += $stepMinutes) {
            $slotEnd = $slotStart + $durationMinutes;
            $slotStartStr = minutesToTime($slotStart);
            $slotEndStr = minutesToTime($slotEnd);
            $status = 'available';
            $reason = 'Wolny termin';

            if ($date < $today || ($date === $today && $slotStart <= $nowMinutes)) {
                $status = 'past';
                $reason = 'Termin minął';
            } else {
                foreach ($existingReservations as $res) {
                    $resStart = timeToMinutes($res['start_time']);
                    $resEnd = timeToMinutes($res['end_time']);

                    if ($slotStart < $resEnd && $slotEnd > $resStart) {
                        $status = 'conflict';
                        $reason = 'Zajęte (' . substr($res['start_time'], 0, 5) . '–' . substr($res['end_time'], 0, 5) . ')';
                        break;
                    }
                }
            }

            $slots[] = [
                'start'  => $slotStartStr,
                'end'    => $slotEndStr,
                'status' => $status,
                'reason' => $reason
            ];
        }
    }

    $formattedShifts = array_map(
        fn($s) => substr($s['start_time'], 0, 5) . ' – ' . substr($s['end_time'], 0, 5),
        $shifts
    );

    return [
        'shifts' => $formattedShifts,
        'slots'  => $slots
    ];
}

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $ajaxAction = $_GET['ajax'];

    if ($ajaxAction === 'employees') {
        $serviceId = (int)($_GET['service_id'] ?? 0);
        $stmt = $pdo->prepare('
            SELECT e.id AS employee_id, e.description, u.name, u.surname
            FROM employee_services es
            JOIN employees e ON es.employee_id = e.id
            JOIN users u ON e.user_id = u.id
            JOIN services s ON es.service_id = s.id
            WHERE es.service_id = :srv_id
              AND s.active = 1
              AND e.active = 1
              AND u.active = 1
            ORDER BY u.surname ASC
        ');
        $stmt->execute(['srv_id' => $serviceId]);
        $empList = $stmt->fetchAll();

        foreach ($empList as &$emp) {
            $emp['employee_id'] = (int)$emp['employee_id'];
            $emp['upcoming_dates'] = getEmployeeUpcomingDates($pdo, $emp['employee_id'], 8);
        }
        unset($emp);

        echo json_encode(['ok' => true, 'employees' => $empList]);
        exit;
    }

    if ($ajaxAction === 'slots') {
        $serviceId = (int)($_GET['service_id'] ?? 0);
        $employeeId = (int)($_GET['employee_id'] ?? 0);
        $date = trim($_GET['date'] ?? '');
        $excludeId = (int)($_GET['exclude_id'] ?? 0);

        if ($serviceId <= 0 || $employeeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['ok' => false, 'message' => 'Nieprawidłowe parametry zapytania.']);
            exit;
        }

        $srvStmt = $pdo->prepare('
            SELECT s.id, s.name, s.duration, s.price
            FROM services s
            JOIN employee_services es ON es.service_id = s.id
            JOIN employees e ON es.employee_id = e.id
            JOIN users u ON e.user_id = u.id
            WHERE s.id = :srv_id
              AND e.id = :emp_id
              AND s.active = 1
              AND e.active = 1
              AND u.active = 1
        ');
        $srvStmt->execute(['srv_id' => $serviceId, 'emp_id' => $employeeId]);
        $srv = $srvStmt->fetch();

        if (!$srv) {
            echo json_encode(['ok' => false, 'message' => 'Wybrany pracownik nie wykonuje tej usługi.']);
            exit;
        }

        $result = calculateTimeSlots($pdo, $employeeId, $date, (int)$srv['duration'], $excludeId);
        echo json_encode([
            'ok'       => true,
            'date'     => $date,
            'duration' => (int)$srv['duration'],
            'shifts'   => $result['shifts'],
            'slots'    => $result['slots']
        ]);
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Nieznana akcja.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_reservation') {
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $reservationDate = trim($_POST['reservation_date'] ?? '');
        $startTimeInput = trim($_POST['start_time'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        $dateObj = \DateTime::createFromFormat('Y-m-d', $reservationDate);
        $validDate = $dateObj && $dateObj->format('Y-m-d') === $reservationDate;
        $validTime = (bool)preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $startTimeInput);

        if ($serviceId <= 0 || $employeeId <= 0 || !$validDate || !$validTime) {
            $errorMsg = 'Wybierz poprawną usługę, fizjoterapeutę, datę oraz godzinę wizyty.';
        } elseif (mb_strlen($comment) > 500) {
            $errorMsg = 'Uwagi do rezerwacji mogą mieć maksymalnie 500 znaków.';
        } else {
            $verifyStmt = $pdo->prepare('
                SELECT s.id AS service_id, s.name AS service_name, s.duration, s.price,
                       e.id AS employee_id, u.name AS emp_name, u.surname AS emp_surname
                FROM services s
                JOIN employee_services es ON es.service_id = s.id
                JOIN employees e ON es.employee_id = e.id
                JOIN users u ON e.user_id = u.id
                WHERE s.id = :srv_id
                  AND e.id = :emp_id
                  AND s.active = 1
                  AND e.active = 1
                  AND u.active = 1
            ');
            $verifyStmt->execute(['srv_id' => $serviceId, 'emp_id' => $employeeId]);
            $verified = $verifyStmt->fetch();

            if (!$verified) {
                $errorMsg = 'Wybrana usługa jest nieaktywna lub przypisany fizjoterapeuta jej nie wykonuje.';
            } else {
                $duration = (int)$verified['duration'];
                $startMin = timeToMinutes($startTimeInput);
                $endMin = $startMin + $duration;

                if ($endMin > 24 * 60) {
                    $errorMsg = 'Czas zakończenia zabiegu wykracza poza dobę.';
                } else {
                    $startTimeSql = minutesToTime($startMin) . ':00';
                    $endTimeSql = minutesToTime($endMin) . ':00';
                    $today = date('Y-m-d');
                    $nowTimeSql = date('H:i:s');

                    if ($reservationDate < $today || ($reservationDate === $today && $startTimeSql <= $nowTimeSql)) {
                        $errorMsg = 'Nie można utworzyć rezerwacji na termin w przeszłości.';
                    } else {
                        $shifts = getEmployeeShiftsForDate($pdo, $employeeId, $reservationDate);
                        if (empty($shifts)) {
                            $errorMsg = 'Wybrany fizjoterapeuta nie przyjmuje w dniu ' . $reservationDate . '.';
                        } else {
                            $fitsInWorkingHours = false;
                            foreach ($shifts as $shift) {
                                if ($startTimeSql >= $shift['start_time'] && $endTimeSql <= $shift['end_time']) {
                                    $fitsInWorkingHours = true;
                                    break;
                                }
                            }

                            if (!$fitsInWorkingHours) {
                                $errorMsg = 'Wybrany przedział czasowy (' . substr($startTimeSql, 0, 5) . ' – ' . substr($endTimeSql, 0, 5) . ') wykracza poza godziny pracy fizjoterapeuty.';
                            } else {
                                try {
                                    $pdo->beginTransaction();

                                    $conflictStmt = $pdo->prepare("
                                        SELECT id, start_time, end_time
                                        FROM reservations
                                        WHERE employee_id = :emp_id
                                          AND reservation_date = :r_date
                                          AND status != 'anulowana'
                                          AND start_time < :new_end
                                          AND end_time > :new_start
                                        FOR UPDATE
                                    ");
                                    $conflictStmt->execute([
                                        'emp_id'    => $employeeId,
                                        'r_date'    => $reservationDate,
                                        'new_end'   => $endTimeSql,
                                        'new_start' => $startTimeSql
                                    ]);
                                    $conflict = $conflictStmt->fetch();

                                    if ($conflict) {
                                        $pdo->rollBack();
                                        $errorMsg = 'Wybrany termin (' . substr($startTimeSql, 0, 5) . ' – ' . substr($endTimeSql, 0, 5) . ') koliduje z inną rezerwacją (' . substr($conflict['start_time'], 0, 5) . ' – ' . substr($conflict['end_time'], 0, 5) . '). Wybierz inną godzinę.';
                                    } else {
                                        $clientConflictStmt = $pdo->prepare("
                                            SELECT id, start_time, end_time
                                            FROM reservations
                                            WHERE user_id = :u_id
                                              AND reservation_date = :r_date
                                              AND status != 'anulowana'
                                              AND start_time < :new_end
                                              AND end_time > :new_start
                                            FOR UPDATE
                                        ");
                                        $clientConflictStmt->execute([
                                            'u_id'      => $userId,
                                            'r_date'    => $reservationDate,
                                            'new_end'   => $endTimeSql,
                                            'new_start' => $startTimeSql
                                        ]);
                                        $clientConflict = $clientConflictStmt->fetch();

                                        if ($clientConflict) {
                                            $pdo->rollBack();
                                            $errorMsg = 'Masz już inną własną rezerwację w tym samym czasie (' . substr($clientConflict['start_time'], 0, 5) . ' – ' . substr($clientConflict['end_time'], 0, 5) . ').';
                                        } else {
                                            $insertStmt = $pdo->prepare("
                                                INSERT INTO reservations (user_id, employee_id, service_id, reservation_date, start_time, end_time, status, comment)
                                                VALUES (:u_id, :emp_id, :srv_id, :r_date, :start_t, :end_t, 'oczekująca', :comment)
                                            ");
                                            $insertStmt->execute([
                                                'u_id'    => $userId,
                                                'emp_id'  => $employeeId,
                                                'srv_id'  => $serviceId,
                                                'r_date'  => $reservationDate,
                                                'start_t' => $startTimeSql,
                                                'end_t'   => $endTimeSql,
                                                'comment' => $comment !== '' ? $comment : null
                                            ]);

                                            $pdo->commit();
                                            $successMsg = 'Rezerwacja na zabieg „' . $verified['service_name'] . '” w dniu ' . $reservationDate . ' (' . substr($startTimeSql, 0, 5) . ' – ' . substr($endTimeSql, 0, 5) . ') została pomyślnie zapisana!';
                                            $_GET['service_id'] = null;
                                            $_GET['employee_id'] = null;
                                            $_GET['date'] = null;
                                        }
                                    }
                                } catch (\PDOException $e) {
                                    if ($pdo->inTransaction()) {
                                        $pdo->rollBack();
                                    }
                                    $errorMsg = 'Wystąpił błąd podczas zapisywania rezerwacji.';
                                }
                            }
                        }
                    }
                }
            }
        }
    } elseif ($action === 'cancel_reservation') {
        $reservationId = (int)($_POST['reservation_id'] ?? 0);
        $checkStmt = $pdo->prepare('
            SELECT id, reservation_date, start_time, status
            FROM reservations
            WHERE id = :id AND user_id = :u_id
        ');
        $checkStmt->execute(['id' => $reservationId, 'u_id' => $userId]);
        $res = $checkStmt->fetch();

        $today = date('Y-m-d');
        $nowTime = date('H:i:s');

        if (!$res) {
            $errorMsg = 'Nie znaleziono rezerwacji lub nie masz uprawnień do jej anulowania.';
        } elseif (!in_array($res['status'], ['oczekująca', 'potwierdzona'], true)) {
            $errorMsg = 'Nie można anulować rezerwacji o statusie: ' . $res['status'] . '.';
        } elseif ($res['reservation_date'] < $today || ($res['reservation_date'] === $today && $res['start_time'] <= $nowTime)) {
            $errorMsg = 'Można anulować wyłącznie przyszłe rezerwacje.';
        } else {
            try {
                $cancelStmt = $pdo->prepare("UPDATE reservations SET status = 'anulowana' WHERE id = :id AND user_id = :u_id");
                $cancelStmt->execute(['id' => $reservationId, 'u_id' => $userId]);
                $successMsg = 'Twoja rezerwacja #' . $reservationId . ' została anulowana.';
            } catch (\PDOException $e) {
                $errorMsg = 'Nie udało się anulować rezerwacji.';
            }
        }
    } elseif ($action === 'reschedule_reservation') {
        $reservationId = (int)($_POST['reservation_id'] ?? 0);
        $newDate = trim($_POST['new_date'] ?? '');
        $newTime = trim($_POST['new_time'] ?? '');

        $dateObj = \DateTime::createFromFormat('Y-m-d', $newDate);
        $validDate = $dateObj && $dateObj->format('Y-m-d') === $newDate;
        $validTime = (bool)preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $newTime);

        $checkStmt = $pdo->prepare('
            SELECT r.*, s.duration, s.name AS service_name, s.active AS service_active, e.active AS emp_active
            FROM reservations r
            JOIN services s ON r.service_id = s.id
            JOIN employees e ON r.employee_id = e.id
            WHERE r.id = :id AND r.user_id = :u_id
        ');
        $checkStmt->execute(['id' => $reservationId, 'u_id' => $userId]);
        $res = $checkStmt->fetch();

        $today = date('Y-m-d');
        $nowTimeSql = date('H:i:s');

        if (!$res) {
            $errorMsg = 'Nie znaleziono rezerwacji lub brak uprawnień.';
        } elseif (!$validDate || !$validTime) {
            $errorMsg = 'Podaj poprawną nową datę i godzinę wizyty.';
        } elseif (!in_array($res['status'], ['oczekująca', 'potwierdzona'], true)) {
            $errorMsg = 'Nie można przełożyć zakończonej lub anulowanej rezerwacji.';
        } elseif ($res['reservation_date'] < $today || ($res['reservation_date'] === $today && $res['start_time'] <= $nowTimeSql)) {
            $errorMsg = 'Można przełożyć wyłącznie przyszłą rezerwację.';
        } else {
            $duration = (int)$res['duration'];
            $startMin = timeToMinutes($newTime);
            $endMin = $startMin + $duration;
            $startTimeSql = minutesToTime($startMin) . ':00';
            $endTimeSql = minutesToTime($endMin) . ':00';

            if ($newDate < $today || ($newDate === $today && $startTimeSql <= $nowTimeSql)) {
                $errorMsg = 'Nowy termin wizyty nie może być w przeszłości.';
            } else {
                $shifts = getEmployeeShiftsForDate($pdo, (int)$res['employee_id'], $newDate);
                $fitsInShift = false;
                foreach ($shifts as $shift) {
                    if ($startTimeSql >= $shift['start_time'] && $endTimeSql <= $shift['end_time']) {
                        $fitsInShift = true;
                        break;
                    }
                }

                if (!$fitsInShift) {
                    $errorMsg = 'Nowy termin (' . substr($startTimeSql, 0, 5) . ' – ' . substr($endTimeSql, 0, 5) . ') nie mieści się w godzinach pracy fizjoterapeuty w dniu ' . $newDate . '.';
                } else {
                    try {
                        $pdo->beginTransaction();

                        $confStmt = $pdo->prepare("
                            SELECT id, start_time, end_time
                            FROM reservations
                            WHERE employee_id = :emp_id
                              AND reservation_date = :r_date
                              AND status != 'anulowana'
                              AND id != :res_id
                              AND start_time < :new_end
                              AND end_time > :new_start
                            FOR UPDATE
                        ");
                        $confStmt->execute([
                            'emp_id'    => (int)$res['employee_id'],
                            'r_date'    => $newDate,
                            'res_id'    => $reservationId,
                            'new_end'   => $endTimeSql,
                            'new_start' => $startTimeSql
                        ]);
                        $conflict = $confStmt->fetch();

                        if ($conflict) {
                            $pdo->rollBack();
                            $errorMsg = 'Nowy termin koliduje z inną wizytą (' . substr($conflict['start_time'], 0, 5) . ' – ' . substr($conflict['end_time'], 0, 5) . ').';
                        } else {
                            $upRes = $pdo->prepare("
                                UPDATE reservations
                                SET reservation_date = :r_date,
                                    start_time = :start_t,
                                    end_time = :end_t,
                                    status = 'oczekująca'
                                WHERE id = :id AND user_id = :u_id
                            ");
                            $upRes->execute([
                                'r_date'  => $newDate,
                                'start_t' => $startTimeSql,
                                'end_t'   => $endTimeSql,
                                'id'      => $reservationId,
                                'u_id'    => $userId
                            ]);
                            $pdo->commit();
                            $successMsg = 'Termin rezerwacji #' . $reservationId . ' został zmieniony na ' . $newDate . ' (' . substr($startTimeSql, 0, 5) . ' – ' . substr($endTimeSql, 0, 5) . ').';
                        }
                    } catch (\PDOException $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $errorMsg = 'Nie udało się przełożyć wizyty.';
                    }
                }
            }
        }
    } elseif (isset($_POST['update_profile'])) {
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

$userStmt = $pdo->prepare('SELECT name, surname, email, phone, role, created_at FROM users WHERE id = :id');
$userStmt->execute(['id' => $userId]);
$user = $userStmt->fetch();

$activeServices = $pdo->query('
    SELECT s.id, s.name, s.description, s.duration, s.price, c.name AS category_name
    FROM services s
    JOIN service_categories c ON s.category_id = c.id
    WHERE s.active = 1
    ORDER BY c.name ASC, s.name ASC
')->fetchAll();

$preselectedServiceId = (int)($_POST['service_id'] ?? $_GET['service_id'] ?? 0);
$preselectedEmployeeId = (int)($_POST['employee_id'] ?? $_GET['employee_id'] ?? 0);
$preselectedDate = trim($_POST['reservation_date'] ?? $_GET['date'] ?? '');
$preselectedTime = trim($_POST['start_time'] ?? '');

$myReservationsStmt = $pdo->prepare('
    SELECT r.*,
           s.name AS service_name, s.duration AS service_duration, s.price AS service_price,
           c.name AS category_name,
           u_emp.name AS emp_name, u_emp.surname AS emp_surname
    FROM reservations r
    JOIN services s ON r.service_id = s.id
    JOIN service_categories c ON s.category_id = c.id
    JOIN employees e ON r.employee_id = e.id
    JOIN users u_emp ON e.user_id = u_emp.id
    WHERE r.user_id = :u_id
    ORDER BY r.reservation_date DESC, r.start_time DESC
');
$myReservationsStmt->execute(['u_id' => $userId]);
$allMyReservations = $myReservationsStmt->fetchAll();

$todayDate = date('Y-m-d');
$nowTimeStr = date('H:i:s');
$upcomingReservations = [];
$pastOrCancelledReservations = [];

foreach ($allMyReservations as $res) {
    $isFuture = ($res['reservation_date'] > $todayDate) || ($res['reservation_date'] === $todayDate && $res['start_time'] > $nowTimeStr);
    if ($isFuture && in_array($res['status'], ['oczekująca', 'potwierdzona'], true)) {
        $upcomingReservations[] = $res;
    } else {
        $pastOrCancelledReservations[] = $res;
    }
}

usort($upcomingReservations, function ($a, $b) {
    $cmp = strcmp($a['reservation_date'], $b['reservation_date']);
    return $cmp !== 0 ? $cmp : strcmp($a['start_time'], $b['start_time']);
});

function getStatusBadgeClass(string $status): string
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
    <title>Panel Pacjenta - Gabinet Fizjoterapii</title>
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
                <span class="user-greeting">Pacjent: <strong><?php echo htmlspecialchars($user['name'] . ' ' . $user['surname']); ?></strong></span>
                <a href="index.php#oferta" class="btn btn-outline btn-sm">Oferta usług</a>
                <a href="#rezerwacja" class="btn btn-primary btn-sm">Nowa rezerwacja</a>
                <a href="#moje-wizyty" class="btn btn-outline btn-sm">Moje wizyty (<?php echo count($upcomingReservations); ?>)</a>
                <a href="#moje-konto" class="btn btn-outline btn-sm">Moje konto</a>
                <a href="logout.php" class="btn btn-danger btn-sm">Wyloguj się</a>
            </nav>
        </div>
    </header>

    <main class="panel-container">
        <div class="panel-welcome">
            <div class="badge-role badge-client">Strefa Pacjenta</div>
            <h1>Panel Pacjenta i Rezerwacja Wizyt</h1>
            <p>Witaj, <?php echo htmlspecialchars($user['name'] . ' ' . $user['surname']); ?>! Umów nowy zabieg, zarządzaj nadchodzącymi wizytami lub edytuj dane konta.</p>
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

        <section id="rezerwacja" class="card mb-3">
            <div class="card-header">
                <h2>Nowa Rezerwacja Zabiegu</h2>
                <span class="text-muted" style="font-size:0.85rem;">Kolejność: usługa &rarr; pracownik &rarr; data &rarr; godzina &rarr; potwierdzenie</span>
            </div>
            <div class="card-body">
                <div class="wizard-stepper">
                    <div class="wizard-step-indicator active" id="ind-step-1">1. Wybór usługi</div>
                    <div class="wizard-step-indicator" id="ind-step-2">2. Fizjoterapeuta</div>
                    <div class="wizard-step-indicator" id="ind-step-3">3. Data wizyty</div>
                    <div class="wizard-step-indicator" id="ind-step-4">4. Dostępna godzina</div>
                    <div class="wizard-step-indicator" id="ind-step-5">5. Potwierdzenie</div>
                </div>

                <form method="POST" action="client_panel.php" id="reservation-form"
                      data-preselected-service="<?php echo (int)$preselectedServiceId; ?>"
                      data-preselected-employee="<?php echo (int)$preselectedEmployeeId; ?>"
                      data-preselected-date="<?php echo htmlspecialchars($preselectedDate); ?>"
                      data-preselected-time="<?php echo htmlspecialchars($preselectedTime); ?>">
                    <input type="hidden" name="action" value="create_reservation">
                    <input type="hidden" name="service_id" id="input-service-id" value="<?php echo (int)$preselectedServiceId; ?>">
                    <input type="hidden" name="employee_id" id="input-employee-id" value="<?php echo (int)$preselectedEmployeeId; ?>">
                    <input type="hidden" name="start_time" id="input-start-time" value="<?php echo htmlspecialchars($preselectedTime); ?>">

                    <div class="wizard-section" id="step-1-section">
                        <div class="wizard-section-title">
                            <span class="step-badge">1</span>
                            <span>Wybierz usługę / zabieg fizjoterapeutyczny</span>
                        </div>
                        <?php if (empty($activeServices)): ?>
                            <p class="text-muted">Brak aktywnych usług w ofercie gabinetu.</p>
                        <?php else: ?>
                            <div class="choice-grid" id="services-choice-grid">
                                <?php foreach ($activeServices as $srv): ?>
                                    <?php $isSelectedSrv = ((int)$srv['id'] === $preselectedServiceId); ?>
                                    <div class="choice-card service-choice <?php echo $isSelectedSrv ? 'selected' : ''; ?>"
                                         data-id="<?php echo (int)$srv['id']; ?>"
                                         data-name="<?php echo htmlspecialchars($srv['name']); ?>"
                                         data-duration="<?php echo (int)$srv['duration']; ?>"
                                         data-price="<?php echo number_format((float)$srv['price'], 2, ',', ' '); ?>">
                                        <div class="choice-title"><?php echo htmlspecialchars($srv['name']); ?></div>
                                        <div class="choice-sub"><?php echo htmlspecialchars($srv['category_name']); ?></div>
                                        <div class="choice-sub mt-2">
                                            ⏱ <strong><?php echo (int)$srv['duration']; ?> min</strong> &nbsp;|&nbsp;
                                            💳 <strong><?php echo number_format((float)$srv['price'], 2, ',', ' '); ?> zł</strong>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="wizard-section disabled-step" id="step-2-section">
                        <div class="wizard-section-title">
                            <span class="step-badge">2</span>
                            <span>Wybierz fizjoterapeutę wykonującego zabieg</span>
                        </div>
                        <div id="employees-container">
                            <p class="text-muted">Najpierw wybierz usługę w kroku 1, aby zobaczyć dostępnych specjalistów.</p>
                        </div>
                    </div>

                    <div class="wizard-section disabled-step" id="step-3-section">
                        <div class="wizard-section-title">
                            <span class="step-badge">3</span>
                            <span>Wybierz datę wizyty</span>
                        </div>
                        <div id="date-selection-area">
                            <div class="form-row" style="align-items:center;">
                                <div class="form-group" style="max-width:280px; margin-bottom:0;">
                                    <label for="reservation_date">Data wizyty *</label>
                                    <input type="date" id="reservation_date" name="reservation_date" class="form-control"
                                           min="<?php echo date('Y-m-d'); ?>"
                                           value="<?php echo htmlspecialchars($preselectedDate); ?>" disabled required>
                                </div>
                                <div style="flex:1;">
                                    <label>Najbliższe dni dyżurów wybranego fizjoterapeuty (kliknij, aby wybrać):</label>
                                    <div id="upcoming-dates-chips" class="date-chips">
                                        <span class="text-muted" style="font-size:0.85rem;">Wybierz fizjoterapeutę w kroku 2.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wizard-section disabled-step" id="step-4-section">
                        <div class="wizard-section-title">
                            <span class="step-badge">4</span>
                            <span>Wybierz dostępną godzinę</span>
                        </div>
                        <div id="slots-info-bar" class="text-muted mb-2" style="font-size:0.88rem;">
                            Wybierz fizjoterapeutę oraz datę, aby wyznaczyć dostępne godziny.
                        </div>
                        <div id="slots-container"></div>
                    </div>

                    <div class="wizard-section disabled-step" id="step-5-section">
                        <div class="wizard-section-title">
                            <span class="step-badge">5</span>
                            <span>Potwierdzenie rezerwacji</span>
                        </div>

                        <div class="summary-box">
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <span>Wybrany zabieg</span>
                                    <strong id="sum-service">—</strong>
                                </div>
                                <div class="summary-item">
                                    <span>Fizjoterapeuta</span>
                                    <strong id="sum-employee">—</strong>
                                </div>
                                <div class="summary-item">
                                    <span>Data wizyty</span>
                                    <strong id="sum-date">—</strong>
                                </div>
                                <div class="summary-item">
                                    <span>Godzina (przedział)</span>
                                    <strong id="sum-time">—</strong>
                                </div>
                                <div class="summary-item">
                                    <span>Czas trwania i koszt</span>
                                    <strong id="sum-price">—</strong>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="comment">Dodatkowe informacje dla fizjoterapeuty (opcjonalnie)</label>
                            <textarea id="comment" name="comment" class="form-control" rows="2" maxlength="500"
                                      placeholder="np. ból odcinka lędźwiowego, stan po urazie kolana..."></textarea>
                        </div>

                        <button type="submit" id="confirm-reservation-btn" class="btn btn-primary" disabled>
                            Potwierdzam i zapisuję rezerwację
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section id="moje-wizyty" class="card mt-4">
            <div class="card-header">
                <h2>Nadchodzące Wizyty (<?php echo count($upcomingReservations); ?>)</h2>
                <span class="text-muted" style="font-size:0.85rem;">Możesz anulować lub przełożyć własną przyszłą rezerwację</span>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usługa / Zabieg</th>
                                <th>Fizjoterapeuta</th>
                                <th>Data</th>
                                <th>Godzina</th>
                                <th>Cena</th>
                                <th>Status</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($upcomingReservations)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">Nie masz obecnie żadnych zaplanowanych przyszłych wizyt.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($upcomingReservations as $res): ?>
                                    <tr>
                                        <td>#<?php echo (int)$res['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($res['service_name']); ?></strong><br>
                                            <span class="text-muted" style="font-size:0.8rem;">
                                                <?php echo htmlspecialchars($res['category_name']); ?> (<?php echo (int)$res['service_duration']; ?> min)
                                            </span>
                                            <?php if (!empty($res['comment'])): ?>
                                                <div class="text-muted" style="font-size:0.8rem; margin-top:0.2rem;">
                                                    💬 <?php echo htmlspecialchars($res['comment']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($res['emp_name'] . ' ' . $res['emp_surname']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($res['reservation_date']); ?></strong></td>
                                        <td><?php echo htmlspecialchars(substr($res['start_time'], 0, 5) . ' – ' . substr($res['end_time'], 0, 5)); ?></td>
                                        <td><strong><?php echo number_format((float)$res['service_price'], 2, ',', ' '); ?> zł</strong></td>
                                        <td>
                                            <span class="status-badge <?php echo getStatusBadgeClass($res['status']); ?>">
                                                <?php echo htmlspecialchars($res['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn btn-outline btn-sm toggle-reschedule-btn"
                                                        data-target="reschedule-box-<?php echo (int)$res['id']; ?>">
                                                    Przełóż
                                                </button>
                                                <form method="POST" action="client_panel.php#moje-wizyty"
                                                      onsubmit="return confirm('Czy na pewno chcesz anulować tę wizytę?');">
                                                    <input type="hidden" name="action" value="cancel_reservation">
                                                    <input type="hidden" name="reservation_id" value="<?php echo (int)$res['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Anuluj wizytę</button>
                                                </form>
                                            </div>

                                            <div id="reschedule-box-<?php echo (int)$res['id']; ?>" class="reschedule-box" style="display:none;">
                                                <form method="POST" action="client_panel.php#moje-wizyty">
                                                    <input type="hidden" name="action" value="reschedule_reservation">
                                                    <input type="hidden" name="reservation_id" value="<?php echo (int)$res['id']; ?>">
                                                    <div style="font-size:0.82rem; font-weight:600; margin-bottom:0.4rem;">
                                                        Zmień termin wizyty #<?php echo (int)$res['id']; ?>:
                                                    </div>
                                                    <div style="display:flex; gap:0.4rem; flex-wrap:wrap; align-items:center;">
                                                        <input type="date" name="new_date" class="form-control"
                                                               style="width:auto; padding:0.3rem 0.5rem; font-size:0.82rem;"
                                                               min="<?php echo date('Y-m-d'); ?>"
                                                               value="<?php echo htmlspecialchars($res['reservation_date']); ?>" required>
                                                        <input type="time" name="new_time" class="form-control"
                                                               style="width:auto; padding:0.3rem 0.5rem; font-size:0.82rem;"
                                                               step="900"
                                                               value="<?php echo htmlspecialchars(substr($res['start_time'], 0, 5)); ?>" required>
                                                        <button type="submit" class="btn btn-primary btn-sm">Zapisz nowy termin</button>
                                                    </div>
                                                </form>
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

        <section class="card mt-4">
            <div class="card-header">
                <h2>Historia oraz Anulowane Wizyty (<?php echo count($pastOrCancelledReservations); ?>)</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usługa / Zabieg</th>
                                <th>Fizjoterapeuta</th>
                                <th>Data</th>
                                <th>Godzina</th>
                                <th>Cena</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pastOrCancelledReservations)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Brak zakończonych lub anulowanych rezerwacji.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pastOrCancelledReservations as $res): ?>
                                    <tr>
                                        <td>#<?php echo (int)$res['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($res['service_name']); ?></strong><br>
                                            <span class="text-muted" style="font-size:0.8rem;"><?php echo htmlspecialchars($res['category_name']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($res['emp_name'] . ' ' . $res['emp_surname']); ?></td>
                                        <td><?php echo htmlspecialchars($res['reservation_date']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($res['start_time'], 0, 5) . ' – ' . substr($res['end_time'], 0, 5)); ?></td>
                                        <td><?php echo number_format((float)$res['service_price'], 2, ',', ' '); ?> zł</td>
                                        <td>
                                            <span class="status-badge <?php echo getStatusBadgeClass($res['status']); ?>">
                                                <?php echo htmlspecialchars($res['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="moje-konto" class="panel-grid mt-4">
            <div class="card">
                <div class="card-header">
                    <h2>Moje Konto - Edycja Profilu</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="client_panel.php#moje-konto">
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

            <div class="card">
                <div class="card-header">
                    <h2>Bezpieczeństwo - Zmiana Hasła</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="client_panel.php#moje-konto">
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
        </section>
    </main>

    <script src="../js/script.js"></script>
</body>
</html>
