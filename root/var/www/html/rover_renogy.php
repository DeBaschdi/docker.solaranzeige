#!/usr/bin/php
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
//  Es dient dem Auslesen des Renogy Ladereglers  über die USB
//  Schnittstelle. Das Auslesen wird hier mit einer Schleife durchgeführt.
//  Wie oft die Daten ausgelesen und gespeichert werden steht in der
//  user.config.php
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
$Version = "";
$Device = "LR"; // LR = Laderegler
$RemoteDaten = true;
$Befehl = array("DeviceID" => "01", "BefehlFunctionCode" => "03", "RegisterAddress" => "0014", "RegisterCount" => "0004");
$Start = time( ); // Timestamp festhalten
$funktionen->log_schreiben( "---------   Start  rover_regler.php  ------------------------ ", "|--", 6 );
$funktionen->log_schreiben( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
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
//  Nach em Öffnen des Port muss sofort der Regler ausgelesen werden, sonst
//  sendet er asynchrone Daten!
$USB1 = $funktionen->openUSB( $USBRegler );
if (!is_resource( $USB1 )) {
  $funktionen->log_schreiben( "USB Port kann nicht geöffnet werden. [1]", "XX ", 7 );
  $funktionen->log_schreiben( "Exit.... ", "XX ", 7 );
  goto Ausgang;
}

/***************************************************************************
//  Einen Befehl an den Laderegler senden
//
//  Per MODBUS Befehl
//
***************************************************************************/
if (file_exists( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" )) {
  $funktionen->log_schreiben( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' vorhanden----", "|- ", 5 );
  $Inhalt = file_get_contents( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" );
  $Befehle = explode( "\n", trim( $Inhalt ));
  $funktionen->log_schreiben( "Befehle: ".print_r( $Befehle, 1 ), "|- ", 9 );
  for ($i = 0; $i < count( $Befehle ); $i++) {
    if ($i >= 4) {
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
      $Regler14 = $INI_File["Regler14"];
      $funktionen->log_schreiben( "Befehlsliste: ".print_r( $Regler14, 1 ), "|- ", 9 );
      foreach ($Regler14 as $Template) {
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

    /***********************************************************************
    // Hier wird der Befehl gesendet...
    //
    ***********************************************************************/
    if (strtoupper( $Teile[0] ) == "LOAD") {
      if (strtoupper( $Teile[1] ) == "ON") {
        //  Load einschalten
        //  Load einschalten
        $Befehl = array("DeviceID" => "01", "BefehlFunctionCode" => "06", "RegisterAddress" => "010A", "RegisterCount" => "0001");
        $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
        $funktionen->log_schreiben( "010A : ".$rc, "   ", 7 );
        if ($rc == false) {
          $funktionen->log_schreiben( "Fehler! Load Ausgang konnte nicht eingeschaltet werden.", "   ", 1 );
        }
        else {
          $funktionen->log_schreiben( "Load Ausgang wird eingeschaltet.", "   ", 1 );
        }
      }
      if (strtoupper( $Teile[1] ) == "OFF") {
        //  Load ausschalten
        //  Load ausschalten
        $Befehl = array("DeviceID" => "01", "BefehlFunctionCode" => "06", "RegisterAddress" => "010A", "RegisterCount" => "0000");
        $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
        $funktionen->log_schreiben( "010A : ".$rc, "   ", 7 );
        if ($rc == false) {
          $funktionen->log_schreiben( "Fehler! Load Ausgang konnte nicht ausgeschaltet werden.", "   ", 1 );
        }
        else {
          $funktionen->log_schreiben( "Load Ausgang wird ausgeschaltet.", "   ", 1 );
        }
      }
      if (strtoupper( $Teile[1] ) == "MAN") {
        //  Load Mode auf Manual umschalten
        //  Load Mode auf Manual umschalten
        $Befehl = array("DeviceID" => "01", "BefehlFunctionCode" => "06", "RegisterAddress" => "E01D", "RegisterCount" => "000F");
        $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
        $funktionen->log_schreiben( "010A : ".$rc, "   ", 7 );
        if ($rc == false) {
          $funktionen->log_schreiben( "Fehler! Load Ausgang konnte nicht ausgeschaltet werden.", "   ", 1 );
        }
        else {
          $funktionen->log_schreiben( "Load Ausgang wird ausgeschaltet.", "   ", 1 );
        }
      }
    }
    sleep( 2 );
  }
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
  $funktionen->log_schreiben( "Die Daten werden ausgelesen...".print_r( $Befehl, 1 ), ">  ", 9 );

  /**************************************************************************
  //  Ab hier wird der Regler ausgelesen.
  //
  //  Ergebniswerte:
  //  $aktuelleDaten["Modell"]
  //  $aktuelleDaten["Firmware"]
  //  $aktuelleDaten["Produkt"]
  //  $aktuelleDaten["Objekt"]
  //  $aktuelleDaten["Datum"]
  //  $aktuelleDaten["Batteriespannung"]
  //  $aktuelleDaten["Solarstrom"]
  //  $aktuelleDaten["Solarspannung"]
  //  $aktuelleDaten["Batterieentladestrom"]
  //  $aktuelleDaten["WattstundenGesamt"]
  //  $aktuelleDaten["Temperatur"]
  //  $aktuelleDaten["BatterieTemperatur"]
  //  $aktuelleDaten["Batterieladestrom"]
  //  $aktuelleDaten["Batterieentladeleistung"]
  //  $aktuelleDaten["BatterieMaxVoltHeute"]
  //  $aktuelleDaten["BatterieMinVoltHeute"]
  //  $aktuelleDaten["BatterieSOC"]
  //  $aktuelleDaten["Solarleistung"]
  //  $aktuelleDaten["SolarstromMaxHeute"]
  //  $aktuelleDaten["Ladestatus"]
  //  $aktuelleDaten["WattstundenGesamtHeute"]
  //  $aktuelleDaten["VerbrauchGesamtHeute"]
  //  $aktuelleDaten["VerbrauchGesamt"]
  //
  **************************************************************************/
  $aktuelleDaten["Ladestatus"] = 0;

  /****************************************************************************
  //  Ab hier wird der Regler ausgelesen.
  //
  ****************************************************************************/
  $Befehl["RegisterAddress"] = "000C";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0008";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "000C : ".$rc, "   ", 7 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Modell"] = trim( $funktionen->Hex2String( $funktionen->renogy_daten( $rc, false, false )));
  //  Firmware Version     Firmware Version     Firmware Version     Firmware
  //  Firmware Version     Firmware Version     Firmware Version     Firmware
  $Befehl["RegisterAddress"] = "0014";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0004";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "0014 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $Version = $funktionen->renogy_daten( $rc, false, false );
  $aktuelleDaten["Firmware"] = substr( $Version, 2, 8 );
  //  SOC      SOC      SOC      SOC      SOC      SOC      SOC      SOC
  //  SOC      SOC      SOC      SOC      SOC      SOC      SOC      SOC
  $Befehl["RegisterAddress"] = "0100";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "0100 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["BatterieSOC"] = $funktionen->renogy_daten( $rc, true, false );
  //  Batteriespannung        Batteriespannung        Batteriespannung
  //  Batteriespannung        Batteriespannung        Batteriespannung
  $Befehl["RegisterAddress"] = "0101";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "0101 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Batteriespannung"] = ($funktionen->renogy_daten( $rc, true, false ) / 10);
  //  Temperatur     Temperatur     Temperatur     Temperatur
  //  Temperatur     Temperatur     Temperatur     Temperatur
  $Befehl["RegisterAddress"] = "0103";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "0103 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Temperatur"] = $funktionen->hexdecs( substr( $funktionen->renogy_daten( $rc, false, false ), 0, 2 ));
  $aktuelleDaten["BatterieTemperatur"] = $funktionen->hexdecs( substr( $funktionen->renogy_daten( $rc, false, false ), 2, 2 ));
  //  Batterieladestrom       Batterieladestrom       Batterieladestrom
  //  Batterieladestrom       Batterieladestrom       Batterieladestrom
  $Befehl["RegisterAddress"] = "0102";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "0102 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Batterieladestrom"] = $funktionen->renogy_daten( $rc, true, true );
  //  Batterieentladestrom       Batterieentladestrom       Batterieentladestrom
  //  Batterieentladestrom       Batterieentladestrom       Batterieentladestrom
  $Befehl["RegisterAddress"] = "0105";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "0105 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Batterieentladestrom"] = $funktionen->renogy_daten( $rc, true, true );
  //  Batteriespannung max       Batteriespannung max       Batteriespannung max
  //  Batteriespannung max       Batteriespannung max       Batteriespannung max
  $Befehl["RegisterAddress"] = "010C";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "010C : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["BatterieMaxVoltHeute"] = ($funktionen->renogy_daten( $rc, true, false ) / 10);
  //  Batteriespannung min       Batteriespannung min       Batteriespannung min
  //  Batteriespannung min       Batteriespannung min       Batteriespannung min
  $Befehl["RegisterAddress"] = "010B";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "010B : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["BatterieMinVoltHeute"] = ($funktionen->renogy_daten( $rc, true, false ) / 10);
  //  Batterieentladeleistung     Batterieentladeleistung   Batterieentladeleistung
  //  Batterieentladeleistung     Batterieentladeleistung   Batterieentladeleistung
  $Befehl["RegisterAddress"] = "0106";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "0106 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Batterieentladeleistung"] = $funktionen->renogy_daten( $rc, true, false );
  //  Solarspannung        Solarspannung        Solarspannung
  //  Solarspannung        Solarspannung        Solarspannung
  $Befehl["RegisterAddress"] = "0107";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "0107 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Solarspannung"] = ($funktionen->renogy_daten( $rc, true, false ) / 10);
  //  Solarstrom        Solarstrom        Solarstrom       Solarstrom
  //  Solarstrom        Solarstrom        Solarstrom       Solarstrom
  $Befehl["RegisterAddress"] = "0108";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "0108 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Solarstrom"] = $funktionen->renogy_daten( $rc, true, true );
  //  LOAD Ausgang ON/OFF      LOAD Ausgang ON/OFF      LOAD Ausgang ON/OFF
  //  LOAD Ausgang ON/OFF      LOAD Ausgang ON/OFF      LOAD Ausgang ON/OFF
  $Befehl["RegisterAddress"] = "010A";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "010A : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["LOAD_Ausgang"] = $funktionen->renogy_daten( $rc, true, false );
  if ($aktuelleDaten["LOAD_Ausgang"] == 0) {
    $funktionen->log_schreiben( "Load Ausgang ist ausgeschaltet.", "   ", 7 );
  }
  else {
    $funktionen->log_schreiben( "Load Ausgang ist eingeschaltet.", "   ", 7 );
  }
  //  Solarstrom max     Solarstrom max    Solarstrom max
  //  Solarstrom max     Solarstrom max    Solarstrom max
  $Befehl["RegisterAddress"] = "010D";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "010D : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["SolarstromMaxHeute"] = $funktionen->renogy_daten( $rc, true, true );
  //  Solarleistung        Solarleistung       Solarleistung
  //  Solarleistung        Solarleistung       Solarleistung
  $Befehl["RegisterAddress"] = "0109";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "0109 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Solarleistung"] = $funktionen->renogy_daten( $rc, true, false );
  if ($aktuelleDaten["Solarleistung"] > 100000) {
    $funktionen->log_schreiben( "Fehler!: ".$rc, "   ", 7 );
    goto Ausgang;
  }
  //  Ladestatus      Ladestatus      Ladestatus     Ladestatus
  //  Ladestatus      Ladestatus      Ladestatus     Ladestatus
  $Befehl["RegisterAddress"] = "0120";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "0120 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Ladestatus"] = substr( $funktionen->renogy_daten( $rc, false, false ), 2, 2 );
  if ($aktuelleDaten["Ladestatus"] == "") {
    $funktionen->log_schreiben( "Fehler!: ".$rc, "   ", 7 );
    goto Ausgang;
  }
  //  Fehlercode     Fehlercode     Fehlercode     Fehlercode
  //  Fehlercode     Fehlercode     Fehlercode     Fehlercode
  $Befehl["RegisterAddress"] = "0121";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "0121 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Fehlercode"] = substr( $funktionen->renogy_daten( $rc, false, false ), 2, 2 );
  //  Wattstunden Gesamt Heute   Wattstunden Gesamt Heute   Wattstunden
  //  Wattstunden Gesamt Heute   Wattstunden Gesamt Heute   Wattstunden
  $Befehl["RegisterAddress"] = "0113";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "0113 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["WattstundenGesamtHeute"] = $funktionen->renogy_daten( $rc, true, false );
  //  Wattstunden Gesamt   Wattstunden Gesamt    Wattstunden Gesamt
  //  Wattstunden Gesamt   Wattstunden Gesamt    Wattstunden Gesamt
  $Befehl["RegisterAddress"] = "011C";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "011C : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["WattstundenGesamt"] = $funktionen->renogy_daten( $rc, true, false );
  //  Verbrauch Gesamt Heute   Verbrauch Gesamt Heute   Verbrauch Gesamt
  //  Verbrauch Gesamt Heute   Verbrauch Gesamt Heute   Verbrauch Gesamt
  $Befehl["RegisterAddress"] = "0114";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "0113 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["VerbrauchGesamtHeute"] = $funktionen->renogy_daten( $rc, true, false );
  //  Verbrauch Gesamt   Verbrauch Gesamt    Verbrauch Gesamt
  //  Verbrauch Gesamt   Verbrauch Gesamt    Verbrauch Gesamt
  $Befehl["RegisterAddress"] = "011E";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "011C : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["VerbrauchGesamt"] = $funktionen->renogy_daten( $rc, true, false );
  //  Working Mode      Working Mode      Working Mode
  //  Working Mode      Working Mode      Working Mode
  $Befehl["RegisterAddress"] = "E01D";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->renogy_auslesen( $USB1, $Befehl );
  $funktionen->log_schreiben( "E01D : ".$rc, "   ", 7 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["WorkingMode"] = $funktionen->renogy_daten( $rc, true, false );

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Produkt"] = "Rover/Toyo/SRNE";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);

  /**************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  **************************************************************************/
  $FehlermeldungText = "";
  $funktionen->log_schreiben( var_export( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/rover_renogy_math.php" )) {
    include 'rover_renogy_math.php'; // Falls etwas neu berechnet werden muss.
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
    $funktionen->log_schreiben( "OK. Daten gelesen.", "   ", 9 );
    $funktionen->log_schreiben( "Schleife ".$i." Ausgang...", "   ", 8 );
    break;
  }
  $i++;
} while (($Start + 56) > time( ));
if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {

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
Ausgang:$funktionen->log_schreiben( "---------   Stop   rover_regler.php    ---------------------- ", "|--", 6 );
return;
?>