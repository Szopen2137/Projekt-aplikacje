<?php
session_start();
require_once '../includes/config.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? null;
$userName = $_SESSION['name'] ?? null;

$panelUrl = 'login.php';
if ($isLoggedIn) {
    if ($userRole === 'admin') {
        $panelUrl = 'admin_panel.php';
    } elseif ($userRole === 'employee') {
        $panelUrl = 'employee_panel.php';
    } else {
        $panelUrl = 'client_panel.php';
    }
}

$selectedCategory = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$searchQuery = trim($_GET['q'] ?? '');

$categories = $pdo->query('SELECT id, name, description FROM service_categories ORDER BY name ASC')->fetchAll();

$sql = '
    SELECT s.id, s.category_id, s.name, s.description, s.duration, s.price, c.name AS category_name
    FROM services s
    JOIN service_categories c ON s.category_id = c.id
    WHERE s.active = 1
';
$params = [];

if ($selectedCategory > 0) {
    $sql .= ' AND s.category_id = :cat_id';
    $params['cat_id'] = $selectedCategory;
}

if ($searchQuery !== '') {
    $sql .= ' AND (s.name LIKE :q1 OR s.description LIKE :q2 OR c.name LIKE :q3)';
    $like = '%' . $searchQuery . '%';
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
}

$sql .= ' ORDER BY c.name ASC, s.name ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

