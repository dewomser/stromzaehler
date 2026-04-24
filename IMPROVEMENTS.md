# Stromzähler Arduino + PHP8.5 - Verbesserungen & Dokumentation

## 📋 Übersicht der Änderungen

Dieses Projekt wurde komplett überarbeitet und modernisiert:

### **Arduino-Sketches** ✅
- ✅ `examples_improved.ino` - Hauptversion mit umfassenden Kommentaren (350+ Zeilen)
- ✅ `examples/stromzaehler/stromzaehler_improved.ino` - Kompakte Variante
- ✅ Debouncing gegen Kontaktprellen
- ✅ Timeout-Handling (5 min = 0 Watt)
- ✅ CORS-Header für Remote-Zugriff
- ✅ Serial-Debugging mit aussagekräftigen Meldungen
- ✅ Timeout-Schutz beim HTTP-Handling

### **PHP 8.5 Script** ✅
- ✅ `stromtabelle1_improved.php` - Vollständige Neufassung
- ✅ MySQLi mit Exception Handling (statt deprecated mysql_*)
- ✅ Prepared Statements (SQL-Injection safe)
- ✅ Named Parameters in mysqli
- ✅ UTF-8 Charset (utf8mb4) korrekt
- ✅ Regex-Parsing für robuste Wertextraktion
- ✅ File Locks beim Schreiben
- ✅ Error Logging System
- ✅ Timeout bei Arduino-Abfrage
- ✅ 250+ Zeilen Dokumentation

---

## 🚀 Quick Start

### 1. Arduino Sketch hochladen

```cpp
// Öffne examples_improved.ino in Arduino IDE
// Board: Arduino Mega 2560
// COM-Port: wählen
// Upload
```

**Erwartete Serial Output (9600 Baud):**
```
===============================================
Arduino Stromzaehler - Webserver
===============================================
IP-Adresse: 192.168.1.6
HTTP-Server aktiv auf Port 80
S0-Stromzaehler auf Pin 22 bereit
Warte auf Impulse...
===============================================

Impuls: 2000 W (900 ms)
Impuls: 2050 W (878 ms)
Client verbunden
Response: 2000 Watt
Client disconnected
```

### 2. PHP 8.5 Script konfigurieren

```php
// In stromtabelle1_improved.php (Zeile 40-51):

const DB_HOST = 'localhost';
const DB_USER = 'your_db_user';           // ← deine Daten
const DB_PASSWORD = 'your_db_password';
const DB_NAME = 'your_database';
const ARDUINO_URL = 'http://192.168.1.6/';
const OUTPUT_DIR = '/var/www/html/stromzaehler/';
const FONT_FILE = OUTPUT_DIR . 'arial.ttf';
```

### 3. Datenbank erstellen

```sql
CREATE DATABASE stromzaehler CHARACTER SET utf8mb4;

CREATE TABLE watt (
  id INT AUTO_INCREMENT PRIMARY KEY,
  watt INT NOT NULL,
  zeit TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

GRANT ALL PRIVILEGES ON stromzaehler.* TO 'stromzaehler'@'localhost' IDENTIFIED BY 'password';
```

### 4. Cron-Job einrichten

```bash
# Terminal: crontab -e

# Alle 2 Stunden
0 */2 * * * php -f /var/www/html/stromzaehler/stromtabelle1_improved.php

# Oder alle 5 Minuten für häufigere Updates:
*/5 * * * * php -f /var/www/html/stromzaehler/stromtabelle1_improved.php
```

### 5. Manuell testen

```bash
# PHP testen
php -f stromtabelle1_improved.php

# Arduino anpingen
ping 192.168.1.6

# Im Browser
http://192.168.1.6

# Mit curl
curl http://192.168.1.6
```

---

## ⚡ Stromzähler-Kalibrierung

Die Berechnung basiert auf der Formel:

```
Watt = 1800000 / Impulsabstand_ms

Beispiele:
- 1000W → ~1800 ms zwischen Impulsen
- 2000W → ~900 ms zwischen Impulsen
- 3000W → ~600 ms zwischen Impulsen
- 100W → ~18000 ms zwischen Impulsen
```

### Bekannte Stromzähler

| Zählertyp | Kalibrierung | Formel |
|-----------|--------------|--------|
| Voltcraft DPM1L32-D | 1 Impuls = 1 Wh | `1800000 / ms` |
| Siemens 7KT | 1 Impuls = 1 Wh | `1800000 / ms` |
| EasyMeter | 1 Impuls = 0,1 Wh | `180000 / ms` |
| EMH eHZ-K | 1 Impuls = 1 Wh | `1800000 / ms` |

**Ermittle den Faktor selbst:**
1. Heizer mit bekannter Leistung (z.B. 1000W) einschalten
2. Impulsabstand messen (Serial Monitor)
3. Faktor = Leistung (W) × Impulsabstand (ms) / 1000

---

## 🔧 Fehlersuche & Debugging

