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
//  Es dient dem Auslesen des KACO Wechselrichter über die
//  LAN Schnittstelle. ( MODBUS TCP )
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
$funktionen->log_schreiben("-------------   Start  kaco_wr.php    -------------------------- ","|--",6);

$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");
$funktionen->log_schreiben( "Kaco WR: ".$WR_IP." Port: ".$WR_Port." GeräteID: ".$WR_Adresse, "   ", 7 ); 


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
$StatusFile = $Pfad."/database/".$GeraeteNummer.".WhProTag.txt";
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
  //  MODBUS TCP
  ****************************************************************************/

  $rc = $funktionen->modbus_register_lesen($COM1,"40000","0002","String","03");
  $aktuelleDaten["Modbus"] = $rc["Wert"];
  if (trim($aktuelleDaten["Modbus"]) == false) {
    $funktionen->log_schreiben(print_r($rc,1),"!  ",6);
  }



  $rc = $funktionen->modbus_register_lesen($COM1,"40018","0010","String","03");
  $aktuelleDaten["KacoWRName"] = trim($rc["Wert"]);
  $rc = $funktionen->modbus_register_lesen($COM1,"40050","0010","String","03");
  $aktuelleDaten["Seriennummer"] = trim($rc["Wert"]);
  $rc = $funktionen->modbus_register_lesen($COM1,"40070","0002","U16","03");
  $aktuelleDaten["Modell"] = $rc["Wert"];

  $rc = $funktionen->modbus_register_lesen($COM1,"40076","0002","SF16","03");
  $aktuelleDaten["SF"] = $rc["Wert"];

  $rc = $funktionen->modbus_register_lesen($COM1,"40073","0002","U16","03");
  $aktuelleDaten["AC_Ausgangsstrom_R"] = ($rc["Wert"]*$aktuelleDaten["SF"]);
  $rc = $funktionen->modbus_register_lesen($COM1,"40074","0002","U16","03");
  $aktuelleDaten["AC_Ausgangsstrom_S"] = ($rc["Wert"]*$aktuelleDaten["SF"]);
  $rc = $funktionen->modbus_register_lesen($COM1,"40075","0002","U16","03");
  $aktuelleDaten["AC_Ausgangsstrom_T"] = ($rc["Wert"]*$aktuelleDaten["SF"]);
  $rc = $funktionen->modbus_register_lesen($COM1,"40072","0002","U16","03");
  $aktuelleDaten["AC_Ausgangsstrom"] = ($rc["Wert"]*$aktuelleDaten["SF"]);


  $rc = $funktionen->modbus_register_lesen($COM1,"40083","0002","SF16","03");
  $aktuelleDaten["SF"] = $rc["Wert"];

  $rc = $funktionen->modbus_register_lesen($COM1,"40080","0002","U16","03");
  $aktuelleDaten["AC_Ausgangsspannung_R"] = ($rc["Wert"]*$aktuelleDaten["SF"]);
  $rc = $funktionen->modbus_register_lesen($COM1,"40081","0002","U16","03");
  $aktuelleDaten["AC_Ausgangsspannung_S"] = ($rc["Wert"]*$aktuelleDaten["SF"]);
  $rc = $funktionen->modbus_register_lesen($COM1,"40082","0002","U16","03");
  $aktuelleDaten["AC_Ausgangsspannung_T"] = ($rc["Wert"]*$aktuelleDaten["SF"]);

  $rc = $funktionen->modbus_register_lesen($COM1,"40085","0002","SF16","03");
  $aktuelleDaten["SF"] = $rc["Wert"];

  $rc = $funktionen->modbus_register_lesen($COM1,"40084","0002","U16","03");
  $aktuelleDaten["AC_Leistung"] = ($rc["Wert"]*$aktuelleDaten["SF"]);

  $rc = $funktionen->modbus_register_lesen($COM1,"40087","0002","SF16","03");
  $aktuelleDaten["SF"] = $rc["Wert"];

  $rc = $funktionen->modbus_register_lesen($COM1,"40086","0002","U16","03");
  $aktuelleDaten["AC_Frequenz"] = round(($rc["Wert"]*$aktuelleDaten["SF"]),0);


  $rc = $funktionen->modbus_register_lesen($COM1,"40089","0002","SF16","03");
  $aktuelleDaten["SF"] = $rc["Wert"];

  $rc = $funktionen->modbus_register_lesen($COM1,"40088","0002","U16","03");
  $aktuelleDaten["AC_Scheinleistung"] = ($rc["Wert"]*$aktuelleDaten["SF"]);

  $rc = $funktionen->modbus_register_lesen($COM1,"40091","0002","SF16","03");
  $aktuelleDaten["SF"] = $rc["Wert"];

  $rc = $funktionen->modbus_register_lesen($COM1,"40090","0002","U16","03");
  $aktuelleDaten["AC_Blindleistung"] = ($rc["Wert"]*$aktuelleDaten["SF"]);

  $rc = $funktionen->modbus_register_lesen($COM1,"40093","0002","SF16","03");
  $aktuelleDaten["SF"] = $rc["Wert"];

  $rc = $funktionen->modbus_register_lesen($COM1,"40092","0002","U16","03");
  $aktuelleDaten["AC_Leistungsfaktor"] = ($rc["Wert"]*$aktuelleDaten["SF"]);


  $rc = $funktionen->modbus_register_lesen($COM1,"40096","0002","SF16","03");
  $aktuelleDaten["SF"] = $rc["Wert"];


  $rc = $funktionen->modbus_register_lesen($COM1,"40094","0002","U32","03");
  $aktuelleDaten["WattstundenGesamt"] = ($rc["Wert"]*$aktuelleDaten["SF"]);


  $rc = $funktionen->modbus_register_lesen($COM1,"40098","0002","SF16","03");
  $aktuelleDaten["SF"] = $rc["Wert"];

  $rc = $funktionen->modbus_register_lesen($COM1,"40097","0002","U16","03");
  $aktuelleDaten["Solarstrom"] = ($rc["Wert"]*$aktuelleDaten["SF"]);

  $rc = $funktionen->modbus_register_lesen($COM1,"40100","0002","SF16","03");
  $aktuelleDaten["SF"] = $rc["Wert"];

  $rc = $funktionen->modbus_register_lesen($COM1,"40099","0002","U16","03");
  $aktuelleDaten["Solarspannung"] = ($rc["Wert"]*$aktuelleDaten["SF"]);
  $aktuelleDaten["KeineSonne"] = $rc["KeineSonne"];  

  $rc = $funktionen->modbus_register_lesen($COM1,"40102","0002","SF16","03");
  $aktuelleDaten["SF"] = $rc["Wert"];

  $rc = $funktionen->modbus_register_lesen($COM1,"40101","0002","U16","03");
  $aktuelleDaten["Solarleistung"] = ($rc["Wert"]*$aktuelleDaten["SF"]);
  $aktuelleDaten["KeineSonne"] = $rc["KeineSonne"];  

  
  $rc = $funktionen->modbus_register_lesen($COM1,"40107","0002","SF16","03");
  $aktuelleDaten["SF"] = $rc["Wert"];

  $rc = $funktionen->modbus_register_lesen($COM1,"40103","0002","U16","03");
  $aktuelleDaten["Temperatur"] = ($rc["Wert"]*$aktuelleDaten["SF"]);


  $rc = $funktionen->modbus_register_lesen($COM1,"40108","0002","U16","03");
  $aktuelleDaten["Status"] = $rc["Wert"];

  $rc = $funktionen->modbus_register_lesen($COM1,"40110","0004","Hex","03");
  $aktuelleDaten["Warnungen"] = $rc["Wert"];


  if ($aktuelleDaten["KeineSonne"]) {
    $aktuelleDaten["AC_Blindleistung"] = 0;
    $aktuelleDaten["AC_Scheinleistung"] = 0;
    $aktuelleDaten["KeineSonne"] = 1;
    $funktionen->log_schreiben("Keine Sonne.","   ",1);
  }
  elseif ($aktuelleDaten["Solarleistung"] == 0) {
    // $aktuelleDaten["Status"] = 8;
    $aktuelleDaten["KeineSonne"] = 1;
    $funktionen->log_schreiben("Keine Solarleistung.","   ",1);
  }
  else {
    $aktuelleDaten["KeineSonne"] = 0;
  }



  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/


  /***************************************************************************
  //  Wenn es Probleme mit dem Auslesen gab...
  ***************************************************************************/
  if ($aktuelleDaten["KeineSonne"] == true) {
    if (date("i") % 20 != 0) {
      // Alle 20 Minuten trotzdem abspeichern.
      break;
    }
  }

  $funktionen->log_schreiben(print_r($aktuelleDaten,1),"*- ",9);


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
  $aktuelleDaten["Firmware"] = $aktuelleDaten["KacoWRName"];
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


  $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists ("/var/www/html/kaco_wr_math.php")) {
    include 'kaco_wr_math.php';  // Falls etwas neu berechnet werden muss.
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


  $funktionen->log_schreiben(print_r($aktuelleDaten,1),"*- ",10);


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
    $funktionen->log_schreiben("Schleife ".$i." Ausgang...","   ",5);
    break;
  }

  $i++;
} while (($Start + 54) > time());



if ($aktuelleDaten["KeineSonne"] == false) {


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
if (file_exists($StatusFile) and isset($aktuelleDaten["Solarleistung"])) {
  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $whProTag = file_get_contents($StatusFile);
  // aktuellen Wert in die Datei schreiben:
  $whProTag = ($whProTag + ($aktuelleDaten["Solarleistung"]/60));
  $rc = file_put_contents($StatusFile,$whProTag);
  $funktionen->log_schreiben("Solarleistung: ".$aktuelleDaten["Solarleistung"]." Watt -  WattstundenGesamtHeute: ".round($whProTag,2),"   ",5);
}


Ausgang:

$funktionen->log_schreiben("-------------   Stop   kaco_wr.php    -------------------------- ","|--",6);

return;






?>
