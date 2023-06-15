#!/usr/bin/php
<?php
/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2015-2020]  [Ulrich Kunz]
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
//  Es dient dem Auslesen des Delta Wechselrichters SL 2500 über die USB 
//  Schnittstelle mit RS485 Adapter.
//  Im Moment nur für Variant 1 Geräte.
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
$crc_algo = $CRC_16_ARC_;
$Version = "";
$Start = time();  // Timestamp festhalten
$funktionen->log_schreiben("-------------   Start  delta_wechselrichter.php   ----------------- ","|--",6);

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

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//
*****************************************************************************/
$StatusFile = $Pfad."/database/".$GeraeteNummer.".WhProTag.txt";
if (!file_exists($StatusFile)) {
  /***************************************************************************
  //  Inhalt der Status Datei anlegen, wenn nicht existiert.
  ***************************************************************************/
  $rc = file_put_contents($StatusFile,"0");
  if ($rc === false) {
    $funktionen->log_schreiben("Konnte die Datei whProTag_delta.txt nicht anlegen.",5);
  }
}
else {
  $aktuelleDaten["WattstundenGesamtGestern"] = file_get_contents($StatusFile);
  $funktionen->log_schreiben("WattstundenGesamtGestern: ".$aktuelleDaten["WattstundenGesamtGestern"],"   ",8);
}



if (empty($WR_Adresse)) {
  $WR_ID = "01";
}
elseif(strlen($WR_Adresse) == 1)  {
  $WR_ID = str_pad(dechex($WR_Adresse),2,"0",STR_PAD_LEFT);
}
else {
  $WR_ID = str_pad(dechex(substr($WR_Adresse,-2)),2,"0",STR_PAD_LEFT);
}

