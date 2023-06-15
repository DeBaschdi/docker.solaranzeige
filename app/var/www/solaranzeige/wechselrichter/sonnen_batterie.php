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
//  Es dient dem Auslesen der Sonnen Batterien über die LAN Schnittstelle
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
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

$Tracelevel = 7;  //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$Start = time();  // Timestamp festhalten
$funktionen->log_schreiben("-------------   Start  sonnen_batterie.php    ------------------ ","|--",6);

$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");
$funktionen->log_schreiben( "Sonnenbatterie: ".$WR_IP." Port: ".$WR_Port." GeräteID: ".$WR_Adresse, "   ", 7 ); 

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
$funktionen->log_schreiben("Hardware Version: ".$Version,"o  ",9);

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
$StatusFile = $Pfad."/database/".$GeraeteNummer.".WhProTag_sonnen.txt";
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



$COM1 = fsockopen($WR_IP, $WR_Port, $errno, $errstr, 5);
if (!is_resource($COM1)) {
  $funktionen->log_schreiben("Kein Kontakt zum Wechselrichter ".$WR_IP.",  Port: ".$WR_Port.",  Fehlermeldung: ".$errstr,"XX ",3);
  $funktionen->log_schreiben("Exit.... ","XX ",3);
  goto Ausgang;
}


$i = 1;
do {
  $funktionen->log_schreiben("Die Daten werden ausgelesen...","+  ",9);

  /****************************************************************************
  //  Ab hier wird die Batterie ausgelesen.
  //
  //
  ****************************************************************************/


  $URL  = "api/v1/status";

  $Daten = $funktionen->read($WR_IP,$WR_Port,$URL);

  if ($Daten === false) {
    $funktionen->log_schreiben("Status Werte sind falsch... nochmal lesen.","   ",3);
    if ($i >= 6) {
      $funktionen->log_schreiben(var_export($funktionen->read($WR_IP,$WR_Port,$URL),1),"o=>",9   );
      break;
    }
    $i++;
    continue;
  }
  $aktuelleDaten["Ausgangsleistung"] = $Daten["Apparent_output"];
  $aktuelleDaten["Batterieladung"] = $Daten["BatteryCharging"];
  $aktuelleDaten["Batterieentladung"] = $Daten["BatteryDischarging"];
  $aktuelleDaten["Verbrauch"] = $Daten["Consumption_W"];
  $aktuelleDaten["AC_Frequenz"] = $Daten["Fac"];
  $aktuelleDaten["Batterieentladung"] = $Daten["FlowConsumptionBattery"];
  $aktuelleDaten["Netzbezug"] = $Daten["FlowConsumptionGrid"];
  $aktuelleDaten["PV_Erzeugung"] = $Daten["FlowConsumptionProduction"];
  $aktuelleDaten["Batterie_Netzladung"] = $Daten["FlowGridBattery"];
  $aktuelleDaten["Batterie_PV_Ladung"] = $Daten["FlowProductionBattery"];
  $aktuelleDaten["PV_Einspeisung"] = $Daten["FlowProductionGrid"];
  $aktuelleDaten["Einspeisung_Bezug"] = $Daten["GridFeedIn_W"];    //  + / -
  $aktuelleDaten["Operating_Mode"] = $Daten["OperatingMode"];
  $aktuelleDaten["Lade_Entladeleistung"] = $Daten["Pac_total_W"];     //  + = geladen  - = entladen
  $aktuelleDaten["PV_Leistung"] = $Daten["Production_W"];
  $aktuelleDaten["SOC"] = $Daten["RSOC"];
  $aktuelleDaten["Sac1"] = $Daten["Sac1"];
  $aktuelleDaten["Sac2"] = $Daten["Sac2"];
  $aktuelleDaten["Sac3"] = $Daten["Sac3"];
  $aktuelleDaten["Systemstatus"] = $Daten["SystemStatus"];
  $aktuelleDaten["User_SOC"] = $Daten["USOC"];
  $aktuelleDaten["AC_Spannung"] = $Daten["Uac"];
  $aktuelleDaten["Batteriespannung"] = $Daten["Ubat"];
  $aktuelleDaten["Firmware"] = 0;  //  Dummy

  if($aktuelleDaten["Netzbezug"] == 1) {
    $aktuelleDaten["AC_Bezug"] = abs($aktuelleDaten["Einspeisung_Bezug"]);
  }
  else {
    $aktuelleDaten["AC_Bezug"] = 0;
  }

  if ($aktuelleDaten["Einspeisung_Bezug"] > 0) {
    $aktuelleDaten["AC_Einspeisung"] = $aktuelleDaten["Einspeisung_Bezug"];
  }
  else {
    $aktuelleDaten["AC_Einspeisung"] = 0;
  }




  foreach($aktuelleDaten as $key=>$wert) {
    if (empty($wert))  {
      $aktuelleDaten[$key] = 0;
    }
  }




  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/


  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Produkt"] = "Sonnen Batterie";
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


  if ($i == 1) 
    $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists ("/var/www/html/sonnen_batterie_math.php")) {
    include 'sonnen_batterie_math.php';  // Falls etwas neu berechnet werden muss.
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
  $aktuelleDaten["Uhrzeit"]   = date("H:i:s");


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
    $funktionen->log_schreiben("Schleife: ".($i)." Zeitspanne: ".(floor((54 - (time() - $Start))/($Wiederholungen-$i+1))),"   ",9);
    sleep(floor((54 - (time() - $Start))/($Wiederholungen-$i+1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben("OK. Daten gelesen.","   ",9);
    $funktionen->log_schreiben("Schleife ".$i." Ausgang...","   ",8);
    break;
  }

  $i++;
} while (($Start + 54) > time());







if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {

  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $aktuelleDaten["Solarspannung"] = $aktuelleDaten["Solarspannung1"];
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
if (file_exists($StatusFile) and isset($aktuelleDaten["PV_Leistung"])) {
  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $whProTag = file_get_contents($StatusFile);
  // aktuellen Wert in die Datei schreiben:
  $whProTag = ($whProTag + ($aktuelleDaten["PV_Leistung"]/60));
  $rc = file_put_contents($StatusFile,$whProTag);
  $funktionen->log_schreiben("WattstundenGesamtHeute: ".round($whProTag,2),"   ",5);
}

Ausgang:

$funktionen->log_schreiben("-------------   Stop   sonnen_batterie.php    ------------------ ","|--",6);

return;




?>
