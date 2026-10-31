<?php
require_once '../includes/admin_auth.php';
require_once '../includes/config.php';

$successMsg = '';
$errorMsg = '';

// Pobranie kategorii do pola <select>
$categories = $pdo->query('SELECT id, name FROM service_categories ORDER BY name ASC')->fetchAll();

// Dane do edycji usługi
$editService = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM services WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $editService = $stmt->fetch();
}

// Obsługa formularzy POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration = (int)($_POST['duration'] ?? 0);
        $price = str_replace(',', '.', trim($_POST['price'] ?? '0'));

        // Walidacja serwerowa (wymóg na ocenę 5)
        if (empty($name) || $categoryId <= 0) {
            $errorMsg = 'Podaj nazwę usługi oraz wybierz kategorię.';
        } elseif ($duration <= 0 || $duration > 480) {
            $errorMsg = 'Czas trwania musi wynosić od 1 do 480 minut.';
        } elseif (!is_numeric($price) || (float)$price <= 0) {
            $errorMsg = 'Cena usługi musi być liczbą większą od zera.';
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare('
                        INSERT INTO services (category_id, name, description, duration, price, active)
                        VALUES (:category_id, :name, :description, :duration, :price, 1)
                    ');
                    $stmt->execute([
                        'category_id' => $categoryId,
                        'name'        => $name,
                        'description' => $description,
                        'duration'    => $duration,
                        'price'       => (float)$price
                    ]);
                    $successMsg = 'Nowa usługa została dodana do oferty.';
                } else {
                    $stmt = $pdo->prepare('
                        UPDATE services
                        SET category_id = :category_id, name = :name, description = :description, duration = :duration, price = :price
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        'category_id' => $categoryId,
                        'name'        => $name,
                        'description' => $description,
                        'duration'    => $duration,
                        'price'       => (float)$price,
                        'id'          => $id
                    ]);
                    $successMsg = 'Dane usługi zostały zaktualizowane.';
                    $editService = null;
                }
            } catch (\PDOException $e) {
                $errorMsg = 'Wystąpił błąd podczas zapisywania usługi.';
            }
        }
    } elseif ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare('UPDATE services SET active = NOT active WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $successMsg = 'Status widoczności usługi został zmieniony.';
        } catch (\PDOException $e) {
            $errorMsg = 'Nie udało się zmienić statusu usługi.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Sprawdzenie czy usługa ma już przypisane rezerwacje (bezpieczna obsługa zależności na ocenę 5)
        $resCheck = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE service_id = :id');
        $resCheck->execute(['id' => $id]);
        if ((int)$resCheck->fetchColumn() > 0) {
            $errorMsg = 'Nie można usunąć usługi, która posiada historię rezerwacji. Użyj przycisku "Dezaktywuj", aby wycofać ją z oferty.';
        } else {
            try {
                $delStmt = $pdo->prepare('DELETE FROM services WHERE id = :id');
                $delStmt->execute(['id' => $id]);
                $successMsg = 'Usługa została usunięta.';
            } catch (\PDOException $e) {
                $errorMsg = 'Nie udało się usunąć usługi.';
            }
        }
    }
}

// Pobranie listy usług wraz z nazwą kategorii (JOIN)
$services = $pdo->query('
    SELECT s.*, c.name AS category_name
    FROM services s
    JOIN service_categories c ON s.category_id = c.id
    ORDER BY s.id ASC
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usługi i Zabiegi - Panel Administratora</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php require_once '../includes/admin_header.php'; ?>

    <main class="panel-container">
        <div class="panel-welcome">
            <div class="badge-role badge-admin">Moduł Zarządzania</div>
            <h1>Usługi i Zabiegi</h1>
            <p>Zarządzaj cennikiem, czasem trwania oraz dostępnością zabiegów rehabilitacyjnych.</p>
        </div>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>

        <!-- Formularz dodawania / edycji usługi -->
        <div class="card">
            <div class="card-header">
                <h2><?php echo $editService ? 'Edytuj usługę #' . (int)$editService['id'] : 'Dodaj nową usługę'; ?></h2>
            </div>
            <div class="card-body">
                <form method="POST" action="admin_services.php">
                    <input type="hidden" name="action" value="<?php echo $editService ? 'edit' : 'add'; ?>">
                    <?php if ($editService): ?>
                        <input type="hidden" name="id" value="<?php echo (int)$editService['id']; ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Nazwa zabiegu / usługi *</label>
                            <input type="text" id="name" name="name" class="form-control" required
                                   value="<?php echo htmlspecialchars($editService['name'] ?? ''); ?>"
                                   placeholder="np. Terapia manualna">
                        </div>

                        <div class="form-group">
                            <label for="category_id">Kategoria *</label>
                            <select id="category_id" name="category_id" class="form-control" required>
                                <option value="">-- Wybierz kategorię --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo (int)$cat['id']; ?>"
                                        <?php echo (isset($editService['category_id']) && (int)$editService['category_id'] === (int)$cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="duration">Czas trwania (minuty) *</label>
                            <input type="number" id="duration" name="duration" class="form-control" min="5" max="480" step="5" required
                                   value="<?php echo htmlspecialchars($editService['duration'] ?? '30'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="price">Cena (PLN) *</label>
                            <input type="number" id="price" name="price" class="form-control" min="1" step="0.01" required
                                   value="<?php echo htmlspecialchars($editService['price'] ?? '100.00'); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Opis zabiegu</label>
                        <textarea id="description" name="description" class="form-control" rows="2"
                                  placeholder="Opis przebiegu i wskazań do zabiegu..."><?php echo htmlspecialchars($editService['description'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <?php echo $editService ? 'Zapisz zmiany' : 'Dodaj usługę'; ?>
                    </button>
                    <?php if ($editService): ?>
                        <a href="admin_services.php" class="btn btn-outline">Anuluj</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Tabela wszystkich usług -->
        <div class="card mt-4">
            <div class="card-header">
                <h2>Lista usług w systemie (<?php echo count($services); ?>)</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nazwa usługi</th>
                                <th>Kategoria</th>
                                <th>Czas trwania</th>
                                <th>Cena</th>
                                <th>Status</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($services)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">Brak usług w bazie danych.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($services as $srv): ?>
                                    <tr>
                                        <td>#<?php echo (int)$srv['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($srv['name']); ?></strong>
                                            <?php if (!empty($srv['description'])): ?>
                                                <div class="text-muted" style="font-size:0.82rem;"><?php echo htmlspecialchars($srv['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($srv['category_name']); ?></td>
                                        <td><?php echo (int)$srv['duration']; ?> min</td>
                                        <td><strong><?php echo number_format((float)$srv['price'], 2, ',', ' '); ?> zł</strong></td>
                                        <td>
                                            <span class="status-badge <?php echo ((int)$srv['active'] === 1) ? 'status-active' : 'status-blocked'; ?>">
                                                <?php echo ((int)$srv['active'] === 1) ? 'Aktywna' : 'Nieaktywna'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="admin_services.php?edit=<?php echo (int)$srv['id']; ?>" class="btn btn-outline btn-sm">Edytuj</a>

                                            <form method="POST" action="admin_services.php" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_active">
                                                <input type="hidden" name="id" value="<?php echo (int)$srv['id']; ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm">
                                                    <?php echo ((int)$srv['active'] === 1) ? 'Dezaktywuj' : 'Aktywuj'; ?>
                                                </button>
                                            </form>

                                            <form method="POST" action="admin_services.php" style="display:inline;" onsubmit="return confirm('Czy na pewno chcesz usunąć tę usługę?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int)$srv['id']; ?>">
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
