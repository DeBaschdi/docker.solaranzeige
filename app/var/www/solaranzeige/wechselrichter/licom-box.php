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
//  Es dient dem Auslesen der AX Licom-Box von Effekta über eine RS485
//  Schnittstelle mit USB Adapter.
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
$Start = time( ); // Timestamp festhalten
$funktionen->log_schreiben( "----------------------   Start  licom-box.php   --------------------- ", "|--", 6 );
$funktionen->log_schreiben( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
$aktuelleDaten["KeineSonne"] = false;
$Timer = 200000; // Wartezeit für das Lesen der MODBUS Daten
setlocale( LC_TIME, "de_DE.utf8" );
//  Hardware Version ermitteln.
//  $Platine = "Raspberry Pi Model B Plus Rev 1.2";
$Teile = explode( " ", $Platine );
if ($Teile[1] == "Pi") {
  $funktionen->log_schreiben( "Hardware Version: ".$Platine, "o  ", 8 );
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

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//
*****************************************************************************/
$StatusFile = $Pfad."/database/".$GeraeteNummer.".WhProTag_licom.txt";
if (file_exists( $StatusFile )) {

  /***************************************************************************
  //  Daten einlesen ...
  ***************************************************************************/
  $aktuelleDaten["WattstundenGesamtHeute"] = file_get_contents( $StatusFile );
  $aktuelleDaten["WattstundenGesamtHeute"] = round( $aktuelleDaten["WattstundenGesamtHeute"], 2 );
  $funktionen->log_schreiben( "WattstundenGesamtHeute: ".$aktuelleDaten["WattstundenGesamtHeute"], "   ", 8 );
  if (empty($aktuelleDaten["WattstundenGesamtHeute"])) {
    $aktuelleDaten["WattstundenGesamtHeute"] = 0;
  }
  if (date( "H:i" ) == "00:00" or date( "H:i" ) == "00:01") { // Jede Nacht 0 Uhr
    $aktuelleDaten["WattstundenGesamtHeute"] = 0; //  Tageszähler löschen
    $rc = file_put_contents( $StatusFile, "0" );
    $funktionen->log_schreiben( "WattstundenGesamtHeute gelöscht.", "    ", 5 );
  }
}
else {
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;

  /***************************************************************************
  //  Inhalt der Status Datei anlegen.
  ***************************************************************************/
  $rc = file_put_contents( $StatusFile, "0" );
  if ($rc === false) {
    $funktionen->log_schreiben( "Konnte die Datei kwhProTag_licom.txt nicht anlegen.", "   ", 5 );
  }
}
$funktionen->log_schreiben( "WR_ID: ".$WR_ID, "+  ", 8 );
$USB1 = $funktionen->openUSB( $USBRegler );
if (!is_resource( $USB1 )) {
  $funktionen->log_schreiben( "USB Port kann nicht geöffnet werden. [1]", "XX ", 7 );
  $funktionen->log_schreiben( "Exit.... ", "XX ", 7 );
  goto Ausgang;
}



$i = 1;
do {
  $funktionen->log_schreiben( "Die Daten werden ausgelesen...", "+  ", 9 );

  /****************************************************************************
  //  Ab hier wird der Regler ausgelesen.
  //  Zuerst wird die seriennummer gesucht. An der LiCom Box können mehrere
  //  Wechselrichter hängen.
  ****************************************************************************/
  for ($d = 0; $d <= 6; $d++) {
    for ($f = 0; $f < 4; $f++) {
      $Befehl["DeviceID"] = $WR_ID;
      $Befehl["BefehlFunctionCode"] = "10";
      $Befehl["RegisterAddress"] = "015E";
      $Befehl["RegisterCount"] = "0001";
      $Befehl["Befehl"] = "000".$d;
      $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
      sleep( 1 ); // Muss auf mindestens 1 Sekunde bleiben.
      $Befehl["DeviceID"] = $WR_ID;
      $Befehl["BefehlFunctionCode"] = "03";
      $Befehl["RegisterAddress"] = "015E";
      $Befehl["RegisterCount"] = "0001";
      $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
      $aktuelleDaten["Parallel_index"] = hexdec( substr( $rc["data"], 0, 4 ));
      $funktionen->log_schreiben( "Index in: ".$d." Index out: ".$aktuelleDaten["Parallel_index"], "   ", 8 );
      if ($aktuelleDaten["Parallel_index"] == $d) {
        break;
      }
      sleep( 2 ); // Muss auf mindesten 1 Sekunde bleiben.
    }
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = "0160";
    $Befehl["RegisterCount"] = "0007";
    $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["Seriennummer"] = $funktionen->Hex2string( $rc["data"] );
    $funktionen->log_schreiben( "Aktuelle Seriennummer ".$aktuelleDaten["Seriennummer"], "   ", 8 );
    if ($aktuelleDaten["Seriennummer"] == $Seriennummer) {
      break;
    }
    elseif ($aktuelleDaten["Seriennummer"] == "00000000000000" and $d < 4) {
      //  falsches Gerät wurde ausgelesen.
      $d = 0;
    }
    if ($d > 5) {
      $funktionen->log_schreiben( "Die Seriennummer ".$Seriennummer." gibt es nicht.", "   ", 5 );
      goto Ausgang;
    }
  }



  /*******/
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterAddress"] = "0003";
  $Befehl["RegisterCount"] = "0003";
  $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  $aktuelleDaten["Warnungen"] = $rc["data"];

  /*******/
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterAddress"] = "00ED";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  $aktuelleDaten["Temperatur"] = hexdec( $rc["data"] );

  /*******/
  $Befehl["RegisterAddress"] = "03E1";
  $Befehl["RegisterCount"] = "0005";
  $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  $aktuelleDaten["Firmware"] = $funktionen->Hex2string( $rc["data"] );
  $funktionen->log_schreiben( "Firmware Version: ".$aktuelleDaten["Firmware"], "   ", 7 );

  /*******/
  $Befehl["RegisterAddress"] = "0127";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  $aktuelleDaten["Solarstrom"] = hexdec( $rc["data"] );

  /*******/
  $Befehl["RegisterAddress"] = "015E";
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  $funktionen->log_schreiben( $rc["data"], "   ", 10 );
  $aktuelleDaten["Parallel_index"] = hexdec( substr( $rc["data"], 0, 4 ));
  $aktuelleDaten["Parallel_index_vorhanden"] = $funktionen->Hex2string( substr( $rc["data"], 4, 2 ));
  //
  //  Hieran kann man wahrscheinlich Parallele Wechselrichter erkennen
  //  Das muss noch geprüft werden.
  $Befehl["RegisterAddress"] = "0311";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  $aktuelleDaten["Solarleistung"] = hexdec( $rc["data"] );
  $Befehl["RegisterAddress"] = "00D0";
  $Befehl["RegisterCount"] = "0020";
  $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  $aktuelleDaten["Modus"] = $funktionen->Hex2string( substr( $rc["data"], 0, 2 ));
  $aktuelleDaten["Netzspannung"] = hexdec( substr( $rc["data"], 4, 4 )) / 10;
  $aktuelleDaten["Netzfrequenz"] = hexdec( substr( $rc["data"], 20, 4 )) / 10;
  $aktuelleDaten["AC_Ausgangsspannung"] = hexdec( substr( $rc["data"], 32, 4 )) / 10;
  $aktuelleDaten["AC_Wirkleistung"] = hexdec( substr( $rc["data"], 36, 8 ));
  $aktuelleDaten["AC_Ausgangsfrequenz"] = hexdec( substr( $rc["data"], 44, 4 )) / 10;
  $aktuelleDaten["Ausgangslast"] = hexdec( substr( $rc["data"], 52, 4 ));
  $aktuelleDaten["Batteriespannung"] = hexdec( substr( $rc["data"], 64, 4 )) / 100;
  $aktuelleDaten["Batteriekapazitaet"] = hexdec( substr( $rc["data"], 72, 4 ));
  $aktuelleDaten["Batterieladestrom"] = hexdec( substr( $rc["data"], 76, 4 ));
  $funktionen->log_schreiben( print_r( $rc, 1 ), "   ", 10 );
  $aktuelleDaten["Solarspannung"] = hexdec( substr( $rc["data"], 104, 4 )) / 10;
  $aktuelleDaten["Max_Temp"] = hexdec( substr( $rc["data"], 116, 4 ));

  /*******/
  $Befehl["RegisterAddress"] = "0127";
  $Befehl["RegisterCount"] = "0010";
  $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  $funktionen->log_schreiben( $rc["data"], "   ", 10 );
  $aktuelleDaten["Solarladestrom"] = hexdec( substr( $rc["data"], 0, 4 ));
  $aktuelleDaten["Batteriespannung_SCC"] = hexdec( substr( $rc["data"], 4, 4 )) / 100;
  $aktuelleDaten["Batterieentladestrom"] = $funktionen->hexdecs( substr( $rc["data"], 8, 8 ));
  $aktuelleDaten["Status"] = $funktionen->d2b( substr( $rc["data"], 16, 4 ));

  /*******/
  $Befehl["RegisterAddress"] = "0167";
  $Befehl["RegisterCount"] = "0030";
  $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  $aktuelleDaten["Modus"] = $funktionen->Hex2string( substr( $rc["data"], 0, 2 ));
  $aktuelleDaten["FehlerCode"] = $funktionen->Hex2string( substr( $rc["data"], 4, 4 ));
  $aktuelleDaten["Netzspannung"] = hexdec( substr( $rc["data"], 8, 4 )) / 10;
  $aktuelleDaten["Netzfrequenz"] = hexdec( substr( $rc["data"], 12, 4 )) / 100;
  $aktuelleDaten["AC_Ausgangsspannung"] = hexdec( substr( $rc["data"], 16, 4 )) / 10;
  $aktuelleDaten["AC_Ausgangsfrequenz"] = hexdec( substr( $rc["data"], 20, 4 )) / 100;
  $aktuelleDaten["AC_Scheinleistung"] = hexdec( substr( $rc["data"], 24, 8 ));
  $aktuelleDaten["AC_Wirkleistung"] = hexdec( substr( $rc["data"], 32, 8 ));
  $aktuelleDaten["Ausgangslast"] = hexdec( substr( $rc["data"], 40, 4 ));
  $aktuelleDaten["Batteriespannung"] = hexdec( substr( $rc["data"], 44, 4 )) / 10;
  $aktuelleDaten["Batterieladestrom"] = hexdec( substr( $rc["data"], 48, 4 ));
  $aktuelleDaten["Batteriekapazitaet"] = hexdec( substr( $rc["data"], 52, 4 ));
  $aktuelleDaten["Solarspannung"] = hexdec( substr( $rc["data"], 56, 4 )) / 10;
  $aktuelleDaten["LadestromGesamt"] = hexdec( substr( $rc["data"], 60, 4 ));
  $aktuelleDaten["LeistungProzent"] = hexdec( substr( $rc["data"], 80, 4 ));
  $aktuelleDaten["Status"] = $funktionen->d2b( substr( $rc["data"], 84, 4 ));
  $aktuelleDaten["Solarladestrom"] = hexdec( substr( $rc["data"], 108, 4 ));
  $aktuelleDaten["Batterieentladestrom"] = $funktionen->hexdecs( substr( $rc["data"], 112, 8 ));
  $aktuelleDaten["Output_Mode"] = hexdec( substr( $rc["data"], 88, 4 ));
  $aktuelleDaten["QuellePrioritaet"] = hexdec( substr( $rc["data"], 92, 4 ));
  $aktuelleDaten["Max_Ladestrom"] = hexdec( substr( $rc["data"], 96, 4 ));
  $aktuelleDaten["Max_Lade_Range"] = hexdec( substr( $rc["data"], 100, 4 ));
  $aktuelleDaten["Max_AC_Ladestrom"] = hexdec( substr( $rc["data"], 104, 4 ));

  /*******/
  $Befehl["RegisterAddress"] = "049A";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  $aktuelleDaten["GeraeteTyp"] = $funktionen->Hex2string( $rc["data"] );

  /*******/
  $Befehl["RegisterAddress"] = "0488";
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  $aktuelleDaten["Modell"] = $funktionen->Hex2string( $rc["data"] );


  $Befehl["RegisterAddress"] = "04A0";
  $Befehl["RegisterCount"] = "0010";
  $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  $aktuelleDaten["Info_AC_Spannung"] = hexdec( substr( $rc["data"], 0, 4 ))/10;
  $aktuelleDaten["Info_AC_Frequenz"] = hexdec( substr( $rc["data"], 4, 4 ))/10;
  $aktuelleDaten["Info_Max_Ladestrom"] = hexdec( substr( $rc["data"], 8, 4 ));
  $aktuelleDaten["Info_Bat_Niedrigspannung"] = hexdec( substr( $rc["data"], 12, 4 ))/10;
  $aktuelleDaten["Info_Erhaltungsladung"] = hexdec( substr( $rc["data"], 16, 4 ))/10;
  $aktuelleDaten["Info_Ladespannung"] = hexdec( substr( $rc["data"], 20, 4 ))/10;
  $aktuelleDaten["Info_Ladestart_normal"] = hexdec( substr( $rc["data"], 24, 4 ))/10;
  $aktuelleDaten["Info_Max_Ladestrom"] = hexdec( substr( $rc["data"], 28, 4 ));
  $aktuelleDaten["Info_AC_Spannungsschwankung"] = hexdec( substr( $rc["data"], 32, 4 ));
  $aktuelleDaten["Info_Ausgangsprioritaet"] = hexdec( substr( $rc["data"], 36, 4 ));
  $aktuelleDaten["Info_Ladeprioritaet"] = hexdec( substr( $rc["data"], 40, 4 ));
  $aktuelleDaten["Info_BatterieTyp"] = hexdec( substr( $rc["data"], 44, 4 ));
  $aktuelleDaten["Info_PV_Leistungsausgleich"] = hexdec( substr( $rc["data"], 48, 4 ));
  $aktuelleDaten["Info_Ausgangsmode"] = hexdec( substr( $rc["data"], 52, 4 ));
  $aktuelleDaten["Info_Ladestart"] = hexdec( substr( $rc["data"], 56, 4 ))/10;
  $aktuelleDaten["Info_PV_OK"] = hexdec( substr( $rc["data"], 60, 4 ));


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
  $aktuelleDaten["Produkt"] = "Licom-Box";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  $funktionen->log_schreiben( print_r( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/licom-box_math.php" )) {
    include 'licom-box_math.php'; // Falls etwas neu berechnet werden muss.
  }

  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT and strtoupper( $MQTTAuswahl ) != "OPENWB") {
    $funktionen->log_schreiben( "MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1 );
    require ($Pfad."/mqtt_senden.php");
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
    $Zeitspanne = (9 - (time( ) - $Start));
    $funktionen->log_schreiben( "Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2 );
    if ($Zeitspanne > 0) {
      sleep( $Zeitspanne );
    }
    break;
  }
  else {
    $funktionen->log_schreiben( "Schleife: ".($i)." Zeitspanne: ".(floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9 );
    sleep( floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben( "Schleife ".$i." Ausgang...", "   ", 8 );
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

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//  Leistung in Watt / 60 Minuten, da 60 mal in der Stunde addiert wird.
*****************************************************************************/
if (file_exists( $StatusFile ) and isset($aktuelleDaten["Solarleistung"])) {

  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $whProTag = file_get_contents( $StatusFile );
  // aktuellen Wert in die Datei schreiben:
  $whProTag = ($whProTag + ($aktuelleDaten["Solarleistung"] / 60));
  $rc = file_put_contents( $StatusFile, $whProTag );
  $funktionen->log_schreiben( "Solarleistung: ".$aktuelleDaten["Solarleistung"]." Watt -  WattstundenGesamtHeute: ".round( $whProTag, 2 ), "   ", 5 );
}

/******/
Ausgang:

/******/
$funktionen->log_schreiben( "----------------------   Stop   licombox.php   --------------------- ", "|--", 6 );
return;
?>