$specialistsRaw = $pdo->query('
    SELECT es.service_id, u.name, u.surname
    FROM employee_services es
    JOIN employees e ON es.employee_id = e.id
    JOIN users u ON e.user_id = u.id
    WHERE e.active = 1 AND u.active = 1
    ORDER BY u.surname ASC
')->fetchAll();

$serviceSpecialists = [];
foreach ($specialistsRaw as $row) {
    $serviceSpecialists[(int)$row['service_id']][] = $row['name'] . ' ' . $row['surname'];
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
            <a href="index.php" class="header-brand">
                <span class="brand-icon">⚕</span>
                <span>Fizjo<strong>Med</strong></span>
            </a>
            <nav class="header-nav">
                <a href="#oferta" class="btn btn-outline btn-sm">Oferta zabiegów</a>
                <?php if ($isLoggedIn): ?>
                    <span class="user-greeting">Witaj, <strong><?php echo htmlspecialchars($userName); ?></strong>!</span>
                    <a href="<?php echo htmlspecialchars($panelUrl); ?>" class="btn btn-primary btn-sm">Mój Panel</a>
                    <a href="logout.php" class="btn btn-danger btn-sm">Wyloguj</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline btn-sm">Logowanie</a>
                    <a href="register.php" class="btn btn-primary btn-sm">Rejestracja</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="panel-container">
        <div class="hero-banner">
            <h1>Profesjonalna Rehabilitacja i Fizjoterapia</h1>
            <p>Sprawdź naszą ofertę zabiegów, wybierz fizjoterapeutę oraz dogodny termin wizyty online bez wychodzenia z domu.</p>
            <div class="hero-buttons">
                <?php if ($isLoggedIn && $userRole === 'client'): ?>
                    <a href="client_panel.php#rezerwacja" class="btn btn-primary">Umów nową wizytę &rarr;</a>
                    <a href="client_panel.php#moje-wizyty" class="btn btn-outline">Moje rezerwacje</a>
                <?php elseif ($isLoggedIn): ?>
                    <a href="<?php echo htmlspecialchars($panelUrl); ?>" class="btn btn-primary">Przejdź do panelu &rarr;</a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary">Załóż konto pacjenta</a>
                    <a href="login.php" class="btn btn-outline">Zaloguj się i zarezerwuj</a>
                <?php endif; ?>
            </div>
        </div>

        <section id="oferta" class="mt-4">
            <div class="panel-welcome">
                <h2>Oferta Usług i Zabiegów Fizjoterapeutycznych</h2>
                <p>Wszystkie aktywne zabiegi wykonywane w naszym gabinecie. Filtruj według kategorii lub wyszukaj interesujący Cię zabieg.</p>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="category-pills">
                        <a href="index.php#oferta" class="category-pill <?php echo ($selectedCategory === 0) ? 'active' : ''; ?>">
                            Wszystkie kategorie
                        </a>
                        <?php foreach ($categories as $cat): ?>
                            <a href="index.php?category_id=<?php echo (int)$cat['id']; ?>#oferta"
                               class="category-pill <?php echo ($selectedCategory === (int)$cat['id']) ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <form method="GET" action="index.php#oferta" style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                        <?php if ($selectedCategory > 0): ?>
                            <input type="hidden" name="category_id" value="<?php echo (int)$selectedCategory; ?>">
                        <?php endif; ?>
                        <div style="flex:1; min-width:220px;">
                            <input type="text" name="q" class="form-control"
                                   placeholder="Szukaj zabiegu po nazwie lub opisie..."
                                   value="<?php echo htmlspecialchars($searchQuery); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Szukaj</button>
                        <?php if ($selectedCategory > 0 || $searchQuery !== ''): ?>
                            <a href="index.php#oferta" class="btn btn-outline">Wyczyść filtry</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php if (empty($services)): ?>
                <div class="card">
                    <div class="card-body text-center">
                        <p class="text-muted">Brak aktywnych usług spełniających wybrane kryteria filtrowania.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="offer-grid">
                    <?php foreach ($services as $srv): ?>
                        <?php
                        $srvId = (int)$srv['id'];
                        $assignedList = $serviceSpecialists[$srvId] ?? [];
                        $isAvailable = !empty($assignedList);
                        ?>
                        <div class="offer-card">
                            <div>
                                <div style="display:flex; justify-content:space-between; align-items:center; gap:0.5rem; margin-bottom:0.4rem;">
                                    <span class="offer-category"><?php echo htmlspecialchars($srv['category_name']); ?></span>
                                    <span class="status-badge <?php echo $isAvailable ? 'status-active' : 'status-oczekujaca'; ?>">
                                        <?php echo $isAvailable ? 'Dostępna' : 'Brak specjalisty'; ?>
                                    </span>
                                </div>
                                <h3 class="offer-title"><?php echo htmlspecialchars($srv['name']); ?></h3>
                                <p class="offer-desc">
                                    <?php echo !empty($srv['description']) ? htmlspecialchars($srv['description']) : 'Profesjonalny zabieg fizjoterapeutyczny wykonywany w gabinecie.'; ?>
                                </p>
                            </div>

                            <div>
                                <div class="offer-meta">
                                    <span>⏱ Czas trwania: <strong><?php echo (int)$srv['duration']; ?> min</strong></span>
                                    <span class="offer-price"><?php echo number_format((float)$srv['price'], 2, ',', ' '); ?> zł</span>
                                </div>

                                <div class="offer-specialists">
                                    <?php if ($isAvailable): ?>
                                        👨‍⚕️ <strong>Specjaliści:</strong> <?php echo htmlspecialchars(implode(', ', $assignedList)); ?>
                                    <?php else: ?>
                                        ⚠️ Chwilowo brak przypisanego fizjoterapeuty
                                    <?php endif; ?>
                                </div>

                                <?php if ($isLoggedIn && $userRole === 'client'): ?>
                                    <?php if ($isAvailable): ?>
                                        <a href="client_panel.php?service_id=<?php echo $srvId; ?>#rezerwacja" class="btn btn-primary btn-block">
                                            Wybierz i zarezerwuj termin &rarr;
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary btn-block" disabled>Chwilowo niedostępne</button>
                                    <?php endif; ?>
                                <?php elseif (!$isLoggedIn): ?>
                                    <a href="login.php" class="btn btn-outline btn-block">
                                        Zaloguj się, aby zarezerwować
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars($panelUrl); ?>" class="btn btn-outline btn-block">
                                        Przejdź do swojego panelu
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
