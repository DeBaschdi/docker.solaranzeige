<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2021]  [Ulrich Kunz]
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
//  Es dient der Steuerung von Wärmepumpen und Heizungselementen
//  In der SQLite3 Datenbank "automation.sqlite3" sind die nötigen Parameter
//
//  Dieser Script kann folgendermaßen aufgerufen werden:  php automation.php.
//  Er läuft unabhängig von der Solaranzeigen Software und wird mit einem cron Job
//  jede Minute gestartet.
//
//
*****************************************************************************+
//  Tracelevel:
//  0 = keine LOG Meldungen
//  1 = Nur Fehlermeldungen
//  2 = Fehlermeldungen und Warnungen
//  3 = Fehlermeldungen, Warnungen und Informationen
//  4 = Debugging
*****************************************************************************/
$Tracelevel = 3;
//
//

$basedir = dirname(__FILE__,2);
require($basedir."/library/base.inc.php");

$Datenbankname = "/var/www/html/database/automation.sqlite3";
$LRaktiv = false;
$WRaktiv = false;
$SMaktiv = false;
$BMSaktiv = false;
$WRVar = 0;
$MeterVar = 0;
$BMSVar = 0;
$Brokermeldung = "";
$Relais1[1] = "";
$Relais1[2] = "";
$Relais1[3] = "";
$Relais1[4] = "";
$Relais2[1] = "";
$Relais2[2] = "";
$Relais2[3] = "";
$Relais2[4] = "";
$Relais1WertON = "on";
$Relais1WertOFF = "off";
$Relais2WertON = "on";
$Relais2WertOFF = "off";
$PVLeistung = 0;
$ACLeistung = 0;
$Bezug = 0;
$Einspeisung = 0;
$SOC = 0;
$MQTTDaten = array();
Log::write( "- - - - - - - - - -    Start Automation   - - - - - - - - -", "|-->", 1 );

/****************************************************************************
//  SQLite Datenbank starten
//
****************************************************************************/
try {
  $db = db_connect( $Datenbankname );
  $db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
}
catch (PDOException $e) {
// Print PDOException message
  Log::write( $e->getMessage( ), "", 1 );
}
$sql = "SELECT * FROM config";
$result = $db->query( $sql )->fetchAll( PDO::FETCH_ASSOC );
//  Alle Felder auslesen
if (isset($result[0])) {
  $var = $result[0];
}
else {
  Log::write( "Es ist kein Überwachungsgerät konfiguriert. Ende!", "", 1 );
  Log::write( "Bitte die Konfiguration überprüfen.", "", 2 );
  goto Ausgang;
}
Log::write( print_r( $var, 1 ), "", 4 );
//     Relais 1  Relais 1  Relais 1  Relais 1  Relais 1
//     Nur große Buchstaben verwenden
switch (strtoupper( $var ["Relais1Typ"] )) {

  case "SONOFF POW":
  case "SONOFF BASIC":
  case "SONOFF TH16/TH10":
  case "Sonoff S26R2":
  case "GOSUND SP1/SP111":
  case "SHELLY 1PM":
  case "SHELLY 1":
  case "Nous A1T":
  case "Shelly Plug S":
  case "Relais mit 1 Kontakt":
    $Relais1[1] = "cmnd/".$var ["Relais1Topic"]."/power";
    $var ["Relais1AnzKontakte"] = 1;
    break;

  case "SONOFF 4CH":
    $Relais1[1] = "cmnd/".$var ["Relais1Topic"]."/power1";
    $Relais1[2] = "cmnd/".$var ["Relais1Topic"]."/power2";
    $Relais1[3] = "cmnd/".$var ["Relais1Topic"]."/power3";
    $Relais1[4] = "cmnd/".$var ["Relais1Topic"]."/power4";
    break;

  case "GOSUND SP211":
  case "SHELLY 2.5":
  case "SONOFF DUAL R2":
  case "Relais mit 2 Kontakten":
    $Relais1[1] = "cmnd/".$var ["Relais1Topic"]."/power1";
    $Relais1[2] = "cmnd/".$var ["Relais1Topic"]."/power2";
    if ($var ["Relais1AnzKontakte"] > 2) {
      $var ["Relais1AnzKontakte"] = 2;
    }
    break;

  default:
    $Relais1[1] = "cmnd/".$var ["Relais1Topic"]."/power";
    break;
}
//     Relais 2  Relais 2  Relais 2  Relais 2  Relais 2
//     Nur große Buchstaben verwenden
switch (strtoupper( $var ["Relais2Typ"] )) {

  case "SONOFF POW":
  case "SONOFF BASIC":
  case "SONOFF TH16/10":
  case "Sonoff S26R2":
  case "GOSUND SP1/SP111":
  case "SHELLY 1PM":
  case "SHELLY 1":
  case "Nous A1T":
  case "Shelly Plug S":
  case "Relais mit 1 Kontakt":
    $Relais2[1] = "cmnd/".$var ["Relais2Topic"]."/power";
    break;

  case "SONOFF 4CH":
    $Relais2[1] = "cmnd/".$var ["Relais2Topic"]."/power1";
    $Relais2[2] = "cmnd/".$var ["Relais2Topic"]."/power2";
    $Relais2[3] = "cmnd/".$var ["Relais2Topic"]."/power3";
    $Relais2[4] = "cmnd/".$var ["Relais2Topic"]."/power4";
    break;

  case "SHELLY 2.5":
  case "GOSUND SP211":
  case "SONOFF DUAL R2":
  case "Relais mit 2 Kontakten":   
    $Relais2[1] = "cmnd/".$var ["Relais2Topic"]."/power1";
    $Relais2[2] = "cmnd/".$var ["Relais2Topic"]."/power2";
    if ($var ["Relais2AnzKontakte"] > 2) {
      $var ["Relais2AnzKontakte"] = 2;
    }
    break;

  default:
    $Relais2[1] = "cmnd/".$var ["Relais2Topic"]."/power";
    break;
}
if ($var ["LRReglerNr"] > 0) {
  //  Measurement PV
  $LRVar = influxDB_lesen( trim( $var ["LRDB"] ), $var ["LRMeasurement"] );
  $PVLeistung = $LRVar[$var ["LRFeldname"]];
  $LRaktiv = true;
  Log::write( "Laderegler ist konfiguriert.", "", 4 );
  // Sind die aWATTar Preise vorhanden?
  // $aWATTar["Sortierung"]  + $aWATTar["Preis_kWh"]
  $aWATTar = influxDB_lesen( $var ["LRDB"], "awattarPreise", "where Stunde = ".date( "H" ));
  if (!is_array( $aWATTar )) {
    $aWATTar["Sortierung"] = 0;
    $aWATTar["Preis_kWh"] = 0;
  }
  Log::write( "PV Leistung: ".$PVLeistung." W", "", 3 );
  Log::write( "aWATTar Preise:\n".print_r( $aWATTar, 1 ), "", 4 );
}
if ($var ["WRReglerNr"] > 0) {
  //  Measurement AC
  $WRVar = influxDB_lesen( trim( $var ["WRDB"] ), $var ["WRMeasurement"] );
  $ACLeistung = $WRVar[$var ["WRFeldname"]];
  Log::write( "Wechselrichter ist konfiguriert.", "", 4 );
  $WRaktiv = true;
  // Sind die aWATTar Preise vorhanden?
  // $aWATTar["Sortierung"]  + $aWATTar["Preis_kWh"]
  $aWATTar = influxDB_lesen( $var ["WRDB"], "awattarPreise", "where Stunde = ".date( "H" ));
  if (!is_array( $aWATTar )) {
    $aWATTar["Sortierung"] = 0;
    $aWATTar["Preis_kWh"] = 0;
  }
  Log::write( "AC Leistung: ".$ACLeistung." W", "", 3 );
  Log::write( "aWATTar Preise:\n".print_r( $aWATTar, 1 ), "", 4 );
}
if ($var ["SMReglerNr"] > 0) {
  //  Measurement AC
  $MeterVar = influxDB_lesen( trim( $var ["SMDB"] ), $var ["SMMeasurement"] );
  $Bezug = $MeterVar[$var ["SMBezug"]];
  $Einspeisung = $MeterVar[$var ["SMEinspeisung"]];
  Log::write( "SmartMeter ist konfiguriert.\n".print_r( $MeterVar, 1 ), "", 4 );
  $SMaktiv = true;
  Log::write( "Bezug: ".$Bezug." W", "", 3 );
  Log::write( "Einspeisung: ".$Einspeisung." W", "", 3 );
}
if ($var ["BMSReglerNr"] > 0) {
  //  Measurement Batterie
  $BMSVar = influxDB_lesen( trim( $var ["BMSDB"] ), $var ["BMSMeasurement"] );
  $SOC = $BMSVar[$var ["BMSFeldname"]];
  Log::write( "BMS ist konfiguriert.", "", 4 );
  $BMSaktiv = true;
  Log::write( "Kapazität: ".$SOC." %", "", 3 );
}
if (1 == 2) {
  // Hier können noch weitere Measurements von den verschiedenen Datenbanken
  // abgefrgt werden, die dann in der Datei auto-math.php zur Berechnung
  // benutzt werden können.
  // Das ist nur ein Template und auch inaktiviert. Es muss erst mit den
  // richtigen Namen ausgefüllt werden.
  //
  $ZusatzVar = influxDB_lesen( $Datenbank, $Measurement );
  $Sonderwerte = $ZusatzVar["Feldname"];
  Log::write( "Sonderwerte:\n".print_r( $ZusatzVar, 1 ), "", 4 );
}
// Hier stehen die Variablen zur Verfügung.
// Array's = $LRVar, $WRVar, $MeterVar, $BMSVar, $ZusatzVar

/****************************************************************************
//  Ist ein Laderegler, Wechselrichter usw. konfiguriert?
//
****************************************************************************/
if ($LRaktiv == false and $WRaktiv == false and $SMaktiv == false and $BMSaktiv == false) {
  Log::write( "Es ist kein Überwachungsgerät konfiguriert. Ende!", "", 1 );
  Log::write( "Bitte die Konfiguration überprüfen.", "", 2 );
  goto Ausgang;
}

/*****************************************************************************
//  Ist ein Relais aktiviert?
//  Wenn nein, dann Ausgang.
*****************************************************************************/
if ($var ["Relais1aktiv"] == 0 and $var ["Relais2aktiv"] == 0) {
  Log::write( "Es ist kein Relais konfiguriert. Ende!", "", 1 );
  goto Ausgang;
}

