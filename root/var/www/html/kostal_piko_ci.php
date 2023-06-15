#!/usr/bin/php
<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2015-2021]  [Ulrich Kunz]
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
//  Es dient dem Auslesen des KOSTAL Plenticore Wechselrichter über die
//  LAN Schnittstelle.
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
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}

$Tracelevel = 7;  //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$Start = time();  // Timestamp festhalten
$funktionen->log_schreiben("-------------   Start  kostal_piko_ci.php    --------------- ","|--",6);

$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");



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



$COM1 = fsockopen($WR_IP, $WR_Port, $errno, $errstr, 5);   // 5 = Timeout in Sekunden
if (!is_resource($COM1)) {
  $funktionen->log_schreiben("Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port,"XX ",3);
  $funktionen->log_schreiben("Exit.... ","XX ",9);
  goto Ausgang;
}

$i = 1;
do {
  $funktionen->log_schreiben("Die Daten werden ausgelesen...","+  ",9);

  /****************************************************************************
  //  Ab hier wird der Wechselrichter ausgelesen.
  //
  //  Ergebniswerte:
  //  $aktuelleDaten["Firmware"]                Nummer
  //  $aktuelleDaten["Produkt"]                 Text
  //  $aktuelleDaten["Objekt"]                  Text
  //  $aktuelleDaten["Datum"]                  Text
  //  $aktuelleDaten["AC_Spannung_R"]
  //  $aktuelleDaten["AnzahlPhasen"]
  //  $aktuelleDaten["AC_Spannung_S"]
  //  $aktuelleDaten["AC_Spannung_T"]
  //  $aktuelleDaten["AC_Frequenz"]
  //  $aktuelleDaten["AC_Leistung"]
  //  $aktuelleDaten["AC_Scheinleistung"]
  //  $aktuelleDaten["AC_Wirkleistung"]
  //  $aktuelleDaten["Ausgangslast"]
  //  $aktuelleDaten["Verbrauch"]
  //  $aktuelleDaten["Einspeisung"]
  //  $aktuelleDaten["AC_Solarleistung"]
  //  $aktuelleDaten["Batteriespannung"]
  //  $aktuelleDaten["Batteriestrom"]
  //  $aktuelleDaten["SOC"]
  //  $aktuelleDaten["Batterie_Temperatur"]
  //  $aktuelleDaten["Bat_Cycles"]
  //  $aktuelleDaten["PV_Leistung"]
  //  $aktuelleDaten["PV1_Spannung"]
  //  $aktuelleDaten["PV1_Leistung"]
  //  $aktuelleDaten["PV1_Strom"]
  //  $aktuelleDaten["PV2_Spannung"]
  //  $aktuelleDaten["PV2_Strom"]
  //  $aktuelleDaten["PV2_Leistung"]
  //  $aktuelleDaten["PV3_Spannung"]
  //  $aktuelleDaten["PV3_Strom"]
  //  $aktuelleDaten["PV3_Leistung"]
  //  $aktuelleDaten["Status"]
  //  $aktuelleDaten["Seriennummer"]
  //  $aktuelleDaten["WattstundenGesamtHeute"]
  //  $aktuelleDaten["WattstundenGesamtMonat"]
  //  $aktuelleDaten["WattstundenGesamtJahr"]
  //  $aktuelleDaten["WattstundenGesamt"]
  //
  ****************************************************************************/


  $rc = $funktionen->kostal_register_lesen($COM1,"000E","0008","String");
  $aktuelleDaten["Seriennummer"] = $rc["Wert"];
  if (trim($aktuelleDaten["Seriennummer"]) == false) {
    $funktionen->log_schreiben(print_r($rc,1),"!  ",6);
  }

  $rc = $funktionen->kostal_register_lesen($COM1,"0020","0001","U16-1");
  $aktuelleDaten["AnzahlPhasen"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"0022","0001","U16-1");
  $aktuelleDaten["AnzahlStrings"] = $rc["Wert"];

  $rc = $funktionen->kostal_register_lesen($COM1,"0026","0008","String");
  $aktuelleDaten["Softwarestand"] = trim($rc["Wert"]);

  $rc = $funktionen->kostal_register_lesen($COM1,"0038","0002","U16");
  // $aktuelleDaten["Status"] = hexdec(substr($rc["Wert"],0,4));  // Da stimmt was nicht..
  $aktuelleDaten["Status"] = $rc["Wert"];

  $rc = $funktionen->kostal_register_lesen($COM1,"0064","0002","Float");
  $aktuelleDaten["PV_Leistung"] = $rc["Wert"];


  $rc = $funktionen->kostal_register_lesen($COM1,"006C","0002","Float_Piko");
  $aktuelleDaten["Verbrauch_Netz"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"0070","0002","Float_Piko");
  $aktuelleDaten["Gesamtverbrauch_Netz"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"0072","0002","Float_Piko");
  $aktuelleDaten["Gesamtverbrauch_PV"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"0074","0002","Float_Piko");
  $aktuelleDaten["Verbrauch_PV"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"0076","0002","Float_Piko");
  $aktuelleDaten["Gesamtverbrauch"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"0098","0002","Float_Piko");
  $aktuelleDaten["AC_Frequenz"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"007C","0002","Float_Piko");
  $aktuelleDaten["Ausgangslast"] = $rc["Wert"];

  $rc = $funktionen->kostal_register_lesen($COM1,"009E","0002","Float_Piko");
  $aktuelleDaten["AC_Spannung_R"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"00A4","0002","Float_Piko");
  $aktuelleDaten["AC_Spannung_S"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"00AA","0002","Float_Piko");
  $aktuelleDaten["AC_Spannung_T"] = $rc["Wert"];

  $rc = $funktionen->kostal_register_lesen($COM1,"00AC","0002","Float_Piko");
  $aktuelleDaten["AC_Leistung"] = $rc["Wert"];

  $rc = $funktionen->kostal_register_lesen($COM1,"00AE","0002","Float_Piko");
  $aktuelleDaten["AC_Wirkleistung"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"00B2","0002","Float_Piko");
  $aktuelleDaten["AC_Scheinleistung"] = $rc["Wert"];


  $rc = $funktionen->kostal_register_lesen($COM1,"0140","0002","Float_Piko");
  $aktuelleDaten["WattstundenGesamt"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"0142","0002","Float_Piko");
  $aktuelleDaten["WattstundenGesamtHeute"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"0146","0002","Float_Piko");
  $aktuelleDaten["WattstundenGesamtMonat"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"0144","0002","Float_Piko");
  $aktuelleDaten["WattstundenGesamtJahr"] = $rc["Wert"];

  $rc = $funktionen->kostal_register_lesen($COM1,"00DE","0002","Float_Piko");
  $aktuelleDaten["PV1_Strom"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"00E0","0002","Float_Piko");
  $aktuelleDaten["PV1_Leistung"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"00E6","0002","Float_Piko");
  $aktuelleDaten["PV1_Spannung"] = $rc["Wert"];

  $rc = $funktionen->kostal_register_lesen($COM1,"00E8","0002","Float_Piko");
  $aktuelleDaten["PV2_Strom"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"00EA","0002","Float_Piko");
  $aktuelleDaten["PV2_Leistung"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"00F0","0002","Float_Piko");
  $aktuelleDaten["PV2_Spannung"] = $rc["Wert"];

  $rc = $funktionen->kostal_register_lesen($COM1,"00F2","0002","Float_Piko");
  $aktuelleDaten["PV3_Strom"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"00F4","0002","Float_Piko");
  $aktuelleDaten["PV3_Leistung"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"00FA","0002","Float_Piko");
  $aktuelleDaten["PV3_Spannung"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"00FC","0002","Float_Piko");
  $aktuelleDaten["AC_Solarleistung"] = $rc["Wert"];


  $rc = $funktionen->kostal_register_lesen($COM1,"0064","0002","Float_Piko");
  $aktuelleDaten["Total_DC_Power"] = $rc["Wert"];
  $rc = $funktionen->kostal_register_lesen($COM1,"0090","0002","Float_Piko");
  $aktuelleDaten["Laufzeit"] = $rc["Wert"];


  if (trim($aktuelleDaten["Seriennummer"]) == false or $aktuelleDaten["Status"] == 3 or $aktuelleDaten["Seriennummer"] == 0) {
    $funktionen->log_schreiben(print_r($rc,1),"!  ",6);
  }

  if ($aktuelleDaten["AC_Solarleistung"] > 32767) {
    $aktuelleDaten["AC_Solarleistung"] = 0;
  }

  $aktuelleDaten["Verbrauch"] = $aktuelleDaten["Verbrauch_PV"] + $aktuelleDaten["Verbrauch_Netz"] + $aktuelleDaten["Verbrauch_Batterie"];
  $aktuelleDaten["Einspeisung"] = $aktuelleDaten["AC_Solarleistung"] - $aktuelleDaten["Verbrauch"];
  $aktuelleDaten["PV_Leistung"] = ($aktuelleDaten["PV1_Leistung"] + $aktuelleDaten["PV2_Leistung"] + $aktuelleDaten["PV3_Leistung"]);
  if ($aktuelleDaten["Einspeisung"] > 0) {
    $aktuelleDaten["Ueberschuss"] = $aktuelleDaten["Einspeisung"];
  }
  else {
    $aktuelleDaten["Ueberschuss"] = 0;
  }

  if ($aktuelleDaten["Total_DC_Power"] > 0) {
    $aktuelleDaten["WirkungsgradWR"] = ($aktuelleDaten["Verbrauch"] + $aktuelleDaten["Ueberschuss"]) / $aktuelleDaten["Total_DC_Power"];
  }
  else {
    $aktuelleDaten["WirkungsgradWR"] = 0;
  }



  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/


  /***************************************************************************
  //  Wenn es Probleme mit dem Auslesen gab...
  ***************************************************************************/
  if (trim($aktuelleDaten["Seriennummer"]) == false or $aktuelleDaten["Seriennummer"] == 0) {
    $funktionen->log_schreiben(print_r($aktuelleDaten,1),"*- ",6);
    break;
  }

  /***************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  ***************************************************************************/
  $FehlermeldungText = "";


  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Firmware"] = $aktuelleDaten["Softwarestand"];
  $aktuelleDaten["Produkt"]  = $aktuelleDaten["Seriennummer"];
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);



  $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists ("/var/www/html/kostal_piko_ci_math.php")) {
    include 'kostal_piko_ci_math.php';  // Falls etwas neu berechnet werden muss.
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
    $Zeitspanne = (8 - (time() - $Start));
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
    $funktionen->log_schreiben("Schleife ".$i." Ausgang...","   ",5);
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
    $aktuelleDaten["Solarspannung"] = $aktuelleDaten["PV1_Spannung"];
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

Ausgang:

$funktionen->log_schreiben("-------------   Stop   kostal_piko_ci.php    --------------- ","|--",6);

return;






?>
