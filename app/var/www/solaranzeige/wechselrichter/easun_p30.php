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
//  Es dient dem Auslesen des EASUN Geräten ähnlich der AX-Serie von Effekta
//  Serieller Adapter mit 2400 Baud!
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//  Protokoll P16  Protokoll ID = 30     QPI = 30
//
//  Achtung! Der Regler sendet zwischendurch immer wieder asynchrone Daten!
//
*****************************************************************************/
$Tracelevel = 7; //  1 bis 10  10 = Debug
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$RemoteDaten = true;
$Start = time( ); // Timestamp festhalten
Log::write( "--------------   Start  easun_p30.php   ------------------------ ", "|--", 6 );
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
$StatusFile = $basedir."/database/".$GeraeteNummer.".WhProTag_easun.txt";
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
  // HF2211 WLAN Gateway wird benutzt (Noch nicht getestet 8.4.2021)
  $USB = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 15 ); // 5 Sekunden Timeout
  if ($USB === false) {
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
      $Regler26 = $INI_File["Regler26"];
      Log::write( "Befehlsliste: ".print_r( $Regler26, 1 ), "|- ", 10 );
      foreach ($Regler26 as $Template) {
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
    if (strlen( $Befehle[$i] ) > 8) {
      fputs( $USB, substr( $Befehle[$i], 0, 8 ));
      usleep( 2000 );
      fputs( $USB, substr( $Befehle[$i], 8 ).$CRC."\r" );
    }
    else {
      fputs( $USB, $Befehle[$i].$CRC."\r" );
    }
    usleep( 20000 );
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
$Zeit1 = 400000; // Normal = 40000 Die Serielle Schnittstelle hat nur 2400 Baud
$Zeit2 = 100000; // Normal = 10000
$i = 1;
do {
  //  QPI     QPI     QPI     QPI     QPI     QPI     QPI     QPI     QPI
  $Wert = false;
  $rc = fgets( $USB, 4096 ); // Alte Daten löschen
  usleep( 1000 );
  $CRC = Utils::hex2str( dechex( Utils::CRC16Normal( "QPI" )));
  fputs( $USB, "QPI".$CRC."\r" );
  usleep( $Zeit1 ); //  Es dauert etwas, bis die ersten Daten kommen ...
  Log::write( "Befehl: QPI\r", "    ", 9 );
  $Antwort = "";
  for ($k = 1; $k < 200; $k++) {
    $rc = fgets( $USB, 4096 ); // 4096
    usleep( $Zeit2 );
    $Antwort .= trim( $rc, "\0" );
    if (substr( $Antwort, - 1 ) == "\r" and substr( $Antwort, 0, 1 ) == "(") {
      Log::write( "Antwort: ".bin2hex( $rc ), "    ", 9 );
      if (substr( $Antwort, 1, 3 ) == "NAK") {
        $Wert = false;
        Log::write( "Wechselrichter Antwortet mit NAK!", "    ", 5 );
      }
      else {
        $aktuelleDaten["Protokoll"] = substr( $Antwort, 3, 2 );
        Log::write( "Protokoll: ".substr( $Antwort, 3, 2 ), "    ", 7 );
        $Wert = true;
      }
      $rc = "";
      break;
    }
  }
  if ($Wert === false) {
    Log::write( "Datenübertragung vom Wechselrichter war erfolglos!", "    ", 9 );
    $rc = "";
  }
  //  QMOD     QMOD     QMOD     QMOD     QMOD     QMOD     QMOD     QMOD
  $CRC = Utils::hex2str( dechex( Utils::CRC16Normal( "QMOD" )));
  fputs( $USB, "QMOD".$CRC."\r" );
  usleep( $Zeit1 ); // Es dauert etwas, bis die ersten Daten kommen ...
  $Antwort = "";
  Log::write( "Befehl: QMOD\r", "    ", 9 );
  for ($k = 1; $k < 200; $k++) {
    $rc = fgets( $USB, 4096 ); // 4096
    usleep( $Zeit2 );
    $Antwort .= trim( $rc, "\0" );
    //  P = Power Mode
    //  S = Standbay Mode
    //  Y = Bypass Mode
    //  B = Batterie Mode
    //  T = Batterie Test Mode
    //  F = Fehler Mode
    //  E = ECO Mode
    //  C = Converter Mode
    //  D = Shutdown Mode
    if (substr( $Antwort, - 1 ) == "\r" and substr( $Antwort, 0, 1 ) == "(") {
      Log::write( "Antwort: ".$Antwort, "    ", 9 );
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
    Log::write( "Datenübertragung vom Wechselrichter war erfolglos! [Modus]", "    ", 9 );
    $rc = "";
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

      case "E":
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
  //  QPIWS     QPIWS     QPIWS     QPIWS     QPIWS     QPIWS     QPIWS     QPIWS
  $Wert = false;
  $Antwort = "";
  // Warnungen
  $CRC = Utils::hex2str( dechex( Utils::CRC16Normal( "QPIWS" )));
  fputs( $USB, "QPIWS".$CRC."\r" );
  usleep( $Zeit1 ); // Es dauert etwas, bis die ersten Daten kommen ...
  for ($k = 1; $k < 200; $k++) {
    $rc = fgets( $USB, 4096 ); // 4096
    usleep( $Zeit2 );
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
    Log::write( "Datenübertragung vom Wechselrichter war erfolglos! [Warnungen]", "    ", 9 );
    $rc = "";
  }
  if ($Wert === true) {
    $aktuelleDaten["Warnungen"] = substr( $Antwort, 1, 32 );
    Log::write( "Warnungen: ".substr( $Antwort, 1, 32 ), "    ", 8 );
    $aktuelleDaten["Fehlermeldung"] = "Alles in Ordnung.";
    if (substr( $aktuelleDaten["Warnungen"], 0, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Keine Sonne";
    }
    if (substr( $aktuelleDaten["Warnungen"], 1, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Wechselrichterfehler";
    }
    if (substr( $aktuelleDaten["Warnungen"], 2, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "BUS Überspannung";
    }
    if (substr( $aktuelleDaten["Warnungen"], 3, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "BUS Unterspannung";
    }
    if (substr( $aktuelleDaten["Warnungen"], 4, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "BUS Fehler";
    }
    if (substr( $aktuelleDaten["Warnungen"], 5, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Netzfehler";
    }
    if (substr( $aktuelleDaten["Warnungen"], 6, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "OPVShort";
    }
    if (substr( $aktuelleDaten["Warnungen"], 7, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "WR-Spannung zu niedrig";
    }
    if (substr( $aktuelleDaten["Warnungen"], 8, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "WR-Spannung zu hoch";
    }
    if (substr( $aktuelleDaten["Warnungen"], 9, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Temperatur zu hoch";
    }
    if (substr( $aktuelleDaten["Warnungen"], 10, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Lüfter blockiert";
    }
    if (substr( $aktuelleDaten["Warnungen"], 11, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Batteriespannung zu hoch";
    }
    if (substr( $aktuelleDaten["Warnungen"], 12, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Batteriespannung zu niedrig";
    }
    if (substr( $aktuelleDaten["Warnungen"], 13, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "unbekannt";
    }
    if (substr( $aktuelleDaten["Warnungen"], 14, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Battery under shutdown";
    }
    if (substr( $aktuelleDaten["Warnungen"], 15, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Batterie derating";
    }
    if (substr( $aktuelleDaten["Warnungen"], 16, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Over load";
    }
    if (substr( $aktuelleDaten["Warnungen"], 17, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "EEPROM Fehler";
    }
    if (substr( $aktuelleDaten["Warnungen"], 18, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Inverter over current";
    }
    if (substr( $aktuelleDaten["Warnungen"], 19, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Wechselrichter Fehler";
    }
    if (substr( $aktuelleDaten["Warnungen"], 20, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Testfehler";
    }
    if (substr( $aktuelleDaten["Warnungen"], 21, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "OP DC Voltage Over";
    }
    if (substr( $aktuelleDaten["Warnungen"], 22, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Batterie nicht angeschlossen";
    }
    if (substr( $aktuelleDaten["Warnungen"], 23, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Stromsensor Fehler";
    }
    if (substr( $aktuelleDaten["Warnungen"], 24, 1 ) == "1") {
      $aktuelleDaten["Fehlermeldung"] = "Batterie Kurzschluss";
    }
  }
  //  QID     QID     QID     QID     QID     QID     QID     QID     QID
  $Wert = false;
  $Antwort = "";
  $rc = fgets( $USB, 4096 ); // Alte Daten löschen
  $CRC = Utils::hex2str( dechex( Utils::CRC16Normal( "QID" )));
  fputs( $USB, "QID".$CRC."\r" );
  usleep( $Zeit1 ); // Es dauert etwas, bis die ersten Daten kommen ...
  for ($k = 1; $k < 200; $k++) {
    $rc = fgets( $USB, 4096 ); // 4096
    usleep( $Zeit2 ); // Die EASUN Geraete sind so langsam...
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
    Log::write( "Datenübertragung vom Wechselrichter war erfolglos! [Seriennummer]", "    ", 9 );
    $rc = "";
  }
  $aktuelleDaten["Seriennummer"] = substr( $Antwort, 1, 14 );
  // QPIRI    QPIRI    QPIRI    QPIRI    QPIRI    QPIRI    QPIRI    QPIRI    QPIRI
  $Wert = false;
  $Antwort = "";
  $rc = fgets( $USB, 4096 ); // Alte Daten löschen
  $CRC = Utils::hex2str( dechex( Utils::CRC16Normal( "QPIRI" )));
  fputs( $USB, "QPIRI".$CRC."\r" );
  usleep( $Zeit1 ); //  Es dauert etwas, bis die ersten Daten kommen ...
  for ($k = 1; $k < 100; $k++) {      // 200 war vorher 2.11.2022
    $rc = fgets( $USB, 4096 ); // 4096
    usleep( $Zeit2 );
    $Antwort .= trim( $rc, "\0" );
    // echo $Antwort."\n";
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
    Log::write( "Datenübertragung vom Wechselrichter war erfolglos! [Standardwerte]", "    ", 9 );
    $rc = "";
  }
  Log::write( substr( $Antwort, 1, - 3 )."  i: ".$k." Länge: ".strlen($Antwort), "    ", 7 );
  if ($Wert === true and strlen( $Antwort ) >= 97) {
    $Teile = explode( " ", substr( $Antwort, 1, - 3 ));
    $aktuelleDaten["Nennspannung"] = floatval($Teile[0]);
    $aktuelleDaten["Nennstrom"] = floatval($Teile[1]);
    $aktuelleDaten["AC_Nennspannung"] = floatval($Teile[2]);
    $aktuelleDaten["AC_Nennfrequenz"] = floatval($Teile[3]);
    $aktuelleDaten["AC_Nennstrom"] = floatval($Teile[4]);
    $aktuelleDaten["AC_Scheinnennleistung"] = floatval($Teile[5]);
    $aktuelleDaten["AC_Wirknennleistung"] = floatval($Teile[6]);
    $aktuelleDaten["Batterienennspannung."] = floatval($Teile[7]);
    $aktuelleDaten["Batterienachladung"] = floatval($Teile[8]);
    $aktuelleDaten["Batterieunterspannung"] = floatval($Teile[9]);
    $aktuelleDaten["Batterieladespannung"] = floatval($Teile[10]);
    $aktuelleDaten["Erhaltungsladung"] = floatval($Teile[11]);
    $aktuelleDaten["Batterietyp"] = floatval($Teile[12]);
    $aktuelleDaten["MaxNetzLadung"] = floatval($Teile[13]);
    $aktuelleDaten["MaxPVLadung"] = floatval($Teile[14]);
    $aktuelleDaten["InputVoltageRange"] = floatval($Teile[15]);
    $aktuelleDaten["OutputPriority"] = floatval($Teile[16]);
    $aktuelleDaten["Ladestatus"] = floatval($Teile[17]);
    $aktuelleDaten["MaxParallelGeraete"] = floatval($Teile[18]);
    $aktuelleDaten["Maschinentyp"] = $Teile[19];
    $aktuelleDaten["Topologie"] = $Teile[20];
    $aktuelleDaten["OutputMode"] = $Teile[21];
    $aktuelleDaten["BatterieMinSpannung"] = floatval($Teile[22]);
  }
  // QPIGS     QPIGS     QPIGS     QPIGS     QPIGS     QPIGS     QPIGS
  $Wert = false;
  $Antwort = "";
  $rc = fgets( $USB, 4096 ); // Alte Daten löschen
  $CRC = Utils::hex2str( dechex( Utils::CRC16Normal( "QPIGS" )));
  fputs( $USB, "QPIGS".$CRC."\r" );
  usleep( $Zeit1 ); //  dauert etwas, bis die ersten Daten kommen ...
  for ($k = 1; $k < 100; $k++) {       // 200 war vorher 2.11.2022
    $rc = fgets( $USB, 4096 ); // 4096
    usleep( $Zeit2 ); // Die EASUN Geraete sind so langsam...
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
    Log::write( "Datenübertragung vom Wechselrichter war erfolglos! [Daten]", "    ", 9 );
    $rc = "";
  }
  Log::write( substr( $Antwort, 1, -3 )."  i: ".$k." Länge: ".strlen($Antwort), "    ", 8 );
  if ($Wert === true and strlen( $Antwort ) == 110) {
    $Teile = explode( " ", substr( $Antwort, 1, - 3 ));
    $aktuelleDaten["Netzspannung"] = floatval($Teile[0]);
    $aktuelleDaten["Netzfrequenz"] = floatval($Teile[1]);
    $aktuelleDaten["AC_Ausgangsspannung"] = floatval($Teile[2]);
    $aktuelleDaten["AC_Ausgangsfrequenz"] = floatval($Teile[3]);
    $aktuelleDaten["AC_Scheinleistung"] = floatval($Teile[4]);
    $aktuelleDaten["AC_Wirkleistung"] = floatval($Teile[5]);
    $aktuelleDaten["Ausgangslast"] = floatval($Teile[6]);
    $aktuelleDaten["BUSspannung"] = floatval($Teile[7]);
    $aktuelleDaten["Batteriespannung"] = floatval($Teile[8]);
    $aktuelleDaten["Batterieladestrom"] = floatval($Teile[9]);
    $aktuelleDaten["Batteriekapazitaet"] = floatval($Teile[10]);
    $aktuelleDaten["Temperatur"] = (floatval($Teile[11]) / 10);
    $aktuelleDaten["Solarstrom"] = floatval($Teile[12]);
    $aktuelleDaten["Solarspannung"] = floatval($Teile[13]);
    $aktuelleDaten["BatterieSCCSpannung"] = floatval($Teile[14]);
    $aktuelleDaten["Batterieentladestrom"] = floatval($Teile[15]);
    $aktuelleDaten["Optionen"] = $Teile[16];
    $aktuelleDaten["BatterieOffsetFan"] = $Teile[17];
    $aktuelleDaten["EEPROM"] = $Teile[18];
    $aktuelleDaten["Solarleistung"] = floatval($Teile[19]);
    $aktuelleDaten["Status"] = $Teile[20];
  }
  // QPIGS2     QPIGS2     QPIGS2     QPIGS2     QPIGS2     QPIGS2     QPIGS2
  $Wert = false;
  $Antwort = "";
  $rc = fgets( $USB, 4096 ); // Alte Daten löschen
  $CRC = Utils::hex2str( dechex( Utils::CRC16Normal( "QPIGS2" )));
  fputs( $USB, "QPIGS2".$CRC."\r" );
  usleep( $Zeit1 ); //  dauert etwas, bis die ersten Daten kommen ...
  for ($k = 1; $k < 20; $k++) {        // 200 war vorher 2.11.2022
    $rc = fgets( $USB, 4096 ); // 4096
    usleep( $Zeit2 ); // Die EASUN Geraete sind so langsam...
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
    Log::write( "Datenübertragung [QPIGS2] vom Wechselrichter war erfolglos!", "    ", 8 );
    $rc = "";
  }
  Log::write( substr( $Antwort, 1, -3 )."  i: ".$k." Länge: ".strlen($Antwort), "    ", 8 );
  if ($Wert === true and strlen( $Antwort ) == 70) {
    $Teile = explode( " ", substr( $Antwort, 1, - 3 ));
    $aktuelleDaten["Solarstrom2"] = floatval($Teile[0]);
    $aktuelleDaten["Solarspannung2"] = floatval($Teile[1]);
    $aktuelleDaten["Solarleistung2"] = floatval($Teile[2]);
    $aktuelleDaten["Solarleistung1"] = $aktuelleDaten["Solarleistung"];
    $aktuelleDaten["Solarleistung"] = ($aktuelleDaten["Solarleistung1"] + $aktuelleDaten["Solarleistung2"]);
  }
  else {
    Log::write( "Befehl [QPIGS2] gibt es nicht!", "    ", 7 );
  }

  if (empty($aktuelleDaten["Ladestatus"])) {
    $aktuelleDaten["Ladestatus"] = 0;
  }
  if (isset($aktuelleDaten["Fehlermeldung"])) {
    Log::write( "Fehlermeldung: ".$aktuelleDaten["Fehlermeldung"], "   ", 1 );
  }

  // QALL     QALL     QALL     QALL     QALL     QALL     QALL
  $Wert = false;
  $Antwort = "";
  $rc = fgets( $USB, 4096 ); // Alte Daten löschen
  $CRC = Utils::hex2str( dechex( Utils::CRC16Normal( "QALL" )));
  fputs( $USB, "QALL".$CRC."\r" );
  usleep( $Zeit1 ); //  dauert etwas, bis die ersten Daten kommen ...
  for ($k = 1; $k < 20; $k++) {        // 200 war vorher 2.11.2022
    $rc = fgets( $USB, 4096 ); // 4096
    usleep( $Zeit2 ); // Die EASUN Geraete sind so langsam...
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
    Log::write( "Datenübertragung [QALL] vom Wechselrichter war erfolglos!", "    ", 8 );
    $rc = "";
  }
  Log::write( substr( $Antwort, 1, -3 )."  i: ".$k." Länge: ".strlen($Antwort), "    ", 8 );
  if ($Wert === true and strlen( $Antwort ) == 82 ) {
    $Teile = explode( " ", substr( $Antwort, 1, - 3 ));
    // $aktuelleDaten["1"] = $Teile[0];
    // $aktuelleDaten["2"] = $Teile[1];
    // $aktuelleDaten["3"] = $Teile[2];
    // $aktuelleDaten["4"] = $Teile[3];
    $aktuelleDaten["AC_Wirkleistung"] = floatval($Teile[4]);
    $aktuelleDaten["AC_Ausgangslast"] = floatval($Teile[5]);
    $aktuelleDaten["Batteriespannung"] = floatval($Teile[6]);
    $aktuelleDaten["Batteriekapazitaet"] = floatval($Teile[7]);
    $aktuelleDaten["Batterieladestrom"] = floatval($Teile[8]);
    $aktuelleDaten["Batterieentladestrom"] = floatval($Teile[9]);
    $aktuelleDaten["Solarspannung"] = floatval($Teile[10]);
    $aktuelleDaten["Solarstrom"] = floatval($Teile[11]);
    $aktuelleDaten["Solarleistung"] = floatval($Teile[12]);
    $aktuelleDaten["MaxSolarTagesleistung"] = floatval($Teile[13]);
    $aktuelleDaten["MaxSolarGesamtleistung"] = (floatval($Teile[14])*1000);
    // $aktuelleDaten["Mode"] = $Teile[15];
    // $aktuelleDaten["17"] = $Teile[16];
    // $aktuelleDaten["18"] = $Teile[17];
  }



  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Firmware"] = 0;
  $aktuelleDaten["Produkt"] = "Protokoll 30";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  Log::write( var_export( $aktuelleDaten, 1 ), "   ", 8);

  /**************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  **************************************************************************/
  if (file_exists($basedir."/custom/easun_p30_math.php" )) {
    include $basedir.'/custom/easun_p30_math.php'; // Falls etwas neu berechnet werden muss.
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
if (isset($aktuelleDaten["Modus"]) and isset($aktuelleDaten["Regler"])) {

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
  Log::write( "WattstundenGesamtHeute: ".round( $whProTag, 2 ), "   ", 5 );
}
Ausgang:Log::write( "--------------   Stop   easun_p30.php    ----------------------- ", "|--", 6 );
return;
?>