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
//  Es dient dem Auslesen des Wechselrichters SofarSolar über eine RS485
//  Schnittstelle mit USB Adapter. Protokoll Version 2013-8-17
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Start = time( ); // Timestamp festhalten
Log::write( "----------------------   Start  sofarsolar_wr.php   --------------------- ", "|--", 6 );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
$aktuelleDaten["KeineSonne"] = false;
$Timer = 800000; // Wartezeit für das Lesen der MODBUS Daten
setlocale( LC_TIME, "de_DE.utf8" );
//  Hardware Version ermitteln.
//  $Platine = "Raspberry Pi Model B Plus Rev 1.2";
$Teile = explode( " ", $Platine );
if ($Teile[1] == "Pi") {
  Log::write( "Hardware Version: ".$Platine, "o  ", 8 );
  $Version = trim( $Teile[2] );
  if ($Teile[3] == "Model") {
    $Version .= trim( $Teile[4] );
    if ($Teile[5] == "Plus") {
      $Version .= trim( $Teile[5] );
    }
  }
}
switch ($Version) {

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
Log::write( "WR_ID: ".$WR_ID, "+  ", 8 );
$USB1 = USB::openUSB( $USBRegler );
if (!is_resource( $USB1 )) {
  Log::write( "USB Port kann nicht geöffnet werden. [1]", "XX ", 7 );
  Log::write( "Exit.... ", "XX ", 7 );
  goto Ausgang;
}
$i = 1;
do {
  Log::write( "Die Daten werden ausgelesen...", "+  ", 9 );

  /****************************************************************************
  //  Ab hier wird der Regler ausgelesen.
  //  Modell: x.x KTL-X
  ****************************************************************************/
  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["BefehlFunctionCode"] = "04";
  $Befehl["RegisterAddress"] = str_pad( "2000", 4, "0", STR_PAD_LEFT );
  $Befehl["RegisterCount"] = "0010";
  $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  $aktuelleDaten["Produkt"] = hexdec( substr( $rc["data"], 0, 4 ));
  $aktuelleDaten["Seriennummer"] = Utils::hex2string( substr( $rc["data"], 4, 28 ));
  $aktuelleDaten["Firmware"] = Utils::hex2string( substr( $rc["data"], 32, 8 ));
  $aktuelleDaten["Hardware"] = Utils::hex2string( substr( $rc["data"], 40, 8 ));
  Log::write( "Produkt: ".$aktuelleDaten["Produkt"], "   ", 7 );
  Log::write( "Seriennummer: ".$aktuelleDaten["Seriennummer"], "   ", 7 );

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterAddress"] = str_pad( "0", 4, "0", STR_PAD_LEFT );
  $Befehl["RegisterCount"] = "0030";
  $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  $aktuelleDaten["Status"] = hexdec( substr( $rc["data"], 0, 4 )); // 0
  $aktuelleDaten["Fehler1"] = hexdec( substr( $rc["data"], 4, 4 )); // 1
  $aktuelleDaten["Fehler2"] = hexdec( substr( $rc["data"], 8, 4 ));
  $aktuelleDaten["Fehler3"] = hexdec( substr( $rc["data"], 12, 4 ));
  $aktuelleDaten["Fehler4"] = hexdec( substr( $rc["data"], 16, 4 ));
  $aktuelleDaten["Fehler5"] = hexdec( substr( $rc["data"], 20, 4 ));
  $aktuelleDaten["PV1_Spannung"] = hexdec( substr( $rc["data"], 24, 4 )) / 10;
  $aktuelleDaten["PV1_Strom"] = hexdec( substr( $rc["data"], 28, 4 )) / 100;
  $aktuelleDaten["PV2_Spannung"] = hexdec( substr( $rc["data"], 32, 4 )) / 10;
  $aktuelleDaten["PV2_Strom"] = hexdec( substr( $rc["data"], 36, 4 )) / 100;
  $aktuelleDaten["PV1_Leistung"] = hexdec( substr( $rc["data"], 48, 4 )) * 10; // A
  $aktuelleDaten["PV2_Leistung"] = hexdec( substr( $rc["data"], 52, 4 )) * 10; // B
  $aktuelleDaten["AC_Frequenz"] = hexdec( substr( $rc["data"], 56, 4 )) / 100; // C Ab hier + 2 in der Dokumentation
  $aktuelleDaten["AC_Spannung_R"] = hexdec( substr( $rc["data"], 60, 4 )) / 10; // D  => F
  $aktuelleDaten["AC_Strom_R"] = hexdec( substr( $rc["data"], 64, 4 )) / 100; // E  => 10
  $aktuelleDaten["AC_Spannung_S"] = hexdec( substr( $rc["data"], 68, 4 )) / 10; // F  => 11
  $aktuelleDaten["AC_Strom_S"] = hexdec( substr( $rc["data"], 72, 4 )) / 100; // 10 => 12
  $aktuelleDaten["AC_Spannung_T"] = hexdec( substr( $rc["data"], 76, 4 )) / 10; // 11 => 13
  $aktuelleDaten["AC_Strom_T"] = hexdec( substr( $rc["data"], 80, 4 )) / 100; // 12 => 14
  $Energie_Total_H = (substr( $rc["data"], 84, 4 )); // 13 => 15
  $Energie_Total_L = (substr( $rc["data"], 88, 4 )); // 14 => 16
  $Laufzeit_Total_H = (substr( $rc["data"], 92, 4 )); // 15 => 17
  $Laufzeit_Total_L = (substr( $rc["data"], 96, 4 )); // 16 => 18
  $aktuelleDaten["WattstundenGesamtHeute"] = hexdec( substr( $rc["data"], 100, 4 )) * 10; // 17 => 19  Watt
  $aktuelleDaten["Laufzeit_Heute"] = hexdec( substr( $rc["data"], 104, 4 )); // 18 => 1A  Minuten
  $aktuelleDaten["Temperatur_Module"] = hexdec( substr( $rc["data"], 108, 4 )); // 19 => 1B  °C
  $aktuelleDaten["Temperatur"] = hexdec( substr( $rc["data"], 112, 4 )); // 1A => 1C  °C
  Log::write( "Auslesen des Gerätes beendet.", "   ", 8 );

  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/
  $aktuelleDaten["Energie_Total"] = (hexdec( $Energie_Total_H.$Energie_Total_L ) * 1000);
  $aktuelleDaten["Laufzeit_Total"] = hexdec( $Laufzeit_Total_H.$Laufzeit_Total_L );
  if ($aktuelleDaten["PV2_Strom"] > 0.1) {
    $aktuelleDaten["PV_Leistung"] = ($aktuelleDaten["PV1_Leistung"] + $aktuelleDaten["PV2_Leistung"]);
  }
  else {
    $aktuelleDaten["PV_Leistung"] = $aktuelleDaten["PV1_Leistung"];
    $aktuelleDaten["PV2_Leistung"] = 0;
  }

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
  $aktuelleDaten["Produkt"] = "SofarSolar";
  $aktuelleDaten["Modell"] = "KTL-X";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  Log::write( print_r( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists($basedir."/custom/sofarsolar_wr_math.php" )) {
    include $basedir.'/custom/sofarsolar_wr_math.php'; // Falls etwas neu berechnet werden muss.
  }

  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT and strtoupper( $MQTTAuswahl ) != "OPENWB") {
    Log::write( "MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1 );
    require($basedir."/services/mqtt_senden.php");
  }

  /****************************************************************************
  //  Zeit und Datum
  ****************************************************************************/
  $aktuelleDaten["Timestamp"] = time( );
  $aktuelleDaten["Monat"] = date( "n" );
  $aktuelleDaten["Woche"] = date( "W" );
  $aktuelleDaten["Wochentag"] = strftime( "%A", time( ));
  $aktuelleDaten["Datum"] = date( "d.m.Y" );
  $aktuelleDaten["Uhrzeit"] = date( "H:i:s" );

  /****************************************************************************
  //  InfluxDB  Zugangsdaten ...stehen in der user.config.php
  //  falls nicht, sind das hier die default Werte.
  ****************************************************************************/
  $aktuelleDaten["InfluxAdresse"] = $InfluxAdresse;
  $aktuelleDaten["InfluxPort"] = $InfluxPort;
  $aktuelleDaten["InfluxUser"] = $InfluxUser;
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
      $rc = InfluxDB::influx_remote_test( );
      if ($rc) {
        $rc = InfluxDB::influx_remote( $aktuelleDaten );
        if ($rc) {
          $RemoteDaten = false;
        }
      }
      else {
        $RemoteDaten = false;
      }
    }
    if ($InfluxDB_local) {
      $rc = InfluxDB::influx_local( $aktuelleDaten );
    }
  }
  else {
    $rc = InfluxDB::influx_local( $aktuelleDaten );
  }
  if (is_file( $basedir."/config/1.user.config.php" )) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (9 - (time( ) - $Start));
    Log::write( "Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2 );
    if ($Zeitspanne > 0) {
      sleep( $Zeitspanne );
    }
    break;
  }
  else {
    Log::write( "Schleife: ".($i)." Zeitspanne: ".(floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9 );
    sleep( floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write( "Schleife ".$i." Ausgang...", "   ", 8 );
    break;
  }
  $i++;
} while (($Start + 54) > time( ));
if (isset($aktuelleDaten["Seriennummer"])) {

  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    Log::write( "Daten werden zur HomeMatic übertragen...", "   ", 8 );
    require($basedir."/services/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    Log::write( "Nachrichten versenden...", "   ", 8 );
    require($basedir."/services/meldungen_senden.php");
  }
  Log::write( "OK. Datenübertragung erfolgreich.", "   ", 7 );
}
else {
  Log::write( "Keine gültigen Daten empfangen.", "!! ", 6 );
}
Ausgang:Log::write( "----------------------   Stop   sofarsolar_wr.php   --------------------- ", "|--", 6 );
return;
?>