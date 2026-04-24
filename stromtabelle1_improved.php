<?php
/**
 * ============================================================================
 * Stromzähler Datenlogger - PHP 8.5 Version
 * ============================================================================
 * 
 * Beschreibung:
 * - Liest den aktuellen Stromverbrauch vom Arduino-Webserver
 * - Speichert die Werte in eine MariaDB/MySQL Datenbank
 * - Erstellt eine PNG-Grafik mit den letzten 10 Messwerten
 * 
 * Anforderungen:
 * - PHP 8.1+ (mysqli)
 * - MySQL/MariaDB Datenbank
 * - GD Library für Bildgenerierung
 * - TTF Font-Datei (arial.ttf)
 * 
 * Einrichtung:
 * 1. Datenbank und Tabelle erstellen (siehe unten)
 * 2. Konfiguration in dieser Datei anpassen
 * 3. Im Cron-Job eintragen: 0 */2 * * * php -f /pfad/zur/stromtabelle1.php
 * 
 * Datenbank-Struktur:
 * CREATE TABLE watt (
 *   id INT AUTO_INCREMENT PRIMARY KEY,
 *   watt INT NOT NULL,
 *   zeit TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 * 
 * @author Stefan Höhn (untergang.de) - Original
 * @modified 2026 - PHP 8.5 Anpassung und Verbesserungen
 * ============================================================================
 */

// ============================================================================
// KONFIGURATION
// ============================================================================

// Datenbank-Verbindungsparameter
const DB_HOST = 'localhost';
const DB_USER = 'your_db_user';           // !!! Bitte anpassen !!!
const DB_PASSWORD = 'your_db_password';   // !!! Bitte anpassen !!!
const DB_NAME = 'your_database';          // !!! Bitte anpassen !!!
const DB_TABLE = 'watt';

// Arduino Webserver URL
// Format: http://[IP]:[PORT]/
const ARDUINO_URL = 'http://welt.untergang.de:81/';

// Dateisystem-Pfade (absolut, mit Slash am Ende)
const OUTPUT_DIR = '/var/www/html/stromzaehler/';
const CACHE_FILE = OUTPUT_DIR . 'watt_current.inc';
const GRAPH_FILE = OUTPUT_DIR . 'stromtabelle.png';
const FONT_FILE = OUTPUT_DIR . 'arial.ttf';

// Grafik-Parameter
const GRAPH_WIDTH = 250;
const GRAPH_HEIGHT = 250;
const GRAPH_COLUMNS = 10;  // Anzahl der Datenpunkte in der Grafik

// ============================================================================
// FEHLERBEHANDLUNG UND LOGGING
// ============================================================================

// Aktiviere Error Reporting für Entwicklung
// In Production sollte error_reporting = E_ALL & ~E_NOTICE
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Fehler-Logging-Funktion
function logError(string $message, ?Throwable $exception = null): void
{
    $timestamp = date('Y-m-d H:i:s');
    $errorMsg = "[$timestamp] $message";
    
    if ($exception !== null) {
        $errorMsg .= " | Exception: " . $exception->getMessage();
        $errorMsg .= " in " . $exception->getFile() . ":" . $exception->getLine();
    }
    
    error_log($errorMsg, 3, OUTPUT_DIR . 'error.log');
}

try {
    // ========================================================================
    // PART 1: Messwert vom Arduino auslesen
    // ========================================================================
    
    // Lese die Webseite vom Arduino
    $response = @file_get_contents(ARDUINO_URL, false, stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'Stromzaehler-Logger/1.0 (PHP 8.5)'
        ]
    ]));
    
    if ($response === false) {
        throw new Exception("Konnte Daten vom Arduino nicht abrufen: " . ARDUINO_URL);
    }
    
    // Extrahiere die Zahlenwerte (Watt)
    // Der Arduino antwortet im Format: "XXX Watt"
    // Mit regex: nur die Zahlen vor dem Wort "Watt"
    if (!preg_match('/^(\d+)\s+Watt/i', trim($response), $matches)) {
        throw new Exception("Arduino-Response konnte nicht geparst werden: " . htmlspecialchars($response));
    }
    
    $watt_value = (int)$matches[1];
    
    if ($watt_value < 0 || $watt_value > 999999) {
        throw new Exception("Ungültiger Watt-Wert: $watt_value");
    }
    
    // Speichere den aktuellen Wert im Cache-File (für schnelle Web-Anzeige)
    if (!is_writable(OUTPUT_DIR)) {
        throw new Exception("Ausgabeverzeichnis ist nicht beschreibbar: " . OUTPUT_DIR);
    }
    
    file_put_contents(CACHE_FILE, $watt_value . " Watt", LOCK_EX);
    
    // ========================================================================
    // PART 2: Datenbankverbindung und Datenspeicherung
    // ========================================================================
    
    // Verbinde mit der Datenbank (MySQLi mit Exceptions)
    $db = new mysqli(
        hostname: DB_HOST,
        username: DB_USER,
        password: DB_PASSWORD,
        database: DB_NAME
    );
    
    // Prüfe die Verbindung
    if ($db->connect_error) {
        throw new Exception("Datenbankverbindung fehlgeschlagen: " . $db->connect_error);
    }
    
    // Setze UTF-8 als Zeichensatz
    $db->set_charset('utf8mb4');
    
    // Prepared Statement für sichere SQL-Abfrage (verhindert SQL-Injection)
    $stmt = $db->prepare("INSERT INTO " . DB_TABLE . " (watt, zeit) VALUES (?, NOW())");
    
    if ($stmt === false) {
        throw new Exception("SQL-Fehler beim Prepare: " . $db->error);
    }
    
    // Binde den Watt-Wert an die prepared statement (i = integer)
    $stmt->bind_param('i', $watt_value);
    
    // Führe die Abfrage aus
    if (!$stmt->execute()) {
        throw new Exception("SQL-Fehler beim Execute: " . $stmt->error);
    }
    
    $stmt->close();
    
    // ========================================================================
    // PART 3: Abrufen der letzten Messwerte für die Grafik
    // ========================================================================
    
    // Hole die letzten GRAPH_COLUMNS Messwerte in chronologischer Reihenfolge
    $result = $db->query(
        "SELECT watt, DATE_FORMAT(zeit, '%H:%i') as zeitstr 
         FROM " . DB_TABLE . " 
         ORDER BY zeit DESC 
         LIMIT " . GRAPH_COLUMNS
    );
    
    if ($result === false) {
        throw new Exception("Datenbankabfrage fehlgeschlagen: " . $db->error);
    }
    
    // Speichere die Ergebnisse in Arrays und kehre die Reihenfolge um
    // (für Grafik von alt nach neu)
    $watt_data = [];
    $time_data = [];
    
    while ($row = $result->fetch_assoc()) {
        $watt_data[] = (int)$row['watt'];
        $time_data[] = $row['zeitstr'];
    }
    
    $result->free();
    
    // Kehre die Arrays um (neueste Daten zuletzt für Grafik)
    $watt_data = array_reverse($watt_data);
    $time_data = array_reverse($time_data);
    
    // Schließe die Datenbankverbindung
    $db->close();
    
    // ========================================================================
    // PART 4: PNG-Grafik generieren
    // ========================================================================
    
    if (empty($watt_data)) {
        logError("Keine Messdaten verfügbar für Grafik");
    } else {
        // Erstelle ein neues Bild (TrueColor für bessere Qualität)
        $image = imagecreatetruecolor(GRAPH_WIDTH, GRAPH_HEIGHT);
        
        if ($image === false) {
            throw new Exception("Konnte Bild nicht erstellen");
        }
        
        // Definiere Farben im RGB-Format
        $color_white = imagecolorallocate($image, 255, 255, 255);     // Hintergrund
        $color_gray = imagecolorallocate($image, 192, 192, 192);      // Raster
        $color_blue = imagecolorallocate($image, 0, 150, 255);        // Balken
        $color_light_blue = imagecolorallocate($image, 0, 200, 255);  // Balken-Highlight
        $color_black = imagecolorallocate($image, 0, 0, 0);           // Text
        
        // Fülle den Hintergrund
        imagefilledrectangle($image, 0, 0, GRAPH_WIDTH, GRAPH_HEIGHT, $color_white);
        
        // Zeichne den Rahmen
        imageline($image, 0, 0, 0, GRAPH_HEIGHT - 1, $color_gray);
        imageline($image, 0, 0, GRAPH_WIDTH - 1, 0, $color_gray);
        imageline($image, GRAPH_WIDTH - 1, 0, GRAPH_WIDTH - 1, GRAPH_HEIGHT - 1, $color_gray);
        imageline($image, 0, GRAPH_HEIGHT - 1, GRAPH_WIDTH - 1, GRAPH_HEIGHT - 1, $color_gray);
        
        // Berechne die maximale Leistung für Skalierung der Y-Achse
        $max_watt = max($watt_data);
        if ($max_watt == 0) $max_watt = 1;  // Verhindere Division durch Null
        
        $column_width = GRAPH_WIDTH / count($watt_data);
        
        // Zeichne Raster und Zeitlabels
        if (file_exists(FONT_FILE)) {
            imagettftext($image, 10, 90, 25, 120, $color_black, FONT_FILE, $time_data[0]);
        }
        
        // Zeichne vertikale Rasterlinien und Zeitlabels
        for ($i = 1; $i < count($watt_data); $i++) {
            $x = (int)($i * $column_width);
            
            // Vertikale Rasterlinie
            imageline($image, $x, 0, $x, GRAPH_HEIGHT, $color_gray);
            
            // Zeitlabel (wenn Font vorhanden)
            if (file_exists(FONT_FILE) && isset($time_data[$i])) {
                imagettftext($image, 10, 90, $x + 5, 120, $color_black, FONT_FILE, $time_data[$i]);
            }
            
            // Horizontale Rasterlinie
            imageline($image, 0, $x, GRAPH_WIDTH, $x, $color_gray);
        }
        
        // Zeichne Säulendiagramm
        for ($i = 0; $i < count($watt_data); $i++) {
            $x1 = (int)($i * $column_width);
            $x2 = (int)(($i + 1) * $column_width);
            
            // Berechne die Höhe des Balkens proportional zur maximalen Leistung
            $bar_height = (int)(($watt_data[$i] / $max_watt) * GRAPH_HEIGHT);
            $y1 = GRAPH_HEIGHT - $bar_height;
            $y2 = GRAPH_HEIGHT;
            
            // Zeichne dunklen Balken
            imagefilledrectangle($image, $x1, $y1, $x2, $y2, $color_blue);
            
            // Zeichne helleren Balken (Highlight-Effekt)
            imagefilledrectangle($image, $x1 + 1, $y1 + 1, $x2 - 1, $y2 - 1, $color_light_blue);
        }
        
        // Speichere die Grafik als PNG
        if (!imagepng($image, GRAPH_FILE)) {
            throw new Exception("Konnte PNG-Datei nicht speichern: " . GRAPH_FILE);
        }
        
        // Gebe den Speicher frei
        imagedestroy($image);
    }
    
    // ========================================================================
    // SUCCESS
    // ========================================================================
    
    // Optional: Gebe eine Erfolgsmeldung aus (für Debugging/Logging)
    // echo "OK: $watt_value Watt - Grafik aktualisiert\n";
    
} catch (Exception $e) {
    // Fehlerbehandlung
    logError("Fehler in stromtabelle1.php", $e);
    
    // Optional: Fehler per E-Mail benachrichtigen (bei Production)
    // mail('admin@example.com', 'Stromzähler Fehler', $e->getMessage());
    
    // Gib den Fehler aus (nur wenn direkt aufgerufen, nicht beim Cron)
    if (php_sapi_name() === 'cli') {
        echo "FEHLER: " . $e->getMessage() . "\n";
    }
    
    exit(1);
}
?>
