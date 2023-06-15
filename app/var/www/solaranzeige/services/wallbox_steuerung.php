<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2022]  [Ulrich Kunz]
//
//  Dieses Programm ist freie Software. Sie können es unter den Bedingungen
//  der GNU General Public License, wie von der Free Software Foundation
//  veröffentlicht, weitergeben und/oder modifizieren, entweder gemäß
//  Version 3 der Lizenz oder (nach Ihrer Option) jeder späteren Version.
//
//  Die Veröffentlichung dieses Programms erfolgt in der Hoffnung, dass es
//  Ihnen von Nutzen sein wird, aber OHNE IRGENDEINE GARANTIE, sogar ohne
//  die implizite Garantie der MARKTREIFE oder der VERWENDBARKEIT FÜR EINEN
//  BESTIMMTEN ZWECK. Details finden Sie in der GNU General Public License.
//
//  Ein original Exemplar der GNU General Public License finden Sie hier:
//  http://www.gnu.org/licenses/
//
//  Dies ist ein Programmteil des Programms "Solaranzeige"
//
//  Es dient der Steuerung von Wallboxen. In der dazugehörigen wallbox.config.php
//  werden die individuellen Werte festgelegt.
//
//  Dieser Script kann folgendermaßen aufgerufen werden:  php wallbox_steuerung.php.
//  Er läuft unabhängig von der Solaranzeigen Software und wird mit einem cron Job
//  jede Minute gestartet.
//
*****************************************************************************/
//
//
//

/*****************************************************************************
//  Hier wird die user.config.php eingebunden, damit die Variablen benutzt
//  werden können. Das ist aber nicht zwingend notwendig. Man kann auch seine
//  eigenen Variablen nutzen.
*****************************************************************************/
$path_parts = pathinfo( $argv[0] );
$Pfad = $path_parts['dirname'];
$Ueberschuss = false;
$Datenbankname = "steuerung";
$Measurement = "Wallbox";
$StartLadung = false;
$Kabelstatus = 0;
$Ladestrom = 0;
$Pause = 0;
$StationBereit = 0;
$SOC = 100;
$Ladestatus = 1;
$Praezision = 2;

/*****************************************************************************
//  Tracelevel:
//  0 = keine LOG Meldungen
//  1 = Nur Fehlermeldungen
//  2 = Fehlermeldungen und Warnungen
//  3 = Fehlermeldungen, Warnungen und Informationen
//  4 = Debugging
*****************************************************************************/
$Tracelevel = 3;
log_schreiben( "- - - - - - - - -    Start WB Steuerung   - - - - - - - -", "|-->", 1 );

