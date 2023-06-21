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
//  Es dient dem Auslesen des Victron-energy Reglers über die USB Schnittstelle
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//  Protokoll Pl16
//
//  Sollen mehre Geräte augelesen werden, dann nur mit RS232 Anschluss und
//  Regler = 45 = Phocos Any-Grid  => 2400 Baud.
//
//  Achtung! Der Regler sendet zwischendurch immer wieder asynchrone Daten!
//
*****************************************************************************/
$Tracelevel = 7; //  1 bis 10  10 = Debug
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$RemoteDaten = true;
$Start = time( ); // Timestamp festhalten
Log::write( "-----------   Start  ax_wechselrichter.php   ----------------- ", "|--", 6 );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
//  Hardware Version ermitteln.
$Teile = explode( " ", $Platine );
if ($Teile[1] == "Pi") {
  $Version = trim( $Teile[2] );
  if ($Teile[3] == "Model") {
    $Version .= trim( $Teile[4] );
    if ($Teile[5] == "Plus") {
      $Version .= trim( $Teile[5] );
    }
  }
}
Log::write( "Hardware Version: ".$Version, "o  ", 8 );
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

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//
*****************************************************************************/
$StatusFile = $basedir."/database/".$GeraeteNummer.".WhProTag_ax.txt";
if (file_exists( $StatusFile )) {

  /***************************************************************************
  //  Daten einlesen ...
  ***************************************************************************/
  $aktuelleDaten["WattstundenGesamtHeute"] = file_get_contents( $StatusFile );
  $aktuelleDaten["WattstundenGesamtHeute"] = round( $aktuelleDaten["WattstundenGesamtHeute"], 2 );
  Log::write( "WattstundenGesamtHeute: ".$aktuelleDaten["WattstundenGesamtHeute"], "   ", 8 );
  if (empty($aktuelleDaten["WattstundenGesamtHeute"])) {
    $aktuelleDaten["WattstundenGesamtHeute"] = 0;
  }
  if (date( "H:i" ) == "00:00" or date( "H:i" ) == "00:01") { // Jede Nacht 0 Uhr
    $aktuelleDaten["WattstundenGesamtHeute"] = 0; //  Tageszähler löschen
    $rc = file_put_contents( $StatusFile, "0" );
    Log::write( "WattstundenGesamtHeute gelöscht.", "    ", 5 );
  }
}
else {
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;

  /***************************************************************************
  //  Inhalt der Status Datei anlegen.
  ***************************************************************************/
  $rc = file_put_contents( $StatusFile, "0" );
  if ($rc === false) {
    Log::write( "Konnte die Datei kwhProTag_ax.txt nicht anlegen.", "   ", 5 );
  }
}
//  Nach em Öffnen des Port muss sofort der Regler ausgelesen werden, sonst
//  sendet er asynchrone Daten!
if ($HF2211) {
  // HF2211 WLAN Gateway wird benutzt
  $USB = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 5 ); // 5 Sekunden Timeout
  if ( $USB === false ) {
    Log::write( "Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
    Log::write( "Exit.... ", "XX ", 3 );
    goto Ausgang;
  }
}
else {
  $USB = USB::openUSB( $USBRegler );
  if (!is_resource( $USB )) {
    Log::write( "USB Port kann nicht geöffnet werden. [1]", "XX ", 7 );
    Log::write( "Exit.... ", "XX ", 7 );
    goto Ausgang;
  }
}
stream_set_blocking( $USB, false );

