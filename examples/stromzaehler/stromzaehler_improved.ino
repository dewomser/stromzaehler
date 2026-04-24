/**
 * ============================================================================
 * Arduino Stromzähler - Webserver Sketchvariation
 * ============================================================================
 * 
 * Diese Variante ist identisch zu examples_improved.ino
 * Wird in examples/stromzaehler/ verwaltet
 * 
 * @see examples_improved.ino für vollständige Dokumentation
 */

// Bibliotheken einbinden
#include <SPI.h>          // Serial Peripheral Interface für Ethernet Shield
#include <Ethernet.h>     // Ethernet-Bibliothek für W5100 Shield

// ============================================================================
// KONFIGURATION
// ============================================================================

// MAC-Adresse des Ethernet Shields (eindeutig pro Gerät)
byte mac[] = { 0x90, 0xA2, 0xDA, 0x00, 0x6C, 0x8E };

// Statische IP-Adresse im lokalen Netzwerk
IPAddress ip(192, 168, 1, 6);

// S0-Impuls Eingabe-Pin (digitaler Pin 22 am Arduino Mega)
const byte COUNTER_PIN = 22;

// HTTP-Server Port (Standard Web-Port)
const int HTTP_PORT = 80;

// Debouncing Verzögerung in Millisekunden
const unsigned long DEBOUNCE_DELAY = 50;

// ============================================================================
// GLOBALE VARIABLEN
// ============================================================================

// Stromzähler-Messwerte
unsigned long millisBetweenPulses = 0;    // Zeit zwischen zwei Impulsen (ms)
unsigned long lastPulseTime = 0;          // Zeitstempel des letzten Impulses
byte lastPinState = LOW;                  // Letzter bekannter Pin-Status
unsigned long lastDebounceTime = 0;       // Zeit für Debouncing

// Aktuelle Leistung in Watt
// Berechnung: 1800000 ms / Impulsabstand = Watt
unsigned int currentWatt = 0;

// Ethernet Server auf Port 80
EthernetServer server(HTTP_PORT);

// ============================================================================
// SETUP
// ============================================================================

void setup()
{
  // Serielle Schnittstelle für Debug-Ausgaben
  Serial.begin(9600);
  delay(1000);
  
  Serial.println("===============================================");
  Serial.println("Arduino Stromzaehler - Startup");
  Serial.println("===============================================");
  
  // Initialisiere Ethernet mit statischer IP
  Ethernet.begin(mac, ip);
  
  Serial.print("IP-Adresse: ");
  Serial.println(Ethernet.localIP());
  
  // Starte HTTP-Server
  server.begin();
  Serial.println("HTTP-Server aktiv auf Port 80");
  
  // Stromzähler-Eingabe konfigurieren
  pinMode(COUNTER_PIN, INPUT);
  digitalWrite(COUNTER_PIN, LOW);
  
  // Initialisiere Variablen
  millisBetweenPulses = 0;
  lastPulseTime = millis();
  lastPinState = LOW;
  currentWatt = 0;
  
  Serial.println("S0-Stromzaehler auf Pin 22 bereit");
  Serial.println("===============================================\n");
}

// ============================================================================
// LOOP - HAUPTSCHLEIFE
// ============================================================================

void loop()
{
  // =========================================================================
  // Teil 1: Stromzähler-Impulserkennung
  // =========================================================================
  
  byte currentPinState = digitalRead(COUNTER_PIN);
  unsigned long currentTime = millis();
  
  // Erkenne aufsteigende Flanke (LOW → HIGH)
  if (currentPinState == HIGH && lastPinState == LOW) {
    
    // Debouncing: Verhindere Fehler durch Kontaktprellen
    if ((currentTime - lastDebounceTime) > DEBOUNCE_DELAY) {
      
      // Berechne Zeit zwischen Impulsen
      millisBetweenPulses = currentTime - lastPulseTime;
      lastPulseTime = currentTime;
      lastDebounceTime = currentTime;
      
      // Berechne Watt-Wert (nur wenn sinnvoller Zeitabstand)
      if (millisBetweenPulses > 0) {
        currentWatt = 1800000 / millisBetweenPulses;
        
        // Begrenzung auf realistisches Maximum
        if (currentWatt > 99999) currentWatt = 99999;
      }
      
      // Debug-Ausgabe
      Serial.print("Impuls: ");
      Serial.print(currentWatt);
      Serial.print(" W (");
      Serial.print(millisBetweenPulses);
      Serial.println(" ms)");
    }
  }
  
  lastPinState = currentPinState;
  
  // Timeout-Handling (5 Minuten ohne Impuls)
  if ((currentTime - lastPulseTime) > 300000) {
    if (currentWatt != 0) {
      currentWatt = 0;
      Serial.println("Timeout - Watt = 0");
    }
  }
  
  // =========================================================================
  // Teil 2: HTTP-Request verarbeiten
  // =========================================================================
  
  EthernetClient client = server.available();
  
  if (client) {
    Serial.println("Client verbunden");
    
    boolean currentLineIsBlank = true;
    unsigned long clientStartTime = millis();
    
    while (client.connected()) {
      // Timeout (5 Sekunden)
      if ((millis() - clientStartTime) > 5000) {
        break;
      }
      
      if (client.available()) {
        char c = client.read();
        
        // Ende des HTTP-Headers erkannt (leere Zeile)
        if (c == '\n' && currentLineIsBlank) {
          
          // Sende HTTP-Response
          client.println("HTTP/1.1 200 OK");
          client.println("Content-Type: text/plain; charset=UTF-8");
          client.println("Connection: close");
          client.println("Access-Control-Allow-Origin: *");
          client.println();
          
          // Sende den Watt-Wert
          client.print(currentWatt);
          client.println(" Watt");
          
          Serial.print("Response: ");
          Serial.print(currentWatt);
          Serial.println(" Watt");
          
          break;
        }
        
        if (c == '\n') {
          currentLineIsBlank = true;
        }
        else if (c != '\r') {
          currentLineIsBlank = false;
        }
      }
    }
    
    delay(100);
    client.stop();
    Serial.println("Client disconnected\n");
  }
  
  delay(10);
}