/****************************************************************************
//  Moscitto Client starten
//
****************************************************************************/
$client = new Mosquitto\Client( );
$client->onConnect( 'connect' );
$client->onDisconnect( 'disconnect' );
$client->onSubscribe( 'subscribe' );
$client->onMessage( 'message' );
$client->onLog( 'logger' );
$client->onPublish( 'publish' );
$client->connect( $var ["BrokerIP"], $var ["BrokerPort"], 5 );
for ($i = 1; $i < 20; $i++) {
  // Warten bis der connect erfolgt ist.
  if (empty($Brokermeldung)) {
    $client->loop( 100 );
  }
  else {
    break;
  }
}

/************************************************************************
//  Abfrage der Kontakte aus den aktiven Relais
//  $db,$client,$relais,$topic,$wert
************************************************************************/
if ($var ["Relais1aktiv"] == 1) {
  if ($var ["Relais1TopicFormat"] == 0) {
    $Relais1Kontakte = relais_abfragen( $db, $client, 1, $var ["Relais1Topic"]."/cmnd/status", null );
  }
  if ($var ["Relais1TopicFormat"] == 1) {
    $Relais1Kontakte = relais_abfragen( $db, $client, 1, "cmnd/".$var ["Relais1Topic"]."/status", null );
  }
}
if ($var ["Relais2aktiv"] == 1) {
  if ($var ["Relais2TopicFormat"] == 0) {
    $Relais2Kontakte = relais_abfragen( $db, $client, 2, $var ["Relais2Topic"]."/cmnd/status", null );
  }
  if ($var ["Relais2TopicFormat"] == 1) {
    $Relais2Kontakte = relais_abfragen( $db, $client, 2, "cmnd/".$var ["Relais2Topic"]."/status", null );
  }
}

/****************************************************************************
//  User PHP Script, falls gewünscht oder nötig
****************************************************************************/
if (file_exists( $basedir."/custom/auto-math.php" )) {
  include $basedir."/custom/auto-math.php"; // Falls etwas neu berechnet werden muss.
}

