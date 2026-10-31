<?php
require_once '../includes/admin_auth.php';
require_once '../includes/config.php';

$successMsg = '';
$errorMsg = '';

// Dane do formularza edycji
$editCategory = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM service_categories WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $editCategory = $stmt->fetch();
}

// Obsługa akcji POST (Dodawanie, Edycja, Usuwanie)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            $errorMsg = 'Nazwa kategorii jest wymagana.';
        } elseif (mb_strlen($name) > 100) {
            $errorMsg = 'Nazwa kategorii nie może przekraczać 100 znaków.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO service_categories (name, description) VALUES (:name, :description)');
                $stmt->execute(['name' => $name, 'description' => $description]);
                $successMsg = 'Nowa kategoria została dodana.';
            } catch (\PDOException $e) {
                $errorMsg = 'Wystąpił błąd podczas dodawania kategorii.';
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            $errorMsg = 'Nazwa kategorii jest wymagana.';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE service_categories SET name = :name, description = :description WHERE id = :id');
                $stmt->execute(['name' => $name, 'description' => $description, 'id' => $id]);
                $successMsg = 'Kategoria została zaktualizowana.';
                $editCategory = null;
            } catch (\PDOException $e) {
                $errorMsg = 'Wystąpił błąd podczas edycji kategorii.';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        // Bezpieczna obsługa zależności (wymóg na ocenę 5): sprawdzenie czy do kategorii są przypisane usługi
        $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM services WHERE category_id = :id');
        $checkStmt->execute(['id' => $id]);
        $servicesInCat = (int)$checkStmt->fetchColumn();

        if ($servicesInCat > 0) {
            $errorMsg = "Nie można usunąć tej kategorii, ponieważ są do niej przypisane usługi ($servicesInCat). Najpierw przenieś lub usuń te usługi.";
        } else {
            try {
                $delStmt = $pdo->prepare('DELETE FROM service_categories WHERE id = :id');
                $delStmt->execute(['id' => $id]);
                $successMsg = 'Kategoria została pomyślnie usunięta.';
            } catch (\PDOException $e) {
                $errorMsg = 'Nie udało się usunąć kategorii.';
            }
        }
    }
}

// Pobranie wszystkich kategorii wraz z liczbą przypisanych usług
$categoriesStmt = $pdo->query('
    SELECT c.*, COUNT(s.id) AS services_count
    FROM service_categories c
    LEFT JOIN services s ON s.category_id = c.id
    GROUP BY c.id
    ORDER BY c.id ASC
');
$categories = $categoriesStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategorie Usług - Panel Administratora</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php require_once '../includes/admin_header.php'; ?>

    <main class="panel-container">
        <div class="panel-welcome">
            <div class="badge-role badge-admin">Moduł Zarządzania</div>
            <h1>Kategorie Usług</h1>
            <p>Dodawaj, edytuj oraz zarządzaj kategoriami zabiegów fizjoterapeutycznych.</p>
        </div>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>

        <div class="panel-grid">
            <!-- Formularz dodawania / edycji kategorii -->
            <div class="card">
                <div class="card-header">
                    <h2><?php echo $editCategory ? 'Edytuj kategorię #' . (int)$editCategory['id'] : 'Dodaj nową kategorię'; ?></h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="admin_categories.php">
                        <input type="hidden" name="action" value="<?php echo $editCategory ? 'edit' : 'add'; ?>">
                        <?php if ($editCategory): ?>
                            <input type="hidden" name="id" value="<?php echo (int)$editCategory['id']; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="name">Nazwa kategorii *</label>
                            <input type="text" id="name" name="name" class="form-control" required
                                   value="<?php echo htmlspecialchars($editCategory['name'] ?? ''); ?>"
                                   placeholder="np. Masaże lecznicze">
                        </div>

                        <div class="form-group">
                            <label for="description">Opis kategorii</label>
                            <textarea id="description" name="description" class="form-control" rows="4"
                                      placeholder="Krótki opis zabiegów w tej kategorii..."><?php echo htmlspecialchars($editCategory['description'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <?php echo $editCategory ? 'Zapisz zmiany' : 'Dodaj kategorię'; ?>
                        </button>
                        <?php if ($editCategory): ?>
                            <a href="admin_categories.php" class="btn btn-outline">Anuluj</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Tabela kategorii -->
            <div class="card">
                <div class="card-header">
                    <h2>Lista kategorii (<?php echo count($categories); ?>)</h2>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nazwa</th>
                                    <th>Opis</th>
                                    <th>Usługi</th>
                                    <th>Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Brak zdefiniowanych kategorii.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td>#<?php echo (int)$cat['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($cat['description'] ?? '—'); ?></td>
                                            <td><?php echo (int)$cat['services_count']; ?></td>
                                            <td>
                                                <a href="admin_categories.php?edit=<?php echo (int)$cat['id']; ?>" class="btn btn-outline btn-sm">Edytuj</a>
                                                <form method="POST" action="admin_categories.php" style="display:inline;" onsubmit="return confirm('Czy na pewno chcesz usunąć tę kategorię?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
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
        </div>
    </main>
</body>
</html>
