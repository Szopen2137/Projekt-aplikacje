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
*Sekcja zostanie zaktualizowana w późniejszych etapach.* 
Obecnie planowane jest wdrożenie następujących funkcji dodatkowych, wykraczających poza wymagania podstawowe:
- Zastosowanie technologii AJAX do dynamicznego pobierania dostępnych terminów.
- Rejestrowanie historii zmian statusów rezerwacji wizyt.