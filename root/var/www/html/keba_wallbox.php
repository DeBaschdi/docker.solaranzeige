#!/usr/bin/php
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
//  Es dient dem Auslesen des HRDi marlec Laderegler über den
//  Seriell - USB Adapter.
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
$funktionen->log_schreiben( "-------------   Start  keba_wallbox.php   --------------------- ", "|--", 6 );
$funktionen->log_schreiben( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
$aktuelleDaten["AnzPhasen"] = 0;
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
$funktionen->log_schreiben( "Hardware Version: ".$Version, "o  ", 8 );
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
//Create a UDP socket
if (!($sock = socket_create( AF_INET, SOCK_DGRAM, 0 ))) {
  $errorcode = socket_last_error( );
  $errormsg = socket_strerror( $errorcode );
  $funktionen->log_schreiben( "UDP Socket konnte nicht geöffnet werden", "XX ", 3 );
  $funktionen->log_schreiben( "Kein Kontakt zur Wallbox ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
  $funktionen->log_schreiben( $errormsg, "XX ", 3 );
  goto Ausgang;
}
if (!socket_bind( $sock, "0.0.0.0", $WR_Port )) { // Bind an localhost
  $errorcode = socket_last_error( );
  $errormsg = socket_strerror( $errorcode );
  $funktionen->log_schreiben( "UDP Socket Bind Fehler", "XX ", 3 );
  $funktionen->log_schreiben( $errormsg, "XX ", 3 );
  goto Ausgang;
}
socket_set_option( $sock, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 1, "usec" => 500000));
$funktionen->log_schreiben( "UDP Socket Bind OK.", "   ", 8 );

/***************************************************************************
//  Einen Befehl an die Wallbox senden
//
//  Per MQTT  alw = 0
//  Per HTTP  alw_0
//
***************************************************************************/
if (file_exists( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" )) {
  $funktionen->log_schreiben( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' vorhanden----", "|- ", 5 );
  $Inhalt = file_get_contents( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" );
  $Befehle = explode( "\n", trim( $Inhalt ));
  $funktionen->log_schreiben( "Befehle: ".print_r( $Befehle, 1 ), "|- ", 9 );
  for ($i = 0; $i < count( $Befehle ); $i++) {
    if ($i >= 6) {
      //  Es werden nur maximal 7 Befehle pro Datei verarbeitet!
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
      $Regler30 = $INI_File["Regler30"];
      $funktionen->log_schreiben( "Befehlsliste: ".print_r( $Regler30, 1 ), "|- ", 9 );
      foreach ($Regler30 as $Template) {
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
    //
    if (trim( $Teile[0] ) == "currtime") {
      //  currtime hat 2 Parameter! Ein Dummy Parameter wird fest gesetzt falls er
      //  nich angegeben wurde. Delay = 1 Sekunden fest
      if (isset($Teile[2])) {
        $Teile[1] = $Teile[1]." ".$Teile[2];
      }
      else {
        $Teile[1] = $Teile[1]." 1";
      }
    }
    if (trim( $Teile[0] ) == "start") {
      //  start hat 2 Parameter! Ein Dummy Parameter wird fest gesetzt.
      $Teile[1] = $Teile[1]." 00000000000000000000";
    }
    $data = $Teile[0]." ".$Teile[1];
    $funktionen->log_schreiben( "Befehl wird verarbeitet: ".$data, "    ", 7 );
    socket_sendto( $sock, $data, strlen( $data ), 0, $WR_IP, $WR_Port );
    for ($t = 1; $t < 6; $t++) {
      // Receive some data
      $rc = socket_recvfrom( $sock, $buf, 512, 0, $WR_IP, $WR_Port );
      usleep( 500000 );
      $funktionen->log_schreiben( "Antwort: ".trim( $buf ), "    ", 7 );
      if ($buf) {
        $Msg = "Befehl: ".$Teile[0]." ".$Teile[1];
        $Teile2 = explode( ":", $buf );
        if (trim( $Teile2[0] ) == "TCH-OK") {
          $funktionen->log_schreiben( $Msg." gesendet!", "    ", 7 );
          break;
        }
        elseif (trim( $Teile2[0] ) == "TCH-ERR") {
          $funktionen->log_schreiben( $Msg." nicht gesendet: ".trim( $Teile[2] ), "    ", 7 );
          break;
        }
      }
    }
    sleep( 2 );
  }
  // Buffer leeren...
  $rc = socket_recvfrom( $sock, $buf, 512, 0, $WR_IP, $WR_Port );
  $rc = unlink( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" );
  if ($rc) {
    $funktionen->log_schreiben( "Datei  /../pipe/".$GeraeteNummer.".befehl.steuerung  gelöscht.", "    ", 9 );
  }
}
else {
  $funktionen->log_schreiben( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' nicht vorhanden----", "|- ", 9 );
}
$i = 1;
do {

  /***************************************************************************
  //  Ab hier wird die Wallbox ausgelesen.
  //
  ***************************************************************************/
  $data = "i";  // Command => i  (Firmware Version)
  $funktionen->log_schreiben( "Abfrage: ".$data, "   ", 9 );
  for ($t = 1; $t < 4; $t++) {
    socket_sendto( $sock, $data, strlen( $data ), 0, $WR_IP, $WR_Port );
    // Receive some data
    // $rc = socket_recvfrom($sock, $buf, 512, MSG_DONTWAIT, $WR_IP, $WR_Port);
    $rc = socket_recvfrom( $sock, $buf, 512, 0, $WR_IP, $WR_Port );
    usleep( 50000 );
    if ($buf) {
      if (!strpos( $buf, "}" )) {
        $Teile = explode( ":", $buf );
        $aktuelleDaten[trim( $Teile[0], "\"" )] = trim( $Teile[1], "\"" );
        $aktuelleDaten["Modell"] = trim( substr( trim( $Teile[1], "\"" ), 0, 4 ));
        $funktionen->log_schreiben( "Modell: ".$aktuelleDaten["Modell"], "   ", 3 );
        break;
      }
    }
  }
  if ($t == 3 and $i < 3) {
    $i++;
    sleep( 1 );
    continue;
  }
  $buf = "";
  $data = "report 1"; // Command => report 1 
  $funktionen->log_schreiben( "Abfrage: ".$data, "   ", 9 );
  for ($t = 1; $t < 3; $t++) {
    socket_sendto( $sock, $data, strlen( $data ), 0, $WR_IP, $WR_Port );
    //Receive some data
    $rc = socket_recvfrom( $sock, $buf, 2000, 0, $WR_IP, $WR_Port );
    usleep( 50000 );
    if ($buf) {
      $Zeile = explode( ",", $buf );
      $funktionen->log_schreiben( "buf: ".$buf, "   ", 9 );
      for ($k = 1; $k < count( $Zeile ); $k++) {
        $Teile = explode( ":", trim( $Zeile[$k] ));
        $Teile[1] = trim( $Teile[1], " " );
        $aktuelleDaten[trim( $Teile[0], "\" " )] = trim( $Teile[1], "\"" );
      }
      if (isset($aktuelleDaten["timeQ"])) {
        break;
      }
    }
  }
  if ($t == 3) {
    $i++;
    sleep( 1 );
    break;
  }
  $funktionen->log_schreiben( "Produkt: ".$aktuelleDaten["Product"], "   ", 3 );
  $buf = "";
  $data = "report 2";  // Command => report 2
  $funktionen->log_schreiben( "Abfrage: ".$data, "   ", 9 );
  for ($t = 1; $t < 3; $t++) {
    socket_sendto( $sock, $data, strlen( $data ), 0, $WR_IP, $WR_Port );
    //Receive some data
    $rc = socket_recvfrom( $sock, $buf, 2000, 0, $WR_IP, $WR_Port );
    usleep( 50000 );
    if ($buf) {
      $Zeile = explode( ",", $buf );
      for ($k = 1; $k < count( $Zeile ); $k++) {
        $Teile = explode( ":", trim( $Zeile[$k] ));
        $Teile[1] = strtr( $Teile[1], "\n}\"", "   " );
        $Teile[1] = trim( $Teile[1], " " );
        $aktuelleDaten[trim( $Teile[0], "\" " )] = trim( $Teile[1], "\"" );
      }
      if (isset($aktuelleDaten["Input"])) {
        break;
      }
    }
  }
  if ($t == 3) {
    $i++;
    sleep( 1 );
    continue;
  }
  $buf = "";
  $data = "report 3";
  $funktionen->log_schreiben( "Abfrage: ".$data, "   ", 9 );
  for ($t = 1; $t < 3; $t++) {
    socket_sendto( $sock, $data, strlen( $data ), 0, $WR_IP, $WR_Port );
    //Receive some data
    $rc = socket_recvfrom( $sock, $buf, 2000, 0, $WR_IP, $WR_Port );
    usleep( 50000 );
    if ($buf) {
      $Zeile = explode( ",", $buf );
      for ($k = 1; $k < count( $Zeile ); $k++) {
        $Teile = explode( ":", trim( $Zeile[$k] ));
        $Teile[1] = strtr( $Teile[1], "\n}\"", "   " );
        $Teile[1] = trim( $Teile[1], " " );
        $aktuelleDaten[trim( $Teile[0], "\" " )] = trim( $Teile[1], "\"" );
      }
      if (isset($aktuelleDaten["PF"])) {
        break;
      }
    }
  }
  if ($t == 3) {
    $i++;
    sleep( 1 );
    continue;
  }

  /************************************************************
  //    Weitere Werte, falls RFID Auswertung benötigt werden.
  //    In diesem Fall über die wall-math.php die Werte in die
  //    Datenbank speichern.
  //
  //    '100_Session ID' => '162'
  //    '100_Curr HW' => '32000'
  //    '100_E start' => '9998617'
  //    '100_E pres' => '76810'
  //    '100_started[s]' => '1622189541'
  //    '100_ended[s]' => '0'
  //    '100_started' => '2021-05-28 08'
  //    '100_ended' => '0'
  //    '100_reason' => '0'
  //    '100_timeQ' => '0'
  //    '100_RFID tag' => '0000000000000000'
  //    '100_RFID class' => '00000000000000000000'
  //    '100_Serial' => '20777556'
  //    '100_Sec' => '600'
  //
  ************************************************************/
  $buf = "";
  $data = "report 100";
  $funktionen->log_schreiben( "Abfrage: ".$data, "   ", 9 );
  for ($t = 1; $t < 3; $t++) {
    socket_sendto( $sock, $data, strlen( $data ), 0, $WR_IP, $WR_Port );
    //Receive some data
    $rc = socket_recvfrom( $sock, $buf, 2000, 0, $WR_IP, $WR_Port );
    usleep( 50000 );
    if ($buf) {
      $Zeile = explode( ",", $buf );
      $funktionen->log_schreiben( "buf: ".$buf, "   ", 9 );
      for ($k = 1; $k < count( $Zeile ); $k++) {
        $Teile = explode( ":", trim( $Zeile[$k] ));
        $Teile[1] = strtr( $Teile[1], "\n}\"", "   " );
        $Teile[1] = trim( $Teile[1], " " );
        $aktuelleDaten["100_".trim( $Teile[0], "\" " )] = trim( $Teile[1], "\"" );
      }
      if (isset($aktuelleDaten["100_RFID class"])) {
        //  Solange auslesen bis die richtigen Werte kommen.
        break;
      }
    }
  }
  if ($t == 3) {
    $i++;
    sleep( 1 );
    continue;
  }
  socket_close( $sock );

  /**************************************************************************
  //  Ende Wallbox auslesen
  ***************************************************************************/
  //  Wenn zu viele Lesefehler vorkommen.
  if ($i == 5) {
    break;
  }

  /**************************************************************************
  //  Mit wieviel Phasen wird geladen?
  ***************************************************************************/
  if ($aktuelleDaten["I1"] > 1000) {
    $aktuelleDaten["AnzPhasen"] = $aktuelleDaten["AnzPhasen"] + 1;
  }
  if ($aktuelleDaten["I2"] > 1000) {
    $aktuelleDaten["AnzPhasen"] = $aktuelleDaten["AnzPhasen"] + 1;
  }
  if ($aktuelleDaten["I3"] > 1000) {
    $aktuelleDaten["AnzPhasen"] = $aktuelleDaten["AnzPhasen"] + 1;
  }
  $FehlermeldungText = "";

  $funktionen->log_schreiben( var_export( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Produkt"] = $aktuelleDaten["Product"];
  $aktuelleDaten["WattstundenGesamtHeute"] = ($aktuelleDaten["E pres"] / 10);
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  $funktionen->log_schreiben( print_r( $aktuelleDaten, 1 ), "*- ", 9 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/keba_wallbox_math.php" )) {
    include 'keba_wallbox_math.php'; // Falls etwas neu berechnet werden muss.
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
    $funktionen->log_schreiben( "Schleife: ".($i)." Zeitspanne: ".(floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9 );
    sleep( floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben( "Schleife ".$i." Ausgang...", "   ", 5 );
    break;
  }
  $i++;
} while (($Start + 54) > time( ));
if (1 == 1) {

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
Ausgang:$funktionen->log_schreiben( "-------------   Stop   keba_wallbox.php   --------------------- ", "|--", 6 );
return;
?>
