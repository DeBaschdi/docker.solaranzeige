#!/usr/bin/php
<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2015-2021]  [Ulrich Kunz]
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
//  Es dient dem Auslesen des Wechselrichters ALPHA ESS über eine RS485
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
$funktionen->log_schreiben( "----------------------   Start  alpha_ess.php   --------------------- ", "|--", 6 );
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

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//
*****************************************************************************/
$StatusFile = $Pfad."/database/".$GeraeteNummer.".WhProTag.txt";
if (!file_exists( $StatusFile )) {

  /***************************************************************************
  //  Inhalt der Status Datei anlegen, wenn nicht existiert.
  ***************************************************************************/
  $rc = file_put_contents( $StatusFile, "0" );
  if ($rc === false) {
    $funktionen->log_schreiben( "Konnte die Datei whProTag_delta.txt nicht anlegen.", 5 );
  }
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;
}
else {
  $aktuelleDaten["WattstundenGesamtHeute"] = file_get_contents( $StatusFile );
  $funktionen->log_schreiben( "WattstundenGesamtHeute: ".$aktuelleDaten["WattstundenGesamtHeute"], "   ", 8 );
}
/****************/
$USB1 = $funktionen->openUSB( $USBRegler );
if (!is_resource( $USB1 )) {
  $funktionen->log_schreiben( "USB Port kann nicht geöffnet werden. [1]", "XX ", 7 );
  $funktionen->log_schreiben( "Exit.... ", "XX ", 7 );
  goto Ausgang;
}
$i = 1;
do {
  $funktionen->log_schreiben( "Die Daten werden ausgelesen...", "+  ", 9 );
  $aktuelleDaten["Produkt"] = "Alpha ESS";
  $aktuelleDaten["Firmware"] = "1.0";

  /****************************************************************************
  //  Neue Software Version   Handuch V. 1.23
  //
  //  Ergebniswerte:
  //  $aktuelleDaten["Firmware"]                Nummer
  //  $aktuelleDaten["Produkt"]                 Text
  //  $aktuelleDaten["Objekt"]                  Text
  //
  //
  //  BMU-SW-Version"] = 157                Batterie Management Einheit
  //  LMU-SW-Version"] = 239                Lokale Management Einheit
  //  ISO-SW-Version"] = 155                Landesversion
  //
  ****************************************************************************/
  $Befehl["DeviceID"] = "55";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterAddress"] = "0115";
  $Befehl["RegisterCount"] = "0003";
  $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
  $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
  if ($rc == false) {
    $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
    $i++;
    continue;
  }
  $funktionen->log_schreiben( $rc, "   ", 9 );
  $aktuelleDaten["BMU-SW-Version"] = $funktionen->hexdecs( substr( $rc, 6, 4 ));
  $aktuelleDaten["LMU-SW-Version"] = $funktionen->hexdecs( substr( $rc, 10, 4 ));
  $aktuelleDaten["ISO-SW-Version"] = $funktionen->hexdecs( substr( $rc, 14, 4 ));
  $funktionen->log_schreiben( "BMU - LMU - ISO  SW-Versionen:   ".$aktuelleDaten["BMU-SW-Version"]." - ".$aktuelleDaten["LMU-SW-Version"]." - ".$aktuelleDaten["ISO-SW-Version"], "   ", 2 );

  /**************************************************************************
  //  Neuere Software Version                     Handbuch V. 1.23
  //
  //  Die Speicheradresse wird so errechnet:
  //  ((hexdec( 10 ) * 4) + 6)
  //  (( hedec("Hexstring") * 4 Stellen = 2 Byte ) + 6 Stellen Frameheader)
  //  Abzüglich noch der Speicherstellen Offset
  //
  //  [BMU-SW-Version] => 157
  //  [LMU-SW-Version] => 239
  //  [ISO-SW-Version] => 155
  //
  **************************************************************************/
  if ($aktuelleDaten["BMU-SW-Version"] > 155) {

    /************/
    $Befehl["RegisterAddress"] = "0000";
    $Befehl["RegisterCount"] = "0036";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
      $i++;
      continue;
    }
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $aktuelleDaten["NetzeinspeisungGesamt"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( 10 ) * 4) + 6), 8 )) / 100);
    $aktuelleDaten["NetzbezugGesamt"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( 12 ) * 4) + 6), 8 )) / 100);
    $aktuelleDaten["Netz_Spannung_R"] = $funktionen->hexdecs( substr( $rc, ((hexdec( 14 ) * 4) + 6), 4 )) / 10;
    $aktuelleDaten["Netz_Spannung_S"] = $funktionen->hexdecs( substr( $rc, ((hexdec( 15 ) * 4) + 6), 4 )) / 10;
    $aktuelleDaten["Netz_Spannung_T"] = $funktionen->hexdecs( substr( $rc, ((hexdec( 16 ) * 4) + 6), 4 )) / 10;
    $aktuelleDaten["Netz_Strom_R"] = $funktionen->hexdecs( substr( $rc, ((hexdec( 17 ) * 4) + 6), 4 )) / 100;
    $aktuelleDaten["Netz_Strom_S"] = $funktionen->hexdecs( substr( $rc, ((hexdec( 18 ) * 4) + 6), 4 )) / 100;
    $aktuelleDaten["Netz_Strom_T"] = $funktionen->hexdecs( substr( $rc, ((hexdec( 19 ) * 4) + 6), 4 )) / 100;
    $aktuelleDaten["Netz_Frequenz"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "1A" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["Netz_Wirkleistung_R"] = $funktionen->hexdecs( substr( $rc, ((hexdec( "1B" ) * 4) + 6), 8 ));
    $aktuelleDaten["Netz_Wirkleistung_S"] = $funktionen->hexdecs( substr( $rc, ((hexdec( "1D" ) * 4) + 6), 8 ));
    $aktuelleDaten["Netz_Wirkleistung_T"] = $funktionen->hexdecs( substr( $rc, ((hexdec( "1F" ) * 4) + 6), 8 ));
    $aktuelleDaten["Netz_WirkleistungGesamt"] = $funktionen->hexdecs( substr( $rc, ((hexdec( "21" ) * 4) + 6), 8 ));
    // Aus Kompatibilitätsgründen zur Zeit noch nötig
    $aktuelleDaten["AC_Wirkleistung_R"] = $aktuelleDaten["Netz_Wirkleistung_R"];
    $aktuelleDaten["AC_Wirkleistung_S"] = $aktuelleDaten["Netz_Wirkleistung_S"];
    $aktuelleDaten["AC_Wirkleistung_T"] = $aktuelleDaten["Netz_Wirkleistung_T"];
    $aktuelleDaten["AC_Wirkleistung"] = $aktuelleDaten["Netz_WirkleistungGesamt"];

    /************************/
    $Befehl["RegisterAddress"] = "0080";
    $Befehl["RegisterCount"] = "0036";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
      $i++;
      continue;
    }
    $funktionen->log_schreiben( $rc, "   ", 9 );
    //  Offset = 80    Speicherstelle 80 = 00 ,  90 = 10
    $aktuelleDaten["PV_EinspeisungGesamt"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "10" ) * 4) + 6), 8 )) * 10);
    $aktuelleDaten["PV_BezugGesamt"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "12" ) * 4) + 6), 8 )) * 10);
    $aktuelleDaten["PV_Wirkleistung_R"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "1B" ) * 4) + 6), 8 )));
    $aktuelleDaten["PV_Wirkleistung_S"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "1D" ) * 4) + 6), 8 )));
    $aktuelleDaten["PV_Wirkleistung_T"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "1F" ) * 4) + 6), 8 )));
    $aktuelleDaten["PV_LeistungGesamt"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "21" ) * 4) + 6), 8 )));
    $aktuelleDaten["AC_LeistungGesamt"] = $aktuelleDaten["PV_LeistungGesamt"];

    /*********/
    $Befehl["RegisterAddress"] = "0100";
    $Befehl["RegisterCount"] = "0048";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
      $i++;
      continue;
    }
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $aktuelleDaten["Batteriespannung"] = (hexdec( substr( $rc, ((hexdec( "0" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["Batteriestrom"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "1" ) * 4) + 6), 4 )) / 10);
    if ($aktuelleDaten["BMU-SW-Version"] >= 157) {
      $aktuelleDaten["SOC"] = (hexdec( substr( $rc, ((hexdec( "2" ) * 4) + 6), 4 )) / 10);
    }
    else {
      $aktuelleDaten["SOC"] = hexdec( substr( $rc, ((hexdec( "2" ) * 4) + 6), 4 ));
    }
    $aktuelleDaten["Batteriestatus"] = (substr( $rc, ((hexdec( "3" ) * 4) + 6), 4 ));
    $aktuelleDaten["Batterierelaisstatus"] = (substr( $rc, ((hexdec( "4" ) * 4) + 6), 4 ));
    $aktuelleDaten["Batterieanzahl"] = hexdec( substr( $rc, ((hexdec( "18" ) * 4) + 6), 4 ));
    $aktuelleDaten["Batteriekapazitaet"] = (hexdec( substr( $rc, ((hexdec( "19" ) * 4) + 6), 4 )) * 100);
    $aktuelleDaten["Batteriewarnungen"] = base_convert( substr( $rc, ((hexdec( "1C" ) * 4) + 6), 4 ), 16, 2 );
    $aktuelleDaten["Batteriefehler"] = base_convert( substr( $rc, ((hexdec( "1E" ) * 4) + 6), 8 ), 16, 2 );
    $aktuelleDaten["BatterieladeenergieGesamt"] = (hexdec( substr( $rc, ((hexdec( "20" ) * 4) + 6), 8 )) * 100);
    $aktuelleDaten["BatterieentladeenergieGesamt"] = (hexdec( substr( $rc, ((hexdec( "22" ) * 4) + 6), 8 )) * 100);
    $aktuelleDaten["Netzladeenergie"] = (hexdec( substr( $rc, ((hexdec( "24" ) * 4) + 6), 8 )) * 100);
    $aktuelleDaten["Batterieleistung"] = $funktionen->hexdecs( substr( $rc, ((hexdec( "24" ) * 4) + 6), 4 ));
    $aktuelleDaten["BatterieRemainingTime"] = (hexdec( substr( $rc, ((hexdec( "27" ) * 4) + 6), 4 )));
    $aktuelleDaten["BatterieImplementationChargeSOC"] = (hexdec( substr( $rc, ((hexdec( "28" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["BatterieImplementationDischargeSOC"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "29" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["BatterieRemainingChargeSOC"] = (hexdec( substr( $rc, ((hexdec( "2A" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["BatterieRemainingDischargeSOC"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "2B" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["BatterieMaxChargePower"] = (hexdec( substr( $rc, ((hexdec( "2C" ) * 4) + 6), 4 )));
    $aktuelleDaten["BatterieMaxDischargePower"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "2D" ) * 4) + 6), 4 )));

    /*******************/
    $Befehl["RegisterAddress"] = "0400";
    $Befehl["RegisterCount"] = "0041";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
      $i++;
      continue;
    }
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $aktuelleDaten["AC_Spannung_R"] = (hexdec( substr( $rc, ((hexdec( "0" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_Spannung_S"] = (hexdec( substr( $rc, ((hexdec( "1" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_Spannung_T"] = (hexdec( substr( $rc, ((hexdec( "2" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_Strom_R"] = (hexdec( substr( $rc, ((hexdec( "3" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_Strom_S"] = (hexdec( substr( $rc, ((hexdec( "4" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_Strom_T"] = (hexdec( substr( $rc, ((hexdec( "5" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_Leistung_R"] = hexdec( substr( $rc, ((hexdec( "6" ) * 4) + 6), 8 ));
    $aktuelleDaten["AC_Leistung_S"] = hexdec( substr( $rc, ((hexdec( "8" ) * 4) + 6), 8 ));
    $aktuelleDaten["AC_Leistung_T"] = hexdec( substr( $rc, ((hexdec( "A" ) * 4) + 6), 8 ));
    $aktuelleDaten["AC_Leistung"] = hexdec( substr( $rc, ((hexdec( "C" ) * 4) + 6), 8 ));
    $aktuelleDaten["AC_BackupSpannung_R"] = (hexdec( substr( $rc, ((hexdec( "E" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_BackupSpannung_S"] = (hexdec( substr( $rc, ((hexdec( "F" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_BackupSpannung_T"] = (hexdec( substr( $rc, ((hexdec( "10" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_BackupStrom_R"] = (hexdec( substr( $rc, ((hexdec( "11" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_BackupStrom_S"] = (hexdec( substr( $rc, ((hexdec( "12" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_BackupStrom_T"] = (hexdec( substr( $rc, ((hexdec( "13" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_BackupLeistung_R"] = hexdec( substr( $rc, ((hexdec( "14" ) * 4) + 6), 8 ));
    $aktuelleDaten["AC_BackupLeistung_S"] = hexdec( substr( $rc, ((hexdec( "16" ) * 4) + 6), 8 ));
    $aktuelleDaten["AC_BackupLeistung_T"] = hexdec( substr( $rc, ((hexdec( "18" ) * 4) + 6), 8 ));
    $aktuelleDaten["AC_BackupLeistung"] = hexdec( substr( $rc, ((hexdec( "1A" ) * 4) + 6), 8 ));
    $aktuelleDaten["BackupFrequenz"] = (hexdec( substr( $rc, ((hexdec( "1C" ) * 4) + 6), 4 )) / 100);
    $aktuelleDaten["PV_Spannung1"] = (hexdec( substr( $rc, ((hexdec( "1D" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Strom1"] = (hexdec( substr( $rc, ((hexdec( "1E" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Leistung1"] = hexdec( substr( $rc, ((hexdec( "1F" ) * 4) + 6), 8 ));
    $aktuelleDaten["PV_Spannung2"] = (hexdec( substr( $rc, ((hexdec( "21" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Strom2"] = (hexdec( substr( $rc, ((hexdec( "22" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Leistung2"] = hexdec( substr( $rc, ((hexdec( "23" ) * 4) + 6), 8 ));
    $aktuelleDaten["PV_Spannung3"] = (hexdec( substr( $rc, ((hexdec( "25" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Strom3"] = (hexdec( substr( $rc, ((hexdec( "26" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Leistung3"] = hexdec( substr( $rc, ((hexdec( "27" ) * 4) + 6), 8 ));
    $aktuelleDaten["PV_Spannung4"] = (hexdec( substr( $rc, ((hexdec( "29" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Strom4"] = (hexdec( substr( $rc, ((hexdec( "2A" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Leistung4"] = hexdec( substr( $rc, ((hexdec( "2B" ) * 4) + 6), 8 ));
    $aktuelleDaten["PV_Spannung5"] = (hexdec( substr( $rc, ((hexdec( "2D" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Strom5"] = (hexdec( substr( $rc, ((hexdec( "2E" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Leistung5"] = hexdec( substr( $rc, ((hexdec( "2F" ) * 4) + 6), 8 ));
    $aktuelleDaten["PV_Spannung6"] = (hexdec( substr( $rc, ((hexdec( "31" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Strom6"] = (hexdec( substr( $rc, ((hexdec( "32" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Leistung6"] = hexdec( substr( $rc, ((hexdec( "33" ) * 4) + 6), 8 ));
    $aktuelleDaten["Temperatur"] = (hexdec( substr( $rc, ((hexdec( "35" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["WR_Warnungen"] = base_convert( substr( $rc, ((hexdec( "36" ) * 4) + 6), 8 ), 16, 2 );
    $aktuelleDaten["WR_Fehler"] = base_convert( substr( $rc, ((hexdec( "3A" ) * 4) + 6), 8 ), 16, 2 );
    $aktuelleDaten["PV_LeistungGesamt"] = (hexdec( substr( $rc, ((hexdec( "3E" ) * 4) + 6), 8 )) * 100);
    $aktuelleDaten["WR_Arbeitsmodus"] = (hexdec( substr( $rc, ((hexdec( "40" ) * 4) + 6), 4 )));

    /*********/
    $Befehl["RegisterAddress"] = "072D";
    $Befehl["RegisterCount"] = "0002";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 8 );
    }
    $aktuelleDaten["Batteriemodus"] = hexdec( substr( $rc, 6, 4 ));
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $Befehl["RegisterAddress"] = "074B";
    $Befehl["RegisterCount"] = "0003";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
      $i++;
    }
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $aktuelleDaten["EMS-SW-Version"] = $funktionen->hexdecs( substr( $rc, ((hexdec( 0 ) * 4) + 6), 4 )).".".$funktionen->hexdecs( substr( $rc, ((hexdec( 1 ) * 4) + 6), 4 )).".".$funktionen->hexdecs( substr( $rc, ((hexdec( 2 ) * 4) + 6), 4 ));
    $funktionen->log_schreiben( "Neue Software Version:  ".$aktuelleDaten["EMS-SW-Version"], "   ", 5 );
    $Befehl["RegisterAddress"] = "0800";
    $Befehl["RegisterCount"] = "0001";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
    }
    $aktuelleDaten["Netzeinspeisung"] = $funktionen->hexdecs( substr( $rc, ((hexdec( "00" ) * 4) + 6), 4 ));
    $aktuelleDaten["Netzeinspeisung_Prozent"] = $funktionen->hexdecs( substr( $rc, ((hexdec( "00" ) * 4) + 6), 4 ));
    $funktionen->log_schreiben( $rc, "   ", 9 );
  }
  if ($aktuelleDaten["BMU-SW-Version"] > 150) {

    /**************************************************************************
    //  Alte Software Version   Handuch V. 1.7
    //
    //  BMU-SW-Version"] = 153
    //  LMU-SW-Version"] = 239
    //  ISO-SW-Version"] = 152
    //
    //
    //  Ergebniswerte:
    //  $aktuelleDaten["Firmware"]                Nummer
    //  $aktuelleDaten["Produkt"]                 Text
    //  $aktuelleDaten["Objekt"]                  Text
    //  $aktuelleDaten["AC_Wirkleistung_R"]                   [0000]
    //  $aktuelleDaten["AC_Wirkleistung_S"]                   [0002]
    //  $aktuelleDaten["AC_Wirkleistung_T"]                   [0004]
    //  $aktuelleDaten["AC_Wirkleistung"]                     [0006]
    //  $aktuelleDaten["NetzeinspeisungGesamt"]               [0008]
    //  $aktuelleDaten["NetzbezugGesamt"]                     [000A]
    //  $aktuelleDaten["PV_Wirkleistung_R"]                   [000C]
    //  $aktuelleDaten["PV_Wirkleistung_S"]                   [000E]
    //  $aktuelleDaten["PV_Wirkleistung_T"]                   [0010]
    //  $aktuelleDaten["AC_LeistungGesamt"]                   [0012]
    //  $aktuelleDaten["PV_EinspeisungGesamt"]                [0014]
    //  $aktuelleDaten["Batteriespannung"]                    [0100]
    //  $aktuelleDaten["Batteriestrom"]                       [0101]
    //  $aktuelleDaten["SOC"]                                 [0102]
    //  $aktuelleDaten["Batteriestatus"]                      [0103]
    //  $aktuelleDaten["Batterierelaisstatus"]                [0104]
    //  $aktuelleDaten["Batterieanzahl"]                      [0118]
    //  $aktuelleDaten["Batteriekapazitaet"]                  [0119]
    //  $aktuelleDaten["Batteriewarnungen"]                   [011C]
    //  $aktuelleDaten["Batteriefehler"]                      [011E]
    //  $aktuelleDaten["BatterieladeenergieGesamt"]           [0120]
    //  $aktuelleDaten["BatterieentladeenergieGesamt"]        [0122]
    //  $aktuelleDaten["Netzladeenergie"]                     [0124]
    //  $aktuelleDaten["Batterieleistung"]                    [0126]
    //  $aktuelleDaten["BatterieRemainingTime"]               [0127]
    //  $aktuelleDaten["BatterieImplementationChargeSOC"]     [0128]
    //  $aktuelleDaten["BatterieImplementationDischargeSOC"]  [0129]
    //  $aktuelleDaten["BatterieRemainingChargeSOC"]          [012A]
    //  $aktuelleDaten["BatterieRemainingDischargeSOC"]       [012B]
    //  $aktuelleDaten["AC_Spannung_R"]                       [0400]
    //  $aktuelleDaten["AC_Spannung_S"]                       [0401]
    //  $aktuelleDaten["AC_Spannung_T"]                       [0402]
    //  $aktuelleDaten["AC_Strom_R"]                          [0403]
    //  $aktuelleDaten["AC_Strom_S"]                          [0404]
    //  $aktuelleDaten["AC_Strom_T"]                          [0405]
    //  $aktuelleDaten["AC_Leistung_R"]                       [0406]
    //  $aktuelleDaten["AC_Leistung_S"]                       [0408]
    //  $aktuelleDaten["AC_Leistung_T"]                       [040A]
    //  $aktuelleDaten["AC_Leistung"]                         [040C]
    //  $aktuelleDaten["AC_BackupSpannung_R"]                 [040E]
    //  $aktuelleDaten["AC_BackupSpannung_S"]                 [040F]
    //  $aktuelleDaten["AC_BackupSpannung_T"]                 [0410]
    //  $aktuelleDaten["AC_BackupStrom_R"]                    [0411]
    //  $aktuelleDaten["AC_BackupStrom_S"]                    [0412]
    //  $aktuelleDaten["AC_BackupStrom_T"]                    [0413]
    //  $aktuelleDaten["AC_BackupLeistung_R"]                 [0414]
    //  $aktuelleDaten["AC_BackupLeistung_S"]                 [0416]
    //  $aktuelleDaten["AC_BackupLeistung_T"]                 [0418]
    //  $aktuelleDaten["AC_BackupLeistung"]                   [041A]
    //  $aktuelleDaten["BackupFrequenz"]                      [041C]
    //  $aktuelleDaten["PV_Spannung1"]                        [041D]
    //  $aktuelleDaten["PV_Strom1"]                           [041E]
    //  $aktuelleDaten["PV_Leistung1"]                        [041F]
    //  $aktuelleDaten["PV_Spannung2"]                        [0421]
    //  $aktuelleDaten["PV_Strom2"]                           [0422]
    //  $aktuelleDaten["PV_Leistung2"]                        [0423]
    //  $aktuelleDaten["PV_Spannung3"]                        [0425]
    //  $aktuelleDaten["PV_Strom3"]                           [0424]
    //  $aktuelleDaten["PV_Leistung3"]                        [0427]
    //  $aktuelleDaten["Temperatur"]                          [0429]
    //  $aktuelleDaten["WR_Warnungen"]                        [042A]
    //  $aktuelleDaten["WR_Fehler"]                           [042C]
    //  $aktuelleDaten["PV_LeistungGesamt"]                   [042E]
    //  $aktuelleDaten["Netzeinspeisung"]                     [0700]
    //  $aktuelleDaten["Batteriemodus"]                       [072D]
    //
    //
    ****************************************************************************/
    $aktuelleDaten["PV_Leistung4"] = 0;
    $aktuelleDaten["PV_Leistung5"] = 0;
    $aktuelleDaten["PV_Leistung6"] = 0;
    $Befehl["RegisterAddress"] = "0000";
    $Befehl["RegisterCount"] = "0016";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
      $i++;
      continue;
    }
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $aktuelleDaten["AC_Wirkleistung_R"] = $funktionen->hexdecs( substr( $rc, 6, 8 ));
    $aktuelleDaten["AC_Wirkleistung_S"] = $funktionen->hexdecs( substr( $rc, 14, 8 ));
    $aktuelleDaten["AC_Wirkleistung_T"] = $funktionen->hexdecs( substr( $rc, 22, 8 ));
    $aktuelleDaten["AC_Wirkleistung"] = $funktionen->hexdecs( substr( $rc, 30, 8 ));
    $aktuelleDaten["NetzeinspeisungGesamt"] = ($funktionen->hexdecs( substr( $rc, 38, 8 )) * 10);
    $aktuelleDaten["NetzbezugGesamt"] = ($funktionen->hexdecs( substr( $rc, 46, 8 )) * 10);
    $aktuelleDaten["PV_Wirkleistung_R"] = $funktionen->hexdecs( substr( $rc, 54, 8 ));
    $aktuelleDaten["PV_Wirkleistung_S"] = $funktionen->hexdecs( substr( $rc, 62, 8 ));
    $aktuelleDaten["PV_Wirkleistung_T"] = $funktionen->hexdecs( substr( $rc, 70, 8 ));
    $aktuelleDaten["AC_LeistungGesamt"] = $funktionen->hexdecs( substr( $rc, 78, 8 ));
    $aktuelleDaten["PV_EinspeisungGesamt"] = ($funktionen->hexdecs( substr( $rc, 86, 8 )) * 10);

    /***********/
    $Befehl["RegisterAddress"] = "0700";
    $Befehl["RegisterCount"] = "0002";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
    }
    $aktuelleDaten["Netzeinspeisung"] = hexdec( substr( $rc, 6, 4 ));
    $funktionen->log_schreiben( $rc, "   ", 9 );

    /***********/
    $Befehl["RegisterAddress"] = "072D";
    $Befehl["RegisterCount"] = "0002";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 8 );
    }
    $aktuelleDaten["Batteriemodus"] = hexdec( substr( $rc, 6, 4 ));
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $Befehl["RegisterAddress"] = "0100";
    $Befehl["RegisterCount"] = "0005";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
      $i++;
      continue;
    }
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $aktuelleDaten["Batteriespannung"] = (hexdec( substr( $rc, 6, 4 )) / 10);
    $aktuelleDaten["Batteriestrom"] = ($funktionen->hexdecs( substr( $rc, 10, 4 )) / 10);
    $aktuelleDaten["SOC"] = (hexdec( substr( $rc, 14, 4 )) / 10);
    $aktuelleDaten["Batteriestatus"] = (substr( $rc, 18, 4 ));
    $aktuelleDaten["Batterierelaisstatus"] = (substr( $rc, 22, 4 ));

    /***********/
    $Befehl["RegisterAddress"] = "0118";
    $Befehl["RegisterCount"] = "001A";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 8 );
      $funktionen->log_schreiben( "Dieses Gerät liefert nicht alle Werte.", "   ", 8 );
      $i++;
      $Befehl["RegisterAddress"] = "0118";
      $Befehl["RegisterCount"] = "000E";
      $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
      $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
      if ($rc == false) {
        $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
        $i++;
        continue;
      }
    }
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $aktuelleDaten["Batterieanzahl"] = hexdec( substr( $rc, 6, 4 ));
    $aktuelleDaten["Batteriekapazitaet"] = (hexdec( substr( $rc, 10, 4 )) * 100);
    $aktuelleDaten["Batteriewarnungen"] = base_convert( substr( $rc, 22, 8 ), 16, 2 );
    $aktuelleDaten["Batteriefehler"] = base_convert( substr( $rc, 30, 8 ), 16, 2 );
    $aktuelleDaten["BatterieladeenergieGesamt"] = (hexdec( substr( $rc, 38, 8 )) * 100);
    $aktuelleDaten["BatterieentladeenergieGesamt"] = (hexdec( substr( $rc, 46, 8 )) * 100);
    $aktuelleDaten["Netzladeenergie"] = (hexdec( substr( $rc, 54, 8 )) * 100);
    $aktuelleDaten["Batterieleistung"] = $funktionen->hexdecs( substr( $rc, 62, 4 ));
    $aktuelleDaten["BatterieRemainingTime"] = (hexdec( substr( $rc, 66, 4 )));
    $aktuelleDaten["BatterieImplementationChargeSOC"] = hexdec( substr( $rc, 70, 4 ));
    $aktuelleDaten["BatterieImplementationDischargeSOC"] = hexdec( substr( $rc, 74, 4 ));
    $aktuelleDaten["BatterieRemainingChargeSOC"] = (hexdec( substr( $rc, 78, 4 )));
    $aktuelleDaten["BatterieRemainingDischargeSOC"] = (hexdec( substr( $rc, 82, 4 )));

    /***********/
    $Befehl["RegisterAddress"] = "0400";
    $Befehl["RegisterCount"] = "0030";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
      $i++;
      continue;
    }
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $aktuelleDaten["AC_Spannung_R"] = (hexdec( substr( $rc, 6, 4 )) / 10);
    $aktuelleDaten["AC_Spannung_S"] = (hexdec( substr( $rc, 10, 4 )) / 10);
    $aktuelleDaten["AC_Spannung_T"] = (hexdec( substr( $rc, 14, 4 )) / 10);
    $aktuelleDaten["AC_Strom_R"] = (hexdec( substr( $rc, 18, 4 )) / 10);
    $aktuelleDaten["AC_Strom_S"] = (hexdec( substr( $rc, 22, 4 )) / 10);
    $aktuelleDaten["AC_Strom_T"] = (hexdec( substr( $rc, 26, 4 )) / 10);
    $aktuelleDaten["AC_Leistung_R"] = hexdec( substr( $rc, 30, 8 ));
    $aktuelleDaten["AC_Leistung_S"] = hexdec( substr( $rc, 38, 8 ));
    $aktuelleDaten["AC_Leistung_T"] = hexdec( substr( $rc, 46, 8 ));
    $aktuelleDaten["AC_Leistung"] = hexdec( substr( $rc, 54, 8 ));
    $aktuelleDaten["AC_BackupSpannung_R"] = (hexdec( substr( $rc, 62, 4 )) / 10);
    $aktuelleDaten["AC_BackupSpannung_S"] = (hexdec( substr( $rc, 66, 4 )) / 10);
    $aktuelleDaten["AC_BackupSpannung_T"] = (hexdec( substr( $rc, 70, 4 )) / 10);
    $aktuelleDaten["AC_BackupStrom_R"] = (hexdec( substr( $rc, 74, 4 )) / 10);
    $aktuelleDaten["AC_BackupStrom_S"] = (hexdec( substr( $rc, 78, 4 )) / 10);
    $aktuelleDaten["AC_BackupStrom_T"] = (hexdec( substr( $rc, 82, 4 )) / 10);
    $aktuelleDaten["AC_BackupLeistung_R"] = hexdec( substr( $rc, 86, 8 ));
    $aktuelleDaten["AC_BackupLeistung_S"] = hexdec( substr( $rc, 94, 8 ));
    $aktuelleDaten["AC_BackupLeistung_T"] = hexdec( substr( $rc, 102, 8 ));
    $aktuelleDaten["AC_BackupLeistung"] = hexdec( substr( $rc, 110, 8 ));
    $aktuelleDaten["BackupFrequenz"] = (hexdec( substr( $rc, 118, 4 )) / 100);
    $aktuelleDaten["PV_Spannung1"] = (hexdec( substr( $rc, 122, 4 )) / 10);
    $aktuelleDaten["PV_Strom1"] = (hexdec( substr( $rc, 126, 4 )) / 10);
    $aktuelleDaten["PV_Leistung1"] = hexdec( substr( $rc, 130, 8 ));
    $aktuelleDaten["PV_Spannung2"] = (hexdec( substr( $rc, 138, 4 )) / 10);
    $aktuelleDaten["PV_Strom2"] = (hexdec( substr( $rc, 142, 4 )) / 10);
    $aktuelleDaten["PV_Leistung2"] = hexdec( substr( $rc, 146, 8 ));
    $aktuelleDaten["PV_Spannung3"] = (hexdec( substr( $rc, 154, 4 )) / 10);
    $aktuelleDaten["PV_Strom3"] = (hexdec( substr( $rc, 158, 4 )) / 10);
    $aktuelleDaten["PV_Leistung3"] = hexdec( substr( $rc, 162, 8 ));
    $aktuelleDaten["Temperatur"] = (hexdec( substr( $rc, 170, 4 )) / 10);
    $aktuelleDaten["WR_Warnungen"] = base_convert( substr( $rc, 174, 8 ), 16, 2 );
    $aktuelleDaten["WR_Fehler"] = base_convert( substr( $rc, 182, 8 ), 16, 2 );
    $aktuelleDaten["PV_LeistungGesamt"] = (hexdec( substr( $rc, 190, 8 )) * 100);
  }
  elseif ($aktuelleDaten["LMU-SW-Version"] > 185) {

    /**************************************************************************
    //  SMILE 5 Geräte
    //
    **************************************************************************/
    $Befehl["RegisterAddress"] = "0000";
    $Befehl["RegisterCount"] = "0036";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
      $i++;
      continue;
    }
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $aktuelleDaten["NetzeinspeisungGesamt"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( 10 ) * 4) + 6), 8 )) / 100);
    $aktuelleDaten["NetzbezugGesamt"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( 12 ) * 4) + 6), 8 )) / 100);
    $aktuelleDaten["Netz_Spannung_R"] = $funktionen->hexdecs( substr( $rc, ((hexdec( 14 ) * 4) + 6), 4 )) / 10;
    $aktuelleDaten["Netz_Spannung_S"] = $funktionen->hexdecs( substr( $rc, ((hexdec( 15 ) * 4) + 6), 4 )) / 10;
    $aktuelleDaten["Netz_Spannung_T"] = $funktionen->hexdecs( substr( $rc, ((hexdec( 16 ) * 4) + 6), 4 )) / 10;
    $aktuelleDaten["Netz_Strom_R"] = $funktionen->hexdecs( substr( $rc, ((hexdec( 17 ) * 4) + 6), 4 )) / 100;
    $aktuelleDaten["Netz_Strom_S"] = $funktionen->hexdecs( substr( $rc, ((hexdec( 18 ) * 4) + 6), 4 )) / 100;
    $aktuelleDaten["Netz_Strom_T"] = $funktionen->hexdecs( substr( $rc, ((hexdec( 19 ) * 4) + 6), 4 )) / 100;
    $aktuelleDaten["Netz_Frequenz"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "1A" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["Netz_Wirkleistung_R"] = $funktionen->hexdecs( substr( $rc, ((hexdec( "1B" ) * 4) + 6), 8 ));
    $aktuelleDaten["Netz_Wirkleistung_S"] = $funktionen->hexdecs( substr( $rc, ((hexdec( "1D" ) * 4) + 6), 8 ));
    $aktuelleDaten["Netz_Wirkleistung_T"] = $funktionen->hexdecs( substr( $rc, ((hexdec( "1F" ) * 4) + 6), 8 ));
    $aktuelleDaten["Netz_WirkleistungGesamt"] = $funktionen->hexdecs( substr( $rc, ((hexdec( "21" ) * 4) + 6), 8 ));
    // Aus Kompatibilitätsgründen zur Zeit noch nötig
    $aktuelleDaten["AC_Wirkleistung_R"] = $aktuelleDaten["Netz_Wirkleistung_R"];
    $aktuelleDaten["AC_Wirkleistung_S"] = $aktuelleDaten["Netz_Wirkleistung_S"];
    $aktuelleDaten["AC_Wirkleistung_T"] = $aktuelleDaten["Netz_Wirkleistung_T"];
    $aktuelleDaten["AC_Wirkleistung"] = $aktuelleDaten["Netz_WirkleistungGesamt"];

    /************************/
    $Befehl["RegisterAddress"] = "0080";
    $Befehl["RegisterCount"] = "0026";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
      $i++;
      continue;
    }
    $funktionen->log_schreiben( $rc, "   ", 9 );
    //  Offset = 80    Speicherstelle 80 = 00 ,  90 = 10
    $aktuelleDaten["PV_EinspeisungGesamt"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "10" ) * 4) + 6), 8 )) / 100);
    $aktuelleDaten["PV_BezugGesamt"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "12" ) * 4) + 6), 8 )) / 100);
    $aktuelleDaten["PV_Wirkleistung_R"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "1B" ) * 4) + 6), 8 )));
    $aktuelleDaten["PV_Wirkleistung_S"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "1D" ) * 4) + 6), 8 )));
    $aktuelleDaten["PV_Wirkleistung_T"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "1F" ) * 4) + 6), 8 )));
    $aktuelleDaten["PV_LeistungGesamt"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "21" ) * 4) + 6), 8 )));
    $aktuelleDaten["AC_LeistungGesamt"] = $aktuelleDaten["PV_LeistungGesamt"];

    /*********/
    $Befehl["RegisterAddress"] = "0100";
    $Befehl["RegisterCount"] = "0048";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
      $i++;
      continue;
    }
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $aktuelleDaten["Batteriespannung"] = (hexdec( substr( $rc, ((hexdec( "0" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["Batteriestrom"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "1" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["SOC"] = (hexdec( substr( $rc, ((hexdec( "2" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["Batteriestatus"] = (substr( $rc, ((hexdec( "3" ) * 4) + 6), 4 ));
    $aktuelleDaten["Batterierelaisstatus"] = (substr( $rc, ((hexdec( "4" ) * 4) + 6), 4 ));
    $aktuelleDaten["Batterieanzahl"] = hexdec( substr( $rc, ((hexdec( "18" ) * 4) + 6), 4 ));
    $aktuelleDaten["Batteriekapazitaet"] = (hexdec( substr( $rc, ((hexdec( "19" ) * 4) + 6), 4 )) * 100);
    $aktuelleDaten["Batteriewarnungen"] = base_convert( substr( $rc, ((hexdec( "1C" ) * 4) + 6), 4 ), 16, 2 );
    $aktuelleDaten["Batteriefehler"] = base_convert( substr( $rc, ((hexdec( "1E" ) * 4) + 6), 8 ), 16, 2 );
    $aktuelleDaten["BatterieladeenergieGesamt"] = (hexdec( substr( $rc, ((hexdec( "20" ) * 4) + 6), 8 )) * 100);
    $aktuelleDaten["BatterieentladeenergieGesamt"] = (hexdec( substr( $rc, ((hexdec( "22" ) * 4) + 6), 8 )) * 100);
    $aktuelleDaten["Netzladeenergie"] = (hexdec( substr( $rc, ((hexdec( "24" ) * 4) + 6), 8 )) * 100);
    $aktuelleDaten["Batterieleistung"] = $funktionen->hexdecs( substr( $rc, ((hexdec( "24" ) * 4) + 6), 4 ));
    $aktuelleDaten["BatterieRemainingTime"] = (hexdec( substr( $rc, ((hexdec( "27" ) * 4) + 6), 4 )));
    $aktuelleDaten["BatterieImplementationChargeSOC"] = (hexdec( substr( $rc, ((hexdec( "28" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["BatterieImplementationDischargeSOC"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "29" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["BatterieRemainingChargeSOC"] = (hexdec( substr( $rc, ((hexdec( "2A" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["BatterieRemainingDischargeSOC"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "2B" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["BatterieMaxChargePower"] = (hexdec( substr( $rc, ((hexdec( "2C" ) * 4) + 6), 4 )));
    $aktuelleDaten["BatterieMaxDischargePower"] = ($funktionen->hexdecs( substr( $rc, ((hexdec( "2D" ) * 4) + 6), 4 )));

    /*******************/
    $Befehl["RegisterAddress"] = "0400";
    $Befehl["RegisterCount"] = "0041";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
      $i++;
      continue;
    }
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $aktuelleDaten["AC_Spannung_R"] = (hexdec( substr( $rc, ((hexdec( "0" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_Spannung_S"] = (hexdec( substr( $rc, ((hexdec( "1" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_Spannung_T"] = (hexdec( substr( $rc, ((hexdec( "2" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_Strom_R"] = (hexdec( substr( $rc, ((hexdec( "3" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_Strom_S"] = (hexdec( substr( $rc, ((hexdec( "4" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_Strom_T"] = (hexdec( substr( $rc, ((hexdec( "5" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_Leistung_R"] = hexdec( substr( $rc, ((hexdec( "6" ) * 4) + 6), 8 ));
    $aktuelleDaten["AC_Leistung_S"] = hexdec( substr( $rc, ((hexdec( "8" ) * 4) + 6), 8 ));
    $aktuelleDaten["AC_Leistung_T"] = hexdec( substr( $rc, ((hexdec( "A" ) * 4) + 6), 8 ));
    $aktuelleDaten["AC_Leistung"] = hexdec( substr( $rc, ((hexdec( "C" ) * 4) + 6), 8 ));
    $aktuelleDaten["AC_BackupSpannung_R"] = (hexdec( substr( $rc, ((hexdec( "E" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_BackupSpannung_S"] = (hexdec( substr( $rc, ((hexdec( "F" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_BackupSpannung_T"] = (hexdec( substr( $rc, ((hexdec( "10" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_BackupStrom_R"] = (hexdec( substr( $rc, ((hexdec( "11" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_BackupStrom_S"] = (hexdec( substr( $rc, ((hexdec( "12" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_BackupStrom_T"] = (hexdec( substr( $rc, ((hexdec( "13" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["AC_BackupLeistung_R"] = hexdec( substr( $rc, ((hexdec( "14" ) * 4) + 6), 8 ));
    $aktuelleDaten["AC_BackupLeistung_S"] = hexdec( substr( $rc, ((hexdec( "16" ) * 4) + 6), 8 ));
    $aktuelleDaten["AC_BackupLeistung_T"] = hexdec( substr( $rc, ((hexdec( "18" ) * 4) + 6), 8 ));
    $aktuelleDaten["AC_BackupLeistung"] = hexdec( substr( $rc, ((hexdec( "1A" ) * 4) + 6), 8 ));
    $aktuelleDaten["BackupFrequenz"] = (hexdec( substr( $rc, ((hexdec( "1C" ) * 4) + 6), 4 )) / 100);
    $aktuelleDaten["PV_Spannung1"] = (hexdec( substr( $rc, ((hexdec( "1D" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Strom1"] = (hexdec( substr( $rc, ((hexdec( "1E" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Leistung1"] = hexdec( substr( $rc, ((hexdec( "1F" ) * 4) + 6), 8 ));
    $aktuelleDaten["PV_Spannung2"] = (hexdec( substr( $rc, ((hexdec( "21" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Strom2"] = (hexdec( substr( $rc, ((hexdec( "22" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Leistung2"] = hexdec( substr( $rc, ((hexdec( "23" ) * 4) + 6), 8 ));
    $aktuelleDaten["PV_Spannung3"] = (hexdec( substr( $rc, ((hexdec( "25" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Strom3"] = (hexdec( substr( $rc, ((hexdec( "26" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Leistung3"] = hexdec( substr( $rc, ((hexdec( "27" ) * 4) + 6), 8 ));
    $aktuelleDaten["PV_Spannung4"] = (hexdec( substr( $rc, ((hexdec( "29" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Strom4"] = (hexdec( substr( $rc, ((hexdec( "2A" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Leistung4"] = hexdec( substr( $rc, ((hexdec( "2B" ) * 4) + 6), 8 ));
    $aktuelleDaten["PV_Spannung5"] = (hexdec( substr( $rc, ((hexdec( "2D" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Strom5"] = (hexdec( substr( $rc, ((hexdec( "2E" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Leistung5"] = hexdec( substr( $rc, ((hexdec( "2F" ) * 4) + 6), 8 ));
    $aktuelleDaten["PV_Spannung6"] = (hexdec( substr( $rc, ((hexdec( "31" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Strom6"] = (hexdec( substr( $rc, ((hexdec( "32" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["PV_Leistung6"] = hexdec( substr( $rc, ((hexdec( "33" ) * 4) + 6), 8 ));
    $aktuelleDaten["Temperatur"] = (hexdec( substr( $rc, ((hexdec( "35" ) * 4) + 6), 4 )) / 10);
    $aktuelleDaten["WR_Warnungen"] = base_convert( substr( $rc, ((hexdec( "36" ) * 4) + 6), 8 ), 16, 2 );
    $aktuelleDaten["WR_Fehler"] = base_convert( substr( $rc, ((hexdec( "3A" ) * 4) + 6), 8 ), 16, 2 );
    $aktuelleDaten["PV_LeistungGesamt"] = (hexdec( substr( $rc, ((hexdec( "3E" ) * 4) + 6), 8 )) * 100);
    $aktuelleDaten["WR_Arbeitsmodus"] = (hexdec( substr( $rc, ((hexdec( "40" ) * 4) + 6), 4 )));

    /*********/
    $Befehl["RegisterAddress"] = "072D";
    $Befehl["RegisterCount"] = "0002";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 8 );
    }
    $aktuelleDaten["Batteriemodus"] = hexdec( substr( $rc, 6, 4 ));
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $Befehl["RegisterAddress"] = "074B";
    $Befehl["RegisterCount"] = "0003";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
      $i++;
    }
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $aktuelleDaten["EMS-SW-Version"] = $funktionen->hexdecs( substr( $rc, ((hexdec( 0 ) * 4) + 6), 4 )).".".$funktionen->hexdecs( substr( $rc, ((hexdec( 1 ) * 4) + 6), 4 )).".".$funktionen->hexdecs( substr( $rc, ((hexdec( 2 ) * 4) + 6), 4 ));
    $aktuelleDaten["Firmware"] = $aktuelleDaten["EMS-SW-Version"];
    $funktionen->log_schreiben( "Software Version:  ".$aktuelleDaten["EMS-SW-Version"], "   ", 5 );
    $Befehl["RegisterAddress"] = "0800";
    $Befehl["RegisterCount"] = "0001";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
    }
    $aktuelleDaten["Netzeinspeisung"] = $funktionen->hexdecs( substr( $rc, ((hexdec( "00" ) * 4) + 6), 4 ));
    $aktuelleDaten["Netzeinspeisung_Prozent"] = $funktionen->hexdecs( substr( $rc, ((hexdec( "00" ) * 4) + 6), 4 ));
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $Befehl["RegisterAddress"] = "0743";
    $Befehl["RegisterCount"] = "0008";
    $CRC = bin2hex( $funktionen->crc16( hex2bin( $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"] )));
    $rc = $funktionen->alpha_auslesen( $USB1, $Befehl["DeviceID"].$Befehl["BefehlFunctionCode"].$Befehl["RegisterAddress"].$Befehl["RegisterCount"].$CRC );
    if ($rc == false) {
      $funktionen->log_schreiben( "Datenfehler! Adresse: ".$Befehl["RegisterAddress"], "   ", 7 );
      $i++;
      continue;
    }
    $funktionen->log_schreiben( $rc, "   ", 9 );
    $aktuelleDaten["Seriennummer"] = $funktionen->Hex2String( substr( $rc, 6, 30 ));
    $aktuelleDaten["Produkt"] = "SMILE5";
    $funktionen->log_schreiben( "Produkt:  ".$aktuelleDaten["Produkt"], "   ", 5 );
  }
  else {
    $funktionen->log_schreiben( "Softwarestand unbekannt. Gerät kann nicht ausgelesen werden!", "   ", 2 );
    goto Ausgang;

    /**********/
  }

  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/
  $aktuelleDaten["PV_Leistung"] = ($aktuelleDaten["PV_Leistung1"] + $aktuelleDaten["PV_Leistung2"] + $aktuelleDaten["PV_Leistung3"] + $aktuelleDaten["PV_Leistung4"] + $aktuelleDaten["PV_Leistung5"] + $aktuelleDaten["PV_Leistung6"]);
  $funktionen->log_schreiben( print_r( $aktuelleDaten, 1 ), "   ", 8 );

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
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  $aktuelleDaten["AC_Frequenz"] = $aktuelleDaten["BackupFrequenz"];
  if (isset($Alpha_ESS)) {
    $aktuelleDaten["Alpha_ESS"] = $Alpha_ESS;
  }

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/alpha_ess_math.php" )) {
    include 'alpha_ess_math.php'; // Falls etwas neu berechnet werden muss.
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
  elseif ($InfluxDB_local) {
    $rc = $funktionen->influx_local( $aktuelleDaten );
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben( "Schleife ".$i." Ausgang...", "   ", 8 );
    break;
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
  $i++;
} while (($Start + 54) > time( ));
if (isset($aktuelleDaten["Temperatur"]) and isset($aktuelleDaten["Regler"])) {

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
//  Der Aufwand wird betrieben, da der Wechselrichter mit sehr wenig Licht
//  tagsüber sich ausschaltet und der Zähler sich zurück setzt.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//  Leistung in Watt / 60 Minuten, da 60 mal in der Stunde addiert wird.
*****************************************************************************/
if (file_exists( $StatusFile )) {

  /***************************************************************************
  //  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
  //  pro Tag zu speichern.
  //  Jede Nacht 0 Uhr Tageszähler auf 0 setzen
  ***************************************************************************/
  if (date( "H:i" ) == "00:00" or date( "H:i" ) == "00:01") {
    $rc = file_put_contents( $StatusFile, "0" );
    $funktionen->log_schreiben( "WattstundenGesamtHeute  gesetzt.", "o- ", 5 );
  }

  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $whProTag = file_get_contents( $StatusFile );
  $whProTag = ($whProTag + ($aktuelleDaten["PV_Leistung"]) / 60);
  $rc = file_put_contents( $StatusFile, round( $whProTag, 2 ));
  $funktionen->log_schreiben( "WattstundenGesamtHeute: ".round( $whProTag, 2 ), "   ", 5 );
}
Ausgang:$funktionen->log_schreiben( "----------------------   Stop   alpha_ess.php   --------------------- ", "|--", 6 );
return;
?>