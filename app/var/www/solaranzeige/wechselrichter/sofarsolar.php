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
//  P2     HYD 5…20KTL-3PH      Hybrid    SOFAR HYD-3PH and SOFAR -G3
//  Q1     SOFAR 80…136KTL      Solar     SOFAR HYD-3PH and SOFAR -G3
//  R1     SOFAR 255KTL-HV      Solar     SOFAR HYD-3PH and SOFAR -G3
//  S1     SOFAR 3.3…50KTLX-G3  Solar     SOFAR HYD-3PH and SOFAR -G3
//  U1     ME 5…20KTL-3PH       Battery   SOFAR HYD-3PH and SOFAR -G3
//
//
***************************************************************************/
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$Version = "";
$RemoteDaten = true;
$Start = time( ); // Timestamp festhalten
Log::write( "----------------------   Start  sofarsolar.php   --------------------- ", "|--", 6 );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
$aktuelleDaten["KeineSonne"] = false;
$Timer = 800000; // Wartezeit für das Lesen der MODBUS Daten
setlocale( LC_TIME, "de_DE.utf8" );
//  Hardware Version ermitteln.
//  $Platine = "Raspberry Pi Model B Plus Rev 1.2";
$Teile = explode( " ", $Platine );
if ($Teile[1] == "Pi") {
  Log::write( "Hardware Version: ".$Platine, "o  ", 8 );
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
Log::write( "WR_ID: ".$WR_ID, "+  ", 8 );
$USB1 = USB::openUSB( $USBRegler );
if (!is_resource( $USB1 )) {
  Log::write( "USB Port kann nicht geöffnet werden. [1]", "XX ", 7 );
  Log::write( "Exit.... ", "XX ", 7 );
  goto Ausgang;
}
$i = 1;
do {
  Log::write( "Die Daten werden ausgelesen...", "+  ", 9 );

  /****************************************************************************
  //  Ab hier wird der Regler ausgelesen.
  //  verschiedene Modelle
  ****************************************************************************/
  $Protokoll = 1;
  $aktuelleDaten["AC"]["Einspeisung"] = 0;
  $aktuelleDaten["AC"]["Bezug"] = 0;
  $aktuelleDaten["AC"]["Hausverbrauch"] = 0;
  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterAddress"] = str_pad( "0044", 4, "0", STR_PAD_LEFT );
  $Befehl["RegisterCount"] = "0001";
  $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  if ($rc == false) {
    Log::write( "Das Gerät kann nicht ausgelesen werden.", "   ", 7 );
    goto Ausgang;
  }
  $aktuelleDaten["Service"]["ModbusProtokollVersion"] = substr( $rc["data"], 0, 4 );
  Log::write( "Modbus Protokoll Version: ".$aktuelleDaten["Service"]["ModbusProtokollVersion"], "   ", 7 );
  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterAddress"] = str_pad( "444", 4, "0", STR_PAD_LEFT );
  $Befehl["RegisterCount"] = "0016";
  $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
  $aktuelleDaten["Service"]["Seriennummer.Text"] = Utils::hex2string( substr( $rc["data"], 4, 28 ));
  Log::write( "Modell: ".Utils::hex2string( substr( $rc["data"], 6, 4 )), "   ", 7 );
  switch (Utils::hex2string( substr( $rc["data"], 6, 4 ))) {

    case "A1":
      $Protokoll = 1;
      $aktuelleDaten["Service"]["Modell.Text"] = "Sofar 1000..3000TL";
      break;

    case "A3":
      $Protokoll = 1;
      $aktuelleDaten["Service"]["Modell.Text"] = "Sofar 1100..3300TL-G3";
      break;

    case "B1":
      $Protokoll = 1;
      $aktuelleDaten["Service"]["Modell.Text"] = "Sofar 3..6KTLM";
      break;

    case "C1":
    case "C2":
    case "C3":
    case "C4":
      $Protokoll = 1;
      $aktuelleDaten["Service"]["Modell.Text"] = "Sofar 10..20KTL";
      break;

    case "D1":
    case "D2":
    case "D3":
    case "D4":
      $Protokoll = 1;
      $aktuelleDaten["Service"]["Modell.Text"] = "Sofar 30..40KTL";
      break;

    case "E1":
      $Protokoll = 3;
      $aktuelleDaten["Service"]["Modell.Text"] = "Sofar ME 3000-SP";
      break;

    case "F1":
    case "F2":
    case "F3":
    case "F4":
      $Protokoll = 1;
      $aktuelleDaten["Modell"] = "Sofar 3.3..12KTL";
      break;

    case "H1":
      $Protokoll = 1;
      $aktuelleDaten["Modell"] = "Sofar 3..7.5KTL";
      break;

    case "J2":
      $Protokoll = 1;
      $aktuelleDaten["Modell"] = "Sofar 50..70KTL";
      break;

    case "M1":
      $Protokoll = 3;
      $aktuelleDaten["Service"]["Modell.Text"] = "Sofar HYD 3000..6000-ES";
      break;

    case "M2":
      $Protokoll = 2;
      $aktuelleDaten["Service"]["Modell.Text"] = "Sofar HYD 3000..6000-EP";
      break;

    case "P1":
    case "P2":
      $Protokoll = 2;
      $aktuelleDaten["Service"]["Modell.Text"] = "Sofar HYD 5..20KTL-3PH";
      break;

    case "Q1":
      $Protokoll = 2;
      $aktuelleDaten["Service"]["Modell.Text"] = "SOFAR 80..136KTL ";
      break;

    case "R1":
      $Protokoll = 2;
      $aktuelleDaten["Service"]["Modell.Text"] = "SOFAR 255KTL-HV";
      break;

    case "S1":
      $Protokoll = 2;
      $aktuelleDaten["Service"]["Modell.Text"] = "SOFAR 3.3..50KTLX-G3";
      break;

    case "U1":
      $Protokoll = 2;
      $aktuelleDaten["Service"]["Modell.Text"] = "Sofar ME 5..20KTL-3PH";
      break;

    default:
      $Protokoll = 0;
      $aktuelleDaten["Service"]["Modell.Text"] = "unbekannt";
      Log::write( "Das Modell ist noch nicht bekannt: ".Utils::hex2string( substr( $rc["data"], 6, 4 )), "   ", 7 );
      break;
  }
  //***************************************
  if ($Protokoll == 2) {
    $aktuelleDaten["Info"]["Produkt.Text"] = hexdec( substr( $rc["data"], 0, 4 ));
    $aktuelleDaten["Service"]["Seriennummer.Text"] = Utils::hex2string( substr( $rc["data"], 4, 28 ));
    $aktuelleDaten["Service"]["Hardware.Text"] = Utils::hex2string( substr( $rc["data"], 32, 8 ));
    $aktuelleDaten["Info"]["Firmware.Text"] = Utils::hex2string( substr( $rc["data"], 40, 48 ));
    $aktuelleDaten["Service"]["ModellID"] = hexdec( substr( $rc["data"], 0, 4 ));
    Log::write( "Seriennummer: ".$aktuelleDaten["Service"]["Seriennummer.Text"], "   ", 7 );
    //
    //***************************************
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "404", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0020";
    $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["Service"]["DeviceStatus"] = hexdec( substr( $rc["data"], 0, 4 ));
    $aktuelleDaten["Service"]["FehlerCode"] = hexdec( substr( $rc["data"], 4, 4 ));
    // 409 - 20
    // 40E - 40
    // 413 - 60
    $aktuelleDaten["Service"]["TempInnen"] = Utils::hexdecs( substr( $rc["data"], 80, 4 ));
    // 419 - 84
    $aktuelleDaten["Service"]["TempKuehler"] = Utils::hexdecs( substr( $rc["data"], 88, 4 ));
    // 41B - 92
    // 41C - 96
    // 41D - 100
    // 41E - 104
    // 41F - 108
    $aktuelleDaten["Service"]["TempWR"] = Utils::hexdecs( substr( $rc["data"], 112, 4 ));
    //
    //***************************************
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "484", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0039";
    $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["AC"]["Frequenz"] = hexdec( substr( $rc["data"], 0, 4 )) / 100;
    $aktuelleDaten["AC"]["WRLeistungTotal"] = Utils::hexdecs( substr( $rc["data"], 4, 4 )) * 10;
    //$aktuelleDaten["AC"]["WRBlindleistungTotal"] = Utils::hexdecs( substr( $rc["data"], 8, 4 )) * 10;
    //$aktuelleDaten["AC"]["WRScheinleistungTotal"] = Utils::hexdecs( substr( $rc["data"], 12, 4 )) * 10;
    $aktuelleDaten["AC"]["Einspeisung_Bezug"] = Utils::hexdecs( substr( $rc["data"], 16, 4 )) * 10;
    $aktuelleDaten["AC"]["SpannungL1"] = hexdec( substr( $rc["data"], 36, 4 )) / 10;
    $aktuelleDaten["AC"]["WRStromL1"] = hexdec( substr( $rc["data"], 40, 4 )) / 100;
    $aktuelleDaten["AC"]["WRLeistungL1"] = Utils::hexdecs( substr( $rc["data"], 44, 4 )) * 10;
    //$aktuelleDaten["AC"]["WRBlindleistungL1"] = Utils::hexdecs( substr( $rc["data"], 48, 4 )) * 10;
    $aktuelleDaten["AC"]["NETZStromL1"] = hexdec( substr( $rc["data"], 56, 4 )) / 100;
    $aktuelleDaten["AC"]["NETZLeistungL1"] = Utils::hexdecs( substr( $rc["data"], 60, 4 )) * 10;
    //$aktuelleDaten["AC"]["NETZBlindleistungL1"] = Utils::hexdecs( substr( $rc["data"], 64, 4 )) * 10;
    $aktuelleDaten["AC"]["SpannungL2"] = hexdec( substr( $rc["data"], 80, 4 )) / 10;
    $aktuelleDaten["AC"]["WRStromL2"] = hexdec( substr( $rc["data"], 84, 4 )) / 100;
    $aktuelleDaten["AC"]["WRLeistungL2"] = Utils::hexdecs( substr( $rc["data"], 88, 4 )) * 10;
    //$aktuelleDaten["AC"]["WRBlindleistungL2"] = Utils::hexdecs( substr( $rc["data"], 92, 4 )) * 10;
    $aktuelleDaten["AC"]["NETZStromL2"] = hexdec( substr( $rc["data"], 100, 4 )) / 100;
    $aktuelleDaten["AC"]["NETZLeistungL2"] = Utils::hexdecs( substr( $rc["data"], 104, 4 )) * 10;
    //$aktuelleDaten["AC"]["NETZBlindleistungL2"] = Utils::hexdecs( substr( $rc["data"], 108, 4 )) * 10;
    $aktuelleDaten["AC"]["SpannungL3"] = hexdec( substr( $rc["data"], 124, 4 )) / 10;
    $aktuelleDaten["AC"]["WRStromL3"] = hexdec( substr( $rc["data"], 128, 4 )) / 100;
    $aktuelleDaten["AC"]["WRLeistungL3"] = Utils::hexdecs( substr( $rc["data"], 132, 4 )) * 10;
    //$aktuelleDaten["AC"]["WRBlindleistungL3"] = Utils::hexdecs( substr( $rc["data"], 136, 4 )) * 10;
    $aktuelleDaten["AC"]["NETZStromL3"] = hexdec( substr( $rc["data"], 144, 4 )) / 100;
    $aktuelleDaten["AC"]["NETZLeistungL3"] = Utils::hexdecs( substr( $rc["data"], 148, 4 )) * 10;
    //$aktuelleDaten["AC"]["NETZBlindleistungL3"] = Utils::hexdecs( substr( $rc["data"], 152, 4 )) * 10;
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "4AF", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0001";
    $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["AC"]["Hausverbrauch"] = Utils::hexdecs( substr( $rc["data"], 0, 4 )) * 10;
    //***************************************
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "504", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0020";
    $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["EPS"]["Leistung"] = Utils::hexdecs( substr( $rc["data"], 0, 4 )) * 10;
    // 505 - 4
    // 506 - 8
    $aktuelleDaten["EPS"]["Frequenz"] = hexdec( substr( $rc["data"], 12, 4 )) / 100;
    // 507 - 16
    // 509 - 20
    $aktuelleDaten["EPS"]["Spannung_R"] = hexdec( substr( $rc["data"], 24, 4 )) / 10;
    $aktuelleDaten["EPS"]["Strom_R"] = hexdec( substr( $rc["data"], 28, 4 )) / 100;
    $aktuelleDaten["EPS"]["Leistung_R"] = Utils::hexdecs( substr( $rc["data"], 32, 4 )) * 10;
    // 50D - 36
    // 50E - 40
    // 50F - 44
    $aktuelleDaten["EPS"]["Spannung_Last_R"] = hexdec( substr( $rc["data"], 48, 4 )) / 10;
    // 511 - 52
    $aktuelleDaten["EPS"]["Spannung_S"] = hexdec( substr( $rc["data"], 56, 4 )) / 10;
    $aktuelleDaten["EPS"]["Strom_S"] = hexdec( substr( $rc["data"], 60, 4 )) / 100;
    $aktuelleDaten["EPS"]["Leistung_S"] = Utils::hexdecs( substr( $rc["data"], 64, 4 )) * 10;
    // 515 - 68
    // 516 - 72
    // 517 - 76
    $aktuelleDaten["EPS"]["Spannung_Last_S"] = hexdec( substr( $rc["data"], 80, 4 )) / 10;
    // 515 - 84
    $aktuelleDaten["EPS"]["Spannung_T"] = hexdec( substr( $rc["data"], 88, 4 )) / 10;
    $aktuelleDaten["EPS"]["Strom_T"] = hexdec( substr( $rc["data"], 92, 4 )) / 100;
    $aktuelleDaten["EPS"]["Leistung_T"] = Utils::hexdecs( substr( $rc["data"], 96, 4 )) * 10;
    // 50D - 100
    // 50E - 104
    // 50F - 108
    $aktuelleDaten["EPS"]["Spannung_Last_T"] = hexdec( substr( $rc["data"], 112, 4 )) / 10;
    //***************************************
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "584", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "000C";
    $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["PV1"]["Spannung"] = hexdec( substr( $rc["data"], 0, 4 )) / 10;
    $aktuelleDaten["PV1"]["Strom"] = hexdec( substr( $rc["data"], 4, 4 )) / 100;
    $aktuelleDaten["PV1"]["Leistung"] = hexdec( substr( $rc["data"], 8, 4 )) * 10;
    $aktuelleDaten["PV2"]["Spannung"] = hexdec( substr( $rc["data"], 12, 4 )) / 10;
    $aktuelleDaten["PV2"]["Strom"] = hexdec( substr( $rc["data"], 16, 4 )) / 100;
    $aktuelleDaten["PV2"]["Leistung"] = hexdec( substr( $rc["data"], 20, 4 )) * 10;
    $aktuelleDaten["PV3"]["Spannung"] = hexdec( substr( $rc["data"], 24, 4 )) / 10;
    $aktuelleDaten["PV3"]["Strom"] = hexdec( substr( $rc["data"], 28, 4 )) / 100;
    $aktuelleDaten["PV3"]["Leistung"] = hexdec( substr( $rc["data"], 32, 4 )) * 10;
    $aktuelleDaten["PV4"]["Spannung"] = hexdec( substr( $rc["data"], 36, 4 )) / 10;
    $aktuelleDaten["PV4"]["Strom"] = hexdec( substr( $rc["data"], 40, 4 )) / 100;
    $aktuelleDaten["PV4"]["Leistung"] = hexdec( substr( $rc["data"], 44, 4 )) * 10;
    //
    //***************************************
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "5C4", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0001";
    $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["PV"]["Leistung"] = hexdec( substr( $rc["data"], 0, 4 )) * 100;
    //
    //***************************************
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "604", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0010";
    $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["Batterie"]["Spannung"] = hexdec( substr( $rc["data"], 0, 4 )) / 10;
    $aktuelleDaten["Batterie"]["Strom"] = Utils::hexdecs( substr( $rc["data"], 4, 4 )) / 100;
    $aktuelleDaten["Batterie"]["Leistung"] = Utils::hexdecs( substr( $rc["data"], 8, 4 )) * 10;
    $aktuelleDaten["Batterie"]["Temperatur"] = Utils::hexdecs( substr( $rc["data"], 12, 4 ));
    $aktuelleDaten["Batterie"]["SOC"] = hexdec( substr( $rc["data"], 16, 4 ));
    $aktuelleDaten["Batterie"]["SOH"] = hexdec( substr( $rc["data"], 20, 4 ));
    $aktuelleDaten["Batterie"]["Zyklus"] = hexdec( substr( $rc["data"], 24, 4 ));
    if (hexdec( substr( $rc["data"], 28, 4 )) > 0) {
      $aktuelleDaten["Batterie2"]["Spannung"] = hexdec( substr( $rc["data"], 28, 4 )) / 10;
      $aktuelleDaten["Batterie2"]["Strom"] = Utils::hexdecs( substr( $rc["data"], 32, 4 )) / 100;
      $aktuelleDaten["Batterie2"]["Leistung"] = Utils::hexdecs( substr( $rc["data"], 36, 4 )) * 10;
      $aktuelleDaten["Batterie2"]["Temperatur"] = Utils::hexdecs( substr( $rc["data"], 40, 4 ));
      $aktuelleDaten["Batterie2"]["SOC"] = hexdec( substr( $rc["data"], 44, 4 ));
      $aktuelleDaten["Batterie2"]["SOH"] = hexdec( substr( $rc["data"], 48, 4 ));
      $aktuelleDaten["Batterie2"]["Zyklus"] = hexdec( substr( $rc["data"], 52, 4 ));
    }
    //
    //***************************************
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "667", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0003";
    $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["Batterie"]["Leistung"] = Utils::hexdecs( substr( $rc["data"], 0, 4 )) * 100;
    $aktuelleDaten["Batterie"]["SOC"] = hexdec( substr( $rc["data"], 4, 4 ));
    $aktuelleDaten["Batterie"]["SOH"] = hexdec( substr( $rc["data"], 8, 4 ));
    //
    //***************************************
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "684", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0033";
    $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["Summen"]["WattstundenGesamtHeute"] = hexdec( substr( $rc["data"], 0, 8 )) * 10;
    $aktuelleDaten["Summen"]["WattstundenGesamt"] = hexdec( substr( $rc["data"], 8, 8 )) * 100;
    $aktuelleDaten["Summen"]["HausverbrauchHeute"] = hexdec( substr( $rc["data"], 16, 8 )) * 10;
    $aktuelleDaten["Summen"]["HausverbrauchGesamt"] = hexdec( substr( $rc["data"], 24, 8 )) * 100;
    $aktuelleDaten["Summen"]["NetzbezugHeute"] = hexdec( substr( $rc["data"], 32, 8 )) * 10;
    $aktuelleDaten["Summen"]["NetzbezugGesamt"] = hexdec( substr( $rc["data"], 40, 8 )) * 100;
    $aktuelleDaten["Summen"]["EinspeisungHeute"] = hexdec( substr( $rc["data"], 48, 8 )) * 10;
    $aktuelleDaten["Summen"]["EinspeisungGesamt"] = hexdec( substr( $rc["data"], 56, 8 )) * 100;
    $aktuelleDaten["Summen"]["BatterieLadungHeute"] = hexdec( substr( $rc["data"], 64, 8 )) * 10;
    $aktuelleDaten["Summen"]["BatterieLadungGesamt"] = hexdec( substr( $rc["data"], 72, 8 )) * 100;
    $aktuelleDaten["Summen"]["BatterieEntladungHeute"] = hexdec( substr( $rc["data"], 80, 8 )) * 10;
    $aktuelleDaten["Summen"]["BatterieEntladungGesamt"] = hexdec( substr( $rc["data"], 88, 8 )) * 100;
    //
    //***************************************
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "900D", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0001";
    $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["Batterie"]["Anz_BatteriePacks"] = hexdec( substr( $rc["data"], 0, 2 ));
    //
    //***************************************
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "9051", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0012";
    $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["BattInfo"]["Zellspannung1"] = (hexdec( substr( $rc["data"], 0, 4 )) / 1000);
    $aktuelleDaten["BattInfo"]["Zellspannung2"] = (hexdec( substr( $rc["data"], 4, 4 )) / 1000);
    $aktuelleDaten["BattInfo"]["Zellspannung3"] = (hexdec( substr( $rc["data"], 8, 4 )) / 1000);
    $aktuelleDaten["BattInfo"]["Zellspannung4"] = (hexdec( substr( $rc["data"], 12, 4 )) / 1000);
    $aktuelleDaten["BattInfo"]["Zellspannung5"] = (hexdec( substr( $rc["data"], 16, 4 )) / 1000);
    $aktuelleDaten["BattInfo"]["Zellspannung6"] = (hexdec( substr( $rc["data"], 20, 4 )) / 1000);
    $aktuelleDaten["BattInfo"]["Zellspannung7"] = (hexdec( substr( $rc["data"], 24, 4 )) / 1000);
    $aktuelleDaten["BattInfo"]["Zellspannung8"] = (hexdec( substr( $rc["data"], 28, 4 )) / 1000);
    $aktuelleDaten["BattInfo"]["Zellspannung9"] = (hexdec( substr( $rc["data"], 32, 4 )) / 1000);
    $aktuelleDaten["BattInfo"]["Zellspannung10"] = (hexdec( substr( $rc["data"], 36, 4 )) / 1000);
    $aktuelleDaten["BattInfo"]["Zellspannung11"] = (hexdec( substr( $rc["data"], 40, 4 )) / 1000);
    $aktuelleDaten["BattInfo"]["Zellspannung12"] = (hexdec( substr( $rc["data"], 44, 4 )) / 1000);
    $aktuelleDaten["BattInfo"]["Zellspannung13"] = (hexdec( substr( $rc["data"], 48, 4 )) / 1000);
    $aktuelleDaten["BattInfo"]["Zellspannung14"] = (hexdec( substr( $rc["data"], 52, 4 )) / 1000);
    $aktuelleDaten["BattInfo"]["Zellspannung15"] = (hexdec( substr( $rc["data"], 56, 4 )) / 1000);
    $aktuelleDaten["BattInfo"]["Zellspannung16"] = (hexdec( substr( $rc["data"], 60, 4 )) / 1000);
    //********************************************
    if ($aktuelleDaten["Batterie"]["Leistung"] >= 0) {
      $aktuelleDaten["Batterie"]["LadeLeistung"] = $aktuelleDaten["Batterie"]["Leistung"];
      $aktuelleDaten["Batterie"]["EntladeLeistung"] = 0;
    }
    else {
      $aktuelleDaten["Batterie"]["EntladeLeistung"] = abs( $aktuelleDaten["Batterie"]["Leistung"] );
      $aktuelleDaten["Batterie"]["LadeLeistung"] = 0;
    }
    if (isset($aktuelleDaten["Batterie2"]["Leistung"])) {
      if ($aktuelleDaten["Batterie2"]["Leistung"] >= 0) {
        $aktuelleDaten["Batterie2"]["LadeLeistung"] = $aktuelleDaten["Batterie2"]["Leistung"];
        $aktuelleDaten["Batterie2"]["EntladeLeistung"] = 0;
      }
      else {
        $aktuelleDaten["Batterie2"]["EntladeLeistung"] = abs( $aktuelleDaten["Batterie2"]["Leistung"] );
        $aktuelleDaten["Batterie2"]["LadeLeistung"] = 0;
      }
    }
    if ($aktuelleDaten["AC"]["Einspeisung_Bezug"] >= 0) {
      $aktuelleDaten["AC"]["Einspeisung"] = $aktuelleDaten["AC"]["Einspeisung_Bezug"];
      $aktuelleDaten["AC"]["Bezug"] = 0;
    }
    else {
      $aktuelleDaten["AC"]["Einspeisung"] = 0;
      $aktuelleDaten["AC"]["Bezug"] = abs( $aktuelleDaten["AC"]["Einspeisung_Bezug"] );
    }
    switch ($aktuelleDaten["Service"]["DeviceStatus"]) {

      case 0:
        $aktuelleDaten["Service"]["Geraetestatus.Text"] = "Wartestatus";
        break;

      case 1:
        $aktuelleDaten["Service"]["Geraetestatus.Text"] = "Erkennungsstatus";
        break;

      case 2:
        $aktuelleDaten["Service"]["Geraetestatus.Text"] = "Netz gekoppelt";
        break;

      case 3:
        $aktuelleDaten["Service"]["Geraetestatus.Text"] = "Netzteilproblem";
        break;

      case 4:
        $aktuelleDaten["Service"]["Geraetestatus.Text"] = "Warnung";
        break;

      case 5:
        $aktuelleDaten["Service"]["Geraetestatus.Text"] = "Fehler";
        break;

      case 6:
        $aktuelleDaten["Service"]["Geraetestatus.Text"] = "Upgrade Status";
        break;

      case 7:
        $aktuelleDaten["Service"]["Geraetestatus.Text"] = "Self-charging";
        break;

      case 8:
        $aktuelleDaten["Service"]["Geraetestatus.Text"] = "SVG Status";
        break;

      case 9:
        $aktuelleDaten["Service"]["Geraetestatus.Text"] = "PID Status";
        break;
    }
  }
  elseif ($Protokoll == 1) {

    /************************************************************************
    //  PROTOKOLL 1       PROTOKOLL 1       PROTOKOLL 1       PROTOKOLL 1
    ************************************************************************/
    $aktuelleDaten["Service"]["Modell.Text"] = "KTL-X";
    //
    //***************************************
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = str_pad( "2000", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0010";
    $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["Info"]["Produkt.Text"] = hexdec( substr( $rc["data"], 0, 4 ));
    $aktuelleDaten["Service"]["Seriennummer.Text"] = Utils::hex2string( substr( $rc["data"], 4, 28 ));
    $aktuelleDaten["Info"]["Firmware.Text"] = Utils::hex2string( substr( $rc["data"], 32, 8 ));
    $aktuelleDaten["Service"]["Hardware.Text"] = Utils::hex2string( substr( $rc["data"], 40, 8 ));
    Log::write( "Seriennummer: ".$aktuelleDaten["Service"]["Seriennummer.Text"], "   ", 7 );
    //
    //***************************************
    $Befehl["DeviceID"] = $WR_ID;
    $Befehl["BefehlFunctionCode"] = "03";
    $Befehl["RegisterAddress"] = str_pad( "0", 4, "0", STR_PAD_LEFT );
    $Befehl["RegisterCount"] = "0030";
    $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl, $Timer );
    $aktuelleDaten["Service"]["Status"] = hexdec( substr( $rc["data"], 0, 4 )); // 0
    $aktuelleDaten["Service"]["Fehler1"] = hexdec( substr( $rc["data"], 4, 4 )); // 1
    $aktuelleDaten["Service"]["Fehler2"] = hexdec( substr( $rc["data"], 8, 4 ));
    $aktuelleDaten["Service"]["Fehler3"] = hexdec( substr( $rc["data"], 12, 4 ));
    $aktuelleDaten["Service"]["Fehler4"] = hexdec( substr( $rc["data"], 16, 4 ));
    $aktuelleDaten["Service"]["Fehler5"] = hexdec( substr( $rc["data"], 20, 4 ));
    $aktuelleDaten["PV1"]["Spannung"] = hexdec( substr( $rc["data"], 24, 4 )) / 10;
    $aktuelleDaten["PV1"]["Strom"] = hexdec( substr( $rc["data"], 28, 4 )) / 100;
    $aktuelleDaten["PV2"]["Spannung"] = hexdec( substr( $rc["data"], 32, 4 )) / 10;
    $aktuelleDaten["PV2"]["Strom"] = hexdec( substr( $rc["data"], 36, 4 )) / 100;
    $aktuelleDaten["PV1"]["Leistung"] = hexdec( substr( $rc["data"], 48, 4 )) * 10; // A
    $aktuelleDaten["PV2"]["Leistung"] = hexdec( substr( $rc["data"], 52, 4 )) * 10; // B
    $aktuelleDaten["AC"]["Frequenz"] = hexdec( substr( $rc["data"], 56, 4 )) / 100; // C Ab hier + 2 in der Dokumentation
    $aktuelleDaten["AC"]["Spannung_R"] = hexdec( substr( $rc["data"], 60, 4 )) / 10; // D  => F
    $aktuelleDaten["AC"]["Strom_R"] = hexdec( substr( $rc["data"], 64, 4 )) / 100; // E  => 10
    $aktuelleDaten["AC"]["Spannung_S"] = hexdec( substr( $rc["data"], 68, 4 )) / 10; // F  => 11
    $aktuelleDaten["AC"]["Strom_S"] = hexdec( substr( $rc["data"], 72, 4 )) / 100; // 10 => 12
    $aktuelleDaten["AC"]["Spannung_T"] = hexdec( substr( $rc["data"], 76, 4 )) / 10; // 11 => 13
    $aktuelleDaten["AC"]["Strom_T"] = hexdec( substr( $rc["data"], 80, 4 )) / 100; // 12 => 14
    $Energie_Total_H = (substr( $rc["data"], 84, 4 )); // 13 => 15
    $Energie_Total_L = (substr( $rc["data"], 88, 4 )); // 14 => 16
    $Laufzeit_Total_H = (substr( $rc["data"], 92, 4 )); // 15 => 17
    $Laufzeit_Total_L = (substr( $rc["data"], 96, 4 )); // 16 => 18
    $aktuelleDaten["Summen"]["WattstundenGesamtHeute"] = hexdec( substr( $rc["data"], 100, 4 )) * 10; // 17 => 19  Watt
    $aktuelleDaten["Summen"]["Laufzeit_Heute"] = hexdec( substr( $rc["data"], 104, 4 )); // 18 => 1A  Minuten
    $aktuelleDaten["Service"]["Temperatur_Module"] = hexdec( substr( $rc["data"], 108, 4 )); // 19 => 1B  °C
    $aktuelleDaten["Service"]["Temperatur"] = hexdec( substr( $rc["data"], 112, 4 )); // 1A => 1C  °C

    /****************************************************************************
    //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
    ****************************************************************************/
    $aktuelleDaten["Summen"]["Energie_Total"] = (hexdec( $Energie_Total_H.$Energie_Total_L ) * 1000);
    $aktuelleDaten["Summen"]["Laufzeit_Total"] = hexdec( $Laufzeit_Total_H.$Laufzeit_Total_L );
    if ($aktuelleDaten["PV2"]["Strom"] > 0.1) {
      $aktuelleDaten["PV"]["Leistung"] = ($aktuelleDaten["PV1"]["Leistung"] + $aktuelleDaten["PV2"]["Leistung"]);
    }
    else {
      $aktuelleDaten["PV"]["PV_Leistung"] = $aktuelleDaten["PV1"]["Leistung"];
      $aktuelleDaten["PV2"]["Leistung"] = 0;
    }
  }
  else {
    Log::write( "Dieses Protokoll ist noch nicht implementiert.", "   ", 5 );
    goto Ausgang;
  }
  Log::write( "Auslesen des Gerätes beendet.", "   ", 8 );

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
  $aktuelleDaten["Info"]["Objekt.Text"] = $Objekt;
  $aktuelleDaten["Info"]["Produkt.Text"] = "SofarSolar";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  Log::write( print_r( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists($basedir."/custom/sofarsolar_math.php" )) {
    include $basedir.'/custom/sofarsolar_math.php'; // Falls etwas neu berechnet werden muss.
  }

  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT and strtoupper( $MQTTAuswahl ) != "OPENWB") {
    Log::write( "MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1 );
    require($basedir."/services/mqtt_senden.php");
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
    Log::write( "Schleife: ".($i)." Zeitspanne: ".(floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9 );
    sleep( floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write( "Schleife ".$i." Ausgang...", "   ", 8 );
    break;
  }
  $i++;
} while (($Start + 54) > time( ));
if (isset($aktuelleDaten["Service"])) {

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

/***********/
Ausgang:

/***********/
Log::write( "----------------------   Stop   sofarsolar.php   --------------------- ", "|--", 6 );
return;
?>