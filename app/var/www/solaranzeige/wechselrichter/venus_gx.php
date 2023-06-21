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
//  Es dient dem Auslesen der Venus GX, CCGX, Cerbo GX Geräten von Victron
//  über die Modbus TCP Schnittstelle
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
//
*****************************************************************************/
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$Timebase = 60000;
$Start = time( ); // Timestamp festhalten
$Unit_IDs = str_replace( " ", "", $WR_Adresse );
$UnitID = explode( ",", $Unit_IDs );
Log::write( "-------------   Start  venus_gx.php    -------------------------- ", "|--", 6 );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
//  Hardware Version ermitteln.
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

Log::write( "folgende Geräte-ID's sollen ausgelesen werden: ".$WR_Adresse, "   ", 6 );
$UID = explode( ",", $WR_Adresse );

$COM1 = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 10 ); // 10 = Timeout in Sekunden
if (!is_resource( $COM1 )) {
  Log::write( "Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
  Log::write( "Exit.... ", "XX ", 9 );
  goto Ausgang;
}
$k = 1;
do {
  Log::write( "Die Daten werden ausgelesen...", "+  ", 7 );

  /****************************************************************************
  //  Ab hier wird der Venus GX ausgelesen.
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
  // com.victronenergy.system
  // UnitID = 100
  $GeraeteAdresse = "64";
  $FunktionsCode = "03";
  $RegisterAdresse = 800; // Dezimal
  $RegisterAnzahl = "0006"; // Hex
  $DatenTyp = "Zeichenkette";
  $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    if ($Ergebnis["Befehl"] == "83") {
      Log::write( "Dieses Register gibt es nicht. [No. 800]", "   ", 5 );
      $aktuelleDaten["Unit_100"]["Firmware.Text"] = "0";
    }
    else {
      Log::write( "Seriennummer: ".strtoupper( $Ergebnis["Wert"] ), "   ", 5 );
      $aktuelleDaten["Unit_100"]["Firmware.Text"] = strtoupper( $Ergebnis["Wert"] );
    }
  }
  else {
    Log::write( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }
  for ($i = 0; $i < count( $UnitID ); $i++) {
    // UnitID = 100
    // com.victronenergy.system
    if ($UnitID[$i] == "100") {
      $GeraeteAdresse = str_pad( dechex( $UnitID[$i] ), 2, "0", STR_PAD_LEFT );
      $RegisterAdresse = 808; // Dezimal
      $RegisterAnzahl = "0010"; // Hex
      $DatenTyp = "Hex";
      $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
      $venus["PV-AC coupled on output L1"] = hexdec( substr( $Ergebnis["Wert"], 0, 4 ));
      $venus["PV-AC coupled on output L2"] = hexdec( substr( $Ergebnis["Wert"], 4, 4 ));
      $venus["PV-AC coupled on output L3"] = hexdec( substr( $Ergebnis["Wert"], 8, 4 ));
      $venus["PV-AC coupled on input L1"] = hexdec( substr( $Ergebnis["Wert"], 12, 4 ));
      $venus["PV-AC coupled on input L2"] = hexdec( substr( $Ergebnis["Wert"], 16, 4 ));
      $venus["PV-AC coupled on input L3"] = hexdec( substr( $Ergebnis["Wert"], 20, 4 ));
      $venus["PV-AC coupled on generator L1"] = hexdec( substr( $Ergebnis["Wert"], 24, 4 ));
      $venus["PV-AC coupled on generator L2"] = hexdec( substr( $Ergebnis["Wert"], 28, 4 ));
      $venus["PV-AC coupled on generator L3"] = hexdec( substr( $Ergebnis["Wert"], 32, 4 ));
      $venus["AC_Consumption_L1"] = hexdec( substr( $Ergebnis["Wert"], 36, 4 ));
      $venus["AC_Consumption_L2"] = hexdec( substr( $Ergebnis["Wert"], 40, 4 ));
      $venus["AC_Consumption_L3"] = hexdec( substr( $Ergebnis["Wert"], 44, 4 ));
      $venus["Grid_L1"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 48, 4 ));
      $venus["Grid_L2"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 52, 4 ));
      $venus["Grid_L3"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 56, 4 ));
      $venus["Active_input_source"] = substr( $Ergebnis["Wert"], 60, 4 );
      $GeraeteAdresse = "64"; // UnitID = 100
      $RegisterAdresse = 840; // Dezimal
      $RegisterAnzahl = "0007"; // Hex
      $DatenTyp = "Hex";
      $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
      $venus["Battery-Voltage"] = hexdec( substr( $Ergebnis["Wert"], 0, 4 )) / 10;
      $venus["Battery-Current"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 4, 4 )) / 10;
      $venus["Battery-Power"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 8, 4 ));
      $venus["Battery-State_of_charge"] = hexdec( substr( $Ergebnis["Wert"], 12, 4 ));
      $venus["Battery-State"] = hexdec( substr( $Ergebnis["Wert"], 16, 4 ));
      $venus["Battery-Consumed-Amphours"] = hexdec( substr( $Ergebnis["Wert"], 20, 4 )) * 10;
      $venus["Battery-Time_to_go"] = hexdec( substr( $Ergebnis["Wert"], 24, 4 ));
      $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_Leistung_R"] = $venus["Grid_L1"];
      $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_Leistung_S"] = $venus["Grid_L2"];
      $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_Leistung_T"] = $venus["Grid_L3"];
      $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Bat_Spannung"] = $venus["Battery-Voltage"];
      $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Bat_Strom"] = $venus["Battery-Current"];
      $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Bat_Leistung"] = $venus["Battery-Power"];
      $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Bat_SOC"] = $venus["Battery-State_of_charge"];
      $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Bat_Status"] = $venus["Battery-State"];
      $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Kritische_Lasten_R"] = $venus["PV-AC coupled on output L1"];
      $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Kritische_Lasten_S"] = $venus["PV-AC coupled on output L2"];
      $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Kritische_Lasten_T"] = $venus["PV-AC coupled on output L3"];
      $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_Verbrauch_R"] = $venus["AC_Consumption_L1"];
      $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_Verbrauch_S"] = $venus["AC_Consumption_L2"];
      $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_Verbrauch_T"] = $venus["AC_Consumption_L3"];
      Log::write( var_export( $venus, 1 ), "   ", 10 );
      continue;
    }
    if ($UnitID[$i] == "227" or $UnitID[$i] == "228" or $UnitID[$i] == "229" or $UnitID[$i] == "239" or $UnitID[$i] == "239") { // 12.4.2023
      // Multiplus
      // com.victronenergy.vebus
      $GeraeteAdresse = str_pad( dechex( $UnitID[$i] ), 2, "0", STR_PAD_LEFT );
      $RegisterAdresse = 3; // Dezimal 3
      $RegisterAnzahl = "0042"; // Hex 42
      $DatenTyp = "Hex";
      $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
      if ($Ergebnis["Befehl"] == "83") {
        Log::write( "Info: Geräte-ID: ".$UnitID[$i].", Speicherstelle: ".$RegisterAdresse, "   ", 1 );
      }
      else {
        $venus["Input_Voltage_Phase1"] = hexdec( substr( $Ergebnis["Wert"], 0, 4 )) / 10;
        $venus["Input_Voltage_Phase2"] = hexdec( substr( $Ergebnis["Wert"], 4, 4 )) / 10;
        $venus["Input_Voltage_Phase3"] = hexdec( substr( $Ergebnis["Wert"], 8, 4 )) / 10;
        $venus["Input_Current_Phase1"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 12, 4 )) / 10;
        $venus["Input_Current_Phase2"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 16, 4 )) / 10;
        $venus["Input_Current_Phase3"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 20, 4 )) / 10;
        $venus["Input_Frequency1"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 24, 4 )) / 100;
        $venus["Input_Frequency2"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 28, 4 )) / 100;
        $venus["Input_Frequency3"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 32, 4 )) / 100;
        $venus["Input_Power1"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 36, 4 )) * 10;
        $venus["Input_Power2"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 40, 4 )) * 10;
        $venus["Input_Power3"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 44, 4 )) * 10;
        $venus["Output_Voltage_Phase1"] = hexdec( substr( $Ergebnis["Wert"], 48, 4 )) / 10;
        $venus["Output_Voltage_Phase2"] = hexdec( substr( $Ergebnis["Wert"], 52, 4 )) / 10;
        $venus["Output_Voltage_Phase3"] = hexdec( substr( $Ergebnis["Wert"], 56, 4 )) / 10;
        $venus["Output_Current_Phase1"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 60, 4 )) / 10;
        $venus["Output_Current_Phase2"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 64, 4 )) / 10;
        $venus["Output_Current_Phase3"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 68, 4 )) / 10;
        $venus["Output_Frequency"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 72, 4 )) / 100;
        $venus["Active_input_current_limit"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 76, 4 )) / 10;
        $venus["Output_Power1"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 80, 4 )) * 10;
        $venus["Output_Power2"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 84, 4 )) * 10;
        $venus["Output_Power3"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 88, 4 )) * 10;
        $venus["Battery_Voltage"] = hexdec( substr( $Ergebnis["Wert"], 92, 4 )) / 100;
        $venus["Battery_Current"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 96, 4 )) / 10;
        $venus["Phase_Count"] = hexdec( substr( $Ergebnis["Wert"], 100, 4 ));
        $venus["Active_Input"] = hexdec( substr( $Ergebnis["Wert"], 104, 4 ));
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Anz_Phasen"] = $venus["Phase_Count"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_Spannung_R"] = $venus["Output_Voltage_Phase1"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_Spannung_S"] = $venus["Output_Voltage_Phase2"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_Spannung_T"] = $venus["Output_Voltage_Phase3"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_Strom_R"] = $venus["Output_Current_Phase1"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_Strom_S"] = $venus["Output_Current_Phase2"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_Strom_T"] = $venus["Output_Current_Phase3"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_Leistung_R"] = $venus["Output_Power1"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_Leistung_S"] = $venus["Output_Power2"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_Leistung_T"] = $venus["Output_Power3"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_IN_Spannung_R"] = $venus["Input_Voltage_Phase1"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_IN_Spannung_S"] = $venus["Input_Voltage_Phase2"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_IN_Spannung_T"] = $venus["Input_Voltage_Phase3"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_IN_Strom_R"] = $venus["Input_Current_Phase1"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_IN_Strom_S"] = $venus["Input_Current_Phase2"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_IN_Strom_T"] = $venus["Input_Current_Phase3"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_IN_Leistung_R"] = $venus["Input_Power1"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_IN_Leistung_S"] = $venus["Input_Power2"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["AC_IN_Leistung_T"] = $venus["Input_Power3"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Batt_Spannung"] = $venus["Battery_Voltage"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Batt_Strom"] = $venus["Battery_Current"];
        Log::write( var_export( $venus, 1 ), "   ", 10 );
        continue;
      }
    }
    if ($UnitID[$i] == "236" or $UnitID[$i] == "237" or $UnitID[$i] == "238" or $UnitID[$i] == "239" or $UnitID[$i] == "223" or $UnitID[$i] == "224" or $UnitID[$i] == "226" or $UnitID[$i] == "229") {
      //  Solar Ladegeräte
      //  com.victronenergy.solarcharger
      $GeraeteAdresse = str_pad( dechex( $UnitID[$i] ), 2, "0", STR_PAD_LEFT );
      $RegisterAdresse = 771; // Dezimal 3
      $RegisterAnzahl = "0015"; // Hex 42
      $DatenTyp = "Hex";
      $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
      if ($Ergebnis["Befehl"] == "83") {
        Log::write( "Info: Geräte-ID: ".$UnitID[$i].", Speicherstelle: ".$RegisterAdresse, "   ", 1 );
      }
      else {
        $venus["Battery_Voltage_"] = hexdec( substr( $Ergebnis["Wert"], 0, 4 )) / 100;
        $venus["Battery_Current_"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 4, 4 )) / 10;
        $venus["Battery_Temperature_"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 8, 4 )) / 10;
        $venus["Charger_ON_OFF"] = hexdec( substr( $Ergebnis["Wert"], 12, 4 ));
        $venus["Charger_State"] = hexdec( substr( $Ergebnis["Wert"], 16, 4 ));
        $venus["PV_Voltage"] = hexdec( substr( $Ergebnis["Wert"], 20, 4 )) / 100;
        $venus["PV_Current"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 24, 4 )) / 10;
        $venus["Equalization_pending"] = hexdec( substr( $Ergebnis["Wert"], 28, 4 ));
        $venus["Equalization_time_pending"] = hexdec( substr( $Ergebnis["Wert"], 32, 4 )) / 10;
        $venus["Yield_today"] = hexdec( substr( $Ergebnis["Wert"], 52, 4 )) / 10;
        $venus["Yield_yesterday"] = hexdec( substr( $Ergebnis["Wert"], 60, 4 )) / 10;
        $venus["Error_Code"] = hexdec( substr( $Ergebnis["Wert"], 68, 4 ));
        $venus["PV_Power"] = hexdec( substr( $Ergebnis["Wert"], 72, 4 )) / 10;
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["PV_Spannung"] = $venus["PV_Voltage"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["PV_Strom"] = $venus["PV_Current"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["PV_Energie_Heute"] = $venus["Yield_today"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["PV_Leistung"] = $venus["PV_Power"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Ladegeraet_Status"] = $venus["Charger_State"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Bat_Spannung"] = $venus["Battery_Voltage_"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Bat_Strom"] = $venus["Battery_Current_"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Bat_temperatur"] = $venus["Battery_Temperature_"];
        Log::write( var_export( $venus, 1 ), "   ", 10 );
        continue;
      }
      Log::write( var_export( $venus, 1 ), "   ", 10 );
    }
    if ($UnitID[$i] == "30" or $UnitID[$i] == "31" or $UnitID[$i] == "32" or $UnitID[$i] == "33" or $UnitID[$i] == "34") {
      // Grid - Zähler
      $GeraeteAdresse = str_pad( dechex( $UnitID[$i] ), 2, "0", STR_PAD_LEFT );
      $RegisterAdresse = 2600; // Dezimal 2600
      $RegisterAnzahl = "0022"; // Hex 42
      $DatenTyp = "Hex";
      $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
      if ($Ergebnis["Befehl"] == "83") {
        Log::write( "Info: Geräte-ID: ".$UnitID[$i].", Speicherstelle: ".$RegisterAdresse, "   ", 1 );
      }
      else {
        $venus["Grid-L1-Power"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 0, 4 ));
        $venus["Grid-L2-Power"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 4, 4 ));
        $venus["Grid-L3-Power"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 8, 4 ));
        $venus["Grid-L1-Energy_from_net"] = hexdec( substr( $Ergebnis["Wert"], 12, 4 )) * 10;
        $venus["Grid-L2-Energy_from_net"] = hexdec( substr( $Ergebnis["Wert"], 16, 4 )) * 10;
        $venus["Grid-L3-Energy_from_net"] = hexdec( substr( $Ergebnis["Wert"], 20, 4 )) * 10;
        $venus["Grid-L1-Energy_to_net"] = hexdec( substr( $Ergebnis["Wert"], 24, 4 )) * 10;
        $venus["Grid-L2-Energy_to_net"] = hexdec( substr( $Ergebnis["Wert"], 28, 4 )) * 10;
        $venus["Grid-L3-Energy_to_net"] = hexdec( substr( $Ergebnis["Wert"], 32, 4 )) * 10;
        $venus["Seriennummer"] = (substr( $Ergebnis["Wert"], 36, 28 ));
        $venus["Grid-L1-Voltage"] = hexdec( substr( $Ergebnis["Wert"], 64, 4 )) / 10;
        $venus["Grid-L1-Current"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 68, 4 )) / 10;
        $venus["Grid-L2-Voltage"] = hexdec( substr( $Ergebnis["Wert"], 72, 4 )) / 10;
        $venus["Grid-L2-Current"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 76, 4 )) / 10;
        $venus["Grid-L3-Voltage"] = hexdec( substr( $Ergebnis["Wert"], 80, 4 )) / 10;
        $venus["Grid-L3-Current"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 84, 4 )) / 10;
        $venus["Grid-L1-Energy_from_net-total"] = hexdec( substr( $Ergebnis["Wert"], 88, 8 )) * 10;
        $venus["Grid-L2-Energy_from_net-total"] = hexdec( substr( $Ergebnis["Wert"], 96, 8 )) * 10;
        $venus["Grid-L3-Energy_from_net-total"] = hexdec( substr( $Ergebnis["Wert"], 104, 8 )) * 10;
        $venus["Grid-L1-Energy_to_net-total"] = hexdec( substr( $Ergebnis["Wert"], 112, 8 )) * 10;
        $venus["Grid-L2-Energy_to_net-total"] = hexdec( substr( $Ergebnis["Wert"], 120, 8 )) * 10;
        $venus["Grid-L3-Energy_to_net-total"] = hexdec( substr( $Ergebnis["Wert"], 128, 8 )) * 10;
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Leistung_R"] = $venus["Grid-L1-Power"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Leistung_S"] = $venus["Grid-L2-Power"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Leistung_T"] = $venus["Grid-L3-Power"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Bezug_R"] = $venus["Grid-L1-Energy_from_net"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Bezug_S"] = $venus["Grid-L2-Energy_from_net"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Bezug_T"] = $venus["Grid-L3-Energy_from_net"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Einspeisung_R"] = $venus["Grid-L1-Energy_to_net"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Einspeisung_S"] = $venus["Grid-L2-Energy_to_net"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Einspeisung_T"] = $venus["Grid-L3-Energy_to_net"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Spannung_R"] = $venus["Grid-L1-Voltage"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Spannung_S"] = $venus["Grid-L2-Voltage"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Spannung_T"] = $venus["Grid-L3-Voltage"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Strom_R"] = $venus["Grid-L1-Current"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Strom_S"] = $venus["Grid-L2-Current"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Strom_T"] = $venus["Grid-L3-Current"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Leistung"] = ($venus["Grid-L1-Power"] + $venus["Grid-L2-Power"] + $venus["Grid-L3-Power"]);
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Bezug"] = ($venus["Grid-L1-Energy_from_net"] + $venus["Grid-L2-Energy_from_net"] + $venus["Grid-L3-Energy_from_net"]);
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Netz_Einspeisung"] = ($venus["Grid-L1-Energy_to_net"] + $venus["Grid-L2-Energy_to_net"] + $venus["Grid-L3-Energy_to_net"]);
        Log::write( var_export( $venus, 1 ), "   ", 10 );
        continue;
      }
    }
    if ($UnitID[$i] == "225" or $UnitID[$i] == "1" or $UnitID[$i] == "2" or $UnitID[$i] == "238") {
      // Batterie
      $GeraeteAdresse = str_pad( dechex( $UnitID[$i] ), 2, "0", STR_PAD_LEFT );
      $RegisterAdresse = 259; // Dezimal 1282
      $RegisterAnzahl = "0012"; // Hex 42
      $DatenTyp = "Hex";
      $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
      if ($Ergebnis["Befehl"] == "83") {
        Log::write( "Info: Geräte-ID: ".$UnitID[$i].", Speicherstelle: ".$RegisterAdresse, "   ", 1 );
      }
      else {
        $venus["Battery_Voltage*"] = hexdec( substr( $Ergebnis["Wert"], 0, 4 )) / 100;
        $venus["Starterbattery_Voltage"] = hexdec( substr( $Ergebnis["Wert"], 4, 4 )) / 100;
        $venus["Battery_Current*"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 8, 4 )) / 10;
        $venus["Battery_Temperature*"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 12, 4 )) / 10;
        //16
        //20
        //24
        $venus["State_of_charge*"] = hexdec( substr( $Ergebnis["Wert"], 28, 4 )) / 10;
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Bat_Spannung"] = $venus["Battery_Voltage*"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Bat_Strom"] = $venus["Battery_Current*"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Bat_Temperatur"] = $venus["Battery_Temperature*"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Bat_SOC"] = $venus["State_of_charge*"];
        $RegisterAdresse = 300; // Dezimal 1282
        $RegisterAnzahl = "0012"; // Hex 42
        $DatenTyp = "Hex";
        $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
        // 0
        // 4
        // 8
        // 12
        $venus["State_of_health"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 16, 4 )) / 10;
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Bat_SOH"] = $venus["State_of_health"];
        $RegisterAdresse = 1282; // Dezimal 1282
        $RegisterAnzahl = "0012"; // Hex 42
        $DatenTyp = "Hex";
        $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
        $venus["State"] = hexdec( substr( $Ergebnis["Wert"], 0, 4 ));
        $venus["Error"] = hexdec( substr( $Ergebnis["Wert"], 4, 4 ));
        $venus["System_switch"] = hexdec( substr( $Ergebnis["Wert"], 8, 4 ));
        $venus["Balancing"] = hexdec( substr( $Ergebnis["Wert"], 12, 4 ));
        $venus["Number_of_batteries"] = hexdec( substr( $Ergebnis["Wert"], 16, 4 ));
        $venus["Batteries_parallel"] = hexdec( substr( $Ergebnis["Wert"], 20, 4 ));
        $venus["Batteries_series"] = hexdec( substr( $Ergebnis["Wert"], 24, 4 ));
        $venus["Number_of_cells_per_battery"] = hexdec( substr( $Ergebnis["Wert"], 28, 4 ));
        $venus["Minimum_cell_voltage"] = hexdec( substr( $Ergebnis["Wert"], 32, 4 )) / 100;
        $venus["Maximum_cell_voltage"] = hexdec( substr( $Ergebnis["Wert"], 36, 4 )) / 100;
        $venus["Shutdowns_with_error"] = hexdec( substr( $Ergebnis["Wert"], 40, 4 ));
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Anz_Zellen_pro_Batterie"] = $venus["Number_of_cells_per_battery"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Balancing"] = $venus["Balancing"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Max_Zellen_Spannung"] = $venus["Maximum_cell_voltage"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Min_Zellen_Spannung"] = $venus["Minimum_cell_voltage"];
        Log::write( var_export( $Ergebnis, 1 ), "   ", 10 );
        Log::write( var_export( $venus, 1 ), "   ", 10 );
        continue;
      }
    }
    if ($UnitID[$i] == "29") {
      //  Temperaturfühler
      //  com.victronenergy.temperatur
      $GeraeteAdresse = str_pad( dechex( $UnitID[$i] ), 2, "0", STR_PAD_LEFT );
      $RegisterAdresse = 3300; // Dezimal 3300
      $RegisterAnzahl = "0009"; // Hex 9
      $DatenTyp = "Hex";
      $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
      if ($Ergebnis["Befehl"] == "83") {
        Log::write( "Info: Geräte-ID: ".$UnitID[$i].", Speicherstelle: ".$RegisterAdresse, "   ", 1 );
      }
      else {
        $venus["ProductID"] = hexdec( substr( $Ergebnis["Wert"], 0, 4 ));
        $venus["Temperature_Scale_Factor"] = hexdec( substr( $Ergebnis["Wert"], 4, 4 ));
        $venus["Temperature_Offset"] = hexdec( substr( $Ergebnis["Wert"], 8, 4 ));
        $venus["Temperature_Type"] = hexdec( substr( $Ergebnis["Wert"], 12, 4 ));
        $venus["Temperature"] = hexdec( substr( $Ergebnis["Wert"], 16, 4 )) / 100;
        $venus["Temperature_Status"] = hexdec( substr( $Ergebnis["Wert"], 20, 4 ));
        $venus["Humidity"] = hexdec( substr( $Ergebnis["Wert"], 24, 4 ));
        $venus["Sensor_battery_voltage"] = hexdec( substr( $Ergebnis["Wert"], 28, 4 ));
        $venus["Atmospheric_pressure"] = hexdec( substr( $Ergebnis["Wert"], 32, 4 )) ;


        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Temperatur_Typ"] = $venus["Temperature_Type"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Temperatur"] = $venus["Temperature"];

        Log::write( var_export( $Ergebnis, 1 ), "   ", 10 );
        Log::write( var_export( $venus, 1 ), "   ", 10 );
        continue;
      }
    }
    if ($UnitID[$i] == "32") {
      //  Zähler
      //  com.victronenergy.pvinverter
      $GeraeteAdresse = str_pad( dechex( $UnitID[$i] ), 2, "0", STR_PAD_LEFT );
      $RegisterAdresse = 1026; // Dezimal 1026
      $RegisterAnzahl = "0019"; // Hex 9
      $DatenTyp = "Hex";
      $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
      if ($Ergebnis["Befehl"] == "83") {
        Log::write( "Info: Geräte-ID: ".$UnitID[$i].", Speicherstelle: ".$RegisterAdresse, "   ", 1 );
      }
      else {
        $venus["Position"] = hexdec( substr( $Ergebnis["Wert"], 0, 4 ));
        $venus["L1 Voltage"] = hexdec( substr( $Ergebnis["Wert"], 4, 4 ))/ 10;
        $venus["L1 Current"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 8, 4 ))/ 10;
        $venus["L1 Power"] = hexdec( substr( $Ergebnis["Wert"], 12, 4 ));
        $venus["L1 Energy"] = hexdec( substr( $Ergebnis["Wert"], 16, 4 ))*10;
        $venus["L2 Voltage"] = hexdec( substr( $Ergebnis["Wert"], 20, 4 ))/10;
        $venus["L2 Current"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 24, 4 ))/10;
        $venus["L2 Power"] = hexdec( substr( $Ergebnis["Wert"], 28, 4 ));
        $venus["L2 Energy"] = hexdec( substr( $Ergebnis["Wert"], 32, 4 ))*10;
        $venus["L3 Voltage"] = hexdec( substr( $Ergebnis["Wert"], 36, 4 ))/10;
        $venus["L3 Current"] = Utils::hexdecs( substr( $Ergebnis["Wert"], 40, 4 ))/10;
        $venus["L3 Power"] = hexdec( substr( $Ergebnis["Wert"], 44, 4 ));
        $venus["L3 Energy"] = hexdec( substr( $Ergebnis["Wert"], 48, 4 ))*10;
        $venus["Serial"] = (substr( $Ergebnis["Wert"], 52, 28 ));

        $venus["L1 EnergyTotal"] = hexdec( substr( $Ergebnis["Wert"], 80, 8 )) * 10;
        $venus["L2 EnergyTotal"] = hexdec( substr( $Ergebnis["Wert"], 88, 8 )) * 10;
        $venus["L3 EnergyTotal"] = hexdec( substr( $Ergebnis["Wert"], 96, 8 )) * 10;

        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Spannung_R"] = $venus["L1 Voltage"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Strom_R"] = $venus["L1 Current"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Leistung_R"] = $venus["L1 Power"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Energie_R"] = $venus["L1 Energy"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Spannung_S"] = $venus["L2 Voltage"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Strom_S"] = $venus["L2 Current"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Leistung_S"] = $venus["L2 Power"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Energie_S"] = $venus["L2 Energy"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Spannung_T"] = $venus["L3 Voltage"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Strom_T"] = $venus["L3 Current"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Leistung_T"] = $venus["L3 Power"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Energie_T"] = $venus["L3 Energy"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["Seriennummer.Text"] = hex2bin(trim($venus["Serial"],"00"));
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["EnergieTotal_R"] = $venus["L1 EnergyTotal"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["EnergieTotal_S"] = $venus["L2 EnergyTotal"];
        $aktuelleDaten["Unit_".trim( $UnitID[$i] )]["EnergieTotal_T"] = $venus["L3 EnergyTotal"];


        Log::write( var_export( $Ergebnis, 1 ), "   ", 10 );
        Log::write( var_export( $venus, 1 ), "   ", 10 );
        continue;
      }
    }
    else {
      if (isset($UnitID[$i])) {
        Log::write( "eMail an: support@solaranzeige.de  Fehlende Geräte-ID ist: ".$UnitID[$i], "ERR", 5 );
        Log::write( "Die Geräte-ID ist noch nicht implementiert. Bitte melden Sie sich:", "ERR", 5 );
      }
    }
  }

  /***************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/
  //print_r( $venus );
  //print_r( $aktuelleDaten);
  // exit;

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
  $aktuelleDaten["Info"]["Objekt.Text"] = $Objekt;
  $aktuelleDaten["Info"]["Produkt.Text"] = "Cerbo GX";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);

  Log::write( var_export( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists($basedir."/custom/venus_gx_math.php" )) {
    include $basedir.'/custom/venus_gx_math.php'; // Falls etwas neu berechnet werden muss.
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
    $Zeitspanne = (7 - (time( ) - $Start));
    Log::write( "Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2 );
    if ($Zeitspanne > 0) {
      sleep( $Zeitspanne );
    }
    break;
  }
  else {
    Log::write( "Schleife: ".($k)." Zeitspanne: ".(floor( (56 - (time( ) - $Start)))), "   ", 9 );
    sleep( floor( (56 - (time( ) - $Start))));
  }
  if ($Wiederholungen <= $k or $k >= 1) {
    //  Die RCT Wechselrichter dürfen nur einmal pro Minute ausgelesen werden!
    Log::write( "Schleife ".$k." Ausgang...", "   ", 5 );
    break;
  }
  $k++;
} while (($Start + 54) > time( ));

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

/*******/
Ausgang:

/*******/
fclose( $COM1 );
Log::write( "-------------   Stop   venus_gx.php    -------------------------- ", "|--", 6 );
return;
?>

