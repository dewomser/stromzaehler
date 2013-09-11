/variblen
$ro = 0;
//mysql-daten
$host = "rdbms.strato.de";
$user = "user";
$passwort = "pw";
$datenbank = "DB1234";
$tabelle = "watt";

//Datei vom Arduino mit Stromzähler wird ausgelesen
$watt_lesen = file_get_contents("http://untergang.homelinux.net:81");
 
//den Wert in eine Datei schreiben watt.inc
 
$handle = fopen ("/absoluter_Pfad/htdocs/mambo/mosaddphp/watt.inc", w);
fwrite ($handle, $watt_lesen);
fclose ($handle);

$link = mysql_connect($host, $user, $passwort);
//if (!$link) {
//    die('Verbindung schlug fehl: ' . mysql_error());
//}
// echo 'Erfolgreich verbunden';

mysql_select_db($datenbank , $link); 

 //echo $watt_lesen;
 //das Wort Watt wird entfernt
 $watt_ohne_bz = strcspn($watt_lesen,"Watt");
 //echo $watt_ohne_bz;
 $watt_wert = substr($watt_lesen,0,$watt_ohne_bz);
 
 // Wert in Tabelle einfügen (ID,Wert,Datum)
 mysql_query("INSERT INTO `watt` VALUES (0,$watt_wert, NOW())");
  
 // Abfrage Datenbank 
 $result = mysql_query("SELECT id,watt,zeit FROM watt ORDER BY `zeit` DESC LIMIT 0, 10");

 while ($row = mysql_fetch_array($result, MYSQL_ASSOC))
  {
	  // warum 2 getrennte arrays?
  $datenr[$ro] = ($row["watt"]);
  $zeitro[$ro] = ($row["zeit"]);
  
  $ro++;
  }
 //$datenr: Reihenfolge wird vertauscht wegen Darstellung in Tabelle
 //$daten ist das array was für das Diagramm benutzt wird.
 $daten = array_reverse($datenr);
 $zeiten = array_reverse($zeitro);
mysql_free_result($result);
mysql_close($link);

/
// PNG-Grafik definieren
// header("Content-type: image/png");

// Breite/Höhe des Diagramm
$imgBreite=250;
$imgHoehe=250;
//  Schriftart liegt im selben Ordner
$font='arial.ttf';

// Image-Objekt erzeugen und Farben definieren
$bild = imagecreatetruecolor($imgHoehe, $imgBreite);
// $bild=imagecreate($imgHoehe, $imgBreite); ...truecolor wegen Schriftfunktion
$farbeWeiss=imagecolorallocate($bild, 255, 255, 255);
$farbeGrau=imagecolorallocate($bild, 192, 192, 192);
$farbeBlau=imagecolorallocate($bild, 0, 150, 255);
$farbeHellblau=imagecolorallocate($bild, 0, 200, 255);
//schwarz für die Schrift
$black=imagecolorallocate($bild, 0, 0, 0);

imagefilledrectangle($bild, 0, 0, $imgHoehe, $imgBreite, $farbeWeiss);

// Rand für die Grafik erzeugen
imageline($bild, 0, 0, 0, 250, $farbeGrau);
imageline($bild, 0, 0, 250, 0, $farbeGrau);
imageline($bild, 249, 0, 249, 249, $farbeGrau);
imageline($bild, 0, 249, 249, 249, $farbeGrau);


// Raster erzeugen
// Weil der Zähler i$ bei 1 losgeht diese Zeile
imagettftext($bild, 10, 90, 25, 120, $black, $font,$zeiten[0]);

for ($i=1; $i<count($daten); $i++){
    imageline($bild, $i*25, 0, $i*25, 250, $farbeGrau);
    //hier wird das Datum geschrieben ..Parameter 90 = hochkant
    imagettftext($bild, 10, 90,($i+1)*25, 120, $black, $font,$zeiten[$i]);
    imageline($bild, 0, $i*25, 250, $i*25, $farbeGrau);
}

// Liniendiagramm erzeugen
//for ($i=0; $i<count($daten); $i++){
//imageline($bild, $i*25, (250-$daten[$i]),
//    ($i+1)*25, (250-$daten[$i+1]), $farbeBlau);
//}


// Säulendiagramme erzeugen
for ($i=0; $i<count($daten); $i++){
    imagefilledrectangle($bild, $i*25, (250-$daten[$i]),
    ($i+1)*25, 250, $farbeBlau);
    imagefilledrectangle($bild, ($i*25)+1,
    (250-$daten[$i])+1,
    (($i+1)*25)-5, 248, $farbeHellblau);
}
// Diagramm ausgeben und Grafik
imagepng($bild, 'watttabelle.png');
// aus dem Speicher entfernen
imagedestroy($bild);

//  cronscript:
// /bin/php ./dawosist/watt2.php ; cd  ./mambo/mosaddphp/ ; date +'%H:%M Uhr am %d.%m.%Y' >watt-date.inc
?>  
