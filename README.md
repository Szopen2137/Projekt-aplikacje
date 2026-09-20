# System Rezerwacji Usług - Gabinet Fizjoterapii

## Autorzy
* **Alan Ossoliński** – [@rudyk0607](https://github.com/rudyk0607) 
* **Szymon Trojanek** – [@Szopen2137](https://github.com/Szopen2137)

## Opis systemu
Kompletna aplikacja internetowa umożliwiająca pacjentom rezerwowanie terminów zabiegów fizjoterapeutycznych. System wspiera obsługę gabinetu poprzez dedykowane panele dla trzech ról: klienta (pacjenta), pracownika (fizjoterapeuty) oraz administratora zarządzającego placówką, ofertą i personelem. 

## Zastosowane technologie
* PHP
* HTML5
* CSS
* JavaScript
* MySQL
* Git oraz GitHub

## Instrukcja uruchomienia
1. Upewnij się, że posiadasz zainstalowane środowisko serwera lokalnego obsługujące PHP i MySQL (np. XAMPP).
2. Sklonuj niniejsze repozytorium do głównego katalogu serwera (np. folder `htdocs` w środowisku XAMPP).
3. Uruchom moduły Apache i MySQL w panelu kontrolnym serwera.
4. Przejdź do narzędzia phpMyAdmin (domyślnie `http://localhost/phpmyadmin`) i utwórz pustą bazę danych.
5. Zaimportuj do nowo utworzonej bazy plik `database.sql`, który znajduje się w folderze `/database/` niniejszego repozytorium.
6. Skopiuj plik includes/config.example.php, zmień jego nazwę na config.php i uzupełnij go własnymi danymi dostępowymi do lokalnego serwera MySQL.
7. Uruchom projekt wpisując w pasku adresu przeglądarki: `http://localhost/nazwa-folderu-z-projektem`.

## Testowe dane logowania
W załączonej bazie danych przygotowano konta testowe z przypisanymi odpowiednimi rolami:

* **Konto Administratora**
  * E-mail: `admin@gabinet.pl`
  * Hasło: `haslo123`

* **Konto Pracownika (Fizjoterapeuty)**
  * E-mail: `anna@gabinet.pl`
  * Hasło: `haslo123`

* **Konto Klienta (Pacjenta)**
  * E-mail: `pacjent@test.pl`
  * Hasło: `haslo123`

## Funkcje dodatkowe
*Sekcja zostanie zaktualizowana w późniejszych etapach.* 
Obecnie planowane jest wdrożenie następujących funkcji dodatkowych, wykraczających poza wymagania podstawowe:
* Zastosowanie technologii AJAX do dynamicznego pobierania dostępnych terminów.
* Rejestrowanie historii zmian statusów rezerwacji wizyt.