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
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//  Achtung! Der Regler sendet zwischendurch immer wieder asynchrone Daten!
//
*****************************************************************************/
$Tracelevel = 7; //  1 bis 10  10 = Debug
$Device = "WR"; // WR = Wechselrichter
$Uhrzeit = true;
$RemoteDaten = true;
$Version = "";
$Startzeit = time( ); // Timestamp festhalten
Log::write( "-------------   Start  mpi_3phasen_serie.php   --------------- ", "|--", 6 );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
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
Log::write( "Hardware Version: ".$Version, "o  ", 9 );
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
$USB1 = USB::openUSB( $USBRegler );
if (!is_resource( $USB1 )) {
  Log::write( "USB Port kann nicht geöffnet werden. [1]", "XX ", 7 );
  Log::write( "Exit.... ", "XX ", 7 );
  goto Ausgang;
}

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
    if ($i > 10) {
      //  Es werden nur maximal 10 Befehle pro Datei verarbeitet!
      break;
    }

    /*********************************************************************************
    //  In der Datei "befehle.ini.php" müssen alle gültigen Befehle aufgelistet
    //  werden, die man benutzen möchte.
    //  Achtung! Genau darauf achten, dass der Befehl richtig geschrieben wird,
    //  damit das Gerät keinen Schaden nimmt.
    //  QPI ist nur zum Testen ...
    //  Siehe Dokument:  Befehle_senden.pdf
    *********************************************************************************/
    if (file_exists( $basedir."/config/befehle.ini" )) {
      Log::write( "Die Befehlsliste 'befehle.ini.php' ist vorhanden----", "|- ", 9 );
      $INI_File = parse_ini_file( $basedir."/config/befehle.ini", true );
      $Regler9 = $INI_File["Regler9"];
      Log::write( "Befehlsliste: ".print_r( $Regler9, 1 ), "|- ", 10 );
      $Subst = $Befehle[$i];
      foreach ($Regler9 as $Template) {
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

    /************************************************************************
    //  Ab hier wird der Befehl gesendet.
    ************************************************************************/
    $Befehle[$i] = "^S".sprintf( "%03u", strlen( $Befehle[$i] ) + 1 ).$Befehle[$i];
    Log::write( "Befehl zur Ausführung:".$Befehle[$i], "|- ", 3 );
    $RAW_daten = MPI::mpi_usb_lesen( $USB1, $Befehle[$i] );
    if ($RAW_daten === false) {
      Log::write( "Befehl nicht ausgeführt: ".$Befehle[$i], "o  ", 5 );
      Log::write( "Der Befehl wurde abgelehnt.", "o  ", 5 );
      Log::write( "Befehlsausführung abgebrochen", "o  ", 5 );
      break;
    }
    else {
      Log::write( "Befehl ".$Befehle[$i]." erfolgreich gesendet!", "    ", 9 );
      Log::write( "Antwort: ".$RAW_daten, "    ", 5 );
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
  Log::write( "Die Daten werden ausgelesen...", "+  ", 9 );

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
  //
  //  Dummy Einträge solange noch keine Daten vom Wechselrichter geliefert werden.
  //
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;
  $aktuelleDaten["WattstundenGesamtMonat"] = 0;
  $aktuelleDaten["WattstundenGesamtJahr"] = 0;
  $aktuelleDaten["KiloWattstundenTotal"] = 0;
  $aktuelleDaten["Modus"] = 0;
  $aktuelleDaten["ErrorCodes"] = 0;
  for ($k = 1; $k < 3; $k++) {
    $Befehl = "^P003PI"; //  Auslesen der Protokoll Nummer
    $RAW_daten = MPI::mpi_usb_lesen( $USB1, $Befehl );
    if ($RAW_daten === false) {
      Log::write( "Keine Verbindung möglich. Ausgang! ".$Befehl, "o  ", 5 );
      goto Ausgang;
    }
    $aktuelleDaten = array_merge( $aktuelleDaten, MPI::mpi_entschluesseln( substr( $Befehl, 5 ), $RAW_daten ));
    break;
  }
  Log::write( $RAW_daten, "   ", 9 );
  for ($k = 1; $k < 3; $k++) {
    $Befehl = "^P003MD"; //  Auslesen Stromwerte
    $RAW_daten = MPI::mpi_usb_lesen( $USB1, $Befehl );
    if ($RAW_daten === false) {
      Log::write( "Continue: ".$Befehl, "o  ", 8 );
      goto Ausgang;
    }
    $aktuelleDaten = array_merge( $aktuelleDaten, MPI::mpi_entschluesseln( substr( $Befehl, 5 ), $RAW_daten ));
    break;
  }
  Log::write( $RAW_daten, "   ", 9 );
  for ($k = 1; $k < 3; $k++) {
    $Befehl = "^P003GS"; //  Auslesen Stromwerte
    $RAW_daten = MPI::mpi_usb_lesen( $USB1, $Befehl );
    if ($RAW_daten === false) {
      Log::write( "Continue: ".$Befehl, "o  ", 8 );
      continue;
    }
    $aktuelleDaten = array_merge( $aktuelleDaten, MPI::mpi_entschluesseln( substr( $Befehl, 5 ), $RAW_daten ));
    break;
  }
  Log::write( $RAW_daten, "   ", 9 );
  for ($k = 1; $k < 3; $k++) {
    $Befehl = "^P003PS"; //  Auslesen Power Status
    $RAW_daten = MPI::mpi_usb_lesen( $USB1, $Befehl );
    if ($RAW_daten === false) {
      Log::write( "Continue: ".$Befehl, "o  ", 8 );
      continue;
    }
    $aktuelleDaten = array_merge( $aktuelleDaten, MPI::mpi_entschluesseln( substr( $Befehl, 5 ), $RAW_daten ));
    break;
  }
  Log::write( $RAW_daten, "   ", 9 );

  for ($k = 1; $k < 3; $k++) {
    $Befehl = "^P003WS"; //  Auslesen Warnungen
    $RAW_daten = MPI::mpi_usb_lesen( $USB1, $Befehl );
    if ($RAW_daten === false) {
      Log::write( "Continue: ".$Befehl, "o  ", 8 );
      continue;
    }
    $aktuelleDaten = array_merge( $aktuelleDaten, MPI::mpi_entschluesseln( substr( $Befehl, 5 ), $RAW_daten ));
    break;
  }
  Log::write( $RAW_daten, "   ", 9 );
  for ($k = 1; $k < 3; $k++) {
    //  Achtung! Die Gesamtleistung kann nur ausgelesen werden, wenn Datum und Zeit
    //  im Gerät richtig eingegeben sind. Die Routine funktioniert! 9.12.2018
    $Summe = 0;
    $Befehl = "^P014ED".date( "Ymd" ); //  Auslesen der WattstundenGesamtHeute (aktueller Tag!)
    for ($n = 0; $n < strlen( $Befehl ); $n++) {
      $Summe = $Summe + ord( $Befehl[$n] );
    }
    $hex = substr( "00".hexdec( substr( dechex( $Summe ), - 2 )), - 3 );
    $RAW_daten = MPI::mpi_usb_lesen( $USB1, $Befehl.$hex );
    if ($RAW_daten === false) {
      Log::write( "Continue: ".$Befehl, "o  ", 8 );
      continue;
    }
    $aktuelleDaten = array_merge( $aktuelleDaten, MPI::mpi_entschluesseln( substr( $Befehl, 5 ), $RAW_daten ));
    break;
  }
  Log::write( $RAW_daten, "   ", 9 );
  if ($k == 3) {
    $Uhrzeit = false;
  }

  /*************************    Ausgeschaltet am 8.1.2023
  for ($k = 1; $k < 3; $k++) {
  //  Achtung! Die Gesamtleistung kann nur ausgelesen werden, wenn Datum und Zeit
  //  im Gerät richtig eingegeben sind. Die Routine funktioniert! 9.12.2018
  $Summe = 0;
  $Befehl = "^P012EM".date("Ym",strtotime("-1 month"));  //  Auslesen der WattstundenGesamtMonat
  for ($n = 0; $n < strlen($Befehl); $n++) {             //  nur Vormonat möglich! Aktueller Monat nicht.
  $Summe = $Summe + ord($Befehl[$n]);
  }
  $hex = substr("00".hexdec(substr(dechex($Summe),-2)),-3);
  $RAW_daten = MPI::mpi_usb_lesen($USB1, $Befehl.$hex);
  if ($RAW_daten === false) {
  Log::write("Continue: ".$Befehl,"o  ",8);
  continue;
  }
  $aktuelleDaten = array_merge($aktuelleDaten, MPI::mpi_entschluesseln(substr($Befehl,5),$RAW_daten));
  break;
  }
  Log::write("Befehl: ".$Befehl,"   ",1);
  Log::write("RawData: ".$RAW_daten,"   ",1);
  *************************/
  if ($k == 3 and $Uhrzeit == false) {
    Log::write( "Es sieht so aus, als ob die Uhrzeit im Gerät nicht korrekt ist. Bitte prüfen!", "   ", 6 );
  }
  else {
    for ($k = 1; $k < 3; $k++) {
      //  Achtung! Die Gesamtleistung kann nur ausgelesen werden, wenn Datum und Zeit
      //  im Gerät richtig eingegeben sind. Die Routine funktioniert! 9.12.2018
      $Summe = 0;
      $Befehl = "^P010EY".date( "Y", strtotime( "-1 year" )); //  Auslesen der WattstundenGesamtJahr
      for ($n = 0; $n < strlen( $Befehl ); $n++) { //  Nur Vorjahr möglich! Aktuelles Jahr nicht.
        $Summe = $Summe + ord( $Befehl[$n] );
      }
      $hex = substr( "00".hexdec( substr( dechex( $Summe ), - 2 )), - 3 );
      $RAW_daten = MPI::mpi_usb_lesen( $USB1, $Befehl.$hex );
      if ($RAW_daten === false) {
        Log::write( "Continue: ".$Befehl, "o  ", 8 );
        continue;
      }
      $aktuelleDaten = array_merge( $aktuelleDaten, MPI::mpi_entschluesseln( substr( $Befehl, 5 ), $RAW_daten ));
      break;
    }
    Log::write( $RAW_daten, "   ", 9 );
    for ($k = 1; $k < 3; $k++) {
      $Befehl = "^P003DM"; //  Auslesen Produkt
      $RAW_daten = MPI::mpi_usb_lesen( $USB1, $Befehl );
      if ($RAW_daten === false) {
        Log::write( "Continue: ".$Befehl, "o  ", 8 );
        continue;
      }
      $aktuelleDaten = array_merge( $aktuelleDaten, MPI::mpi_entschluesseln( substr( $Befehl, 5 ), $RAW_daten ));
      break;
    }
    Log::write( $RAW_daten, "   ", 9 );
    for ($k = 1; $k < 3; $k++) {
      $Befehl = "^P003ET"; //  Auslesen Gesamte kWh seit Geräteherstellung
      $RAW_daten = MPI::mpi_usb_lesen( $USB1, $Befehl );
      if ($RAW_daten === false) {
        Log::write( "Continue: ".$Befehl, "o  ", 8 );
        $aktuelleDaten["KiloWattstundenTotal"] = 0;
        continue;
      }
      $aktuelleDaten = array_merge( $aktuelleDaten, MPI::mpi_entschluesseln( substr( $Befehl, 5 ), $RAW_daten ));
      break;
    }
    Log::write( $RAW_daten, "   ", 9 );
  }
  $Befehl = "^P005HECS"; //  Auslesen Energy control status
  $RAW_daten = MPI::mpi_usb_lesen( $USB1, $Befehl );
  if ($RAW_daten === false) {
    Log::write( "Befehl nicht ausgeführt: ".$Befehl, "o  ", 8 );
  }
  Log::write( $RAW_daten, "   ", 9 );
  for ($k = 1; $k < 3; $k++) {
    $Befehl = "^P005BATS"; //  Auslesen Battery maximum charge current
    $RAW_daten = MPI::mpi_usb_lesen( $USB1, $Befehl );
    if ($RAW_daten === false) {
      Log::write( "Befehl nicht ausgeführt: ".$Befehl, "o  ", 8 );
    }
    $aktuelleDaten = array_merge( $aktuelleDaten, MPI::mpi_entschluesseln( substr( $Befehl, 5 ), $RAW_daten ));
  }
  Log::write( $RAW_daten, "   ", 9 );
  for ($k = 1; $k < 3; $k++) {
    $Befehl = "^P003DI"; //  Auslesen Battery maximum charge current
    $RAW_daten = MPI::mpi_usb_lesen( $USB1, $Befehl );
    if ($RAW_daten === false) {
      Log::write( "Befehl nicht ausgeführt: ".$Befehl, "o  ", 8 );
    }
    Log::write( $RAW_daten, "   ", 9 );
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, MPI::mpi_entschluesseln( substr( $Befehl, 5 ), $RAW_daten ));

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
  if (empty($aktuelleDaten["Batterieleistung"])) {
    $aktuelleDaten["Batterieleistung"] = round(($aktuelleDaten["Batteriespannung"] * $aktuelleDaten["Batteriestrom"]),1);
  }
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  Log::write( var_export( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists($basedir."/custom/mpi_3phasen_serie_math.php" )) {
    include $basedir.'/custom/mpi_3phasen_serie_math.php'; // Falls etwas neu berechnet werden muss.
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
  $aktuelleDaten["Solarleistung"] = ($aktuelleDaten["Solarleistung1"] + $aktuelleDaten["Solarleistung2"]);
  $aktuelleDaten["Solarstrom"] = ($aktuelleDaten["Solarstrom1"] + $aktuelleDaten["Solarstrom2"]);
  if ($aktuelleDaten["Warnungen"] > 0 or $aktuelleDaten["ErrorCodes"] > 0) {
    Log::write( "Fehlercode. ".$aktuelleDaten["ErrorCodes"]." Warnung: ".$aktuelleDaten["Warnungen"], "   ", 7 );
  }

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
    $Zeitspanne = (9 - (time( ) - $Startzeit));
    Log::write( "Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2 );
    if ($Zeitspanne > 0) {
      sleep( $Zeitspanne );
    }
    break;
  }
  else {
    Log::write( "Schleife: ".($i)." Zeitspanne: ".(floor( (54 - (time( ) - $Startzeit)) / ($Wiederholungen - $i + 1))), "   ", 9 );
    sleep( floor( (54 - (time( ) - $Startzeit)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write( "OK. Daten gelesen.", "   ", 9 );
    Log::write( "Schleife ".$i." Ausgang...", "   ", 8 );
    break;
  }
  $i++;
} while (($Startzeit + 54) > time( ));
if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {

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
  //
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
Ausgang:Log::write( "-------------   Stop   mpi_3phasen_serie.php   --------------- ", "|--", 6 );
return;
?>
