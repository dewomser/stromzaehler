<?php
// Dieses Script funktioniert mit PHP7 und MYSQLi
// Ein Arduino mit Stromzähler, der als Webserver
// dient wird abgefragt um eine PNG-Grafik Stromverbrauch zu erzeugen. 

//Variablen
$ro = 0;
//mysql-daten
$host = "localhost";
$user = "foo";
$passwort = "foo_pw";
$datenbank = "foo";
$tabelle = "watt";

//Datei vom Arduino mit Stromzähler wird ausgelesen
$watt_lesen = file_get_contents("http://welt.untergang.de:81/");
 
$link = mysqli_connect($host, $user, $passwort, $datenbank);
//if (!$link) {
//    die('Verbindung schlug fehl: ' . mysql_error());
//}
// echo 'Erfolgreich verbunden';

 //das Wort Watt wird entfernt
 $watt_ohne_bz = strcspn($watt_lesen,"Watt");
 //echo $watt_ohne_bz;
 $watt_wert = substr($watt_lesen,0,$watt_ohne_bz);

$handle = fopen ("/var/www/gagagag/watt.inc","w");
fwrite ($handle, $watt_wert." Watt");
fclose ($handle);

 // Wert in Tabelle einfügen (ID,Wert,Datum)
 $query = "INSERT INTO `watt` VALUES (0,$watt_wert, NOW())";
 $link->query($query);
 // Diagramm wird erstellt 
 $result = mysqli_query($link, "SELECT id,watt,zeit FROM watt ORDER BY `zeit` DESC LIMIT 0, 10");
// $result = mysqli_query("SELECT id,watt,zeit FROM watt ORDER BY `zeit` DESC LIMIT 0, 10");

 while ($row = $result ->fetch_array( MYSQLI_ASSOC))
 // $row = $result->fetch_array(MYSQLI_ASSOC);
  {
  $datenr[$ro] = ($row["watt"]);
  $zeitro[$ro] = ($row["zeit"]);
  
  $ro++;
  }
 //$datenr: Reihenfolge wird vertauscht wegen Darstellung in Tabelle
 //$daten ist das array was für das Diagramm benutzt wird.
 $daten = array_reverse($datenr);
 $zeiten = array_reverse($zeitro);
mysqli_free_result($result);
mysqli_close($link);

// orginal Diagramm Werte
//$daten=array(10,125,100,238,200,175,100,200,250,225,125);
//print_r($daten);

// PNG-Grafik nur dann definieren wenn dieses PHP-Script ein Bild ist. 
//header("Content-type: image/png");

// Breite/Höhe des Diagramm
$imgBreite=250;
$imgHoehe=250;
$font="/var/www/html/gagagag/arial.ttf";

// Image-Objekt erzeugen und Farben definieren
$bild = imagecreatetruecolor($imgHoehe, $imgBreite);
// $bild=imagecreate($imgHoehe, $imgBreite);
$farbeWeiss=imagecolorallocate($bild, 255, 255, 255);
$farbeGrau=imagecolorallocate($bild, 192, 192, 192);
$farbeBlau=imagecolorallocate($bild, 0, 150, 255);
$farbeHellblau=imagecolorallocate($bild, 0, 200, 255);
$black=imagecolorallocate($bild, 0, 0, 0);

// Rand für die Grafik erzeugen
imagefilledrectangle($bild, 0, 0, $imgHoehe, $imgBreite, $farbeWeiss);

imageline($bild, 0, 0, 0, 250, $farbeGrau);
imageline($bild, 0, 0, 250, 0, $farbeGrau);
imageline($bild, 249, 0, 249, 249, $farbeGrau);
imageline($bild, 0, 249, 249, 249, $farbeGrau);

// Raster erzeugen

imageTTFText($bild, 10, 90, 25, 120, $black,$font,$zeiten[0]);

for ($i=1; $i<count($daten); $i++){
    imageline($bild, $i*25, 0, $i*25, 250, $farbeGrau);
    imageTTFText($bild, 10, 90,($i+1)*25, 120, $black,$font,$zeiten[$i]);
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
// aus dem Speicher entfernen

imagepng($bild, '/var/www/html/gagagag/stromtabelle.png');
//Bild nicht anzeigen

imagedestroy($bild);
?>  
