#!/usr/bin/php
<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2021]  [Ulrich Kunz]
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
//  Es dient dem Auslesen der Wallbe Wallbox über das LAN
//  Port 502 GeräteID = 255
//
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
$path_parts = pathinfo( $argv[0] );
$Pfad = $path_parts['dirname'];
if (!is_file( $Pfad."/1.user.config.php" )) {
  // Handelt es sich um ein Multi Regler System?
  require ($Pfad."/user.config.php");
}
require_once ($Pfad."/phpinc/funktionen.inc.php");
if (!isset($funktionen)) {
  $funktionen = new funktionen( );
}
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Version = "";
$Start = time( ); // Timestamp festhalten
$funktionen->log_schreiben( "-------------   Start  vestel_wallbox.php   --------------------- ", "|--", 6 );
$funktionen->log_schreiben( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
//  Hardware Version ermitteln.
$Teile = explode( " ", $Platine );
if ($Teile[1] == "Pi") {
  $funktionen->log_schreiben( "Hardware Version: ".$Platine, "o  ", 7 );
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
  $WR_ID = str_pad( $WR_Adresse, 2, "0", STR_PAD_LEFT );
}
elseif (strlen( $WR_Adresse ) == 3) {
  $WR_ID = $WR_Adresse;
}
else {
  $WR_ID = str_pad( substr( $WR_Adresse, - 2 ), 2, "0", STR_PAD_LEFT );
}
$WR_ID = strtoupper( dechex( $WR_ID ));
$COM1 = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 15 );
if (!is_resource( $COM1 )) {
  $funktionen->log_schreiben( "Kein Kontakt zur Wallbox ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
  $funktionen->log_schreiben( "Exit.... ", "XX ", 3 );
  goto Ausgang;
}

/***************************************************************************
//  Einen Befehl an die Wallbox senden
//
//  Per MQTT  start = 1    amp = 6
//  Per HTTP  start_1      amp_6
//
***************************************************************************/
if (file_exists( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" )) {
  $funktionen->log_schreiben( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' vorhanden----", "|- ", 5 );
  $Inhalt = file_get_contents( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" );
  $Befehle = explode( "\n", trim( $Inhalt ));
  $funktionen->log_schreiben( "Befehle: ".print_r( $Befehle, 1 ), "|- ", 9 );
  for ($i = 0; $i < count( $Befehle ); $i++) {
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
    if (file_exists( $Pfad."/befehle.ini.php" )) {
      $funktionen->log_schreiben( "Die Befehlsliste 'befehle.ini.php' ist vorhanden----", "|- ", 9 );
      $INI_File = parse_ini_file( $Pfad.'/befehle.ini.php', true );
      $Regler35 = $INI_File["Regler35"];
      $funktionen->log_schreiben( "Befehlsliste: ".print_r( $Regler35, 1 ), "|- ", 9 );
      foreach ($Regler35 as $Template) {
        $Subst = $Befehle[$i];
        $l = strlen( $Template );
        for ($p = 1; $p < $l;++$p) {
          $funktionen->log_schreiben( "Template: ".$Template." Subst: ".$Subst." l: ".$l, "|- ", 10 );
          if ($Template[$p] == "#") {
            $Subst[$p] = "#";
          }
        }
        if ($Template == $Subst) {
          break;
        }
      }
      if ($Template != $Subst) {
        $funktionen->log_schreiben( "Dieser Befehl ist nicht zugelassen. ".$Befehle[$i], "|o ", 3 );
        $funktionen->log_schreiben( "Die Verarbeitung der Befehle wird abgebrochen.", "|o ", 3 );
        break;
      }
    }
    else {
      $funktionen->log_schreiben( "Die Befehlsliste 'befehle.ini.php' ist nicht vorhanden----", "|- ", 3 );
      break;
    }
    $Teile = explode( "_", $Befehle[$i] );
    $Antwort = "";
    // Hier wird der Befehl gesendet...
    //  $Teile[0] = Befehl
    //  $Teile[1] = Wert
    //  GeraeteAdresse.Befehl.Register.Laenge.Wert
    if (strtolower( $Teile[0] ) == "start") {
      continue;
    }
    if (strtolower( $Teile[0] ) == "stop") {
      $sendenachricht = hex2bin( "000100000006FF06138C0000"); //  Stromänderung zu 0
      // Eventuel die Wallbox noch deaktivieren.
    }
    if (strtolower( $Teile[0] ) == "amp") {
      $Ampere =  $Teile[1];
      $AmpHex = str_pad( dechex( $Ampere ), 4, "0", STR_PAD_LEFT );
      //  11 = 000B = 11 Ampere
      $sendenachricht = hex2bin( "000100000006FF06138C".$AmpHex ); //  Stromänderung
    }
    $rc = fwrite( $COM1, $sendenachricht );
    $Antwort = bin2hex( fread( $COM1, 1000 )); // 1000 Bytes lesen
    $funktionen->log_schreiben( "Antwort: ".$Antwort, "   ", 3 );
    sleep( 2 );
  }
  $rc = unlink( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" );
  if ($rc) {
    $funktionen->log_schreiben( "Datei  /../pipe/".$GeraeteNummer.".befehl.steuerung  gelöscht.", "    ", 9 );
  }

  //  Standard: 16 Ampere bei Kommunikationsfehler
  $sendenachricht = hex2bin( "000100000006FF0607D00010" ); //  Stromstärke fix Kommunikationsfehlern
  $rc = fwrite( $COM1, $sendenachricht );
  $Antwort = bin2hex( fread( $COM1, 1000 )); // 1000 Bytes lesen
  $funktionen->log_schreiben( "Stromänderung fix: ".$Antwort, "   ", 3 );

}
else {
  $funktionen->log_schreiben( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' nicht vorhanden----", "|- ", 9 );
}

if (date( "i" ) % 2 != 0) {
  //  Alle 2 Minuten das Live Bit einschalten.
  $sendenachricht = hex2bin( "000100000006FF0617700001" ); //  Live Bit setzen
  $rc = fwrite( $COM1, $sendenachricht );
  $Antwort = bin2hex( fread( $COM1, 1000 )); // 1000 Bytes lesen
  $funktionen->log_schreiben( "Live Bit an: ".$Antwort, "   ", 4 );

  $sendenachricht = hex2bin( "000100000006FF0607D200F0" ); //  Timeout setzen auf 240 Sekunden
  $rc = fwrite( $COM1, $sendenachricht );
  $Antwort = bin2hex( fread( $COM1, 1000 )); // 1000 Bytes lesen
  $funktionen->log_schreiben( "Timeout setzen: ".$Antwort, "   ", 4 );
}



$i = 1;
do {

  /***************************************************************************
  //  Ab hier wird die Wallbox ausgelesen.
  //
  //  modbus_register_lesen($COM1,$Register,$Laenge,$Typ,$GeraeteAdresse,$Befehl)
  ***************************************************************************/
  $funktionen->log_schreiben( "Abfrage: ", "   ", 9 );
  $aktuelleDaten["PerModbusAktiviert"] = 0; // Dummy
  $aktuelleDaten["Ladung_erlaubt"] = 0; // Dummy
  $aktuelleDaten["LadebedingungenOK"] = 0; // Dummy
  //  Read Register  Function 04
  //                 modbus_register_lesen( $COM1, $Register, $Laenge, $Typ, $GeraeteAdresse, $Befehl = "03" ) {
  $rc = $funktionen->modbus_register_lesen( $COM1, "0100", "0019", "String3", $WR_ID, "04" );
  $aktuelleDaten["Seriennummer"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0130", "0032", "String3", $WR_ID, "04" );
  $aktuelleDaten["Ladepunkt_ID"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0190", "000A", "String3", $WR_ID, "04" );
  $aktuelleDaten["Ladepunkt_Brand"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0210", "0005", "String3", $WR_ID, "04" );
  $aktuelleDaten["Ladepunkt_Modell"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0230", "0032", "String3", $WR_ID, "04" );
  $aktuelleDaten["Firmware"] = trim( $rc["Wert"] );
  $funktionen->log_schreiben( "Firmware Version: ".$aktuelleDaten["Firmware"], "   ", 6 );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0400", "0002", "U32", $WR_ID, "04" );
  $aktuelleDaten["MaxPower"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "0404", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["AnzPhasen"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "1000", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["Status"] = trim( $rc["Wert"] ); // Stationsstatus
  $rc = $funktionen->modbus_register_lesen( $COM1, "1001", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["LadungAktiv"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "1002", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["LadestationEin"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "1004", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["Kabelstatus"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "1006", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["ErrorCode"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "1008", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["Strom_R"] = trim( $rc["Wert"] ) / 1000;
  $rc = $funktionen->modbus_register_lesen( $COM1, "1010", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["Strom_S"] = trim( $rc["Wert"] ) / 1000;
  $rc = $funktionen->modbus_register_lesen( $COM1, "1012", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["Strom_T"] = trim( $rc["Wert"] ) / 1000;
  $rc = $funktionen->modbus_register_lesen( $COM1, "1014", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["Spannung_R"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "1016", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["Spannung_S"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "1018", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["Spannung_T"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "1020", "0002", "U32", $WR_ID, "04" );
  $aktuelleDaten["Leistung"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "1024", "0002", "U32", $WR_ID, "04" );
  $aktuelleDaten["Leistung_R"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "1028", "0002", "U32", $WR_ID, "04" );
  $aktuelleDaten["Leistung_S"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "1032", "0002", "U32", $WR_ID, "04" );
  $aktuelleDaten["Leistung_T"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "1036", "0002", "U32", $WR_ID, "04" );
  $aktuelleDaten["Zaehlerstand"] = ($rc["Wert"] * 100);
  $rc = $funktionen->modbus_register_lesen( $COM1, "1100", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["MaxLadestrom"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "1102", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["MinLadestrom"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "1104", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["HardwareLimit"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "1106", "0001", "U16-1", $WR_ID, "04" );
  $aktuelleDaten["KabelLimit"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "1502", "0002", "U32", $WR_ID, "04" );
  $aktuelleDaten["LadeLeistung"] = trim( $rc["Wert"] );
  // Zeit   151104 = 15:11:04 Uhr)
  $rc = $funktionen->modbus_register_lesen( $COM1, "1504", "0002", "HEX", $WR_ID, "04" );
  $aktuelleDaten["StartLadung"] = hexdec( trim( $rc["Wert"] ));
  // Ergebnis in Minuten
  $rc = $funktionen->modbus_register_lesen( $COM1, "1508", "0002", "U32", $WR_ID, "04" );
  $aktuelleDaten["Ladezeit"] = floor( $rc["Wert"] / 60 );
  // Zeit   151104 = 15:11:04 Uhr)
  $rc = $funktionen->modbus_register_lesen( $COM1, "1512", "0002", "HEX", $WR_ID, "04" );
  $aktuelleDaten["EndeLadung"] = hexdec( trim( $rc["Wert"] ));
  $rc = $funktionen->modbus_register_lesen( $COM1, "2002", "0001", "U16-1", $WR_ID, "03" );
  $aktuelleDaten["Timeout"] = trim( $rc["Wert"] );
  //  Holding Register  R/W  Function 03
  //  Stromvorgabe
  $rc = $funktionen->modbus_register_lesen( $COM1, "5004", "0001", "U16-1", $WR_ID, "03" );
  $aktuelleDaten["Ladestrom"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_register_lesen( $COM1, "6000", "0001", "U16-1", $WR_ID, "03" );
  $aktuelleDaten["Alive"] = trim( $rc["Wert"] );
  // $funktionen->log_schreiben( "rc. ".print_r($rc,1), "   ", 7 );
  if ($aktuelleDaten["Kabelstatus"] == 0) {
    $aktuelleDaten["Kabel_entriegeln"] = 0;
    $aktuelleDaten["Kabel_angeschlossen"] = 0;
  }
  elseif ($aktuelleDaten["Kabelstatus"] == 3) {
    $aktuelleDaten["Kabel_entriegeln"] = 1;
    $aktuelleDaten["Kabel_angeschlossen"] = 1;
  }
  else {
    $aktuelleDaten["Kabel_angeschlossen"] = 1;
    $aktuelleDaten["Kabel_entriegeln"] = 0;
  }

  /**************************************************************************
  //  Ende Wallbox auslesen
  ***************************************************************************/
  $FehlermeldungText = "";

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Produkt"] = $aktuelleDaten["Ladepunkt_Brand"]."  ".$aktuelleDaten["Ladepunkt_Modell"];
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  $aktuelleDaten["WattstundenGesamtHeute"] = 0; // dummy
  $aktuelleDaten["GesamtLeistung"] = 0; // dummy
  $aktuelleDaten["Frequenz"] = 0; // dummy

  if (date("Ymd") < "20220107") {
    $funktionen->log_schreiben( var_export( $aktuelleDaten, 1 ), "   ", 7 );
  }
  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/vestel_wallbox_math.php" )) {
    include 'vestel_wallbox_math.php'; // Falls etwas neu berechnet werden muss.
  }

  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT) {
    $funktionen->log_schreiben( "MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1 );
    require ($Pfad."/mqtt_senden.php");
  }

  /****************************************************************************
  //  Zeit und Datum
  ****************************************************************************/
  //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
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
      $rc = $funktionen->influx_remote_test( );
      if ($rc) {
        $rc = $funktionen->influx_remote( $aktuelleDaten );
        if ($rc) {
          $RemoteDaten = false;
        }
      }
      else {
        $RemoteDaten = false;
      }
    }
    if ($InfluxDB_local) {
      $rc = $funktionen->influx_local( $aktuelleDaten );
    }
  }
  else {
    $rc = $funktionen->influx_local( $aktuelleDaten );
  }
  if (is_file( $Pfad."/1.user.config.php" )) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (7 - (time( ) - $Start));
    $funktionen->log_schreiben( "Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2 );
    if ($Zeitspanne > 0) {
      sleep( $Zeitspanne );
    }
    break;
  }
  else {
    $funktionen->log_schreiben( "Schleife: ".($i)." Zeitspanne: ".(floor( ((9 * $i) - (time( ) - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9 );
    sleep( floor( ((9 * $i) - (time( ) - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben( "Schleife ".$i." Ausgang...", "   ", 5 );
    break;
  }
  $i++;
} while (($Start + 54) > time( ));

if (isset($aktuelleDaten["Firmware"])) {

  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $aktuelleDaten["Solarspannung"] = $aktuelleDaten["Solarspannung1"];
    $funktionen->log_schreiben( "Daten werden zur HomeMatic übertragen...", "   ", 8 );
    require ($Pfad."/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    $funktionen->log_schreiben( "Nachrichten versenden...", "   ", 8 );
    require ($Pfad."/meldungen_senden.php");
  }
  $funktionen->log_schreiben( "OK. Datenübertragung erfolgreich.", "   ", 7 );
}
else {
  $funktionen->log_schreiben( "Keine gültigen Daten empfangen.", "!! ", 6 );
}
//
Ausgang:
//
$funktionen->log_schreiben( "-------------   Stop   vestel_wallbox.php   --------------------- ", "|--", 6 );
return;
?>
