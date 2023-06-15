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
//  ALTE VERSION!     ALTE VERSION!     ALTE VERSION!     ALTE VERSION!
//
//  Es dient dem Auslesen des Wechselrichters SofarSolar über eine RS485
//  Schnittstelle mit USB Adapter. Protokoll Version 2013-8-17
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//  S/N    Familie              Typ       Modbus Protokoll
//  ---    -------------------- --------  ------------------------------------
//  A1     SOFAR 1000...3000TL  Solar     SOFAR 1…40KTL
//  A3     SOFAR 1100…3300TL-G3 Solar     SOFAR 1…40KTL
//  B1     SOFAR 3...6KTLM      Solar     SOFAR 1…40KTL
//  C1…C4  SOFAR 10…20KTL       Solar     SOFAR 1…40KTL
//  D1…D4  SOFAR 30…40KTL       Solar     SOFAR 1…40KTL
//  E1     ME 3000-SP           Battery   SOFAR HYD 3...6K-ES and ME3000-SP
//  F1…F4  SOFAR 3.3…12KTL-X    Solar     SOFAR 1…40KTL
//  G1…G4  SOFAR 30…40KTL-G2    Solar     SOFAR 1…40KTL
//  H1     SOFAR 3…6KTLM-G2     Solar     SOFAR 1…40KTL
//  I1     SOFAR 50…70KTL       Solar     SOFAR 50…70KTL
//  J1…J3  SOFAR 50…70KTL-G2    Solar     SOFAR 50…70KTL
//  K1     SOFAR 7.5KTLM        Solar     SOFAR 1…40KTL
//  L1     SOFAR 20...33KTL-G2  Solar     SOFAR 1…40KTL
//  M1     HYD 3000…6000-ES     Hybrid    SOFAR HYD 3...6K-ES and ME3000-SP
//  M2     HYD 3000…6000-EP     Hybrid    SOFAR HYD-3PH and SOFAR -G3
//  N1     SOFAR 10...15KTL-G2  Solar     SOFAR 1…40KTL
//  P1     HYD 5…20KTL-3PH      Hybrid    SOFAR HYD-3PH and SOFAR -G3
//  Q1     SOFAR 80…136KTL      Solar     SOFAR HYD-3PH and SOFAR -G3
//  R1     SOFAR 255KTL-HV      Solar     SOFAR HYD-3PH and SOFAR -G3
//  S1     SOFAR 3.3…50KTLX-G3  Solar     SOFAR HYD-3PH and SOFAR -G3
//  U1     ME 5…20KTL-3PH       Battery   SOFAR HYD-3PH and SOFAR -G3
//
//
***************************************************************************/
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
$RemoteDaten = true;
$Start = time( ); // Timestamp festhalten
$funktionen->log_schreiben( "----------------------   Start  sofarsolar_wr2.php   --------------------- ", "|--", 6 );
$funktionen->log_schreiben( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
$aktuelleDaten["KeineSonne"] = false;
$Timer = 800000; // Wartezeit für das Lesen der MODBUS Daten
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
  //  Modell: x.x KTL-X
  ****************************************************************************/
  $Protokoll = 1;
  $aktuelleDaten["Einspeisung"] = 0;
  $aktuelleDaten["Bezug"] = 0;
  $aktuelleDaten["Hausverbrauch"] = 0;
  $aktuelleDaten["Anz_MPP_Trackers"] = 1;
  $aktuelleDaten["Effizienz"] = 0;
  $aktuelleDaten["Batterie_Status"] = 0;

  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterAddress"] = str_pad( "0044", 4, "0", STR_PAD_LEFT );
  $Befehl["RegisterCount"] = "0001";
  $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  if ($rc == false) {
    $funktionen->log_schreiben("Das Gerät kann nicht ausgelesen werden.", "   ", 7 );
    goto Ausgang;
  }
  $aktuelleDaten["ModbusProtokollVersion"] = substr( $rc["data"], 0, 4 );
  $funktionen->log_schreiben( "Modbus Protokoll Version: ".$aktuelleDaten["ModbusProtokollVersion"], "   ", 7 );


  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterAddress"] = str_pad( "444", 4, "0", STR_PAD_LEFT );
  $Befehl["RegisterCount"] = "0016";
  $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  $aktuelleDaten["Seriennummer"] = $funktionen->hex2string( substr( $rc["data"], 4, 28 ));
  $funktionen->log_schreiben( "Modell: ".$funktionen->hex2string( substr( $rc["data"], 6, 4 )), "   ", 7 );
  switch ($funktionen->hex2string( substr( $rc["data"], 6, 4 ))) {

    case "A1":
      $Protokoll = 1;
      $aktuelleDaten["Modell"] = "Sofar 1000..3000TL";
      break;

    case "A3":
      $Protokoll = 1;
      $aktuelleDaten["Modell"] = "Sofar 1100..3300TL-G3";
      break;

    case "B1":
      $Protokoll = 1;
      $aktuelleDaten["Modell"] = "Sofar 3..6KTLM";
      break;

    case "C1":
    case "C2":
    case "C3":
    case "C4":
      $Protokoll = 1;
      $aktuelleDaten["Modell"] = "Sofar 10..20KTL";
      break;

    case "D1":
    case "D2":
    case "D3":
    case "D4":
      $Protokoll = 1;
      $aktuelleDaten["Modell"] = "Sofar 30..40KTL";
      break;

    case "E1":
      $Protokoll = 3;
      $aktuelleDaten["Modell"] = "Sofar ME 3000-SP";
      break;

    case "M1":
      $Protokoll = 3;
      $aktuelleDaten["Modell"] = "Sofar HYD 3000..6000-ES";
      break;

    case "M2":
      $Protokoll = 2;
      $aktuelleDaten["Modell"] = "Sofar HYD 3000..6000-EP";
      break;

    case "P1":
    case "P2":
      $Protokoll = 2;
      $aktuelleDaten["Anz_MPP_Trackers"] = 2;
      $aktuelleDaten["Modell"] = "Sofar HYD 5..20KTL-3PH";
      break;

    case "Q1":
      $Protokoll = 2;
      $aktuelleDaten["Anz_MPP_Trackers"] = 2;
      $aktuelleDaten["Modell"] = "SOFAR 80..136KTL ";
      break;

    case "R1":
      $Protokoll = 2;
      $aktuelleDaten["Anz_MPP_Trackers"] = 2;
      $aktuelleDaten["Modell"] = "SOFAR 255KTL-HV";
      break;

    case "S1":
      $Protokoll = 2;
      $aktuelleDaten["Anz_MPP_Trackers"] = 2;
      $aktuelleDaten["Modell"] = "SOFAR 3.3..50KTLX-G3";
      break;

    case "U1":
      $Protokoll = 2;
      $aktuelleDaten["Modell"] = "Sofar ME 5..20KTL-3PH";
      break;

    default:
      $Protokoll = 0;
      $aktuelleDaten["Modell"] = "unbekannt";
      $funktionen->log_schreiben( "Das Modell ist noch nicht bekannt: ".$funktionen->hex2string( substr( $rc["data"], 6, 4 )), "   ", 7 );
      break;
  }
  //***************************************
  if ($Protokoll == 2) {
    $aktuelleDaten["Anz_PV_Strings"] = 4;
    $aktuelleDaten["Produkt"] = hexdec( substr( $rc["data"], 0, 4 ));
    $aktuelleDaten["Seriennummer"] = $funktionen->hex2string( substr( $rc["data"], 4, 28 ));
    $aktuelleDaten["Hardware"] = $funktionen->hex2string( substr( $rc["data"], 32, 8 ));
    $aktuelleDaten["Firmware"] = $funktionen->hex2string( substr( $rc["data"], 40, 48 ));
    $aktuelleDaten["ModellID"] = hexdec( substr( $rc["data"], 0, 4 ));
    $funktionen->log_schreiben( "Seriennummer: ".$aktuelleDaten["Seriennummer"], "   ", 7 );
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "404", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0020";
    $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["DeviceStatus"] = hexdec( substr( $rc["data"], 0, 4 ));
    $aktuelleDaten["FehlerCode"] = hexdec( substr( $rc["data"], 4, 4 ));
    $aktuelleDaten["Temperatur1"] = hexdec( substr( $rc["data"], 88, 4 ));
    $aktuelleDaten["Temperatur"] = hexdec( substr( $rc["data"], 112, 4 ));
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "484", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0023";
    $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["AC_Frequenz"] = hexdec( substr( $rc["data"], 0, 4 )) / 100;
    $aktuelleDaten["AC_Leistung"] = $funktionen->hexdecs( substr( $rc["data"], 4, 4 )) * 10;
    $aktuelleDaten["Einspeisung_Bezug"] = $funktionen->hexdecs( substr( $rc["data"], 16, 4 )) * 10;
    $aktuelleDaten["AC_Spannung_R"] = hexdec( substr( $rc["data"], 36, 4 )) / 10;
    $aktuelleDaten["AC_Strom_R"] = hexdec( substr( $rc["data"], 40, 4 )) / 100;
    $aktuelleDaten["AC_Leistung_R"] = $funktionen->hexdecs( substr( $rc["data"], 44, 4 )) * 10;
    $aktuelleDaten["AC_Spannung_S"] = hexdec( substr( $rc["data"], 80, 4 )) / 10;
    $aktuelleDaten["AC_Strom_S"] = hexdec( substr( $rc["data"], 84, 4 )) / 100;
    $aktuelleDaten["AC_Leistung_S"] = $funktionen->hexdecs( substr( $rc["data"], 88, 4 )) * 10;
    $aktuelleDaten["AC_Spannung_T"] = hexdec( substr( $rc["data"], 124, 4 )) / 10;
    $aktuelleDaten["AC_Strom_T"] = hexdec( substr( $rc["data"], 128, 4 )) / 100;
    $aktuelleDaten["AC_Leistung_T"] = $funktionen->hexdecs( substr( $rc["data"], 132, 4 )) * 10;
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "584", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "000C";
    $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["PV1_Spannung"] = hexdec( substr( $rc["data"], 0, 4 )) / 10;
    $aktuelleDaten["PV1_Strom"] = hexdec( substr( $rc["data"], 4, 4 )) / 100;
    $aktuelleDaten["PV1_Leistung"] = hexdec( substr( $rc["data"], 8, 4 )) * 10;
    $aktuelleDaten["PV2_Spannung"] = hexdec( substr( $rc["data"], 12, 4 )) / 10;
    $aktuelleDaten["PV2_Strom"] = hexdec( substr( $rc["data"], 16, 4 )) / 100;
    $aktuelleDaten["PV2_Leistung"] = hexdec( substr( $rc["data"], 20, 4 )) * 10;
    $aktuelleDaten["PV3_Spannung"] = hexdec( substr( $rc["data"], 24, 4 )) / 10;
    $aktuelleDaten["PV3_Strom"] = hexdec( substr( $rc["data"], 28, 4 )) / 100;
    $aktuelleDaten["PV3_Leistung"] = hexdec( substr( $rc["data"], 32, 4 )) * 10;
    $aktuelleDaten["PV4_Spannung"] = hexdec( substr( $rc["data"], 36, 4 )) / 10;
    $aktuelleDaten["PV4_Strom"] = hexdec( substr( $rc["data"], 40, 4 )) / 100;
    $aktuelleDaten["PV4_Leistung"] = hexdec( substr( $rc["data"], 44, 4 )) * 10;
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "5C4", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["PV_Leistung"] = hexdec( substr( $rc["data"], 0, 4 )) * 100;
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "604", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0010";
    $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["Batterie_Spannung"] = hexdec( substr( $rc["data"], 0, 4 )) / 10;
    $aktuelleDaten["Batterie_Strom"] = $funktionen->hexdecs( substr( $rc["data"], 4, 4 )) / 100;
    $aktuelleDaten["Batterie_Leistung"] = $funktionen->hexdecs( substr( $rc["data"], 8, 4 )) * 10;
    $aktuelleDaten["Batterie_Temperatur"] = $funktionen->hexdecs( substr( $rc["data"], 12, 4 ));
    $aktuelleDaten["Batterie_SOC"] = hexdec( substr( $rc["data"], 16, 4 ));
    $aktuelleDaten["Batterie_Zyklus"] = hexdec( substr( $rc["data"], 24, 4 ));
    $aktuelleDaten["Batterie2_Spannung"] = hexdec( substr( $rc["data"], 28, 4 )) / 10;
    $aktuelleDaten["Batterie2_Strom"] = $funktionen->hexdecs( substr( $rc["data"], 32, 4 )) / 100;
    $aktuelleDaten["Batterie2_Leistung"] = $funktionen->hexdecs( substr( $rc["data"], 36, 4 )) * 10;
    $aktuelleDaten["Batterie2_Temperatur"] = $funktionen->hexdecs( substr( $rc["data"], 40, 4 ));
    $aktuelleDaten["Batterie2_SOC"] = hexdec( substr( $rc["data"], 44, 4 ));
    $aktuelleDaten["Batterie2_Zyklus"] = hexdec( substr( $rc["data"], 52, 4 ));
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "667", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0003";
    $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["Batterie_Leistung"] = $funktionen->hexdecs( substr( $rc["data"], 0, 4 )) * 100;
    $aktuelleDaten["SOC"] = hexdec( substr( $rc["data"], 4, 4 ));
    $aktuelleDaten["SOH"] = hexdec( substr( $rc["data"], 8, 4 ));
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "684", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0033";
    $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["WattstundenGesamtHeute"] = hexdec( substr( $rc["data"], 0, 8 )) * 10;
    $aktuelleDaten["WattstundenGesamt"] = hexdec( substr( $rc["data"], 8, 8 )) * 100;
    $aktuelleDaten["HausverbrauchHeute"] = hexdec( substr( $rc["data"], 16, 8 )) * 10;
    $aktuelleDaten["HausverbrauchGesamt"] = hexdec( substr( $rc["data"], 24, 8 )) * 100;
    $aktuelleDaten["EinspeisungHeute"] = hexdec( substr( $rc["data"], 32, 8 )) * 10;
    $aktuelleDaten["EinspeisungGesamt"] = hexdec( substr( $rc["data"], 40, 8 )) * 100;
    $aktuelleDaten["BezugHeute"] = hexdec( substr( $rc["data"], 48, 8 )) * 10;
    $aktuelleDaten["BezugGesamt"] = hexdec( substr( $rc["data"], 56, 8 )) * 100;
    $aktuelleDaten["BatterieLadungHeute"] = hexdec( substr( $rc["data"], 64, 8 )) * 10;
    $aktuelleDaten["BatterieLadungGesamt"] = hexdec( substr( $rc["data"], 72, 8 )) * 100;
    $aktuelleDaten["BatterieEntladungHeute"] = hexdec( substr( $rc["data"], 80, 8 )) * 10;
    $aktuelleDaten["BatterieEntladungGesamt"] = hexdec( substr( $rc["data"], 88, 8 )) * 100;

    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "900D", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["Anz_BatteriePacks"] = hexdec( substr( $rc["data"], 0, 2 ));



    //********************************************
    if ($aktuelleDaten["Batterie_Leistung"] >= 0) {
      $aktuelleDaten["Batterie_Ladung"] = $aktuelleDaten["Batterie_Leistung"];
      $aktuelleDaten["Batterie_Entladung"] = 0;
    }
    else {
      $aktuelleDaten["Batterie_Entladung"] = abs( $aktuelleDaten["Batterie_Leistung"] );
      $aktuelleDaten["Batterie_Ladung"] = 0;
    }
    if ($aktuelleDaten["Batterie2_Leistung"] >= 0) {
      $aktuelleDaten["Batterie2_Ladung"] = $aktuelleDaten["Batterie2_Leistung"];
      $aktuelleDaten["Batterie2_Entladung"] = 0;
    }
    else {
      $aktuelleDaten["Batterie2_Entladung"] = abs( $aktuelleDaten["Batterie2_Leistung"] );
      $aktuelleDaten["Batterie2_Ladung"] = 0;
    }
    if ($aktuelleDaten["Einspeisung_Bezug"] >= 0) {
      $aktuelleDaten["Einspeisung"] = $aktuelleDaten["Einspeisung_Bezug"];
      $aktuelleDaten["Bezug"] = 0;
    }
    else {
      $aktuelleDaten["Einspeisung"] = 0;
      $aktuelleDaten["Bezug"] = abs( $aktuelleDaten["Einspeisung_Bezug"] );
    }

    switch ($aktuelleDaten["DeviceStatus"]) {

       case 0:
         $aktuelleDaten["Geraetestatus"] = "Wartestatus";
       break;

       case 1:
         $aktuelleDaten["Geraetestatus"] = "Erkennungsstatus";
       break;

       case 2:
         $aktuelleDaten["Geraetestatus"] = "Netz gekoppelt";
       break;

       case 3:
         $aktuelleDaten["Geraetestatus"] = "Netzteilproblem";
       break;

       case 4:
         $aktuelleDaten["Geraetestatus"] = "Warnung";
       break;

       case 5:
         $aktuelleDaten["Geraetestatus"] = "Fehler";
       break;

       case 6:
         $aktuelleDaten["Geraetestatus"] = "Upgrade Status";
       break;

       case 7:
         $aktuelleDaten["Geraetestatus"] = "Self-charging";
       break;

       case 8:
         $aktuelleDaten["Geraetestatus"] = "SVG Status";
       break;

       case 9:
         $aktuelleDaten["Geraetestatus"] = "PID Status";
       break;
    }

  }
  elseif ($Protokoll == 1) {
  
    $aktuelleDaten["Modell"] = "KTL-X";
    $Befehl["DeviceID"] = "01";
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = str_pad( "2000", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0010";
    $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["Produkt"] = hexdec( substr( $rc["data"], 0, 4 ));
    $aktuelleDaten["Seriennummer"] = $funktionen->hex2string( substr( $rc["data"], 4, 28 ));
    $aktuelleDaten["Firmware"] = $funktionen->hex2string( substr( $rc["data"], 32, 8 ));
    $aktuelleDaten["Hardware"] = $funktionen->hex2string( substr( $rc["data"], 40, 8 ));
    $funktionen->log_schreiben( "Seriennummer: ".$aktuelleDaten["Seriennummer"], "   ", 7 );
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "0", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0030";
    $rc = $funktionen->phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
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
  }
  else {
    $funktionen->log_schreiben( "Dieses Protokoll ist noch nicht implementiert.", "   ", 5 );
    goto Ausgang;
  }
  $funktionen->log_schreiben( "Auslesen des Gerätes beendet.", "   ", 8 );

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
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  $funktionen->log_schreiben( print_r( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/sofarsolar_wr_math.php" )) {
    include 'sofarsolar_wr_math.php'; // Falls etwas neu berechnet werden muss.
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

/***********/
Ausgang:

/***********/
$funktionen->log_schreiben( "----------------------   Stop   sofarsolar_wr2.php   --------------------- ", "|--", 6 );
return;
?>