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
//  Es dient dem Auslesen des Fronius Symo Wechselrichters über die LAN Schnittstelle
//  Port = 80
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$DatenOK = true;
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$Start = time( ); // Timestamp festhalten
Log::write( "-------------   Start  fronius_symo_serie.php    --------------- ", "|--", 6 );
setlocale( LC_TIME, "de_DE.utf8" );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
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
Log::write( "Hardware Version: ".$Version, "o  ", 9 );
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
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//
*****************************************************************************/
$StatusFile = $basedir."/database/".$GeraeteNummer.".WhProTag_fronius.txt";
if (file_exists( $StatusFile )) {

  /***************************************************************************
  //  Daten einlesen ...
  ***************************************************************************/
  $aktuelleDaten["WattstundenGesamtHeute"] = file_get_contents( $StatusFile );
  $aktuelleDaten["WattstundenGesamtHeute"] = round( $aktuelleDaten["WattstundenGesamtHeute"], 2 );
  Log::write( "WattstundenGesamtHeute: ".$aktuelleDaten["WattstundenGesamtHeute"], "   ", 8 );
  if (empty($aktuelleDaten["WattstundenGesamtHeute"])) {
    $aktuelleDaten["WattstundenGesamtHeute"] = 0;
  }
  if (date( "H:i" ) == "00:00" or date( "H:i" ) == "00:01") { // Jede Nacht 0 Uhr
    $aktuelleDaten["WattstundenGesamtHeute"] = 0; //  Tageszähler löschen
    $rc = file_put_contents( $StatusFile, "0" );
    Log::write( "WattstundenGesamtHeute gelöscht.", "    ", 5 );
  }
}
else {
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;

  /***************************************************************************
  //  Inhalt der Status Datei anlegen.
  ***************************************************************************/
  $rc = file_put_contents( $StatusFile, "0" );
  if ($rc === false) {
    Log::write( "Konnte die Datei kwhProTag_ax.txt nicht anlegen.", "   ", 5 );
  }
}
if (Utils::tageslicht( ) or $InfluxDaylight === false) {
  //  Der Wechselrichter wird nur am Tage abgefragt.
  //  Er hat einen direkten LAN Anschluß
  $COM1 = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 5 );
  if (!is_resource( $COM1 )) {
    Log::write( "Kein Kontakt zum Wechselrichter ".$WR_IP.",  Port: ".$WR_Port.",  Fehlermeldung: ".$errstr, "XX ", 3 );
    Log::write( "Exit.... ", "XX ", 3 );
    goto Ausgang;
  }
}
else {
  Log::write( "Es ist dunkel... ", "X  ", 7 );
  goto Ausgang;
}

/***************************************************************************
//  Einen Befehl an den Wechselrichter senden.
//
//
//
//
***************************************************************************/

