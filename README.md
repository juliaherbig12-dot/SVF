# SV Frohnstetten CMS – v2.13

## Neuerungen
- Startseite „Unsere Teams“ zeigt nur die Bereiche Aktive Herren, Aktive Damen und Jugend. Klick öffnet jeweils dieselbe Bereichsseite wie die Fußball-Navigation.
- Volleyball nutzt dieselbe große Kachel-Logik wie Fußball; Sportarten bekommen eigene grafische Akzente.
- Fußball wird als Spielgemeinschaft gekennzeichnet. Zugehörige Vereine können im Redaktionsbereich gepflegt und verlinkt werden.
- Neues Redaktionsmodul `Redaktion → Sportbereiche`: zuerst Sportart auswählen, danach nur die Gruppen dieses Bereichs verwalten.
- Gruppen-Unterseite mit separaten Bereichen für Übersicht, Kader, Trainingszeiten und Ansprechpartner sowie gefilterten Berichte-/Bilder-Ansichten.
- Trainingszeiten und Ansprechpartner können nun gruppenspezifisch gepflegt werden.
- Berichte und Medien können nach Gruppe gefiltert werden; neue Bilder werden bei einem gruppenspezifischen Upload der Gruppe zugeordnet.
- `Unser Verein` ist im Redaktionsbereich in echte Unterseiten aufgeteilt: Vorstand, Übungszeiten, Links, Vereinsseiten, Dokumente.

## Start lokal
```text
php storage\\init.php
php -S 127.0.0.1:8081 -t public
```

Öffnen: http://127.0.0.1:8081

Redaktion: http://127.0.0.1:8081/login/

Test-Admin:
- E-Mail: `admin@svfrohnstetten.local`
- Passwort: `SVF-Test-2026!`

Die Datenbankmigration ist abwärtskompatibel und ergänzt die neuen Tabellen/Felder bei einem bestehenden lokalen SQLite-Datenbestand.


## v2.13.1 – Fehlerbehebung
- Datenbankmigration korrigiert: fehlende `articles`-Tabelle wird jetzt beim Start zuverlässig angelegt.
- Medien-Tabelle wird vor ihren Erweiterungen angelegt.
- Bekannte Fußball-Spielgemeinschaftspartner werden initial eingetragen: TSV Stetten a.k.M. e.V. und SV Schwenningen e.V.
- `start-local.bat` bricht bei einem Fehler der Datenbankinitialisierung jetzt verständlich ab.

## v2.13.3 – Redakteur-Login, ruhigeres Design & SGM-Social-Links
- Redakteure können sich jetzt regulär anmelden und landen im Redaktions-Dashboard.
- Administrator-only Bereiche bleiben für Redakteure gesperrt; zugewiesene Sportbereiche werden im Dashboard angezeigt.
- Das öffentliche Design wurde bewusst ruhiger und natürlicher gestaltet: weniger Verläufe, weniger starke Schatten und abgerundete „KI-Karten“, klarere Typografie und mehr klassischer Vereinscharakter.
- Das Redaktionsdesign wurde passend dazu ebenfalls zurückgenommen.
- Bei den SGM-Heuberg-Partnern gibt es jetzt zusätzlich ein eigenes Instagram-Feld. Sobald die exakten Profil-URLs hinterlegt sind, erscheinen sie direkt bei den SGM-Links auf der Website.

Hinweis zu Instagram: Die exakten offiziellen Instagram-Profil-URLs von SGM Heuberg bzw. den beteiligten Vereinen konnten nicht zuverlässig verifiziert werden. Deshalb wurden keine möglicherweise falschen Profiladressen erfunden. Die Links können im Redaktionsbereich unter `Sportbereiche → Fußball → SGM Heuberg` direkt eingetragen werden.

## v2.13.4 – Datenhaltung

Die strukturierten Vereins-Stammdaten werden nicht mehr als PHP-Arrays in `storage/init.php` geführt.
Die Erstbefüllung liegt in:
- `storage/seed/structure.json` – Abteilungen, Gruppen und SGM-Partner
- `storage/seed/club.json` – Vorstand/Ansprechpartner, Übungszeiten und Links

Wichtig: Diese JSON-Dateien sind ausschließlich die **Erstbefüllung einer leeren Datenbank**. Im laufenden Betrieb werden die Daten aus SQLite gelesen und über die Redaktion verwaltet. Bestehende Daten werden beim Start nicht überschrieben.

## v2.13.5 – Vereinsdaten vollständig aus Seed-Dateien
- Die bisher in `storage/init.php` fest eingebetteten Vereinsseiten und Vereinsunterlagen wurden entfernt.
- `storage/seed/club.json` enthält jetzt auch Vereinsseiten und sämtliche Vereinsdokument-Inhalte.
- `storage/seed/structure.json` enthält auch die SGM-Heuberg-Konfiguration.
- `storage/init.php` enthält keine fest codierten Vorstands-, Trainings-, Vereinsseiten- oder Dokumentinhalte mehr.
- Seed-Dateien dienen ausschließlich der Erstbefüllung einer leeren Datenbank; bestehende redaktionelle Inhalte werden nicht überschrieben.
- Die alte `init.php.bak` mit den früheren Datenlisten wurde aus dem Paket entfernt.

## v3.2 – System & mobile/SEO
- Admin: Administratoren können unter „Backup“ die aktuelle SQLite-Datenbank herunterladen.
- Öffentliche Seiten: zusätzliche Meta-Beschreibungen für Suchmaschinen.
- `robots.txt` schützt Redaktions- und interne Bereiche vor Indexierung.
- Header-Logo erhält auf Desktop und Mobilgeräten mehr Platz, damit das Wappen nicht angeschnitten wird.
- Rechtliche Links (Impressum/Datenschutz) sind im Footer erreichbar.

## v3.3 – Redaktionsausbau
- Bereichsseiten in der Redaktion um klarere Unterseiten ergänzt.
- Untergruppen werden direkt auf der jeweiligen Bereichsseite angezeigt.
- Öffentliche Vorschau ist direkt aus einem Bereich erreichbar.
- FUSSBALL.DE-Widgets können direkt innerhalb der jeweiligen Fußballmannschaft bearbeitet werden.
- Kader, Training, Ansprechpartner, Berichte und Bilder bleiben getrennt und bereichsbezogen.
