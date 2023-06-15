#!/usr/bin/php
<?php
/****************************************************************************/
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
//  Es dient dem Auslesen des my-pvr Thor Regler
//  über die LAN Schnittstelle 
//
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
/****************************************************************************/
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

// Achtung Änderung! Die Adresse $WR_Adresse muss in Dezimal eingegeben werden!
if (empty($WR_Adresse)) {
  $WR_ID = "01";
}
elseif (strlen( $WR_Adresse ) == 1) {
  $WR_ID = str_pad( dechex( $WR_Adresse ), 2, "0", STR_PAD_LEFT );
}
elseif (strlen( $WR_Adresse ) == 2) {
  $WR_ID = str_pad( dechex( substr( $WR_Adresse, - 2 )), 2, "0", STR_PAD_LEFT );
}
else {
  $WR_ID = dechex( $WR_Adresse );
}


$Startzeit = time();  // Timestamp festhalten
$funktionen->log_schreiben("-------------   Start  my-pv-thor.php  ----------------------------- ","|--",6);

$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");
$funktionen->log_schreiben( "my-PV Thor: ".$WR_IP." Port: ".$WR_Port." GeräteID: ".$WR_Adresse, "   ", 7 );


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

//  Warten bis LAN Connect erfolgreich war.
usleep(80000); // normal 800000,   bei Kaskade 500000

