# Projekt-aplikacje
projekt do szkoły tworzenie stron i aplikacji internetowych
## Tematyka strony: 
* Salon fizjoterapii
## Autorzy

* **Alan Ossoliński** – [@rudyk0607](https://github.com/rudyk0607) 
* **Szymon Trojanek** – [@Szopen2137](https://github.com/Szopen2137)



## Instrukcja uruchomienia (Lokalnie)

1. **Wymagania wstępne:** Zainstaluj lokalny serwer obsługujący PHP i MySQL, np. XAMPP lub Laragon.
2. **Pobranie projektu:** Sklonuj repozytorium do głównego katalogu serwera (np. folder `htdocs` w XAMPP lub `www` w Laragonie).
3. **Uruchomienie serwera:** Włącz moduły Apache oraz MySQL w panelu kontrolnym swojego serwera lokalnego.
4. **Import bazy danych:** 
   * Przejdź pod adres `http://localhost/phpmyadmin`.
   * Utwórz nową bazę danych.
   * Zaimportuj plik `database.sql` znajdujący się w folderze `/database` naszego projektu.
5. **Konfiguracja (jeśli dotyczy):** Upewnij się, że dane połączenia z bazą w pliku konfiguracyjnym aplikacji (np. `config.php`) zgadzają się z Twoimi ustawieniami lokalnymi (domyślnie użytkownik `root`, brak hasła).
6. **Uruchomienie:** Otwórz przeglądarkę i wejdź pod adres `http://localhost/nazwa_folderu_z_projektem`.

## Konta testowe
W bazie znajdują się wstępnie skonfigurowane konta testowe:
* **Administrator:** admin@gabinet.pl / haslo123
* **Fizjoterapeuta:** anna@gabinet.pl / haslo123
* **Pacjent (Klient):** pacjent@test.pl / haslo123