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
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Version = "";
$Start = time( ); // Timestamp festhalten
Log::write( "-------------   Start  innogy_wallbox.php   --------------------- ", "|--", 6 );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
//  Hardware Version ermitteln.
$Teile = explode( " ", $Platine );
if ($Teile[1] == "Pi") {
  Log::write( "Hardware Version: ".$Platine, "o  ", 7 );
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

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Ladeleistung der Wallbox
//  pro Ladung zu speichern.
//
*****************************************************************************/
$StatusFile = $basedir."/database/".$GeraeteNummer.".WhProLadung.txt";
if (file_exists( $StatusFile )) {

  /***************************************************************************
  //  Daten einlesen ...
  ***************************************************************************/
  $aktuelleDaten["WattstundenProLadung"] = file_get_contents( $StatusFile );
  Log::write( "WattstundenProLadung: ".round( $aktuelleDaten["WattstundenProLadung"], 2 ), "   ", 8 );
  if (empty($aktuelleDaten["WattstundenProLadung"])) {
    $aktuelleDaten["WattstundenProLadung"] = 0;
  }
}
else {
  /***************************************************************************
  //  Inhalt der Status Datei anlegen.
  ***************************************************************************/
  $rc = file_put_contents( $StatusFile, "0" );
  if ($rc === false) {
    Log::write( "Konnte die Datei WhProLadung.txt nicht anlegen.", "XX ", 5 );
  }
}


$COM1 = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 15 ); // normal 15
if (!is_resource( $COM1 )) {
  Log::write( "Kein Kontakt zur Wallbox oder Port nicht offen. IP: ".$WR_IP."  Port: ".$WR_Port, "ERR", 3 );
  Log::write( "Fehlermeldungen: ".$errno."   ".$errstr, "ERR", 5 );
  Log::write( "Exit.... ", "ERR", 3 );
  goto Ausgang;
}



