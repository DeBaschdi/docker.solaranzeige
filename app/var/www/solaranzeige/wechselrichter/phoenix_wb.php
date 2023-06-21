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
//  Es dient dem Auslesen der Phoenix Contact Wallbox über das LAN
//  
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}

$Tracelevel = 7;  //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Version = "";
$Start = time();  // Timestamp festhalten
Log::write("-----------------   Start  phoenix_wb.php   --------------------- ","|--",6);

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

// Feste Phoenix Contact Wallbox Adresse = 180
$WR_ID = "B4";  // Dezimal 180



$COM1 = fsockopen($WR_IP, $WR_Port, $errno, $errstr, 5);
if (!is_resource($COM1)) {
  Log::write("Kein Kontakt zur Wallbox ".$WR_IP."  Port: ".$WR_Port." GeräteID: ".$WR_ID,"XX ",3);
  Log::write("Exit.... ","XX ",3);
  goto Ausgang;
}



/***************************************************************************
//  Einen Befehl an die Wallbox senden
//
//  Per MQTT  start = 1    amp = 6
//  Per HTTP  start_1      amp_6
//
***************************************************************************/
if (file_exists("/var/www/pipe/".$GeraeteNummer.".befehl.steuerung")) {

    Log::write("Steuerdatei '".$GeraeteNummer.".befehl.steuerung' vorhanden----","|- ",5);
    $Inhalt = file_get_contents("/var/www/pipe/".$GeraeteNummer.".befehl.steuerung");
    $Befehle = explode("\n",trim($Inhalt));
    Log::write("Befehle: ".print_r($Befehle,1),"|- ",9);

    for ($i = 0; $i < count($Befehle); $i++) {

      if ($i >= 6) {
        //  Es werden nur maximal 5 Befehle pro Datei verarbeitet!
        break;
      }
      /*********************************************************************************
      //  In der Datei "befehle.ini.php" müssen alle gültigen Befehle aufgelistet
      //  werden, die man benutzen möchte.
      //  Achtung! Genau darauf achten, dass der Befehl richtig geschrieben wird,
      //  damit das Gerät keinen Schaden nimmt.
      //  curr_6000 ist nur zum Testen ...
      //  Siehe Dokument:  Befehle_senden.pdf
      *********************************************************************************/
      if (file_exists($basedir."/config/befehle.ini")) {

        Log::write("Die Befehlsliste 'befehle.ini.php' ist vorhanden----","|- ",9);
        $INI_File =  parse_ini_file($basedir."/config/befehle.ini", true);
        $Regler35 = $INI_File["Regler35"];
        Log::write("Befehlsliste: ".print_r($Regler35,1),"|- ",9);

        foreach ($Regler35 as $Template) {
          $Subst = $Befehle[$i];
          $l = strlen($Template);
          for ($p = 1; $p < $l; ++$p) {
            Log::write("Template: ".$Template." Subst: ".$Subst." l: ".$l,"|- ",10);
            if ($Template[$p] == "#") {
              $Subst[$p] = "#";
            }
          }
          if ($Template == $Subst) {
            break;
          }
        }
        if ($Template != $Subst) {
          Log::write("Dieser Befehl ist nicht zugelassen. ".$Befehle[$i],"|o ",3);
          Log::write("Die Verarbeitung der Befehle wird abgebrochen.","|o ",3);
          break;
        }
      }
      else {
        Log::write("Die Befehlsliste 'befehle.ini.php' ist nicht vorhanden----","|- ",3);
        break;
      }

      $Teile = explode("_",$Befehle[$i]);
      $Antwort = "";
      // Hier wird der Befehl gesendet...
      //  $Teile[0] = Befehl
      //  $Teile[1] = Wert

      if (strtolower($Teile[0]) == "start") {
        if ($Teile[1] == 0) {
          $sendenachricht = hex2bin("000100000006".$WR_ID."0501900000");  //  Ladung unterbrechen
        }
        else {
          $sendenachricht = hex2bin("000100000006".$WR_ID."050190FF00");  //  Ladung einschalten
        }
      }
      if (strtolower($Teile[0]) == "amp") {
        $Ampere = floor($Teile[1]/100);
        $AmpHex = str_pad(dechex($Ampere),4,"0",STR_PAD_LEFT);
        $sendenachricht = hex2bin("000100000006".$WR_ID."060210".$AmpHex);  //  30 = 1E = 3 Ampere

      }
      $rc = fwrite($COM1, $sendenachricht);
      $Antwort = bin2hex(fread($COM1,1000));      // 1000 Bytes lesen
      Log::write("Antwort: ".$Antwort,"   ",3);

      sleep(2);
    }
    $rc = unlink("/var/www/pipe/".$GeraeteNummer.".befehl.steuerung");
    if ($rc) {
      Log::write("Datei  /../pipe/".$GeraeteNummer.".befehl.steuerung  gelöscht.","    ",9);
    }
}
else {
  Log::write("Steuerdatei '".$GeraeteNummer.".befehl.steuerung' nicht vorhanden----","|- ",9);
}