/************************************************************************************
//  Sollen Befehle an den Wechselrichter gesendet werden?
//
************************************************************************************/
if (file_exists( "/var/www/pipe/".$GeraeteNummer.".befehl.steuerung" )) {
  Log::write( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' vorhanden----", "|- ", 5 );
  $Inhalt = file_get_contents( "/var/www/pipe/".$GeraeteNummer.".befehl.steuerung" );
  $Befehle = explode( "\n", trim( $Inhalt ));
  Log::write( "Befehle: ".print_r( $Befehle, 1 ), "|- ", 9 );
  for ($i = 0; $i < count( $Befehle ); $i++) {
    if ($i >= 4) {
      //  Es werden nur maximal 5 Befehle pro Datei verarbeitet!
      break;
    }

    /**************************************************************************
    //  In der Datei "befehle.ini.php" müssen alle gültigen Befehle aufgelistet
    //  werden, die man benutzen möchte.
    //  Achtung! Genau darauf achten, dass der Befehl richtig geschrieben wird,
    //  damit das Gerät keinen Schaden nimmt.
    //  QPI ist nur zum Testen ...
    //  Siehe Dokument:  Befehle_senden.pdf
    **************************************************************************/
    if (file_exists( $basedir."/config/befehle.ini" )) {
      Log::write( "Die Befehlsliste 'befehle.ini.php' ist vorhanden----", "|- ", 9 );
      $INI_File = parse_ini_file( $basedir."/config/befehle.ini", true );
      $Regler7 = $INI_File["Regler7"];
      Log::write( "Befehlsliste: ".print_r( $Regler7, 1 ), "|- ", 10 );
      foreach ($Regler7 as $Template) {
        $Subst = $Befehle[$i];
        $l = strlen( $Template );
        for ($p = 1; $p < $l;++$p) {
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
    $Wert = false;
    $Antwort = "";
    //  Besonderheiten bei der Prüfsumme korrigieren
    //  ---------------------------------------------
    $CRC_raw = str_pad(dechex(Utils::CRC16Normal( $Befehle[$i] )), 4, "0", STR_PAD_LEFT );
    // $CRC_raw = dechex( Utils::CRC16Normal( $Befehle[$i] )); // geändert 4.2.2023
    if (substr( $CRC_raw, 0, 2 ) == "0a") {
      $CRC_raw = "0b".substr( $CRC_raw, 2, 2 );
    }
    elseif (substr( $CRC_raw, 0, 2 ) == "0d") {
      $CRC_raw = "0e".substr( $CRC_raw, 2, 2 );
    }
    if (substr( $CRC_raw, 2, 2 ) == "0a") {
      $CRC_raw = substr( $CRC_raw, 0, 2 )."0b";
    }
    elseif (substr( $CRC_raw, 2, 2 ) == "0d") {
      $CRC_raw = substr( $CRC_raw, 0, 2 )."0e";
    }
    $CRC = Utils::hex2str( $CRC_raw );
    if (strlen( $Befehle[$i] ) > 6) {
      fputs( $USB, substr( $Befehle[$i], 0, 4 ));
      usleep( 2000 );
      fputs( $USB, substr( $Befehle[$i], 4 ));
    }
    else {
      fputs( $USB, $Befehle[$i] );
    }
    usleep( 2000 );
    fputs( $USB, $CRC."\r" );
    for ($k = 1; $k < 200; $k++) {
      $rc = fgets( $USB, 4096 ); // 4096
      usleep( 20000 ); // 20000 ist ein guter Wert.  6.8.2019
      $Antwort .= trim( $rc, "\0" );
      if (substr( $Antwort, - 1 ) == "\r" and substr( $Antwort, 0, 1 ) == "(") {
        if (substr( $Antwort, 1, 3 ) == "NAK") {
          Log::write( "NAK empfangen: ".strtoupper( Utils::string2hex( $Antwort )), "    ", 7 );
          $rc = "";
          $Wert = false;
          break;
        }
        if (substr( $Antwort, 1, 3 ) == "ACK") {
          Log::write( "ACK empfangen: ".strtoupper( Utils::string2hex( $Antwort )), "    ", 8 );
          $rc = "";
          $Wert = true;
          break;
        }
        else {
          $Wert = true;
        }
        $rc = "";
        break;
      }
    }
    if ($Wert === false) {
      Log::write( "Befehlsausführung erfolglos! ".Utils::string2hex( $Befehle[$i].$CRC."\r" ), "    ", 7 );
      Log::write( "receive: ".strtoupper( Utils::string2hex( $Antwort )), "    ", 9 );
    }
    if ($Wert === true) {
      Log::write( "Befehl ".$Befehle[$i]." erfolgreich gesendet!", "    ", 7 );
    }
  }
  $rc = unlink( "/var/www/pipe/".$GeraeteNummer.".befehl.steuerung" );
  if ($rc) {
    Log::write( "Datei  /pipe/".$GeraeteNummer.".befehl.steuerung  gelöscht.", "    ", 8 );
  }
}
else {
  Log::write( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' nicht vorhanden----", "|- ", 9 );
}

/*******************************************************************************
//
//  Befehle senden Ende
//
//  Hier beginnt das Auslesen der Daten
//
*******************************************************************************/
$i = 1;
do {
  $Wert = false;
  $Antwort = "";
  // fputs( $USB, "\0" );
  $rc = fgets( $USB, 4096 ); // Alte Daten löschen
  $CRC = Utils::hex2str( dechex( Utils::CRC16Normal( "QMOD" )));
  fputs( $USB, "QMOD".$CRC."\r" );
  usleep( 1000 ); //  [normal 1000] Es dauert etwas, bis die ersten Daten kommen ...
  for ($k = 1; $k < 200; $k++) {
    $rc = fgets( $USB, 4096 ); // 4096
    usleep( 10000 );
    $Antwort .= trim( $rc, "\0" );
    if (substr( $Antwort, - 1 ) == "\r" and substr( $Antwort, 0, 1 ) == "(") {
      if (substr( $Antwort, 1, 3 ) == "NAK") {
        $Wert = false;
      }
      else {
        $Wert = true;
      }
      $rc = "";
      break;
    }
  }
  if ($Wert === false) {
    Log::write( "Datenübertragung vom Wechselrichter war erfolglos! [Modus]\n", "    ", 5 );
    $rc = "";
    if ($i < 5) {
      $i++;
      continue;
    }
    else {
      break;
    }
  }
  if ($Wert === true) {
    $aktuelleDaten["Modus"] = substr( $Antwort, 1, 1 );
    Log::write( "Modus: ".substr( $Antwort, 1, 1 ), "    ", 8 );
    switch ($aktuelleDaten["Modus"]) {

      case "P":
        $aktuelleDaten["DeviceStatus"] = 1;
        break;

      case "S":
        $aktuelleDaten["DeviceStatus"] = 2;
        break;

      case "B":
        $aktuelleDaten["DeviceStatus"] = 3;
        break;

      case "L":
        $aktuelleDaten["DeviceStatus"] = 4;
        break;

      case "F":
        $aktuelleDaten["DeviceStatus"] = 5;
        break;

      case "H":
        $aktuelleDaten["DeviceStatus"] = 6;
        break;

      case "Y":
        $aktuelleDaten["DeviceStatus"] = 7;
        break;

      case "T":
        $aktuelleDaten["DeviceStatus"] = 8;
        break;

      case "D":
        $aktuelleDaten["DeviceStatus"] = 9;
        break;

      case "G":
        $aktuelleDaten["DeviceStatus"] = 10;
        break;

      case "C":
        $aktuelleDaten["DeviceStatus"] = 11;
        break;

      default:
        $aktuelleDaten["DeviceStatus"] = 0;
        break;
    }
  }
  $Wert = false;
  $Antwort = "";
  // Warnungen
  $CRC = Utils::hex2str( dechex( Utils::CRC16Normal( "QPIWS" )));
  fputs( $USB, "QPIWS" );
  usleep( 2000 );
  fputs( $USB, $CRC."\r" );
  usleep( 1000 ); //  [normal 1000] Es dauert etwas, bis die ersten Daten kommen ...
  for ($k = 1; $k < 200; $k++) {
    $rc = fgets( $USB, 4096 ); // 4096
    usleep( 10000 );
    $Antwort .= trim( $rc, "\0" );
    if (substr( $Antwort, - 1 ) == "\r" and substr( $Antwort, 0, 1 ) == "(") {
      if (substr( $Antwort, 1, 3 ) == "NAK") {
        $Wert = false;
      }
      else {
        $Wert = true;
      }
      $rc = "";
      break;
    }
  }
  if ($Wert === false) {
    Log::write( "Datenübertragung vom Wechselrichter war erfolglos! [Warnungen]\n", "    ", 6 );
    $rc = "";
    if ($i < 5) {
      $i++;
      continue;
    }
    else {
      break;
    }
  }
  if ($Wert === true) {
    $aktuelleDaten["Warnungen"] = substr( $Antwort, 1, 32 );
    Log::write( "Warnungen: ".substr( $Antwort, 1, 32 ), "    ", 8 );
    if (substr( $aktuelleDaten["Warnungen"], 0, 1 ) == "1") {
    }
    if (substr( $aktuelleDaten["Warnungen"], 1, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Inverter fault";
      if (substr( $aktuelleDaten["Warnungen"], 9, 1 ) == "1") {
        $aktuelleDaten["Fehlermeldung"] = "Over temperature";
      }
      if (substr( $aktuelleDaten["Warnungen"], 10, 1 ) == "1") {
        $aktuelleDaten["Fehlermeldung"] = "Fan locked";
      }
      if (substr( $aktuelleDaten["Warnungen"], 11, 1 ) == "1") {
        $aktuelleDaten["Fehlermeldung"] = "Battery voltage high";
      }
      if (substr( $aktuelleDaten["Warnungen"], 16, 1 ) == "1") {
        $aktuelleDaten["Fehlermeldung"] = "Over load";
      }
    }
    if (substr( $aktuelleDaten["Warnungen"], 2, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Bus Over";
    }
    if (substr( $aktuelleDaten["Warnungen"], 3, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Bus Under";
    }
    if (substr( $aktuelleDaten["Warnungen"], 4, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Bus Soft Fail";
    }
    if (substr( $aktuelleDaten["Warnungen"], 7, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Inverter voltage too low";
    }
    if (substr( $aktuelleDaten["Warnungen"], 8, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Inverter voltage too high";
    }
    if (substr( $aktuelleDaten["Warnungen"], 18, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Inverter Over Current";
    }
    if (substr( $aktuelleDaten["Warnungen"], 19, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Inverter Soft Fail";
    }
    if (substr( $aktuelleDaten["Warnungen"], 20, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "SelfTest Fail";
    }
    if (substr( $aktuelleDaten["Warnungen"], 21, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "OP DC Voltage Over";
    }
    if (substr( $aktuelleDaten["Warnungen"], 22, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Bat Open";
    }
    if (substr( $aktuelleDaten["Warnungen"], 23, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Current Sensor Fail";
    }
    if (substr( $aktuelleDaten["Warnungen"], 24, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Battery Short";
    }
  }
  if (1 == 1) {
    //  Wird jetzt immer ausgelesen.
    //  Keine Speicherung der Daten in der Influx Datenbank.
    //  Inverter rated information inquiry   QPIRI
    $Wert = false;
    $Antwort = "";
    // fputs( $USB, "\0" );
    $rc = fgets( $USB, 4096 ); // Alte Daten löschen
    $CRC = Utils::hex2str( dechex( Utils::CRC16Normal( "QPIRI" )));
    fputs( $USB, "QPIRI" );
    usleep( 2000 );
    fputs( $USB, $CRC."\r" );
    usleep( 1000 ); //  [normal 10000] Es dauert etwas, bis die ersten Daten kommen ...
    for ($k = 1; $k < 200; $k++) {
      $rc = fgets( $USB, 4096 ); // 4096
      usleep( 10000 ); // Die Effekta Geraete sind so langsam...
      $Antwort .= trim( $rc, "\0" );
      if (substr( $Antwort, - 1 ) == "\r" and substr( $Antwort, 0, 1 ) == "(") {
        if (substr( $Antwort, 1, 3 ) == "NAK") {
          $Wert = false;
          $Antwort = substr( $Antwort, 0, 4 ); // Den CRC abschneiden
        }
        else {
          $Wert = true;
        }
        $rc = "";
        break;
      }
    }
    if ($Wert === false) {
      Log::write( "Datenübertragung vom Wechselrichter war erfolglos! Continue..\n", "    ", 6 );
      $rc = "";
      if ($i < 5) {
        $i++;
        continue;
      }
      else {
        break;
      }
    }
    Log::write( substr( $Antwort, 1, 98 )."  i: ".$k, "    ", 9 );
    if ($Wert === true) {
      $Teile = explode( " ", substr( $Antwort, 1, 98 ));
      $aktuelleDaten["Set_Grid_rating_voltage"] = $Teile[0];
      $aktuelleDaten["Set_Grid_rating_current"] = $Teile[1];
      $aktuelleDaten["Set_AC_output_rating_voltage"] = $Teile[2];
      $aktuelleDaten["Set_AC_output_rating_frequency"] = $Teile[3];
      $aktuelleDaten["Set_AC_output_rating_current"] = $Teile[4];
      $aktuelleDaten["Set_AC_output_rating_apparent_power"] = $Teile[5];
      $aktuelleDaten["Set_AC_output_rating_active_power"] = $Teile[6];
      $aktuelleDaten["Set_Battery_rating_voltage"] = $Teile[7];
      $aktuelleDaten["Set_Battery_re-charge_voltage"] = $Teile[8];
      $aktuelleDaten["Set_Battery_under_voltage"] = $Teile[9];
      $aktuelleDaten["Set_Battery_bulk_voltage"] = $Teile[10];
      $aktuelleDaten["Set_Battery_float_voltage"] = $Teile[11];
      $aktuelleDaten["Set_Battery_type"] = $Teile[12];
      $aktuelleDaten["Set_Current_max_AC_charging_current"] = $Teile[13];
      $aktuelleDaten["Set_Current_max_charging_current"] = $Teile[14];
      $aktuelleDaten["Set_Input_voltage_range"] = $Teile[15];
      $aktuelleDaten["Set_Output_source_priority"] = $Teile[16];
      $aktuelleDaten["Set_Charger_source_priority"] = $Teile[17];
      $aktuelleDaten["Set_Parallel_max_number"] = $Teile[18];
      $aktuelleDaten["Set_Machine_type"] = $Teile[19];
      $aktuelleDaten["Set_Topology"] = $Teile[20];
      $aktuelleDaten["Set_Output_mode"] = $Teile[21];
      $aktuelleDaten["Set_Battery_re-discharger_voltage"] = $Teile[22];
      $aktuelleDaten["Set_PV_OK_condition_for_parallel"] = $Teile[23];
      $aktuelleDaten["Set_PV_power_balance"] = substr( $Teile[24], 0, 1 );
      if (isset($Teile[25])) {
        $aktuelleDaten["Set_Max_charging_time_at_CV_stage"] = $Teile[25];
      }
      if (isset($Teile[26])) {
        $aktuelleDaten["Set_Operation_Logic"] = $Teile[26];
      }
    }
  }

  // Daten
  $Wert = false;
  $Antwort = "";
  // fputs( $USB, "\0" );
  $rc = fgets( $USB, 4096 ); // Alte Daten löschen
  $CRC = Utils::hex2str( dechex( Utils::CRC16Normal( "QPIGS" )));
  fputs( $USB, "QPIGS" );
  usleep( 2000 );
  fputs( $USB, $CRC."\r" );
  usleep( 1000 ); //  [normal 10000] Es dauert etwas, bis die ersten Daten kommen ...
  for ($k = 1; $k < 200; $k++) {
    $rc = fgets( $USB, 4096 ); // 4096
    usleep( 10000 ); // Die Effekta Geraete sind so langsam...
    $Antwort .= trim( $rc, "\0" );
    if (substr( $Antwort, - 1 ) == "\r" and substr( $Antwort, 0, 1 ) == "(") {
      if (substr( $Antwort, 1, 3 ) == "NAK") {
        $Wert = false;
        $Antwort = substr( $Antwort, 0, 4 ); // Den CRC abschneiden
      }
      else {
        $Wert = true;
      }
      $rc = "";
      break;
    }
  }
  if ($Wert === false) {
    Log::write( "Datenübertragung vom Wechselrichter war erfolglos! Continue..\n", "    ", 6 );
    $rc = "";
    if ($i < 5) {
      $i++;
      continue;
    }
    else {
      break;
    }
  }
  Log::write( substr( $Antwort, 1, 106 )."  i: ".$k, "    ", 6 );
  if ($Wert === true and strlen( $Antwort ) > 106) {
    $Teile = explode( " ", substr( $Antwort, 1, 106 ));
    $aktuelleDaten["Netzspannung"] = $Teile[0] + 0;
    $aktuelleDaten["Netzfrequenz"] = $Teile[1] + 0;
    $aktuelleDaten["AC_Ausgangsspannung"] = $Teile[2] + 0;
    $aktuelleDaten["AC_Ausgangsfrequenz"] = $Teile[3] + 0;
    $aktuelleDaten["AC_Scheinleistung"] = $Teile[4] + 0;
    $aktuelleDaten["AC_Wirkleistung"] = $Teile[5] + 0;
    $aktuelleDaten["AC_Leistung"] = $Teile[5] + 0; // Wird für die HomeMatic gebraucht.
    $aktuelleDaten["Ausgangslast"] = $Teile[6] + 0;
    $aktuelleDaten["Batteriespannung"] = $Teile[8] + 0;
    $aktuelleDaten["Batterieladestrom"] = $Teile[9] + 0;
    $aktuelleDaten["Batteriekapazitaet"] = $Teile[10] + 0;
    $aktuelleDaten["Temperatur"] = $Teile[11] + 0;
    $aktuelleDaten["Regler"] = $Regler;
    $aktuelleDaten["Produkt"] = "5000";
    $aktuelleDaten["Solarstrom"] = $Teile[12] + 0;
    $aktuelleDaten["Solarspannung"] = $Teile[13] + 0;
    $aktuelleDaten["Batterieentladestrom"] = $Teile[15] + 0;
    $aktuelleDaten["Optionen"] = $Teile[16];
    $aktuelleDaten["Firmware"] = $Teile[18]; //  EEPROM Version
    $aktuelleDaten["ErrorCodes"] = "0";
    $aktuelleDaten["Solarleistung"] = $Teile[19] + 0;
    $aktuelleDaten["Geraetestatus"] = $Teile[20];
    if ($aktuelleDaten["Set_Machine_type"] == "11" or $aktuelleDaten["Set_Machine_type"] == "20") {
      // Nur wenn 2 Stränge vorhanden sind.
      // Daten
      $Wert = false;
      $Antwort = "";
      // fputs( $USB, "\0" );
      $rc = fgets( $USB, 4096 ); // Alte Daten löschen
      $CRC = Utils::hex2str( dechex( Utils::CRC16Normal( "QPIGS2" )));
      fputs( $USB, "QPIGS2" );
      usleep( 2000 );
      fputs( $USB, $CRC."\r" );
      usleep( 1000 ); //  [normal 10000] Es dauert etwas, bis die ersten Daten kommen ...
      for ($k = 1; $k < 200; $k++) {
        $rc = fgets( $USB, 4096 ); // 4096
        usleep( 10000 ); // Die Effekta Geraete sind so langsam...
        $Antwort .= trim( $rc, "\0" );
        if (substr( $Antwort, - 1 ) == "\r" and substr( $Antwort, 0, 1 ) == "(") {
          if (substr( $Antwort, 1, 3 ) == "NAK") {
            $Wert = false;
            $Antwort = substr( $Antwort, 0, 4 ); // Den CRC abschneiden
          }
          else {
            $Wert = true;
          }
          $rc = "";
          break;
        }
      }
      Log::write( substr( $Antwort, 1, 106 )."  i: ".$k, "    ", 8 );
      if ($Wert === true and strlen( $Antwort ) > 40) {
        $Teile = explode( " ", substr( $Antwort, 1, 40 ));
        $aktuelleDaten["PV1_Spannung"] = $aktuelleDaten["Solarspannung"];
        $aktuelleDaten["PV1_Strom"] = $aktuelleDaten["Solarstrom"];
        $aktuelleDaten["PV1_Leistung"] = $aktuelleDaten["Solarleistung"];
        $aktuelleDaten["PV2_Spannung"] = $Teile[1] + 0;
        $aktuelleDaten["PV2_Strom"] = $Teile[0] + 0;
        $aktuelleDaten["PV2_Leistung"] = $Teile[3] + 0;
        $aktuelleDaten["Solarleistung"] = $aktuelleDaten["Solarleistung"] + $Teile[3];
      }
    }
    if ($aktuelleDaten["Firmware"] == "04") {
      $aktuelleDaten["Temperatur"] = ($aktuelleDaten["Temperatur"] / 10);
    }

    /****************************************************************************
    //  Hier wird der Ladestatus errechnet.
    //  10 = Solarladung
    //  11 = Netzladung
    //  12 = Netz und Solarladung
    ****************************************************************************/
    if (substr( $aktuelleDaten["Optionen"], 5, 1 ) == "1" and substr( $aktuelleDaten["Optionen"], 6, 1 ) == "1" and substr( $aktuelleDaten["Optionen"], 7, 1 ) == "1") {
      $aktuelleDaten["Ladestatus"] = 12;
    }
    elseif (substr( $aktuelleDaten["Optionen"], 5, 1 ) == "1" and substr( $aktuelleDaten["Optionen"], 6, 1 ) == "1") {
      $aktuelleDaten["Ladestatus"] = 10;
    }
    elseif (substr( $aktuelleDaten["Optionen"], 5, 1 ) == "1" and substr( $aktuelleDaten["Optionen"], 7, 1 ) == "1") {
      $aktuelleDaten["Ladestatus"] = 11;
    }
    else {
      $aktuelleDaten["Ladestatus"] = 0;
    }

    /**************************************************************************
    //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
    //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
    //  Die Funktion ist noch nicht überall implementiert.
    **************************************************************************/
    $FehlermeldungText = "";
    if ($aktuelleDaten["Modus"] == "F") {
      $FehlermeldungText = "Der Wechselrichter meldet einen Fehler. Bitte prüfen.";
      Log::write( $FehlermeldungText, "** ", 1 );
      Log::write( $aktuelleDaten["Fehlermeldung"], "** ", 1 );
      if (isset($aktuelleDaten["Fehlermeldung"])) {
        $FehlermeldungText = $aktuelleDaten["Fehlermeldung"];
      }
    }
    if (isset($aktuelleDaten["Fehlermeldung"])) {
      Log::write( "Fehlermeldung: ".$aktuelleDaten["Fehlermeldung"], "   ", 1 );
    }

    /****************************************************************************
    //  Die Daten werden für die Speicherung vorbereitet.
    ****************************************************************************/
    $aktuelleDaten["Regler"] = $Regler;
    $aktuelleDaten["Objekt"] = $Objekt;
    $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
    if ($i == 1)
      Log::write( print_r( $aktuelleDaten, 1 ), "   ", 8 );

    /**************************************************************************
    //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
    //  an den mqtt-Broker Mosquitto gesendet.
    //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
    **************************************************************************/
    if ($MQTT and strtoupper($MQTTAuswahl) != "OPENWB") {
      Log::write( "MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1 );
      require($basedir."/services/mqtt_senden.php");
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

    /**************************************************************************
    //  User PHP Script, falls gewünscht oder nötig
    **************************************************************************/
    if (file_exists($basedir."/custom/ax_wechselrichter_math.php" )) {
      include $basedir.'/custom/ax_wechselrichter_math.php'; // Falls etwas neu berechnet werden muss.
    }

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
    //  Es kann auch nichts abgespeichert werden.
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
    elseif ($InfluxDB_local) {
      $rc = InfluxDB::influx_local( $aktuelleDaten );
    }
  }
  if (is_file( $basedir."/config/1.user.config.php" )) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (7 - (time( ) - $Start));
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
} while (($Start + 56) > time( ));
stream_set_blocking( $USB, true );
if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {

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
  Log::write( "Solarleistung: ".$aktuelleDaten["Solarleistung"]." Watt -  WattstundenGesamtHeute: ".round( $whProTag, 2 ), "   ", 5 );
}
Ausgang:Log::write( "---------   Stop   ax_wechselrichter.php    ------------------ ", "|--", 6 );
return;
?>