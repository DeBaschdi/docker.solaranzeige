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
//  Es dient dem Auslesen des PAC2200 SmartMeter über die LAN Schnittstelle.
//  MODBUS TCP  Port 502
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
//
*****************************************************************************/
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}

$zentralerTimestamp = time();

$Tracelevel = 7;  //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "SM"; // SM = SmartMeter
$Version = "";
$Start = time();  // Timestamp festhalten
Log::write("-------------   Start  pac2200_meter.php    -------------------------- ","|--",6);

Log::write("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
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
Log::write("Hardware Version: ".$Version,"o  ",8);

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
  Log::write("Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port,"XX ",3);
  Log::write("Exit.... ","XX ",9);
  goto Ausgang;
}

$i = 1;
do {
  Log::write("Die Daten werden ausgelesen...","+  ",9);

  /****************************************************************************
  //  Ab hier wird der Wechselrichter ausgelesen.
  //
  // function modbus_register_lesen($COM1,$Register,$Laenge,$Typ,$UnitID,$Befehl="03")
  //
  // Auf UnitID und Befehl achten!   UnitID muss 03 sein. Ist hier fest vergeben.
  //  Befehl 03 = single Byte read
  //  UnitID 03 = default
  ****************************************************************************/


  $rc = ModBus::modbus_register_lesen($COM1,"0001","0002","F32","01","03");
  $aktuelleDaten["AC_Spannung_R"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0003","0002","F32","01","03");
  $aktuelleDaten["AC_Spannung_S"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0005","0002","F32","01","03");
  $aktuelleDaten["AC_Spannung_T"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0007","0002","F32","01","03");
  $aktuelleDaten["AC_Spannung_RS"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0009","0002","F32","01","03");
  $aktuelleDaten["AC_Spannung_ST"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0011","0002","F32","01","03");
  $aktuelleDaten["AC_Spannung_RT"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0013","0002","F32","01","03");
  $aktuelleDaten["AC_Strom_R"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0015","0002","F32","01","03");
  $aktuelleDaten["AC_Strom_S"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0017","0002","F32","01","03");
  $aktuelleDaten["AC_Strom_T"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0025","0002","F32","01","03");
  $aktuelleDaten["AC_Leistung_R"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0027","0002","F32","01","03");
  $aktuelleDaten["AC_Leistung_S"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0029","0002","F32","01","03");
  $aktuelleDaten["AC_Leistung_T"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0055","0002","F32","01","03");
  $aktuelleDaten["Frequenz"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0065","0002","F32","01","03");
  $aktuelleDaten["AC_Leistung"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0069","0002","F32","01","03");
  $aktuelleDaten["PF_Leistung"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0037","0002","F32","01","03");
  $aktuelleDaten["PF_R"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0039","0002","F32","01","03");
  $aktuelleDaten["PF_S"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0041","0002","F32","01","03");
  $aktuelleDaten["PF_T"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0211","0002","U32","01","03");
  $aktuelleDaten["Tarif"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0501","0002","F32","01","03");
  $aktuelleDaten["Bezug"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0505","0002","F32","01","03");
  $aktuelleDaten["Einspeisung"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0801","0004","F64","01","03");
  $aktuelleDaten["Wh_Bezug"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0809","0004","F64","01","03");
  $aktuelleDaten["Wh_Einspeisung"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"0961","0004","F64","01","03");
  $aktuelleDaten["GesamterLeistungsbedarf"] = round($rc["Wert"],2);

  $rc = ModBus::modbus_register_lesen($COM1,"64001","001b","","01","03");
  


  $aktuelleDaten["HerstellerID"] = hexdec(substr($rc["Wert"],0,4));
  $aktuelleDaten["Seriennummer"] = Utils::Hex2String(substr($rc["Wert"],44,32));
  $aktuelleDaten["Firmware"] = Utils::Hex2String(substr($rc["Wert"],80,2)).hexdec(substr($rc["Wert"],82,2)).".".hexdec(substr($rc["Wert"],84,2)).".".hexdec(substr($rc["Wert"],86,2));

  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/




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
  $aktuelleDaten["Produkt"] = "Siemens PAC2200";
  $aktuelleDaten["Modell"] = "PAC2200";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);

  $aktuelleDaten["WattstundenGesamtHeute"] = 0;  // Dummy

  $aktuelleDaten["AC_Strom"] = $aktuelleDaten["AC_Strom_R"] + $aktuelleDaten["AC_Strom_S"] + $aktuelleDaten["AC_Strom_T"];

  Log::write(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists($basedir."/custom/pac2200_meter_math.php")) {
    include $basedir.'/custom/pac2200_meter_math.php';  // Falls etwas neu berechnet werden muss.
  }



  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT) {
    Log::write("MQTT Daten zum [ $MQTTBroker ] senden.","   ",1);
    require($basedir."/services/mqtt_senden.php");
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
      $rc = InfluxDB::influx_remote_test();
      if ($rc) {
        $rc = InfluxDB::influx_remote($aktuelleDaten);
        if ($rc) {
          $RemoteDaten = false;
        }
      }
      else {
        $RemoteDaten = false;
      }
    }
    if ($InfluxDB_local) {
      $rc = InfluxDB::influx_local($aktuelleDaten);
    }
  }
  else {
    $rc = InfluxDB::influx_local($aktuelleDaten);
  }




  if (is_file($basedir."/config/1.user.config.php")) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (7 - (time() - $Start));
    Log::write("Multi-Regler-Ausgang. ".$Zeitspanne,"   ",2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    Log::write("Schleife: ".($i)." Zeitspanne: ".(floor((56 - (time() - $Start))/($Wiederholungen-$i+1))),"   ",9);
    sleep(floor((56 - (time() - $Start))/($Wiederholungen-$i+1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write("Schleife ".$i." Ausgang...","   ",5);
    break;
  }

  $i++;
} while (($Start + 54) > time());


if (isset($aktuelleDaten["Firmware"])) {


  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $aktuelleDaten["Solarspannung"] = $aktuelleDaten["Solarspannung1"];
    Log::write("Daten werden zur HomeMatic übertragen...","   ",8);
    require($basedir."/services/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    Log::write("Nachrichten versenden...","   ",8);
    require($basedir."/services/meldungen_senden.php");
  }

  Log::write("OK. Datenübertragung erfolgreich.","   ",7);
}
else {
  Log::write("Keine gültigen Daten empfangen.","!! ",6);
}




Ausgang:

fclose($COM1);

Log::write("-------------   Stop   pac2200_meter.php    -------------------------- ","|--",6);

return;




?>