$i = 1;
do {

  /***************************************************************************
  //  Ab hier wird die Wallbox ausgelesen.
  //
  ***************************************************************************/
  Log::write("Abfrage der Daten. ","   ",8);

  $rc = ModBus::modbus_register_lesen($COM1,"0100","0001","String2",$WR_ID,"04");
  $aktuelleDaten["Status"] = trim($rc["Wert"]);

  $rc = ModBus::modbus_register_lesen($COM1,"0102","0002","Dec32Bit",$WR_ID,"04");
  $aktuelleDaten["Ladezeit"] = trim($rc["Wert"]);

  $rc = ModBus::modbus_register_lesen($COM1,"0105","0002","Dec32Bit",$WR_ID,"04");
  $aktuelleDaten["Firmware"] = trim($rc["Wert"]);


  $rc = ModBus::modbus_register_lesen($COM1,"0107","0001","Dec16Bit",$WR_ID,"04");
  $aktuelleDaten["ErrorCode"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0108","0002","Dec32Bit",$WR_ID,"04");
  $aktuelleDaten["Spannung_R"] = $rc["Wert"];


  $rc = ModBus::modbus_register_lesen($COM1,"0110","0002","Dec32Bit",$WR_ID,"04");
  $aktuelleDaten["Spannung_S"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0112","0002","Dec32Bit",$WR_ID,"04");
  $aktuelleDaten["Spannung_T"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0114","0002","Dec32Bit",$WR_ID,"04");
  $aktuelleDaten["Strom_R"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0116","0002","Dec32Bit",$WR_ID,"04");
  $aktuelleDaten["Strom_S"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0118","0002","Dec32Bit",$WR_ID,"04");
  $aktuelleDaten["Strom_T"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0120","0002","Dec32Bit",$WR_ID,"04");
  $aktuelleDaten["Leistung"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0128","0002","Dec32Bit",$WR_ID,"04");
  $aktuelleDaten["GesamtLeistung"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0130","0002","Dec32Bit",$WR_ID,"04");
  $aktuelleDaten["MaxLeistung"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0132","0002","Dec32Bit",$WR_ID,"04");
  $aktuelleDaten["LadeLeistung"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0134","0002","Dec32Bit",$WR_ID,"04");
  $aktuelleDaten["Frequenz"] = $rc["Wert"];

  $aktuelleDaten["Ladestrom"] = ($rc["Wert"]);

  $rc = ModBus::modbus_register_lesen($COM1,"0304","0006","String2",$WR_ID,"03");
  $aktuelleDaten["Seriennummer"] = ($rc["Wert"]);

  $rc = ModBus::modbus_register_lesen($COM1,"0310","0005","String2",$WR_ID,"03");
  $aktuelleDaten["GeraeteName"] = trim($rc["Wert"],"\0");

  $rc = ModBus::modbus_register_lesen($COM1,"0337","0001","Dec16Bit",$WR_ID,"03");
  $aktuelleDaten["Meter_Leistung"] = ($rc["Wert"]);

  $rc = ModBus::modbus_register_lesen($COM1,"0364","0001","Float",$WR_ID,"03");
  $aktuelleDaten["Meter_Leistung_Faktor"] = ($rc["Wert"]);



  $rc = ModBus::modbus_register_lesen($COM1,"0525","0001","Dec16Bit",$WR_ID,"03");
  $aktuelleDaten["Locktime"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0526","0001","Dec16Bit",$WR_ID,"03");
  $aktuelleDaten["Unlocktime"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0300","0001","Dec16Bit",$WR_ID,"03");
  $aktuelleDaten["MaxLadestrom"] = ($rc["Wert"]);



  $rc = ModBus::modbus_register_lesen($COM1,"0400","0001","Dec16Bit",$WR_ID,"01");
  $aktuelleDaten["LadungAktiv"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0402","0001","Dec16Bit",$WR_ID,"01");
  $aktuelleDaten["LadestationEin"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0403","0001","Dec16Bit",$WR_ID,"01");
  $aktuelleDaten["PerModbusAktiviert"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0405","0001","Dec16Bit",$WR_ID,"01");
  $aktuelleDaten["StatusRegister1steuern"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0406","0001","Dec16Bit",$WR_ID,"01");
  $aktuelleDaten["StatusRegister2steuern"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0407","0001","Dec16Bit",$WR_ID,"01");
  $aktuelleDaten["StatusRegister3steuern"] = $rc["Wert"];

  $rc = ModBus::modbus_register_lesen($COM1,"0408","0001","Dec16Bit",$WR_ID,"01");
  $aktuelleDaten["StatusRegister4steuern"] = $rc["Wert"];


  $rc = ModBus::modbus_register_lesen($COM1,"0436","0001","Dec16Bit",$WR_ID,"01");
  if (!$rc) {
    $aktuelleDaten["Ladung_erlaubt"] = 0;
    $aktuelleDaten["LadebedingungenOK"] = 1;    // Nur Dummy Wert
  }
  else {
    $aktuelleDaten["Ladung_erlaubt"] = $rc["Wert"];
    $aktuelleDaten["LadebedingungenOK"] = $rc["Wert"];    // Nur Dummy Wert
  }

  $rc = ModBus::modbus_register_lesen($COM1,"0439","0001","Dec16Bit",$WR_ID,"01");
  if (!$rc) {
    $aktuelleDaten["Kabel_angeschlossen"] = 0;
  }
  else {
    $aktuelleDaten["Kabel_angeschlossen"] = $rc["Wert"];
  }

  $rc = ModBus::modbus_register_lesen($COM1,"0440","0001","Dec16Bit",$WR_ID,"01");
  if (!$rc) {
    $aktuelleDaten["Kabel_entriegeln"] = 0;
  }
  else {
    $aktuelleDaten["Kabel_entriegeln"] = $rc["Wert"];
  }

  $rc = ModBus::modbus_register_lesen($COM1,"0461","0001","Dec16Bit",$WR_ID,"01");
  if (!$rc) {
    $aktuelleDaten["AufOKwarten"] = 0;
  }
  else {
    $aktuelleDaten["AufOKwarten"] = $rc["Wert"];
  }

  $rc = ModBus::modbus_register_lesen($COM1,"0465","0001","Dec16Bit",$WR_ID,"01");
  if (!$rc) {
    $aktuelleDaten["Kabel_verriegelt"] = 0;
  }
  else {
    $aktuelleDaten["Kabel_verriegelt"] = $rc["Wert"];
  }

  $rc = ModBus::modbus_register_lesen($COM1,"0467","0001","Dec16Bit",$WR_ID,"01");
  if (!$rc) {
    $aktuelleDaten["Ladung_unterbrochen"] = 0;
  }
  else {
    $aktuelleDaten["Ladung_unterbrochen"] = $rc["Wert"];
  }

  $rc = ModBus::modbus_register_lesen($COM1,"0468","0001","Dec16Bit",$WR_ID,"01");
  if (!$rc) {
    $aktuelleDaten["Ladung_unterbrechen"] = 0;
  }
  else {
    $aktuelleDaten["Ladung_unterbrechen"] = $rc["Wert"];
  }
  


  /**************************************************************************
  //  Ende Wallbox auslesen
  ***************************************************************************/

  $aktuelleDaten["Ladestrom"] = $aktuelleDaten["Strom_R"];


  $FehlermeldungText = "";


  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Produkt"] = "Phoenix";

  $aktuelleDaten["WattstundenGesamtHeute"] = 0;  // dummy

  Log::write(print_r($aktuelleDaten,1),"*- ",8);


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
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


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
    Log::write("Schleife: ".($i)." Zeitspanne: ".(floor(((9*$i) - (time() - $Start))/($Wiederholungen-$i+1))),"   ",9);
    sleep(floor(((9*$i) - (time() - $Start))/($Wiederholungen-$i+1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write("Schleife ".$i." Ausgang...","   ",5);
    break;
  }

  $i++;
} while (($Start + 54) > time());


if (1 == 1) {


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

Log::write("-----------------   Stop   phoenix_wb.php   --------------------- ","|--",6);

return;






?>
