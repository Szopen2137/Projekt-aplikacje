# System Rezerwacji Usług - Gabinet Fizjoterapii

## Autorzy
* **Alan Ossoliński** – [@rudyk0607](https://github.com/rudyk0607) 
* **Szymon Trojanek** – [@Szopen2137](https://github.com/Szopen2137)

## Opis systemu
Kompletna aplikacja internetowa umożliwiająca pacjentom rezerwowanie terminów zabiegów fizjoterapeutycznych[cite: 1]. System wspiera obsługę gabinetu poprzez dedykowane panele dla trzech ról: klienta (pacjenta), pracownika (fizjoterapeuty) oraz administratora zarządzającego placówką, ofertą i personelem[cite: 1]. 

## Zastosowane technologie
* PHP[cite: 1]
* HTML5[cite: 1]
* CSS[cite: 1]
* JavaScript[cite: 1]
* MySQL[cite: 1]
* Git oraz GitHub[cite: 1]

## Instrukcja uruchomienia
1. Upewnij się, że posiadasz zainstalowane środowisko serwera lokalnego obsługujące PHP i MySQL (np. XAMPP)[cite: 1].
2. Sklonuj niniejsze repozytorium do głównego katalogu serwera (np. folder `htdocs` w środowisku XAMPP).
3. Uruchom moduły Apache i MySQL w panelu kontrolnym serwera.
4. Przejdź do narzędzia phpMyAdmin (domyślnie `http://localhost/phpmyadmin`) i utwórz pustą bazę danych.
5. Zaimportuj do nowo utworzonej bazy plik `database.sql`, który znajduje się w folderze `/database/` niniejszego repozytorium[cite: 1].
6. Uruchom projekt wpisując w pasku adresu przeglądarki: `http://localhost/nazwa-folderu-z-projektem`.

## Testowe dane logowania
W załączonej bazie danych przygotowano konta testowe z przypisanymi odpowiednimi rolami[cite: 1]:

* **Konto Administratora**[cite: 1]
  * E-mail: `admin@gabinet.pl`
  * Hasło: `haslo123`

* **Konto Pracownika (Fizjoterapeuty)**[cite: 1]
  * E-mail: `anna@gabinet.pl`
  * Hasło: `haslo123`

* **Konto Klienta (Pacjenta)**
  * E-mail: `pacjent@test.pl`
  * Hasło: `haslo123`

## Funkcje dodatkowe
*Sekcja zostanie zaktualizowana w późniejszych etapach.* 
Obecnie planowane jest wdrożenie następujących funkcji dodatkowych, wykraczających poza wymagania podstawowe[cite: 1]:
* Zastosowanie technologii AJAX do dynamicznego pobierania dostępnych terminów[cite: 1].
* Rejestrowanie historii zmian statusów rezerwacji wizyt[cite: 1].