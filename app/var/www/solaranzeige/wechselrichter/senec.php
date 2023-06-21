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
//  Es dient dem Auslesen des SENEC Batterie-Speicher-Management über LAN
//  mit Port 80
//
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Start = time( ); // Timestamp festhalten
Log::write( "-----------------   Start  senec.php   --------------------- ", "|--", 6 );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
Log::write( "Senec: ".$WR_IP." Port: ".$WR_Port." GeräteID: ".$WR_Adresse, "   ", 7 );

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
//  Die Status Datei wird dazu benutzt, um mehrere Variablen des Reglers
//  pro Tag zu speichern.
//
*****************************************************************************/
$StatusFile = $basedir."/database/".$GeraeteNummer.".Tagesdaten.txt";
$Tagesdaten = array("WattstundenGesamtHeute" => 0, "BatterieGeladenHeute" => 0, "BatterieEntladenHeute" => 0);
if (!file_exists( $StatusFile )) {

  /***************************************************************************
  //  Inhalt der Status Datei anlegen, wenn nicht existiert.
  ***************************************************************************/
  $rc = file_put_contents( $StatusFile, serialize( $Tagesdaten ));
  if ($rc === false) {
    Log::write( "Konnte die Datei ".$StatusFile." nicht anlegen.", 5 );
  }
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;
  $aktuelleDaten["BatterieGeladenHeute"] = 0;
  $aktuelleDaten["BatterieEntladenHeute"] = 0;
}
else {
  $Tagesdaten = unserialize( file_get_contents( $StatusFile ));
  $aktuelleDaten["WattstundenGesamtHeute"] = $Tagesdaten["WattstundenGesamtHeute"];
  $aktuelleDaten["BatterieGeladenHeute"] = $Tagesdaten["BatterieGeladenHeute"];
  $aktuelleDaten["BatterieEntladenHeute"] = $Tagesdaten["BatterieEntladenHeute"];
}
$http_daten = array("URL" => "http://".$WR_IP."/lala.cgi", "Port" => $WR_Port, "Header" => array('Content-Type: application/json'));
$i = 1;
do {
  Log::write( "Die Daten werden ausgelesen...", "+  ", 9 );

  /****************************************************************************
  //  Ab hier wird der BMS  ausgelesen.
  //
  //  Ergebniswerte:
  //
  ****************************************************************************/
  $http_daten["Data"] = '{"PV1":{"POWER_RATIO":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["PV_Begrenzung"] = Utils::senec( $rc["PV1"]["POWER_RATIO"] );
  $http_daten["Data"] = '{"PM1OBJ1":{"P_TOTAL":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["AC_Leistung"] = round( Utils::senec( $rc["PM1OBJ1"]["P_TOTAL"] ), 2 );
  $http_daten["Data"] = '{"PM1OBJ1":{"FREQ":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Frequenz"] = round( Utils::senec( $rc["PM1OBJ1"]["FREQ"] ), 2 );
  $http_daten["Data"] = '{"PM1OBJ1":{"U_AC":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["AC_Spannung_R"] = round( Utils::senec( $rc["PM1OBJ1"]["U_AC"][0] ), 2 );
  $aktuelleDaten["AC_Spannung_S"] = round( Utils::senec( $rc["PM1OBJ1"]["U_AC"][1] ), 2 );
  $aktuelleDaten["AC_Spannung_T"] = round( Utils::senec( $rc["PM1OBJ1"]["U_AC"][2] ), 2 );
  $http_daten["Data"] = '{"PM1OBJ1":{"I_AC":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["AC_Strom_R"] = round( Utils::senec( $rc["PM1OBJ1"]["I_AC"][0] ), 2 );
  $aktuelleDaten["AC_Strom_S"] = round( Utils::senec( $rc["PM1OBJ1"]["I_AC"][1] ), 2 );
  $aktuelleDaten["AC_Strom_T"] = round( Utils::senec( $rc["PM1OBJ1"]["I_AC"][2] ), 2 );
  $http_daten["Data"] = '{"PM1OBJ1":{"P_AC":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["AC_Leistung_R"] = round( Utils::senec( $rc["PM1OBJ1"]["P_AC"][0] ), 2 );
  $aktuelleDaten["AC_Leistung_S"] = round( Utils::senec( $rc["PM1OBJ1"]["P_AC"][1] ), 2 );
  $aktuelleDaten["AC_Leistung_T"] = round( Utils::senec( $rc["PM1OBJ1"]["P_AC"][2] ), 2 );
  $http_daten["Data"] = '{"ENERGY":{"GUI_BAT_DATA_FUEL_CHARGE":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Bat_SOC"] = round( Utils::senec( $rc["ENERGY"]["GUI_BAT_DATA_FUEL_CHARGE"] ), 0 );
  $http_daten["Data"] = '{"ENERGY":{"GUI_BAT_DATA_POWER":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Bat_Leistung"] = round( Utils::senec( $rc["ENERGY"]["GUI_BAT_DATA_POWER"] ), 2 );
  $http_daten["Data"] = '{"ENERGY":{"GUI_BAT_DATA_VOLTAGE":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Bat_Spannung"] = round( Utils::senec( $rc["ENERGY"]["GUI_BAT_DATA_VOLTAGE"] ), 2 );
  $http_daten["Data"] = '{"ENERGY":{"GUI_BAT_DATA_CURRENT":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Bat_Strom"] = round( Utils::senec( $rc["ENERGY"]["GUI_BAT_DATA_CURRENT"] ), 2 );
  $http_daten["Data"] = '{"ENERGY":{"GUI_HOUSE_POW":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Hausverbrauch"] = round( Utils::senec( $rc["ENERGY"]["GUI_HOUSE_POW"] ), 2 );
  $http_daten["Data"] = '{"ENERGY":{"GUI_INVERTER_POWER":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["PV_Leistung"] = round( Utils::senec( $rc["ENERGY"]["GUI_INVERTER_POWER"] ), 2 );
  $http_daten["Data"] = '{"ENERGY":{"GUI_GRID_POW":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Netz_Leistung"] = round( Utils::senec( $rc["ENERGY"]["GUI_GRID_POW"] ), 2 );
  $http_daten["Data"] = '{"ENERGY":{"STAT_STATE":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Status"] = round( Utils::senec( $rc["ENERGY"]["STAT_STATE"] ), 2 );
  $http_daten["Data"] = '{"ENERGY":{"STAT_HOURS_OF_OPERATION":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Betriebsstunden"] = round( Utils::senec( $rc["ENERGY"]["STAT_HOURS_OF_OPERATION"] ), 2 );
  $http_daten["Data"] = '{"BMS":{"NR_INSTALLED":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Anz_Batterien"] = Utils::senec( $rc["BMS"]["NR_INSTALLED"] );
  $http_daten["Data"] = '{"BMS":{"TOTAL_CURRENT":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Gesamtstrom"] = Utils::senec( $rc["BMS"]["TOTAL_CURRENT"] );
  $http_daten["Data"] = '{"STATISTIC":{"LIVE_GRID_IMPORT":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["NetzbezugGesamt"] = round( Utils::senec( $rc["STATISTIC"]["LIVE_GRID_IMPORT"] ) * 1000, 1 );
  $http_daten["Data"] = '{"STATISTIC":{"LIVE_GRID_EXPORT":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["EinspeisungGesamt"] = round( Utils::senec( $rc["STATISTIC"]["LIVE_GRID_EXPORT"] ) * 1000, 1 );
  $http_daten["Data"] = '{"STATISTIC":{"LIVE_HOUSE_CONS":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["VerbrauchGesamt"] = round( Utils::senec( $rc["STATISTIC"]["LIVE_HOUSE_CONS"] ) * 1000, 1 );
  $http_daten["Data"] = '{"STATISTIC":{"LIVE_PV_GEN":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["PV_Gesamtleistung"] = round( Utils::senec( $rc["STATISTIC"]["LIVE_PV_GEN"] ) * 1000, 1 );
  $http_daten["Data"] = '{"STATISTIC":{"CURRENT_STATE":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["GeraeteStatus"] = substr( str_repeat( 0, 4 ).dechex( Utils::senec( $rc["STATISTIC"]["CURRENT_STATE"] )), - 4 );

  /**************************************************************************/
  //  Eingefügt am 29.5.2021
  $aktuelleDaten["Batterie_Temperatur"] = 0;
  $aktuelleDaten["Gehaeuse_Temperatur"] = 0;
  $aktuelleDaten["CPU_Temperatur"] = 0;
  $aktuelleDaten["FAN_Speed"] = 0;
  $aktuelleDaten["Ladezyklen_BMS1"] = 0;
  $aktuelleDaten["Ladezyclen_BMS2"] = 0;
  $aktuelleDaten["Ladezyklen_BMS3"] = 0;
  $aktuelleDaten["Ladezyklen_BMS4"] = 0;
  $aktuelleDaten["Bat1_Strom"] = 0;
  $aktuelleDaten["Bat2_Strom"] = 0;
  $aktuelleDaten["Bat3_Strom"] = 0;
  $aktuelleDaten["Bat4_Strom"] = 0;
  $aktuelleDaten["Bat1_Spannung"] = 0;
  $aktuelleDaten["Bat2_Spannung"] = 0;
  $aktuelleDaten["Bat3_Spannung"] = 0;
  $aktuelleDaten["Bat4_Spannung"] = 0;
  $aktuelleDaten["Bat1_SOC"] = 0;
  $aktuelleDaten["Bat2_SOC"] = 0;
  $aktuelleDaten["Bat3_SOC"] = 0;
  $aktuelleDaten["Bat4_SOC"] = 0;

  /*************    Eingefügt am 30.4.2022   ***********************************/
  $aktuelleDaten["Bat1_Cell_Volt1"] = 0;
  $aktuelleDaten["Bat1_Cell_Volt2"] = 0;
  $aktuelleDaten["Bat1_Cell_Volt3"] = 0;
  $aktuelleDaten["Bat1_Cell_Volt4"] = 0;
  $aktuelleDaten["Bat1_Cell_Volt5"] = 0;
  $aktuelleDaten["Bat1_Cell_Volt6"] = 0;
  $aktuelleDaten["Bat1_Cell_Volt7"] = 0;
  $aktuelleDaten["Bat1_Cell_Volt8"] = 0;
  $aktuelleDaten["Bat1_Cell_Volt9"] = 0;
  $aktuelleDaten["Bat1_Cell_Volt10"] = 0;
  $aktuelleDaten["Bat1_Cell_Volt11"] = 0;
  $aktuelleDaten["Bat1_Cell_Volt12"] = 0;
  $aktuelleDaten["Bat1_Cell_Volt13"] = 0;
  $aktuelleDaten["Bat1_Cell_Volt14"] = 0;
  $aktuelleDaten["Bat2_Cell_Volt1"] = 0;
  $aktuelleDaten["Bat2_Cell_Volt2"] = 0;
  $aktuelleDaten["Bat2_Cell_Volt3"] = 0;
  $aktuelleDaten["Bat2_Cell_Volt4"] = 0;
  $aktuelleDaten["Bat2_Cell_Volt5"] = 0;
  $aktuelleDaten["Bat2_Cell_Volt6"] = 0;
  $aktuelleDaten["Bat2_Cell_Volt7"] = 0;
  $aktuelleDaten["Bat2_Cell_Volt8"] = 0;
  $aktuelleDaten["Bat2_Cell_Volt9"] = 0;
  $aktuelleDaten["Bat2_Cell_Volt10"] = 0;
  $aktuelleDaten["Bat2_Cell_Volt11"] = 0;
  $aktuelleDaten["Bat2_Cell_Volt12"] = 0;
  $aktuelleDaten["Bat2_Cell_Volt13"] = 0;
  $aktuelleDaten["Bat2_Cell_Volt14"] = 0;
  $aktuelleDaten["Bat3_Cell_Volt1"] = 0;
  $aktuelleDaten["Bat3_Cell_Volt2"] = 0;
  $aktuelleDaten["Bat3_Cell_Volt3"] = 0;
  $aktuelleDaten["Bat3_Cell_Volt4"] = 0;
  $aktuelleDaten["Bat3_Cell_Volt5"] = 0;
  $aktuelleDaten["Bat3_Cell_Volt6"] = 0;
  $aktuelleDaten["Bat3_Cell_Volt7"] = 0;
  $aktuelleDaten["Bat3_Cell_Volt8"] = 0;
  $aktuelleDaten["Bat3_Cell_Volt9"] = 0;
  $aktuelleDaten["Bat3_Cell_Volt10"] = 0;
  $aktuelleDaten["Bat3_Cell_Volt11"] = 0;
  $aktuelleDaten["Bat3_Cell_Volt12"] = 0;
  $aktuelleDaten["Bat3_Cell_Volt13"] = 0;
  $aktuelleDaten["Bat3_Cell_Volt14"] = 0;
  $aktuelleDaten["Bat4_Cell_Volt1"] = 0;
  $aktuelleDaten["Bat4_Cell_Volt2"] = 0;
  $aktuelleDaten["Bat4_Cell_Volt3"] = 0;
  $aktuelleDaten["Bat4_Cell_Volt4"] = 0;
  $aktuelleDaten["Bat4_Cell_Volt5"] = 0;
  $aktuelleDaten["Bat4_Cell_Volt6"] = 0;
  $aktuelleDaten["Bat4_Cell_Volt7"] = 0;
  $aktuelleDaten["Bat4_Cell_Volt8"] = 0;
  $aktuelleDaten["Bat4_Cell_Volt9"] = 0;
  $aktuelleDaten["Bat4_Cell_Volt10"] = 0;
  $aktuelleDaten["Bat4_Cell_Volt11"] = 0;
  $aktuelleDaten["Bat4_Cell_Volt12"] = 0;
  $aktuelleDaten["Bat4_Cell_Volt13"] = 0;
  $aktuelleDaten["Bat4_Cell_Volt14"] = 0;
  $http_daten["Data"] = '{"BMS":{"CELL_VOLTAGES_MODULE_A":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Bat1_Cell_Volt1"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_A"][0] );
  $aktuelleDaten["Bat1_Cell_Volt2"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_A"][1] );
  $aktuelleDaten["Bat1_Cell_Volt3"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_A"][2] );
  $aktuelleDaten["Bat1_Cell_Volt4"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_A"][3] );
  $aktuelleDaten["Bat1_Cell_Volt5"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_A"][4] );
  $aktuelleDaten["Bat1_Cell_Volt6"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_A"][5] );
  $aktuelleDaten["Bat1_Cell_Volt7"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_A"][6] );
  $aktuelleDaten["Bat1_Cell_Volt8"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_A"][7] );
  $aktuelleDaten["Bat1_Cell_Volt9"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_A"][8] );
  $aktuelleDaten["Bat1_Cell_Volt10"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_A"][9] );
  $aktuelleDaten["Bat1_Cell_Volt11"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_A"][10] );
  $aktuelleDaten["Bat1_Cell_Volt12"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_A"][11] );
  $aktuelleDaten["Bat1_Cell_Volt13"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_A"][12] );
  $aktuelleDaten["Bat1_Cell_Volt14"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_A"][13] );
  $http_daten["Data"] = '{"BMS":{"CELL_VOLTAGES_MODULE_B":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Bat2_Cell_Volt1"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_B"][0] );
  $aktuelleDaten["Bat2_Cell_Volt2"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_B"][1] );
  $aktuelleDaten["Bat2_Cell_Volt3"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_B"][2] );
  $aktuelleDaten["Bat2_Cell_Volt4"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_B"][3] );
  $aktuelleDaten["Bat2_Cell_Volt5"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_B"][4] );
  $aktuelleDaten["Bat2_Cell_Volt6"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_B"][5] );
  $aktuelleDaten["Bat2_Cell_Volt7"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_B"][6] );
  $aktuelleDaten["Bat2_Cell_Volt8"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_B"][7] );
  $aktuelleDaten["Bat2_Cell_Volt9"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_B"][8] );
  $aktuelleDaten["Bat2_Cell_Volt10"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_B"][9] );
  $aktuelleDaten["Bat2_Cell_Volt11"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_B"][10] );
  $aktuelleDaten["Bat2_Cell_Volt12"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_B"][11] );
  $aktuelleDaten["Bat2_Cell_Volt13"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_B"][12] );
  $aktuelleDaten["Bat2_Cell_Volt14"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_B"][13] );
  $http_daten["Data"] = '{"BMS":{"CELL_VOLTAGES_MODULE_C":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Bat3_Cell_Volt1"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_C"][0] );
  $aktuelleDaten["Bat3_Cell_Volt2"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_C"][1] );
  $aktuelleDaten["Bat3_Cell_Volt3"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_C"][2] );
  $aktuelleDaten["Bat3_Cell_Volt4"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_C"][3] );
  $aktuelleDaten["Bat3_Cell_Volt5"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_C"][4] );
  $aktuelleDaten["Bat3_Cell_Volt6"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_C"][5] );
  $aktuelleDaten["Bat3_Cell_Volt7"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_C"][6] );
  $aktuelleDaten["Bat3_Cell_Volt8"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_C"][7] );
  $aktuelleDaten["Bat3_Cell_Volt9"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_C"][8] );
  $aktuelleDaten["Bat3_Cell_Volt10"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_C"][9] );
  $aktuelleDaten["Bat3_Cell_Volt11"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_C"][10] );
  $aktuelleDaten["Bat3_Cell_Volt12"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_C"][11] );
  $aktuelleDaten["Bat3_Cell_Volt13"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_C"][12] );
  $aktuelleDaten["Bat3_Cell_Volt14"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_C"][13] );
  $http_daten["Data"] = '{"BMS":{"CELL_VOLTAGES_MODULE_D":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Bat4_Cell_Volt1"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_D"][0] );
  $aktuelleDaten["Bat4_Cell_Volt2"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_D"][1] );
  $aktuelleDaten["Bat4_Cell_Volt3"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_D"][2] );
  $aktuelleDaten["Bat4_Cell_Volt4"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_D"][3] );
  $aktuelleDaten["Bat4_Cell_Volt5"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_D"][4] );
  $aktuelleDaten["Bat4_Cell_Volt6"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_D"][5] );
  $aktuelleDaten["Bat4_Cell_Volt7"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_D"][6] );
  $aktuelleDaten["Bat4_Cell_Volt8"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_D"][7] );
  $aktuelleDaten["Bat4_Cell_Volt9"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_D"][8] );
  $aktuelleDaten["Bat4_Cell_Volt10"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_D"][9] );
  $aktuelleDaten["Bat4_Cell_Volt11"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_D"][10] );
  $aktuelleDaten["Bat4_Cell_Volt12"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_D"][11] );
  $aktuelleDaten["Bat4_Cell_Volt13"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_D"][12] );
  $aktuelleDaten["Bat4_Cell_Volt14"] = Utils::senec( $rc["BMS"]["CELL_VOLTAGES_MODULE_D"][13] );

  /*****************************************************************************/

  //  Eingefügt Timo 09.05.2022
  $aktuelleDaten["Bat1_Cell_Temp1"] = 0;
  $aktuelleDaten["Bat1_Cell_Temp2"] = 0;
  $aktuelleDaten["Bat1_Cell_Temp3"] = 0;
  $aktuelleDaten["Bat1_Cell_Temp4"] = 0;
  $aktuelleDaten["Bat1_Cell_Temp5"] = 0;
  $aktuelleDaten["Bat1_Cell_Temp6"] = 0;
  $aktuelleDaten["Bat2_Cell_Temp1"] = 0;
  $aktuelleDaten["Bat2_Cell_Temp2"] = 0;
  $aktuelleDaten["Bat2_Cell_Temp3"] = 0;
  $aktuelleDaten["Bat2_Cell_Temp4"] = 0;
  $aktuelleDaten["Bat2_Cell_Temp5"] = 0;
  $aktuelleDaten["Bat2_Cell_Temp6"] = 0;
  $aktuelleDaten["Bat3_Cell_Temp1"] = 0;
  $aktuelleDaten["Bat3_Cell_Temp2"] = 0;
  $aktuelleDaten["Bat3_Cell_Temp3"] = 0;
  $aktuelleDaten["Bat3_Cell_Temp4"] = 0;
  $aktuelleDaten["Bat3_Cell_Temp5"] = 0;
  $aktuelleDaten["Bat3_Cell_Temp6"] = 0;
  $aktuelleDaten["Bat4_Cell_Temp1"] = 0;
  $aktuelleDaten["Bat4_Cell_Temp2"] = 0;
  $aktuelleDaten["Bat4_Cell_Temp3"] = 0;
  $aktuelleDaten["Bat4_Cell_Temp4"] = 0;
  $aktuelleDaten["Bat4_Cell_Temp5"] = 0;
  $aktuelleDaten["Bat4_Cell_Temp6"] = 0;

  $http_daten["Data"] = '{"BMS":{"CELL_TEMPERATURES_MODULE_A":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Bat1_Cell_Temp1"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_A"][0] );
  $aktuelleDaten["Bat1_Cell_Temp2"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_A"][1] );
  $aktuelleDaten["Bat1_Cell_Temp3"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_A"][2] );
  $aktuelleDaten["Bat1_Cell_Temp4"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_A"][3] );
  $aktuelleDaten["Bat1_Cell_Temp5"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_A"][4] );
  $aktuelleDaten["Bat1_Cell_Temp6"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_A"][5] );
  $http_daten["Data"] = '{"BMS":{"CELL_TEMPERATURES_MODULE_B":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Bat2_Cell_Temp1"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_B"][0] );
  $aktuelleDaten["Bat2_Cell_Temp2"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_B"][1] );
  $aktuelleDaten["Bat2_Cell_Temp3"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_B"][2] );
  $aktuelleDaten["Bat2_Cell_Temp4"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_B"][3] );
  $aktuelleDaten["Bat2_Cell_Temp5"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_B"][4] );
  $aktuelleDaten["Bat2_Cell_Temp6"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_B"][5] );
  $http_daten["Data"] = '{"BMS":{"CELL_TEMPERATURES_MODULE_C":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Bat3_Cell_Temp1"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_C"][0] );
  $aktuelleDaten["Bat3_Cell_Temp2"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_C"][1] );
  $aktuelleDaten["Bat3_Cell_Temp3"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_C"][2] );
  $aktuelleDaten["Bat3_Cell_Temp4"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_C"][3] );
  $aktuelleDaten["Bat3_Cell_Temp5"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_C"][4] );
  $aktuelleDaten["Bat3_Cell_Temp6"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_C"][5] );
  $http_daten["Data"] = '{"BMS":{"CELL_TEMPERATURES_MODULE_D":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Bat4_Cell_Temp1"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_D"][0] );
  $aktuelleDaten["Bat4_Cell_Temp2"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_D"][1] );
  $aktuelleDaten["Bat4_Cell_Temp3"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_D"][2] );
  $aktuelleDaten["Bat4_Cell_Temp4"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_D"][3] );
  $aktuelleDaten["Bat4_Cell_Temp5"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_D"][4] );
  $aktuelleDaten["Bat4_Cell_Temp6"] = Utils::senec( $rc["BMS"]["CELL_TEMPERATURES_MODULE_D"][5] );

  /*****************************************************************************/

  $http_daten["Data"] = '{"TEMPMEASURE":{"BATTERY_TEMP":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Batterie_Temperatur"] = round( Utils::senec( $rc["TEMPMEASURE"]["BATTERY_TEMP"] ), 1 );
  $http_daten["Data"] = '{"TEMPMEASURE":{"CASE_TEMP":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Gehaeuse_Temperatur"] = round( Utils::senec( $rc["TEMPMEASURE"]["CASE_TEMP"] ), 1 );
  $http_daten["Data"] = '{"TEMPMEASURE":{"MCU_TEMP":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["CPU_Temperatur"] = round( Utils::senec( $rc["TEMPMEASURE"]["MCU_TEMP"] ), 1 );
  $http_daten["Data"] = '{"FAN_SPEED":{"INV_LV":""}}';
  $rc = Utils::http_read( $http_daten );
  if (isset($rc["FAN_SPEED"]["INV_LV"])) {
    $aktuelleDaten["FAN_Speed"] = Utils::senec( $rc["FAN_SPEED"]["INV_LV"] );
  }
  $http_daten["Data"] = '{"BMS":{"CYCLES":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Ladezyklen_BMS1"] = Utils::senec( $rc["BMS"]["CYCLES"][0] );
  $aktuelleDaten["Ladezyclen_BMS2"] = Utils::senec( $rc["BMS"]["CYCLES"][1] );
  $aktuelleDaten["Ladezyklen_BMS3"] = Utils::senec( $rc["BMS"]["CYCLES"][2] );
  $aktuelleDaten["Ladezyklen_BMS4"] = Utils::senec( $rc["BMS"]["CYCLES"][3] );
  //------------------------------
  //  Eingefügt Jan. 2022
  $http_daten["Data"] = '{"BMS":{"CURRENT":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Bat1_Strom"] = round( Utils::senec( $rc["BMS"]["CURRENT"][0] ), 2 );
  $aktuelleDaten["Bat2_Strom"] = round( Utils::senec( $rc["BMS"]["CURRENT"][1] ), 2 );
  $aktuelleDaten["Bat3_Strom"] = round( Utils::senec( $rc["BMS"]["CURRENT"][2] ), 2 );
  $aktuelleDaten["Bat4_Strom"] = round( Utils::senec( $rc["BMS"]["CURRENT"][3] ), 2 );
  $http_daten["Data"] = '{"BMS":{"VOLTAGE":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Bat1_Spannung"] = round( Utils::senec( $rc["BMS"]["VOLTAGE"][0] ), 2 );
  $aktuelleDaten["Bat2_Spannung"] = round( Utils::senec( $rc["BMS"]["VOLTAGE"][1] ), 2 );
  $aktuelleDaten["Bat3_Spannung"] = round( Utils::senec( $rc["BMS"]["VOLTAGE"][2] ), 2 );
  $aktuelleDaten["Bat4_Spannung"] = round( Utils::senec( $rc["BMS"]["VOLTAGE"][3] ), 2 );
  $http_daten["Data"] = '{"BMS":{"SOC":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Bat1_SOC"] = round( Utils::senec( $rc["BMS"]["SOC"][0] ), 2 );
  $aktuelleDaten["Bat2_SOC"] = round( Utils::senec( $rc["BMS"]["SOC"][1] ), 2 );
  $aktuelleDaten["Bat3_SOC"] = round( Utils::senec( $rc["BMS"]["SOC"][2] ), 2 );
  $aktuelleDaten["Bat4_SOC"] = round( Utils::senec( $rc["BMS"]["SOC"][3] ), 2 );
  //------------------------------
  $http_daten["Data"] = '{"BAT1":{"NSP2_FW":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Firmware"] = Utils::senec( $rc["BAT1"]["NSP2_FW"] );
  if ($aktuelleDaten["Netz_Leistung"] < 0) {
    $aktuelleDaten["Einspeisung"] = abs( $aktuelleDaten["Netz_Leistung"] );
    $aktuelleDaten["Bezug"] = 0;
  }
  else {
    $aktuelleDaten["Einspeisung"] = 0;
    $aktuelleDaten["Bezug"] = $aktuelleDaten["Netz_Leistung"];
  }
  $aktuelleDaten["MPPT1_Strom"] = 0;
  $aktuelleDaten["MPPT2_Strom"] = 0;
  $aktuelleDaten["MPPT3_Strom"] = 0;
  $aktuelleDaten["MPPT1_Spannung"] = 0;
  $aktuelleDaten["MPPT2_Spannung"] = 0;
  $aktuelleDaten["MPPT3_Spannung"] = 0;
  $aktuelleDaten["MPPT1_Leistung"] = 0;
  $aktuelleDaten["MPPT2_Leistung"] = 0;
  $aktuelleDaten["MPPT3_Leistung"] = 0;
  $aktuelleDaten["Softwareversion"] = 0;
  $aktuelleDaten["Anz_Wallboxen"] = 0;
  $http_daten["Data"] = '{"PV1":{"MPP_CUR":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["MPPT1_Strom"] = round( Utils::senec( $rc["PV1"]["MPP_CUR"]["0"] ), 2 );
  $aktuelleDaten["MPPT2_Strom"] = round( Utils::senec( $rc["PV1"]["MPP_CUR"]["1"] ), 2 );
  $aktuelleDaten["MPPT3_Strom"] = round( Utils::senec( $rc["PV1"]["MPP_CUR"]["2"] ), 2 );
  $http_daten["Data"] = '{"PV1":{"MPP_VOL":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["MPPT1_Spannung"] = round( Utils::senec( $rc["PV1"]["MPP_VOL"]["0"] ), 2 );
  $aktuelleDaten["MPPT2_Spannung"] = round( Utils::senec( $rc["PV1"]["MPP_VOL"]["1"] ), 2 );
  $aktuelleDaten["MPPT3_Spannung"] = round( Utils::senec( $rc["PV1"]["MPP_VOL"]["2"] ), 2 );
  $http_daten["Data"] = '{"PV1":{"MPP_POWER":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["MPPT1_Leistung"] = round( Utils::senec( $rc["PV1"]["MPP_POWER"]["0"] ), 2 );
  $aktuelleDaten["MPPT2_Leistung"] = round( Utils::senec( $rc["PV1"]["MPP_POWER"]["1"] ), 2 );
  $aktuelleDaten["MPPT3_Leistung"] = round( Utils::senec( $rc["PV1"]["MPP_POWER"]["2"] ), 2 );
  $http_daten["Data"] = '{"WIZARD":{"APPLICATION_VERSION":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Softwareversion"] = Utils::senec( $rc["WIZARD"]["APPLICATION_VERSION"] );
  $http_daten["Data"] = '{"WIZARD":{"SETUP_NUMBER_WALLBOXES":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Anz_Wallboxen"] = Utils::senec( $rc["WIZARD"]["SETUP_NUMBER_WALLBOXES"] );

  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/

  /**************************************************************************
  //  Falls ein ErrorCode / Statusmeldung vorliegt, wird er hier in einen
  //  lesbaren Text umgewandelt, sodass er als Fehlermeldung gesendet werden
  //  kann. Die Funktion ist noch nicht überall implementiert.
  **************************************************************************/
  $FehlermeldungText = "";
  switch ($aktuelleDaten["Status"]) {

    case 0:
      $FehlermeldungText = "INITIALZUSTAND";
      break;

    case 1:
      $FehlermeldungText = "KEINE KOMMUNIKATION LADEGERAET";
      break;

    case 2:
      $FehlermeldungText = "FEHLER LEISTUNGSMESSGERAET";
      break;

    case 3:
      $FehlermeldungText = "RUNDSTEUEREMPFAENGER";
      break;

    case 4:
      $FehlermeldungText = "ERSTLADUNG";
      break;

    case 5:
      $FehlermeldungText = "WARTUNGSLADUNG";
      break;

    case 6:
      $FehlermeldungText = "WARTUNGSLADUNG FERTIG";
      break;

    case 7:
      $FehlermeldungText = "WARTUNG NOTWENDIG";
      break;

    case 8:
      $FehlermeldungText = "MAN. SICHERHEITSLADUNG";
      break;

    case 9:
      $FehlermeldungText = "SICHERHEITSLADUNG FERTIG";
      break;

    case 10:
      $FehlermeldungText = "VOLLLADUNG";
      break;

    case 11:
      $FehlermeldungText = "AUSGLEICHSLADUNG: LADEN";
      break;

    case 12:
      $FehlermeldungText = "SULFATLADUNG: LADEN";
      break;

    case 13:
      $FehlermeldungText = "AKKU VOLL";
      break;

    case 14:
      $FehlermeldungText = "LADEN";
      break;

    case 15:
      $FehlermeldungText = "AKKU LEER";
      break;

    case 16:
      $FehlermeldungText = "ENTLADEN";
      break;

    case 17:
      $FehlermeldungText = "PV + ENTLADEN";
      break;

    case 18:
      $FehlermeldungText = "NETZ + ENTLADEN";
      break;

    case 19:
      $FehlermeldungText = "PASSIV";
      break;

    case 20:
      $FehlermeldungText = "AUSGESCHALTET";
      break;

    case 21:
      $FehlermeldungText = "EIGENVERBRAUCH";
      break;

    case 22:
      $FehlermeldungText = "NEUSTART";
      break;

    case 23:
      $FehlermeldungText = "MAN. AUSGLEICHSLADUNG: LADEN";
      break;

    case 24:
      $FehlermeldungText = "MAN. SULFATLADUNG: LADEN";
      break;

    case 25:
      $FehlermeldungText = "SICHERHEITSLADUNG";
      break;

    case 26:
      $FehlermeldungText = "AKKU-SCHUTZBETRIEB";
      break;

    case 27:
      $FehlermeldungText = "EG FEHLER";
      break;

    case 28:
      $FehlermeldungText = "EG LADEN";
      break;

    case 29:
      $FehlermeldungText = "EG ENTLADEN";
      break;

    case 30:
      $FehlermeldungText = "EG PASSIV";
      break;

    case 31:
      $FehlermeldungText = "EG LADEN VERBOTEN";
      break;

    case 32:
      $FehlermeldungText = "EG ENTLADEN VERBOTEN";
      break;

    case 33:
      $FehlermeldungText = "NOTLADUNG";
      break;

    case 34:
      $FehlermeldungText = "SOFTWAREAKTUALISIERUNG";
      break;

    case 35:
      $FehlermeldungText = "FEHLER: NA-SCHUTZ";
      break;

    case 36:
      $FehlermeldungText = "FEHLER: NA-SCHUTZ NETZ";
      break;

    case 37:
      $FehlermeldungText = "FEHLER: NA-SCHUTZ HARDWARE";
      break;

    case 38:
      $FehlermeldungText = "KEINE SERVERVERBINDUNG";
      break;

    case 39:
      $FehlermeldungText = "BMS FEHLER";
      break;

    case 40:
      $FehlermeldungText = "WARTUNG: FILTER";
      break;

    case 41:
      $FehlermeldungText = "SCHLAFMODUS";
      break;

    case 42:
      $FehlermeldungText = "WARTE AUF ÜBERSCHUSS";
      break;

    case 43:
      $FehlermeldungText = "KAPAZITÄTSTEST: LADEN";
      break;

    case 44:
      $FehlermeldungText = "KAPAZITÄTSTEST: ENTLADEN";
      break;

    case 45:
      $FehlermeldungText = "MAN. SULFATLADUNG: WARTEN";
      break;

    case 46:
      $FehlermeldungText = "MAN. SULFATLADUNG: FERTIG";
      break;

    case 47:
      $FehlermeldungText = "MAN. SULFATLADUNG: FEHLER";
      break;

    case 48:
      $FehlermeldungText = "AUSGLEICHSLADUNG: WARTEN";
      break;

    case 49:
      $FehlermeldungText = "NOTLADUNG: FEHLER";
      break;

    case 50:
      $FehlermeldungText = "MAN: AUSGLEICHSLADUNG: WARTEN";
      break;

    case 51:
      $FehlermeldungText = "MAN: AUSGLEICHSLADUNG: FEHLER";
      break;

    case 52:
      $FehlermeldungText = "MAN: AUSGLEICHSLADUNG: FERTIG";
      break;

    case 53:
      $FehlermeldungText = "AUTO: SULFATLADUNG: WARTEN";
      break;

    case 54:
      $FehlermeldungText = "LADESCHLUSSPHASE";
      break;

    case 55:
      $FehlermeldungText = "BATTERIETRENNSCHALTER AUS";
      break;

    case 56:
      $FehlermeldungText = "PEAK-SHAVING: WARTEN";
      break;

    case 57:
      $FehlermeldungText = "FEHLER LADEGERAET";
      break;

    case 58:
      $FehlermeldungText = "NPU-FEHLER";
      break;

    case 59:
      $FehlermeldungText = "BMS OFFLINE";
      break;

    case 60:
      $FehlermeldungText = "WARTUNGSLADUNG FEHLER";
      break;

    case 61:
      $FehlermeldungText = "MAN. SICHERHEITSLADUNG FEHLER";
      break;

    case 62:
      $FehlermeldungText = "SICHERHEITSLADUNG FEHLER";
      break;

    case 63:
      $FehlermeldungText = "KEINE MASTERVERBINDUNG";
      break;

    case 64:
      $FehlermeldungText = "LITHIUM SICHERHEITSMODUS AKTIV";
      break;

    case 65:
      $FehlermeldungText = "LITHIUM SICHERHEITSMODUS BEENDET";
      break;

    case 66:
      $FehlermeldungText = "FEHLER BATTERIESPANNUNG";
      break;

    case 67:
      $FehlermeldungText = "BMS DC AUSGESCHALTET";
      break;

    case 68:
      $FehlermeldungText = "NETZINITIALISIERUNG";
      break;

    case 69:
      $FehlermeldungText = "NETZSTABILISIERUNG";
      break;

    case 70:
      $FehlermeldungText = "FERNABSCHALTUNG";
      break;

    case 71:
      $FehlermeldungText = "OFFPEAK-LADEN";
      break;

    case 72:
      $FehlermeldungText = "FEHLER HALBBRÜCKE";
      break;

    case 73:
      $FehlermeldungText = "BMS: FEHLER BETRIEBSTEMPERATUR";
      break;

    case 74:
      $FehlermeldungText = "FACOTRY SETTINGS NICHT GEFUNDEN";
      break;

    case 75:
      $FehlermeldungText = "NETZERSATZBETRIEB";
      break;

    case 76:
      $FehlermeldungText = "NETZERSATZBETRIEB AKKU LEER";
      break;

    case 77:
      $FehlermeldungText = "NETZERSATZBETRIEB FEHLER";
      break;

    case 78:
      $FehlermeldungText = "INITIALISIERUNG";
      break;

    case 79:
      $FehlermeldungText = "INSTALLATIONSMODUS";
      break;

    case 80:
      $FehlermeldungText = "NETZAUSFALL";
      break;

    case 81:
      $FehlermeldungText = "BMS UPDATE ERFORDERLICH";
      break;

    case 82:
      $FehlermeldungText = "BMS KONFIGURATION ERFORDERLICH";
      break;

    case 83:
      $FehlermeldungText = "ISOLATIONSPRÜFUNG";
      break;

    case 84:
      $FehlermeldungText = "SELBSTTEST";
      break;

    case 85:
      $FehlermeldungText = "EXTERNE KONTROLLE";
      break;

    case 86:
      $FehlermeldungText = "FEHLER: TEMPERATURSENSOR";
      break;

    case 87:
      $FehlermeldungText = "NETZBETREIBER: LADEN VERBOTEN";
      break;

    case 88:
      $FehlermeldungText = "GRID OPERATOR: ENTLADEN VERBOTEN";
      break;

    case 89:
      $FehlermeldungText = "UNGENUTZTE KAPAZITÄT";
      break;

    case 90:
      $FehlermeldungText = "SELBSTTEST FEHLER";
      break;

    case 91:
      $FehlermeldungText = "ERDUNG FEHLERHAFT";
      break;

    case 95:
      $FehlermeldungText = "BATTERIE DIAGNOSE";
      break;

    case 96:
      $FehlermeldungText = "BALANCING";
      break;

    case 97:
      $FehlermeldungText = "SICHERHEITSENTLADUNG";
      break;

    default:
      $FehlermeldungText = "UNBEKANNT";
      break;
  }
  $aktuelleDaten["Statusmeldung"] = $FehlermeldungText;
  //  Eingefügt 10.01.2022
  $aktuelleDaten["Wallbox_Gesamtleistung"] = 0;
  $aktuelleDaten["Wallbox_Leistung"] = 0;
  $aktuelleDaten["L1_Leistung"] = 0;
  $aktuelleDaten["L2_Leistung"] = 0;
  $aktuelleDaten["L3_Leistung"] = 0;
  $aktuelleDaten["WB_Status"] = 0;
  // Es gibt 4 Wallbox Gesamtzähler. Hier wird nur der erste ausgelesen [0]
  $http_daten["Data"] = '{"STATISTIC":{"LIVE_WB_ENERGY":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Wallbox_Gesamtleistung"] = round( Utils::senec( $rc["STATISTIC"]["LIVE_WB_ENERGY"][0]) * 1000, 1 );
  $http_daten["Data"] = '{"WALLBOX":{"APPARENT_CHARGING_POWER":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["Wallbox_Leistung"] = round( Utils::senec( $rc["WALLBOX"]["APPARENT_CHARGING_POWER"][0] ), 2 );
  $http_daten["Data"] = '{"WALLBOX":{"L1_CHARGING_CURRENT":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["L1_Leistung"] = round( Utils::senec( $rc["WALLBOX"]["L1_CHARGING_CURRENT"][0] ), 2 );
  $http_daten["Data"] = '{"WALLBOX":{"L2_CHARGING_CURRENT":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["L2_Leistung"] = round( Utils::senec( $rc["WALLBOX"]["L2_CHARGING_CURRENT"][0] ), 2 );
  $http_daten["Data"] = '{"WALLBOX":{"L3_CHARGING_CURRENT":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["L3_Leistung"] = round( Utils::senec( $rc["WALLBOX"]["L3_CHARGING_CURRENT"][0] ), 2 );
  $http_daten["Data"] = '{"WALLBOX":{"STATE":""}}';
  $rc = Utils::http_read( $http_daten );
  $aktuelleDaten["WB_Status"] = round( Utils::senec( $rc["WALLBOX"]["STATE"][0] ), 2 );

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Produkt"] = "Senec";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  if ($i == 1)
    Log::write( var_export( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists($basedir."/custom/senec_math.php" )) {
    include $basedir.'/custom/senec_math.php'; // Falls etwas neu berechnet werden muss.
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
    $Zeitspanne = (8 - (time( ) - $Start));
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
if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {

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
    $Tagesdaten = array("WattstundenGesamtHeute" => 0, "BatterieGeladenHeute" => 0, "BatterieEntladenHeute" => 0);
    $rc = file_put_contents( $StatusFile, serialize( $Tagesdaten ));
    Log::write( "WattstundenGesamtHeute  gesetzt.", "o- ", 5 );
  }

  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $Tagesdaten = unserialize( file_get_contents( $StatusFile ));
  $Tagesdaten["WattstundenGesamtHeute"] = ($Tagesdaten["WattstundenGesamtHeute"] + ($aktuelleDaten["PV_Leistung"]) / 60);
  if ($aktuelleDaten["Bat_Leistung"] > 0) {
    $Tagesdaten["BatterieGeladenHeute"] = round( ($Tagesdaten["BatterieGeladenHeute"] + ($aktuelleDaten["Bat_Leistung"]) / 60), 2 );
  }
  if ($aktuelleDaten["Bat_Leistung"] < 0) {
    // Entladung auch als positive Wh Zahl
    $Tagesdaten["BatterieEntladenHeute"] = round( ($Tagesdaten["BatterieEntladenHeute"] + abs( $aktuelleDaten["Bat_Leistung"] ) / 60), 2 );
  }
  $rc = file_put_contents( $StatusFile, serialize( $Tagesdaten ));
  Log::write( "WattstundenGesamtHeute: ".round( $Tagesdaten["WattstundenGesamtHeute"], 2 ), "   ", 5 );
  Log::write( "BatterieGeladenHeute: ".round( $Tagesdaten["BatterieGeladenHeute"], 2 ), "   ", 5 );
  Log::write( "BatterieEntladenHeute: ".round( $Tagesdaten["BatterieEntladenHeute"], 2 ), "   ", 5 );
}
Ausgang:Log::write( "-----------------   Stop   senec.php   -------------------- ", "|--", 6 );
return;
?>