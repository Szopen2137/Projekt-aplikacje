# System Rezerwacji Usług – Gabinet Fizjoterapii (FizjoMed)

🌐 **Wersja online (live):** [https://fizjo.dziadu.dev/](https://fizjo.dziadu.dev/)

## Autorzy projektu
- **Alan Ossoliński** – [@rudyk0607](https://github.com/rudyk0607)
- **Szymon Trojanek** – [@Szopen2137](https://github.com/Szopen2137)

---

## Opis systemu
Kompletna aplikacja internetowa wspomagająca obsługę gabinetu fizjoterapii oraz umożliwiająca pacjentom rezerwację terminów zabiegów online. System realizuje podział na trzy niezależne role użytkowników:
- **Klient (Pacjent)** – przegląda ofertę zabiegów, wybiera fizjoterapeutę oraz wolny termin z graficznego kalendarza, składa rezerwacje, przekłada lub anuluje nadchodzące wizyty oraz zarządza swoim profilem.
- **Pracownik (Fizjoterapeuta)** – obsługuje własny harmonogram wizyt (z podziałem na dzisiejsze, przyszłe oraz historię), przegląda szczegóły pacjentów, zmienia statusy realizowanych zabiegów oraz zarządza własnymi godzinami dyżurów.
- **Administrator** – zarządza całą placówką: kategoriami, cennikiem i czasem trwania usług, personelem, przypisaniem usług w relacji N:M, grafikiem dostępności, kontami użytkowników oraz wszystkimi rezerwacjami.

---

## Zastosowane technologie
- **Backend:** PHP 8+ (architektura oparta na PDO i zapytaniach przygotowanych *Prepared Statements*)
- **Baza danych:** MySQL / MariaDB (znormalizowana relacyjna baza danych, kodowanie `utf8mb4`)
- **Frontend:** HTML5, CSS3 (responsywny interfejs własny), JavaScript (ES6+, Fetch API / AJAX)
- **Kontrola wersji i wdrożenie:** Git, GitHub, Docker / serwer produkcyjny

---

## Zrealizowany zakres projektu

### 1. Baza danych i bezpieczeństwo (Etap 1 i 2)
- **Relacyjna baza danych:** 7 powiązanych tabel (`users`, `service_categories`, `services`, `employees`, `employee_services`, `employee_availability`, `reservations`) z kluczami obcymi oraz tabelą pośredniczącą realizującą relację `N:M` między fizjoterapeutami a usługami.
- **Ochrona przed SQL Injection:** Wszystkie zapytania z danymi wejściowymi korzystają z bindowania parametrów w PDO (`ATTR_EMULATE_PREPARES => false`).
- **Bezpieczeństwo haseł i sesji:** Haszowanie haseł za pomocą `password_hash()` (`bcrypt`), weryfikacja przez `password_verify()`, regeneracja ID sesji przy logowaniu (`session_regenerate_id()`) oraz weryfikacja ról i aktywności konta po stronie serwera.
- **Ochrona przed XSS i kontrola własności danych:** Filtrowanie wszystkich danych wyjściowych przez `htmlspecialchars()` oraz ścisła weryfikacja właściciela zasobu (`user_id` / `employee_id`) przed każdą operacją edycji, podglądu lub anulowania.

### 2. Panel Administratora (Etap 3)
- **Dashboard (`admin_panel.php`):** Statystyki na żywo z bazy danych – liczba klientów, pracowników, usług, wszystkich rezerwacji, wizyt na dziś oraz łączny przychód.
- **Kategorie usług (`admin_categories.php`):** Pełny CRUD kategorii z blokadą usunięcia kategorii posiadającej przypisane usługi.
- **Usługi i zabiegi (`admin_services.php`):** Zarządzanie cennikiem, czasem trwania, przypisaniem do kategorii, aktywacją/dezaktywacją oraz blokadą usunięcia usługi posiadającej historię rezerwacji.
- **Pracownicy i grafik (`admin_employees.php`):** Tworzenie profili fizjoterapeutów, przypisywanie wykonywanych zabiegów (relacja `N:M`) oraz definiowanie godzin pracy.
- **Użytkownicy (`admin_users.php`):** Lista kont z wyszukiwarką po imieniu, nazwisku i e-mailu, zmianą roli oraz blokowaniem/odblokowywaniem dostępu.
- **Rezerwacje (`admin_reservations.php`):** Przegląd wszystkich wizyt, filtrowanie wielokryterialne (data, status, pracownik), zmiana statusów oraz eksport do pliku CSV.

### 3. Część Klienta, System Rezerwacji i Panel Pracownika (Etap 4)
- **Publiczna oferta usług (`index.php`):** Katalog aktywnych zabiegów z filtrowaniem po kategoriach, wyszukiwarką tekstową, czasem trwania, ceną oraz listą przypisanych specjalistów.
- **5-etapowy proces rezerwacji (`client_panel.php`):** Obowiązkowy przepływ:
  `1. Usługa -> 2. Fizjoterapeuta -> 3. Data wizyty -> 4. Dostępna godzina -> 5. Potwierdzenie`
- **Algorytm dostępności i wykrywania konfliktów przedziałów:**
  - System wyznacza możliwe sloty czasowe wyłącznie w granicach godzin dyżuru wybranego pracownika i z uwzględnieniem czasu trwania zabiegu (`duration`).
  - Blokowane są terminy w przeszłości, poza godzinami pracy oraz wszystkie częściowo lub całkowicie nakładające się rezerwacje wg reguły:
    `start_nowej < koniec_istniejącej AND koniec_nowej > start_istniejącej`
  - Bezpośrednio przed zapisem (`INSERT`) serwer ponownie weryfikuje brak kolizji wewnątrz transakcji SQL z blokadą `SELECT ... FOR UPDATE`, co zabezpiecza przed ominięciem walidacji w przeglądarce oraz równoczesnym zajęciem tego samego terminu.
- **Panel Pacjenta (`client_panel.php`):** Podgląd własnych rezerwacji z podziałem na nadchodzące oraz historię/anulowane, anulowanie własnych przyszłych wizyt, zmiana terminu (przełożenie wizyty) oraz edycja danych profilu i hasła.
- **Panel Fizjoterapeuty (`employee_panel.php`):** Podział własnych wizyt na dzisiejsze, przyszłe oraz historię, karta szczegółów rezerwacji z danymi kontaktowymi pacjenta i uwagami, zmiana statusu wizyty (`oczekująca`, `potwierdzona`, `zrealizowana`, `anulowana`) oraz zarządzanie własnym grafikiem dyżurów.

---

## Funkcje dodatkowe
1. **Graficzny kalendarz dyżurów:** Interaktywny kalendarz miesięczny w kreatorze rezerwacji podświetlający dni i godziny pracy wybranego fizjoterapeuty oraz blokujący dni wolne i minione.
2. **Dynamiczne pobieranie danych (AJAX / Fetch API):** Asynchroniczne ładowanie specjalistów przypisanych do usługi oraz wolnych i kolidujących slotów godzinowych bez przeładowywania strony.
3. **Przełożenie terminu wizyty przez pacjenta:** Możliwość zmiany daty i godziny nadchodzącej rezerwacji z pełną walidacją godzin pracy i kolizji przedziałów.
4. **Eksport rezerwacji do CSV oraz statystyki finansowe:** Pobieranie raportów rezerwacji z uwzględnieniem aktywnych filtrów oraz podsumowanie przychodów na dashboardzie administratora.
5. **Wyszukiwarka i filtrowanie oferty oraz użytkowników:** Filtrowanie zabiegów po kategoriach i frazie na stronie głównej oraz wyszukiwanie kont w panelu administratora.

---

## Struktura repozytorium
```text
├── css/
│   └── style.css
├── database/
│   ├── database.sql
│   └── diagram.md
├── images/
│   └── diagram.png
├── includes/
│   ├── admin_auth.php
│   ├── admin_header.php
│   └── config.example.php
├── js/
│   └── script.js
├── public/
│   ├── index.php
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── client_panel.php
│   ├── employee_panel.php
│   ├── admin_panel.php
│   ├── admin_categories.php
│   ├── admin_services.php
│   ├── admin_employees.php
│   ├── admin_users.php
│   └── admin_reservations.php
└── README.md
```

---

## Instrukcja uruchomienia

### 1. Wersja online
Aplikacja jest wdrożona i dostępna pod adresem: **[https://fizjo.dziadu.dev/](https://fizjo.dziadu.dev/)**

### 2. Uruchomienie lokalne (XAMPP)
1. Sklonuj repozytorium do katalogu `htdocs` w XAMPP (np. `C:\xampp\htdocs\Projekt-aplikacje`).
2. Uruchom moduły **Apache** oraz **MySQL** w panelu XAMPP Control Panel.
3. Otwórz **phpMyAdmin** (`http://localhost/phpmyadmin`) i utwórz nową bazę danych o nazwie `fizjoterapia` (kodowanie `utf8mb4_unicode_ci`).
4. Zaimportuj do utworzonej bazy plik `database/database.sql`.
5. W katalogu `includes/` skopiuj plik `config.example.php` jako `config.php` i upewnij się, że parametry połączenia odpowiadają Twojej konfiguracji lokalnej (`$db = 'fizjoterapia'`).
6. Otwórz aplikację w przeglądarce pod adresem: `http://localhost/Projekt-aplikacje/public/index.php`.

---

## Testowe dane logowania

| Rola w systemie | Adres e-mail | Hasło | Docelowy panel |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@gabinet.pl` | `password` | `admin_panel.php` |
| **Pracownik (Fizjoterapeuta)** | `anna@gabinet.pl` | `password` | `employee_panel.php` |
| **Klient (Pacjent)** | `pacjent@test.pl` | `password` | `client_panel.php` |