$i = 0;
do {
  $funktionen->log_schreiben("Die Daten werden ausgelesen...",">  ",9);
  $i++;

  /****************************************************************************
  //  Ab hier wird der Thor Regler ausgelesen.
  //
  // function modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase = 600000 ) {
  //
  ****************************************************************************/
  $Timebase = 10000;
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;  // Dummy..

  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "03", "1000", "55", "Hex", $Timebase );
  if ($rc == false and $i < 2) {
    $funktionen->log_schreiben("Fehler! Keine gültigen Daten empfangen. ","   ",5);
    continue;
  }

  $aktuelleDaten["Power"] = hexdec( substr( $rc["Wert"], 0, 4 ));
  $aktuelleDaten["Temperatur1"] = (hexdec( substr( $rc["Wert"], 4, 4 ))/10);
  $aktuelleDaten["HW1TempMax"] = (hexdec( substr( $rc["Wert"], 8, 4 ))/10);
  $aktuelleDaten["Status"] = hexdec( substr( $rc["Wert"], 12, 4 ));
  $aktuelleDaten["PowerTimeout"] = hexdec( substr( $rc["Wert"], 16, 4 ));
  $aktuelleDaten["BoostMode"] = hexdec( substr( $rc["Wert"], 20, 4 ));
  $aktuelleDaten["HW1TempMin"] = (hexdec( substr( $rc["Wert"], 24, 4 ))/10);
  $aktuelleDaten["BoosttimeStart"] = hexdec( substr( $rc["Wert"], 28, 4 ));
  $aktuelleDaten["BoosttimeStop"] = hexdec( substr( $rc["Wert"], 32, 4 ));
  $aktuelleDaten["Stunde"] = hexdec( substr( $rc["Wert"], 36, 4 ));
  $aktuelleDaten["Minute"] = hexdec( substr( $rc["Wert"], 40, 4 ));
  $aktuelleDaten["Sekunde"] = hexdec( substr( $rc["Wert"], 44, 4 ));
  $aktuelleDaten["BoostActivate"] = hexdec( substr( $rc["Wert"], 48, 4 ));
  $aktuelleDaten["AC_Thor_Nummer"] = hexdec( substr( $rc["Wert"], 52, 4 ));
  $aktuelleDaten["MaxPower"] = hexdec( substr( $rc["Wert"], 56, 4 ));
  $aktuelleDaten["ChipTemperatur"] = (hexdec( substr( $rc["Wert"], 60, 4 ))/10);
  $aktuelleDaten["Firmware"] = hexdec( substr( $rc["Wert"], 64, 4 ));
  $aktuelleDaten["PSFirmware"] = hexdec( substr( $rc["Wert"], 68, 4 ));
  $aktuelleDaten["Seriennummer"] = $funktionen->hex2str( substr( $rc["Wert"], 72, 32 ));
  $aktuelleDaten["Boosttime2Start"] = hexdec(substr( $rc["Wert"], 104, 4 ));
  $aktuelleDaten["Boosttime2Stop"] = hexdec( substr( $rc["Wert"], 108, 4 ));
  $aktuelleDaten["Temperatur2"] = (hexdec( substr( $rc["Wert"], 120, 4 ))/10);
  $aktuelleDaten["Temperatur3"] = (hexdec( substr( $rc["Wert"], 124, 4 ))/10);
  $aktuelleDaten["Temperatur4"] = (hexdec( substr( $rc["Wert"], 128, 4 ))/10);
  $aktuelleDaten["Temperatur5"] = (hexdec( substr( $rc["Wert"], 132, 4 ))/10);
  $aktuelleDaten["Temperatur6"] = (hexdec( substr( $rc["Wert"], 136, 4 ))/10);
  $aktuelleDaten["Temperatur7"] = (hexdec( substr( $rc["Wert"], 140, 4 ))/10);
  $aktuelleDaten["Temperatur8"] = (hexdec( substr( $rc["Wert"], 144, 4 ))/10);
  $aktuelleDaten["HW2TempMax"] = (hexdec( substr( $rc["Wert"], 148, 4 ))/10);
  $aktuelleDaten["HW3TempMax"] = (hexdec( substr( $rc["Wert"], 152, 4 ))/10);
  $aktuelleDaten["HW2TempMin"] = (hexdec( substr( $rc["Wert"], 156, 4 ))/10);
  $aktuelleDaten["HW3TempMin"] = (hexdec( substr( $rc["Wert"], 160, 4 ))/10);

  $aktuelleDaten["RH1Max"] = (hexdec( substr( $rc["Wert"], 164, 4 ))/10);
  $aktuelleDaten["RH2Max"] = (hexdec( substr( $rc["Wert"], 168, 4 ))/10);
  $aktuelleDaten["RH3Max"] = (hexdec( substr( $rc["Wert"], 172, 4 ))/10);

  $aktuelleDaten["RH1TagMin"] = (hexdec( substr( $rc["Wert"], 176, 4 ))/10);
  $aktuelleDaten["RH2TagMin"] = (hexdec( substr( $rc["Wert"], 180, 4 ))/10);
  $aktuelleDaten["RH3TagMin"] = (hexdec( substr( $rc["Wert"], 184, 4 ))/10);
  $aktuelleDaten["RH1NachtMin"] = (hexdec( substr( $rc["Wert"], 188, 4 ))/10);
  $aktuelleDaten["RH2NachtMin"] = (hexdec( substr( $rc["Wert"], 192, 4 ))/10);
  $aktuelleDaten["RH3NachtMin"] = (hexdec( substr( $rc["Wert"], 196, 4 ))/10);

  $aktuelleDaten["Nachtmode"] = hexdec( substr( $rc["Wert"], 200, 4 ));

  $aktuelleDaten["LegionellenInterval"] = hexdec( substr( $rc["Wert"], 212, 4 ));
  $aktuelleDaten["LegionellenStart"] = hexdec( substr( $rc["Wert"], 216, 4 ));
  $aktuelleDaten["LegionellenStop"] = hexdec( substr( $rc["Wert"], 220, 4 ));

  $aktuelleDaten["LegionellenTemp"] = hexdec( substr( $rc["Wert"], 224, 4 ));
  $aktuelleDaten["LegionellenMode"] = (hexdec( substr( $rc["Wert"], 228, 4 )));

  $aktuelleDaten["Spannung_R"] = hexdec( substr( $rc["Wert"], 244, 4 ));
  $aktuelleDaten["Strom_R"] = (hexdec( substr( $rc["Wert"], 248, 4 ))/10);
  $aktuelleDaten["Spannung_out"] = hexdec( substr( $rc["Wert"], 252, 4 ));
  $aktuelleDaten["Frequenz"] = (hexdec( substr( $rc["Wert"], 256, 4 ))/1000);
  $aktuelleDaten["Mode"] = hexdec( substr( $rc["Wert"], 260, 4 ));

  $aktuelleDaten["Spannung_S"] = hexdec( substr( $rc["Wert"], 268, 4 ));
  $aktuelleDaten["Strom_S"] = (hexdec( substr( $rc["Wert"], 272, 4 ))/10);

  $aktuelleDaten["Zaehler_Leistung"] = $funktionen->hexdecs( substr( $rc["Wert"], 276, 4 ));

  $aktuelleDaten["Spannung_T"] = hexdec( substr( $rc["Wert"], 288, 4 ));
  $aktuelleDaten["Strom_T"] = (hexdec( substr( $rc["Wert"], 292, 4 ))/10);

  $aktuelleDaten["Betriebsstatus"] = hexdec( substr( $rc["Wert"], 308, 4 ));

  $aktuelleDaten["DeviceState"] = hexdec( substr( $rc["Wert"], 324, 4 ));
  $aktuelleDaten["LeistungGesamt"] = hexdec( substr( $rc["Wert"], 328, 4 ));
  $aktuelleDaten["SolarLeistung"] = hexdec( substr( $rc["Wert"], 332, 4 ));
  $aktuelleDaten["NetzLeistung"] = hexdec( substr( $rc["Wert"], 336, 4 ));
  $aktuelleDaten["PWM_OUT"] = hexdec( substr( $rc["Wert"], 340, 4 ));


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
  $aktuelleDaten["Produkt"]  = "my-pv Thor";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);

  $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ",8);



  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists ("/var/www/html/my-pv-thor_math.php")) {
    include 'my-pv-thor_math.php';  // Falls etwas neu berechnet werden muss.
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

  if (is_file($Pfad."/1.user.config.php")) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (9 - (time() - $Startzeit));
    $funktionen->log_schreiben("Multi-Regler-Ausgang. ".$Zeitspanne,"   ",2);
    if ($Zeitspanne > 0) {
      // sleep($Zeitspanne);
      // Der Huawei mit sDongle ist sehr langsam. Deshalb keine Pause.
    }
    break;
  }
  else {
    if (floor(((9*$i) - (time() - $Startzeit)) / ($Wiederholungen - $i+1)) > 0) {
      $funktionen->log_schreiben("Schleife: ".($i)." Zeitspanne: ".(floor(((9*$i) - (time() - $Startzeit))/($Wiederholungen-$i+1))),"   ",3);
      sleep(floor(((9*$i) - (time() - $Startzeit)) / ($Wiederholungen - $i+1)));
    }
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben("OK. Daten gelesen.","   ",9);
    $funktionen->log_schreiben("Schleife ".$i." Ausgang...","   ",8);
    break;
  }
  $i++;

} while (($Startzeit + 56) > time());


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

/*******/
Ausgang:
/*******/

$funktionen->log_schreiben("-------------   Stop   my-pv-thor.php    --------------------------- ","|--",6);

return;


?>