/*********************************************************************************
//  In der Datei "wallbox.steuerung.ini" stehen die Werte, die zur Steuerung der
//  Wallbox benutzt werden.
//  Auslesen der wallbox.steuerung.ini
//  Siehe Dokument:  wallbox_steuern.pdf
*********************************************************************************/
for ($i = 1; $i < 7; $i++) {
  if (file_exists( $Pfad."/".$i.".wallbox.steuerung.ini" )) {
    log_schreiben( "Grundlage der Steuerung ist die INI Datei '".$i.".wallbox.steuerung.ini'.", "|- ", 3 );
    $INI = parse_ini_file( $Pfad."/".$i.".wallbox.steuerung.ini", true, INI_SCANNER_TYPED );
    if (!isset($INI["PV-Quelle"]["BisSOC"]) or !isset($INI["Batterie-Quelle"]["BisSOC"]) or !isset($INI["Netz-Quelle"]["BisSOC"])) {
      log_schreiben( "Die Datei '".$i.".wallbox.steuerung.ini' konnte nicht richtig ausgelesen werden.", "|- ", 1 );
      log_schreiben( print_r( $INI, 1 ), "|- ", 4 );
      goto Ausgang;
    }
    //  Sonnenaufgang und Sonnenuntergang berechnen (default Standort ist Frankfurt)
    $now = time( );
    $gmt_offset = 1 + date( "I" );
    $zenith = 50 / 60;
    $zenith = $zenith + 90;
    $Sonnenuntergang = date_sunset( $now, SUNFUNCS_RET_TIMESTAMP, $INI["Allgemein"]["Breitengrad"], $INI["Allgemein"]["Laengengrad"], $zenith, $gmt_offset );
    $Sonnenaufgang = date_sunrise( $now, SUNFUNCS_RET_TIMESTAMP, $INI["Allgemein"]["Breitengrad"], $INI["Allgemein"]["Laengengrad"], $zenith, $gmt_offset );
    // Datenbank "steuerung"   Measurement: "Wallbox"
    $ch = curl_init( 'http://localhost/query?db='.$Datenbankname.'&precision=s&q='.urlencode( 'select * from '.$Measurement.' order by time desc limit 1' ));
    $rc = datenbank( $ch );
    if (!isset($rc["JSON_Ausgabe"]["results"][0]["series"])) {
      log_schreiben( "Es fehlt die Datenbank 'steuerung' oder sie ist leer.", "|- ", 1 );
      goto Ausgang;
    }
    else {
      if (isset($rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][4])) {
        $wbSteuerung = array($rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][1] => $rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][1], $rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][2] => $rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][2], $rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][3] => $rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][3], $rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][4] => $rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][4]);
      }
      else {
        $wbSteuerung = array($rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][1] => $rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][1], $rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][2] => $rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][2], $rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][3] => $rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][3]);
      }
      if ($wbSteuerung["wbSteuerung1"] == 0 and $wbSteuerung["wbSteuerung2"] == 0 and $wbSteuerung["wbSteuerung3"] == 0) {
        log_schreiben( "Steuerung nicht aktiviert. Keine Ladequelle ausgesucht. Ausgang...", "", 1 );
        goto Ausgang;
      }
      if ($INI["Allgemein"]["Batterie"] == false) {
        log_schreiben( "Keine Batterie angeschlossen...", "", 4 );
      }
      if (isset($INI["PV-Quelle"]["Intervall"])) {
        $Intervall = abs( round( $INI["PV-Quelle"]["Intervall"] ));
        if ($Intervall >= 1 AND $Intervall <= 9) {
          log_schreiben( "Intervall: ".$INI["PV-Quelle"]["Intervall"], "", 4 );
        }
        else {
          log_schreiben( "Intervall ungültig: ".$INI["PV-Quelle"]["Intervall"], "", 1 );
          $Intervall = 3;
        }
      }
      else {
        $Intervall = 3; //  Alle 3 Minuten
      }
      if (isset($INI["Allgemein"]["Phasen"]) and $INI["Allgemein"]["Phasen"] == 1) {
        log_schreiben( "Phasen: ".$INI["Allgemein"]["Phasen"], "", 4 );
        $PUI = 230; //  Wechselstromberechnung
      }
      elseif (isset($INI["Allgemein"]["Phasen"]) and $INI["Allgemein"]["Phasen"] == 2) {
        log_schreiben( "Phasen: ".$INI["Allgemein"]["Phasen"], "", 4 );
        $PUI = 400; //  2 Phasen Berechnung
      }
      else {
        $PUI = (400 * 1.73); //  Drehstromberechnung
      }
      if (isset($INI["PV-Quelle"]["Inselanlage"]) and $INI["PV-Quelle"]["Inselanlage"] == 1) {
        log_schreiben( "es ist eine Inselanlage", "", 2 );
        $Inselanlage = true;
      }
      else {
        $Inselanlage = false;
      }
      if (isset($INI["PV-Quelle"]["NurBeiSonne"]) and $INI["PV-Quelle"]["NurBeiSonne"] == 1) {
        log_schreiben( "Ladung nur bei genügend Sonnenenergie. [ INI > NurBeiSonne = yes ]", "", 3 );
        $NurBeiSonne = true;
      }
      else {
        $NurBeiSonne = false;
      }
      if (isset($INI["PV-Quelle"]["Eigenverbrauch"])) {
        $Eigenverbrauch = $INI["PV-Quelle"]["Eigenverbrauch"];
        log_schreiben( "Eigenverbrauch laut INI Datei: ".$Eigenverbrauch." Watt", "", 3 );
      }
      else {
        $Eigenverbrauch = 0;
      }
      if (isset($INI["Batterie-Quelle"]["Kap60"])) {
        $BatterieKap60 = $INI["Batterie-Quelle"]["Kap60"];
        $NetzKap60 = $INI["Netz-Quelle"]["Kap60"];
      }
      else {
        $BatterieKap60 = 60;
        $NetzKap60 = 60;
      }
      // $DB6 = Datenbank der steuerung    Measurement: Ladung
      $ch = curl_init( 'http://localhost/query?db='.$Datenbankname.'&precision=s&q='.urlencode( 'select * from Ladung order by time desc limit 1' ));
      $rc = datenbank( $ch );
      if (!isset($rc["JSON_Ausgabe"]["results"][0]["series"])) {
        log_schreiben( "Es fehlt in der Datenbank '".$Datenbankname."' das Measurement 'Laden' oder sie ist leer.", "|- ", 1 );
        // Flag setzen in die Datenbank "Steuerung"  Measurement "Ladung"  Flag "unterbrechung"
        $query = "Ladung Unterbrechung=0";
        $ch = curl_init( 'http://localhost/write?db=steuerung&precision=s' );
        $rc = datenbank( $ch, $query );
        log_schreiben( "Unterbrechung Flag aufgehoben.", "", 4 );
      }
      else {
        for ($h = 1; $h < count( $rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"] ); $h++) {
          $DB6[$rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][$h]] = $rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][$h];
        }
        $Pause = $DB6["Unterbrechung"];
        log_schreiben( "Datenbank: '".$Datenbankname."' ".print_r( $DB6, 1 ), "", 4 );
      }

      /**************************************************************************
      //  Auslesen der user.config.php für den Wechselrichter
      **************************************************************************/
      require ($Pfad."/".$INI["Geraete"]["Wechselrichter"]);
      $wrRegler = $Regler;
      $wrGeraeteNummer = substr( $INI["Geraete"]["Wechselrichter"], 0, 1 );
      sleep( 1 ); // Damit die Datenbank durch den Wechselrichter nicht belegt ist.
      $wrDatenbankname = $InfluxDBLokal;
      // $DB1 = Datenbank des Wechselrichters   Measurement: PV
      $ch = curl_init( 'http://localhost/query?db='.$wrDatenbankname.'&precision=s&q='.urlencode( 'select * from PV order by time desc limit 1' ));
      $rc = datenbank( $ch );
      if (!isset($rc["JSON_Ausgabe"]["results"][0]["series"])) {
        log_schreiben( "Es fehlt die Datenbank '".$wrDatenbankname."' mit dem Measurement PV, versuche es mit Measurement AC ", "|- ", 7 );
        $ch = curl_init( 'http://localhost/query?db='.$wrDatenbankname.'&precision=s&q='.urlencode( 'select * from AC order by time desc limit 1' ));
        $rc = datenbank( $ch );
        if (!isset($rc["JSON_Ausgabe"]["results"][0]["series"])) {
          log_schreiben( "Es fehlt die Datenbank '".$wrDatenbankname."' mit dem Measurement PV und AC oder sie ist leer.", "|- ", 1 );
          goto Ausgang;
        }
      }
      for ($h = 1; $h < count( $rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"] ); $h++) {
        $DB1[$rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][$h]] = $rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][$h];
      }
      log_schreiben( "Datenbank: '".$wrDatenbankname."' ".print_r( $DB1, 1 ), "", 4 );
      switch ($wrRegler) {

        case 3:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 7:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 8:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 9:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 10:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 12:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 14:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 16:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 17:
          $Solarleistung = $DB1["Gesamtleistung"];
          break;

        case 18:
          $Solarleistung = $DB1["Gesamtleistung"];
          break;

        case 19:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 22:
          $Solarleistung = $DB1["Leistung_neg"]; // SmartMeter Einspeisung
          break;

        case 24:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 25:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 26:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 27:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 31:
          $Solarleistung = $DB1["Gesamtleistung"];
          if ($Solarleistung >= 0) {
            $Solarleistung = 0;
          }
          else {
            $Solarleistung = abs( $DB1["Gesamtleistung"] );
          }
          break;

        case 32:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 36:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 37: // Nur alte Datenbankstruktur
          $Solarleistung = $DB1["Leistung"];
          break;

        case 43:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 45:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 46:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 48:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 49:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 52:
          $Solarleistung = $DB1["PV_Leistung"];
          break;

        case 55:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 56:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 62:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 64:
          $Solarleistung = $DB1["PV_Leistung"];
          break;

        case 65:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 66:
          $Solarleistung = $DB1["Gesamtleistung"];
          break;

        case 67:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 68:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 70:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 71:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 73:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 76:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 77:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 80:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 82:
          $Solarleistung = $DB1["Leistung"];
          break;

        case 84:
          $Solarleistung = $DB1["Leistung"];
          break;

        default:
          if (isset($DB1["Leistung"])) {
            $Solarleistung = $DB1["Leistung"];
          }
          elseif (isset($DB1["Gesamtleistung"])) {
            $Solarleistung = $DB1["Gesamtleistung"];
          }
          else {
            $Solarleistung = 0;
          }
          break;
      }
      // $Solarleistung = 0;  // Nur zum Testen
      if ($INI["Allgemein"]["Batterie"] == true) {
        if (!isset($INI["Geraete"]["BMS"])) {
          // $DB2 = Datenbank des WR  Measurement: Batterie
          // BMS Datenbank ist weiter unten zu finden. Zeile 1291
          $ch = curl_init( 'http://localhost/query?db='.$wrDatenbankname.'&precision=s&q='.urlencode( 'select * from Batterie order by time desc limit 1' ));
          $rc = datenbank( $ch );
          if (!isset($rc["JSON_Ausgabe"]["results"][0]["series"])) {
            log_schreiben( "Es fehlt die Datenbank '".$wrDatenbankname."' mit dem Measurement Batterie oder sie ist leer.", "|- ", 1 );
            log_schreiben( "Wenn es das Measurement nicht gibt, dann bitte Batterie=no in der INI Datei eintragen. Es geht auch ohne Batterieüberwachung.", "|- ", 1 );
            goto Ausgang;
          }
          for ($h = 1; $h < count( $rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"] ); $h++) {
            $DB2[$rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][$h]] = $rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][$h];
          }
          log_schreiben( "Datenbank: '".$wrDatenbankname."' ".print_r( $DB2, 1 ), "", 4 );
          switch ($wrRegler) {

            case 3:
              $SOC = $DB2["SOC"];
              break;

            case 7:
              $SOC = $DB2["Kapazitaet"];
              break;

            case 8:
              $SOC = $DB2["Kapazitaet"];
              break;

            case 9:
              $SOC = $DB2["Kapazitaet"];
              break;

            case 14:
              $SOC = $DB2["SOC"];
              break;

            case 16:
              $SOC = $DB2["SOC"];
              break;

            case 17:
              $SOC = $DB2["SOC"];
              break;

            case 18:
              $SOC = $DB2["SOC"];
              break;

            case 19:
              $SOC = $DB2["SOC"];
              break;

            case 24:
              $SOC = $DB2["Kapazitaet"];
              break;

            case 25:
              $SOC = $DB2["SOC"];
              break;

            case 26:
              $SOC = $DB2["Kapazitaet"];
              break;

            case 27:
              $SOC = $DB2["SOC"];
              break;

            case 38:
              $SOC = $DB2["SOC"];
              break;

            case 43:
              $SOC = $DB2["SOC"];
              break;

            case 45:
              $SOC = $DB2["SOC"];
              break;

            case 52:
              $SOC = $DB2["SOC"];
              break;

            case 55:
              $SOC = $DB2["SOC"];
              break;

            case 56:
              $SOC = $DB2["SOC"];
              break;

            case 57:
              $SOC = $DB2["SOC"];
              break;

            case 62:
              $SOC = $DB2["SOC"];
              break;

            case 64:
              $SOC = $DB2["SOC"];
              break;

            case 65:
              $SOC = $DB2["SOC"];
              break;

            case 68:
              $SOC = $DB2["SOC"];
              break;

            case 70:
              $SOC = $DB2["SOC"];
              break;

            case 71:
              $SOC = $DB2["SOC"];
              break;

            case 77:
              $SOC = $DB2["Kapazitaet"];
              break;

            case 80:
              $SOC = $DB2["Batterie_SOC"];
              break;

            case 82:
              $SOC = $DB2["SOC"];
              break;

            case 84:
              $SOC = $DB2["SOC"];
              break;

            default:
              $SOC = 100;
              break;
          }
        }
      }
      else {
        $SOC = 100;
      }

      /**************************************************************************
      //  Auslesen der user.config.php für die Wallbox
      **************************************************************************/
      require ($Pfad."/".$INI["Geraete"]["Wallbox"]);
      $wbRegler = $Regler;
      $wbGeraeteNummer = substr( $INI["Geraete"]["Wallbox"], 0, 1 );
      $wbDatenbankname = $InfluxDBLokal;
      // $DB3 = Datenbank der Wallbox  Measurement: Summen
      $ch = curl_init( 'http://localhost/query?db='.$wbDatenbankname.'&precision=s&q='.urlencode( 'select * from Summen order by time desc limit 1' ));
      $rc = datenbank( $ch );
      if (!isset($rc["JSON_Ausgabe"]["results"][0]["series"])) {
        log_schreiben( "Es fehlt die Datenbank '".$wbDatenbankname."' mit dem Measurement Summen oder sie ist leer.", "|- ", 1 );
        goto Ausgang;
      }
      for ($h = 1; $h < count( $rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"] ); $h++) {
        $DB3[$rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][$h]] = $rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][$h];
      }
      log_schreiben( "Datenbank: '".$wbDatenbankname."' ".print_r( $DB3, 1 ), "", 4 );
      // $DB4 = Datenbank der Wallbox  Measurement: Service
      $ch = curl_init( 'http://localhost/query?db='.$wbDatenbankname.'&precision=s&q='.urlencode( 'select * from Service order by time desc limit 1' ));
      $rc = datenbank( $ch );
      if (!isset($rc["JSON_Ausgabe"]["results"][0]["series"])) {
        log_schreiben( "Es fehlt die Datenbank '".$wbDatenbankname."' mit dem Measurement Service oder sie ist leer.", "|- ", 1 );
        goto Ausgang;
      }
      for ($h = 1; $h < count( $rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"] ); $h++) {
        $DB4[$rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][$h]] = $rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][$h];
      }
      log_schreiben( "Datenbank: '".$wbDatenbankname."' ".print_r( $DB4, 1 ), "", 4 );
      // $DB7 = Datenbank der Wallbox  Measurement: AC
      $ch = curl_init( 'http://localhost/query?db='.$wbDatenbankname.'&precision=s&q='.urlencode( 'select * from AC  order by time desc limit 1' ));
      $rc = datenbank( $ch );
      if (!isset($rc["JSON_Ausgabe"]["results"][0]["series"])) {
        log_schreiben( "Es fehlt die Datenbank '".$wbDatenbankname."' mit dem Measurement AC oder sie ist leer.", "|- ", 1 );
        goto Ausgang;
      }
      for ($h = 1; $h < count( $rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"] ); $h++) {
        $DB7[$rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][$h]] = $rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][$h];
      }
      log_schreiben( "Datenbank: '".$wbDatenbankname."' ".print_r( $DB7, 1 ), "", 4 );

      /*************************************************************************
      //  Ladestatus 1 = Nicht bereit
      //  Ladestatus 2 = Bereit zum Laden
      //  Ladestatus 3 = Es wird geladen
      //  Ladestatus 4 = Ladung beendet
      //  Ladestatus 5 = Ladung unterbrochen

      //  Auflistung der einzelnen Wallboxen:
      //  Kabelstatus 7 = Kabel ist angeschlossen und verriegelt.
      /   WhLadung in kWh
      ************************************************************************/
      switch ($wbRegler) {

        case 29:
          // Go-eCharger
          //  Zugangskontrolle 0 = ohne Auth.  1 = mit RFID Karte
          //  RFID_Karte = 0 keine Karte aktiv
          //
          //  Ladestatus 1 = Nicht bereit
          //  Ladestatus 2 = Bereit zum Laden
          //  Ladestatus 3 = Es wird geladen
          //  Ladestatus 4 = Ladung beendet und Ladung unterbrochen
          //  Ladestatus 5 = Ladung unterbrochen
          $RFID_Karte = $DB4["RFID_Karte"]; // uby
          $Ladestatus = $DB4["Stationsstatus"]; // car
          $StationBereit = $DB4["StationBereit"]; // alw
          $Zugangskontrolle = $DB4["Zugangskontrolle"]; // ast
          // Minimum Ladestärke bei dieser Wallbox
          $MaxAmpere = (round( $DB4["MaxAmpere"] ) * 1000); // Stromvorgabe
          if ($MaxAmpere < 6000) {
            $MaxAmpere = 6000;
          }
          $StromL1 = $DB7["Strom_R"];
          $StromL2 = $DB7["Strom_S"];
          $StromL3 = $DB7["Strom_T"];
          if ($Ladestatus == 1) {
            $Ladestatus = 1; //  Kein Auto vorhanden
          }
          elseif ($Ladestatus == 2) {
            $Ladestatus = 3; //  es wird geladen
            $Kabelstatus = 7; //  Kabel verriegelt
          }
          elseif ($Ladestatus == 4 and $Pause == 1) {
            $Ladestatus = 2; //  Auto bereit zum Laden
            $Kabelstatus = 7; //  Kabel verriegelt
          } //  Pause
          elseif ($Ladestatus == 4 and $StationBereit == 0) {
            $Ladestatus = 2; //  Auto bereit zum Laden
            $Kabelstatus = 7; //  Kabel verriegelt
            //  Ladestation aktivieren
          }
          elseif ($Ladestatus == 4) { //  Ladung vom Auto beendet
            $Ladestatus = 4; //  Auto ist geladen
            $Kabelstatus = 7; //  Kabel noch verriegelt
          }
          elseif ($Ladestatus == 3 and $StationBereit == 0) {
            $Ladestatus = 2; //  Auto bereit zum Laden
            $Kabelstatus = 7; //  Kabel verriegelt
          }
          elseif ($Ladestatus == 3) {
            $Ladestatus = 5; //  Auto bereit zum Laden
            $Kabelstatus = 7; //  Kabel verriegelt
            //  Auto muss Ladung freigeben.
          }
          elseif ($Zugangskontrolle == 1 and $RFID_Karte == 0) {
            //  Keine RFID Karte aktiv jedoch Autorisierung aktiv
            log_schreiben( "Warte auf Aktivierung durch RFID Karte", "", 2 );
            $Ladestatus = 1; //  Wallbox nicht bereit
          }
          //  Automatische Abfrage der benutzten Phasen.          //  Überschreibt den Phasen Eintrag in der wallbox.steuerung.ini
          //
          if ($StromL1 > 1 and $StromL2 > 1 and $StromL3 > 1) {
            $PUI = (400 * 1.73); //  Wechselstromberechnung 3 Phasen
          }
          elseif (($StromL1 > 1 and $StromL2 > 1) or ($StromL1 > 1 and $StromL3 > 1) or ($StromL2 > 1 and $StromL3 > 1)) {
            $PUI = 400; //  Wechselstromberechnung 2 Phasen
          }
          else {
            $PUI = 230; //  Wechselstromberechnung 1 Phase
          }
          $WhLadung = $DB3["Wh_Ladevorgang"];
          break;

        case 30:
          //  Keba Wallbox P30C
          $Ladestatus = $DB4["Stationsstatus"];
          $Kabelstatus = $DB4["Ladekabel"];
          $FreigabeUser = $DB4["EnableUser"];
          $MaxAmpere = ($DB4["MaxAmpere"] * 1000); // Stromvorgabe
          if ($MaxAmpere < 6000) {
            $MaxAmpere = 6000;
          }
          $INI["Batterie-Quelle"]["MaxEnergie"] = 0; // MaxEnergie ausschalten bei der Keba
          //
          //  Ladestatus vorher:
          //  0 = Startup
          //  1 = not ready
          //  2 = Ready for charging
          //  3 = Charging
          //  4 = Error
          //  5 = Charging temporarity interrupted
          //
          //  Nachher:
          //  Ladestatus 1 = Nicht bereit
          //  Ladestatus 2 = Bereit zum Laden
          //  Ladestatus 3 = Es wird geladen
          //  Ladestatus 4 = Ladung beendet
          //  Ladestatus 5 = Ladung unterbrochen
          //
          //
          if ($Ladestatus == 0) { // Das Auto hat die Ladung unterbrochen.
            $Ladestatus = 1;
          }
          if ($Ladestatus == 2) {
            $Ladestatus = 2;
          }
          if ($Ladestatus == 5 and $Pause = 1) {
            // Es handelt sich um eine Pause. Warte auf wiedereinschaltung.
            $Ladestatus = 2;
          }
          //  Kabelstatus
          //  0 = No cable plugged
          //  1 = Cable plugged
          //  3 = Cable plugged and locked
          //  5 = Cable plugged and locked. Vehicle not locked
          //  7 = Cable locked, ready for charging
          //  Abfrage der benutzten Phasen.
          //  Überschreibt den Phasen Eintrag in der wallbox.steuerung.ini
          if (isset($DB4["AnzPhasen"])) {
            if ($DB4["AnzPhasen"] == 3) {
              $PUI = (400 * 1.73); //  Wechselstromberechnung 3 Phasen
            }
            elseif ($DB4["AnzPhasen"] == 2) {
              $PUI = 400; //  Wechselstromberechnung 2 Phasen
            }
            else {
              $PUI = 230; //  Wechselstromberechnung 1 Phase
            }
          }
          $WhLadung = ($DB3["Wh_Ladevorgang"] / 1000);
          break;

        case 35:
        case 47:
          //  35 = Wallbe Wallbox   47 = Phoenix Contact
          $LSAktiv = $DB4["LadungAktiv"];
          $FreigabeUser = $DB4["Ladung_erlaubt"];
          $Ladebedingungen_OK = $DB4["Ladebedingungen_OK"];
          $MaxAmpere = ($DB4["MaxLadestrom"] * 1000); // Stromvorgabe
          // Minimum Ladestärke bei dieser Wallbox
          if ($MaxAmpere < 6000) {
            $MaxAmpere = 6000;
          }
          //  Vorher:
          //  Ladestatus
          //  0 = keine Ladung
          //  1 = Charging
          //
          //  Nachher:
          //  Ladestatus 1 = Nicht bereit
          //  Ladestatus 2 = Bereit zum Laden
          //  Ladestatus 3 = Es wird geladen
          //  Ladestatus 4 = Ladung beendet
          //  Ladestatus 5 = Ladung unterbrochen
          //
          if ($LSAktiv == 0) { // Das Auto hat die Ladung unterbrochen.
            $Ladestatus = 1;
          }
          if ($DB4["Stationsstatus"] == "B" and $LSAktiv == 1 and $MaxAmpere > 0) { // Das Auto hat die Ladung unterbrochen.
            $Ladestatus = 4;
            //  Hier eventuell das Kabel entriegeln
            log_schreiben( "Ladung beendet. Bitte das Ladekabel entriegelt / entfernen.", "", 4 );

            /*******************
            $ret = befehl_senden("Unlock",$wbRegler,$wbGeraeteNummer,1);
            if ($ret == true) {
            log_schreiben("Force unlock.","",3);
            log_schreiben("Unlock,$wbRegler,$wbGeraeteNummer,1","",3);
            }
            ******************/
          }
          if ($DB4["Stationsstatus"] == "A") { // Bereit zur Ladung
            $Ladestatus = 1;
          }
          if ($DB4["Stationsstatus"] == "B") { // Bereit zur Ladung
            $Ladestatus = 2;
          }
          if ($DB4["Stationsstatus"] == "C" and $DB4["LadungAktiv"] == "1") { // Das Auto wird geladen.
            $Ladestatus = 3;
          }
          if ($LSAktiv == 1 and $Pause == 1 and $DB4["Stationsstatus"] == "B") {
            // Es handelt sich um eine Pause. Warte auf wiedereinschaltung.
            $Ladestatus = 2;
          }
          //  Kabelstatus
          //  0 = No cable plugged
          //  1 = Cable plugged
          //  3 = Cable plugged and locked
          //  5 = Cable plugged and locked. Vehicle not locked
          //  7 = Cable locked, ready for charging
          switch ($DB4["Stationsstatus"]) {

            case "A":
              $Kabelstatus = 0;
              break;

            case "B":
              $Kabelstatus = 7;
              break;

            case "C":
              $Kabelstatus = 7;
              break;

            case "D":
              $Kabelstatus = 7;
              break;

            case "E":
              $Kabelstatus = 7;
              break;

            case "F":
              $Kabelstatus = 0;
              break;

            default:
              $Kabelstatus = 0;
              break;
          }
          $WhLadung = ($DB3["Wh_Ladevorgang"] / 1000);
          break;

        case 37:
          //  Simple EVSE Wallbox
          $LSAktiv = $DB4["freigeschaltet"];
          $MaxAmpere = ($DB4["Stromvorgabe"] * 1000); // MaxAmpere = Stromvorgabe
          // Minimum Ladestärke bei dieser Wallbox
          if ($MaxAmpere < 6000) {
            $MaxAmpere = 6000;
          }
          //  Vorher:
          //  Ladestatus
          //  0 = keine Ladung
          //  1 = Charging
          //
          //  Nachher:
          //  Ladestatus 1 = Nicht bereit
          //  Ladestatus 2 = Bereit zum Laden
          //  Ladestatus 3 = Es wird geladen
          //  Ladestatus 4 = Ladung beendet
          //  Ladestatus 5 = Ladung unterbrochen
          //
          if ($LSAktiv == 0) { // Das Auto hat die Ladung unterbrochen.
            $Ladestatus = 1;
          }
          if ($DB4["Stationsstatus"] == "2" and $LSAktiv == 1) { // Das Auto hat die Ladung unterbrochen.
            $Ladestatus = 4;
            //  Hier eventuell das Kabel entriegeln
            log_schreiben( "Kann das Kabel entriegelt werden?", "", 4 );
          }
          if ($DB4["Stationsstatus"] == "1") { // Bereit zur Ladung
            $Ladestatus = 1;
          }
          if ($DB4["Stationsstatus"] == "2") { // Bereit zur Ladung
            $Ladestatus = 2;
          }
          if ($DB4["Stationsstatus"] == "3" and $LSAktiv == "1") { // Das Auto wird geladen.
            $Ladestatus = 3;
          }
          //  Kabelstatus
          //  0 = No cable plugged
          //  1 = Cable plugged
          //  3 = Cable plugged and locked
          //  5 = Cable plugged and locked. Vehicle not locked
          //  7 = Cable locked, ready for charging
          switch ($DB4["Stationsstatus"]) {

            case "0":
              $Kabelstatus = 0;
              break;

            case "1":
              $Kabelstatus = 0;
              break;

            case "2":
              $Kabelstatus = 7;
              break;

            case "3":
              $Kabelstatus = 7;
              break;
          }
          $WhLadung = ($DB3["Wh_Ladevorgang"] / 1000);
          break;

        case 39:
          //  openWB Wallbox
          //
          //  Ladestatus 1 = Nicht bereit
          //  Ladestatus 2 = Bereit zum Laden
          //  Ladestatus 3 = Es wird geladen
          //  Ladestatus 4 = Ladung beendet
          //  Ladestatus 5 = Ladung unterbrochen
          $Ladestatus = $DB4["Ladestatus"];
          //  Der Stecker ist eingesteckt und verriegelt
          //  1 = verriegelt
          $LSAktiv = $DB4["Ladevorgang"];
          //  Der Stecker ist eingesteckt und verriegelt
          //  1 = verriegelt
          $Stationsstatus = $DB4["Stationsstatus"];
          //  Kabelstatus
          //  0 = No cable plugged
          //  1 = Cable plugged
          //  3 = Cable plugged and locked
          //  5 = Cable plugged and locked. Vehicle not locked
          //  7 = Cable locked, ready for charging
          $Kabelstatus = $DB4["LadeStecker"];
          if ($Kabelstatus == 0) {
            $Kabelstatus = 1;
          }
          elseif ($Kabelstatus == 1) {
            $Kabelstatus = 7;
          }
          // Minimum Ladestärke bei dieser Wallbox
          $MaxAmpere = ($DB4["MaxAmpere"] * 1000); // Stromvorgabe
          if ($MaxAmpere < 6000) {
            $MaxAmpere = 6000;
          }
          if ($Ladestatus == 2 and $Pause == 1) {
            $Ladestatus = 2; //  Auto bereit zum Laden
            $StationBereit = 1;
          }
          elseif ($Ladestatus == 4 and $LSAktiv == 1) {
            $Ladestatus = 4; //  Auto hat Ladung beendet (voll)
            $StationBereit = 1;
          }
          elseif ($Ladestatus == 1 and $LSAktiv == 1) {
            $Ladestatus = 1; //  Kabel nicht angeschlossen
          }
          elseif ($Ladestatus == 2 and $LSAktiv == 1) {
            $Ladestatus = 2; //  Auto bereit zum Laden
            $StationBereit = 1;
          }
          elseif ($Ladestatus == 4) { // Auto hat Ladung unterbrochen
            $StationBereit = 1;
          }
          elseif ($Ladestatus == 3) { //  Auto wird geladen
            $StationBereit = 1;
          }
          //  Abfrage der benutzten Phasen.
          //  Überschreibt den Phasen Eintrag in der wallbox.steuerung.ini
          if (isset($DB4["ZaehlerPhasen_akt"])) {
            if ($DB4["ZaehlerPhasen_akt"] == 3) {
              $PUI = (400 * 1.73); //  Wechselstromberechnung 3 Phasen
            }
            elseif ($DB4["ZaehlerPhasen_akt"] == 2) {
              $PUI = 400; //  Wechselstromberechnung 2 Phasen
            }
            else {
              $PUI = 230; //  Wechselstromberechnung 1 Phase
            }
          }
          $WhLadung = $DB3["Wh_Ladevorgang"];
          break;

        case 44:
          // Webasto Wallbox
          //
          //  Ladestatus 0 = Nicht bereit
          //  Ladestatus 1 = Nicht bereit
          //  Ladestatus 2 = Bereit zum Laden
          //  Ladestatus 3 = Es wird geladen
          //  Ladestatus 4 = Ladung beendet
          //  Ladestatus 5 = Ladung unterbrochen
          $Ladestatus = $DB4["Stationsstatus"];
          $LSAktiv = $DB4["LadungAktiv"];
          //  Kabelstatus
          //  0 = No cable plugged
          //  1 = Cable plugged
          //  3 = Cable plugged and locked
          //  5 = Cable plugged and locked. Vehicle not locked
          //  7 = Cable locked, ready for charging
          $Kabelstatus = $DB4["Kabelstatus"];
          if ($Kabelstatus == 0) {
            $Kabelstatus = 1;
          }
          elseif ($Kabelstatus == 1) {
            $Kabelstatus = 5;
          }
          if ($Kabelstatus == 2) {
            $Kabelstatus = 7;
          }
          // Minimum Ladestärke bei dieser Wallbox
          $MaxAmpere = ($DB4["MaxAmpere"] * 1000); // Stromvorgabe
          if ($MaxAmpere < 6000) {
            $MaxAmpere = 6000;
          }
          if ($Ladestatus == 0 or $Ladestatus == 1) {
            $Ladestatus = 1; //  Kein Auto vorhanden
          }
          elseif ($Ladestatus == 4 and $Pause == 1) {
            $Ladestatus = 2; //  Auto bereit zum Laden
          }
          elseif ($Ladestatus == 4 and $LSAktiv == 1) {
            $Ladestatus = 2; //  Auto bereit zum Laden
          }
          $WhLadung = $DB3["Wh_Ladevorgang"];
          break;

        case 60:
          //  Hardy Barth Wallbox
          //  RFID ist noch nicht eingebunden!
          //
          $Ladestatus = $DB4["StateID"];
          $StationBereit = $DB4["Kabel"]; // 1 = Kabel angeschlossen
          // Minimum Ladestärke bei dieser Wallbox
          $MaxAmpere = (round( $DB4["LadungAmpere"] ) * 1000); // Stromvorgabe
          if ($MaxAmpere < 6000) {
            $MaxAmpere = 6000;
          }
          $StromL1 = $DB7["Strom_R"];
          $StromL2 = $DB7["Strom_S"];
          $StromL3 = $DB7["Strom_T"];
          //  Kabelstatus
          //  0 = No cable plugged
          //  1 = Cable plugged
          //  3 = Cable plugged and locked
          //  5 = Cable plugged and locked. Vehicle not locked
          //  7 = Cable locked, ready for charging
          if ($StationBereit == 0) {
            $Kabelstatus = 0; //  Kabel nicht angeschlossen
          }
          elseif ($StationBereit == 1) {
            $Kabelstatus = 7; //  Kabel nicht angeschlossen
          }
          //  Ladestatus 1 = Nicht bereit
          //  Ladestatus 2 = Bereit zum Laden
          //  Ladestatus 3 = Es wird geladen
          //  Ladestatus 4 = Ladung beendet und Ladung unterbrochen
          //  Ladestatus 5 = Ladung unterbrochen
          if ($Ladestatus == 0 and $StationBereit == 0) {
            $Ladestatus = 1; //  Kein Auto vorhanden
          }
          elseif ($Ladestatus == 0 and $StationBereit == 1) {
            $Ladestatus = 2; //  Bereit zum laden
          }
          elseif ($Ladestatus == 5) {
            $Ladestatus = 3; //  es wird geladen
          }
          elseif ($Ladestatus == 17 and $Pause == 1) {
            $Ladestatus = 2; //  Auto bereit zum Laden
          } //  Pause
          elseif ($Ladestatus == 17) {
            $Ladestatus = 2; //  Auto bereit zum Laden
          }
          elseif ($Ladestatus == 4) { //  Ladung vom Auto beendet
            $Ladestatus = 4; //  Auto ist geladen
          }
          //  Automatische Abfrage der benutzten Phasen.          //  Überschreibt den Phasen Eintrag in der wallbox.steuerung.ini
          //
          if ($StromL1 > 1 and $StromL2 > 1 and $StromL3 > 1) {
            $PUI = (400 * 1.73); //  Wechselstromberechnung 3 Phasen
          }
          elseif (($StromL1 > 1 and $StromL2 > 1) or ($StromL1 > 1 and $StromL3 > 1) or ($StromL2 > 1 and $StromL3 > 1)) {
            $PUI = 400; //  Wechselstromberechnung 2 Phasen
          }
          else {
            $PUI = 230; //  Wechselstromberechnung 1 Phase
          }
          $WhLadung = $DB3["Wh_Ladevorgang"];
          $Praezision = 3; //  Nur volle Ampere werden benutzt.
          break;

        case 63:
          //  cFos Wallbox
          $Ladestatus = $DB4["Stationsstatus"];
          $FreigabeUser = $DB4["EnableUser"];
          $MaxAmpere = ($DB4["MaxAmpere"] * 100); // Stromvorgabe
          if ($MaxAmpere < 6000) {
            $MaxAmpere = 6000;
          }
          $INI["Batterie-Quelle"]["MaxEnergie"] = 0; // MaxEnergie ausschalten bei der Keba
          //
          //  Ladestatus vorher:
          //  0 = warten
          //  1 = Fahrzeug ready
          //  2 = Charging
          //  3 = Charging
          //  4 = kein Strom
          //  5 = Fehler
          //
          //  Nachher:
          //  Ladestatus 1 = Nicht bereit
          //  Ladestatus 2 = Bereit zum Laden
          //  Ladestatus 3 = Es wird geladen
          //  Ladestatus 4 = Ladung beendet
          //  Ladestatus 5 = Ladung unterbrochen
          //
          //
          if ($Ladestatus == 0) { // Warten.
            $Ladestatus = 1;
            $Kabelstatus = 0;
          }
          elseif ($Ladestatus == 1) { // KFZ Bereit.
            $Ladestatus = 2;
            $Kabelstatus = 7;
          }
          elseif ($Ladestatus == 2) { // Charging
            $Ladestatus = 3;
            $Kabelstatus = 7;
          }
          elseif ($Ladestatus == 3) { // Charging mit Kühlung
            $Ladestatus = 3;
            $Kabelstatus = 7;
          }
          elseif ($Ladestatus == 4) { // Kein Strom
            $Ladestatus = 3;
            $Kabelstatus = 7;
          }
          if ($Ladestatus == 5) { // Fehler
            $Ladestatus = 6;
            $Kabelstatus = 0;
          }
          //  Kabelstatus
          //  0 = No cable plugged
          //  1 = Cable plugged
          //  3 = Cable plugged and locked
          //  5 = Cable plugged and locked. Vehicle not locked
          //  7 = Cable locked, ready for charging
          //  $Kabelstatus = $DB4["Ladekabel"]; // Ausgeschaltet.
          //  Abfrage der benutzten Phasen.
          //  Überschreibt den Phasen Eintrag in der wallbox.steuerung.ini
          if (isset($DB4["AnzPhasen"])) {
            if ($DB4["AnzPhasen"] == 3) {
              $PUI = (400 * 1.73); //  Wechselstromberechnung 3 Phasen
            }
            elseif ($DB4["AnzPhasen"] == 2) {
              $PUI = 400; //  Wechselstromberechnung 2 Phasen
            }
            else {
              $PUI = 230; //  Wechselstromberechnung 1 Phase
            }
          }
          $WhLadung = ($DB3["Wh_Ladevorgang"] / 1000);
          break;

        case 69:
          //  VESTEL EVC04 Wallbox
          $Ladestatus = $DB4["Stationsstatus"];
          $FreigabeUser = $DB4["Ladung_erlaubt"];
          $MaxAmpere = ($DB4["MaxLadestrom"] * 1000); // Stromvorgabe
          if ($MaxAmpere < 6000) {
            $MaxAmpere = 6000;
          }
          $INI["Batterie-Quelle"]["MaxEnergie"] = 0; // MaxEnergie ausschalten bei der Keba
          //
          //  Ladestatus vorher:
          //  0 = warten
          //  1 = Fahrzeug ready
          //  2 = Charging
          //  3 = Charging
          //  4 = kein Strom
          //  5 = Fehler
          //
          //  Nachher:
          //  Ladestatus 1 = Nicht bereit
          //  Ladestatus 2 = Bereit zum Laden
          //  Ladestatus 3 = Es wird geladen
          //  Ladestatus 4 = Ladung beendet
          //  Ladestatus 5 = Ladung unterbrochen
          //
          //
          if ($Ladestatus == 0) { // Warten.
            $Ladestatus = 1;
          }
          elseif ($Ladestatus == 1) { // Bootvorgang.
            $Ladestatus = 2;
          }
          elseif ($Ladestatus == 2) { // Charging
            $Ladestatus = 3;
          }
          elseif ($Ladestatus == 3) { // Pause
            $Ladestatus = 2;
          }
          elseif ($Ladestatus == 4) { // Pause
            $Ladestatus = 2;
          }
          if ($Ladestatus == 5) { // Ladung beendet
            $Ladestatus = 4;
          }
          //  Kabelstatus
          //  0 = No cable plugged
          //  1 = Cable plugged
          //  3 = Cable plugged and locked
          //  5 = Cable plugged and locked. Vehicle not locked
          //  7 = Cable locked, ready for charging
          if ($DB4["Kabelstatus"] == 3) {
            $Kabelstatus = 7;
          }
          elseif ($DB4["Kabelstatus"] == 2) {
            $Kabelstatus = 3;
          }
          else {
            $Kabelstatus = $DB4["Kabelstatus"];
          }
          //  Abfrage der benutzten Phasen.
          //  Überschreibt den Phasen Eintrag in der wallbox.steuerung.ini
          if (isset($DB4["AnzPhasen"])) {
            if ($DB4["AnzPhasen"] == 3) {
              $PUI = (400 * 1.73); //  Wechselstromberechnung 3 Phasen
            }
            elseif ($DB4["AnzPhasen"] == 2) {
              $PUI = 400; //  Wechselstromberechnung 2 Phasen
            }
            else {
              $PUI = 230; //  Wechselstromberechnung 1 Phase
            }
          }
          $WhLadung = ($DB3["Wh_Ladevorgang"] / 1000);
          break;

        case 78:
          //  Innogy Wallbox
          //  Zugangskontrolle 0 = ohne Auth.  1 = mit RFID Karte
          //  RFID_Karte = 0 keine Karte aktiv
          //  aktueller Ladestrom = MaxAmpere
          //
          //  Ladestatus 1 = Nicht bereit
          //  Ladestatus 2 = Bereit zum Laden
          //  Ladestatus 3 = Es wird geladen
          //  Ladestatus 4 = Ladung beendet und Ladung unterbrochen
          //  Ladestatus 5 = Ladung unterbrochen
          $RFID_Karte = 0; // dummy
          // $DB4["Ladestatus"]; // Measuremnent Service A,B,C
          $StationBereit = $DB4["StationAktiv"]; // Measurement Service
          $Zugangskontrolle = 0; // dummy
          // Minimum Ladestärke bei dieser Wallbox
          $MaxAmpere = (round( $DB4["MaxAmpere"] ) * 1000); // Stromvorgabe
          if ($MaxAmpere < 6000) {
            $MaxAmpere = 6000;
          }
          $StromL1 = $DB7["Strom_R"];
          $StromL2 = $DB7["Strom_S"];
          $StromL3 = $DB7["Strom_T"];
          if ($DB4["Ladestatus"] == "B" and $StationBereit == 0 and $MaxAmpere == 0) { // Das Auto hat die Ladung unterbrochen.
            $Ladestatus = 4;
            log_schreiben( "Ladung beendet. Bitte das Ladekabel entriegeln / entfernen.", "", 4 );
          }
          if ($DB4["Ladestatus"] == "A") { // Bereit zur Ladung
            $Ladestatus = 1;
          }
          if ($DB4["Ladestatus"] == "B") { // Bereit zur Ladung
            $Ladestatus = 2;
          }
          if ($DB4["Ladestatus"] == "C") { // Das Auto wird geladen.
            $Ladestatus = 3;
          }
          if ($StationBereit == 1 and $DB4["Ladestatus"] == "B" and $MaxAmpere == 0) {
            // Es handelt sich um eine Pause. Warte auf wiedereinschaltung.
            $Ladestatus = 2;
          }
          //  Kabelstatus
          //  0 = No cable plugged
          //  1 = Cable plugged
          //  3 = Cable plugged and locked
          //  5 = Cable plugged and locked. Vehicle not locked
          //  7 = Cable locked, ready for charging
          if ($DB4["Ladekabel"] == 3) {
            $Kabelstatus = 7;
          }
          elseif ($DB4["Ladekabel"] == 2) {
            $Kabelstatus = 3;
          }
          else {
            $Kabelstatus = $DB4["Ladekabel"];
          }
          //  Automatische Abfrage der benutzten Phasen.          //  Überschreibt den Phasen Eintrag in der wallbox.steuerung.ini
          //
          if ($StromL1 > 1 and $StromL2 > 1 and $StromL3 > 1) {
            $PUI = (400 * 1.73); //  Wechselstromberechnung 3 Phasen
          }
          elseif (($StromL1 > 1 and $StromL2 > 1) or ($StromL1 > 1 and $StromL3 > 1) or ($StromL2 > 1 and $StromL3 > 1)) {
            $PUI = 400; //  Wechselstromberechnung 2 Phasen
          }
          else {
            $PUI = 230; //  Wechselstromberechnung 1 Phase
          }
          $WhLadung = $DB3["Wh_Ladevorgang"];
          break;

        default:
          $Ladestatus = 0;
          $Kabelstatus = 0;
          break;
      }

      /**************************************************************************
      //  Auslesen der user.config.php für das Batterie-Management-System
      //  Nur wenn auch der Eintrag in der INI Datei vorhanden ist.
      **************************************************************************/
      if (isset($INI["Geraete"]["BMS"])) {
        require ($Pfad."/".$INI["Geraete"]["BMS"]);
        // $DB5 = Datenbank des BMS  Measurement: Pack1 oder Batterie
        $bmsRegler = $Regler;
        $bmsGeraeteNummer = substr( $INI["Geraete"]["BMS"], 0, 1 );
        $bmsDatenbankname = $InfluxDBLokal;
        if ($bmsRegler == 15) {
          $ch = curl_init( 'http://localhost/query?db='.$bmsDatenbankname.'&precision=s&q='.urlencode( 'select * from Pack1 order by time desc limit 1' ));
        }
        elseif ($bmsRegler == 41) {
          $ch = curl_init( 'http://localhost/query?db='.$bmsDatenbankname.'&precision=s&q='.urlencode( 'select * from Service order by time desc limit 1' ));
        }
        else {
          $ch = curl_init( 'http://localhost/query?db='.$bmsDatenbankname.'&precision=s&q='.urlencode( 'select * from Batterie order by time desc limit 1' ));
        }
        $rc = datenbank( $ch );

        if (!isset($rc["JSON_Ausgabe"]["results"][0]["series"])) {
          log_schreiben( "Es fehlt die Datenbank '".$bmsDatenbankname."' oder sie ist leer.", "|- ", 1 );
          goto Ausgang;
        }
        for ($h = 1; $h < count( $rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"] ); $h++) {
          $DB5[$rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][$h]] = round($rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][$h],0);
        }
        log_schreiben( "Datenbank: '".$bmsDatenbankname."' ".print_r( $DB5, 1 ), "", 4 );
        switch ($bmsRegler) {

          case 2:
            //  STECA
            $SOC = $DB5["SOC"];
            break;

          case 6:
            //  BMC von Victron
            $SOC = $DB5["Kapazitaet"];
            break;

          case 13:
            //  Joulie-16
            $SOC = $DB5["SOC"];
            break;

          case 15:
          case 41:
            //  Pylontech US2000B
            $SOC = $DB5["SOC"];
            break;

          case 25:
            //  Sonnen Batterie
            $SOC = $DB5["SOC"];
            break;

          case 56:
            //  Huawei M0
            $SOC = $DB5["SOC"];
            break;

          case 57:
            //  DALY BMS
            $SOC = $DB5["SOC"];
            break;

          case 68:
            //  VARTA
            $SOC = $DB5["SOC"];
            break;

          case 83:
            //  JK BMS
            $SOC = $DB5["SOC"];
            break;

          default:
            $SOC = 100;
            break;
        }
      }
      if ($Ladestatus == 1) {
        log_schreiben( "Ladekabel nicht an beiden Seiten verriegelt oder kein Auto angeschlossen. Steuerung beendet....", "", 2 );
        goto Ausgang;
      }
      if ($wbSteuerung["wbSteuerung1"] == 1) { // Start der PV Ladung
        log_schreiben( "aktuelle Solarleistung - Eigenverbrauch: ".($Solarleistung - $Eigenverbrauch)." Watt", "", 3 );
      }
      if ($wbSteuerung["wbSteuerung2"] == 1) { // Start der PV Ladung
        log_schreiben( "aktuelle Solarleistung - Eigenverbrauch: ".($Solarleistung - $Eigenverbrauch)." Watt", "", 3 );
      }
      if ($wbSteuerung["wbSteuerung3"] == 1) { // Start der PV Ladung
      }
      if ($INI["Allgemein"]["Batterie"] == true) {
        log_schreiben( "aktueller SOC der Batterie: ".$SOC."%", "", 3 );
      }
      switch ($Ladestatus) {

        case 1:
          //  Ladestatus 1 = Nicht bereit
          log_schreiben( "Ladestation nicht bereit.", "", 3 );
          break;

        case 2:
          //  Ladestatus 2 = Bereit zum Laden
          log_schreiben( "Ladestation bereit zum Laden.", "", 3 );
          break;

        case 3:
          //  Ladestatus 3 = Es wird geladen
          log_schreiben( "Auto wird geladen.", "", 3 );
          break;

        case 4:
          //  Ladestatus 4 = Ladung beendet
          log_schreiben( "Ladung beendet. Stecker abziehen.", "", 3 );
          break;

        case 5:
          //  Ladestatus 5 = Ladung unterbrochen
          log_schreiben( "Ladung unterbrochen.", "", 3 );
          break;

        default:
          //  Ladestatus 5 = Ladung unterbrochen
          log_schreiben( "Unbekannter Ladestatus: ".$Ladestatus, "", 1 );
          break;
      }
      switch ($Kabelstatus) {

        case 0:
          //  0 = No cable plugged
          log_schreiben( "Kein Kabel angeschlossen.", "", 3 );
          break;

        case 1:
          //  1 = Cable plugged
          log_schreiben( "Kabel angeschlossen.", "", 3 );
          break;

        case 3:
          //  3 = Cable plugged and locked
          log_schreiben( "Kabel angeschlossen und verriegelt.", "", 3 );
          break;

        case 5:
          //  5 = Cable plugged and locked. Vehicle not locked
          log_schreiben( "Kabel angeschlossen und noch nicht verriegelt.", "", 3 );
          break;

        case 7:
          //  7 = Cable locked, ready for charging
          if ($wbRegler == "63") {
            log_schreiben( "Kabel angeschlossen", "", 3 );
          }
          else {
            log_schreiben( "Kabel angeschlossen und beidseitig verriegelt.", "", 3 );
          }
          break;

        default:
          //  unbekannter Kabelstatus
          log_schreiben( "Unbekannter Kabelstatus", "", 1 );
          break;
      }
      if ($Inselanlage) {
        log_schreiben( "Laut INI Datei ist es eine Inselanlage.", "", 3 );
      }

      /****************************************************************************
      //  User PHP Script, falls gewünscht oder nötig
      //  Hier kann man, wenn nötig, Manipulationen an den Daten vornehmen.
      ****************************************************************************/
      if (file_exists( "/var/www/html/wall-math.php" )) {
        include 'wall-math.php'; // Falls etwas neu berechnet werden muss.
      }
      log_schreiben( "Ladestatus: ".$Ladestatus, "", 3 );
      log_schreiben( "Ladepause: ".$Pause, "", 3 );
      log_schreiben( "StationBereit: ".$StationBereit, "", 3 );
      log_schreiben( "Intervall: ".$Intervall, "", 3 );
      log_schreiben( "Solarleistung: ".$Solarleistung, "", 3 );
      log_schreiben( "Kabelstatus: ".$Kabelstatus, "", 3 );

      /*************************************************************************
      //  Ladequelle: PV    Ladequelle: PV    Ladequelle: PV    Ladequelle: PV
      //  Ladequelle ist die  Solarerzeugung
      //  $Solarleistung
      //  $SOC
      //  $Ladestatus
      //  $Kabelstatus
      //  Ladestart, wenn Batterie über 80% voll und PV Leistung größer 1250 Watt
      //  Alle 10 Minuten wird überwacht ob mehr oder weniger PV Leistung zur
      //  Verfügung steht. Anpassung der Ladestromstärke
      //  Ladestop bei 50% SOC
      *************************************************************************/
      if ($wbSteuerung["wbSteuerung1"] == 1) { // Start der PV Ladung
        log_schreiben( "Ladequelle: PV-Module.", "", 3 );
        log_schreiben( "Ladestatus: ".$Ladestatus, "", 4 );
        if ($Kabelstatus == 7) {
          // Ladung kann beginnen
          if ($INI["PV-Quelle"]["MaxEnergie"] > 0) {
            log_schreiben( "Maximale Ladeenergie begrenzt auf: ".($INI["PV-Quelle"]["MaxEnergie"] / 1000)." kWh", "", 2 );
          }
          log_schreiben( "Eingestellter mindest Ladestrom (MinMilliAmpere): ".($INI["PV-Quelle"]["MinMilliAmpere"] / 1000)." A", "", 4 );
          if ($Ladestatus == 3) { // Es wird geladen.
            log_schreiben( "Bis jetzt geladen: ".round( $WhLadung, 0 )." Wh (Wenn ein Zähler angeschlossen ist)", "", 2 );
            log_schreiben( "aktueller Ladestrom: ".($MaxAmpere / 1000)." Ampere.", "", 2 );
            if ($SOC < $INI["PV-Quelle"]["BisSOC"] or ($NurBeiSonne == true and $Solarleistung <= ($Eigenverbrauch + $INI["PV-Quelle"]["MinSolarleistung"]))) {
              log_schreiben( "Unterbrechung wegen zu geringer Solarleistung oder SOC.", "", 2 );
              $ret = befehl_senden( "Pause", $wbRegler, $wbGeraeteNummer, 0 );
              if ($ret == true) {
                log_schreiben( "Ladungspause. SOC kleiner ".$INI["PV-Quelle"]["BisSOC"]."% oder zu wenig Sonne.", "", 3 );
                log_schreiben( "Pause, $wbRegler, $wbGeraeteNummer, 0", "", 4 );
              }
              // Flag setzen in die Datenbank "steuerung"  Measurement "Ladung"  Flag "Unterbrechung"
              $query = "Ladung Unterbrechung=1";
              $ch = curl_init( 'http://localhost/write?db=steuerung&precision=s' );
              $rc = datenbank( $ch, $query );
              log_schreiben( "Unterbrechung Flag gesetzt", "", 4 );
              $Pause = 1;
            }
            elseif ($Inselanlage) {
              if (date( "i" ) % $Intervall == 0) { // Alle 2 Minuten. Kann aber in "% 1"  oder "% 5" geändert werden.
                if ($Solarleistung > 1200) {
                  // Aktuelle Solarleistung - Eigenverbrauch umgerechnet in Milliampere + 1000 Milliampere
                  $Ladestrom = round( (((($Solarleistung - $Eigenverbrauch) / $PUI) * 1000) + 1000), - $Praezision ); // geändert 13.6.2021
                  if ($Ladestrom > $INI["PV-Quelle"]["MaxMilliAmpere"]) {
                    $Ladestrom = $INI["PV-Quelle"]["MaxMilliAmpere"];
                    log_schreiben( "Stromstärke auf MaxMilliAmpere begrenzt,".$INI["PV-Quelle"]["MaxMilliAmpere"], "", 3 );
                  }
                  if ($Ladestrom < round( $INI["PV-Quelle"]["MinMilliAmpere"], - $Praezision )) { // geändert 13.6.2021
                    $Ladestrom = round( $INI["PV-Quelle"]["MinMilliAmpere"], - $Praezision ); // geändert 13.6.2021
                    log_schreiben( "Stromstärke auf MinMilliAmpere gesetzt,".$INI["PV-Quelle"]["MinMilliAmpere"], "", 3 );
                  }
                }
                else {
                  $Ladestrom = $INI["PV-Quelle"]["MinMilliAmpere"];
                  log_schreiben( "Stromstärke auf MinMilliAmpere begrenzt,".$INI["PV-Quelle"]["MinMilliAmpere"], "", 3 );
                }
                if ($MaxAmpere <> $Ladestrom) {
                  // Ändern nur wenn der Strom nicht schon eingestellt ist.
                  $ret = befehl_senden( "Stromaenderung", $wbRegler, $wbGeraeteNummer, $Ladestrom );
                  if ($ret == true) {
                    log_schreiben( "Stromänderung. ".$Ladestrom." MaxAmpere: ".$MaxAmpere, "", 2 );
                    log_schreiben( "Stromänderung,$wbRegler,$wbGeraeteNummer,".$Ladestrom, "", 4 );
                  }
                  if ($Pause == 1) {
                    // Flag setzen in die Datenbank "Steuerung"  Measurement "Ladung"  Flag "unterbrechung"
                    $query = "Ladung Unterbrechung=0";
                    $ch = curl_init( 'http://localhost/write?db=steuerung&precision=s' );
                    $rc = datenbank( $ch, $query );
                    log_schreiben( "Unterbrechung Flag aufgehoben.", "", 4 );
                  }
                }
              }
            }
            elseif (date( "i" ) % $Intervall == 0) { // % 3 = alle 3 Minuten Prüfen ob die Ladung geändert werden muss.
              if ($Solarleistung - $Eigenverbrauch > 0) {
                // Aktuelle Solarleistung umgerechnet in Milliampere
                $Ladestrom = round( (((($Solarleistung - $Eigenverbrauch) / $PUI) * 1000)), - $Praezision ); // -3 auf -2 geändert 12.5.2021
                log_schreiben( "Ladestromstärke errechnet: ".$Ladestrom." Milliampere", "", 3 );
                if ($Ladestrom > $INI["PV-Quelle"]["MaxMilliAmpere"]) {
                  $Ladestrom = $INI["PV-Quelle"]["MaxMilliAmpere"];
                  log_schreiben( "Stromstärke auf MaxMilliAmpere begrenzt,".$INI["PV-Quelle"]["MaxMilliAmpere"], "", 3 );
                }
                if ($Ladestrom < round( $INI["PV-Quelle"]["MinMilliAmpere"], - $Praezision )) { // geändert 13.6.2021
                  $Ladestrom = round( $INI["PV-Quelle"]["MinMilliAmpere"], - $Praezision ); // geändert 13.6.2021
                  log_schreiben( "Stromstärke auf MinMilliAmpere gesetzt,".$INI["PV-Quelle"]["MinMilliAmpere"], "", 3 );
                }
              }
              else {
                $Ladestrom = $INI["PV-Quelle"]["MinMilliAmpere"];
                log_schreiben( "Stromstärke auf MinMilliAmpere begrenzt,".$INI["PV-Quelle"]["MinMilliAmpere"], "", 3 );
              }
              if ($MaxAmpere <> $Ladestrom) {
                log_schreiben( "MaxAmpere Ladestrom: ".$MaxAmpere." Milliampere", "", 4 );
                log_schreiben( "Eingestellter Ladestrom: ".$Ladestrom, "", 4 );
                // Ändern nur wenn der Strom nicht schon eingestellt ist.
                $ret = befehl_senden( "Stromaenderung", $wbRegler, $wbGeraeteNummer, $Ladestrom );
                if ($ret == true) {
                  log_schreiben( "Stromänderung. Neu:".$Ladestrom." Vorher: ".$MaxAmpere, "", 2 );
                  log_schreiben( "Stromänderung,$wbRegler,$wbGeraeteNummer,".$Ladestrom, "", 4 );
                }
                if ($Pause == 1) {
                  // Flag setzen in die Datenbank "Steuerung"  Measurement "Ladung"  Flag "unterbrechung"
                  $query = "Ladung Unterbrechung=0";
                  $ch = curl_init( 'http://localhost/write?db=steuerung&precision=s' );
                  $rc = datenbank( $ch, $query );
                  log_schreiben( "Unterbrechung Flag aufgehoben.", "", 4 );
                }
              }
            }
          }
          elseif ($Ladestatus == 5) { // Ladung wurde unterbrochen.
            //  Wird nicht bei allen Wallboxtypen benutzt
            log_schreiben( "Warte auf Aktivierung durch das Auto, aktuelle Solarleistung: ".$Solarleistung." Watt", "", 3 );
          }
          elseif ($Ladestatus == 4) { // Ladung wurde beendet.
            log_schreiben( "Ladung unterbrochen oder vom Auto / Wallbox beendet.", "", 2 );
          }
          elseif ($Ladestatus == 2) { // Ladestatus 2 -  Ladung könnte beginnen
            // Sind die Voraussetzungen erfüllt?
            if ($INI["PV-Quelle"]["Sonnenaufgang"] == 1 and date( "H:i" ) > date( "H:i", ($Sonnenaufgang + 7200)) and date( "H" ) < 18) {
              log_schreiben( "Es ist tagsüber, mehr als 1 Stunde nach Sonnenaufgang.", "", 3 );
              $StartLadung = true;
            }
            elseif ($INI["PV-Quelle"]["BisUhrzeit"] < $INI["PV-Quelle"]["VonUhrzeit"] and (date( "H:i" ) >= $INI["PV-Quelle"]["VonUhrzeit"] or date( "H:i" ) < $INI["PV-Quelle"]["BisUhrzeit"])) {
              log_schreiben( "Nachtschaltung: ".date( "H:i" ), "", 3 );
              $StartLadung = true;
            }
            elseif (date( "H:i" ) > $INI["PV-Quelle"]["VonUhrzeit"] and date( "H:i" ) < $INI["PV-Quelle"]["BisUhrzeit"]) {
              log_schreiben( "Die Einschaltzeit stimmt: ".date( "H:i" ), "", 3 );
              $StartLadung = true;
            }
            else {
              log_schreiben( "Die Einschaltzeit stimmt nicht überein.", "", 2 );
              $StartLadung = false;
            }
            if ($NurBeiSonne == true and $Solarleistung <= ($Eigenverbrauch + $INI["PV-Quelle"]["MinSolarleistung"])) {
              log_schreiben( "Es soll nur bei Sonne geladen werden.", "", 2 );
              $StartLadung = false;
            }
            if ($Solarleistung >= ($INI["PV-Quelle"]["MinSolarleistung"] + $INI["PV-Quelle"]["Eigenverbrauch"]) and $StartLadung == true) {
              log_schreiben( "Es steht genügend Solarleistung zur Verfügung: ".$Solarleistung." (MinSolarleistung + Eigenverbrauch)", "", 3 );
              if ($SOC >= $INI["PV-Quelle"]["AbSOC"]) {
                log_schreiben( "Die Batteriekapazität ist ausreichend: ".$SOC, "", 3 );
                $ret = befehl_senden( "Start", $wbRegler, $wbGeraeteNummer, $INI["PV-Quelle"]["MinMilliAmpere"] );
                if ($ret == true) {
                  log_schreiben( "Start Ladung mit: ".($INI["PV-Quelle"]["MinMilliAmpere"] / 1000)." A", "", 2 );
                  log_schreiben( "Start,$wbRegler,$wbGeraeteNummer,".$INI["PV-Quelle"]["MinMilliAmpere"], "", 4 );
                  // Flag zurück setzen in die Datenbank "Steuerung"  Measurement "Ladung"  Flag "unterbrechung"
                  $query = "Ladung Unterbrechung=0";
                  $ch = curl_init( 'http://localhost/write?db=steuerung&precision=s' );
                  $rc = datenbank( $ch, $query );
                  log_schreiben( "Unterbrechung Flag aufgehoben.", "", 4 );
                }
                if ($INI["PV-Quelle"]["MaxEnergie"] > 0) {
                  $ret = befehl_senden( "MaxEnergie", $wbRegler, $wbGeraeteNummer, ($INI["PV-Quelle"]["MaxEnergie"]));
                  // $ret = false;
                  if ($ret == true) {
                    log_schreiben( "Maximale Energiemenge laut INI Datei setzen. ", "", 3 );
                    log_schreiben( "MaxEnergie,$wbRegler,$wbGeraeteNummer,".$INI["PV-Quelle"]["MaxEnergie"], "", 4 );
                  }
                }
                else {
                  log_schreiben( "Option Maximale Energiemenge ist ausgeschaltet.", "", 3 );
                }
              }
              else {
                log_schreiben( "Die Batterie ist nicht voll genug. SOC: ".$SOC." % soll aber mindestens ".$INI["PV-Quelle"]["AbSOC"]." % sein.", "", 3 );
              }
            }
            else {
              log_schreiben( "Die Minimale Solarleistung von ".$INI["PV-Quelle"]["MinSolarleistung"]." Watt ist noch nicht erreicht.", "", 3 );
            }
          }
          else {
            log_schreiben( "Wallbox nicht bereit.", "", 3 );
          }
        }
        elseif ($Ladestatus == 4 and $Pause == 0) { // Ladung wurde beendet.
          if ($INI["Allgemein"]["Neutral"]) {
            log_schreiben( "Ladeauswahl auf Neutral gestellt.", "", 4 );
            $query = "Wallbox wbSteuerung1=0,wbSteuerung2=0,wbSteuerung3=0";
            $ch = curl_init( 'http://localhost/write?db=steuerung&precision=s' );
            $rc = datenbank( $ch, $query );
            log_schreiben( print_r( $rc, 1 ), "", 4 );
          }
        }
        else {
          log_schreiben( "Kabel ist nicht angeschlossen oder nicht verriegelt.", "", 2 );
          goto Ausgang;
        }
      }

      /*************************************************************************
      //  Ladequelle: Batterie    Ladequelle: Batterie    Ladequelle: Batterie
      //  Ladequelle ist die Batterie des Wechselrichters
      //
      //  $SOC
      //  $Ladestatus
      //  $Kabelstatus
      //  Batterie kann entladen werden. Auto wird fest mit 'MinMilliAmpere'
      //  geladen. Wenn SOC < 50% wird Ladung stoppen.
      *************************************************************************/
      if ($wbSteuerung["wbSteuerung2"] == 1) {
        log_schreiben( "Ladequelle Solarbatterie.", "", 2 );
        log_schreiben( "Ladestrom (MinMilliAmpere): ".($INI["Batterie-Quelle"]["MinMilliAmpere"] / 1000)." A", "", 4 );
        log_schreiben( "Geladen wird bis SOC ".$BatterieKap60."% mit ".($INI["Batterie-Quelle"]["MaxMilliAmpere"] / 1000)." A", "", 3 );
        log_schreiben( "Ladestatus: ".$Ladestatus, "", 4 );
        if ($Kabelstatus == 7) {
          if ($INI["Batterie-Quelle"]["MaxEnergie"] > 0) {
            log_schreiben( "Maximale Ladeenergie begrenzt auf: ".($INI["Batterie-Quelle"]["MaxEnergie"] / 1000)." kWh", "", 3 );
          }
          if ($Ladestatus == 3) { // Es wird geladen.
            log_schreiben( "Bis jetzt geladen: ".round( $WhLadung, 0 )." Wh (Wenn ein Zähler angeschlossen ist)", "", 2 );
            log_schreiben( "aktueller Ladestrom: ".($MaxAmpere / 1000)." Ampere.", "", 2 );
            if ($SOC <= $INI["Batterie-Quelle"]["BisSOC"] and $INI["Batterie-Quelle"]["BisSOC"] != 0) {
              $ret = befehl_senden( "Pause", $wbRegler, $wbGeraeteNummer, 0 );
              if ($ret == true) {
                log_schreiben( "Ladungspause. ", "", 3 );
                log_schreiben( "Pause, $wbRegler, $wbGeraeteNummer, 0", "", 4 );
              }
              // Flag setzen in die Datenbank "Steuerung"  Measurement "Ladung"  Flag "unterbrechung"
              $query = "Ladung Unterbrechung=1";
              $ch = curl_init( 'http://localhost/write?db=steuerung&precision=s' );
              $rc = datenbank( $ch, $query );
              log_schreiben( "Unterbrechung Flag gesetzt", "", 4 );
              log_schreiben( "Stop Ladung. Die Batteriekapazität ist nicht ausreichend: ".$SOC." %!", "", 2 );
            }
            elseif ($INI["Batterie-Quelle"]["BisSOC"] == 0) {
              log_schreiben( "Die Batterieüberwachung ist ausgeschaltet. BisSOC = 0", "", 3 );
            }
            else {
              log_schreiben( "Es wird geladen. Die Batteriekapazität ist ausreichend: ".$SOC."%", "", 3 );
            }
            if (date( "i" ) % 4 == 0) {
              // alle 4 Minuten Prüfen ob die Ladung erhöht werden kann.
              //  60 - 90 %
              if ($SOC <= $BatterieKap60) {
                // Batterie SOC kleiner 60% (default)
                if ($MaxAmpere <> $INI["Batterie-Quelle"]["MinMilliAmpere"]) {
                  $Ladestrom = $INI["Batterie-Quelle"]["MinMilliAmpere"];
                  log_schreiben( "Die Batterie hat weniger als ".$BatterieKap60."% SOC. Es wird mit MinMilliAmpere ".($INI["Batterie-Quelle"]["MinMilliAmpere"] / 1000)." A geladen.", "", 2 );
                }
              }
              if ($SOC <= 90) {
                // Min Ampere + (Differenz (Min : MAX)/2 )
                if ($MaxAmpere <> ((($INI["Batterie-Quelle"]["MaxMilliAmpere"] - $INI["Batterie-Quelle"]["MinMilliAmpere"]) / 2) + $INI["Batterie-Quelle"]["MinMilliAmpere"])) {
                  $Ladestrom = ((($INI["Batterie-Quelle"]["MaxMilliAmpere"] - $INI["Batterie-Quelle"]["MinMilliAmpere"]) / 2) + $INI["Batterie-Quelle"]["MinMilliAmpere"]);
                  $Ladestrom = round( $Ladestrom, - 3 );
                }
              }
              else {
                // 100%
                $Ladestrom = $INI["Batterie-Quelle"]["MaxMilliAmpere"];
              }
              if ($Ladestrom > 0) {
                $ret = befehl_senden( "Stromaenderung", $wbRegler, $wbGeraeteNummer, $Ladestrom );
                if ($ret == true) {
                  log_schreiben( "Stromänderung. ".$Ladestrom, "", 2 );
                  log_schreiben( "Stromäbderung,$wbRegler,$wbGeraeteNummer,".$Ladestrom, "", 4 );
                }
              }
            }
          }
          elseif ($Ladestatus == 5) { // Ladung wurde unterbrochen.
            //  Wird nicht bei allen Wallboxtypen benutzt
            log_schreiben( "Ladung ist unterbrochen, aktuelle Solarleistung: ".$Solarleistung." Watt", "", 3 );
          }
          elseif ($Ladestatus == 4) { // Ladung wurde beendet.
            log_schreiben( "Ladung vom Fahrzeug oder Wallbox beendet. Bitte das Kabel entfernen.", "", 2 );
          }
          elseif ($Ladestatus == 2) {
            // Sind die Voraussetzungen erfüllt?
            if ($INI["Batterie-Quelle"]["Sonnenaufgang"] == 1 and date( "H:i" ) > date( "H:i", ($Sonnenaufgang + 7200)) and date( "H" ) < 18) {
              log_schreiben( "Es ist tagsüber, mehr als 1 Stunde nach Sonnenaufgang.", "", 3 );
              $StartLadung = true;
            }
            elseif ($INI["Batterie-Quelle"]["BisUhrzeit"] < $INI["Batterie-Quelle"]["VonUhrzeit"] and (date( "H:i" ) >= $INI["Batterie-Quelle"]["VonUhrzeit"] or date( "H:i" ) < $INI["Batterie-Quelle"]["BisUhrzeit"])) {
              log_schreiben( "Nachtschaltung: ".date( "H:i" ), "", 3 );
              $StartLadung = true;
            }
            elseif (date( "H:i" ) > $INI["Batterie-Quelle"]["VonUhrzeit"] and date( "H:i" ) < $INI["Batterie-Quelle"]["BisUhrzeit"]) {
              log_schreiben( "Die Einschaltzeit stimmt: ".date( "H:i" ), "", 3 );
              $StartLadung = true;
            }
            else {
              log_schreiben( "Die Einschaltzeit stimmt nicht überein.", "", 2 );
              $StartLadung = false;
            }
            if ($SOC >= $INI["Batterie-Quelle"]["AbSOC"] and $StartLadung == true) {
              log_schreiben( "Die Batterie ist voll genug. SOC: ".$SOC, "", 2 );
              $ret = befehl_senden( "Start", $wbRegler, $wbGeraeteNummer, $INI["Batterie-Quelle"]["MaxMilliAmpere"] );
              if ($ret == true) {
                log_schreiben( "Start Ladung  mit: ".($INI["Batterie-Quelle"]["MinMilliAmpere"] / 1000)." A", "", 2 );
                log_schreiben( "Start,$wbRegler,$wbGeraeteNummer,".$INI["Batterie-Quelle"]["MaxMilliAmpere"], "", 4 );
              }
              if ($INI["Batterie-Quelle"]["MaxEnergie"] > 0) {
                $ret = befehl_senden( "MaxEnergie", $wbRegler, $wbGeraeteNummer, ($INI["Batterie-Quelle"]["MaxEnergie"]));
                if ($ret == true) {
                  log_schreiben( "Maximale Energiemenge setzen. ", "", 2 );
                  log_schreiben( "MaxEnergie,$wbRegler,$wbGeraeteNummer,".$INI["Batterie-Quelle"]["MaxEnergie"], "", 4 );
                  // Flag setzen in die Datenbank "Steuerung"  Measurement "Ladung"  Flag "unterbrechung"
                  $query = "Ladung Unterbrechung=0";
                  $ch = curl_init( 'http://localhost/write?db=steuerung&precision=s' );
                  $rc = datenbank( $ch, $query );
                  log_schreiben( "Unterbrechung Flag aufgehoben.", "", 4 );
                }
              }
              else {
                log_schreiben( "Option Maximale Energiemenge ausgeschaltet.", "", 3 );
              }
            }
            else {
              log_schreiben( "Die Batterie ist nicht voll genug. SOC: ".$SOC." % soll aber mindestens ".$INI["Batterie-Quelle"]["AbSOC"]." % sein.", "", 2 );
            }
          }
          else {
            log_schreiben( "Wallbox nicht bereit.", "", 2 );
          }
        }
        elseif ($Ladestatus == 4 and $Pause == 0) { // Ladung wurde beendet.
          if ($INI["Allgemein"]["Neutral"]) {
            //  Ladeprogramm auf NEUTRAL gesetzt.
            log_schreiben( "Ladeauswahl auf Neutral gestellt.", "", 4 );
            $query = "Wallbox wbSteuerung1=0,wbSteuerung2=0,wbSteuerung3=0";
            $ch = curl_init( 'http://localhost/write?db=steuerung&precision=s' );
            $rc = datenbank( $ch, $query );
            log_schreiben( print_r( $rc, 1 ), "", 4 );
          }
        }
        else {
          log_schreiben( "Kabel ist nicht angeschlossen oder nicht verriegelt.", "", 2 );
          goto Ausgang;
        }
      }

      /*************************************************************************
      //  Ladequelle: Netzspannung  Ladequelle: Netzspannung  Ladequelle: Netz
      //  Ladequelle ist die Netzspannung
      //
      //  $SOC
      //  $Ladestatus
      //  $Kabelstatus
      //
      //  Es wird mit Maximaler Ladeleistung geladen, bis die Batterie SOC 70%
      //  erreicht, dann wird mit Minimaler Leistung geladen
      //  Außerdem kann die Maximale Gesamtladung angegeben werden. (MaxEnergie)
      *************************************************************************/
      if ($wbSteuerung["wbSteuerung3"] == 1) {
        log_schreiben( "Ladequelle Netz.", "", 2 );
        log_schreiben( "Ladestrom (MinMilliAmpere): ".($INI["Netz-Quelle"]["MinMilliAmpere"] / 1000)." A", "", 4 );
        log_schreiben( "Geladen wird bis SOC ".$NetzKap60."% mit ".($INI["Netz-Quelle"]["MaxMilliAmpere"] / 1000)." A", "", 3 );
        log_schreiben( "Ladestatus: ".$Ladestatus, "", 4 );
        if ($Kabelstatus == 7) {
          if ($INI["Netz-Quelle"]["MaxEnergie"] > 0) {
            log_schreiben( "Maximale Ladeenergie begrenzt auf: ".($INI["Netz-Quelle"]["MaxEnergie"] / 1000)." kWh", "", 2 );
          }
          if ($Ladestatus == 3) { // Es wird geladen.
            log_schreiben( "Bis jetzt geladen: ".round( $WhLadung, 0 )." Wh (Wenn ein Zähler angeschlossen ist)", "", 2 );
            log_schreiben( "aktueller Ladestrom: ".($MaxAmpere / 1000)." Ampere.", "", 2 );
            if ($SOC <= $INI["Netz-Quelle"]["BisSOC"] and $INI["Netz-Quelle"]["BisSOC"] != 0) {
              $ret = befehl_senden( "Pause", $wbRegler, $wbGeraeteNummer, 0 );
              if ($ret == true) {
                log_schreiben( "Ladungspause. ", "", 4 );
                log_schreiben( "Pause, $wbRegler, $wbGeraeteNummer, 0", "", 4 );
                // Flag setzen in die Datenbank "Steuerung"  Measurement "Ladung"  Flag "unterbrechung"
                $query = "Ladung Unterbrechung=1";
                $ch = curl_init( 'http://localhost/write?db=steuerung&precision=s' );
                $rc = datenbank( $ch, $query );
                log_schreiben( "Unterbrechung Flag gesetzt", "", 4 );
              }
              log_schreiben( "Stop Ladung. Die Batteriekapazität ist nicht ausreichend: ".$SOC." %!", "", 2 );
            }
            elseif ($INI["Netz-Quelle"]["BisSOC"] == 0) {
              log_schreiben( "Die Batterieüberwachung ist ausgeschaltet. BisSOC = 0", "", 3 );
            }
            else {
              log_schreiben( "Die Batteriekapazität ist ausreichend: ".$SOC."%", "", 3 );
            }
            log_schreiben( "SOC ".$SOC."% , NetzKap60 ".$NetzKap60."% ", "", 4 );
            if (date( "i" ) % 4 == 0) {
              if ($INI["Netz-Quelle"]["BisSOC"] == 0) {
                $Ladestrom = $INI["Netz-Quelle"]["MaxMilliAmpere"];
                log_schreiben( "Die Batterieüberwachung ist ausgeschaltet. BisSOC = 0", "", 2 );
              }
              elseif ($SOC >= $NetzKap60) {
                if ($MaxAmpere <> $INI["Netz-Quelle"]["MaxMilliAmpere"]) {
                  $Ladestrom = $INI["Netz-Quelle"]["MaxMilliAmpere"];
                  log_schreiben( "Die Batterie hat mehr als ".$NetzKap60."% SOC. Es wird mit MaxMilliAmpere ".($INI["Netz-Quelle"]["MaxMilliAmpere"] / 1000)." A geladen.", "", 2 );
                }
              }
              else {
                if ($MaxAmpere <> $INI["Netz-Quelle"]["MinMilliAmpere"]) {
                  $Ladestrom = $INI["Netz-Quelle"]["MinMilliAmpere"];
                  log_schreiben( "Die Batterie hat weniger als ".$NetzKap60."% SOC. Es wird mit MinMilliAmpere ".($INI["Netz-Quelle"]["MinMilliAmpere"] / 1000)." A geladen.", "", 2 );
                }
              }
              if ($Ladestrom > 0) {
                $ret = befehl_senden( "Stromaenderung", $wbRegler, $wbGeraeteNummer, $Ladestrom );
                if ($ret == true) {
                  log_schreiben( "Stromänderung. ".$Ladestrom, "", 2 );
                  log_schreiben( "Stromäbderung,$wbRegler,$wbGeraeteNummer,".$Ladestrom, "", 4 );
                }
              }
            }
          }
          elseif ($Ladestatus == 5) { // Ladung wurde unterbrochen.
            //  Wird nicht bei allen Wallboxtypen benutzt
            log_schreiben( "Ladung ist unterbrochen, aktuelle Solarleistung: ".$Solarleistung." Watt", "", 3 );
          }
          elseif ($Ladestatus == 4) { // Ladung wurde beendet.
            log_schreiben( "Ladung vom Fahrzeug oder Wallbox beendet. Bitte das Kabel entfernen.", "", 2 );
          }
          elseif ($Ladestatus == 2) {
            // Sind die Voraussetzungen erfüllt?
            if ($INI["Netz-Quelle"]["Sonnenaufgang"] == 1 and date( "H:i" ) > date( "H:i", ($Sonnenaufgang + 7200)) and date( "H" ) < 18) {
              log_schreiben( "Es ist tagsüber, mehr als 1 Stunde nach Sonnenaufgang.", "", 3 );
              $StartLadung = true;
            }
            elseif ($INI["Netz-Quelle"]["BisUhrzeit"] < $INI["Netz-Quelle"]["VonUhrzeit"] and (date( "H:i" ) >= $INI["Netz-Quelle"]["VonUhrzeit"] or date( "H:i" ) < $INI["Netz-Quelle"]["BisUhrzeit"])) {
              log_schreiben( "Nachtschaltung: ".date( "H:i" ), "", 3 );
              $StartLadung = true;
            }
            elseif (date( "H:i" ) > $INI["Netz-Quelle"]["VonUhrzeit"] and date( "H:i" ) < $INI["Netz-Quelle"]["BisUhrzeit"]) {
              log_schreiben( "Die Einschaltzeit stimmt: ".date( "H:i" ), "", 3 );
              $StartLadung = true;
            }
            else {
              log_schreiben( "Die Einschaltzeit stimmt nicht überein.", "", 2 );
              $StartLadung = false;
            }
            if ($SOC >= $INI["Netz-Quelle"]["AbSOC"] and $StartLadung == true) {
              log_schreiben( "Die Batterie ist voll genug. SOC: ".$SOC, "", 3 );
              $ret = befehl_senden( "Start", $wbRegler, $wbGeraeteNummer, $INI["Netz-Quelle"]["MaxMilliAmpere"] );
              if ($ret == true) {
                log_schreiben( "Start Ladung  mit: ".($INI["Netz-Quelle"]["MinMilliAmpere"] / 1000)." A", "", 2 );
                log_schreiben( "Start,$wbRegler,$wbGeraeteNummer,".$INI["Netz-Quelle"]["MaxMilliAmpere"], "", 4 );
                // Flag setzen in die Datenbank "Steuerung"  Measurement "Ladung"  Flag "unterbrechung"
                $query = "Ladung Unterbrechung=0";
                $ch = curl_init( 'http://localhost/write?db=steuerung&precision=s' );
                $rc = datenbank( $ch, $query );
                log_schreiben( "Unterbrechung Flag aufgehoben.", "", 4 );
              }
              if ($INI["Netz-Quelle"]["MaxEnergie"] > 0) {
                $ret = befehl_senden( "MaxEnergie", $wbRegler, $wbGeraeteNummer, ($INI["Netz-Quelle"]["MaxEnergie"]));
                if ($ret == true) {
                  log_schreiben( "Maximale Energiemenge setzen: ".$INI["Netz-Quelle"]["MaxEnergie"]." Wh", "", 2 );
                  log_schreiben( "MaxEnergie,$wbRegler,$wbGeraeteNummer,".$INI["Netz-Quelle"]["MaxEnergie"], "", 4 );
                }
              }
              else {
                log_schreiben( "Option Maximale Energiemenge ausgeschaltet.", "", 3 );
              }
            }
            else {
              log_schreiben( "Die Batterie ist nicht voll genug. SOC: ".$SOC." % soll aber mindestens ".$INI["Netz-Quelle"]["AbSOC"]." % sein.", "", 3 );
            }
          }
          else {
            log_schreiben( "Wallbox nicht bereit.", "", 2 );
          }
        }
        elseif ($Ladestatus == 4 and $Pause == 0) { // Ladung wurde beendet.
          if ($INI["Allgemein"]["Neutral"]) {
            //  Ladeprogramm auf NEUTRAL gesetzt.
            log_schreiben( "Ladeauswahl auf Neutral gestellt.", "", 4 );
            $query = "Wallbox wbSteuerung1=0,wbSteuerung2=0,wbSteuerung3=0";
            $ch = curl_init( 'http://localhost/write?db=steuerung&precision=s' );
            $rc = datenbank( $ch, $query );
            log_schreiben( print_r( $rc, 1 ), "", 4 );
          }
        }
        else {
          log_schreiben( "Kabel ist nicht angeschlossen oder nicht verriegelt.", "", 2 );
          goto Ausgang;
        }
      }
    }
    log_schreiben( "|", "ENDE", 3 );
  }
  else {
    log_schreiben( "Es ist keine ".$i.".wallbox.steuerung.ini vorhanden.", "", 4 );
    continue;
  }
  Ausgang:
}
log_schreiben( "---------------------------------------------------------", "ENDE", 1 );
return;

