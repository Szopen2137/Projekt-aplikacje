# System Rezerwacji Usług - Gabinet Fizjoterapii

🌐 **Wersja na stronie:** [https://fizjo.dziadu.dev/](https://fizjo.dziadu.dev/)

## Autorzy
- **Alan Ossoliński** – [@rudyk0607](https://github.com/rudyk0607) 
- **Szymon Trojanek** – [@Szopen2137](https://github.com/Szopen2137)

## Opis systemu
Kompletna aplikacja internetowa umożliwiająca pacjentom rezerwowanie terminów zabiegów fizjoterapeutycznych. System wspiera obsługę gabinetu poprzez dedykowane panele dla trzech ról: klienta (pacjenta), pracownika (fizjoterapeuty) oraz administratora zarządzającego placówką, ofertą i personelem.

## Zastosowane technologie
- **Backend:** PHP 8+ (z wykorzystaniem biblioteki PDO)
- **Baza danych:** MySQL / MariaDB (zgodna z 3NF)
- **Frontend:** HTML5, CSS3 (Pico CSS), JavaScript
- **Kontrola wersji i wdrożenie:** Git, GitHub, własny serwer produkcyjny

## Architektura i bezpieczeństwo (Etap 1 i 2)
W projekcie wdrożono dobre praktyki programistyczne oraz zabezpieczenia aplikacyjne:
- **Ochrona przed SQL Injection:** Wszystkie zapytania do bazy danych są realizowane przez bibliotekę PDO z użyciem zapytań przygotowanych (*Prepared Statements*).
- **Kryptografia haseł:** Hasła użytkowników są bezpiecznie hashowane algorytmem `bcrypt` za pomocą funkcji `password_hash()` oraz weryfikowane przez `password_verify()`.
- **Bezpieczeństwo sesji i autoryzacja (RBAC):** Zastosowano regenerację identyfikatora sesji przy logowaniu (`session_regenerate_id`) chroniącą przed atakiem *Session Fixation* oraz ścisłą kontrolę dostępu do paneli na podstawie ról.
- **Ochrona przed XSS:** Dane wyjściowe generowane przez użytkowników są filtrowane za pomocą funkcji `htmlspecialchars()`.
- **Separacja konfiguracji:** Plik z poświadczeniami bazy danych (`includes/config.php`) został wykluczony z repozytorium za pomocą `.gitignore` na rzecz bezpiecznego szablonu `config.example.php`.

## Panel Administratora (Etap 3)
Zaimplementowano kompletny panel zarządzania placówką dostępny wyłącznie dla roli `admin`:
- **Dashboard (`admin_panel.php`):** Rzeczywiste statystyki z bazy danych (liczba pacjentów, pracowników, usług, wszystkich rezerwacji, wizyt na dziś oraz podsumowanie przychodów).
- **Kategorie usług (`admin_categories.php`):** Pełny CRUD kategorii z ochroną integralności danych (blokada usunięcia kategorii posiadającej przypisane usługi).
- **Usługi i zabiegi (`admin_services.php`):** Zarządzanie cennikiem, czasem trwania, przypisaniem do kategorii oraz bezpieczna aktywacja/dezaktywacja zabiegów.
- **Pracownicy i grafik (`admin_employees.php`):** Zarządzanie kadrą, przypisywanie wykonywanych usług w relacji N:M (`employee_services`) oraz definiowanie godzin dostępności (`employee_availability`).
- **Użytkownicy (`admin_users.php`):** Przeglądanie kont, wyszukiwarka po imieniu, nazwisku i adresie e-mail, zmiana ról oraz blokowanie/odblokowywanie kont (`active`).
- **Rezerwacje (`admin_reservations.php`):** Lista wszystkich wizyt z wielokrotnym filtrowaniem (data, status, pracownik), zmianą statusów oraz eksportem danych do pliku CSV.

## Część Klienta, System Rezerwacji i Panel Pracownika (Etap 4)
Wdrożono główną logikę biznesową systemu rezerwacji oraz dedykowane panele dla pacjentów i fizjoterapeutów:
- **Publiczna oferta usług (`index.php`):** Katalog aktywnych zabiegów z filtrowaniem według kategorii, wyszukiwarką tekstową, czasem trwania, ceną oraz informacją o przypisanych specjalistach.
- **5-etapowy proces rezerwacji (`client_panel.php` + `js/script.js`):** Obowiązkowa kolejność kroków `usługa -> pracownik -> data -> godzina -> potwierdzenie` z dynamicznym pobieraniem dostępnych fizjoterapeutów i wolnych slotów czasowych przez AJAX (`fetch`).
- **Wykrywanie konfliktów przedziałów czasowych i ochrona przed wyścigiem (Race Condition):** System uwzględnia godziny pracy fizjoterapeuty, czas trwania wybranego zabiegu oraz blokuje wszystkie częściowo i całkowicie nakładające się terminy według reguły `start_nowej < koniec_istniejącej AND koniec_nowej > start_istniejącej`. Bezpośrednio przed zapisem w bazie serwer ponownie weryfikuje dostępność w transakcji SQL z blokadą `SELECT ... FOR UPDATE`.
- **Panel klienta (`client_panel.php`):** Podział własnych wizyt na nadchodzące oraz historię/anulowane, możliwość anulowania własnej przyszłej rezerwacji, opcja przełożenia terminu wizyty z walidacją konfliktów oraz edycja profilu i zmiana hasła.
- **Panel pracownika (`employee_panel.php`):** Podział własnych wizyt fizjoterapeuty na wizyty dzisiejsze, przyszłe oraz historię, widok szczegółów rezerwacji i danych kontaktowych pacjenta, zmiana statusu obsługiwanej wizyty (`oczekująca`, `potwierdzona`, `zrealizowana`, `anulowana`), zarządzanie własnym grafikiem dostępności oraz ścisła kontrola własności po stronie serwera (brak dostępu do wizyt innego pracownika).

## Dostęp do aplikacji

### 1. Wersja online (Zalecana do szybkiego testowania)
Aplikacja została wdrożona na serwerze zewnętrznym i jest dostępna pod adresem: **[https://fizjo.dziadu.dev/](https://fizjo.dziadu.dev/)**

### 2. Instrukcja uruchomienia lokalnego
1. Upewnij się, że posiadasz zainstalowane środowisko serwera lokalnego obsługujące PHP i MySQL (np. XAMPP).
2. Sklonuj niniejsze repozytorium do głównego katalogu serwera (np. `C:\xampp\htdocs\Projekt-aplikacje`)[cite: 3, 4].
3. Uruchom moduły **Apache** oraz **MySQL** w panelu kontrolnym XAMPP.
4. Przejdź do narzędzia phpMyAdmin (`http://localhost/phpmyadmin`) i utwórz nową, pustą bazę danych (np. `fizjoterapia`).
5. Zaimportuj do nowo utworzonej bazy plik `database.sql` (znajdujący się w folderze `/database/` lub głównym katalogu repozytorium).
6. Przejdź do folderu `includes/`, skopiuj plik `config.example.php` i zmień nazwę kopii na `config.php`.
7. Otwórz plik `includes/config.php` i wpisz w nim nazwę swojej bazy danych (w zmiennej `$db`).
8. Uruchom projekt wpisując w pasku adresu przeglądarki: `http://localhost/Projekt-aplikacje/public/index.php`[cite: 4].

## Testowe dane logowania
W bazie danych przygotowano konta testowe z przypisanymi odpowiednimi rolami:

| Rola w systemie | Adres e-mail | Hasło | Docelowy panel |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@gabinet.pl` | `password` | `admin_panel.php` |
| **Pracownik (Fizjoterapeuta)** | `anna@gabinet.pl` | `password` | `employee_panel.php` |
| **Klient (Pacjent)** | `pacjent@test.pl` | `password` | `client_panel.php` |

## Funkcje dodatkowe
W projekcie zaimplementowano i zintegrowano następujące funkcje dodatkowe:
- **Dynamiczne pobieranie terminów (AJAX / Fetch API):** Interaktywny wybór usługi, fizjoterapeuty, dni dyżurów oraz wolnych i zajętych przedziałów godzinowych bez przeładowywania strony.
- **Przełożenie terminu wizyty przez klienta:** Możliwość zmiany daty i godziny własnej przyszłej rezerwacji z pełną walidacją godzin pracy i konfliktów przedziałów czasowych.
- **Zarządzanie własnym grafikiem przez pracownika:** Fizjoterapeuta może samodzielnie dodawać i aktualizować godziny swoich dyżurów w panelu pracownika.
- **Wyszukiwarka i filtrowanie oferty oraz użytkowników:** Filtrowanie zabiegów po kategoriach i frazie na stronie głównej oraz wyszukiwarka użytkowników i wielokryterialne filtrowanie rezerwacji w panelu administratora.