/***************************************************************************
//  ENDE  BEFEHL SENDEN       ENDE  BEFEHL SENDEN       ENDE  BEFEHL SENDEN
***************************************************************************/
$i = 1;
do {
  Log::write( "Die Daten werden ausgelesen...", "+  ", 9 );

  /****************************************************************************
  //  Ab hier wird der Wechselrichter ausgelesen.
  //
  //  Ergebniswerte:
  //  $aktuelleDaten["Firmware"]                Nummer
  //  $aktuelleDaten["Produkt"]                 Text
  //  $aktuelleDaten["Objekt"]                  Text
  //  $aktuelleDaten["Batteriestrom"]
  //  $aktuelleDaten["Batteriespannung"]
  //  $aktuelleDaten["AC_Ausgangsstrom"]
  //  $aktuelleDaten["AC_Ausgangsstrom_R"]
  //  $aktuelleDaten["AC_Ausgangsstrom_S"]
  //  $aktuelleDaten["AC_Ausgangsstrom_T"]
  //  $aktuelleDaten["AC_Ausgangsspannung"]
  //  $aktuelleDaten["AC_Ausgangsspannung_R"]
  //  $aktuelleDaten["AC_Ausgangsspannung_S"]
  //  $aktuelleDaten["AC_Ausgangsspannung_T"]
  //  $aktuelleDaten["AC_Wirkleistung"]
  //  $aktuelleDaten["AC_Wirkleistung_R"]
  //  $aktuelleDaten["AC_Wirkleistung_S"]
  //  $aktuelleDaten["AC_Wirkleistung_T"]
  //  $aktuelleDaten["AC_Ausgangsfrequenz"]
  //  $aktuelleDaten["WattstundenGesamt"]
  //  $aktuelleDaten["WattstundenGesamtHeute"]
  //  $aktuelleDaten["WattstundenGesamtJahr"]
  //  $aktuelleDaten["ErrorCodes"]
  //
  //  EnergyReal_WAC_Minus_Absolute = Einspeisung
  //  EnergyReal_WAC_Plus_Absolute = Netzbezug
  //  E_Total = Solarproduktion
  //  Solarproduktion - Einspeisung = Direktverbrauch
  //  Netzbezug + Direktverbrauch = Hausverbrauch
  //
  //
  //  "1.6-3"
  //  "1.5-18"
  //  "1.5-13"
  //  "1.5-16"
  //  "1.7-4" = GEN24
  //  "1.7-7" = GEN24
  //  "1.8-1"
  ****************************************************************************/
  $rc = Utils::read( $WR_IP, $WR_Port, "solar_api/GetAPIVersion.cgi" );
  // API Version prüfen. Es muss API Version 1 ein.
  if ($rc["APIVersion"] != 1) {
    Log::write( "Ist die API freigeschaltet? Falsche API Version,", "   ", 3 );
    Log::write( "oder keine Verbindung zum Wechselrichter.".print_r( $rc, 1 ), "   ", 3 );
    Log::write( "Exit.... ", "XX ", 3 );
    exit;
  }
  switch ($rc["CompatibilityRange"]) {

    case "1.5-13":
    case "1.5-16":
    case "1.5-18":
    case "1.6-3":
    case "1.7-4":
    case "1.7-7":
    case "1.8-1":
    case "1.6-3":
      Log::write( "API Version: ".$rc["APIVersion"]." CompatibilityRange: ".$rc["CompatibilityRange"], "   ", 2 );
      break;

    default:
      Log::write( "API Version: ".$rc["APIVersion"]." CompatibilityRange noch unbekannt: ".$rc["CompatibilityRange"], "   ", 2 );
      break;
  }
  $aktuelleDaten["Firmware"] = $rc["APIVersion"];
  $aktuelleDaten["AC_Ausgangsfrequenz"] = 0;
  $aktuelleDaten["Solarspannung_String_2"] = 0;
  $aktuelleDaten["Solarstrom_String_2"] = 0;
  $aktuelleDaten["AC_Wirkleistung"] = 0;
  $aktuelleDaten["AC_Ausgangsstrom"] = 0;
  $aktuelleDaten["AC_Ausgangsspannung"] = 0;
  $aktuelleDaten["AC_Ausgangsfrequenz"] = 0;
  $aktuelleDaten["Solarstrom"] = 0;
  $aktuelleDaten["Solarspannung"] = 0;
  $aktuelleDaten["Solarspannung_String_1"] = 0;
  $aktuelleDaten["Solarstrom_String_1"] = 0;
  $aktuelleDaten["Solarspannung_String_2"] = 0;
  $aktuelleDaten["Solarstrom_String_2"] = 0;
  $aktuelleDaten["Geraetestatus"] = 0;
  $aktuelleDaten["ErrorCodes"] = 0;
  $aktuelleDaten["Solarleistung_String_1"] = 0;
  $aktuelleDaten["Solarleistung_String_2"] = 0;
  $aktuelleDaten["Temperatur"] = 0;
  if ($rc["CompatibilityRange"] == "1.6-3" or $rc["CompatibilityRange"] == "1.5-18" or $rc["CompatibilityRange"] == "1.5-16" or $rc["CompatibilityRange"] == "1.5-13" or $rc["CompatibilityRange"] == "1.8-1") {
    $URL = "solar_api/v1/GetArchiveData.cgi";
    $URL .= "?Scope=System";
    $URL .= "&StartDate=".date( DATE_RFC3339, time( ) - 400 );
    $URL .= "&EndDate=".date( DATE_RFC3339 );
    $URL .= "&Channel=Voltage_DC_String_1";
    $URL .= "&Channel=Voltage_DC_String_2";
    $URL .= "&Channel=Current_DC_String_1";
    $URL .= "&Channel=Current_DC_String_2";
    $URL .= "&Channel=Temperature_Powerstage";
    $JSON_Daten = Utils::read( $WR_IP, $WR_Port, $URL );
    if (isset($JSON_Daten["Head"]["Status"]["Code"]) and $JSON_Daten["Head"]["Status"]["Code"] == 0 and !empty($JSON_Daten["Body"]["Data"])) {  // Neu 17.3.2023
      // Es handelt sich um gültige Daten
      $Teile = explode( "/", key( $JSON_Daten["Body"]["Data"] ));
      if ($WR_Adresse != $Teile[1]) {
        Log::write( "Wechselrichter Adresse ist nicht stimmig: ".print_r($Teile,1), "   ", 5 );
      }
      Log::write( print_r( $JSON_Daten, 1 ), "   ", 10 );
      $aktuelleDaten["Solarspannung_String_1"] = end( $JSON_Daten["Body"]["Data"]["inverter/".$WR_Adresse]["Data"]["Voltage_DC_String_1"]["Values"] );
      $aktuelleDaten["Solarstrom_String_1"] = end( $JSON_Daten["Body"]["Data"]["inverter/".$WR_Adresse]["Data"]["Current_DC_String_1"]["Values"] );
      $aktuelleDaten["Solarleistung_String_1"] = $aktuelleDaten["Solarspannung_String_1"] * $aktuelleDaten["Solarstrom_String_1"];
      if (isset($JSON_Daten["Body"]["Data"]["inverter/".$WR_Adresse]["Data"]["Voltage_DC_String_2"])) {
        $aktuelleDaten["Solarspannung_String_2"] = end( $JSON_Daten["Body"]["Data"]["inverter/".$WR_Adresse]["Data"]["Voltage_DC_String_2"]["Values"] );
        $aktuelleDaten["Solarstrom_String_2"] = end( $JSON_Daten["Body"]["Data"]["inverter/".$WR_Adresse]["Data"]["Current_DC_String_2"]["Values"] );
        $aktuelleDaten["Solarleistung_String_2"] = $aktuelleDaten["Solarspannung_String_2"] * $aktuelleDaten["Solarstrom_String_2"];
      }
      $aktuelleDaten["Temperatur"] = 0;
      if (is_array( $JSON_Daten["Body"]["Data"]["inverter/".$WR_Adresse]["Data"]["Temperature_Powerstage"]["Values"] )) {
        $aktuelleDaten["Temperatur"] = end( $JSON_Daten["Body"]["Data"]["inverter/".$WR_Adresse]["Data"]["Temperature_Powerstage"]["Values"] );
      }
    }
    $aktuelleDaten["Gen24"] = false;
  }
  else {
    $aktuelleDaten["Gen24"] = true;
    $aktuelleDaten["Temperatur"] = 0;
    Log::write( "Modell = Fronius GEN 24", "   ", 3 );
  }
  $URL = "solar_api/v1/GetInverterRealtimeData.cgi";
  $URL .= "?Scope=Device";
  $URL .= "&DataCollection=CumulationInverterData";
  $URL .= "&DeviceId=".$WR_Adresse;
  $JSON_Daten = Utils::read( $WR_IP, $WR_Port, $URL );
  Log::write( "URL: ".$URL, "   ", 8 );
  if (isset($JSON_Daten["Head"]["Status"]["Code"]) and $JSON_Daten["Head"]["Status"]["Code"] == 0 and $aktuelleDaten["Gen24"] == false) {
    // Es handelt sich um gültige Daten
    Log::write( print_r( $JSON_Daten, 1 ), "   ", 10 );
    $aktuelleDaten["Geraetestatus"] = $JSON_Daten["Body"]["Data"]["DeviceStatus"]["StatusCode"];
    $aktuelleDaten["WattstundenGesamtHeute"] = $JSON_Daten["Body"]["Data"]["DAY_ENERGY"]["Value"];
    $aktuelleDaten["WattstundenGesamtJahr"] = $JSON_Daten["Body"]["Data"]["YEAR_ENERGY"]["Value"];
    $aktuelleDaten["WattstundenGesamt"] = $JSON_Daten["Body"]["Data"]["TOTAL_ENERGY"]["Value"];
    $aktuelleDaten["ErrorCodes"] = $JSON_Daten["Body"]["Data"]["DeviceStatus"]["ErrorCode"];
    if (isset($JSON_Daten["Body"]["Data"]["PAC"]["Value"])) {
      $aktuelleDaten["AC_Wirkleistung"] = $JSON_Daten["Body"]["Data"]["PAC"]["Value"];
    }
  }
  else {
    $aktuelleDaten["WattstundenGesamtJahr"] = 0;
    $aktuelleDaten["WattstundenGesamt"] = 0;
    $aktuelleDaten["ErrorCodes"] = 0;
    $aktuelleDaten["Geraetestatus"] = 0;
  }
  $URL = "solar_api/v1/GetInverterRealtimeData.cgi";
  $URL .= "?Scope=Device";
  $URL .= "&DataCollection=CommonInverterData";
  $URL .= "&DeviceId=".$WR_Adresse;
  $JSON_Daten = Utils::read( $WR_IP, $WR_Port, $URL );
  Log::write( "URL: ".$URL, "   ", 8 );
  if (isset($JSON_Daten["Head"]["Status"]["Code"]) and $JSON_Daten["Head"]["Status"]["Code"] == 0) {
    // Es handelt sich um gültige Daten
    Log::write( "GetInverterRealtimeData ".print_r( $JSON_Daten, 1 ), "   ", 10 );
    if (isset($JSON_Daten["Body"]["Data"]["FAC"]["Value"])) {
      $aktuelleDaten["AC_Ausgangsfrequenz"] = $JSON_Daten["Body"]["Data"]["FAC"]["Value"];
      $aktuelleDaten["AC_Wirkleistung"] = $JSON_Daten["Body"]["Data"]["PAC"]["Value"];
      $aktuelleDaten["AC_Ausgangsstrom"] = $JSON_Daten["Body"]["Data"]["IAC"]["Value"];
      $aktuelleDaten["AC_Ausgangsspannung"] = $JSON_Daten["Body"]["Data"]["UAC"]["Value"];
      $aktuelleDaten["Solarstrom"] = $JSON_Daten["Body"]["Data"]["IDC"]["Value"];
      $aktuelleDaten["Solarspannung"] = $JSON_Daten["Body"]["Data"]["UDC"]["Value"];
      if ($aktuelleDaten["Gen24"] == true) {
        if (isset($JSON_Daten["Body"]["Data"]["UDC"]["Value"])) {
          $aktuelleDaten["Solarspannung_String_1"] = $JSON_Daten["Body"]["Data"]["UDC"]["Value"];
          $aktuelleDaten["Solarstrom_String_1"] = $JSON_Daten["Body"]["Data"]["IDC"]["Value"];
          $aktuelleDaten["Solarleistung_String_1"] = $aktuelleDaten["Solarspannung_String_1"] * $aktuelleDaten["Solarstrom_String_1"];
        }
        elseif (isset($JSON_Daten["Body"]["Data"]["UDC_1"]["Value"])) {
          $aktuelleDaten["Solarspannung_String_1"] = $JSON_Daten["Body"]["Data"]["UDC_1"]["Value"];
          $aktuelleDaten["Solarstrom_String_1"] = $JSON_Daten["Body"]["Data"]["IDC_1"]["Value"];
          $aktuelleDaten["Solarleistung_String_1"] = $aktuelleDaten["Solarspannung_String_1"] * $aktuelleDaten["Solarstrom_String_1"];
        }
        if (isset($JSON_Daten["Body"]["Data"]["UDC_2"]["Value"])) {
          $aktuelleDaten["Solarspannung_String_2"] = $JSON_Daten["Body"]["Data"]["UDC_2"]["Value"];
          $aktuelleDaten["Solarstrom_String_2"] = $JSON_Daten["Body"]["Data"]["IDC_2"]["Value"];
          $aktuelleDaten["Solarleistung_String_2"] = $aktuelleDaten["Solarspannung_String_2"] * $aktuelleDaten["Solarstrom_String_2"];
        }
        if (isset($JSON_Daten["Body"]["Data"]["UDC_3"]["Value"])) {
          $aktuelleDaten["Solarspannung_String_3"] = $JSON_Daten["Body"]["Data"]["UDC_3"]["Value"];
          $aktuelleDaten["Solarstrom_String_3"] = $JSON_Daten["Body"]["Data"]["IDC_3"]["Value"];
          $aktuelleDaten["Solarleistung_String_3"] = $aktuelleDaten["Solarspannung_String_3"] * $aktuelleDaten["Solarstrom_String_3"];
        }
      }
    }
    if ($aktuelleDaten["Gen24"] == false) {
      $aktuelleDaten["Geraetestatus"] = $JSON_Daten["Body"]["Data"]["DeviceStatus"]["StatusCode"];
      $aktuelleDaten["ErrorCodes"] = $JSON_Daten["Body"]["Data"]["DeviceStatus"]["ErrorCode"];
    }
  }
  else {
    break;
  }
  $URL = "solar_api/v1/GetInverterInfo.cgi";
  $JSON_Daten = Utils::read( $WR_IP, $WR_Port, $URL );
  if (isset($JSON_Daten["Head"]["Status"]["Code"]) and $JSON_Daten["Head"]["Status"]["Code"] == 0) {
    // Es handelt sich um gültige Daten
    Log::write( print_r( $JSON_Daten, 1 ), "   ", 10 );
    $aktuelleDaten["ModulPVLeistung"] = $JSON_Daten["Body"]["Data"][$WR_Adresse]["PVPower"];
    $aktuelleDaten["Gen24Status"] = $JSON_Daten["Body"]["Data"][$WR_Adresse]["StatusCode"];
  }
  else {
    break;
  }
  $URL = "solar_api/v1/GetActiveDeviceInfo.cgi";
  $URL .= "?DeviceClass=System";
  $JSON_Daten = Utils::read( $WR_IP, $WR_Port, $URL );
  // Log::write(print_r($JSON_Daten,1),"   ",1);
  if (isset($JSON_Daten["Head"]["Status"]["Code"]) and $JSON_Daten["Head"]["Status"]["Code"] == 0) {
    // Es handelt sich um gültige Daten
    Log::write( print_r( $JSON_Daten, 1 ), "   ", 10 );
    $aktuelleDaten["Meter"] = count( $JSON_Daten["Body"]["Data"]["Meter"] );
    if (isset($JSON_Daten["Body"]["Data"]["Ohmpilot"])) {
      $aktuelleDaten["Ohmpilot"] = count( $JSON_Daten["Body"]["Data"]["Ohmpilot"] );
    }
    else {
      $aktuelleDaten["Ohmpilot"] = count( $JSON_Daten["Body"]["Data"]["OhmPilot"] );
    }
    $aktuelleDaten["Storage"] = count( $JSON_Daten["Body"]["Data"]["Storage"] );
    if ($aktuelleDaten["Gen24"] == false) {
      $aktuelleDaten["SensorCard"] = count( $JSON_Daten["Body"]["Data"]["SensorCard"] );
      $aktuelleDaten["StringControl"] = count( $JSON_Daten["Body"]["Data"]["StringControl"] );
      $aktuelleDaten["Inverter"] = count( $JSON_Daten["Body"]["Data"]["Inverter"] );
      $aktuelleDaten["InverterID"] = $JSON_Daten["Body"]["Data"]["Inverter"][$WR_Adresse]["DT"];
    }
    else {
      $aktuelleDaten["SensorCard"] = 0;
      $aktuelleDaten["StringControl"] = 0;
      $aktuelleDaten["Inverter"] = 0;
      $aktuelleDaten["InverterID"] = 0;
    }
  }
  else {
    break;
  }
  $URL = "solar_api/v1/GetPowerFlowRealtimeData.fcgi";
  $JSON_Daten = Utils::read( $WR_IP, $WR_Port, $URL );
  Log::write( print_r( $JSON_Daten, 1 ), "   ", 10 );
  if (isset($JSON_Daten["Head"]["Status"]["Code"]) and $JSON_Daten["Head"]["Status"]["Code"] == 0) {
    // Es handelt sich um gültige Daten
    Log::write( print_r( $JSON_Daten, 1 ), "   ", 10 );
    $aktuelleDaten["SummeWattstundenGesamtHeute"] = $JSON_Daten["Body"]["Data"]["Site"]["E_Day"];
    $aktuelleDaten["SummeWattstundenGesamtJahr"] = $JSON_Daten["Body"]["Data"]["Site"]["E_Year"];
    $aktuelleDaten["SummeWattstundenGesamt"] = $JSON_Daten["Body"]["Data"]["Site"]["E_Total"];
    if (isset($JSON_Daten["Body"]["Data"]["Site"]["Meter_Location"])) {
      $aktuelleDaten["Meter_Location"] = $JSON_Daten["Body"]["Data"]["Site"]["Meter_Location"];
    }
    else {
      $aktuelleDaten["Meter_Location"] = $JSON_Daten["Body"]["Data"]["Site"]["Meter_Location_Current"];
    }
    $aktuelleDaten["Mode"] = $JSON_Daten["Body"]["Data"]["Site"]["Mode"];
    $aktuelleDaten["SummePowerGrid"] = $JSON_Daten["Body"]["Data"]["Site"]["P_Grid"];
    $aktuelleDaten["SummePowerLoad"] = $JSON_Daten["Body"]["Data"]["Site"]["P_Load"];
    $aktuelleDaten["SummePowerAkku"] = $JSON_Daten["Body"]["Data"]["Site"]["P_Akku"];
    $aktuelleDaten["SummePowerPV"] = $JSON_Daten["Body"]["Data"]["Site"]["P_PV"];
    $aktuelleDaten["Rel_Autonomy"] = $JSON_Daten["Body"]["Data"]["Site"]["rel_Autonomy"];
    $aktuelleDaten["Rel_SelfConsumption"] = $JSON_Daten["Body"]["Data"]["Site"]["rel_SelfConsumption"];
    if (isset($JSON_Daten["Body"]["Data"]["Inverters"]["1"]["SOC"])) {
      $aktuelleDaten["Akkustand_SOC"] = $JSON_Daten["Body"]["Data"]["Inverters"]["1"]["SOC"];
    }
    else {
      $aktuelleDaten["Akkustand_SOC"] = 0;
    }
    if ($aktuelleDaten["WattstundenGesamtHeute"] == 0 and $aktuelleDaten["WattstundenGesamtJahr"] == 0) {
      $aktuelleDaten["WattstundenGesamtHeute"] = $aktuelleDaten["SummeWattstundenGesamtHeute"];
      $aktuelleDaten["WattstundenGesamtJahr"] = $aktuelleDaten["SummeWattstundenGesamtJahr"];
      $aktuelleDaten["WattstundenGesamt"] = $aktuelleDaten["SummeWattstundenGesamt"];
    }
  }
  else {
    break;
  }
  if ($aktuelleDaten["Meter"] > 0) {
    $URL = "solar_api/v1/GetMeterRealtimeData.cgi";
    $URL .= "?Scope=System";
    $JSON_Daten = Utils::read( $WR_IP, $WR_Port, $URL );
    Log::write( print_r( $JSON_Daten, 1 ), "   ", 10 );
    if (isset($JSON_Daten["Head"]["Status"]["Code"]) and $JSON_Daten["Head"]["Status"]["Code"] == 0) {
      // Es handelt sich um gültige Daten
      Log::write( print_r( $JSON_Daten, 1 ), "   ", 10 );
      if (isset($JSON_Daten["Body"]["Data"]["0"])) { // Channel 0
        if ($aktuelleDaten["Gen24"] == true and isset($JSON_Daten["Body"]["Data"]["0"]["SMARTMETER_POWERACTIVE_MEAN_SUM_F64"])) {
          $aktuelleDaten["Meter1_Wirkleistung"] = $JSON_Daten["Body"]["Data"]["0"]["SMARTMETER_POWERACTIVE_MEAN_SUM_F64"];
          $aktuelleDaten["Meter1_Blindleistung"] = $JSON_Daten["Body"]["Data"]["0"]["SMARTMETER_POWERREACTIVE_MEAN_SUM_F64"];
          $aktuelleDaten["Meter1_Scheinleistung"] = $JSON_Daten["Body"]["Data"]["0"]["SMARTMETER_POWERAPPARENT_MEAN_SUM_F64"];
          $aktuelleDaten["Meter1_EnergieProduziert"] = $JSON_Daten["Body"]["Data"]["0"]["SMARTMETER_ENERGYACTIVE_PRODUCED_SUM_F64"];
          $aktuelleDaten["Meter1_EnergieVerbraucht"] = $JSON_Daten["Body"]["Data"]["0"]["SMARTMETER_ENERGYACTIVE_CONSUMED_SUM_F64"];
          $aktuelleDaten["WattstundenGesamt"] = $JSON_Daten["Body"]["Data"]["0"]["SMARTMETER_ENERGYACTIVE_PRODUCED_SUM_F64"];
        }
        else {
          $aktuelleDaten["Meter1_Wirkleistung"] = $JSON_Daten["Body"]["Data"]["0"]["PowerReal_P_Sum"];
          if (isset($JSON_Daten["Body"]["Data"]["0"]["PowerReactive_Q_Sum"])) {
            $aktuelleDaten["Meter1_Blindleistung"] = $JSON_Daten["Body"]["Data"]["0"]["PowerReactive_Q_Sum"];
            $aktuelleDaten["Meter1_Scheinleistung"] = $JSON_Daten["Body"]["Data"]["0"]["PowerApparent_S_Sum"];
            $aktuelleDaten["Meter1_EnergieProduziert"] = $JSON_Daten["Body"]["Data"]["0"]["EnergyReal_WAC_Sum_Produced"];
            $aktuelleDaten["Meter1_EnergieVerbraucht"] = $JSON_Daten["Body"]["Data"]["0"]["EnergyReal_WAC_Sum_Consumed"];
          }
          else {
            $aktuelleDaten["Meter1_Scheinleistung"] = 0;
            $aktuelleDaten["Meter1_Blindleistung"] = 0;
            $aktuelleDaten["Meter1_EnergieProduziert"] = 0;
            $aktuelleDaten["Meter1_EnergieVerbraucht"] = 0;
          }
        }
      }
      if (isset($JSON_Daten["Body"]["Data"]["1"])) { //  Channel 2
        $aktuelleDaten["Meter2_Wirkleistung"] = $JSON_Daten["Body"]["Data"]["1"]["PowerReal_P_Sum"];
        if (isset($JSON_Daten["Body"]["Data"]["1"]["PowerReactive_Q_Sum"])) {
          $aktuelleDaten["Meter2_Blindleistung"] = $JSON_Daten["Body"]["Data"]["1"]["PowerReactive_Q_Sum"];
          $aktuelleDaten["Meter2_Scheinleistung"] = $JSON_Daten["Body"]["Data"]["1"]["PowerApparent_S_Sum"];
          $aktuelleDaten["Meter2_EnergieProduziert"] = $JSON_Daten["Body"]["Data"]["1"]["EnergyReal_WAC_Sum_Produced"];
          $aktuelleDaten["Meter2_EnergieVerbraucht"] = $JSON_Daten["Body"]["Data"]["1"]["EnergyReal_WAC_Sum_Consumed"];
        }
        else {
          $aktuelleDaten["Meter2_Scheinleistung"] = 0;
          $aktuelleDaten["Meter2_Blindleistung"] = 0;
          $aktuelleDaten["Meter2_EnergieProduziert"] = 0;
          $aktuelleDaten["Meter2_EnergieVerbraucht"] = 0;
        }
      }
      if (isset($JSON_Daten["Body"]["Data"]["2"])) { //  Channel 3
        $aktuelleDaten["Meter3_Wirkleistung"] = $JSON_Daten["Body"]["Data"]["2"]["PowerReal_P_Sum"];
        if (isset($JSON_Daten["Body"]["Data"]["2"]["PowerReactive_Q_Sum"])) {
          $aktuelleDaten["Meter3_Blindleistung"] = $JSON_Daten["Body"]["Data"]["2"]["PowerReactive_Q_Sum"];
          $aktuelleDaten["Meter3_Scheinleistung"] = $JSON_Daten["Body"]["Data"]["2"]["PowerApparent_S_Sum"];
          $aktuelleDaten["Meter3_EnergieProduziert"] = $JSON_Daten["Body"]["Data"]["2"]["EnergyReal_WAC_Sum_Produced"];
          $aktuelleDaten["Meter3_EnergieVerbraucht"] = $JSON_Daten["Body"]["Data"]["2"]["EnergyReal_WAC_Sum_Consumed"];
        }
        else {
          $aktuelleDaten["Meter3_Scheinleistung"] = 0;
          $aktuelleDaten["Meter3_Blindleistung"] = 0;
          $aktuelleDaten["Meter3_EnergieProduziert"] = 0;
          $aktuelleDaten["Meter3_EnergieVerbraucht"] = 0;
        }
      }
    }
    else {
      break;
    }
  }
  // if ($aktuelleDaten["Ohmpilot"] == 1 and $aktuelleDaten["Gen24"] == false) {    // geändert 1.5.2023
  if ( $aktuelleDaten["Ohmpilot"] == 1 ) {
    $URL = "solar_api/v1/GetOhmPilotRealtimeData.cgi";
    $URL .= "?Scope=System";
    $JSON_Daten = Utils::read( $WR_IP, $WR_Port, $URL );
    if (isset($JSON_Daten["Head"]["Status"]["Code"]) and $JSON_Daten["Head"]["Status"]["Code"] == 0) {
      // Es handelt sich um gültige Daten
      Log::write( print_r( $JSON_Daten, 1 ), "   ", 10 );
      if (isset($JSON_Daten["Body"]["Data"]["0"])) { // Channel 0
        $aktuelleDaten["Ohmpilot_EnergieGesamt"] = $JSON_Daten["Body"]["Data"]["0"]["EnergyReal_WAC_Sum_Consumed"];
        $aktuelleDaten["Ohmpilot_Wirkleistung"] = $JSON_Daten["Body"]["Data"]["0"]["PowerReal_PAC_Sum"];
        $aktuelleDaten["Ohmpilot_Temperatur"] = round( $JSON_Daten["Body"]["Data"]["0"]["Temperature_Channel_1"] );
      }
    }
    else {
      break;
    }
  }
  $URL = "solar_api/v1/GetStringRealtimeData.cgi";
  $URL .= "?Scope=System";
  $URL .= "&DataCollection=NowStringControlData";
  $JSON_Daten = Utils::read( $WR_IP, $WR_Port, $URL );
  // Log::write(print_r($JSON_Daten,1),"   ",10);
  if (isset($JSON_Daten["Head"]["Status"]["Code"]) and $JSON_Daten["Head"]["Status"]["Code"] == 255) {
    Log::write( "URL: ".$URL, "   ", 8 );
    Log::write( "Nachricht: ".$JSON_Daten["Head"]["Status"]["Reason"], "   ", 8 );
  }
  if ($aktuelleDaten["Storage"] == 1) {
    $URL = "solar_api/v1/GetStorageRealtimeData.cgi";
    $URL .= "?Scope=System";
    $JSON_Daten = Utils::read( $WR_IP, $WR_Port, $URL );
    if (isset($JSON_Daten["Head"]["Status"]["Code"]) and $JSON_Daten["Head"]["Status"]["Code"] == 0) {
      // Es handelt sich um gültige Daten
      Log::write( print_r( $JSON_Daten, 1 ), "   ", 10 );
      if (isset($JSON_Daten["Body"]["Data"]["0"])) { // Channel 0
        $aktuelleDaten["Batterie_Max_Kapazitaet"] = $JSON_Daten["Body"]["Data"]["0"]["Controller"]["Capacity_Maximum"];
        $aktuelleDaten["Batterie_Strom_DC"] = $JSON_Daten["Body"]["Data"]["0"]["Controller"]["Current_DC"];
        $aktuelleDaten["Batterie_Hersteller"] = $JSON_Daten["Body"]["Data"]["0"]["Controller"]["Details"]["Manufacturer"];
        $aktuelleDaten["Batterie_Seriennummer"] = $JSON_Daten["Body"]["Data"]["0"]["Controller"]["Details"]["Serial"];
        $aktuelleDaten["Batterie_StateOfCharge_Relative"] = $JSON_Daten["Body"]["Data"]["0"]["Controller"]["StateOfCharge_Relative"];
        $aktuelleDaten["Batterie_Status_Batteriezellen"] = $JSON_Daten["Body"]["Data"]["0"]["Controller"]["Status_BatteryCell"];
        $aktuelleDaten["Batterie_Zellentemperatur"] = $JSON_Daten["Body"]["Data"]["0"]["Controller"]["Temperature_Cell"];
        $aktuelleDaten["Batterie_Spannung_DC"] = $JSON_Daten["Body"]["Data"]["0"]["Controller"]["Voltage_DC"];
      }
    }
    else {
      Log::write( "URL: ".$URL, "   ", 8 );
      Log::write( "Nachricht: ".$JSON_Daten["Head"]["Status"]["Reason"], "   ", 8 );
      $aktuelleDaten["Batterie_Max_Kapazitaet"] = 0;
      $aktuelleDaten["Batterie_Strom_DC"] = 0;
      $aktuelleDaten["Batterie_Hersteller"] = 0;
      $aktuelleDaten["Batterie_Seriennummer"] = 0;
      $aktuelleDaten["Batterie_StateOfCharge_Relative"] = 0;
      $aktuelleDaten["Batterie_Status_Batteriezellen"] = 0;
      $aktuelleDaten["Batterie_Zellentemperatur"] = 0;
      $aktuelleDaten["Batterie_Spannung_DC"] = 0;
    }
  }
  foreach ($aktuelleDaten as $key => $wert) {
    if (empty($wert)) {
      $aktuelleDaten[$key] = 0;
    }
  }

  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/

  /**************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  **************************************************************************/
  $FehlermeldungText = Fronius::fronius_getFehlerString( $aktuelleDaten["ErrorCodes"] );

  /****************************************************************************
  //  Wird für die HomeMatic Anbindung benötigt
  ****************************************************************************/
  if (isset($aktuelleDaten["SummePowerPV"]) and $aktuelleDaten["SummePowerPV"] > 0) {
    $aktuelleDaten["Solarleistung"] = ($aktuelleDaten["SummePowerPV"]);
  }
  else {
    $aktuelleDaten["Solarleistung"] = ($aktuelleDaten["Solarspannung"] * $aktuelleDaten["Solarstrom"]);
  }
  $aktuelleDaten["AC_Leistung"] = $aktuelleDaten["AC_Wirkleistung"];

  /***************************************************************************/
  if (isset($aktuelleDaten["SummePowerGrid"])) {
    if ($aktuelleDaten["SummePowerGrid"] < 0) {
      $aktuelleDaten["Einspeisung"] = abs( $aktuelleDaten["SummePowerGrid"] );
      $aktuelleDaten["Verbrauch"] = abs( $aktuelleDaten["SummePowerLoad"] );
      $aktuelleDaten["Bezug"] = 0;
    }
    else {
      $aktuelleDaten["Einspeisung"] = 0;
      $aktuelleDaten["Bezug"] = $aktuelleDaten["SummePowerGrid"];
      $aktuelleDaten["Verbrauch"] = abs( $aktuelleDaten["SummePowerLoad"] );
    }
  }

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  if ($aktuelleDaten["Gen24"] == true) {
    $aktuelleDaten["Produkt"] = "Fronius Symo Gen24";
    $aktuelleDaten["SolarleistungGen24"] = $aktuelleDaten["Solarleistung_String_1"];
    if (isset($aktuelleDaten["Solarleistung_String_2"])) {
      $aktuelleDaten["SolarleistungGen24"] = ($aktuelleDaten["SolarleistungGen24"] + $aktuelleDaten["Solarleistung_String_2"]);
    }
    if (isset($aktuelleDaten["Solarleistung_String_3"])) {
      $aktuelleDaten["SolarleistungGen24"] = ($aktuelleDaten["SolarleistungGen24"] + $aktuelleDaten["Solarleistung_String_3"]);
    }
  }
  else {
    $aktuelleDaten["Produkt"] = "Fronius Symo";
  }
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  Log::write( print_r( $aktuelleDaten, 1 ), "*- ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists($basedir."/custom/fronius_symo_serie_math.php" )) {
    include $basedir.'/custom/fronius_symo_serie_math.php'; // Falls etwas neu berechnet werden muss.
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
  //  Zeit und Datum
  ****************************************************************************/
  //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
  $aktuelleDaten["Timestamp"] = time( );
  $aktuelleDaten["Monat"] = date( "n" );
  $aktuelleDaten["Woche"] = date( "W" );
  $aktuelleDaten["Wochentag"] = strftime( "%A", time( ));
  $aktuelleDaten["Datum"] = date( "d.m.Y" );
  $aktuelleDaten["Uhrzeit"] = date( "H:i:s" );
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 9);

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
    Log::write( "Schleife: ".($i)." Zeitspanne: ".(floor( (54 - (time( ) - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9 );
    sleep( floor( (54 - (time( ) - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write( "OK. Daten gelesen.", "   ", 9 );
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
    if (!isset($aktuelleDaten["Solarspannung"])) {
      $aktuelleDaten["Solarspannung"] = $aktuelleDaten["Solarspannung_String_1"];
    }
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
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//  Leistung in Watt / 60 Minuten, da 60 mal in der Stunde addiert wird.
*****************************************************************************/
if (file_exists( $StatusFile ) and $aktuelleDaten["Gen24"] == true) {

  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $whProTag = file_get_contents( $StatusFile );
  // aktuellen Wert in die Datei schreiben:
  $whProTag = ($whProTag + ($aktuelleDaten["SolarleistungGen24"] / 60));
  $rc = file_put_contents( $StatusFile, $whProTag );
  Log::write( "WattstundenGesamtHeute: ".round( $whProTag, 2 ), "   ", 5 );
}

/*************/
Ausgang:

/*************/
Log::write( "-------------   Stop   fronius_symo_serie.php    --------------- ", "|--", 6 );
return;
?>