### Arduino zeigt keine Impulse
```
Prüfung:
1. Pin 22 mit Multimeter testen
2. Stromzähler mit GND verbunden?
3. DEBOUNCE_DELAY in Sketch erhöhen (50 → 100 ms)
4. Serial Monitor auf 9600 Baud stellen
```

### PHP Script läuft, aber keine Datenbankeinträge
```bash
# Prüfe Datenbank-Verbindung
php -r "
\$db = new mysqli('localhost', 'user', 'pass', 'db');
if (\$db->connect_error) echo 'Fehler: ' . \$db->connect_error;
else echo 'OK';
"

# Prüfe Arduino-Erreichbarkeit
curl http://192.168.1.6

# Logs prüfen
tail -f /var/www/html/stromzaehler/error.log
```

### "Konnte Daten vom Arduino nicht abrufen"
```
Lösungen:
1. Arduino läuft?
2. Ethernet-Kabel steckt?
3. IP-Adresse richtig?
4. Firewall blockiert Port 80?

Test:
ping 192.168.1.6
curl -v http://192.168.1.6
```

### Falsche Watt-Werte
```
Mögliche Ursachen:
1. Stromzähler nicht kalibriert
2. Falscher Kalibrierfaktor (siehe oben)
3. Zu kurze Debounce-Verzögerung
4. Stromzähler defekt

Lösung:
- Formel in Sketch anpassen: 1800000 → neuer_Faktor
- DEBOUNCE_DELAY erhöhen
```

---

## 📊 Grafik-Ausgabe

Das PHP Script erstellt automatisch `stromtabelle.png`:

- **Größe:** 250×250 Pixel
- **Daten:** Letzte 10 Messwerte
- **Format:** Säulendiagramm (Bar Chart)
- **Aktualisierung:** Mit jedem Cron-Durchlauf

**Anzeige:**
```html
<img src="/stromzaehler/stromtabelle.png" alt="Stromverbrauch">
```

---

## 🔒 Sicherheit

### SQL-Injection geschützt
```php
// ✅ SICHER - Prepared Statement
$stmt = $db->prepare("INSERT INTO watt (watt) VALUES (?)");
$stmt->bind_param('i', $watt_value);
$stmt->execute();

// ❌ UNSICHER (alt)
$db->query("INSERT INTO watt VALUES (0, $watt_value, NOW())");
```

### Fehlerbehandlung
```php
try {
    // Code
} catch (Exception $e) {
    logError("Fehler", $e);  // Logged in error.log
}
```

### Timeouts
- Arduino-Abfrage: 5 Sekunden
- Client-Verbindung (Arduino): 5 Sekunden
- Impuls-Timeout (Arduino): 5 Minuten

---

## 📝 Datenbankstruktur

```sql
+-------+------+---------------------+
| id    | watt | zeit                |
+-------+------+---------------------+
| 1     | 2345 | 2026-04-24 14:00:00 |
| 2     | 2412 | 2026-04-24 14:02:00 |
| 3     | 2378 | 2026-04-24 14:04:00 |
+-------+------+---------------------+
```

**Wichtige Felder:**
- `id`: Auto-Increment (Primary Key)
- `watt`: Integer (Leistung in Watt)
- `zeit`: Timestamp (Auto-set zu aktueller Zeit)

---

## 📞 Support & Tipps

### Performance verbessern
```bash
# Datenbank-Index erstellen
CREATE INDEX idx_zeit ON watt(zeit DESC);

# Ältere Daten archivieren (nach 30 Tagen)
DELETE FROM watt WHERE zeit < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### Web-Interface erweitern
```html
<!-- Aktuelle Leistung live -->
<div id="watt">Wird geladen...</div>

<script>
setInterval(async () => {
  const response = await fetch('http://192.168.1.6/');
  const text = await response.text();
  document.getElementById('watt').textContent = text;
}, 1000);
</script>
```

### Alarm bei Überlast
```php
// In stromtabelle1_improved.php
if ($watt_value > 5000) {
    mail('admin@example.com', 'Stromüberlast!', "Leistung: $watt_value W");
}
```

---

## 📚 Zusätzliche Ressourcen

- **Arduino Ethernet Shield:** https://docs.arduino.cc/hardware/ethernet-shield-rev2
- **S0-Schnittstelle:** http://blog.elektrowolle.de/2011/07/26/s0-messwerterfassung-von-stromzahlern/
- **PHP MySQLi:** https://www.php.net/manual/en/mysqli.quickstart.prepared-statements.php
- **GD Library (Grafiken):** https://www.php.net/manual/en/book.image.php

---

## ✨ Versionshistorie

| Version | Datum | Änderungen |
|---------|-------|-----------|
| 2.0 | 2026-04-24 | PHP 8.5 Rewrite, Debouncing, Exception Handling |
| 1.0 | 2012-02-08 | Original von Stefan Höhn |

---

**Viel Erfolg mit deinem Stromzähler-Projekt! 🔌⚡**
