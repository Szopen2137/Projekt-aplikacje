<?php
require_once '../includes/admin_auth.php';
require_once '../includes/config.php';

$successMsg = '';
$errorMsg = '';

// Obsługa blokowania / odblokowywania konta oraz zmiany roli
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetUserId = (int)($_POST['user_id'] ?? 0);

    if ($targetUserId === (int)$_SESSION['user_id']) {
        $errorMsg = 'Nie możesz zablokować ani zmienić roli własnego konta administratora.';
    } elseif ($action === 'toggle_active') {
        try {
            $stmt = $pdo->prepare('UPDATE users SET active = NOT active WHERE id = :id');
            $stmt->execute(['id' => $targetUserId]);
            $successMsg = 'Status konta użytkownika został zmieniony.';
        } catch (\PDOException $e) {
            $errorMsg = 'Wystąpił błąd podczas zmiany statusu konta.';
        }
    } elseif ($action === 'change_role') {
        $newRole = $_POST['role'] ?? 'client';
        if (in_array($newRole, ['client', 'employee', 'admin'], true)) {
            try {
                $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
                $stmt->execute(['role' => $newRole, 'id' => $targetUserId]);
                $successMsg = 'Rola użytkownika została zaktualizowana.';
            } catch (\PDOException $e) {
                $errorMsg = 'Nie udało się zmienić roli użytkownika.';
            }
        }
    }
}

// Wyszukiwarka użytkowników (wymóg na ocenę 5)
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare('
        SELECT id, name, surname, email, phone, role, active, created_at
        FROM users
        WHERE name LIKE :q OR surname LIKE :q OR email LIKE :q
        ORDER BY id DESC
    ');
    $stmt->execute(['q' => '%' . $search . '%']);
    $users = $stmt->fetchAll();
} else {
    $users = $pdo->query('SELECT id, name, surname, email, phone, role, active, created_at FROM users ORDER BY id DESC')->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie Użytkownikami - Panel Administratora</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php require_once '../includes/admin_header.php'; ?>

    <main class="panel-container">
        <div class="panel-welcome">
            <div class="badge-role badge-admin">Moduł Zarządzania</div>
            <h1>Użytkownicy Systemu</h1>
            <p>Przeglądaj, wyszukuj konta, zmieniaj uprawnienia oraz blokuj lub odblokowuj dostęp.</p>
        </div>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>

        <!-- Wyszukiwarka użytkowników (wymóg na ocenę 5) -->
        <div class="card">
            <div class="card-body">
                <form method="GET" action="admin_users.php" style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:flex-end;">
                    <div style="flex:1; min-width:240px;">
                        <label for="search">Wyszukaj użytkownika (imię, nazwisko lub e-mail):</label>
                        <input type="text" id="search" name="search" class="form-control"
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Wpisz szukane nazwisko lub adres e-mail...">
                    </div>
                    <button type="submit" class="btn btn-primary">Szukaj</button>
                    <?php if ($search !== ''): ?>
                        <a href="admin_users.php" class="btn btn-outline">Wyczyść</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Tabela użytkowników -->
        <div class="card mt-4">
            <div class="card-header">
                <h2>Lista użytkowników (<?php echo count($users); ?>)</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Imię i Nazwisko</th>
                                <th>E-mail</th>
                                <th>Telefon</th>
                                <th>Rola</th>
                                <th>Status konta</th>
                                <th>Data rejestracji</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">Nie znaleziono użytkowników spełniających kryteria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>#<?php echo (int)$u['id']; ?></td>
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
                                        <td>
                                            <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                                <form method="POST" action="admin_users.php" style="display:inline;">
                                                    <input type="hidden" name="action" value="toggle_active">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                    <button type="submit" class="btn <?php echo ((int)$u['active'] === 1) ? 'btn-danger' : 'btn-primary'; ?> btn-sm">
                                                        <?php echo ((int)$u['active'] === 1) ? 'Zablokuj' : 'Odblokuj'; ?>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:0.82rem;">Twoje konto</span>
                                            <?php endif; ?>
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
