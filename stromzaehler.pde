/*
  Web  Server + Sromzähler
 
 A simple web server that shows the value of a Sromzähler Voltcraft DPM1L32-D.
 using an Arduino Wiznet Ethernet shield. 
 
 Circuit:
 * Ethernet shield attached to pins 10, 11, 12, 13
 * Digital Input =22
 
 created 18 Dec 2009
 by David A. Mellis
 modified 4 Sep 2010
 by Tom Igoe
 
 und das Script von
 http://blog.elektrowolle.de/2011/07/26/s0-messwerterfassung-von-stromzahlern/
 
 Zusammengemixt am 08.02.2012 von Stefan Höhn http://www.untergang.de
  
 */

#include <SPI.h>
#include <Ethernet.h>

// Enter a MAC address and IP address for your controller below.
// The IP address will be dependent on your local network:
byte mac[] = { 0x90 , 0xA2, 0xDA, 0x00, 0x6C, 0x8E };
byte ip[] = { 192,168,1, 6 };

//zähler <-
const byte counterPin = 22;
unsigned long millisBetween;
unsigned long lastMillis;
byte lastState;
// zähler ->

// Initialize the Ethernet server library
// with the IP address and port you want to use 
// (port 80 is default for HTTP):
Server server(80);

void setup()
{
  // start the Ethernet connection and the server:
  Ethernet.begin(mac, ip);
  server.begin();
 // <-- Zähler
       pinMode(counterPin, INPUT);
     digitalWrite(counterPin, LOW);
     millisBetween = 0;
     lastMillis = 0;
     lastState = 0;
  
  Serial.begin(9600);
  // Zähler -->
}

void loop()
{
  //<--Zähler
  
 unsigned char bitMaskToSend = 0;
  unsigned long time = millis();
 
     byte val = digitalRead(counterPin);
     if (val == HIGH && lastState == LOW) {
       millisBetween = time-lastMillis;
       lastMillis = time;
       bitSet(bitMaskToSend,1);
     }
     lastState = val;
 
     unsigned long dataToWrite = millisBetween;
     if (bitRead(bitMaskToSend,1)) {
       Serial.print((char)('A'));
       Serial.println(1800000/dataToWrite);
  
  } 
  
  
  // Zähler-->
  
  
  
  // listen for incoming clients
  Client client = server.available();
  if (client) {
    // an http request ends with a blank line
    boolean currentLineIsBlank = true;
    while (client.connected()) {
      if (client.available()) {
        char c = client.read();
        // if you've gotten to the end of the line (received a newline
        // character) and the line is blank, the http request has ended,
        // so you can send a reply
        if (c == '\n' && currentLineIsBlank) {
          // send a standard http response header
          client.println("HTTP/1.1 200 OK");
          client.println("Content-Type: text/html");
          client.println();
          client.print(1800000/dataToWrite);
          client.print("Watt");   
         break;
        }
        if (c == '\n') {
          // you're starting a new line
          currentLineIsBlank = true;
        } 
        else if (c != '\r') {
          // you've gotten a character on the current line
          currentLineIsBlank = false;
        }
      }
    }
    // give the web browser time to receive the data
    delay(1);
    // close the connection:
    client.stop();
  }
}
