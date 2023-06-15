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
//  Es dient dem Auslesen des Huawei SL Wechselrichter über die
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
$aktuelleDaten = array();
$RemoteDaten = true;
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$Start = time();  // Timestamp festhalten
$funktionen->log_schreiben("-------------   Start  huawei_SL.php    -------------------------- ","|--",6);
$aktuelleDaten["KeineSonne"] = false;

$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
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
  //  MODBUS TCP
  ****************************************************************************/
  $aktuelleDaten["Netz_Leistung"]   = 0;
  $aktuelleDaten["Netz_Leistung_R"] = 0;
  $aktuelleDaten["Netz_Leistung_S"] = 0;
  $aktuelleDaten["Netz_Leistung_T"] = 0;

  $rc = $funktionen->modbus_register_lesen($COM1,"40500","0001","S16-1","00","03");
  $aktuelleDaten["DC_Strom"] = ($rc["Wert"]/10);
  if (trim($aktuelleDaten["DC_Strom"]) == false) {
    $funktionen->log_schreiben("Fehler! DC_Strom: ".print_r($rc,1),"!  ",6);
  }

  $rc = $funktionen->modbus_register_lesen($COM1,"40521","0002","U32","00","03");
  $aktuelleDaten["PV_Leistung"] = $rc["Wert"];

  $rc = $funktionen->modbus_register_lesen($COM1,"40550","0004","U64-1","00","03");
  $aktuelleDaten["CO2_Ersparnis"] = ($rc["Wert"]/100);

  $rc = $funktionen->modbus_register_lesen($COM1,"40554","0002","S32","00","03");
  $aktuelleDaten["DC_Strom"] = $rc["Wert"];

  $rc = $funktionen->modbus_register_lesen($COM1,"40560","0002","U32","00","03");
  $aktuelleDaten["WattstundenGesamt"] = ($rc["Wert"]*100);

  $rc = $funktionen->modbus_register_lesen($COM1,"40562","0002","U32","00","03");
  $aktuelleDaten["WattstundenGesamtHeute"] = ($rc["Wert"]*100);

  $rc = $funktionen->modbus_register_lesen($COM1,"40564","0002","U32","00","03");
  $aktuelleDaten["Sonnenstunden"] = ($rc["Wert"]/10);

  $rc = $funktionen->modbus_register_lesen($COM1,"40566","0001","U16-1","00","03");
  $aktuelleDaten["Status"] = $rc["Wert"];

  $rc = $funktionen->modbus_register_lesen($COM1,"40572","0001","S16-1","00","03");
  $aktuelleDaten["AC_Strom_R"] = ($rc["Wert"]);

  $rc = $funktionen->modbus_register_lesen($COM1,"40573","0001","S16-1","00","03");
  $aktuelleDaten["AC_Strom_S"] = ($rc["Wert"]);

  $rc = $funktionen->modbus_register_lesen($COM1,"40574","0001","S16-1","00","03");
  $aktuelleDaten["AC_Strom_T"] = ($rc["Wert"]);

  $rc = $funktionen->modbus_register_lesen($COM1,"40575","0001","S16-1","00","03");
  $aktuelleDaten["AC_Spannung_R"] = ($rc["Wert"]/10);

  $rc = $funktionen->modbus_register_lesen($COM1,"40576","0001","S16-1","00","03");
  $aktuelleDaten["AC_Spannung_S"] = ($rc["Wert"]/10);

  $rc = $funktionen->modbus_register_lesen($COM1,"40577","0001","S16-1","00","03");
  $aktuelleDaten["AC_Spannung_T"] = ($rc["Wert"]/10);

  $rc = $funktionen->modbus_register_lesen($COM1,"40685","0001","S16-1","00","03");
  $aktuelleDaten["Effektivitaet"] = ($rc["Wert"]/100);

  $rc = $funktionen->modbus_register_lesen($COM1,"32260","0002","U32","01","03");
  $aktuelleDaten["Netz_Spannung_R"] = ($rc["Wert"]/100);

  $rc = $funktionen->modbus_register_lesen($COM1,"32262","0002","U32","01","03");
  $aktuelleDaten["Netz_Spannung_S"] = ($rc["Wert"]/100);

  $rc = $funktionen->modbus_register_lesen($COM1,"32264","0002","U32","01","03");
  $aktuelleDaten["Netz_Spannung_T"] = ($rc["Wert"]/100);


  $rc = $funktionen->modbus_register_lesen($COM1,"32272","0002","S32","01","03");
  $aktuelleDaten["Netz_Strom_R"] = ($rc["Wert"]/10);

  $rc = $funktionen->modbus_register_lesen($COM1,"32274","0002","S32","01","03");
  $aktuelleDaten["Netz_Strom_S"] = ($rc["Wert"]/10);

  $rc = $funktionen->modbus_register_lesen($COM1,"32276","0002","S32","01","03");
  $aktuelleDaten["Netz_Strom_T"] = ($rc["Wert"]/10);

  $rc = $funktionen->modbus_register_lesen($COM1,"32278","0002","S32","01","03");
  $aktuelleDaten["Netz_Leistung"] = ($rc["Wert"]);
  if (trim($aktuelleDaten["Netz_Leistung"]) == false) {
    $funktionen->log_schreiben("Fehler! Netz_Leistung: ".print_r($rc,1),"!  ",6);
  }

  $rc = $funktionen->modbus_register_lesen($COM1,"32335","0002","S32","01","03");
  $aktuelleDaten["Netz_Leistung_R"] = ($rc["Wert"]);

  $rc = $funktionen->modbus_register_lesen($COM1,"32337","0002","S32","01","03");
  $aktuelleDaten["Netz_Leistung_S"] = ($rc["Wert"]);

  $rc = $funktionen->modbus_register_lesen($COM1,"32339","0002","S32","01","03");
  $aktuelleDaten["Netz_Leistung_T"] = ($rc["Wert"]);

  $rc = $funktionen->modbus_register_lesen($COM1,"32349","0004","S64","01","03");
  $aktuelleDaten["Wh_Einspeisung"] = ($rc["Wert"]*10);

  $rc = $funktionen->modbus_register_lesen($COM1,"32357","0004","S64","01","03");
  $aktuelleDaten["Wh_Bezug"] = ($rc["Wert"]*10);


  $rc = $funktionen->modbus_register_lesen($COM1,"40685","0001","U16-1","00","03");
  $aktuelleDaten["WR_Effektivitaet"] = ($rc["Wert"]/100);

  $rc = $funktionen->modbus_register_lesen($COM1,"50000","0001","U16-1","00","03");
  $aktuelleDaten["AlarmInfo1"] = ($rc["Wert"]/100);

  $rc = $funktionen->modbus_register_lesen($COM1,"50001","0001","U16-1","00","03");
  $aktuelleDaten["AlarmInfo2"] = ($rc["Wert"]/100);


  if ($aktuelleDaten["Netz_Leistung"] > 0) {
    $aktuelleDaten["Bezug"] = $aktuelleDaten["Netz_Leistung"];
    $aktuelleDaten["Einspeisung"] = 0;
    $aktuelleDaten["Verbrauch"] = ($aktuelleDaten["Netz_Leistung"] + $aktuelleDaten["PV_Leistung"]);


  }
  else {
    $aktuelleDaten["Einspeisung"] = abs($aktuelleDaten["Netz_Leistung"]);
    $aktuelleDaten["Bezug"] = 0;
    $aktuelleDaten["Verbrauch"] = ($aktuelleDaten["PV_Leistung"] + $aktuelleDaten["Netz_Leistung"]);
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
  $aktuelleDaten["Firmware"] = "SL3000";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);

  $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists ("/var/www/html/huawei_SL_math.php")) {
    include 'huawei_SL_math.php';  // Falls etwas neu berechnet werden muss.
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



Ausgang:

$funktionen->log_schreiben("-------------   Stop   huawei_SL.php    -------------------------- ","|--",6);

return;






?>