/**********************************************************************
//  Relais Steuerkreis 1
//  Hier beginnt die Auswertung und Steuerung
//
//  *****************************************
**********************************************************************/
if ($var ["Relais1aktiv"] == 1) {
  Log::write( "Relais 1 ist aktiviert.", "", 3 );

  /************************************************************************
  //  Abfrage der Kontakte aus den aktiven Relais
  //  $db,$client,$relais,$topic,$wert
  ************************************************************************/
  if ($var ["Relais1TopicFormat"] == 0) {
    $Relais1Kontakte = relais_abfragen( $db, $client, 1, $var ["Relais1Topic"]."/cmnd/status", null );
  }
  if ($var ["Relais1TopicFormat"] == 1) {
    $Relais1Kontakte = relais_abfragen( $db, $client, 1, "cmnd/".$var ["Relais1Topic"]."/status", null );
  }
  if (count( $Relais1Kontakte ) == 0) {
    Log::write( "Keine Antwort vom Relais 1", "", 2 );
    goto weiter;
  }
  $MQTTDaten = array();
  if (!isset($Relais1Kontakte[2])) {
    $Relais1Kontakte[2] = 0;
  }
  if (!isset($Relais1Kontakte[3])) {
    $Relais1Kontakte[3] = 0;
  }
  if (!isset($Relais1Kontakte[4])) {
    $Relais1Kontakte[4] = 0;
  }

  /***************************************************************************
  //  EIN     EIN     EIN     EIN     EIN     EIN     EIN     EIN     EIN
  //  EIN     EIN     EIN     EIN     EIN     EIN     EIN     EIN     EIN
  ***************************************************************************/
  if ($Relais1Kontakte[1] == 0) {
    Log::write( "Relais 1 Kontakt 1 ist ausgeschaltet", "", 3 );

    /********************************************************************
    //  Start Logik
    //  Relais 1 Kontakt 1 ist zur Zeit ausgeschaltet
    //  EIN     EIN     EIN     EIN     EIN     EIN     EIN     EIN
    //  1.1   1.1   1.1   1.1   1.1   1.1   1.1   1.1   1.1   1.1   1.1
    ********************************************************************/
    $Relais1Kontakt1Auswertung = 0;
    $i = 1;
    $Geraete = 0;
    if ($LRaktiv and $var ["Relais1K1PVein"] != null) {
      if ($PVLeistung >= $var ["Relais1K1PVein"]) {
        Log::write( "PV Leistung ".$PVLeistung." ist größer/gleich Vorgabe: ".$var ["Relais1K1PVein"], "", 3 );
        $Relais1Kontakt1Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        Log::write( "PV Leistung ".$PVLeistung." ist niedriger als die Vorgabe: ".$var ["Relais1K1PVein"], "", 2 );
        $Ergebnis[$i] = false;
      }
      $Bedingung[$i] = $var ["Relais1K1PVBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($WRaktiv and $var ["Relais1K1ACein"] != null) {
      if ($ACLeistung >= $var ["Relais1K1ACein"]) {
        Log::write( "AC Leistung ".$ACLeistung." ist größer/gleich Vorgabe: ".$var ["Relais1K1ACein"], "", 3 );
        $Relais1Kontakt1Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "AC Leistung ".$ACLeistung." ist niedriger als die Vorgabe: ".$var ["Relais1K1ACein"], "", 2 );
      }
      $Bedingung[$i] = $var ["Relais1K1ACBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($SMaktiv and $var ["Relais1K1SMein"] != null) {
      if ($Einspeisung >= $var ["Relais1K1SMein"]) {
        Log::write( "Einspeisung ".$Einspeisung." ist größer/gleich Vorgabe: ".$var ["Relais1K1SMein"], "", 3 );
        $Relais1Kontakt1Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "Einspeisung ".$Einspeisung." ist niedriger als die Vorgabe: ".$var ["Relais1K1SMein"], "", 2 );
      }
      $Bedingung[$i] = $var ["Relais1K1SMBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($BMSaktiv and $var ["Relais1K1BMSein"] != null) {
      if ($SOC >= $var ["Relais1K1BMSein"]) {
        Log::write( "SOC ".$SOC."% ist größer/gleich Vorgabe: ".$var ["Relais1K1BMSein"]."%", "", 3 );
        $Relais1Kontakt1Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "SOC ".$SOC."% ist niedriger als die Vorgabe: ".$var ["Relais1K1BMSein"]."%", "", 2 );
      }
      $i++;
      $Geraete++;
    }
    if ($Geraete > 1) {
      $Relais1Kontakt1Auswertung = auswertung( $Ergebnis, $Bedingung, $Relais1Kontakt1Auswertung, $Geraete );
    }

    /**********************************************************************
    //  Kontaktänderung durch Benutzerauswertung
    **********************************************************************/
    if (isset($UserKontaktAuswertung["Relais1"]["Kontakt1"]) and $UserKontaktAuswertung["Relais1"]["Kontakt1"] == 1) {
      $Relais1Kontakt1Auswertung = 1;
      Log::write( "Relais 1 Kontakt 1 wird jetzt durch Benutzer Berechnung eingeschaltet.", "", 2 );
    }
    elseif (isset($UserKontaktAuswertung["Relais1"]["Kontakt1"]) and $UserKontaktAuswertung["Relais1"]["Kontakt1"] == 0) {
      $Relais1Kontakt1Auswertung = 0;
      Log::write( "Relais 1 Kontakt 1 bleibt durch Benutzer Berechnung ausgeschaltet.", "", 2 );
    }
    if ($Relais1Kontakt1Auswertung == 1) {

      /********************************************************************
      //  Das Relais 1 Kontakt 1 muss eingeschaltet werden!
      //
      ********************************************************************/

      /********************************************************************
      // Schalten von Relais Kontakten:
      // relais_schalten(Datenbank,Mosquitto,Relaiskontakt,Topic,Wert,QoS)
      // $var["Relais1Kontakt1"] wird aktualisiert
      ********************************************************************/
      Log::write( "Relais 1 Kontakt 1 wird jetzt ein geschaltet.", "", 2 );
      $rc = relais_schalten( $db, $client, "Relais1Kontakt1", $Relais1[1], $Relais1WertON, 1 );
    }
  }

  /****************************************************************************/
  if ($Relais1Kontakte[2] == 0 and $var ["Relais1AnzKontakte"] > 1) {
    Log::write( "Relais 1 Kontakt 2 ist ausgeschaltet", "", 3 );

    /********************************************************************
    //  Start Logik
    //  Relais 1 Kontakt 2 ist zur Zeit ausgeschaltet
    //  EIN     EIN     EIN     EIN     EIN     EIN     EIN     EIN
    //  1.2   1.2   1.2   1.2   1.2   1.2   1.2   1.2   1.2   1.2  1.2
    ********************************************************************/
    $Relais1Kontakt2Auswertung = 0;
    $i = 1;
    $Geraete = 0;
    if ($LRaktiv and $var ["Relais1K2PVein"] != null) {
      if ($PVLeistung >= $var ["Relais1K2PVein"]) {
        Log::write( "PV Leistung ".$PVLeistung." ist größer/gleich Vorgabe: ".$var ["Relais1K2PVein"], "", 3 );
        $Relais1Kontakt2Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        Log::write( "PV Leistung ".$PVLeistung." ist niedriger als die Vorgabe: ".$var ["Relais1K2PVein"], "", 2 );
        $Ergebnis[$i] = false;
      }
      $Bedingung[$i] = $var ["Relais1K2PVBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($WRaktiv and $var ["Relais1K2ACein"] != null) {
      if ($ACLeistung >= $var ["Relais1K2ACein"]) {
        Log::write( "AC Leistung ".$ACLeistung." ist größer/gleich Vorgabe: ".$var ["Relais1K2ACein"], "", 3 );
        $Relais1Kontakt2Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "AC Leistung ".$ACLeistung." ist niedriger als die Vorgabe: ".$var ["Relais1K2ACein"], "", 2 );
      }
      $Bedingung[$i] = $var ["Relais1K2ACBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($SMaktiv and $var ["Relais1K2SMein"] != null) {
      if ($Einspeisung >= $var ["Relais1K2SMein"]) {
        Log::write( "Einspeisung ".$Einspeisung." ist größer/gleich Vorgabe: ".$var ["Relais1K2SMein"], "", 3 );
        $Relais1Kontakt2Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "Einspeisung ".$Einspeisung." ist niedriger als die Vorgabe: ".$var ["Relais1K2SMein"], "", 2 );
      }
      $Bedingung[$i] = $var ["Relais1K2SMBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($BMSaktiv and $var ["Relais1K2BMSein"] != null) {
      if ($SOC >= $var ["Relais1K2BMSein"]) {
        Log::write( "SOC ".$SOC."% ist größer/gleich Vorgabe: ".$var ["Relais1K2BMSein"]."%", "", 3 );
        $Relais1Kontakt2Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "SOC ".$SOC."% ist niedriger als die Vorgabe: ".$var ["Relais1K2BMSein"]."%", "", 2 );
      }
      $i++;
      $Geraete++;
    }
    if ($Geraete > 1) {
      $Relais1Kontakt2Auswertung = auswertung( $Ergebnis, $Bedingung, $Relais1Kontakt2Auswertung, $Geraete );
    }

    /**********************************************************************
    //  Kontaktänderung durch Benutzerauswertung
    **********************************************************************/
    if (isset($UserKontaktAuswertung["Relais1"]["Kontakt2"]) and $UserKontaktAuswertung["Relais1"]["Kontakt2"] == 1) {
      $Relais1Kontakt2Auswertung = 1;
      Log::write( "Relais 1 Kontakt 2 wird jetzt durch Benutzer Berechnung eingeschaltet.", "", 2 );
    }
    elseif (isset($UserKontaktAuswertung["Relais1"]["Kontakt2"]) and $UserKontaktAuswertung["Relais1"]["Kontakt2"] == 0) {
      $Relais1Kontakt2Auswertung = 0;
      Log::write( "Relais 1 Kontakt 2 bleibt durch Benutzer Berechnung ausgeschaltet.", "", 2 );
    }
    if ($Relais1Kontakt2Auswertung == 1) {

      /********************************************************************
      //  Das Relais 1 Kontakt 2 muss eingeschaltet werden!
      //
      ********************************************************************/

      /********************************************************************
      // Schalten von Relais Kontakten:
      // relais_schalten(Datenbank,Mosquitto,Relaiskontakt,Topic,Wert,QoS)
      // $var["Relais1Kontakt1"] wird aktualisiert
      ********************************************************************/
      Log::write( "Relais 1 Kontakt 2 wird jetzt ein geschaltet.", "", 2 );
      $rc = relais_schalten( $db, $client, "Relais1Kontakt2", $Relais1[2], $Relais1WertON, 1 );
    }
  }

  /****************************************************************************/
  if ($Relais1Kontakte[3] == 0 and $var ["Relais1AnzKontakte"] > 2) {
    Log::write( "Relais 1 Kontakt 3 ist ausgeschaltet", "", 3 );

    /********************************************************************
    //  Start Logik
    //  Relais 1 Kontakt 3 ist zur Zeit ausgeschaltet
    //  EIN     EIN     EIN     EIN     EIN     EIN     EIN     EIN
    //  1.3   1.3   1.3   1.3   1.3   1.3   1.3   1.3   1.3   1.3   1.3
    ********************************************************************/
    $Relais1Kontakt3Auswertung = 0;
    $i = 1;
    $Geraete = 0;
    if ($LRaktiv and $var ["Relais1K3PVein"] != null) {
      if ($PVLeistung >= $var ["Relais1K3PVein"]) {
        Log::write( "PV Leistung ".$PVLeistung." ist größer/gleich Vorgabe: ".$var ["Relais1K3PVein"], "", 3 );
        $Relais1Kontakt3Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        Log::write( "PV Leistung ".$PVLeistung." ist niedriger als die Vorgabe: ".$var ["Relais1K3PVein"], "", 2 );
        $Ergebnis[$i] = false;
      }
      $Bedingung[$i] = $var ["Relais1K3PVBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($WRaktiv and $var ["Relais1K3ACein"] != null) {
      if ($ACLeistung >= $var ["Relais1K3ACein"]) {
        Log::write( "AC Leistung ".$ACLeistung." ist größer/gleich Vorgabe: ".$var ["Relais1K3ACein"], "", 3 );
        $Relais1Kontakt3Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "AC Leistung ".$ACLeistung." ist niedriger als die Vorgabe: ".$var ["Relais1K3ACein"], "", 2 );
      }
      $Bedingung[$i] = $var ["Relais1K3ACBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($SMaktiv and $var ["Relais1K3SMein"] != null) {
      if ($Einspeisung >= $var ["Relais1K3SMein"]) {
        Log::write( "Einspeisung ".$Einspeisung." ist größer/gleich Vorgabe: ".$var ["Relais1K3SMein"], "", 3 );
        $Relais1Kontakt3Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "Einspeisung ".$Einspeisung." ist niedriger als die Vorgabe: ".$var ["Relais1K3SMein"], "", 2 );
      }
      $Bedingung[$i] = $var ["Relais1K3SMBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($BMSaktiv and $var ["Relais1K3BMSein"] != null) {
      if ($SOC >= $var ["Relais1K3BMSein"]) {
        Log::write( "SOC ".$SOC."% ist größer/gleich Vorgabe: ".$var ["Relais1K3BMSein"]."%", "", 3 );
        $Relais1Kontakt3Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "SOC ".$SOC."% ist niedriger als die Vorgabe: ".$var ["Relais1K3BMSein"]."%", "", 2 );
      }
      $i++;
      $Geraete++;
    }
    if ($Geraete > 1) {
      $Relais1Kontakt3Auswertung = auswertung( $Ergebnis, $Bedingung, $Relais1Kontakt3Auswertung, $Geraete );
    }

    /**********************************************************************
    //  Kontaktänderung durch Benutzerauswertung
    **********************************************************************/
    if (isset($UserKontaktAuswertung["Relais1"]["Kontakt3"]) and $UserKontaktAuswertung["Relais1"]["Kontakt3"] == 1) {
      $Relais1Kontakt3Auswertung = 1;
      Log::write( "Relais 1 Kontakt 3 wird jetzt durch Benutzer Berechnung eingeschaltet.", "", 2 );
    }
    elseif (isset($UserKontaktAuswertung["Relais1"]["Kontakt3"]) and $UserKontaktAuswertung["Relais1"]["Kontakt3"] == 0) {
      $Relais1Kontakt3Auswertung = 0;
      Log::write( "Relais 1 Kontakt 3 bleibt durch Benutzer Berechnung ausgeschaltet.", "", 2 );
    }
    if ($Relais1Kontakt3Auswertung == 1) {

      /********************************************************************
      //  Das Relais 1 Kontakt 3 muss eingeschaltet werden!
      //
      ********************************************************************/

      /********************************************************************
      // Schalten von Relais Kontakten:
      // relais_schalten(Datenbank,Mosquitto,Relaiskontakt,Topic,Wert,QoS)
      // $var["Relais1Kontakt3"] wird aktualisiert
      ********************************************************************/
      Log::write( "Relais 1 Kontakt 3 wird jetzt ein geschaltet.", "", 2 );
      $rc = relais_schalten( $db, $client, "Relais1Kontakt3", $Relais1[3], $Relais1WertON, 1 );
    }
  }

  /****************************************************************************/
  if ($Relais1Kontakte[4] == 0 and $var ["Relais1AnzKontakte"] > 3) {
    Log::write( "Relais 1 Kontakt 4 ist ausgeschaltet", "", 3 );

    /********************************************************************
    //  Start Logik
    //  Relais 1 Kontakt 4 ist zur Zeit ausgeschaltet
    //  EIN     EIN     EIN     EIN     EIN     EIN     EIN     EIN
    //  1.4   1.4   1.4   1.4   1.4   1.4   1.4   1.4   1.4   1.4   1.4
    ********************************************************************/
    $Relais1Kontakt4Auswertung = 0;
    $i = 1;
    $Geraete = 0;
    if ($LRaktiv and $var ["Relais1K4PVein"] != null) {
      if ($PVLeistung >= $var ["Relais1K4PVein"]) {
        Log::write( "PV Leistung ".$PVLeistung." ist größer/gleich Vorgabe: ".$var ["Relais1K4PVein"], "", 3 );
        $Relais1Kontakt4Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        Log::write( "PV Leistung ".$PVLeistung." ist niedriger als die Vorgabe: ".$var ["Relais1K4PVein"], "", 2 );
        $Ergebnis[$i] = false;
      }
      $Bedingung[$i] = $var ["Relais1K4PVBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($WRaktiv and $var ["Relais1K4ACein"] != null) {
      if ($ACLeistung >= $var ["Relais1K4ACein"]) {
        Log::write( "AC Leistung ".$ACLeistung." ist größer/gleich Vorgabe: ".$var ["Relais1K4ACein"], "", 3 );
        $Relais1Kontakt4Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "AC Leistung ".$ACLeistung." ist niedriger als die Vorgabe: ".$var ["Relais1K4ACein"], "", 2 );
      }
      $Bedingung[$i] = $var ["Relais1K4ACBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($SMaktiv and $var ["Relais1K4SMein"] != null) {
      if ($Einspeisung >= $var ["Relais1K4SMein"]) {
        Log::write( "Einspeisung ".$Einspeisung." ist größer/gleich Vorgabe: ".$var ["Relais1K4SMein"], "", 3 );
        $Relais1Kontakt4Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "Einspeisung ".$Einspeisung." ist niedriger als die Vorgabe: ".$var ["Relais1K4SMein"], "", 2 );
      }
      $Bedingung[$i] = $var ["Relais1K4SMBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($BMSaktiv and $var ["Relais1K4BMSein"] != null) {
      if ($SOC >= $var ["Relais1K4BMSein"]) {
        Log::write( "SOC ".$SOC."% ist größer/gleich Vorgabe: ".$var ["Relais1K4BMSein"]."%", "", 3 );
        $Relais1Kontakt4Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "SOC ".$SOC."% ist niedriger als die Vorgabe: ".$var ["Relais1K4BMSein"]."%", "", 2 );
      }
      $i++;
      $Geraete++;
    }
    if ($Geraete > 1) {
      $Relais1Kontakt4Auswertung = auswertung( $Ergebnis, $Bedingung, $Relais1Kontakt4Auswertung, $Geraete );
    }

    /**********************************************************************
    //  Kontaktänderung durch Benutzerauswertung
    **********************************************************************/
    if (isset($UserKontaktAuswertung["Relais1"]["Kontakt4"]) and $UserKontaktAuswertung["Relais1"]["Kontakt4"] == 1) {
      $Relais1Kontakt4Auswertung = 1;
      Log::write( "Relais 1 Kontakt 4 wird jetzt durch Benutzer Berechnung eingeschaltet.", "", 2 );
    }
    elseif (isset($UserKontaktAuswertung["Relais1"]["Kontakt4"]) and $UserKontaktAuswertung["Relais1"]["Kontakt4"] == 0) {
      $Relais1Kontakt4Auswertung = 0;
      Log::write( "Relais 1 Kontakt 4 bleibt durch Benutzer Berechnung ausgeschaltet.", "", 2 );
    }
    if ($Relais1Kontakt4Auswertung == 1) {

      /********************************************************************
      //  Das Relais 1 Kontakt 4 muss eingeschaltet werden!
      //
      ********************************************************************/

      /********************************************************************
      // Schalten von Relais Kontakten:
      // relais_schalten(Datenbank,Mosquitto,Relaiskontakt,Topic,Wert,QoS)
      // $var["Relais1Kontakt4"] wird aktualisiert
      ********************************************************************/
      Log::write( "Relais 1 Kontakt 4 wird jetzt ein geschaltet.", "", 2 );
      $rc = relais_schalten( $db, $client, "Relais1Kontakt4", $Relais1[4], $Relais1WertON, 1 );
    }
  }

  /***************************************************************************
  //  AUS     AUS     AUS     AUS     AUS     AUS     AUS     AUS     AUS
  //  AUS     AUS     AUS     AUS     AUS     AUS     AUS     AUS     AUS
  ***************************************************************************/
  if ($Relais1Kontakte[1] == 1) {
    //  Soll der Kontakt per Zeiteinstellung ausgeschaltet werden? ( 0 = nein )
    if ($var ["Relais1K1ausMinuten"] == 0) {
      Log::write( "Relais 1 Kontakt 1 ist eingeschaltet", "", 3 );

      /********************************************************************
      //  Start Logik
      //  Relais 1 Kontakt 1 ist zur Zeit eingeschaltet
      //  AUS     AUS     AUS     AUS     AUS     AUS     AUS     AUS
      //  1.1   1.1   1.1   1.1   1.1   1.1   1.1   1.1   1.1   1.1   1.1
      ********************************************************************/
      $Relais1Kontakt1Auswertung = 0;
      $i = 1;
      $Geraete = 0;
      if ($LRaktiv and $var ["Relais1K1PVaus"] != null) {
        if ($PVLeistung < $var ["Relais1K1PVaus"]) {
          Log::write( "PV Leistung ".$PVLeistung." ist niedriger als die Vorgabe: ".$var ["Relais1K1PVaus"], "", 3 );
          $Relais1Kontakt1Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "PV Leistung ".$PVLeistung." ist größer als die Vorgabe: ".$var ["Relais1K1PVaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais1K1PVBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($WRaktiv and $var ["Relais1K1ACaus"] != null) {
        if ($ACLeistung < $var ["Relais1K1ACaus"]) {
          Log::write( "AC Leistung ".$ACLeistung." ist niedriger als die Vorgabe: ".$var ["Relais1K1ACaus"], "", 3 );
          $Relais1Kontakt1Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "AC Leistung ".$ACLeistung." ist größer als die Vorgabe: ".$var ["Relais1K1ACaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais1K1ACBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($SMaktiv and $var ["Relais1K1SMaus"] != null) {
        if ($Einspeisung < $var ["Relais1K1SMaus"]) {
          Log::write( "Einspeisung ".$Einspeisung." ist niedriger als die Vorgabe: ".$var ["Relais1K1SMaus"], "", 3 );
          $Relais1Kontakt1Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "Einspeisung ".$Einspeisung." ist größer als die Vorgabe: ".$var ["Relais1K1SMaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais1K1SMBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($BMSaktiv and $var ["Relais1K1BMSaus"] != null) {
        if ($SOC < $var ["Relais1K1BMSaus"]) {
          Log::write( "SOC ".$SOC."% ist niedriger als die Vorgabe: ".$var ["Relais1K1BMSaus"]."%", "", 3 );
          $Relais1Kontakt1Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "SOC ".$SOC."% ist größer als die Vorgabe: ".$var ["Relais1K1BMSaus"]."%", "", 2 );
        }
        $i++;
        $Geraete++;
      }
      if ($Geraete > 1) {
        $Relais1Kontakt1Auswertung = auswertung( $Ergebnis, $Bedingung, $Relais1Kontakt1Auswertung, $Geraete );
      }
    }
    else {
      // Der Kontakt 1 soll per Zeitsteuerung ausgeschaltet werden.
      // Ist der Zeitstempel vorhanden?
      if ($var ["Relais1Kontakt1Timestamp"] == 0) {
        // Falls nicht eintragen.
        $sql = "Update config set Relais1Kontakt1Timestamp = ".time( )." where Id = 1";
        $statement = $db->query( $sql );
        $Startzeit = time( );
      }
      else {
        $Startzeit = $var ["Relais1Kontakt1Timestamp"];
        if (($Startzeit + ($var ["Relais1K1ausMinuten"] * 60) - 20) <= time( )) {
          $Relais1Kontakt1Auswertung = 1;
        }
        else {
          $Relais1Kontakt1Auswertung = 0;
          Log::write( "es dauert noch ".(($Startzeit + ($var ["Relais1K1ausMinuten"] * 60)) - time( ))." Sekunden bis zur Abschaltung", "", 3 );
        }
      }
    }

    /**********************************************************************
    //  Kontaktänderung durch Benutzerauswertung
    **********************************************************************/
    if (isset($UserKontaktAuswertung["Relais1"]["Kontakt1"]) and $UserKontaktAuswertung["Relais1"]["Kontakt1"] == 0) {
      $Relais1Kontakt1Auswertung = 1;
      Log::write( "Relais 1 Kontakt 1 wird jetzt durch Benutzer Berechnung ausgeschaltet.", "", 2 );
    }
    elseif (isset($UserKontaktAuswertung["Relais1"]["Kontakt1"]) and $UserKontaktAuswertung["Relais1"]["Kontakt1"] == 1) {
      $Relais1Kontakt1Auswertung = 0;
      Log::write( "Relais 1 Kontakt 1 bleibt durch Benutzer Berechnung eingeschaltet.", "", 2 );
    }
    if ($Relais1Kontakt1Auswertung == 1) {

      /********************************************************************
      //  Das Relais 1 Kontakt 1 muss ausgeschaltet werden!
      //
      ********************************************************************/

      /********************************************************************
      // Schalten von Relais Kontakten:
      // relais_schalten(Datenbank,Mosquitto,Relaiskontakt,Topic,Wert,QoS)
      // $var["Relais1Kontakt1"] wird aktualisiert
      ********************************************************************/
      Log::write( "Relais 1 Kontakt 1 wird aus geschaltet.", "", 2 );
      $rc = relais_schalten( $db, $client, "Relais1Kontakt1", $Relais1[1], $Relais1WertOFF, 1 );
    }
  }
  if ($Relais1Kontakte[2] == 1) {
    //  Soll der Kontakt per Zeiteinstellung ausgeschaltet werden? ( 0 = nein )
    if ($var ["Relais1K2ausMinuten"] == 0 and $var ["Relais1AnzKontakte"] > 1) {
      Log::write( "Relais 1 Kontakt 2 ist eingeschaltet", "", 3 );

      /********************************************************************
      //  Start Logik
      //  Relais 1 Kontakt 2 ist zur Zeit eingeschaltet
      //  AUS     AUS     AUS     AUS     AUS     AUS     AUS     AUS
      //  1.2   1.2   1.2   1.2   1.2   1.2   1.2   1.2   1.2   1.2  1.2
      ********************************************************************/
      $Relais1Kontakt2Auswertung = 0;
      $i = 1;
      $Geraete = 0;
      if ($LRaktiv and $var ["Relais1K2PVaus"] != null) {
        if ($PVLeistung < $var ["Relais1K2PVaus"]) {
          Log::write( "PV Leistung ".$PVLeistung." ist niedriger als die Vorgabe: ".$var ["Relais1K2PVaus"], "", 3 );
          $Relais1Kontakt2Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "PV Leistung ".$PVLeistung." ist größer als die Vorgabe: ".$var ["Relais1K2PVaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais1K2PVBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($WRaktiv and $var ["Relais1K2ACaus"] != null) {
        if ($ACLeistung < $var ["Relais1K2ACaus"]) {
          Log::write( "AC Leistung ".$ACLeistung." ist niedriger als die Vorgabe: ".$var ["Relais1K2ACaus"], "", 3 );
          $Relais1Kontakt2Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "AC Leistung ".$ACLeistung." ist größer als die Vorgabe: ".$var ["Relais1K2ACaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais1K2ACBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($SMaktiv and $var ["Relais1K2SMaus"] != null) {
        if ($Einspeisung < $var ["Relais1K2SMaus"]) {
          Log::write( "Einspeisung ".$Einspeisung." ist niedriger als die Vorgabe: ".$var ["Relais1K2SMaus"], "", 3 );
          $Relais1Kontakt2Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "Einspeisung ".$Einspeisung." ist größer als die Vorgabe: ".$var ["Relais1K2SMaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais1K2SMBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($BMSaktiv and $var ["Relais1K2BMSaus"] != null) {
        if ($SOC < $var ["Relais1K2BMSaus"]) {
          Log::write( "SOC ".$SOC."% ist niedriger als die Vorgabe: ".$var ["Relais1K2BMSaus"]."%", "", 3 );
          $Relais1Kontakt2Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "SOC ".$SOC."% ist größer als die Vorgabe: ".$var ["Relais1K2BMSaus"]."%", "", 2 );
        }
        $i++;
        $Geraete++;
      }
      if ($Geraete > 1) {
        $Relais1Kontakt2Auswertung = auswertung( $Ergebnis, $Bedingung, $Relais1Kontakt2Auswertung, $Geraete );
      }
    }
    else {
      // Der Kontakt 1 soll per Zeitsteuerung ausgeschaltet werden.
      // Ist der Zeitstempel vorhanden?
      if ($var ["Relais1Kontakt2Timestamp"] == 0) {
        // Falls nicht eintragen.
        $sql = "Update config set Relais1Kontakt2Timestamp = ".time( )." where Id = 1";
        $statement = $db->query( $sql );
        $Startzeit = time( );
      }
      else {
        $Startzeit = $var ["Relais1Kontakt2Timestamp"];
        if (($Startzeit + ($var ["Relais1K2ausMinuten"] * 60) - 20) <= time( )) {
          $Relais1Kontakt2Auswertung = 1;
        }
        else {
          $Relais1Kontakt2Auswertung = 0;
          Log::write( "es dauert noch ".(($Startzeit + ($var ["Relais1K2ausMinuten"] * 60)) - time( ))." Sekunden bis zur Abschaltung", "", 3 );
        }
      }
    }

    /**********************************************************************
    //  Kontaktänderung durch Benutzerauswertung
    **********************************************************************/
    if (isset($UserKontaktAuswertung["Relais1"]["Kontakt2"]) and $UserKontaktAuswertung["Relais1"]["Kontakt2"] == 0) {
      $Relais1Kontakt2Auswertung = 1;
      Log::write( "Relais 1 Kontakt 2 wird jetzt durch Benutzer Berechnung ausgeschaltet.", "", 2 );
    }
    elseif (isset($UserKontaktAuswertung["Relais1"]["Kontakt2"]) and $UserKontaktAuswertung["Relais1"]["Kontakt2"] == 1) {
      $Relais1Kontakt2Auswertung = 0;
      Log::write( "Relais 1 Kontakt 2 bleibt durch Benutzer Berechnung eingeschaltet.", "", 2 );
    }
    if ($Relais1Kontakt2Auswertung == 1) {

      /********************************************************************
      //  Das Relais 1 Kontakt 1 muss ausgeschaltet werden!
      //
      ********************************************************************/

      /********************************************************************
      // Schalten von Relais Kontakten:
      // relais_schalten(Datenbank,Mosquitto,Relaiskontakt,Topic,Wert,QoS)
      // $var["Relais1Kontakt1"] wird aktualisiert
      ********************************************************************/
      Log::write( "Relais 1 Kontakt 2 wird aus geschaltet.", "", 2 );
      $rc = relais_schalten( $db, $client, "Relais1Kontakt2", $Relais1[2], $Relais1WertOFF, 1 );
    }
  }
  if ($Relais1Kontakte[3] == 1) {
    //  Soll der Kontakt per Zeiteinstellung ausgeschaltet werden? ( 0 = nein )
    if ($var ["Relais1K3ausMinuten"] == 0 and $var ["Relais1AnzKontakte"] > 1) {
      Log::write( "Relais 1 Kontakt 3 ist eingeschaltet", "", 3 );

      /********************************************************************
      //  Start Logik
      //  Relais 1 Kontakt 3 ist zur Zeit eingeschaltet
      //  AUS     AUS     AUS     AUS     AUS     AUS     AUS     AUS
      //  1.3   1.3   1.3   1.3   1.3   1.3   1.3   1.3   1.3   1.3   1.3
      ********************************************************************/
      $Relais1Kontakt3Auswertung = 0;
      $i = 1;
      $Geraete = 0;
      if ($LRaktiv and $var ["Relais1K3PVaus"] != null) {
        if ($PVLeistung < $var ["Relais1K3PVaus"]) {
          Log::write( "PV Leistung ".$PVLeistung." ist niedriger als die Vorgabe: ".$var ["Relais1K3PVaus"], "", 3 );
          $Relais1Kontakt3Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "PV Leistung ".$PVLeistung." ist größer als die Vorgabe: ".$var ["Relais1K3PVaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais1K3PVBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($WRaktiv and $var ["Relais1K3ACaus"] != null) {
        if ($ACLeistung < $var ["Relais1K3ACaus"]) {
          Log::write( "AC Leistung ".$ACLeistung." ist niedriger als die Vorgabe: ".$var ["Relais1K3ACaus"], "", 3 );
          $Relais1Kontakt3Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "AC Leistung ".$ACLeistung." ist größer als die Vorgabe: ".$var ["Relais1K3ACaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais1K3ACBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($SMaktiv and $var ["Relais1K3SMaus"] != null) {
        if ($Einspeisung < $var ["Relais1K3SMaus"]) {
          Log::write( "Einspeisung ".$Einspeisung." ist niedriger als die Vorgabe: ".$var ["Relais1K3SMaus"], "", 3 );
          $Relais1Kontakt3Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "Einspeisung ".$Einspeisung." ist größer als die Vorgabe: ".$var ["Relais1K3SMaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais1K3SMBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($BMSaktiv and $var ["Relais1K3BMSaus"] != null) {
        if ($SOC < $var ["Relais1K3BMSaus"]) {
          Log::write( "SOC ".$SOC."% ist niedriger als die Vorgabe: ".$var ["Relais1K3BMSaus"]."%", "", 3 );
          $Relais1Kontakt3Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "SOC ".$SOC."% ist größer als die Vorgabe: ".$var ["Relais1K3BMSaus"]."%", "", 2 );
        }
        $i++;
        $Geraete++;
      }
      if ($Geraete > 1) {
        $Relais1Kontakt3Auswertung = auswertung( $Ergebnis, $Bedingung, $Relais1Kontakt3Auswertung, $Geraete );
      }
    }
    else {
      // Der Kontakt 1 soll per Zeitsteuerung ausgeschaltet werden.
      // Ist der Zeitstempel vorhanden?
      if ($var ["Relais1Kontakt3Timestamp"] == 0) {
        // Falls nicht eintragen.
        $sql = "Update config set Relais1Kontakt3Timestamp = ".time( )." where Id = 1";
        $statement = $db->query( $sql );
        $Startzeit = time( );
      }
      else {
        $Startzeit = $var ["Relais1Kontakt3Timestamp"];
        if (($Startzeit + ($var ["Relais1K3ausMinuten"] * 60) - 20) <= time( )) {
          $Relais1Kontakt3Auswertung = 1;
        }
        else {
          $Relais1Kontakt3Auswertung = 0;
          Log::write( "es dauert noch ".(($Startzeit + ($var ["Relais1K3ausMinuten"] * 60)) - time( ))." Sekunden bis zur Abschaltung", "", 3 );
        }
      }
    }

    /**********************************************************************
    //  Kontaktänderung durch Benutzerauswertung
    **********************************************************************/
    if (isset($UserKontaktAuswertung["Relais1"]["Kontakt3"]) and $UserKontaktAuswertung["Relais1"]["Kontakt3"] == 0) {
      $Relais1Kontakt3Auswertung = 1;
      Log::write( "Relais 1 Kontakt 3 wird jetzt durch Benutzer Berechnung ausgeschaltet.", "", 2 );
    }
    elseif (isset($UserKontaktAuswertung["Relais1"]["Kontakt3"]) and $UserKontaktAuswertung["Relais1"]["Kontakt3"] == 1) {
      $Relais1Kontakt3Auswertung = 0;
      Log::write( "Relais 1 Kontakt 3 bleibt durch Benutzer Berechnung eingeschaltet.", "", 2 );
    }
    if ($Relais1Kontakt3Auswertung == 1) {

      /********************************************************************
      //  Das Relais 1 Kontakt 3 muss ausgeschaltet werden!
      //
      ********************************************************************/

      /********************************************************************
      // Schalten von Relais Kontakten:
      // relais_schalten(Datenbank,Mosquitto,Relaiskontakt,Topic,Wert,QoS)
      // $var["Relais1Kontakt3"] wird aktualisiert
      ********************************************************************/
      Log::write( "Relais 1 Kontakt 3 wird aus geschaltet.", "", 2 );
      $rc = relais_schalten( $db, $client, "Relais1Kontakt3", $Relais1[3], $Relais1WertOFF, 1 );
    }
  }
  if ($Relais1Kontakte[4] == 1) {
    //  Soll der Kontakt per Zeiteinstellung ausgeschaltet werden? ( 0 = nein )
    if ($var ["Relais1K4ausMinuten"] == 0 and $var ["Relais1AnzKontakte"] > 3) {
      Log::write( "Relais 1 Kontakt 4 ist eingeschaltet", "", 3 );

      /********************************************************************
      //  Start Logik
      //  Relais 1 Kontakt 4 ist zur Zeit eingeschaltet
      //  AUS     AUS     AUS     AUS     AUS     AUS     AUS     AUS
      //  1.4   1.4   1.4   1.4   1.4   1.4   1.4   1.4   1.4   1.4   1.4
      ********************************************************************/
      $Relais1Kontakt4Auswertung = 0;
      $i = 1;
      $Geraete = 0;
      if ($LRaktiv and $var ["Relais1K4PVaus"] != null) {
        if ($PVLeistung < $var ["Relais1K4PVaus"]) {
          Log::write( "PV Leistung ".$PVLeistung." ist niedriger als die Vorgabe: ".$var ["Relais1K4PVaus"], "", 3 );
          $Relais1Kontakt4Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "PV Leistung ".$PVLeistung." ist größer als die Vorgabe: ".$var ["Relais1K4PVaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais1K4PVBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($WRaktiv and $var ["Relais1K4ACaus"] != null) {
        if ($ACLeistung < $var ["Relais1K4ACaus"]) {
          Log::write( "AC Leistung ".$ACLeistung." ist niedriger als die Vorgabe: ".$var ["Relais1K4ACaus"], "", 3 );
          $Relais1Kontakt4Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "AC Leistung ".$ACLeistung." ist größer als die Vorgabe: ".$var ["Relais1K4ACaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais1K4ACBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($SMaktiv and $var ["Relais1K4SMaus"] != null) {
        if ($Einspeisung < $var ["Relais1K4SMaus"]) {
          Log::write( "Einspeisung ".$Einspeisung." ist niedriger als die Vorgabe: ".$var ["Relais1K4SMaus"], "", 3 );
          $Relais1Kontakt4Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "Einspeisung ".$Einspeisung." ist größer als die Vorgabe: ".$var ["Relais1K4SMaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais1K4SMBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($BMSaktiv and $var ["Relais1K4BMSaus"] != null) {
        if ($SOC < $var ["Relais1K4BMSaus"]) {
          Log::write( "SOC ".$SOC."% ist niedriger als die Vorgabe: ".$var ["Relais1K4BMSaus"]."%", "", 3 );
          $Relais1Kontakt2Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "SOC ".$SOC."% ist größer als die Vorgabe: ".$var ["Relais1K4BMSaus"]."%", "", 2 );
        }
        $i++;
        $Geraete++;
      }
      if ($Geraete > 1) {
        $Relais1Kontakt4Auswertung = auswertung( $Ergebnis, $Bedingung, $Relais1Kontakt4Auswertung, $Geraete );
      }
    }
    else {
      // Der Kontakt 1 soll per Zeitsteuerung ausgeschaltet werden.
      // Ist der Zeitstempel vorhanden?
      if ($var ["Relais1Kontakt4Timestamp"] == 0) {
        // Falls nicht eintragen.
        $sql = "Update config set Relais1Kontakt4Timestamp = ".time( )." where Id = 1";
        $statement = $db->query( $sql );
        $Startzeit = time( );
      }
      else {
        $Startzeit = $var ["Relais1Kontakt4Timestamp"];
        if (($Startzeit + ($var ["Relais1K4ausMinuten"] * 60) - 20) <= time( )) {
          $Relais1Kontakt4Auswertung = 1;
        }
        else {
          $Relais1Kontakt4Auswertung = 0;
          Log::write( "es dauert noch ".(($Startzeit + ($var ["Relais1K4ausMinuten"] * 60)) - time( ))." Sekunden bis zur Abschaltung", "", 3 );
        }
      }
    }

    /**********************************************************************
    //  Kontaktänderung durch Benutzerauswertung
    **********************************************************************/
    if (isset($UserKontaktAuswertung["Relais1"]["Kontakt4"]) and $UserKontaktAuswertung["Relais1"]["Kontakt4"] == 0) {
      $Relais1Kontakt4Auswertung = 1;
      Log::write( "Relais 1 Kontakt 4 wird jetzt durch Benutzer Berechnung ausgeschaltet.", "", 2 );
    }
    elseif (isset($UserKontaktAuswertung["Relais1"]["Kontakt4"]) and $UserKontaktAuswertung["Relais1"]["Kontakt4"] == 1) {
      $Relais1Kontakt4Auswertung = 0;
      Log::write( "Relais 1 Kontakt 4 bleibt durch Benutzer Berechnung eingeschaltet.", "", 2 );
    }
    if ($Relais1Kontakt4Auswertung == 1) {

      /********************************************************************
      //  Das Relais 1 Kontakt 1 muss ausgeschaltet werden!
      //
      ********************************************************************/

      /********************************************************************
      // Schalten von Relais Kontakten:
      // relais_schalten(Datenbank,Mosquitto,Relaiskontakt,Topic,Wert,QoS)
      // $var["Relais1Kontakt4"] wird aktualisiert
      ********************************************************************/
      Log::write( "Relais 1 Kontakt 4 wird aus geschaltet.", "", 2 );
      $rc = relais_schalten( $db, $client, "Relais1Kontakt4", $Relais1[4], $Relais1WertOFF, 1 );
    }
  }
}
weiter:

/**********************************************************************
//  Relais Steuerkreis 2   Relais Steuerkreis 2   Relais Steuerkreis 2
//  Relais Steuerkreis 2   Relais Steuerkreis 2   Relais Steuerkreis 2
//
//  Hier beginnt die Auswertung und Steuerung
//
//  *****************************************
**********************************************************************/
if ($var ["Relais2aktiv"] == 1) {
  Log::write( "Relais 2 ist aktiviert.", "", 3 );

  /************************************************************************
  //  Abfrage der Kontakte aus den aktiven Relais
  //  $db,$client,$relais,$topic,$wert
  ************************************************************************/
  if ($var ["Relais2TopicFormat"] == 0) {
    $Relais2Kontakte = relais_abfragen( $db, $client, 2, $var ["Relais2Topic"]."/cmnd/status", null );
  }
  if ($var ["Relais2TopicFormat"] == 1) {
    $Relais2Kontakte = relais_abfragen( $db, $client, 2, "cmnd/".$var ["Relais2Topic"]."/status", null );
  }
  if (count( $Relais2Kontakte ) == 0) {
    Log::write( "Keine Antwort vom Relais 2", "", 2 );
    goto ende;
  }
  $MQTTDaten = array();
  if (!isset($Relais2Kontakte[2])) {
    $Relais2Kontakte[2] = 0;
  }
  if (!isset($Relais2Kontakte[3])) {
    $Relais2Kontakte[3] = 0;
  }
  if (!isset($Relais2Kontakte[4])) {
    $Relais2Kontakte[4] = 0;
  }

  /***************************************************************************
  //  EIN     EIN     EIN     EIN     EIN     EIN     EIN     EIN     EIN
  //  EIN     EIN     EIN     EIN     EIN     EIN     EIN     EIN     EIN
  ***************************************************************************/
  if ($Relais2Kontakte[1] == 0) {
    Log::write( "Relais 2 Kontakt 1 ist ausgeschaltet", "", 3 );

    /********************************************************************
    //  Start Logik
    //  Relais 2 Kontakt 1 ist zur Zeit ausgeschaltet
    //  EIN     EIN     EIN     EIN     EIN     EIN     EIN     EIN
    ********************************************************************/
    $Relais2Kontakt1Auswertung = 0;
    $i = 1;
    $Geraete = 0;
    if ($LRaktiv and $var ["Relais2K1PVein"] != null) {
      if ($PVLeistung >= $var ["Relais2K1PVein"]) {
        Log::write( "PV Leistung ".$PVLeistung." ist größer/gleich Vorgabe: ".$var ["Relais2K1PVein"], "", 3 );
        $Relais2Kontakt1Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        Log::write( "PV Leistung ".$PVLeistung." ist niedriger als die Vorgabe: ".$var ["Relais2K1PVein"], "", 2 );
        $Ergebnis[$i] = false;
      }
      $Bedingung[$i] = $var ["Relais2K1PVBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($WRaktiv and $var ["Relais2K1ACein"] != null) {
      if ($ACLeistung >= $var ["Relais2K1ACein"]) {
        Log::write( "AC Leistung ".$ACLeistung." ist größer/gleich Vorgabe: ".$var ["Relais2K1ACein"], "", 3 );
        $Relais2Kontakt1Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "AC Leistung ".$ACLeistung." ist niedriger als die Vorgabe: ".$var ["Relais2K1ACein"], "", 2 );
      }
      $Bedingung[$i] = $var ["Relais2K1ACBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($SMaktiv and $var ["Relais2K1SMein"] != null) {
      if ($Einspeisung >= $var ["Relais2K1SMein"]) {
        Log::write( "Einspeisung ".$Einspeisung." ist größer/gleich Vorgabe: ".$var ["Relais2K1SMein"], "", 3 );
        $Relais2Kontakt1Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "Einspeisung ".$Einspeisung." ist niedriger als die Vorgabe: ".$var ["Relais2K1SMein"], "", 2 );
      }
      $Bedingung[$i] = $var ["Relais2K1SMBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($BMSaktiv and $var ["Relais2K1BMSein"] != null) {
      if ($SOC >= $var ["Relais2K1BMSein"]) {
        Log::write( "SOC ".$SOC."% ist größer/gleich Vorgabe: ".$var ["Relais2K1BMSein"]."%", "", 3 );
        $Relais2Kontakt1Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "SOC ".$SOC."% ist niedriger als die Vorgabe: ".$var ["Relais2K1BMSein"]."%", "", 2 );
      }
      $i++;
      $Geraete++;
    }
    if ($Geraete > 1) {
      $Relais2Kontakt1Auswertung = auswertung( $Ergebnis, $Bedingung, $Relais2Kontakt1Auswertung, $Geraete );
    }

    /**********************************************************************
    //  Kontaktänderung durch Benutzerauswertung
    **********************************************************************/
    if (isset($UserKontaktAuswertung["Relais2"]["Kontakt1"]) and $UserKontaktAuswertung["Relais2"]["Kontakt1"] == 1) {
      $Relais2Kontakt1Auswertung = 1;
      Log::write( "Relais 2 Kontakt 1 wird jetzt durch Benutzer Berechnung eingeschaltet.", "", 2 );
    }
    elseif (isset($UserKontaktAuswertung["Relais2"]["Kontakt1"]) and $UserKontaktAuswertung["Relais2"]["Kontakt1"] == 0) {
      $Relais2Kontakt1Auswertung = 0;
      Log::write( "Relais 2 Kontakt 1 bleibt durch Benutzer Berechnung ausgeschaltet.", "", 2 );
    }
    if ($Relais2Kontakt1Auswertung == 1) {

      /********************************************************************
      //  Das Relais 2 Kontakt 1 muss eingeschaltet werden!
      //
      ********************************************************************/

      /********************************************************************
      // Schalten von Relais Kontakten:
      // relais_schalten(Datenbank,Mosquitto,Relaiskontakt,Topic,Wert,QoS)
      // $var["Relais2Kontakt1"] wird aktualisiert
      ********************************************************************/
      Log::write( "Relais 2 Kontakt 1 wird jetzt ein geschaltet.", "", 2 );
      $rc = relais_schalten( $db, $client, "Relais2Kontakt1", $Relais2[1], $Relais2WertON, 1 );
    }
  }

  /****************************************************************************/
  if ($Relais2Kontakte[2] == 0 and $var ["Relais2AnzKontakte"] > 1) {
    Log::write( "Relais 2 Kontakt 2 ist ausgeschaltet", "", 3 );

    /********************************************************************
    //  Start Logik
    //  Relais 2 Kontakt 2 ist zur Zeit ausgeschaltet
    //  EIN     EIN     EIN     EIN     EIN     EIN     EIN     EIN
    ********************************************************************/
    $Relais2Kontakt2Auswertung = 0;
    $i = 1;
    $Geraete = 0;
    if ($LRaktiv and $var ["Relais2K2PVein"] != null) {
      if ($PVLeistung >= $var ["Relais2K1PVein"]) {
        Log::write( "PV Leistung ".$PVLeistung." ist größer/gleich Vorgabe: ".$var ["Relais2K2PVein"], "", 3 );
        $Relais2Kontakt2Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        Log::write( "PV Leistung ".$PVLeistung." ist niedriger als die Vorgabe: ".$var ["Relais2K2PVein"], "", 2 );
        $Ergebnis[$i] = false;
      }
      $Bedingung[$i] = $var ["Relais2K2PVBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($WRaktiv and $var ["Relais2K2ACein"] != null) {
      if ($ACLeistung >= $var ["Relais2K2ACein"]) {
        Log::write( "AC Leistung ".$ACLeistung." ist größer/gleich Vorgabe: ".$var ["Relais2K2ACein"], "", 3 );
        $Relais2Kontakt2Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "AC Leistung ".$ACLeistung." ist niedriger als die Vorgabe: ".$var ["Relais2K2ACein"], "", 2 );
      }
      $Bedingung[$i] = $var ["Relais2K2ACBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($SMaktiv and $var ["Relais2K2SMein"] != null) {
      if ($Einspeisung >= $var ["Relais2K2SMein"]) {
        Log::write( "Einspeisung ".$Einspeisung." ist größer/gleich Vorgabe: ".$var ["Relais2K2SMein"], "", 3 );
        $Relais2Kontakt2Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "Einspeisung ".$Einspeisung." ist niedriger als die Vorgabe: ".$var ["Relais2K2SMein"], "", 2 );
      }
      $Bedingung[$i] = $var ["Relais2K2SMBedingungein"];
      $i++;
      $Geraete++;
    }
    if ($BMSaktiv and $var ["Relais2K2BMSein"] != null) {
      if ($SOC >= $var ["Relais2K2BMSein"]) {
        Log::write( "SOC ".$SOC."% ist größer/gleich Vorgabe: ".$var ["Relais2K2BMSein"]."%", "", 3 );
        $Relais2Kontakt2Auswertung = 1;
        $Ergebnis[$i] = true;
      }
      else {
        $Ergebnis[$i] = false;
        Log::write( "SOC ".$SOC."% ist niedriger als die Vorgabe: ".$var ["Relais2K2BMSein"]."%", "", 2 );
      }
      $i++;
      $Geraete++;
    }
    if ($Geraete > 1) {
      $Relais2Kontakt2Auswertung = auswertung( $Ergebnis, $Bedingung, $Relais2Kontakt2Auswertung, $Geraete );
    }

    /**********************************************************************
    //  Kontaktänderung durch Benutzerauswertung
    **********************************************************************/
    if (isset($UserKontaktAuswertung["Relais2"]["Kontakt2"]) and $UserKontaktAuswertung["Relais2"]["Kontakt2"] == 1) {
      $Relais2Kontakt2Auswertung = 1;
      Log::write( "Relais 2 Kontakt 2 wird jetzt durch Benutzer Berechnung eingeschaltet.", "", 2 );
    }
    elseif (isset($UserKontaktAuswertung["Relais2"]["Kontakt2"]) and $UserKontaktAuswertung["Relais2"]["Kontakt2"] == 0) {
      $Relais2Kontakt2Auswertung = 0;
      Log::write( "Relais 2 Kontakt 2 bleibt durch Benutzer Berechnung ausgeschaltet.", "", 2 );
    }
    if ($Relais2Kontakt2Auswertung == 1) {

      /********************************************************************
      //  Das Relais 2 Kontakt 2 muss eingeschaltet werden!
      //
      ********************************************************************/

      /********************************************************************
      // Schalten von Relais Kontakten:
      // relais_schalten(Datenbank,Mosquitto,Relaiskontakt,Topic,Wert,QoS)
      // $var["Relais2Kontakt1"] wird aktualisiert
      ********************************************************************/
      Log::write( "Relais 2 Kontakt 2 wird jetzt ein geschaltet.", "", 2 );
      $rc = relais_schalten( $db, $client, "Relais2Kontakt2", $Relais2[2], $Relais2WertON, 1 );
    }
  }

  /***************************************************************************
  //  AUS     AUS     AUS     AUS     AUS     AUS     AUS     AUS     AUS
  //  AUS     AUS     AUS     AUS     AUS     AUS     AUS     AUS     AUS
  ***************************************************************************/
  if ($Relais2Kontakte[1] == 1) {
    //  Soll der Kontakt per Zeiteinstellung ausgeschaltet werden? ( 0 = nein )
    if ($var ["Relais2K1ausMinuten"] == 0) {
      Log::write( "Relais 2 Kontakt 1 ist eingeschaltet", "", 3 );

      /********************************************************************
      //  Start Logik
      //  Relais 2 Kontakt 1 ist zur Zeit eingeschaltet
      //  AUS     AUS     AUS     AUS     AUS     AUS     AUS     AUS
      ********************************************************************/
      $Relais2Kontakt1Auswertung = 0;
      $i = 1;
      $Geraete = 0;
      if ($LRaktiv and $var ["Relais2K1PVaus"] != null) {
        if ($PVLeistung < $var ["Relais2K1PVaus"]) {
          Log::write( "PV Leistung ".$PVLeistung." ist niedriger als die Vorgabe: ".$var ["Relais2K1PVaus"], "", 3 );
          $Relais2Kontakt1Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "PV Leistung ".$PVLeistung." ist größer als die Vorgabe: ".$var ["Relais2K1PVaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais2K1PVBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($WRaktiv and $var ["Relais2K1ACaus"] != null) {
        if ($ACLeistung < $var ["Relais2K1ACaus"]) {
          Log::write( "AC Leistung ".$ACLeistung." ist niedriger als die Vorgabe: ".$var ["Relais2K1ACaus"], "", 3 );
          $Relais2Kontakt1Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "AC Leistung ".$ACLeistung." ist größer als die Vorgabe: ".$var ["Relais2K1ACaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais2K1ACBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($SMaktiv and $var ["Relais2K1SMaus"] != null) {
        if ($Einspeisung < $var ["Relais2K1SMaus"]) {
          Log::write( "Einspeisung ".$Einspeisung." ist niedriger als die Vorgabe: ".$var ["Relais2K1SMaus"], "", 3 );
          $Relais2Kontakt1Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "Einspeisung ".$Einspeisung." ist größer als die Vorgabe: ".$var ["Relais2K1SMaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais2K1SMBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($BMSaktiv and $var ["Relais2K1BMSaus"] != null) {
        if ($SOC < $var ["Relais2K1BMSaus"]) {
          Log::write( "SOC ".$SOC."% ist niedriger als die Vorgabe: ".$var ["Relais2K1BMSaus"]."%", "", 3 );
          $Relais2Kontakt1Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "SOC ".$SOC."% ist größer als die Vorgabe: ".$var ["Relais2K1BMSaus"]."%", "", 2 );
        }
        $i++;
        $Geraete++;
      }
      if ($Geraete > 1) {
        $Relais2Kontakt1Auswertung = auswertung( $Ergebnis, $Bedingung, $Relais2Kontakt1Auswertung, $Geraete );
      }
    }
    else {
      // Der Kontakt 1 soll per Zeitsteuerung ausgeschaltet werden.
      // Ist der Zeitstempel vorhanden?
      if ($var ["Relais2Kontakt1Timestamp"] == 0) {
        // Falls nicht eintragen.
        $sql = "Update config set Relais2Kontakt1Timestamp = ".time( )." where Id = 1";
        $statement = $db->query( $sql );
        $Startzeit = time( );
      }
      else {
        $Startzeit = $var ["Relais2Kontakt1Timestamp"];
        if (($Startzeit + ($var ["Relais2K1ausMinuten"] * 60) - 20) <= time( )) {
          $Relais2Kontakt1Auswertung = 1;
        }
        else {
          $Relais2Kontakt1Auswertung = 0;
          Log::write( "es dauert noch ".(($Startzeit + ($var ["Relais2K1ausMinuten"] * 60)) - time( ))." Sekunden bis zur Abschaltung", "", 3 );
        }
      }
    }

    /**********************************************************************
    //  Kontaktänderung durch Benutzerauswertung
    **********************************************************************/
    if (isset($UserKontaktAuswertung["Relais2"]["Kontakt1"]) and $UserKontaktAuswertung["Relais2"]["Kontakt1"] == 0) {
      $Relais2Kontakt1Auswertung = 1;
      Log::write( "Relais 2 Kontakt 1 wird jetzt durch Benutzer Berechnung ausgeschaltet.", "", 2 );
    }
    elseif (isset($UserKontaktAuswertung["Relais2"]["Kontakt1"]) and $UserKontaktAuswertung["Relais2"]["Kontakt1"] == 1) {
      $Relais2Kontakt1Auswertung = 0;
      Log::write( "Relais 2 Kontakt 1 bleibt durch Benutzer Berechnung eingeschaltet.", "", 2 );
    }
    if ($Relais2Kontakt1Auswertung == 1) {

      /********************************************************************
      //  Das Relais 2 Kontakt 1 muss ausgeschaltet werden!
      //
      ********************************************************************/

      /********************************************************************
      // Schalten von Relais Kontakten:
      // relais_schalten(Datenbank,Mosquitto,Relaiskontakt,Topic,Wert,QoS)
      // $var["Relais2Kontakt1"] wird aktualisiert
      ********************************************************************/
      Log::write( "Relais 2 Kontakt 1 wird aus geschaltet.", "", 2 );
      $rc = relais_schalten( $db, $client, "Relais2Kontakt1", $Relais2[1], $Relais2WertOFF, 1 );
    }
  }
  if ($Relais2Kontakte[2] == 1) {
    //  Soll der Kontakt per Zeiteinstellung ausgeschaltet werden? ( 0 = nein )
    if ($var ["Relais2K2ausMinuten"] == 0 and $var ["Relais2AnzKontakte"] > 1) {
      Log::write( "Relais 2 Kontakt 2 ist eingeschaltet", "", 3 );

      /********************************************************************
      //  Start Logik
      //  Relais 2 Kontakt 2 ist zur Zeit eingeschaltet
      //  AUS     AUS     AUS     AUS     AUS     AUS     AUS     AUS
      ********************************************************************/
      $Relais2Kontakt2Auswertung = 0;
      $i = 1;
      $Geraete = 0;
      if ($LRaktiv and $var ["Relais2K2PVaus"] != null) {
        if ($PVLeistung < $var ["Relais2K2PVaus"]) {
          Log::write( "PV Leistung ".$PVLeistung." ist niedriger als die Vorgabe: ".$var ["Relais2K2PVaus"], "", 3 );
          $Relais2Kontakt2Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "PV Leistung ".$PVLeistung." ist größer als die Vorgabe: ".$var ["Relais2K2PVaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais2K2PVBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($WRaktiv and $var ["Relais2K2ACaus"] != null) {
        if ($ACLeistung < $var ["Relais2K2ACaus"]) {
          Log::write( "AC Leistung ".$ACLeistung." ist niedriger als die Vorgabe: ".$var ["Relais2K2ACaus"], "", 3 );
          $Relais2Kontakt2Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "AC Leistung ".$ACLeistung." ist größer als die Vorgabe: ".$var ["Relais2K2ACaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais2K2ACBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($SMaktiv and $var ["Relais2K2SMaus"] != null) {
        if ($Einspeisung < $var ["Relais2K2SMaus"]) {
          Log::write( "Einspeisung ".$Einspeisung." ist niedriger als die Vorgabe: ".$var ["Relais2K2SMaus"], "", 3 );
          $Relais2Kontakt2Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "Einspeisung ".$Einspeisung." ist größer als die Vorgabe: ".$var ["Relais2K2SMaus"], "", 2 );
        }
        $Bedingung[$i] = $var ["Relais2K2SMBedingungaus"];
        $i++;
        $Geraete++;
      }
      if ($BMSaktiv and $var ["Relais2K2BMSaus"] != null) {
        if ($SOC < $var ["Relais2K2BMSaus"]) {
          Log::write( "SOC ".$SOC."% ist niedriger als die Vorgabe: ".$var ["Relais2K2BMSaus"]."%", "", 3 );
          $Relais2Kontakt2Auswertung = 1;
          $Ergebnis[$i] = true;
        }
        else {
          Log::write( "SOC ".$SOC."% ist größer als die Vorgabe: ".$var ["Relais2K2BMSaus"]."%", "", 2 );
        }
        $i++;
        $Geraete++;
      }
      if ($Geraete > 1) {
        $Relais2Kontakt1Auswertung = auswertung( $Ergebnis, $Bedingung, $Relais2Kontakt2Auswertung, $Geraete );
      }
    }
    else {
      // Der Kontakt 2 soll per Zeitsteuerung ausgeschaltet werden.
      // Ist der Zeitstempel vorhanden?
      if ($var ["Relais2Kontakt2Timestamp"] == 0) {
        // Falls nicht eintragen.
        $sql = "Update config set Relais2Kontakt2Timestamp = ".time( )." where Id = 1";
        $statement = $db->query( $sql );
        $Startzeit = time( );
      }
      else {
        $Startzeit = $var ["Relais2Kontakt2Timestamp"];
        if (($Startzeit + ($var ["Relais2K2ausMinuten"] * 60) - 20) <= time( )) {
          $Relais2Kontakt2Auswertung = 1;
        }
        else {
          $Relais2Kontakt2Auswertung = 0;
          Log::write( "es dauert noch ".(($Startzeit + ($var ["Relais2K2ausMinuten"] * 60)) - time( ))." Sekunden bis zur Abschaltung", "", 3 );
        }
      }
    }

    /**********************************************************************
    //  Kontaktänderung durch Benutzerauswertung
    **********************************************************************/
    if (isset($UserKontaktAuswertung["Relais2"]["Kontakt2"]) and $UserKontaktAuswertung["Relais2"]["Kontakt2"] == 0) {
      $Relais2Kontakt2Auswertung = 1;
      Log::write( "Relais 2 Kontakt 2 wird jetzt durch Benutzer Berechnung ausgeschaltet.", "", 2 );
    }
    elseif (isset($UserKontaktAuswertung["Relais2"]["Kontakt2"]) and $UserKontaktAuswertung["Relais2"]["Kontakt2"] == 1) {
      $Relais2Kontakt2Auswertung = 0;
      Log::write( "Relais 2 Kontakt 2 bleibt durch Benutzer Berechnung eingeschaltet.", "", 2 );
    }
    if ($Relais2Kontakt2Auswertung == 1) {

      /********************************************************************
      //  Das Relais 2 Kontakt 2 muss ausgeschaltet werden!
      //
      ********************************************************************/

      /********************************************************************
      // Schalten von Relais Kontakten:
      // relais_schalten(Datenbank,Mosquitto,Relaiskontakt,Topic,Wert,QoS)
      // $var["Relais2Kontakt1"] wird aktualisiert
      ********************************************************************/
      Log::write( "Relais 2 Kontakt 2 wird aus geschaltet.", "", 2 );
      $rc = relais_schalten( $db, $client, "Relais2Kontakt2", $Relais2[2], $Relais2WertOFF, 1 );
    }
  }
}
ende:$client->disconnect( );
Ausgang:$db1 = null;
$db2 = null;
unset($client);
Log::write( "---------------------------------------------------------", "ENDE", 1 );
exit;

/***************************************************************************************/

/***************************************************************************************/
function db_connect( $Database ) {
  return new PDO( 'sqlite:'.$Database );
}
function influxDB_lesen( $Datenbankname, $Measurement, $Bedingung = "" ) {
  // Alle aktuellen Daten eines Measurement lesen.
  //
  $ch = curl_init( 'http://localhost/query?db='.$Datenbankname.'&precision=s&q='.urlencode( 'select * from '.$Measurement.'  '.$Bedingung.' order by time desc limit 1' ));
  $rc = datenbank( $ch );
  if (!isset($rc["JSON_Ausgabe"]["results"][0]["series"])) {
    if (isset($rc["JSON_Ausgabe"]["results"][0]["error"])) {
      Log::write( $rc["JSON_Ausgabe"]["results"][0]["error"], "", 1 );
      return false;
    }
    if ($Measurement <> "awattarPreise") {
      Log::write( "Datenbank: ".$Datenbankname." Measurement: ".$Measurement." Bedingung: ".$Bedingung." -> Keine Daten vorhanden.", "", 1 );
    }
    return false;
  }
  else {
    for ($i = 1; $i < count( $rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"] ); $i++) {
      // Float Round 4 Stellen nach dem Komma, nur bei Preis_kWh
      if ($rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][$i] == "Preis_kWh") {
        $influxDB[$rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][$i]] = round( (float) $rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][$i], 4 );
      }
      else {
        $influxDB[$rc["JSON_Ausgabe"]["results"][0]["series"][0]["columns"][$i]] = $rc["JSON_Ausgabe"]["results"][0]["series"][0]["values"][0][$i];
      }
    }
    Log::write( "Datenbank: '".$Datenbankname."' ".print_r( $influxDB, 1 ), "", 4 );
  }
  return $influxDB;
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
function connect( $r, $message ) {
  global $Brokermeldung;
  Log::write( "Broker: ".$message, "", 3 );
  $Brokermeldung = $message;
}
function publish( ) {
  Log::write( "Mesage published.", "", 4 );
}
function disconnect( ) {
  Log::write( "Broker disconnect erfolgreich.", "", 3 );
}
function subscribe( ) {
  Log::write( "Subscribed to a topic.", "", 4 );
}
function logger( ) {
  global $MQTTDaten;
  Log::write( print_r( func_get_args( ), 1 ), "", 4 );
  $p = func_get_args( );
  $MQTTDaten["MQTTStatus"] = $p;
  // print_r($p);
}
function message( $message ) {
  global $MQTTDaten;
  $MQTTDaten["MQTTRetain"] = 0;
  $MQTTDaten["MQTTMessageReturnText"] = "RX-OK";
  $MQTTDaten["MQTTNachricht"] = $message->payload;
  $MQTTDaten["MQTTTopic"] = $message->topic;
  $MQTTDaten["MQTTQos"] = $message->qos;
  $MQTTDaten["MQTTMid"] = $message->mid;
  $MQTTDaten["MQTTRetain"] = $message->retain;
}
function relais_schalten( $db, $client, $relais, $topic, $wert, $qos = 1 ) {
  switch (strtoupper( $wert )) {

    case "ON":
    case "AN":
      $zustand = 1;
      break;

    case "OFF":
    case "AUS":
      $zustand = 0;
      break;

    default:
      $zustand = 0;
  }
  $mid = 0;
  while ($mid == 0) {
    $client->loop( );
    //  $client->publish($topic, $wert, $qos, [$retain])
    $mid = $client->publish( $topic, $wert, $qos );
    Log::write( "Sent message ID: {$mid} ".$topic." ".$wert, "", 3 );
    $client->loop( );
  }
  if ($zustand == 1) {
    $sql = "Update config set ".$relais." = ".$zustand.", ".$relais."Timestamp = ".time( )." where Id = 1";
  }
  else {
    $sql = "Update config set ".$relais." = ".$zustand." where Id = 1";
  }
  $statement = $db->query( $sql );
  if ($statement->rowCount( ) != 1) {
    Log::write( "Update nicht erfolgt. [ ".$sql." ]", "", 2 );
    Log::write( print_r( $statement->errorInfo( ), 1 ), "", 2 );
    return false;
  }
  return $mid;
}
function relais_abfragen( $db, $client, $relais, $topic, $wert ) {
  global $MQTTDaten;
  $Power = array();
  $sql = "SELECT * FROM config";
  $result = $db->query( $sql )->fetchAll( PDO::FETCH_ASSOC );
  //  Alle Felder auslesen
  $var = $result[0];
  $client->loop( );
  if ($relais == 1) {
    $client->subscribe( "+/".$var ["Relais1Topic"]."/#", 0 ); // Subscribe
    $client->subscribe( $var ["Relais1Topic"]."/#", 0 ); // Subscribe
  }
  else {
    $client->subscribe( "+/".$var ["Relais2Topic"]."/#", 0 ); // Subscribe
    $client->subscribe( $var ["Relais2Topic"]."/#", 0 ); // Subscribe
  }
  $client->loop( );
  for ($i = 1; $i < 40; $i++) { // geändert 20 -> 40  16.04.2021
    if (substr( $MQTTDaten["MQTTStatus"][1], - 7 ) == "PINGREQ") {
      Log::write( "Keine Antwort vom Broker (Relais). Abbruch!", "", 3 );
      break;
    }
    if (isset($MQTTDaten["MQTTNachricht"]))

      /*********************************************************************
      //  MQTT Meldungen empfangen. Subscribing    Subscribing    Subscribing
      //  Hier werden die Daten vom Mosquitto Broker gelesen.
      *********************************************************************/
      Log::write( print_r( $MQTTDaten, 1 ), "", 4 );
    //  Ist das Relais "ONLINE"?
    if (isset($MQTTDaten["MQTTNachricht"])) {
      Log::write( "Nachricht: ".$MQTTDaten["MQTTNachricht"], "", 4 );
      if (strtoupper( $MQTTDaten["MQTTNachricht"] ) == "ONLINE") {
        // Gerät ist online
        Log::write( "Topic: ".$MQTTDaten["MQTTTopic"], "   ", 3 );
        $MQTTDaten = array();
        $MQTTDaten["MQTTPublishReturnCode"] = $client->publish( $topic, 0, 0, false );
        $client->loop( );
      }
      else {
        $values = json_decode( $MQTTDaten["MQTTNachricht"], true );
        if (is_array( $values )) {
          if (isset($values["StatusSTS"])) {
            Log::write( print_r( $values["StatusSTS"], 1 ), "", 4 );
            foreach ($values["StatusSTS"] as $k => $v) {
              $inputs[] = array("name" => $k, "value" => $v);
              if ($k == "POWER") {
                // Powerstatus
                if (strtoupper( $v ) == "ON")
                  $Power[1] = 1;
                else
                  $Power[1] = 0;
              }
              if ($k == "POWER1") {
                // Powerstatus
                if (strtoupper( $v ) == "ON")
                  $Power[1] = 1;
                else
                  $Power[1] = 0;
              }
              if ($k == "POWER2") {
                // Powerstatus
                if (strtoupper( $v ) == "ON")
                  $Power[2] = 1;
                else
                  $Power[2] = 0;
              }
              if ($k == "POWER3") {
                // Powerstatus
                if (strtoupper( $v ) == "ON")
                  $Power[3] = 1;
                else
                  $Power[3] = 0;
              }
              if ($k == "POWER4") {
                // Powerstatus
                if (strtoupper( $v ) == "ON")
                  $Power[4] = 1;
                else
                  $Power[4] = 0;
              }
            }
            break;
          }
        }
      }
    }
    $client->loop( );
  }
  return $Power;
}

function auswertung( $Ergebnis, $Bedingung, $KontaktAuswertung, $Geraete ) {
  Log::write( "Mehr als eine Bedingung aktiv", "", 3 );
  for ($i = 1; $i <= $Geraete; $i++) {
    if ($i < $Geraete) {
      Log::write( "Verknüpfung mit: ".$Bedingung[$i], "", 3 );
    }
    if (isset($Bedingung[$i])) {
      if ($Bedingung[$i] == "and") {
        if ($i == 1 and !$Ergebnis[$i]) {
          $KontaktAuswertung = 0;
          Log::write( $i.". Parameter im Vergleich ist falsch. (and) ", "", 3 );
          break;
        }
        if (!$Ergebnis[$i] and !$Ergebnis[$i + 1]) {
          $KontaktAuswertung = 0;
          Log::write( "Beide Parameter im Vergleich sind falsch. (and) ", "", 3 );
          break;
        }
        if ($Ergebnis[$i] and $Ergebnis[$i + 1]) {
          $KontaktAuswertung = 1;
          Log::write( "Beide Parameter sind wahr. (and) ", "", 3 );
        }
        else {
          $Ergebnis[$i + 1] = false;
          Log::write( "Ein Parameter ist falsch. (and) ", "", 3 );
          $KontaktAuswertung = 0;
        }
      }
      elseif ($Bedingung[$i] == "or") {
        if ($Ergebnis[$i] or $Ergebnis[$i + 1]) {
          $Ergebnis[$i + 1] = true;
          $KontaktAuswertung = 1;
          Log::write( "Einer der beiden Parameter ist wahr. (or) ", "", 3 );
        }
        else {
          $KontaktAuswertung = 0;
          Log::write( "Beide Parameter sind falsch. (or) ", "", 3 );
        }
      }
      else {
      }
      Log::write( "Relais schalten? ".($KontaktAuswertung ? "ja":"nein"), "", 3 );
    }
  }
  return $KontaktAuswertung;
}
?>