/***************************************************************************
//  Einen Befehl an die Wallbox senden
//
//  Per MQTT  start = 1    amp = 6
//  Per HTTP  start_1      amp_6
//
***************************************************************************/
if (file_exists( "/var/www/pipe/".$GeraeteNummer.".befehl.steuerung" )) {
  Log::write( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' vorhanden----", "|- ", 5 );
  $Inhalt = file_get_contents( "/var/www/pipe/".$GeraeteNummer.".befehl.steuerung" );
  $Befehle = explode( "\n", trim( $Inhalt ));
  Log::write( "Befehle: ".print_r( $Befehle, 1 ), "|- ", 9 );
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
    if (file_exists( $basedir."/config/befehle.ini" )) {
      Log::write( "Die Befehlsliste 'befehle.ini.php' ist vorhanden----", "|- ", 9 );
      $INI_File = parse_ini_file( $basedir."/config/befehle.ini", true );
      $Regler78 = $INI_File["Regler78"];
      Log::write( "Befehlsliste: ".print_r( $Regler78, 1 ), "|- ", 9 );
      foreach ($Regler78 as $Template) {
        $Subst = $Befehle[$i];
        $l = strlen( $Template );
        for ($p = 1; $p < $l;++$p) {
          Log::write( "Template: ".$Template." Subst: ".$Subst." l: ".$l, "|- ", 10 );
          if ($Template[$p] == "#") {
            $Subst[$p] = "#";
          }
        }
        if ($Template == $Subst) {
          break;
        }
      }
      if ($Template != $Subst) {
        Log::write( "Dieser Befehl ist nicht zugelassen. ".$Befehle[$i], "|o ", 3 );
        Log::write( "Die Verarbeitung der Befehle wird abgebrochen.", "|o ", 3 );
        break;
      }
    }
    else {
      Log::write( "Die Befehlsliste 'befehle.ini.php' ist nicht vorhanden----", "|- ", 3 );
      break;
    }
    $Teile = explode( "_", $Befehle[$i] );
    $Antwort = "";
    //  Hier wird der Befehl gesendet...
    //  Beispiel  amp_6  = Auf 6 A ändern
    //  $Teile[0] = Befehl
    //  $Teile[1] = Wert
    //  GeraeteAdresse.Befehl.Register.Laenge.Wert
    if (strtolower( $Teile[0] ) == "start") {
      Log::write( "Start Wallbox.", "    ", 7 );
      // WB auf aktiv stellen
      $rc = ModBus::modbus_tcp_schreiben( $COM1, "01", "10", "1028", "0001", "0001");
      //  Alle 3 Phasen auf 6 Ampere stellen
      $rc = ModBus::modbus_tcp_schreiben( $COM1, "01", "10", "1012", "0006", "40c0000040c0000040c00000");
      // Nur mit einer Phase laden. Funktioniert noch nicht.
      //$rc = ModBus::modbus_tcp_schreiben( $COM1, "01", "10", "1012", "0006", "40c000000000000000000000");
    }
    if (strtolower( $Teile[0] ) == "stop") {
      Log::write( "Stop Wallbox.", "    ", 7 );
      // Alle 3 Phasen auf 0 Ampere stellen
      $rc = ModBus::modbus_tcp_schreiben( $COM1, "01", "10", "1012", "0006", "000000000000000000000000");
    }
    if (strtolower( $Teile[0] ) == "amp") {
      Log::write( "Stromänderung auf ".$Teile[1]." Ampere.", "    ", 7 );
      $Ampere =  $Teile[1];
      $AmpHex = bin2hex(pack('G',$Ampere));
      //  Float 32 >> 11 = 41300000 = 11 Ampere
      $rc =ModBus::modbus_tcp_schreiben( $COM1, "01", "10", "1012", "0006", $AmpHex.$AmpHex.$AmpHex);
    }
    if ($rc) {
      Log::write( "Befehl erfolgreich gesendet.", "    ", 5 );
    }
    sleep( 1 );
  }
  $rc = unlink( "/var/www/pipe/".$GeraeteNummer.".befehl.steuerung" );
  if ($rc) {
    Log::write( "Datei  /../pipe/".$GeraeteNummer.".befehl.steuerung  gelöscht.", "    ", 9 );
  }

}
else {
  Log::write( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' nicht vorhanden----", "|- ", 9 );
}


$i = 1;
do {
  /***************************************************************************
  //  Ab hier wird die Wallbox ausgelesen.
  //
  //  [Produkt] => eBox5DDE
  //  [Seriennummer] => LE005DDE
  //  [Firmware] => 1.3.36
  //  [Hersteller] => innogy eMobility Solutions GmbH
  //  [Status] => A
  //  [Kabelstatus] => 0001
  //  [MaxLadestrom] => 16
  //  [MaxLadestrom_R] => 16
  //  [MaxLadsterom_S] => 16
  //  [Max_Ladestrom_T] => 16
  //  [Strom_R] => 0.07
  //  [Strom_S] => 0.03
  //  [Strom_T] => 0.07
  //
  //  modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase = 600000 ) {
  //  $GeraeteAdresse  $WR_ID in Hex 2stellig
  //  $FunktionsCode   03, 04 usw.
  //  $RegisterAdresse in Dezimal
  //  $RegisterAnzahl  in HEX!
  //  $DatenTyp        U16 Float32 usw.
  //  $Timebase        100000
  //
  ***************************************************************************/

  Log::write( "Abfrage: ", "   ", 9 );

  $Timebase = 10000; // Je nach Dongle Firmware zwischen 60000 und 200000


  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "04", "0000", "0019", "String", $Timebase );
  if ($rc == false) {
    Log::write( "Es werden keine Daten empfangen.", "ERR", 1 );
    goto Ausgang;
  }
  $aktuelleDaten["Produkt"] = trim( $rc["Wert"] );
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "04", "0025", "0019", "String", $Timebase );
  $aktuelleDaten["Seriennummer"] = trim( $rc["Wert"] );
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "04", "0200", "0019", "String", $Timebase );
  $aktuelleDaten["Firmware"] = trim( $rc["Wert"] );
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "04", "0100", "0019", "String", $Timebase );
  $aktuelleDaten["Hersteller"] = trim( $rc["Wert"] );
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "04", "0275", "0002", "String", $Timebase );
  $aktuelleDaten["Status"] = substr( $rc["Wert"], 0, 1 );
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "04", "0300", "0001", "U16", $Timebase );
  $aktuelleDaten["Kabelstatus"] = trim( $rc["Wert"] );
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "04", "1000", "0002", "Float32", $Timebase );
  $aktuelleDaten["MaxLadestrom"] = trim( $rc["Wert"] );
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "04", "1000", "0002", "Float32", $Timebase );
  $aktuelleDaten["MaxLadestrom_R"] = $rc["Wert"];
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "04", "1002", "0002", "Float32", $Timebase );
  $aktuelleDaten["MaxLadsterom_S"] = $rc["Wert"];
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "04", "1004", "0002", "Float32", $Timebase );
  $aktuelleDaten["MaxLadestrom_T"] = $rc["Wert"];
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "04", "1006", "0002", "Float32", $Timebase );
  $aktuelleDaten["Strom_R"] = floor( $rc["Wert"] * 10 )/10;
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "04", "1008", "0002", "Float32", $Timebase );
  $aktuelleDaten["Strom_S"] = floor( $rc["Wert"] * 10 )/10;
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "04", "1010", "0002", "Float32", $Timebase );
  $aktuelleDaten["Strom_T"] = floor( $rc["Wert"] * 10 )/10;

  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "03", "1012", "0002", "Float32", $Timebase );
  $aktuelleDaten["SetStrom_R"] = $rc["Wert"];
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "03", "1014", "0002", "Float32", $Timebase );
  $aktuelleDaten["SetStrom_S"] = $rc["Wert"];
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "03", "1016", "0002", "Float32", $Timebase );
  $aktuelleDaten["SetStrom_T"] = $rc["Wert"];

  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "03", "1025", "0001", "U16", $Timebase );
  $aktuelleDaten["Setup_L1"] = $rc["Wert"];
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "03", "1026", "0001", "U16", $Timebase );
  $aktuelleDaten["Setup_L2"] = $rc["Wert"];
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "03", "1027", "0001", "U16", $Timebase );
  $aktuelleDaten["Setup_L3"] = $rc["Wert"];
  $rc = ModBus::modbus_tcp_lesen( $COM1, $WR_ID, "03", "1028", "0001", "U16", $Timebase );
  $aktuelleDaten["Station_aktiv"] = $rc["Wert"];

  /**************************************************************************
  //  Ende Wallbox auslesen
  ***************************************************************************/
  $FehlermeldungText = "";

  $aktuelleDaten["Leistung_R"] = round(230 * $aktuelleDaten["Strom_R"],1);
  $aktuelleDaten["Leistung_S"] = round(230 * $aktuelleDaten["Strom_S"],1);
  $aktuelleDaten["Leistung_T"] = round(230 * $aktuelleDaten["Strom_T"],1);
  $aktuelleDaten["Leistung"] = ($aktuelleDaten["Leistung_R"] + $aktuelleDaten["Leistung_S"] + $aktuelleDaten["Leistung_T"]);
  $aktuelleDaten["Frequenz"] = 0;
  

  $aktuelleDaten["Anz_Phasen"] = 0;
  if ($aktuelleDaten["Leistung_R"] > 0) {
    $aktuelleDaten["Anz_Phasen"] .= +1 ;
  }
  if ($aktuelleDaten["Leistung_S"] > 0) {
    $aktuelleDaten["Anz_Phasen"] .= +1 ;
  }
  if ($aktuelleDaten["Leistung_T"] > 0) {
    $aktuelleDaten["Anz_Phasen"] .= +1 ;
  }




  if ($aktuelleDaten["Kabelstatus"] != 3 and $aktuelleDaten["WattstundenProLadung"] > 0) {
    $aktuelleDaten["WattstundenProLadung"] = 0; // Zähler pro Ladung zurücksetzen
    $rc = file_put_contents( $StatusFile, "0" );
    Log::write( "WattstundenProLadung gelöscht.", "    ", 5 );
  }

  // Log::write( var_export( $rc, 1 ), "   ", 7 );










  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  $aktuelleDaten["WattstundenGesamtHeute"] = 0; // dummy

  if (date("Ymd") < "20230128") {
    Log::write( var_export( $aktuelleDaten, 1 ), "   ", 7 );
  }
  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists($basedir."/custom/innogy_wallbox_math.php" )) {
    include $basedir.'/custom/innogy_wallbox_math.php'; // Falls etwas neu berechnet werden muss.
  }

  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT) {
    Log::write( "MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1 );
    require($basedir."/services/mqtt_senden.php");
  }


  Log::write( var_export( $aktuelleDaten, 1 ), "   ", 8 );

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
    Log::write( "Schleife: ".($i)." Zeitspanne: ".(floor( ((9 * $i) - (time( ) - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9 );
    sleep( floor( ((9 * $i) - (time( ) - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write( "Schleife ".$i." Ausgang...", "   ", 5 );
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

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Ladeleistung der Wallbox
//  pro Ladung zu speichern.
*****************************************************************************/
if (file_exists( $StatusFile ) and $aktuelleDaten["Kabelstatus"] == 3) {

  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Ladung = Wh
  ***************************************************************************/
  $whProLadung = file_get_contents( $StatusFile );
  $whProLadung = ($whProLadung + ($aktuelleDaten["Leistung"] / 60));
  $rc = file_put_contents( $StatusFile, $whProLadung );
  Log::write( "WattstundenProLadung: ".round( $whProLadung ), "   ", 5 );
}


fclose($COM1);
//
Ausgang:
//
Log::write( "-------------   Stop   innogy_wallbox.php   --------------------- ", "|--", 6 );
return;


?>


