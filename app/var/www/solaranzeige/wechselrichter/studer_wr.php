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
//  Es dient dem Auslesen der Regler der Tracer Serie über die USB Schnittstelle
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
$path_parts = pathinfo($argv[0]);
$Pfad = $path_parts['dirname'];
//if (!is_file($Pfad."/1.user.config.php")) {
if (!is_file($Pfad."/1.user.config.php")) {
  // Handelt es sich um ein Multi Regler System?
  require($Pfad."/user.config.php");
}


require_once($Pfad."/phpinc/funktionen.inc.php");
if (!isset($funktionen)) {
  $funktionen = new funktionen();
}
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}

$Tracelevel = 7;  //  1 bis 10  10 = Debug
$Version = "";
$Device = "WR"; // WR = Wechselrichter
$RemoteDaten = true;


if (empty($WR_Adresse)) {
  $WR_ID = "01";
}
elseif(strlen($WR_Adresse) == 1)  {
  $WR_ID = str_pad($WR_Adresse,2,"0",STR_PAD_LEFT);
}
else {
  $WR_ID = str_pad(substr($WR_Adresse,-2),2,"0",STR_PAD_LEFT);
}

$funktionen->log_schreiben("WR_ID: ".$WR_ID,"+  ",7);


$Befehl = array(
  "DeviceID" => $WR_ID,
  "BefehlFunctionCode" => "04",
  "RegisterAddress" => "3001",
  "RegisterCount" => "0001" );



$Start = time();  // Timestamp festhalten
$funktionen->log_schreiben("---------   Start  studer_wr.php  ---------------------------- ","|--",6);

$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");

//  Hardware Version ermitteln.
$Teile =  explode(" ",$Platine);
if ($Teile[1] == "Pi") {
  $Version = trim($Teile[2]);
  if ($Teile[3] == "Model") {
    $Version .= trim($Teile[4]);
    if ($Teile[5] == "Plus") {
      $Version .= trim($Teile[5]);
    }
  }
}
$funktionen->log_schreiben("Hardware Version: ".$Version,"o  ",8);