/**************************************************************************
//
//    Funktionen       Funktionen       Funktionen       Funktionen
//
**************************************************************************/
/**************************************************************************
//  Log Eintrag in die Logdatei schreiben
//  $LogMeldung = Die Meldung ISO Format
//  $Loglevel=2   Loglevel 1-4   4 = Trace
**************************************************************************/
function log_schreiben( $LogMeldung, $Titel = "", $Loglevel = 3, $UTF8 = 0 ) {
  global $Tracelevel, $Pfad;
  $LogDateiName = $Pfad."/../log/wallbox.log";
  if (strlen( $Titel ) < 4) {
    switch ($Loglevel) {

      case 1:
        $Titel = "ERRO";
        break;

      case 2:
        $Titel = "WARN";
        break;

      case 3:
        $Titel = "INFO";
        break;

      default:
        $Titel = "    ";
        break;
    }
  }
  if ($Loglevel <= $Tracelevel) {
    if ($UTF8) {
      $LogMeldung = utf8_encode( $LogMeldung );
    }
    if ($handle = fopen( $LogDateiName, 'a' )) {
      //  Schreibe in die geöffnete Datei.
      //  Bei einem Fehler bis zu 3 mal versuchen.
      for ($i = 1; $i < 4; $i++) {
        $rc = fwrite( $handle, date( "d.m. H:i:s" )." ".substr( $Titel, 0, 4 )." ".$LogMeldung."\n" );
        if ($rc) {
          break;
        }
        sleep( 1 );
      }
      fclose( $handle );
    }
  }
  return true;
}

