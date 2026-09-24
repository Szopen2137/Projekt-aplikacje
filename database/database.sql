-- Tworzenie tabel
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    surname VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role VARCHAR(50) NOT NULL DEFAULT 'client',
    active BOOLEAN NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE service_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    duration INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE CASCADE
);

CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    description TEXT,
    active BOOLEAN NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE employee_services (
    employee_id INT NOT NULL,
    service_id INT NOT NULL,
    PRIMARY KEY (employee_id, service_id),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

CREATE TABLE employee_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    day_of_week DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    employee_id INT NOT NULL,
    service_id INT NOT NULL,
    reservation_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'oczekująca',
    comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- Hasło dla wszystkich kont to: haslo123

INSERT INTO users (name, surname, email, password, phone, role) VALUES
('Jan', 'Kowalski', 'admin@gabinet.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '111222333', 'admin'),
('Anna', 'Nowak', 'anna@gabinet.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '444555666', 'employee'),
('Piotr', 'Zieliński', 'pacjent@test.pl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '777888999', 'client');

INSERT INTO service_categories (name, description) VALUES
('Masaże lecznicze', 'Zabiegi manualne poprawiające ukrwienie i rozluźniające mięśnie'),
('Fizykoterapia', 'Zabiegi wykorzystujące zjawiska fizyczne');

INSERT INTO services (category_id, name, description, duration, price) VALUES
(1, 'Masaż kręgosłupa', 'Klasyczny masaż leczniczy kręgosłupa', 30, 80.00),
(1, 'Terapia manualna', 'Kompleksowa terapia manualna z wywiadem', 60, 150.00),
(2, 'Prądy TENS', 'Elektroterapia przeciwbólowa', 15, 30.00);

INSERT INTO employees (user_id, description) VALUES
(2, 'Magister fizjoterapii, specjalistka od terapii manualnej i bólów kręgosłupa');

INSERT INTO employee_services (employee_id, service_id) VALUES
(1, 1), (1, 2), (1, 3);

INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time) VALUES
(1, '2026-10-12', '08:00', '16:00'), -- Poniedziałek zapisany jako konkretna data
(1, '2026-10-14', '10:00', '18:00'); -- Środa zapisana jako konkretna data

INSERT INTO reservations (user_id, employee_id, service_id, reservation_date, start_time, end_time, status) VALUES
(3, 1, 2, '2026-10-15', '10:00', '11:00', 'potwierdzona'),
(3, 1, 3, '2026-10-20', '14:00', '14:15', 'oczekująca');