switch($Version) {
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
$USB1 = $funktionen->openUSB($USBRegler);
if (!is_resource($USB1)) {
  $funktionen->log_schreiben("USB Port kann nicht geöffnet werden. [1]","XX ",7);
  $funktionen->log_schreiben("Exit.... ","XX ",7);
  goto Ausgang;
}


$i = 1;
do {
  $funktionen->log_schreiben("Die Daten werden ausgelesen...",">  ",9);

  /**************************************************************************
  //  Ab hier wird der Studer Wechselrichter ausgelesen.
  //
  //  Ergebniswerte:
  // 
  // 
  // 
  // 
  // 
  // 
  // 
  //
  **************************************************************************/

  /****************************************************************************
  //  Ab hier werden die Studer Geräte ausgelesen.
  //
  ****************************************************************************/
  $aktuelleDaten["Wh_LeistungHeute"] = 0;
  $aktuelleDaten["Wh_VerbrauchHeute"] = 0;
  $aktuelleDaten["VT_PV_Strom"]    = 0;
  $aktuelleDaten["VT_PV_Leistung"] = 0;


  $Befehl["DeviceID"] = "01";
  $Befehl["BefehlFunctionCode"] = "04";
  $Befehl["RegisterAddress"] = "000a";
  $Befehl["RegisterCount"] = "0005";
  $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);

  $aktuelleDaten["Firmware"] = hexdec(substr($rc,0,2)).".".hexdec(substr($rc,4,2)).".".hexdec(substr($rc,6,2));


  $Befehl["DeviceID"] = "01";
  $Befehl["BefehlFunctionCode"] = "04";
  $Befehl["RegisterAddress"] = "0005";
  $Befehl["RegisterCount"] = "0005";
  $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);

  $aktuelleDaten["Anz-Xtender"] = hexdec(substr($rc,0,4));
  $aktuelleDaten["Anz-VarioTrack"] = hexdec(substr($rc,4,4));
  $aktuelleDaten["Anz-VarioString"] = hexdec(substr($rc,8,4));
  $aktuelleDaten["Anz-BSP"] = hexdec(substr($rc,12,4));
  $aktuelleDaten["Anz-RCC-xcom"] = hexdec(substr($rc,16,4));

  $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ",9);


  for ($j = 1; $j <= $aktuelleDaten["Anz-Xtender"]; $j++)  { 

    $XtenderID = ($j + 10);
    $XtenderHexID = substr("0".dechex($XtenderID),-2);



    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  Batterie Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "0000";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["Inverter".$j."_BatterieSpannung"] = round(floatval($Zahl[1]),1);


    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  Batterie Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "0002";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    if ($Zahl[1] == 32767)
      $aktuelleDaten["Inverter".$j."_BatterieTemperatur"] = 0;
    else
      $aktuelleDaten["Inverter".$j."_BatterieTemperatur"] = round(floatval($Zahl[1]),1);


    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  Batterie Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "000a";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["Inverter".$j."_BatterieLadestrom"] = round(floatval($Zahl[1]),1);

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  AC Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "009c";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["Inverter".$j."_BatterieEntladungHeute"] = round((floatval($Zahl[1])*1000),0);

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  Batterie Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "000e";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    if ($Zahl[1] == 32767)
      $aktuelleDaten["Inverter".$j."_BatterieSOC"] = 0;
    else
      $aktuelleDaten["Inverter".$j."_BatterieSOC"] = round(floatval($Zahl[1]),1);

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  Batterie Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "0038";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["Inverter".$j."_BatterieMode"] = intval($Zahl[1]);

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  Batterie Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "0014";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["Inverter".$j."_BatterieStatus"] = intval($Zahl[1]);

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  AC Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "0064";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));  
    $aktuelleDaten["Inverter".$j."_AnzBatteriezellen"] = intval($Zahl[1]);

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  AC Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "002a";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["Inverter".$j."_AC_Spannung"] = round(floatval($Zahl[1]),1);


    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  AC Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "002c";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["Inverter".$j."_AC_Strom"] = round(floatval($Zahl[1]));

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  AC Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "002e";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["Inverter".$j."_AC_Leistung"] = round((floatval($Zahl[1])*1000),2);

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  AC Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "0062";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["Inverter".$j."_Status"] = intval($Zahl[1]);


    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  AC Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "00a2";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["Inverter".$j."_Wh_LeistungHeute"] = round((floatval($Zahl[1])*1000),2);

    $aktuelleDaten["Wh_LeistungHeute"] = ($aktuelleDaten["Wh_LeistungHeute"] + $aktuelleDaten["Inverter".$j."_Wh_LeistungHeute"]);


    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  AC Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "00a6";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["Inverter".$j."_Wh_VerbrauchHeute"] = round((floatval($Zahl[1])*1000),2);

    $aktuelleDaten["Wh_VerbrauchHeute"] = ($aktuelleDaten["Wh_VerbrauchHeute"] + $aktuelleDaten["Inverter".$j."_Wh_VerbrauchHeute"]);


    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  AC Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "00a8";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["Inverter".$j."_AC_Frequenz"] = round(floatval($Zahl[1]),1);

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  AC Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "00b2";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);  
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["Inverter".$j."_AC_PhasenNr"] = intval($Zahl[1]);
    if ($aktuelleDaten["Inverter".$j."_AC_PhasenNr"] == 4) {
      $aktuelleDaten["Inverter".$j."_AC_PhasenNr"] = 3;
    }

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  AC Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "00ca";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["Inverter".$j."_AC_Leistung"] = round((floatval($Zahl[1])*1000),2);

  }


  if ($aktuelleDaten["Anz-BSP"] > 0) {

    $Befehl["DeviceID"] = "3c";   // BSP Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "0000";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["BSP_Spannung"] = round(floatval($Zahl[1]),1);

    $Befehl["DeviceID"] = "3c";   // BSP Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "0002";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["BSP_Strom"] = round(floatval($Zahl[1]),1);

    $Befehl["DeviceID"] = "3c";   // BSP Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "0004";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["BSP_SOC"] = round(floatval($Zahl[1]));

    $Befehl["DeviceID"] = "3c";   // BSP Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "0006";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["BSP_Leistung"] = round(floatval($Zahl[1]),1);

    $Befehl["DeviceID"] = "3c";   // BSP Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "003a";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["BSP_Temperatur"] = round(floatval($Zahl[1]),1);

    $Befehl["DeviceID"] = "3c";   // BSP Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "000e";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["BSP_Ah_geladenHeute"] = round(floatval($Zahl[1]),1);

    $Befehl["DeviceID"] = "3c";   // BSP Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "0010";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["BSP_Ah_entladenHeute"] = round(floatval($Zahl[1]),1);

  }


  for ($j = 1; $j <= $aktuelleDaten["Anz-VarioTrack"]; $j++)  { 

    $XtenderID = ($j + 20);
    $XtenderHexID = substr("0".dechex($XtenderID),-2);



    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  Batterie Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "0000";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["VT".$j."_Batteriespannung"] = round(floatval($Zahl[1]),1);

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  Batterie Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "0002";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["VT".$j."_Batteriestrom"] = round(floatval($Zahl[1]),1);

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  Batterie Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "0004";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["VT".$j."_PV_Spannung"] = round(floatval($Zahl[1]),1);

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  Batterie Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "0006";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["VT".$j."_PV_Strom"] = round(floatval($Zahl[1]),1);

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  Batterie Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "0008";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["VT".$j."_PV_Leistung"] = round((floatval($Zahl[1])*1000),1);

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  Batterie Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "000c";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["VT".$j."_Ah_Heute"] = round(floatval($Zahl[1]),1);

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  Batterie Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "000e";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["VT".$j."_Wh_Heute"] = round((floatval($Zahl[1])*1000),1);

    $Befehl["DeviceID"] = $XtenderHexID;   // Xtender 1  Batterie Info
    $Befehl["BefehlFunctionCode"] = "04";
    $Befehl["RegisterAddress"] = "001e";
    $Befehl["RegisterCount"] = "0002";
    $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
    $Zahl = unpack('G', hex2bin($rc));
    $aktuelleDaten["VT".$j."_Type"] = (int)$Zahl[1];

    $aktuelleDaten["VT_PV_Strom"]    = $aktuelleDaten["VT_PV_Strom"] + $aktuelleDaten["VT".$j."_PV_Strom"];
    $aktuelleDaten["VT_PV_Leistung"] = $aktuelleDaten["VT_PV_Leistung"] + $aktuelleDaten["VT".$j."_PV_Leistung"];

  }

  /******************************
  //  Paremeter abfrage
  $Befehl["DeviceID"] = "0b";   // Xtender 1
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterAddress"] = "0014";
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->sdm_auslesen($USB1,$Befehl,true);
  $Zahl = unpack('G', hex2bin($rc));
  $aktuelleDaten["MaxCurrentAC"] = round(floatval($Zahl[1]),1);
  ******************************/




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
  $aktuelleDaten["Produkt"]  = "Studer";
  $aktuelleDaten["WattstundenGesamtHeute"]  = 0;  // dummy
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);



  $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ",8);



  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists ("/var/www/html/studer_wr_math.php")) {
    include 'studer_wr_math.php';  // Falls etwas neu berechnet werden muss.
  }



  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT) {
    $funktionen->log_schreiben("MQTT Daten zum [ $MQTTBroker ] senden.","   ",1);
    require($Pfad."/mqtt_senden.php");
  }

  /****************************************************************************
  //  Zeit und Datum
  ****************************************************************************/
  //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
  $aktuelleDaten["Timestamp"] = time();
  $aktuelleDaten["Monat"]     = date("n");
  $aktuelleDaten["Woche"]     = date("W");
  $aktuelleDaten["Wochentag"] = strftime("%A",time());
  $aktuelleDaten["Datum"]     = date("d.m.Y");
  $aktuelleDaten["Uhrzeit"]      = date("H:i:s");



  /****************************************************************************
  //  InfluxDB  Zugangsdaten ...stehen in der user.config.php
  //  falls nicht, sind das hier die default Werte.
  ****************************************************************************/
  $aktuelleDaten["InfluxAdresse"] = $InfluxAdresse;
  $aktuelleDaten["InfluxPort"] =  $InfluxPort;
  $aktuelleDaten["InfluxUser"] =  $InfluxUser;
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
    $funktionen->log_schreiben("Multi-Regler-Ausgang. ".$Zeitspanne,"   ",2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    $funktionen->log_schreiben("Schleife: ".($i)." Zeitspanne: ".(floor((56 - (time() - $Start))/($Wiederholungen-$i+1))),"   ",9);
    sleep(floor((56 - (time() - $Start))/($Wiederholungen-$i+1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben("OK. Daten gelesen.","   ",9);
    $funktionen->log_schreiben("Schleife ".$i." Ausgang...","   ",8);
    break;
  }
  $i++;

} while (($Start + 56) > time());


if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {


  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $funktionen->log_schreiben("Daten werden zur HomeMatic übertragen...","   ",8);
    require($Pfad."/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    $funktionen->log_schreiben("Nachrichten versenden...","   ",8);
    require($Pfad."/meldungen_senden.php");
  }

  $funktionen->log_schreiben("OK. Datenübertragung erfolgreich.","   ",7);    
}
else {
  $funktionen->log_schreiben("Keine gültigen Daten empfangen.","!! ",6);
}


Ausgang:


$funktionen->log_schreiben("---------   Stop   studer_wr.php   --------------------------- ","|--",6);

return;


?>
