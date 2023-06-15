#!/usr/bin/php
<?php
/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2015-2016]  [Ulrich Kunz]
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
//  Es dient dem Auslesen des Victron-energy Reglers über die USB Schnittstelle
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
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
if (!isset($funktionen)) {
  $funktionen = new funktionen();
}
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}

$Tracelevel = 7;  //  1 bis 10  10 = Debug
$Device = "WR"; // WR = Wechselrichter
$Reglermodelle = array("0300","A042","A043","A04C","A053","A054","A055");
$Version = ""; 
$Start = time();  // Timestamp festhalten
$funktionen->log_schreiben("---------   Start  victron_phoenix.php   ----------------- ","|--",6);

$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");
$RemoteDaten = true;



/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//
*****************************************************************************/
$StatusFile = $Pfad."/database/".$GeraeteNummer.".WhProTag.txt";
if (file_exists($StatusFile)) {
  /***************************************************************************
  //  Daten einlesen ...
  ***************************************************************************/
  $aktuelleDaten["WattstundenGesamtHeute"] = file_get_contents($StatusFile);
  $funktionen->log_schreiben("WattstundenGesamtHeute: ".round($aktuelleDaten["WattstundenGesamtHeute"],2),"   ",8);
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
  /***************************************************************************
  //  Inhalt der Status Datei anlegen.
  ***************************************************************************/
  $rc = file_put_contents($StatusFile,"0");
  if ($rc === false) {
    $funktionen->log_schreiben("Konnte die Datei whProTag_pho.txt nicht anlegen.","XX ",5);
  }
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


//  Nach em Öffnen des Port muss sofort der Regler ausgelesen werden, sonst
//  sendet er asynchrone Daten!
$USB1 = $funktionen->openUSB($USBRegler);
if (!is_resource($USB1)) {
  $funktionen->log_schreiben("USB Port kann nicht geöffnet werden. [1]","XX ",7);
  $funktionen->log_schreiben("Exit.... ","XX ",7);
  goto Ausgang;
}

$i = 1;
do {
  $funktionen->log_schreiben("Die Daten werden ausgelesen...","+  ",9);

  /****************************************************************************
  //  Ab hier wird der Regler ausgelesen.
  //
  //  Ergebniswerte:
  //  $aktuelleDaten["Firmware"]
  //  $aktuelleDaten["Produkt"]
  //  $aktuelleDaten["Batteriespannung"]
  //  $aktuelleDaten["AC_Spannung"]
  //  $aktuelleDaten["AC_Strom"]
  //  $aktuelleDaten["Mode"]
  //  $aktuelleDaten["Warnungen"]
  //
  //
  ****************************************************************************/

  $Befehl = "1";  // Firmware
  $rc = $funktionen->ve_regler_auslesen($USB1,":".$Befehl.$funktionen->VE_CRC($Befehl));
  if ($funktionen->VE_CRC(substr(trim($rc),1,-2)) != substr(trim($rc),-2)) {
    //  Hier kann es vorkommen, dass der Regler zwischendurch asynchrone 
    //  Daten sendet.
    $funktionen->log_schreiben("Firmware".trim($rc),"!!  ",9);
    $m++;
    if ($m > 2) {
      break;
    }
    continue;  // Fehler beim Auslesen aufgetreten. Nochmal...

  }
  $aktuelleDaten = array_merge($aktuelleDaten,$funktionen->ve_ergebnis_auswerten($rc));

  $Befehl = "4";  // Produkt
  $rc = $funktionen->ve_regler_auslesen($USB1,":".$Befehl.$funktionen->VE_CRC($Befehl));
  if ($funktionen->VE_CRC(substr(trim($rc),1,-2)) != substr(trim($rc),-2)) {
    $funktionen->log_schreiben("Produkt".trim($rc),"!!  ",5);
    continue;  // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge($aktuelleDaten,$funktionen->ve_ergebnis_auswerten($rc));


  $Befehl = "7000200";  //  Mode
  $rc = $funktionen->ve_regler_auslesen($USB1,":".$Befehl.$funktionen->VE_CRC($Befehl));
  if ($funktionen->VE_CRC(substr(trim($rc),1,-2)) != substr(trim($rc),-2)) {
    $funktionen->log_schreiben("Optionen".trim($rc),"!!  ",5);
    continue;  // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge($aktuelleDaten,$funktionen->ve_ergebnis_auswerten($rc));
  $funktionen->log_schreiben("Mode:".trim($rc),"!!  ",9);


  $Befehl = "71C0300";  //  Warnungen
  $rc = $funktionen->ve_regler_auslesen($USB1,":".$Befehl.$funktionen->VE_CRC($Befehl));
  if ($funktionen->VE_CRC(substr(trim($rc),1,-2)) != substr(trim($rc),-2)) {
    $funktionen->log_schreiben("Optionen".trim($rc),"!!  ",5);
    continue;  // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge($aktuelleDaten,$funktionen->ve_ergebnis_auswerten($rc));
  $funktionen->log_schreiben("Mode:".trim($rc),"!!  ",9);


  $Befehl = "78DED00";  //  Batteriespannung
  $rc = $funktionen->ve_regler_auslesen($USB1,":".$Befehl.$funktionen->VE_CRC($Befehl));
  if ($funktionen->VE_CRC(substr(trim($rc),1,-2)) != substr(trim($rc),-2)) {
    $funktionen->log_schreiben("Batteriespannung".trim($rc),"!!  ",5);
    continue;  // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge($aktuelleDaten,$funktionen->ve_ergebnis_auswerten($rc));
  $funktionen->log_schreiben("Mode:".trim($rc),"!!  ",9);

  $Befehl = "7002200";  //  AC_Spannung
  $rc = $funktionen->ve_regler_auslesen($USB1,":".$Befehl.$funktionen->VE_CRC($Befehl));
  if ($funktionen->VE_CRC(substr(trim($rc),1,-2)) != substr(trim($rc),-2)) {
    $funktionen->log_schreiben("Batteriespannung".trim($rc),"!!  ",5);
    continue;  // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge($aktuelleDaten,$funktionen->ve_ergebnis_auswerten($rc));
  $funktionen->log_schreiben("Mode:".trim($rc),"!!  ",9);

  $Befehl = "7012200";  //  AC_Strom
  $rc = $funktionen->ve_regler_auslesen($USB1,":".$Befehl.$funktionen->VE_CRC($Befehl));
  if ($funktionen->VE_CRC(substr(trim($rc),1,-2)) != substr(trim($rc),-2)) {
    $funktionen->log_schreiben("Batteriespannung".trim($rc),"!!  ",5);
    continue;  // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge($aktuelleDaten,$funktionen->ve_ergebnis_auswerten($rc));
  $funktionen->log_schreiben("Mode:".trim($rc),"!!  ",9);


  $aktuelleDaten["AC_Leistung"] = $aktuelleDaten["AC_Ausgangsspannung"]*$aktuelleDaten["AC_Ausgangsstrom"];


  $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ",9);



  /**************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  **************************************************************************/
  $FehlermeldungText = "";

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);

  $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists ("/var/www/html/phoenix_victron_math.php")) {
    include 'phoenix_victron_math.php';  // Falls etwas neu berechnet werden muss.
  }


  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT) {
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
  $aktuelleDaten["Uhrzeit"]      = date("H:i:s");


  /****************************************************************************
  //  InfluxDB  Zugangsdaten ...stehen in der user.config.php
  //  falls nicht, sind das hier die default Werte.
  ****************************************************************************/
  $aktuelleDaten["InfluxAdresse"] = $InfluxAdresse;
  $aktuelleDaten["InfluxPort"] = $InfluxPort;
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
    $funktionen->log_schreiben("OK. Daten gelesen.","   ",9);
    $funktionen->log_schreiben("Schleife ".$i." Ausgang...","   ",8);
    break;
  }


  $i++;
} while (($Start + 55) > time());


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
  //
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
if (file_exists($StatusFile) and isset($aktuelleDaten["Firmware"])) {
  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $whProTag = file_get_contents($StatusFile);

  // aktuellen Wert in die Datei schreiben:
  $whProTag = ($whProTag + ($aktuelleDaten["AC_Leistung"]/60));

  $rc = file_put_contents($StatusFile,$whProTag);
  $funktionen->log_schreiben("WattstundenGesamtHeute: ".round($whProTag,2),"   ",5);
}

Ausgang:

$funktionen->log_schreiben("---------   Stop   victron_phoenix.php   ----------------- ","|--",6);

return;



?>