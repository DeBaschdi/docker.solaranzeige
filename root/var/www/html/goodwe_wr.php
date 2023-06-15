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
//  Es dient dem Auslesen des Wechselrichters Growatt über eine RS485
//  Schnittstelle mit USB Adapter. Protokoll Version 1  V3.5
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
$path_parts = pathinfo($argv[0]);
$Pfad = $path_parts['dirname'];
if (!is_file($Pfad."/1.user.config.php")) {
  // Handelt es sich um ein Multi Regler System?
  require ($Pfad."/user.config.php");
}
require_once ($Pfad."/phpinc/funktionen.inc.php");
if (!isset($funktionen)) {
  $funktionen = new funktionen();
}
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Start = time(); // Timestamp festhalten
$funktionen->log_schreiben("----------------------   Start  goodwe_wr.php   --------------------- ", "|--", 6);
$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
$aktuelleDaten["KeineSonne"] = false;
setlocale(LC_TIME, "de_DE.utf8");
//  Hardware Version ermitteln.
//  $Platine = "Raspberry Pi Model B Plus Rev 1.2";
$Teile = explode(" ", $Platine);
if ($Teile[1] == "Pi") {
  $funktionen->log_schreiben("Hardware Version: ".$Platine, "o  ", 8);
  $Version = trim($Teile[2]);
  if ($Teile[3] == "Model") {
    $Version .= trim($Teile[4]);
    if ($Teile[5] == "Plus") {
      $Version .= trim($Teile[5]);
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
elseif (strlen($WR_Adresse) == 1) {
  $WR_ID = str_pad(dechex($WR_Adresse), 2, "0", STR_PAD_LEFT);
}
elseif (strlen($WR_Adresse) == 2) {
  $WR_ID = str_pad(dechex(substr($WR_Adresse, - 2)), 2, "0", STR_PAD_LEFT);
}
else {
  $WR_ID = dechex($WR_Adresse);
}
$funktionen->log_schreiben("WR_ID: ".$WR_ID, "+  ", 8);
$USB1 = $funktionen->openUSB($USBRegler);
if (!is_resource($USB1)) {
  $funktionen->log_schreiben("USB Port kann nicht geöffnet werden. [1]", "XX ", 7);
  $funktionen->log_schreiben("Exit.... ", "XX ", 7);
  goto Ausgang;
}
$i = 1;
do {
  $funktionen->log_schreiben("Die Daten werden ausgelesen...", "+  ", 9);

  /****************************************************************************
  //  Ab hier wird der Regler ausgelesen.
  //
  //  This is a map document of standard MODBUS RTU protocol for only
  //  GoodWe energy storage inverters compatible with LV battery –
  //  ES, EM, SBP series. Inverter Address: Can be assigned from 1-247.
  //  247 is factory default assignment.
  //  Communication baud rate: The default baud rate is 9600 bps,
  //  which is adjustable up
  //
  ****************************************************************************/
  $Befehl["DeviceID"] = "F7";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterAddress"] = "0200";
  $Befehl["RegisterCount"] = "0008";
  $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
  $aktuelleDaten["Seriennummer"] = $funktionen->hex2string($rc["data"]);
  if ($rc["ok"] == 0) {
    $funktionen->log_schreiben("Der Wechselrichter sendet kleine Daten.", "   ", 5);
    goto Ausgang;
  }
  $funktionen->log_schreiben("DeviceID: (HEX) ".$WR_ID, "   ", 5);
  $Befehl["RegisterAddress"] = "0210";
  $Befehl["RegisterCount"] = "0005";
  $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
  $aktuelleDaten["Modell"] = $funktionen->hex2string($rc["data"]);
  $funktionen->log_schreiben("Modell: ".$aktuelleDaten["Modell"], "   ", 5);
  if (strtoupper(substr($aktuelleDaten["Modell"], - 2)) == "XS") {
    $Befehl["RegisterAddress"] = "0340";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV_Mode"] = hexdec($rc["data"]) + 0;
    if ($rc["data"] == "0000") {
      // Es kommt zu wenig Leistung von den Modulen
      $aktuelleDaten["KeineSonne"] == true;
    }
    $Befehl["RegisterAddress"] = "0316";
    $Befehl["RegisterCount"] = "0003";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Firmware"] = $rc["data"];
    $Befehl["RegisterAddress"] = "0222";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["WattstundenGesamt"] = hexdec($rc["data"]) * 100;
    $Befehl["RegisterAddress"] = "0224";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Gesamtertragszeit"] = hexdec($rc["data"]);
    $Befehl["RegisterAddress"] = "0226";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["MPPT1_Spannung"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0228";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["MPPT1_Strom"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0227";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["MPPT2_Spannung"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0229";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["MPPT2_Strom"] = hexdec($rc["data"]) / 10;
    $aktuelleDaten["MPPT1_Leistung"] = ($aktuelleDaten["MPPT1_Spannung"] * $aktuelleDaten["MPPT1_Strom"]);
    $aktuelleDaten["MPPT2_Leistung"] = ($aktuelleDaten["MPPT2_Spannung"] * $aktuelleDaten["MPPT2_Strom"]);
    $aktuelleDaten["PV_Leistung"] = ($aktuelleDaten["MPPT1_Leistung"] + $aktuelleDaten["MPPT2_Leistung"]);
    $Befehl["RegisterAddress"] = "0300";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV1_Spannung"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0302";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV1_Strom"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "030E";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["WR_Mode"] = hexdec($rc["data"]) + 0;
    $Befehl["RegisterAddress"] = "0301";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV2_Spannung"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0303";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV2_Strom"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0357";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV3_Spannung"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0359";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV3_Strom"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0358";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV4_Spannung"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "035A";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV4_Strom"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "030F";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["WR_Temperatur"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "022A";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    if ($rc["data"] == "ffff") {
      $rc["data"] = 0;
    }
    $aktuelleDaten["AC_Spannung_R"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "022D";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    if ($rc["data"] == "ffff") {
      $rc["data"] = 0;
    }
    $aktuelleDaten["AC_Strom_R"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0230";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    if ($rc["data"] == "ffff") {
      $rc["data"] = 0;
    }
    $aktuelleDaten["Netzfrequenz_R"] = hexdec($rc["data"]) / 100;
    $Befehl["RegisterAddress"] = "022B";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    if ($rc["data"] == "ffff") {
      $rc["data"] = 0;
    }
    $aktuelleDaten["AC_Spannung_S"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "022E";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    if ($rc["data"] == "ffff") {
      $rc["data"] = 0;
    }
    $aktuelleDaten["AC_Strom_S"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0231";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    if ($rc["data"] == "ffff") {
      $rc["data"] = 0;
    }
    $aktuelleDaten["Netzfrequenz_S"] = hexdec($rc["data"]) / 100;
    $Befehl["RegisterAddress"] = "022C";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    if ($rc["data"] == "ffff") {
      $rc["data"] = 0;
    }
    $aktuelleDaten["AC_Spannung_T"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "022F";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    if ($rc["data"] == "ffff") {
      $rc["data"] = 0;
    }
    $aktuelleDaten["AC_Strom_T"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0233";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    if ($rc["data"] == "ffff") {
      $rc["data"] = 0;
    }
    $aktuelleDaten["Netzfrequenz_T"] = hexdec($rc["data"]) / 100;
    $Befehl["RegisterAddress"] = "0236";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["WattstundenGesamtHeute"] = hexdec($rc["data"]) * 100;
    $Befehl["RegisterAddress"] = "0320";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["EinspeisungHeute"] = hexdec($rc["data"]) * 100;
    $Befehl["RegisterAddress"] = "0312";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $Einspeisung_total_H = $rc["data"];
    $Befehl["RegisterAddress"] = "0313";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Einspeisung_total"] = (hexdec($Einspeisung_total_H.$rc["data"])) * 100;
    $Befehl["RegisterAddress"] = "0352";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $Einspeisung_H = $rc["data"];
    $Befehl["RegisterAddress"] = "0353";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Einspeisung"] = hexdec($Einspeisung_H.$rc["data"]);
    $Befehl["RegisterAddress"] = "0310";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $Error_StatusH = $rc["data"];
    $Befehl["RegisterAddress"] = "0311";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Error_Status"] = hexdec($Error_StatusH.$rc["data"]) + 0;
  }
  else {
    $Befehl["RegisterAddress"] = "0208";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["VPV"] = $funktionen->hex2string($rc["data"]);
    $Befehl["RegisterAddress"] = "020A";
    $Befehl["RegisterCount"] = "0003";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Firmware"] = trim($funktionen->hex2string($rc["data"]));
    $Befehl["RegisterAddress"] = "0500";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV1_Spannung"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0501";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV1_Strom"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0502";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV1_Mode"] = hexdec($rc["data"]) + 0;
    $Befehl["RegisterAddress"] = "0503";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV2_Spannung"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0504";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV2_Strom"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0505";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV2_Mode"] = hexdec($rc["data"]) + 0;
    $Befehl["RegisterAddress"] = "0539";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV3_Spannung"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0506";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Batterie_Spannung"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0508";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["BMS_Status"] = hexdec($rc["data"]) + 0;
    $Befehl["RegisterAddress"] = "0509";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Batterie_Temperatur"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "050E";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["SOC"] = hexdec($rc["data"]) + 0;
    $Befehl["RegisterAddress"] = "0512";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Batterie_Mode"] = hexdec($rc["data"]) + 0;
    $Befehl["RegisterAddress"] = "0515";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Meter_Status"] = hexdec($rc["data"]) + 0;
    $Befehl["RegisterAddress"] = "0516";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Netzspannung"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0517";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Netzstrom"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0518";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Einspeisung_Bezug"] = $funktionen->hexdecs($rc["data"]);
    $Befehl["RegisterAddress"] = "0519";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Netzfrequenz"] = hexdec($rc["data"]) / 100;
    $Befehl["RegisterAddress"] = "051A";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Netzmode"] = hexdec($rc["data"]) + 0;
    $Befehl["RegisterAddress"] = "051B";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["AC_Spannung"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "051C";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["AC_Strom"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "051D";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Verbrauch"] = $funktionen->hexdecs($rc["data"]);
    $Befehl["RegisterAddress"] = "051F";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["WR_Mode"] = hexdec($rc["data"]) + 0;
    $Befehl["RegisterAddress"] = "0521";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["WR_Temperatur"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "0524";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $Einspeisung_H = $rc["data"];
    $Befehl["RegisterAddress"] = "0525";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Einspeisung_total"] = (hexdec($Einspeisung_H.$rc["data"])) * 100;
    $Befehl["RegisterAddress"] = "0528";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["EinspeisungHeute"] = hexdec($rc["data"]) * 100;
    $Befehl["RegisterAddress"] = "0529";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["BezugHeute"] = hexdec($rc["data"]) * 100;
    $aktuelleDaten["WattstundenGesamtHeute"] = ($aktuelleDaten["BezugHeute"] - $aktuelleDaten["EinspeisungHeute"]);
    $Befehl["RegisterAddress"] = "052D";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $PV_Leistung_total_H = $rc["data"];
    $Befehl["RegisterAddress"] = "052E";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV_Leistung_total"] = (hexdec($PV_Leistung_total_H.$rc["data"])) * 100;
    $Befehl["RegisterAddress"] = "0532";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $Diag_StatusH = $rc["data"];
    $Befehl["RegisterAddress"] = "0533";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Diag_Status"] = hexdec($Diag_StatusH.$rc["data"]) + 0;
    $Befehl["RegisterAddress"] = "0544";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV1_Leistung"] = hexdec($rc["data"]);
    $Befehl["RegisterAddress"] = "0545";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV2_Leistung"] = hexdec($rc["data"]);
    $Befehl["RegisterAddress"] = "0546";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["PV3_Leistung"] = hexdec($rc["data"]);
    $Befehl["RegisterAddress"] = "0547";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["Batterie_Leistung"] = hexdec($rc["data"]);
    $Befehl["RegisterAddress"] = "6000";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["MeterType"] = hexdec($rc["data"]) + 0;
    $Befehl["RegisterAddress"] = "6002";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["AC_Spannung"] = hexdec($rc["data"]) / 10;
    $aktuelleDaten["AC_Spannung_R"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "6003";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["AC_Spannung_S"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "6004";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["AC_Spannung_T"] = hexdec($rc["data"]) / 10;
    $Befehl["RegisterAddress"] = "6008";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["AC_Leistung_R"] = $funktionen->hexdecs($rc["data"]);
    $Befehl["RegisterAddress"] = "6009";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["AC_Leistung_S"] = $funktionen->hexdecs($rc["data"]);
    $Befehl["RegisterAddress"] = "600A";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["AC_Leistung_T"] = $funktionen->hexdecs($rc["data"]);
    $Befehl["RegisterAddress"] = "600B";
    $Befehl["RegisterCount"] = "0001";
    $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
    $aktuelleDaten["AC_Leistung"] = $funktionen->hexdecs($rc["data"]);
    $aktuelleDaten["PV_Leistung"] = ($aktuelleDaten["PV1_Leistung"] + $aktuelleDaten["PV2_Leistung"] + $aktuelleDaten["PV3_Leistung"]);
    $aktuelleDaten["Diag_Binary"] = base_convert(dechex($aktuelleDaten["Diag_Status"]), 16, 2);
    if ($aktuelleDaten["Batterie_Mode"] == "2") {
      $aktuelleDaten["Batterie_Leistung"] = $aktuelleDaten["Batterie_Leistung"] * (- 1);
    }
  }
  $funktionen->log_schreiben("Auslesen des Gerätes beendet.", "   ", 7);

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
  $aktuelleDaten["Produkt"] = "Goodwe";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  $funktionen->log_schreiben(print_r($aktuelleDaten, 1), "   ", 8);

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists("/var/www/html/goodwe_wr_math.php")) {
    include 'goodwe_wr_math.php'; // Falls etwas neu berechnet werden muss.
  }

  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT and strtoupper($MQTTAuswahl) != "OPENWB") {
    $funktionen->log_schreiben("MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1);
    require ($Pfad."/mqtt_senden.php");
  }

  /****************************************************************************
  //  Zeit und Datum
  ****************************************************************************/
  //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
  $aktuelleDaten["Timestamp"] = time();
  $aktuelleDaten["Monat"] = date("n");
  $aktuelleDaten["Woche"] = date("W");
  $aktuelleDaten["Wochentag"] = strftime("%A", time());
  $aktuelleDaten["Datum"] = date("d.m.Y");
  $aktuelleDaten["Uhrzeit"] = date("H:i:s");

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
      $rc = $funktionen->influx_remote_test();
      if ($rc) {
        $rc = $funktionen->influx_remote($aktuelleDaten);
        if ($rc) {
          $RemoteDaten = false;
        }
      }
      else {
        $RemoteDaten = false;
      }
    }
    if ($InfluxDB_local) {
      $rc = $funktionen->influx_local($aktuelleDaten);
    }
  }
  else {
    $rc = $funktionen->influx_local($aktuelleDaten);
  }
  if (is_file($Pfad."/1.user.config.php")) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (9 - (time() - $Start));
    $funktionen->log_schreiben("Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    $funktionen->log_schreiben("Schleife: ".($i)." Zeitspanne: ".(floor((56 - (time() - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9);
    sleep(floor((56 - (time() - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben("Schleife ".$i." Ausgang...", "   ", 8);
    break;
  }
  $i++;
} while (($Start + 54) > time());
if (isset($aktuelleDaten["Seriennummer"]) and $aktuelleDaten["KeineSonne"] == false) {

  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $funktionen->log_schreiben("Daten werden zur HomeMatic übertragen...", "   ", 8);
    require ($Pfad."/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    $funktionen->log_schreiben("Nachrichten versenden...", "   ", 8);
    require ($Pfad."/meldungen_senden.php");
  }
  $funktionen->log_schreiben("OK. Datenübertragung erfolgreich.", "   ", 7);
}
else {
  $funktionen->log_schreiben("Keine gültigen Daten empfangen.", "!! ", 6);
}
Ausgang:$funktionen->log_schreiben("----------------------   Stop   goodwe_wr.php   --------------------- ", "|--", 6);
return;
?>