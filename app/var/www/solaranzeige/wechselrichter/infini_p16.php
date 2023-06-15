#!/usr/bin/php
<?php
/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2020]  [Ulrich Kunz]
//
//  Dieses Programm ist freie Software. Sie können es unter den Bedingungen
//  der GNU General Public License, wie von der Free Software Foundation
//  veröffentlicht, weitergeben und/oder modifizieren, entweder gemäß
//  Version 3 der Lizenz oder (nach Ihrer Option) jeder späteren Version.
//
//  Die Veröffentlichung dieses Programms erfolgt in der Hoffnung, daß es
//  Ihnen von Nutzen sein wird, aber OHNE IRGENDEINE GARANTIE, sogar ohne
//  die implizite Garantie der MARKTREIFE oder der VERWENDBARKEIT FÜR EINEN
//  BESTIMMTEN ZWECK. Details finden Sie in der GNU General Public License.
//
//  Ein original Exemplar der GNU General Public License finden Sie hier:
//  http://www.gnu.org/licenses/
//
//  Dies ist ein Programmteil des Programms "Solaranzeige"
//
//  Es dient dem Auslesen des Infinity Wechselrichters mit P16 Protokoll
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//  Protokoll Pl16
//
//  Achtung! Der Regler sendet zwischendurch immer wieder asynchrone Daten!
//
*****************************************************************************/
$path_parts = pathinfo($argv[0]);
$Pfad = $path_parts['dirname'];
if (!is_file($Pfad."/1.user.config.php")) {
  // Handelt es sich um ein Multi Regler System?
  require($Pfad."/user.config.php");
}
require_once($Pfad."/phpinc/funktionen.inc.php");
$Tracelevel = 7;  //  1 bis 10  10 = Debug
$Device = "WR";   // WR = Wechselrichter

if (!isset($funktionen)) {
  $funktionen = new funktionen();
}

$Version = "";
$RemoteDaten = true;
$Start = time();  // Timestamp festhalten
$funktionen->log_schreiben("-----------   Start  infini_p16.php   ------------------------ ","|--",6);

$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");

// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}


//  Hardware Version ermitteln.
$Teile =  explode(" ",$Platine);
if ($Teile[1] == "Pi") {
  $Version = trim($Teile[2]);
  if ($Teile[3] == "Model") {
    $Version .= trim($Teile[4]);
    if ($Teile[5] == "Plus") {
      $Version .= trim($Teile[5]);
    }
  }
}
$funktionen->log_schreiben("Hardware Version: ".$Version,"o  ",8);

switch($Version) {
  case "2B":
  break;
  case "3B":
  break;
  case "3BPlus":
  break;
  case "4B":
  break;
  default:
  break;
}




/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//
*****************************************************************************/
$StatusFile = $Pfad."/database/".$GeraeteNummer.".WhProTag_ax.txt";
if (file_exists($StatusFile)) {
  /***************************************************************************
  //  Daten einlesen ...
  ***************************************************************************/
  $aktuelleDaten["WattstundenGesamtHeute"] = file_get_contents($StatusFile);
  $aktuelleDaten["WattstundenGesamtHeute"] = round($aktuelleDaten["WattstundenGesamtHeute"],2);
  $funktionen->log_schreiben("WattstundenGesamtHeute: ".$aktuelleDaten["WattstundenGesamtHeute"],"   ",8);
  if (empty($aktuelleDaten["WattstundenGesamtHeute"])){
      $aktuelleDaten["WattstundenGesamtHeute"] = 0;
  }
  if (date("H:i") == "00:00" or date("H:i") == "00:01") {   // Jede Nacht 0 Uhr
    $aktuelleDaten["WattstundenGesamtHeute"] = 0;       //  Tageszähler löschen
    $rc = file_put_contents($StatusFile,"0");
    $funktionen->log_schreiben("WattstundenGesamtHeute gelöscht.","    ",5);
  }
}
else {
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;
  /***************************************************************************
  //  Inhalt der Status Datei anlegen.
  ***************************************************************************/
  $rc = file_put_contents($StatusFile,"0");
  if ($rc === false) {
    $funktionen->log_schreiben("Konnte die Datei kwhProTag_ax.txt nicht anlegen.","   ",5);
  }
}

