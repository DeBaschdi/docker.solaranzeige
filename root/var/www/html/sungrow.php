#!/usr/bin/php
<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016 - 2021] [Ulrich Kunz]
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
//  Es dient dem Auslesen der VARTA Storage Modelle wie z.B.
//  VARTA element, VARTA pulse, VARTA link, VARTA flex storage usw.
//  mit LAN Schnittstelle.
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
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
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$Start = time( ); // Timestamp festhalten
$funktionen->log_schreiben( "-------------   Start  sungrow.php    -------------------------- ", "|--", 6 );
$funktionen->log_schreiben( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
$funktionen->log_schreiben( "Sungrow: ".$WR_IP." Port: ".$WR_Port." GeräteID: ".$WR_Adresse, "   ", 7 );

//  Hardware Version ermitteln.
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
  $WR_ID = "FF";
}
elseif(strlen($WR_Adresse) == 1)  {
  $WR_ID = str_pad(dechex($WR_Adresse),2,"0",STR_PAD_LEFT);
}
else {
  $WR_ID = str_pad(dechex($WR_Adresse),2,"0",STR_PAD_LEFT);
}

$funktionen->log_schreiben("WR_ID: ".$WR_ID,"+  ",8);


$COM1 = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 10 ); // 10 = Timeout in Sekunden
if (!is_resource( $COM1 )) {
  $funktionen->log_schreiben( "Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
  $funktionen->log_schreiben( "Exit.... ", "XX ", 9 );
  goto Ausgang;
}

$i = 1;
do {
  $funktionen->log_schreiben( "Die Daten werden ausgelesen...", "+  ", 7 );

  /****************************************************************************
  //  Ab hier wird der Wechselrichter ausgelesen.
  //
  //    $aktuelleDaten["Firmware"]
  //    $aktuelleDaten["Seriennummer"]
  //    $aktuelleDaten["Produkt"]
  //    $aktuelleDaten["Leistungsklasse"]
  //    $aktuelleDaten["Phasen"]
  //    $aktuelleDaten["Temperatur"]
  //    $aktuelleDaten["PV1_Spannung"]
  //    $aktuelleDaten["PV2_Spannung"]
  //    $aktuelleDaten["PV1_Strom"]
  //    $aktuelleDaten["PV2_Strom"]
  //    $aktuelleDaten["DC_Leistung"]
  //    $aktuelleDaten["AC_Spannung_R"]
  //    $aktuelleDaten["AC_Spannung_S"]
  //    $aktuelleDaten["AC_Spannung_T"]
  //    $aktuelleDaten["Frequenz"]
  //    $aktuelleDaten["PV_Power_Heute"]
  //    $aktuelleDaten["PV_Energie_Monat"]
  //    $aktuelleDaten["System_Status"]
  //    $aktuelleDaten["Status"]
  //    $aktuelleDaten["WattstundenGesamtHeute"]
  //    $aktuelleDaten["WattstundenGesamt"]
  //    $aktuelleDaten["Bezug"]
  //    $aktuelleDaten["Einspeisung"]
  //    $aktuelleDaten["Batterie_Spannung"]
  //    $aktuelleDaten["PV1_Leistung"]
  //    $aktuelleDaten["PV2_Leistung"]
  //
  //  function modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp )
  //
  //
  ****************************************************************************/
  $aktuelleDaten["Anz_PV_Strings"] = "2";
  $aktuelleDaten["Anz_MPP_Trackers"] = "2";
  $aktuelleDaten["Effizienz"] = 0;
  $GeraeteAdresse = $WR_ID;
  $FunktionsCode = "04";
  $RegisterAdresse = (4954 -1);  // Dezimal
  $RegisterAnzahl = "000F";   // Hex
  $DatenTyp = "String";
  $Timebase = 6000; //  Wie lange soll auf eine Antwort gewartet werden?
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    if ($Ergebnis["Befehl"] == "84") {
      $funktionen->log_schreiben( "Das Register 4954 gibt es nicht.", "   ", 8 );
      $aktuelleDaten["Firmware"] = "0";
    }
    else {
      $aktuelleDaten["Firmware"] = $Ergebnis["Wert"];
      $funktionen->log_schreiben( "Firmware: ".$aktuelleDaten["Firmware"], "   ", 5 );
    }
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }


  $RegisterAdresse = (4990 -1);  // Dezimal
  $RegisterAnzahl = "000A";   // Hex
  $DatenTyp = "String";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    if ($Ergebnis["Befehl"] == "84") {
      $funktionen->log_schreiben( "Dieses Register gibt es nicht. [4990]", "   ", 5 );
      $aktuelleDaten["Seriennummer"] = "000000";
    }
    else {
      $aktuelleDaten["Seriennummer"] = $Ergebnis["Wert"];
      $funktionen->log_schreiben( "Seriennummer: ".$aktuelleDaten["Seriennummer"], "   ", 5 );

    }
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

 

  $RegisterAdresse = (5000 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "Hex";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Produkt"] = $Ergebnis["Wert"];
    $aktuelleDaten["ModellID"] = hexdec($Ergebnis["Wert"]);
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  if ($aktuelleDaten["ModellID"] > 3327 and $aktuelleDaten["ModellID"] < 3841) {
    $aktuelleDaten["ModellGruppe"] = "SH";
  }
  else {
    $aktuelleDaten["ModellGruppe"] = "SG";
  }

  $funktionen->log_schreiben( "Produkt: ".$Ergebnis["Wert"] , "   ", 9 );

  $RegisterAdresse = (5001 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Leistungsklasse"] = ($Ergebnis["Wert"]*100);  // in Watt
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (5002 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Phasen"] = $Ergebnis["Wert"];  // 0 = 1 Phase,   1 = 3 Phasen mit Nullleiter,    2 = 3 Phasen Drehstrom
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }


  $RegisterAdresse = (5003 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    if ( $aktuelleDaten["ModellGruppe"] == "SG" ) {
      $aktuelleDaten["WattstundenGesamtHeute"] = ($Ergebnis["Wert"]*100);
    }
    else {
      $aktuelleDaten["WattstundenGesamtHeute"] = ($Ergebnis["Wert"]*100);
    }
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (5004 -1);  // Dezimal
  $RegisterAnzahl = "0002";      // HEX
  $DatenTyp = "U32S";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    if ( $aktuelleDaten["ModellGruppe"] == "SG" ) {
      $aktuelleDaten["WattstundenGesamt"] = ($Ergebnis["Wert"]*1000);
    }
    else {
      $aktuelleDaten["WattstundenGesamt"] = ($Ergebnis["Wert"]*100);
    }
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }


  $RegisterAdresse = (5008 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "I16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Temperatur"] = ($Ergebnis["Wert"]/10);  // in °C
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (5011 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "I16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["PV1_Spannung"] = ($Ergebnis["Wert"]/10);  // in Volt
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (5013 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "I16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["PV2_Spannung"] = ($Ergebnis["Wert"]/10);  // in Volt
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (5015 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "I16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["PV3_Spannung"] = ($Ergebnis["Wert"]/10);  // in Volt
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  if ($aktuelleDaten["PV3_Spannung"] > 0) {
    $aktuelleDaten["Anz_PV_Strings"] = "3";
    $aktuelleDaten["Anz_MPP_Trackers"] = "3";
  }


  $RegisterAdresse = (5012 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "I16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["PV1_Strom"] = ($Ergebnis["Wert"]/10);  // in Ampere
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (5014 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "I16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["PV2_Strom"] = ($Ergebnis["Wert"]/10);  // in Ampere
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (5016 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "I16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["PV3_Strom"] = ($Ergebnis["Wert"]/10);  // in Ampere
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }


  $RegisterAdresse = (5017 -1);  // Dezimal
  $RegisterAnzahl = "0002";      // HEX
  $DatenTyp = "U32S";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["PV_Leistung"] = $Ergebnis["Wert"];  // in Watt
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (5019 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["AC_Spannung_R"] = ($Ergebnis["Wert"]/10);  // in Volt
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (5020 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["AC_Spannung_S"] = ($Ergebnis["Wert"]/10);  // in Volt
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (5021 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["AC_Spannung_T"] = ($Ergebnis["Wert"]/10);  // in Volt
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }



  $RegisterAdresse = (5036 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["AC_Frequenz"] = ($Ergebnis["Wert"]/10);  // in Hertz
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (6196 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["PV_Energie_Heute"] = ($Ergebnis["Wert"]*100);  // in Watt/Stunden
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (6227 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["PV_Energie_Monat"] = ($Ergebnis["Wert"]*100);  // in Wh
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (13000 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "Hex";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["DeviceStatus"] = $Ergebnis["Wert"];
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (13001 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Batterie_Status"] = $Ergebnis["Wert"];
    $aktuelleDaten["StatusBit"] = $funktionen->d2b($Ergebnis["Wert"]);
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (13002 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Energie_Heute"] = ($Ergebnis["Wert"]*100);
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (13003 -1);  // Dezimal
  $RegisterAnzahl = "0002";      // HEX
  $DatenTyp = "U32S";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Energie_Total"] = ($Ergebnis["Wert"]*100);
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }


  $RegisterAdresse = (13008 -1);  // Dezimal
  $RegisterAnzahl = "0002";      // HEX
  $DatenTyp = "I32S";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Hausverbrauch"] = $Ergebnis["Wert"];
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (13010 -1);  // Dezimal
  $RegisterAnzahl = "0002";      // HEX
  $DatenTyp = "I32S";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    if ($Ergebnis["Wert"] > 0) {
      $aktuelleDaten["Einspeisung"] = $Ergebnis["Wert"];
      $aktuelleDaten["Bezug"] = 0;
    } 
    else {
      $aktuelleDaten["Bezug"] = abs($Ergebnis["Wert"]);
      $aktuelleDaten["Einspeisung"] = 0;
    }

  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (13020 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Batterie_Spannung"] = ($Ergebnis["Wert"]/10);
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (13021 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Batterie_Strom"] = ($Ergebnis["Wert"]/10);
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (13022 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Batterie_Leistung"] = $Ergebnis["Wert"];
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (13023 -1);  // Dezimal
  $RegisterAnzahl = "0001";       // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["SOC"] = ($Ergebnis["Wert"]/10);
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (13025 -1);  // Dezimal
  $RegisterAnzahl = "0001";       // HEX
  $DatenTyp = "I16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Batterie_Temperatur"] = ($Ergebnis["Wert"]/10); // in °C
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (13034 -1);  // Dezimal
  $RegisterAnzahl = "0002";      // HEX
  $DatenTyp = "I32S";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["AC_Leistung"] = $Ergebnis["Wert"];
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (13026 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Batterie_Entladung"] = ($Ergebnis["Wert"]*100);
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }


  $RegisterAdresse = (13040 -1);  // Dezimal
  $RegisterAnzahl = "0001";      // HEX
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Batterie_Ladung"] = ($Ergebnis["Wert"]*100);
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (13050 -1);  // Dezimal
  $RegisterAnzahl = "0002";      // HEX
  $DatenTyp = "U32S";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["FehlerCode"] = $Ergebnis["Wert"];
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  if ($aktuelleDaten["ModellGruppe"] == "SG") {
    // SG Modelle

    $RegisterAdresse = (5031 -1);  // Dezimal
    $RegisterAnzahl = "0001";      // HEX
    $DatenTyp = "U16";
    $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
    if (is_array( $Ergebnis )) {
      $aktuelleDaten["AC_Leistung"] = $Ergebnis["Wert"];  // in Watt
    }
    else {
      $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
      goto Ausgang;
    }

    $RegisterAdresse = (5144 -1);  // Dezimal
    $RegisterAnzahl = "0002";      // HEX
    $DatenTyp = "U32S";
    $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
    if (is_array( $Ergebnis )) {
      $aktuelleDaten["Energie_Total"] = ($Ergebnis["Wert"]*100);
    }
    else {
      $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
      goto Ausgang;
    }

    $RegisterAdresse = (5081 -1);  // Dezimal
    $RegisterAnzahl = "0002";      // HEX
    $DatenTyp = "U32S";
    $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
    if (is_array( $Ergebnis )) {
      $aktuelleDaten["DeviceStatus2"] = hexdec($Ergebnis["Wert"]);
    }
    else {
      $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
      goto Ausgang;
    }


    $RegisterAdresse = (5128 -1);  // Dezimal
    $RegisterAnzahl = "0002";      // HEX
    $DatenTyp = "I32S";
    $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
    if (is_array( $Ergebnis )) {
      $aktuelleDaten["WattstundenGesamtMonat"] = (hexdec($Ergebnis["Wert"])*100);
    }
    else {
      $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
      goto Ausgang;
    }

    $RegisterAdresse = (5045 -1);  // Dezimal
    $RegisterAnzahl = "0002";      // HEX
    $DatenTyp = "I32S";
    $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
    if (is_array( $Ergebnis )) {
      $aktuelleDaten["FehlerCode"] = hexdec($Ergebnis["Wert"]);
    }
    else {
      $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
      goto Ausgang;
    }

  }



  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/

  if ($aktuelleDaten["Produkt"] == "0d09") {
    $aktuelleDaten["Modell"] = "SH5K-20";
  }
  elseif ($aktuelleDaten["Produkt"] == "0d06") {
    $aktuelleDaten["Modell"] = "SH3K6";
  }
  elseif ($aktuelleDaten["Produkt"] == "0d07") {
    $aktuelleDaten["Modell"] = "SH4K6";
  }
  elseif ($aktuelleDaten["Produkt"] == "0d03") {
    $aktuelleDaten["Modell"] = "SH5K-V13";
  }
  elseif ($aktuelleDaten["Produkt"] == "0d0c") {
    $aktuelleDaten["Modell"] = "SH5K-30";
  }
   elseif ($aktuelleDaten["Produkt"] == "0d0a") {
    $aktuelleDaten["Modell"] = "SH3K6-30";
  }
  elseif ($aktuelleDaten["Produkt"] == "0d0b") {
    $aktuelleDaten["Modell"] = "SH4K6-30";
  }
  elseif ($aktuelleDaten["Produkt"] == "0d0f") {
    $aktuelleDaten["Modell"] = "SH5.0RS";
  }
  elseif ($aktuelleDaten["Produkt"] == "0d17") {
    $aktuelleDaten["Modell"] = "SH3.0RS";
  }
  elseif ($aktuelleDaten["Produkt"] == "0d0d") {
    $aktuelleDaten["Modell"] = "SH3.6RS";
  }
  elseif ($aktuelleDaten["Produkt"] == "0d0e") {
    $aktuelleDaten["Modell"] = "SH4.6RS";
  }
  elseif ($aktuelleDaten["Produkt"] == "0d10") {
    $aktuelleDaten["Modell"] = "SH6.0RS";
  }
  elseif ($aktuelleDaten["Produkt"] == "0e03") {
    $aktuelleDaten["Modell"] = "SH10RT";
  }
  elseif ($aktuelleDaten["Produkt"] == "0e02") {
    $aktuelleDaten["Modell"] = "SH8.0RT";
  }
  elseif ($aktuelleDaten["Produkt"] == "0e01") {
    $aktuelleDaten["Modell"] = "SH6.0RT";
  }
  elseif ($aktuelleDaten["Produkt"] == "0e00") {
    $aktuelleDaten["Modell"] = "SH5.0RT";
  }
  elseif ($aktuelleDaten["Produkt"] == "0e0e") {
    $aktuelleDaten["Modell"] = "SH8.0RT";
  }
  elseif ($aktuelleDaten["Produkt"] == "0e0f") {
    $aktuelleDaten["Modell"] = "SH10.0RT";
  }
  elseif ($aktuelleDaten["Produkt"] == "243d") {
    $aktuelleDaten["Modell"] = "SG3.0RT";
  }
  elseif ($aktuelleDaten["Produkt"] == "0e00") {
    $aktuelleDaten["Modell"] = "SH5.0RT";
  }
  elseif ($aktuelleDaten["Produkt"] == "243e") {
    $aktuelleDaten["Modell"] = "SG4.0RT";
  }
  elseif ($aktuelleDaten["Produkt"] == "2430") {
    $aktuelleDaten["Modell"] = "SG5.0RT";
  }
  elseif ($aktuelleDaten["Produkt"] == "2431") {
    $aktuelleDaten["Modell"] = "SG6.0RT";
  }
  elseif ($aktuelleDaten["Produkt"] == "243c") {
    $aktuelleDaten["Modell"] = "SG7.0RT";
  }
  elseif ($aktuelleDaten["Produkt"] == "2432") {
    $aktuelleDaten["Modell"] = "SG8.0RT";
  }
  elseif ($aktuelleDaten["Produkt"] == "2433") {
    $aktuelleDaten["Modell"] = "SG10.0RT";
  }
  else {
    $aktuelleDaten["Modell"] = "unbekannt";
    $funktionen->log_schreiben( "Produkt: ".$aktuelleDaten["Produkt"], "   ", 5 );
  }

  $aktuelleDaten["PV1_Leistung"] = ($aktuelleDaten["PV1_Spannung"] * $aktuelleDaten["PV1_Strom"]);
  $aktuelleDaten["PV2_Leistung"] = ($aktuelleDaten["PV2_Spannung"] * $aktuelleDaten["PV2_Strom"]);
  $aktuelleDaten["PV3_Leistung"] = ($aktuelleDaten["PV3_Spannung"] * $aktuelleDaten["PV3_Strom"]);


  /***************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  ***************************************************************************/
  $FehlermeldungText = "";

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Produkt"] = $aktuelleDaten["Modell"];

  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  $funktionen->log_schreiben( var_export( $aktuelleDaten, 1 ), "   ", 8 );


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/rct_wr_math.php" )) {
    include 'rct_wr_math.php'; // Falls etwas neu berechnet werden muss.
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
  if ($Wiederholungen <= $i or $i >= 1) {
    //  Die RCT Wechselrichter dürfen nur einmal pro Minute ausgelesen werden!
    $funktionen->log_schreiben( "Schleife ".$i." Ausgang...", "   ", 5 );
    break;
  }
  $i++;
} while (($Start + 54) > time( ));

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

Ausgang:fclose( $COM1 );
$funktionen->log_schreiben( "-------------   Stop   sungrow.php    -------------------------- ", "|--", 6 );
return;
?>