function datenbank( $ch, $query = "" ) {
  $Ergebnis = array();
  $Ergebnis["Ausgabe"] = false;
  curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
  curl_setopt( $ch, CURLOPT_TIMEOUT, 15 ); //timeout in second s
  curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 12 );
  curl_setopt( $ch, CURLOPT_PORT, 8086 );
  curl_setopt( $ch, CURLOPT_POSTFIELDS, $query );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
  $Ergebnis["result"] = curl_exec( $ch );
  $Ergebnis["rc_info"] = curl_getinfo( $ch );
  $Ergebnis["JSON_Ausgabe"] = json_decode( $Ergebnis["result"], true, 10 );
  $Ergebnis["errorno"] = curl_errno( $ch );
  if ($Ergebnis["rc_info"]["http_code"] == 200 or $Ergebnis["rc_info"]["http_code"] == 204) {
    $Ergebnis["Ausgabe"] = true;
  }
  curl_close( $ch );
  unset($ch);
  return $Ergebnis;
}

function befehl_senden( $Befehl, $Regler, $GeraeteID, $Wert ) {
  // Input in Milliampere
  // Input und Output  1000 = 1 Ampere
  switch ($Regler) {

    case 29:
      // gesendet wird in Ampere
      if ($Befehl == "Start") {
        $wbBefehl = "stp_0\nalw_1\namp_".($Wert / 1000)."\n";
      }
      if ($Befehl == "Stromaenderung") {
        $wbBefehl = "amp_".round( $Wert / 1000 )."\n";
        // $wbBefehl = "amx_".round($Wert / 1000)."\n";
      }
      if ($Befehl == "Stop") {
        $wbBefehl = "alw_0\n";
      }
      if ($Befehl == "Pause") {
        $wbBefehl = "alw_0\n";
      }
      if ($Befehl == "MaxEnergie") {
        $wbBefehl = "stp_2\ndwo_".($Wert / 100)."\n";
      }
      if ($Befehl == "Reboot") {
        $wbBefehl = "rst_1\n";
      }
      break;

    case 30:
      // gesendet wird in Milliampere  KEBA Wallbox
      if ($Befehl == "Start") {
        $wbBefehl = "ena_1\ncurrtime_".$Wert."\n";
      }
      if ($Befehl == "Stromaenderung") {
        $wbBefehl = "curr_".$Wert."\n";
      }
      if ($Befehl == "Stop") {
        $wbBefehl = "ena_0\n";
      }
      if ($Befehl == "Pause") {
        $wbBefehl = "currtime_0_1\n";
      }
      if ($Befehl == "MaxEnergie") {
        $wbBefehl = "setenergy_".($Wert * 10)."\n";
      }
      break;

    case 35:
    case 47:
      //  gesendet wird in Milliampere
      if ($Befehl == "Start") {
        $wbBefehl = "start_1\namp_".$Wert."\n";
      }
      if ($Befehl == "Stromaenderung") {
        $wbBefehl = "amp_".$Wert."\n";
      }
      if ($Befehl == "Stop") {
        $wbBefehl = "start_0\n";
      }
      if ($Befehl == "Pause") {
        $wbBefehl = "start_0\n";
      }
      if ($Befehl == "MaxEnergie") {
        // gibt es bei dieser Wallbox nicht
        $wbBefehl = "";
      }
      if ($Befehl == "Unlock") {
        $wbBefehl = "unlock_1\n";
      }
      break;

    case 37: //Simple EVSE WiFi
      // gesendet wird in Ampere
      if ($Befehl == "Start") {
        $wbBefehl = "current_".($Wert / 1000)."\nactive_true\n";
      }
      if ($Befehl == "Stromaenderung") {
        $wbBefehl = "current_".($Wert / 1000)."\n";
      }
      if ($Befehl == "Stop") {
        $wbBefehl = "active_false\n";
      }
      if ($Befehl == "Pause") {
        $wbBefehl = "active_false\n";
      }
      break;

    case 39: // openWB
      // gesendet wird in Ampere
      if ($Befehl == "Start") {
        $wbBefehl = "start_".($Wert / 1000)."\n";
      }
      if ($Befehl == "Stromaenderung") {
        $wbBefehl = "amp_".($Wert / 1000)."\n";
      }
      if ($Befehl == "Stop") {
        $wbBefehl = "start_0\n";
      }
      if ($Befehl == "Pause") {
        $wbBefehl = "pause\n";
      }
      if ($Befehl == "MaxEnergie") { // gesendet in kW
        $wbBefehl = "maxenergy_".($Wert / 100)."\n";
      }
      break;

    case 44: // Webasto
      // gesendet wird in Ampere
      if ($Befehl == "Start") {
        $wbBefehl = "amp_".round( $Wert, 0 )."\n";
      }
      if ($Befehl == "Stromaenderung") {
        $wbBefehl = "amp_".round( $Wert, 0 )."\n";
      }
      if ($Befehl == "Stop") {
        $wbBefehl = "stop_1\n";
      }
      if ($Befehl == "Pause") {
        $wbBefehl = "amp_0\n";
      }
      if ($Befehl == "MaxEnergie") {
        $wbBefehl = "setenergy_".($Wert)."\n";
      }
      break;

    case 60: // Hard Barth
      // gesendet wird in Ampere
      if ($Befehl == "Start") {
        $wbBefehl = "start\n";
      }
      if ($Befehl == "Stromaenderung") {
        $wbBefehl = "ampere_".round( ($Wert / 1000), 0 )."\n";
      }
      if ($Befehl == "Stop") {
        $wbBefehl = "stop\n";
      }
      if ($Befehl == "Pause") {
        $wbBefehl = "stop\n";
      }
      if ($Befehl == "MaxEnergie") {
        // Noch nicht implementiert.
        $wbBefehl = "setenergy_".($Wert)."\n";
      }
      break;

    case 63: // cFos Wallbox
      // gesendet wird in 0,1 Ampere
      if ($Befehl == "Start") {
        $wbBefehl = "start\n";
      }
      if ($Befehl == "Stromaenderung") {
        $wbBefehl = "amp_".round( ($Wert / 100), 0 )."\n";
      }
      if ($Befehl == "Stop") {
        $wbBefehl = "stop\n";
      }
      if ($Befehl == "Pause") {
        $wbBefehl = "stop\n";
      }
      if ($Befehl == "MaxEnergie") {
        // Noch nicht implementiert.
        $wbBefehl = "setenergy_".($Wert)."\n";
      }
      break;

    case 69: // VESTEL EVC04 Wallbox
      // gesendet wird in  Ampere
      if ($Befehl == "Start") {
        $wbBefehl = "start\n";
      }
      if ($Befehl == "Stromaenderung") {
        $wbBefehl = "amp_".round( ($Wert / 1000), 0 )."\n";
      }
      if ($Befehl == "Stop") {
        $wbBefehl = "stop\n";
      }
      if ($Befehl == "Pause") {
        $wbBefehl = "stop\n";
      }
      break;

    case 78: // Inoggy Wallbox
      // gesendet wird in  Ampere
      if ($Befehl == "Start") {
        $wbBefehl = "start\n";
      }
      if ($Befehl == "Stromaenderung") {
        $wbBefehl = "amp_".round( ($Wert / 1000), 0 )."\n";
      }
      if ($Befehl == "Stop") {
        $wbBefehl = "stop\n";
      }
      if ($Befehl == "Pause") {
        $wbBefehl = "stop\n";
      }
      break;

    default:
      break;
  }
  // $GeraeteID = 20;
  $fh = fopen( "/var/www/pipe/".$GeraeteID.".befehl.steuerung", 'a' );
  fwrite( $fh, $wbBefehl );
  fclose( $fh );
  return true;
}

?>