//  Nach em Öffnen des Port muss sofort der Regler ausgelesen werden, sonst
//  sendet er asynchrone Daten!
$USB = $funktionen->openUSB($USBRegler);
if (!is_resource($USB)) {
  $funktionen->log_schreiben("USB Port kann nicht geöffnet werden. [1]","XX ",7);
  $funktionen->log_schreiben("Exit.... ","XX ",7);
  goto Ausgang;
}

stream_set_blocking ( $USB , false );

/************************************************************************************
//  Sollen Befehle an den Wechselrichter gesendet werden?
//  Befehle können noch nict an den Wechselrichter gesendet werden!
//  Das muss erst noch implementiert werden!
************************************************************************************/

/*******************************************************************************
//
//  Befehle senden Ende
//
//  Hier beginnt das Auslesen der Daten
//
*******************************************************************************/

$i = 1;
do {

  $Wert = false;
  $Antwort = "";
  $rc = fgets($USB,4096); // Alte Daten löschen
  fputs ($USB, "\r");
  usleep(1000);

  fputs ($USB, "QPI\r");
  usleep(30000);       //  [normal 30000] Es dauert etwas, bis die ersten Daten kommen ...

  for ($k = 1 ; $k < 200; $k++) {
    $rc = fgets($USB,4096); // 4096
    usleep(10000);
    $Antwort .= trim($rc,"\0");

    if (substr($Antwort,-1) == "\r" and substr($Antwort,0,1) == "(") {
      if (substr($Antwort,1,3) == "NAK") {
        $Wert = false;
        $funktionen->log_schreiben("Wechselrichter Antwortet mit NAK!","    ",5);
      }
      else {
        $aktuelleDaten["Protokoll"] = substr($Antwort,3,2);
        $funktionen->log_schreiben("Protokoll: ".substr($Antwort,3,2),"    ",8);
        $Wert = true;
      }
      $rc = "";
      break;
    }
  }
  if ($Wert === false) {
    $funktionen->log_schreiben("Datenübertragung vom Wechselrichter war erfolglos!","    ",9);
    $rc = "";
  }

  // echo $k." ".$Antwort."\n";

  fputs ($USB, "QMOD\r");
  usleep(50000);       //  [normal 50000] Es dauert etwas, bis die ersten Daten kommen ...
  $Antwort = "";

  for ($k = 1 ; $k < 200; $k++) {
    $rc = fgets($USB,4096); // 4096
    usleep(50000);
    $Antwort .= trim($rc,"\0");

    if (substr($Antwort,-1) == "\r" and substr($Antwort,0,1) == "(") {
      if (substr($Antwort,1,3) == "NAK") {
        $Wert = false;
      }
      else {
        $Wert = true;
      }
      $rc = "";
      break;
    }
  }
  if ($Wert === false) {
    $funktionen->log_schreiben("Datenübertragung vom Wechselrichter war erfolglos! [Status]","    ",9);
    $rc = "";
  }



  if ($Wert === true) {
    $aktuelleDaten["Modus"] = substr($Antwort,1,1);
    $funktionen->log_schreiben("Modus: ".substr($Antwort,1,1),"    ",8);
    switch($aktuelleDaten["Modus"]) {
      case "P":
        $aktuelleDaten["DeviceStatus"] = 1;
      break;
      case "S":
        $aktuelleDaten["DeviceStatus"] = 2;
      break;
      case "B":
        $aktuelleDaten["DeviceStatus"] = 3;
      break;
      case "L":
        $aktuelleDaten["DeviceStatus"] = 4;
      break;
      case "F":
        $aktuelleDaten["DeviceStatus"] = 5;
      break;
      case "H":
        $aktuelleDaten["DeviceStatus"] = 6;
      break;
      case "Y":
        $aktuelleDaten["DeviceStatus"] = 7;
      break;
      case "T":
        $aktuelleDaten["DeviceStatus"] = 8;
      break;
      case "D":
        $aktuelleDaten["DeviceStatus"] = 9;
      break;
      case "G":
        $aktuelleDaten["DeviceStatus"] = 10;
      break;
      case "C":
        $aktuelleDaten["DeviceStatus"] = 11;
      break;
      default:
        $aktuelleDaten["DeviceStatus"] = 0;
      break;
    }
  }


  // echo $k." ".$Antwort."\n";

  $Wert = false;
  $Antwort = "";
  // Warnungen
  $CRC = $funktionen->hex2str(dechex($funktionen->CRC16Normal("QPIWS")));
  fputs ($USB, "QPIWS\r");
  usleep(50000);       //  [normal 50000] Es dauert etwas, bis die ersten Daten kommen ...

  for ($k = 1 ; $k < 200; $k++) {
    $rc = fgets($USB,4096); // 4096
    usleep(50000);
    $Antwort .= trim($rc,"\0");

    if (substr($Antwort,-1) == "\r" and substr($Antwort,0,1) == "(") {
      if (substr($Antwort,1,3) == "NAK") {
        $Wert = false;
      }
      else {
        $Wert = true;
      }
      $rc = "";
      break;
    }
  }
  if ($Wert === false) {
    $funktionen->log_schreiben("Datenübertragung vom Wechselrichter war erfolglos! [Warnungen]","    ",9);
    $rc = "";
  }
  if ($Wert === true) {
    $aktuelleDaten["Warnungen"] = substr($Antwort,1,32);
    $funktionen->log_schreiben("Warnungen: ".substr($Antwort,1,32),"    ",8);
    $aktuelleDaten["Fehlermeldung"] = "";

    if (substr($aktuelleDaten["Warnungen"],0,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "PV fail";
    }
    if (substr($aktuelleDaten["Warnungen"],1,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Auto adjust processing";
    }
    if (substr($aktuelleDaten["Warnungen"],2,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "External Flash fail";
    }
    if (substr($aktuelleDaten["Warnungen"],3,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "PV loss";
    }
    if (substr($aktuelleDaten["Warnungen"],4,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "PV low";
    }
    if (substr($aktuelleDaten["Warnungen"],5,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Islanding dedect";
    }
    if (substr($aktuelleDaten["Warnungen"],6,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Initial fail";
    }
    if (substr($aktuelleDaten["Warnungen"],7,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Grid voltage high loss";
    }
    if (substr($aktuelleDaten["Warnungen"],8,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Grid voltage low loss";
    }
    if (substr($aktuelleDaten["Warnungen"],9,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Grid frequency high loss";
    }
    if (substr($aktuelleDaten["Warnungen"],10,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Grid frequency low loss";
    }
    if (substr($aktuelleDaten["Warnungen"],11,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Feeding average voltage over";
    }
    if (substr($aktuelleDaten["Warnungen"],12,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "get energy from grid";
    }
    if (substr($aktuelleDaten["Warnungen"],13,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Grid fault";
    }
    if (substr($aktuelleDaten["Warnungen"],14,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Battery under";
    }
    if (substr($aktuelleDaten["Warnungen"],15,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Batterie low";
    }
    if (substr($aktuelleDaten["Warnungen"],16,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Batterie open";
    }
    if (substr($aktuelleDaten["Warnungen"],17,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Batterie discharge low";
    }
    if (substr($aktuelleDaten["Warnungen"],18,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Over load";
    }
    if (substr($aktuelleDaten["Warnungen"],19,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "EPO ative";
    }
    if (substr($aktuelleDaten["Warnungen"],20,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "PV1 loss";
    }
    if (substr($aktuelleDaten["Warnungen"],21,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "PV2 loss";
    }
    if (substr($aktuelleDaten["Warnungen"],22,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Over temperature";
    }
    if (substr($aktuelleDaten["Warnungen"],23,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Ground loss";
    }
    if (substr($aktuelleDaten["Warnungen"],24,1) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Fan lock";
    }

  }


  // echo $k." ".$Antwort."\n";


  $Wert = false;
  $Antwort = "";
  fputs ($USB, "\0");
  $rc = fgets($USB,4096); // Alte Daten löschen
  fputs ($USB, "QMD\r");
  usleep(10000);       //  [normal 10000] Es dauert etwas, bis die ersten Daten kommen ...

  for ($k = 1 ; $k < 200; $k++) {
    $rc = fgets($USB,4096); // 4096
    usleep(10000);  // Die Effekta Geraete sind so langsam...
    $Antwort .= trim($rc,"\0");

    if (substr($Antwort,-1) == "\r" and substr($Antwort,0,1) == "(") {
      if (substr($Antwort,1,3) == "NAK") {
        $Wert = false;
        $Antwort = substr($Antwort,0,4); // Den CRC abschneiden
      }
      else {
        $Wert = true;
      }
      $rc = "";
      break;
    }
  }
  if ($Wert === false) {
    $funktionen->log_schreiben("Datenübertragung vom Wechselrichter war erfolglos! [Modell]","    ",9);
    $rc = "";
  }

  $funktionen->log_schreiben(substr($Antwort,1,45)."  i: ".$k,"    ",10);


  $Wert = false;
  $Antwort = "";
  fputs ($USB, "\0");
  $rc = fgets($USB,4096); // Alte Daten löschen
  fputs ($USB, "QPIRI\r");
  usleep(10000);       //  [normal 10000] Es dauert etwas, bis die ersten Daten kommen ...

  for ($k = 1 ; $k < 200; $k++) {
    $rc = fgets($USB,4096); // 4096
    usleep(10000);  // Die Effekta Geraete sind so langsam...
    $Antwort .= trim($rc,"\0");

    if (substr($Antwort,-1) == "\r" and substr($Antwort,0,1) == "(") {
      if (substr($Antwort,1,3) == "NAK") {
        $Wert = false;
        $Antwort = substr($Antwort,0,4); // Den CRC abschneiden
      }
      else {
        $Wert = true;
      }
      $rc = "";
      break;
    }
  }
  if ($Wert === false) {
    $funktionen->log_schreiben("Datenübertragung vom Wechselrichter war erfolglos! [Standardwerte]","    ",9);
    $rc = "";
  }

  $funktionen->log_schreiben(substr($Antwort,1,45)."  i: ".$k,"    ",10);


  if ($Wert === true and strlen($Antwort) > 45) {

    $Teile = explode(" ",substr($Antwort,1,45));

    $aktuelleDaten["AnzahlMPPT"] = $Teile[7];
  }


  // echo $k." ".$Antwort."\n";

  $Wert = false;
  $Antwort = "";
  fputs ($USB, "\0");
  $rc = fgets($USB,4096); // Alte Daten löschen
  fputs ($USB, "QET\r");
  usleep(10000);       //  [normal 10000] Es dauert etwas, bis die ersten Daten kommen ...

  for ($k = 1 ; $k < 200; $k++) {
    $rc = fgets($USB,4096); // 4096
    usleep(10000);  // Die Effekta Geraete sind so langsam...
    $Antwort .= trim($rc,"\0");

    if (substr($Antwort,-1) == "\r" and substr($Antwort,0,1) == "(") {
      if (substr($Antwort,1,3) == "NAK") {
        $Wert = false;
        $Antwort = substr($Antwort,0,4); // Den CRC abschneiden
      }
      else {
        $Wert = true;
      }
      $rc = "";
      break;
    }
  }
  if ($Wert === false) {
    $funktionen->log_schreiben("Datenübertragung vom Wechselrichter war erfolglos! [Gesamte Energie]","    ",9);
    $rc = "";
  }
  else {
    $funktionen->log_schreiben(substr($Antwort,1,8)."  i: ".$k,"    ",10);
    $aktuelleDaten["KiloWattstundenTotal"] = (substr($Antwort,1,8)*1000);
  }

  // echo $k." ".$Antwort."\n";


  $Wert = false;
  $Antwort = "";
  fputs ($USB, "\0");
  $rc = fgets($USB,4096); // Alte Daten löschen
  $CRC = $funktionen->onebytechecksum("QEY".date("Y"));

  fputs ($USB, "QEY".date("Y"));
  usleep(10000);
  fputs ($USB, $CRC."\r");
  usleep(10000);       //  [normal 10000] Es dauert etwas, bis die ersten Daten kommen ...

  for ($k = 1 ; $k < 200; $k++) {
    $rc = fgets($USB,4096); // 4096
    usleep(10000);  // Die Effekta Geraete sind so langsam...
    $Antwort .= trim($rc,"\0");

    if (substr($Antwort,-1) == "\r" and substr($Antwort,0,1) == "(") {
      if (substr($Antwort,1,3) == "NAK") {
        $Wert = false;
        $Antwort = substr($Antwort,0,4); // Den CRC abschneiden
      }
      else {
        $Wert = true;
      }
      $rc = "";
      break;
    }
  }
  if ($Wert === false) {
    $funktionen->log_schreiben("Datenübertragung vom Wechselrichter war erfolglos! [Gesamte Energie]","    ",9);
    $rc = "";
  }

  $funktionen->log_schreiben("Gesamt Energie für ".date("Y").": ".substr($Antwort,1,8)."  i: ".$k,"    ",10);


  // Daten
  $Wert = false;
  $Antwort = "";
  fputs ($USB, "\0");
  $rc = fgets($USB,4096); // Alte Daten löschen
  fputs ($USB, "QPIGS\r");
  usleep(10000);       //  [normal 10000] Es dauert etwas, bis die ersten Daten kommen ...

  for ($k = 1 ; $k < 200; $k++) {
    $rc = fgets($USB,4096); // 4096
    usleep(10000);  // Die Effekta Geraete sind so langsam...
    $Antwort .= trim($rc,"\0");

    if (substr($Antwort,-1) == "\r" and substr($Antwort,0,1) == "(") {
      if (substr($Antwort,1,3) == "NAK") {
        $Wert = false;
        $Antwort = substr($Antwort,0,4); // Den CRC abschneiden
      }
      else {
        $Wert = true;
      }
      $rc = "";
      break;
    }
  }
  if ($Wert === false) {
    $funktionen->log_schreiben("Datenübertragung vom Wechselrichter war erfolglos! [Daten]","    ",9);
    $rc = "";
  }

  $funktionen->log_schreiben(substr($Antwort,1,132)."  i: ".$k,"    ",8);

  if ($Wert === true and strlen($Antwort) > 132) {

    $Teile = explode(" ",substr($Antwort,1,132));

    if (is_numeric($Teile[0]))
      $aktuelleDaten["Netzspannung"] = $Teile[0];
    else
      $aktuelleDaten["Netzspannung"] = 0;

    if (is_numeric($Teile[1]))
      if (substr($Teile[1],0,1) == "1") {
        $aktuelleDaten["Netzleistung"] = "-".substr($Teile[1],1);
      }
      else {
        $aktuelleDaten["Netzleistung"] = $Teile[1];
      }
    else
      $aktuelleDaten["Netzleistung"] = 0;

    if (is_numeric($Teile[2]))
      $aktuelleDaten["Netzfrequenz"] = $Teile[2];
    else
      $aktuelleDaten["Netzfrequenz"] = 0;


    if (substr($Teile[3],0,1) == "1") {
      $aktuelleDaten["Netzstrom"] = "-".substr($Teile[3],1);
    }
    else {
      $aktuelleDaten["Netzstrom"] = $Teile[3];
    }

    $aktuelleDaten["AC_Ausgangsspannung"] = $Teile[4];
    $aktuelleDaten["AC_Ausgangsleistung"] = $Teile[5];
    $aktuelleDaten["AC_Ausgangsfrequenz"] = $Teile[6];
    $aktuelleDaten["AC_Ausgangsstrom"] = $Teile[7];
    $aktuelleDaten["AC_Ausgangslast"] = $Teile[8];
    $aktuelleDaten["Batteriespannung"] = $Teile[11];
    $aktuelleDaten["Batteriekapazitaet"] = $Teile[13];
    if (is_numeric($Teile[14]))
      $aktuelleDaten["Solarleistung1"] = $Teile[14];
    else
      $aktuelleDaten["Solarleistung1"] = 0;
    if (is_numeric($Teile[15]))
      $aktuelleDaten["Solarleistung2"] = $Teile[15];
    else
      $aktuelleDaten["Solarleistung2"] = 0;
    if (is_numeric($Teile[16]))
      $aktuelleDaten["Solarleistung3"] = $Teile[16];
    else
      $aktuelleDaten["Solarleistung3"] = 0;

    if (is_numeric($Teile[17]))
      $aktuelleDaten["Solarspannung1"] = $Teile[17];
    else
      $aktuelleDaten["Solarspannung1"] = 0;

    if (is_numeric($Teile[18]))
    $aktuelleDaten["Solarspannung2"] = $Teile[18];
    else
      $aktuelleDaten["Solarspannung2"] = 0;
    if (is_numeric($Teile[19]))
    $aktuelleDaten["Solarspannung3"] = $Teile[19];
    else
      $aktuelleDaten["Solarspannung3"] = 0;

    $aktuelleDaten["Temperatur"] = $Teile[20];
    $aktuelleDaten["Geraetestatus"] = $Teile[21];


    if (substr($aktuelleDaten["Geraetestatus"],5,1) == 0 and substr($aktuelleDaten["Geraetestatus"],6,1) == 0) {
      $aktuelleDaten["Laden_Entladen"] = 2;
    }
    // 1 = Laden    0 = Entladen   2 = unbekannt
    $aktuelleDaten["Laden_Entladen"] = substr($aktuelleDaten["Geraetestatus"],6,1);

    if (substr($aktuelleDaten["Geraetestatus"],8,1) == 0 and substr($aktuelleDaten["Geraetestatus"],9,1) == 0) {
      $aktuelleDaten["Bezug_Einspeisung"] = 2;
    }
    // 1 = Bezug   0 = Einspeisung  2 = unbekannt
    $aktuelleDaten["Bezug_Einspeisung"] = substr($aktuelleDaten["Geraetestatus"],9,1);



    if (isset($aktuelleDaten["Fehlermeldung"])) {
      $funktionen->log_schreiben("Fehlermeldung: ".$aktuelleDaten["Fehlermeldung"],"   ",1);
    }

    if ($aktuelleDaten["AnzahlMPPT"] == 1) {
      $aktuelleDaten["Solarleistung"] =  $aktuelleDaten["Solarleistung1"];
      $aktuelleDaten["Batterieleistung"] =  $aktuelleDaten["Solarleistung2"];
    }
    elseif ($aktuelleDaten["AnzahlMPPT"] == 2) {
      $aktuelleDaten["Solarleistung"] =  $aktuelleDaten["Solarleistung1"] + $aktuelleDaten["Solarleistung2"];
    }
    else {
      $aktuelleDaten["Solarleistung"] =  $aktuelleDaten["Solarleistung1"] + $aktuelleDaten["Solarleistung2"] + $aktuelleDaten["Solarleistung3"];
    }




    /****************************************************************************
    //  Die Daten werden für die Speicherung vorbereitet.
    ****************************************************************************/
    $aktuelleDaten["Regler"] = $Regler;
    $aktuelleDaten["Objekt"] = $Objekt;
    $aktuelleDaten["Firmware"] = 0;
    $aktuelleDaten["Produkt"] = "Protokoll 16";
    $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


    $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ",8);


    /**************************************************************************
    //  User PHP Script, falls gewünscht oder nötig
    **************************************************************************/
    if ( file_exists ("/var/www/html/infini_p16_math.php")) {
      include 'infini_p16_math.php';  // Falls etwas neu berechnet werden muss.
    }



    /**************************************************************************
    //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
    //  an den mqtt-Broker Mosquitto gesendet.
    //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
    **************************************************************************/
    if ($MQTT and strtoupper($MQTTAuswahl) != "OPENWB") {
      $funktionen->log_schreiben("MQTT Daten zum [ $MQTTBroker ] senden.","   ",1);
      require($Pfad."/mqtt_senden.php");
    }

    /****************************************************************************
    //  Zeit und Datum
    ****************************************************************************/
    //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
    $aktuelleDaten["Timestamp"] = time();
    $aktuelleDaten["Monat"]     = date("n");
    $aktuelleDaten["Woche"]     = date("W");
    $aktuelleDaten["Wochentag"] = strftime("%A",time());
    $aktuelleDaten["Datum"]     = date("d.m.Y");
    $aktuelleDaten["Uhrzeit"]   = date("H:i:s");


    /****************************************************************************
    //  InfluxDB  Zugangsdaten ...stehen in der user.config.php
    //  falls nicht, sind das hier die default Werte.
    ****************************************************************************/
    $aktuelleDaten["InfluxAdresse"] = $InfluxAdresse;
    $aktuelleDaten["InfluxPort"] =  $InfluxPort;
    $aktuelleDaten["InfluxUser"] =  $InfluxUser;
    $aktuelleDaten["InfluxPassword"] = $InfluxPassword;
    $aktuelleDaten["InfluxDBName"] = $InfluxDBName;
    $aktuelleDaten["InfluxDaylight"] = $InfluxDaylight;
    $aktuelleDaten["InfluxDBLokal"] = $InfluxDBLokal;
    $aktuelleDaten["InfluxSSL"] = $InfluxSSL;
    $aktuelleDaten["Demodaten"] = false;

    /*********************************************************************
    //  Daten werden in die Influx Datenbank gespeichert.
    //  Lokal und Remote bei Bedarf.
    *********************************************************************/
    if ($InfluxDB_remote) {
      // Test ob die Remote Verbindung zur Verfügung steht.
      if ($RemoteDaten) {
        $rc = $funktionen->influx_remote_test();
        if ($rc) {
          $rc = $funktionen->influx_remote($aktuelleDaten);
          if ($rc) {
            $RemoteDaten = false;
          }
        }
        else {
          $RemoteDaten = false;
        }
      }
      if ($InfluxDB_local) {
        $rc = $funktionen->influx_local($aktuelleDaten);
      }
    }
    else {
      $rc = $funktionen->influx_local($aktuelleDaten);
    }

  }

  if (is_file($Pfad."/1.user.config.php")) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (7 - (time() - $Start));
    $funktionen->log_schreiben("Multi-Regler-Ausgang. ".$Zeitspanne,"   ",2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    $funktionen->log_schreiben("Schleife: ".($i)." Zeitspanne: ".(floor((56 - (time() - $Start))/($Wiederholungen-$i+1))),"   ",9);
    sleep(floor((56 - (time() - $Start))/($Wiederholungen-$i+1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
      $funktionen->log_schreiben("Schleife ".$i." Ausgang...","   ",8);
      break;
  }

  $i++;
} while (($Start + 56) > time());

stream_set_blocking ( $USB , true );


if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {

  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $funktionen->log_schreiben("Daten werden zur HomeMatic übertragen...","   ",8);
    require($Pfad."/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    $funktionen->log_schreiben("Nachrichten versenden...","   ",8);
    require($Pfad."/meldungen_senden.php");
  }

  $funktionen->log_schreiben("OK. Datenübertragung erfolgreich.","   ",7);

}
else {
  $funktionen->log_schreiben("Keine gültigen Daten empfangen.","!! ",6);
}

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//  Leistung in Watt / 60 Minuten, da 60 mal in der Stunde addiert wird.
*****************************************************************************/
if (file_exists($StatusFile) and isset($aktuelleDaten["Solarleistung"])) {
  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $whProTag = file_get_contents($StatusFile);
  // aktuellen Wert in die Datei schreiben:
  $whProTag = ($whProTag + ($aktuelleDaten["Solarleistung"]/60));
  $rc = file_put_contents($StatusFile,$whProTag);
  $funktionen->log_schreiben("WattstundenGesamtHeute: ".round($whProTag,2),"   ",5);
}


Ausgang:

$funktionen->log_schreiben("-----------   Stop   infini_p16.php    ----------------------- ","|--",6);

return;



?>