$funktionen->log_schreiben("WR_ID: ".$WR_ID,"+  ",9);




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
  //  $aktuelleDaten["Firmware"]                Nummer
  //  $aktuelleDaten["Produkt"]                 Text
  //  $aktuelleDaten["Objekt"]                  Text
  //  $aktuelleDaten["Batteriespannung"]
  //  $aktuelleDaten["Batterieentladestrom"]
  //  $aktuelleDaten["Batterieladestrom"]
  //  $aktuelleDaten["Ladestatus"]
  //  $aktuelleDaten["Solarstrom"]
  //  $aktuelleDaten["Solarspannung"]
  //  $aktuelleDaten["Solarleistung"]
  //  $aktuelleDaten["WattstundenGesamt"]
  //  $aktuelleDaten["WattstundenGesamtHeute"]
  //  $aktuelleDaten["WattstundenGesamtGestern"]
  //  $aktuelleDaten["maxWattHeute"]
  //  $aktuelleDaten["maxAmpHeute"]
  //  $aktuelleDaten["Temperatur"]
  //  $aktuelleDaten["Optionen"]
  //  $aktuelleDaten["ErrorCodes"]
  //
  ****************************************************************************/


  $Framedaten = array (
      "Start" => "02",
      "Byte1" => "05",
      "ID" => "01",
      "Byte2" => "02",
      "Command" => "0000",
      "CRC" => "0000",
      "Stop" => "03");


  $Framedaten["ID"] = $WR_ID;
  $Framedaten["Command"] = "0000";

  $CRC = $funktionen->crc16_arc(hex2bin($Framedaten["Byte1"].$Framedaten["ID"].$Framedaten["Byte2"].$Framedaten["Command"]));

  $hex_string = $Framedaten["Byte1"].$Framedaten["ID"].$Framedaten["Byte2"].$Framedaten["Command"];
  $Framedaten["CRC"] = substr($CRC,2,2).substr($CRC,0,2);

  $Daten = $funktionen->delta_lesen($USB1,$Framedaten);

  if ($Daten == false) {
    $funktionen->log_schreiben("Zu wenig Sonne vorhanden....","   ",7);
    break;
  }

  $aktuelleDaten["Variant"] = hexdec(substr($Daten,6,2));
  $aktuelleDaten["Modell"] = $funktionen->Hex2String(substr($Daten,8,-2));

  $funktionen->log_schreiben("Variant: ".$aktuelleDaten["Variant"]." Modell: ".$aktuelleDaten["Modell"],"   ",5);


  $Framedaten["Command"] = "0040"; // Software Type

  $CRC = $funktionen->crc16_arc(hex2bin($Framedaten["Byte1"].$Framedaten["ID"].$Framedaten["Byte2"].$Framedaten["Command"]));

  $hex_string = $Framedaten["Byte1"].$Framedaten["ID"].$Framedaten["Byte2"].$Framedaten["Command"];
  $Framedaten["CRC"] = substr($CRC,2,2).substr($CRC,0,2);

  $Daten = $funktionen->delta_lesen($USB1,$Framedaten);

  $aktuelleDaten["Firmware"] = hexdec(substr($Daten,4,2)).".".hexdec(substr($Daten,6,2));

  if ($aktuelleDaten["Variant"] == 1) {
    $Framedaten["Command"] = "6001"; // Daten auslesen

    $CRC = $funktionen->crc16_arc(hex2bin($Framedaten["Byte1"].$Framedaten["ID"].$Framedaten["Byte2"].$Framedaten["Command"]));
    $hex_string = $Framedaten["Byte1"].$Framedaten["ID"].$Framedaten["Byte2"].$Framedaten["Command"];
    $Framedaten["CRC"] = substr($CRC,2,2).substr($CRC,0,2);

    $Daten = $funktionen->delta_lesen($USB1,$Framedaten);

    $aktuelleDaten["PN"] = $funktionen->Hex2String(substr($Daten,4,22));
    $aktuelleDaten["SN"] = $funktionen->Hex2String(substr($Daten,26,36));
    $aktuelleDaten["DateCode"] = $funktionen->Hex2String(substr($Daten,62,8));
    $aktuelleDaten["Revision"] = $funktionen->Hex2String(substr($Daten,70,4));
    $aktuelleDaten["Solarstrom"] = hexdec(substr($Daten,90,4))/10;
    $aktuelleDaten["Solarspannung"] = hexdec(substr($Daten,94,4));
    $aktuelleDaten["AC_Strom"] = hexdec(substr($Daten,102,4))/10;
    $aktuelleDaten["AC_Spannung"] = hexdec(substr($Daten,106,4));
    $aktuelleDaten["AC_Leistung"] = hexdec(substr($Daten,110,4));
    $aktuelleDaten["Frequenz"] = hexdec(substr($Daten,114,4))/100;
    $aktuelleDaten["WattstundenGesamtHeute"] = hexdec(substr($Daten,118,4));
    $aktuelleDaten["DC_Temperatur"] = $funktionen->hexdecs(substr($Daten,126,4));
    $aktuelleDaten["AC_Temperatur"] = $funktionen->hexdecs(substr($Daten,134,4));
    $aktuelleDaten["Max_AC_Strom"] = hexdec(substr($Daten,182,4))/10;
    $aktuelleDaten["Min_AC_Volt"] = hexdec(substr($Daten,186,4));
    $aktuelleDaten["Max_AC_Volt"] = hexdec(substr($Daten,190,4));
    $aktuelleDaten["Max_AC_Leistung"] = hexdec(substr($Daten,194,4));
    $aktuelleDaten["Min_Frequenz"] = hexdec(substr($Daten,198,4))/100;
    $aktuelleDaten["Max_Frequenz"] = hexdec(substr($Daten,202,4))/100;
    $aktuelleDaten["WattstundenGesamt"] = hexdec(substr($Daten,206,8))*100;
    $aktuelleDaten["Betriebsstunden"] = hexdec(substr($Daten,214,8));

  }

  if ($aktuelleDaten["Variant"] >= 212 and $aktuelleDaten["Variant"] <= 222) {

    $Framedaten["Command"] = "6001"; // Daten auslesen

    $CRC = $funktionen->crc16_arc(hex2bin($Framedaten["Byte1"].$Framedaten["ID"].$Framedaten["Byte2"].$Framedaten["Command"]));
    $hex_string = $Framedaten["Byte1"].$Framedaten["ID"].$Framedaten["Byte2"].$Framedaten["Command"];
    $Framedaten["CRC"] = substr($CRC,2,2).substr($CRC,0,2);

    $Daten = $funktionen->delta_lesen($USB1,$Framedaten);

    $aktuelleDaten["PN"] = $funktionen->Hex2String(substr($Daten,4,22));
    $aktuelleDaten["SN"] = $funktionen->Hex2String(substr($Daten,26,26));
    $aktuelleDaten["DateCode"] = $funktionen->Hex2String(substr($Daten,52,8));
    $aktuelleDaten["Revision"] = $funktionen->Hex2String(substr($Daten,60,4));

    $aktuelleDaten["AC_Spannung_R"] = hexdec(substr($Daten,104,4))/10;
    $aktuelleDaten["AC_Strom_R"] = hexdec(substr($Daten,108,4))/100;
    $aktuelleDaten["AC_Leistung_R"] = hexdec(substr($Daten,112,4));
    $aktuelleDaten["AC_Frequenz_R"] = hexdec(substr($Daten,116,4))/100;
    $aktuelleDaten["AC_Spannung_S"] = hexdec(substr($Daten,128,4))/10;
    $aktuelleDaten["AC_Strom_S"] = hexdec(substr($Daten,132,4))/100;
    $aktuelleDaten["AC_Leistung_S"] = hexdec(substr($Daten,136,4));
    $aktuelleDaten["AC_Frequenz_S"] = hexdec(substr($Daten,140,4))/100;
    $aktuelleDaten["AC_Spannung_T"] = hexdec(substr($Daten,152,4))/10;
    $aktuelleDaten["AC_Strom_T"] = hexdec(substr($Daten,156,4))/100;
    $aktuelleDaten["AC_Leistung_T"] = hexdec(substr($Daten,160,4));
    $aktuelleDaten["AC_Frequenz_T"] = hexdec(substr($Daten,164,4))/100;
    $aktuelleDaten["Solarspannung1"] = hexdec(substr($Daten,176,4))/10;
    $aktuelleDaten["Solarstrom1"] = hexdec(substr($Daten,180,4))/100;
    $aktuelleDaten["Solarleistung1"] = hexdec(substr($Daten,184,4));
    $aktuelleDaten["Solarspannung2"] = hexdec(substr($Daten,188,4))/10;
    $aktuelleDaten["Solarstrom2"] = hexdec(substr($Daten,192,4))/100;
    $aktuelleDaten["Solarleistung2"] = hexdec(substr($Daten,196,4));
    $aktuelleDaten["AC_Leistung"] = hexdec(substr($Daten,200,4));
    $aktuelleDaten["WattstundenGesamtHeute"] = hexdec(substr($Daten,212,8));
    $aktuelleDaten["WattstundenGesamt"] = hexdec(substr($Daten,228,8))*1000;
    $aktuelleDaten["Betriebsstunden"] = hexdec(substr($Daten,236,8));
    $aktuelleDaten["DC_Temperatur"] = $funktionen->hexdecs(substr($Daten,244,4));
    $aktuelleDaten["AC_Spannung"] = $aktuelleDaten["AC_Spannung_R"];
    $aktuelleDaten["AC_Strom"] = ($aktuelleDaten["AC_Strom_R"] + $aktuelleDaten["AC_Strom_S"] + $aktuelleDaten["AC_Strom_T"]);
    $aktuelleDaten["AC_Frequenz"] = $aktuelleDaten["AC_Frequenz_R"];
    $aktuelleDaten["Solarleistung"] = ($aktuelleDaten["Solarleistung1"] + $aktuelleDaten["Solarleistung2"]);

  }

  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/



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

  if ($i == 1) 
    $funktionen->log_schreiben(print_r($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists ("/var/www/html/delta_wechselrichter_math.php")) {
    include 'delta_wechselrichter_math.php';  // Falls etwas neu berechnet werden muss.
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




  if ($Wiederholungen <= $i or $i >= 6) {
      $funktionen->log_schreiben("Schleife ".$i." Ausgang...","   ",8);
      break;
  }
  if (is_file($Pfad."/1.user.config.php")) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (9 - (time() - $Start));
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
  $i++;
} while (($Start + 54) > time());


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
//  Jede Nacht 0 Uhr Tageszähler setzen
*****************************************************************************/
if (date("H:i") == "00:00" or date("H:i") == "00:01") {
  $rc = file_put_contents($StatusFile, $aktuelleDaten["WattstundenGesamt"]);
  $funktionen->log_schreiben("WattstundenGesamtGestern  gesetzt.","o--",5);
}

Ausgang:

$funktionen->log_schreiben("-------------   Stop   delta_wechselrichter.php   ----------------- ","|--",6);

return;




?>