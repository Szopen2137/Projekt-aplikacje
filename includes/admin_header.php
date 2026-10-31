<header class="app-header">
    <div class="header-container">
        <div class="header-brand">
            <span class="brand-icon">⚕</span>
            <span>Fizjo<strong>Med</strong> – Panel Admina</span>
        </div>
        <nav class="header-nav">
            <a href="admin_panel.php" class="btn btn-outline btn-sm">Dashboard</a>
            <a href="admin_categories.php" class="btn btn-outline btn-sm">Kategorie</a>
            <a href="admin_services.php" class="btn btn-outline btn-sm">Usługi</a>
            <a href="admin_employees.php" class="btn btn-outline btn-sm">Pracownicy</a>
            <a href="admin_users.php" class="btn btn-outline btn-sm">Użytkownicy</a>
            <a href="admin_reservations.php" class="btn btn-outline btn-sm">Rezerwacje</a>
            <a href="index.php" class="btn btn-secondary btn-sm">Strona główna</a>
            <a href="logout.php" class="btn btn-danger btn-sm">Wyloguj (<?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?>)</a>
        </nav>
    </div>
</header>
