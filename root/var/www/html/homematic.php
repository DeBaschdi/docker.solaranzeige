<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2020]  [Ulrich Kunz]
//
//  Dieses Programm ist freie Software. Sie können es unter den Bedingungen
//  der GNU General Public License, wie von der Free Software Foundation
//  veröffentlicht, weitergeben und/oder modifizieren, entweder gemäß
//  Version 3 der Lizenz oder (nach Ihrer Option) jeder späteren Version.
//
//  Die Ver�ffentlichung dieses Programms erfolgt in der Hoffnung, dass es
//  Ihnen von Nutzen sein wird, aber OHNE IRGENDEINE GARANTIE, sogar ohne
//  die implizite Garantie der MARKTREIFE oder der VERWENDBARKEIT FÜR EINEN
//  BESTIMMTEN ZWECK. Details finden Sie in der GNU General Public License.
//
//  Ein original Exemplar der GNU General Public License finden Sie hier:
//  http://www.gnu.org/licenses/
//
//  Dies ist ein Programmteil des Programms "Solaranzeige"
//
//  Es dient dem übertragen der Daten an eine HOMEMATIC Zentrale.
//  Welche Daten an die Homematic Zentrale übertragen werden und wie oft,
//  wird hier festgelegt.
//
//  Diese Funktion ist nur eingeschaltet, wenn in der user.config.php
//  $Homematic = true  eingetragen ist.
//
*****************************************************************************/
//  Hier werden die HomeMatic Variablen ausgewählt.
//  Die Namen der Variablen sind fix und dürfen nicht geändert werden.
//  Man kann den Namen jedoch eine Zahl zwischen 0 und 9 anhängen, wenn
//  mehrere Raspberry's daten an die HomeMatic senden.
//  Beispiel:  Batteriespannung1
//  Die Zahl muss unmittelbar an den Namen angehängt werden. Kein Unterstrich
//  oder Bindestrich! Genauso muss auch dioe Variable dann in der HomeMatic
//  heißen. Wie im Beispiel oben also Batteriespannung1
//  Folgende Variablen sind möglich bei den einzelnen Reglern:
//
//  IVT-Hirschau SCplus oder SCDplus Regler  No. 1
//-------------------------------------------------
//  Batteriespannung
//  Solarleistung
//  SolarleistungTag
//  BatterieLadestrom
//  Solarspannung
//  BatterieEntladestrom
//
//
//  Regler der Tracer Serie                  No. 3
//--------------------------------------------------
//  BatterieLadestatus
//  BatteriestatusText
//  Batteriespannung
//  Solarleistung
//  SolarleistungTag
//  BatterieLadestrom
//  Solarspannung
//
//
//  BlueSolar und SmartSolar von Victron     No. 4
//---------------------------------------------------
//  BatterieLadestatus
//  BatteriestatusText
//  Batteriespannung
//  Solarleistung
//  SolarleistungTag
//  BatterieLadestrom
//  Solarspannung
//  Temperatur
//
//
//  Wechselrichter von AEconversion, Phoenix und
//  Fronius                            No. 5,11,12
//---------------------------------------------------
//  Geraetestatus
//  Solarspannung
//  Solarstrom
//  Solarleistung
//  acAusgangsspannung
//  acAusgangsstrom
//  acWirkleistung
//  SolarleistungTag
//  Nur bei Fronius Symo mit Meter:
//  Einspeisung
//  Verbrauch
//  Bezug
//  => Nur bei dem Fronius inkl. Batterie
//  LadestatusProzent   ( => SOC )
//
//
//
//
//  BMV und BMS                            No. 6
//---------------------------------------------------
//  Batteriespannung
//  Batteriestrom
//  SOC
//
//
//  Wechselrichter                         No. 7, 8
//---------------------------------------------------
//  Batteriespannung
//  Solarleistung
//  SolarleistungTag
//  Netzspannung
//  Netzfrequenz
//  acAusgangsspannung
//  acAusgangsfrequenz
//  acScheinleistung
//  acWirkleistung
//  Batteriekapazitaet
//  Temperatur
//  BatterieLadestrom
//  Solarspannung
//  Betriebsart
//
//
//  Wechselrichter  MPPSolar und Andere      No. 9
//---------------------------------------------------
//  Batteriespannung
//  Batteriestrom
//  Batteriestromrichtung
//  Batteriekapazitaet
//  Temperatur
//  Solarleistung
//  Solarspannung1
//  Solarspannung2
//  Betriebsart
//  Wirkleistung
//  Scheinleistung
//  Eingangsleistung
//  AC_Spannung
//  AC_Frequenz
//  WattstundenGesamtHeute
//
//
//
//  SolarMax S-Serie                    No. 10
//--------------------------------------------------
//  Solarspannung
//  Solarstrom
//  acAusgangsspannung
//  acAusgangsstrom
//  acWirkleistung
//  SolarleistungTag
//
//
//  Joulie-16 von AutarcTech            No. 13
//--------------------------------------------------
//  Spannung
//  Strom
//  Fehlercode
//  SOC
//  Kapazitaet
//
//
//  SolarEdge                          No. 16 und 20
//--------------------------------------------------
//  AC_Spannung
//  AC_Leistung
//  Einspeisung                 (nur bei Regler 16)
//  Verbrauch                   (nur bei Regler 16)
//  Einspeisung                 (nur bei Regler 16)
//  Solarspannung
//  Solarstrom
//  Solarleistung
//  Temperatur
//  LeistungTag
//
//
//  Kostal Plenticore und Pico       No. 17 + 21
//--------------------------------------------------
//  Ausgangslast                (nicht beim Piko)
//  Verbrauch                   (nicht beim Piko)
//  Einspeisung                 (nicht beim Piko)
//  Solarspannung1              (nicht beim Piko)
//  Solarspannung2              (nicht beim Piko)
//  Solarspannung3              (nicht beim Piko)
//  Solarleistung
//  SOC                         (nicht beim Piko)
//  Batteriespannung            (nicht beim Piko)
//  Batteriestrom               (nicht beim Piko)
//  LeistungTag
//  AC_Leistung
//  AC_Spannung
//
//
//  S10 E von E3/DC                     No. 18
//--------------------------------------------------
//  Wallbox
//  Verbrauch
//  Bezug
//  Solarleistung
//  Batterieladung
//  LeistungTag
//  String1_Leistung
//  String2_Leistung
//  String3_Leistung
//  SOC
//
//
//  Sonoff POW R2                      No. 23
//--------------------------------------------------
//  acAusgangsspannung          Volt
//  acAusgangsstrom             Ampere
//  acScheinleistung            Watt
//  acWirkleistung              Watt
//  Relaisstatus                ( 0/1 )
//  Status                      ( Text )
//
//
//  US2000B Batterie-Management-System  No. 15
//--------------------------------------------------
//  Packx_Strom                 Ampere  x = 1-10
//  Packx_Spannung              Volt    x = 1-10
//  Packx_Ah_left               Ah      x = 1-10
//
//
//
//  Sonnenbatterie                     No. 25
//--------------------------------------------------
//  Batteriespannung		    Volt
//  Batterieladung		        ( 0/1 )
//  PV_Ladung			        ( 0/1 )
//  Netzladung			        ( 0/1 )
//  SOC				            %
//  Leistung			        Watt
//  Verbrauch			        Watt
//  Einspeisung			        Watt
//  Batterieentladung			( 0/1 )
//  Einspeisung_Bezug		+/- Watt
//  Bezug			Watt
//
//
//
//  MPPSolar 5048                       No. 26
//  Licom Box                           No. 77
//--------------------------------------------------
//  Batteriespannung            Volt
//  Solarleistung               Watt
//  SolarleistungTag            Wh
//  Netzspannung                Volt
//  Netzfrequenz                Hertz
//  acAusgangsspannung          Volt
//  acAusgangsfrequenz          Hertz
//  acWirkleistung              Watt
//  Batteriekapazitaet          %
//  Temperatur                  °C
//  BatterieLadestrom           Ampere
//  Solarspannung               Volt
//  Nur MPPSolar 5048:
//  Status                      Zahl
//  BatterieEntladestrom        Ampere
//  DeviceStatus                Zahl
//
//
//
//
//  SMA Tripower                No. 27
//--------------------------------------------------
//  AC_Spannung		        Volt
//  AC_Leistung		        Watt
//  Einspeisung		        Watt
//  Bezug		            Watt
//  DC_Leistung1   	        Watt
//  DC_Leistung2   	        Watt
//  Geraetestatus	        Zahl
//  Temperatur		        °C
//  LeistungTag		        Wh
//  SOC                     %  (falls Wert vorhanden)
//
//
//
//  go-eCharger  Wallbox                No. 29
//--------------------------------------------------
//  aktive_Karte                RFID Karte
//  Gesamtleistung              Wattstunden  Wh
//  Spannung_R                  Volt
//  Spannung_S                  Volt
//  Spannung_T                  Volt
//  Strom_R                     Ampere
//  Strom_S                     Ampere
//  Strom_T                     Ampere
//  Leistung_R                  Watt
//  Leistung_S                  Watt
//  Leistung_T                  Watt
//  Leistung_gesamt             Watt
//  Karte1_Wh                   Wattstunden
//  Karte2_Wh                   Wattstunden
//  Karte3_Wh                   Wattstunden
//  Karte4_Wh                   Wattstunden
//  Karte5_Wh                   Wattstunden
//  Status                      1 = Ladestation Bereit, kein Fahrzeug
//                              2 = Fahrzeug lädt
//                              3 = Warte auf Aktivierung
//                              4 = Ladung beendet, Fahrzeug noch verbunden
//  Ladestrom_max               maximaler Ladestrom in Ampere
//
//
//  Shelly 3EM                          No. 31
//--------------------------------------------------
//  AC_Spannung_R               Volt
//  AC_Spannung_S               Volt
//  AC_Spannung_T               Volt
//  AC_Strom_R                  Ampere
//  AC_Strom_S                  Ampere
//  AC_Strom_T                  Ampere
//  AC_Leistung_R               Watt
//  AC_Leistung_S               Watt
//  AC_Leistung_T               Watt
//  Verbrauch_R                 Wattstunden
//  Verbrauch_S                 Wattstunden
//  Verbrauch_T                 Wattstunden
//  Einspeisung_R               Wattstunden
//  Einspeisung_S               Wattstunden
//  Einspeisung_T               Wattstunden
//  Wirkleistung_Gesamt         Watt
//  Relaisstatus                ( 0/1 )
//
//
//  Kaco Wechselrichter               No. 32
//--------------------------------------------------
//  AC_Ausgangsstrom            Ampere
//  AC_Ausgangsspannung_R       Volt
//  AC_Ausgangsspannung_S       Volt
//  AC_Ausgangsspannung_T       Volt
//  AC_Frequenz                 Hertz
//  AC_Leistung                 Watt
//  AC_Leistungsfaktor          %
//  WattstundenGesamtHeute      Wh
//  Solarspannung               Volt
//  Solarstrom                  Ampere
//  Solarleistung               Watt
//  Temperatur                  °C
//  Status                      Zahl
//  Warnungen                   Zahl
//
//
//
//
//  Alpah ESS                         No. 38
//--------------------------------------------------
//  PV_Leistung                 Watt
//  AC_Leistung                 Watt
//  Batteriespannung            Volt
//  Batteriestrom               Ampere
//  Batterieleistung            Watt
//  Batteriemodus               Zahl 0 - 4
//  Einspeisung                 Watt
//  Bezug                       Watt
//  BatterieSOC                 %
//  Wh_GesamtHeute              Wh
//  Temperatur                  °C
//
//
//
//
//  Phocos + PV18-3KW                 No. 40 + 42
//--------------------------------------------------
//  Netzleistung                Watt
//  AC_Leistung                 Watt
//  Batteriespannung            Volt
//  Batterieleistung            Watt
//  Batteriestrom               Ampere
//  BatterieRelais              Zahl 0 / 1
//  PV_Spannung                 Volt
//  Ausgangslast                Watt
//  MPPTStatus                  Zahl
//  Geraetestatus               Zahl
//  Wh_GesamtHeute              Wattstunden
//
//
//
//
//  Phocos Any-Grid                   No. 45
//--------------------------------------------------
//  AC_Ausgangslast             Watt
//  AC_Leistung                 Watt
//  Batteriespannung            Volt
//  Batteriestrom               Ampere
//  Batterie_SOC                %
//  PV_Leistung                 Watt
//  PV_Spannung                 Volt
//  ReglerMode                  Zahl
//  Geraetestatus               Zahl
//  Wh_GesamtHeute              Wh
//
//
//
//
//  Senec Stromspeicher                No. 43
//--------------------------------------------------
//  AC_Leistung                 Watt
//  AC_Frequenz                 Hertz
//  PV_Leistung                 Watt
//  DC_Strom                    Ampere
//  Batterie_SOC                %
//  Einspeisung                 Watt
//  Bezug                       Watt
//  Hausverbrauch               Watt
//  Batterie_Spannung           Volt
//  WattstundenGesamtHeute      Wh
//  Status                      Zahl
//
//
//
//  Growatt Wechselrichter      No. 48
//--------------------------------------------------
//  AC_Leistung                 Watt
//  Solarleistung               Watt
//  Temperatur                  °C
//  Wh_GesamtHeute              Wh
//
//
//
//  Goodwe Wechselrichter       No. 52
//  Huawei Wechselrichter       No. 56
//  Huawei Wechselrichter       No. 62
//  Goodwe Wechselrichter       No. 64
//  RCT Wechselrichter          No. 65
//  Sungrow Wechselrichter      No. 70
//--------------------------------------------------
//  AC_Leistung                 Watt
//  AC_Frequenz                 Hertz
//  PV_Leistung                 Watt
//  Einspeisung                 Watt
//  Bezug                       Watt
//  Batterie_SOC                %
//  Hausverbrauch               Watt
//  WattstundenGesamtHeute      Wh
//  Status                      Zahl
//
//
//  Verschiedene Zähler
//  Solarlog              No. 53
//  SMA Energy            No. 54
//  SDM630                No. 34
//  SDM230                No. 50
//  PAC2200               No. 51
//  Huawei Smartlogger    No. 49
//--------------------------------------------------
//  AC_Leistung                 Watt     nicht bei 49
//  AC_Strom                    Ampere   nicht bei 49,51
//  AC_Spannung                 Volt     nicht bei 49,51,54
//  Wh_Einspeisung              Wh
//  Wh_Bezug                    Wh
//  Bezug                       Watt     nicht bei 34,53
//  Einspeisung                 Watt     nicht bei 34,53
//  Verbrauch                   Watt     Nur 49
//  AC_Leistung_R               Watt     Nur 34,53,54
//  AC_Leistung_S               Watt     Nur 34,53,54
//  AC_Leistung_T               Watt     Nur 34,53,54
//
//
//
//  Falls Sie die Wetterdaten vom Wetterserver abholen, dann
//  können auch folgende Variablen zusätzlich benutzt werden.
//  -----------------------------------------------------------
//  Wolkendichte              0 - 100 %
//  Wind                      m/s
//  AussenTemperatur          °C
//
//  Bitte in die nächste Zeile alle Variablen, die man benutzen möchte und
//  die in der HomeMatic von Ihnen angelegt worden sind, aufzählen. Immer mit
//  einem Komma getrennt. Achten Sie bitte darauf, dass es
//  unterschiedliche Variablen bei den einzelnen Reglern gibt.
//  Die maximale Anzahl der Variablen, die benutzt werden können ist 21!
//  Es dürfen hier nicht mehr als 10 Variablen aufgezählt werden.
//  Auf Groß- und Kleinschreibung ist zu achten! Unbedingt auch das Dokument
//  HomeMatic_Anbindung.pdf lesen.
//  Wichtig!
//  Bitte die Variablen lieber in der Datei user.config.php eintragen. Dort werden
//  sie nicht gelöscht, wenn ein Update gemacht wird!
//
$HomeMaticVarBak = "Batteriespannung,Solarleistung,Temperatur,Solarspannung";
$Tracelevel_original = $Tracelevel;
$Tracelevel = 7;
$DataString = "";
$result = false;
if (!isset($HomeMaticVar) and !empty($HomeMaticVar)) {
  $HomeMaticVar = $HomeMaticVarBak;
}

/************************************************************
//  Prüfen ob eine Homematic Zentrale erreichbar ist.
//
************************************************************/
$rCurlHandle = curl_init( "http://".$Homematic_IP );
curl_setopt( $rCurlHandle, CURLOPT_CONNECTTIMEOUT, 10 );
curl_setopt( $rCurlHandle, CURLOPT_HEADER, TRUE );
curl_setopt( $rCurlHandle, CURLOPT_NOBODY, TRUE );
curl_setopt( $rCurlHandle, CURLOPT_RETURNTRANSFER, TRUE );

$strResponse = curl_exec( $rCurlHandle );
$Connect = curl_errno( $rCurlHandle);
$funktionen->log_schreiben(print_r(curl_getinfo( $rCurlHandle ),1), "1  " , 9 );
curl_close( $rCurlHandle );
if ($Connect != 0) {
  $funktionen->log_schreiben( "Keine Verbindung zur Homematic Zentrale! IP: ".$Homematic_IP, "   ", 4 );
  $HM_Verbindung = false;
}
else {
  $HM_Verbindung = true;
  $funktionen->log_schreiben( "Verbindung zur Homematic Zentrale besteht. IP: ".$Homematic_IP, "   ", 8 );
}
$HomeMaticVar = trim( $HomeMaticVar );
$EinzelVar = explode( ",", $HomeMaticVar );
$funktionen->log_schreiben( var_export( $EinzelVar, 1 ), "   ", 10 );
if ($HM_Verbindung) {

  /*****************************************************************
  //  Wetterdaten     Wetterdaten     Wetterdaten      Wetterdaten
  //  Wetterdaten     Wetterdaten     Wetterdaten      Wetterdaten
  //
  //  Falls der Wetterserver abgefragt wird, dann hier die Daten
  //  aus der INFLUX Datenbank holen.
  *****************************************************************/
  if ($Wetterdaten) {
    // 1. Wetterdaten lesen
    $query = urlencode( "select Wind, Wolkendichte, Temperatur from  aktuellesWetter order by time desc limit 1" );
    $ch = curl_init( 'http://localhost/query?db='.$aktuelleDaten["InfluxDBLokal"].'&q='.$query );
    $i = 1;
    do {
      curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
      curl_setopt( $ch, CURLOPT_TIMEOUT, 20 ); //  timeout in second s
      curl_setopt( $ch, CURLOPT_PORT, 8086 ); //  Die Wetterdaten werden immer auf dem Raspberry gespeichert
      curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
      $result = curl_exec( $ch );
      $rc_info = curl_getinfo( $ch );
      $Ausgabe = json_decode( $result, true );
      if (curl_errno( $ch )) {
        $funktionen->log_schreiben( "Curl Fehler! Wetterdaten nicht von der InfluxDB gelesen! No. ".curl_errno( $ch ), "   ", 5 );
      }
      if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
        $funktionen->log_schreiben( "Wetterdaten von der InfluxDB  gelesen. ", "*  ", 8 );
        break;
      }
      elseif ($rc_info["http_code"] == 401) {
        $funktionen->log_schreiben( "Influx UserID oder Kennwort ist falsch.", "*  ", 5 );
        break;
      }
      elseif (empty($Ausgabe["error"])) {
        $funktionen->log_schreiben( "InfluxDB Fehler -> nochmal versuchen.", "   ", 5 );
        $i++;
        continue;
      }
      $funktionen->log_schreiben( "Wetterdaten nicht von der InfluxDB gelesen! => [ ".$Ausgabe["error"]." ]", "   ", 5 );
      $funktionen->log_schreiben( "InfluxDB  => [ ".$query." ]", "   ", 5 );
      $funktionen->log_schreiben( "Wetterdaten => [ ".print_r( $aktuelleDaten, 1 )." ]", "   ", 9 );
      $funktionen->log_schreiben( "Wetterdaten nicht von der InfluxDB gelesen! info: ".var_export( $rc_info, 1 ), "   ", 9 );
      $i++;
      sleep( 1 );
    } while ($i < 3);
    curl_close( $ch );
    $aktuelleDaten["Wind"] = $Ausgabe["results"][0]["series"][0]["values"][0][1];
    $aktuelleDaten["Wolkendichte"] = $Ausgabe["results"][0]["series"][0]["values"][0][2];
    $aktuelleDaten["AussenTemperatur"] = $Ausgabe["results"][0]["series"][0]["values"][0][3];
    if ($Prognosedaten <> "keine") {
      // 2. Prognosedaten lesen
      // $morgen  = mktime(11, 55, 0, date("m")  , date("d")+1, date("Y"));
      // $zeit1 = gmdate('Y-m-d\TH:i:s\Z',$morgen);
      // "select Prognose_W, Prognose_Wh from  Wetterprognose where time >= '".$zeit1."' AND time <= '".$zeit2."'"
      $query = urlencode( "select Prognose_W, Prognose_Wh from  Wetterprognose order by time desc limit 1" );
      $ch = curl_init( 'http://localhost/query?db='.$aktuelleDaten["InfluxDBLokal"].'&q='.$query );
      $i = 1;
      do {
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 20 ); //  timeout in second s
        curl_setopt( $ch, CURLOPT_PORT, 8086 ); //  Die Wetterdaten werden immer auf dem Raspberry gespeichert
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        $result = curl_exec( $ch );
        $rc_info = curl_getinfo( $ch );
        $Ausgabe = json_decode( $result, true );
        if (curl_errno( $ch )) {
          $funktionen->log_schreiben( "Curl Fehler! Wetterprognose nicht von der InfluxDB gelesen! No. ".curl_errno( $ch ), "   ", 5 );
        }
        if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
          $funktionen->log_schreiben( "Wetterprognose von der InfluxDB  gelesen. ", "*  ", 8 );
          break;
        }
        elseif ($rc_info["http_code"] == 401) {
          $funktionen->log_schreiben( "Influx UserID oder Kennwort ist falsch.", "*  ", 5 );
          break;
        }
        elseif (empty($Ausgabe["error"])) {
          $funktionen->log_schreiben( "InfluxDB Fehler -> nochmal versuchen.", "   ", 5 );
          $i++;
          continue;
        }
        $funktionen->log_schreiben( "Wetterprognose nicht von der InfluxDB gelesen! => [ ".$Ausgabe["error"]." ]", "   ", 5 );
        $funktionen->log_schreiben( "InfluxDB  => [ ".$query." ]", "   ", 5 );
        $funktionen->log_schreiben( "Wetterprognose => [ ".print_r( $aktuelleDaten, 1 )." ]", "   ", 9 );
        $funktionen->log_schreiben( "Wetterprognose nicht von der InfluxDB gelesen! info: ".var_export( $rc_info, 1 ), "   ", 9 );
        $i++;
        sleep( 1 );
      } while ($i < 3);
      curl_close( $ch );
      $aktuelleDaten["Prognose_W"] = $Ausgabe["results"][0]["series"][0]["values"][0][1];
      $aktuelleDaten["Prognose_Wh"] = $Ausgabe["results"][0]["series"][0]["values"][0][2];
    }
  }
  else {
    $aktuelleDaten["Wind"] = 0;
    $aktuelleDaten["Wolkendichte"] = 0;
    $aktuelleDaten["AussenTemperatur"] = 0;
    $aktuelleDaten["Prognose_W"] = 0;
    $aktuelleDaten["Prognose_Wh"] = 0;
  }

  /*************************************************************
  //  Wetterdaten Ende     Wetterdaten Ende     Wetterdaten Ende
  //  Wetterdaten Ende     Wetterdaten Ende     Wetterdaten Ende
  *************************************************************/


  /*************************************************************
  //
  //  User defined Variablen. Siehe HomeMatic Dokument
  //  Das Array "HM_Var" muss in einer _math Datei angelegt
  //  werden.
  *************************************************************/
  if (isset($HM_Var)) {
    $funktionen->log_schreiben( "HM Übertragung mit _math Datei.", "   ", 7 );
    $funktionen->log_schreiben( "HM_Var: ".print_r( $HM_Var, 1 ), "   ", 8 );
    $g = 0;
    foreach($HM_Var as $key=>$wert) {
      $DataString .= "Antwort".$g."=dom.GetObject('".$key."').State('".$wert."')&";
      $EinzelVar[$g] = $key;
      $g++;
    }
  }
  else {

    /*************************************************************
    //  Nur wenn auch wirklich eine Homematic Zentrale zu
    //  erreichen ist, werden die Daten übertragen.
    *************************************************************/
    for ($i = 0; $i < count( $EinzelVar ); $i++) {
      $Parameter = trim( $EinzelVar[$i] );

      $Bezeichnung = $Parameter;
      if ($Regler == "1") {
        switch ($Bezeichnung) {

          case "BatterieEntladestrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batterieentladestrom"].")";
            break;

          case "Batteriespannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriespannung"].")";
            break;

          case "Solarleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarleistung"].")";
            break;

          case "SolarleistungTag":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "BatterieLadestrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batterieladestrom"].")";
            break;

          case "Solarspannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarspannung"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "3") {
        switch ($Bezeichnung) {

          case "BatterieLadestatus":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Ladestatus"].")";
            break;

          case "BatteriestatusText":
            switch ($aktuelleDaten["Ladestatus"]) {

              case 0:
                $VarText = "Keine_Ladung";
                break;

              case 1:
                $VarText = "Erhaltungsladung";
                break;

              case 2:
                $VarText = "Normale_Ladung";
                break;

              case 3:
                $VarText = "Ausgleichsladung";
                break;

              default:
                $VarText = "Unbekannt";
                break;
            }
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State('".$VarText."')";
            break;

          case "Batteriespannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".round( $aktuelleDaten["Batteriespannung"], 2 ).")";
            break;

          case "Solarleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarleistung"].")";
            break;

          case "SolarleistungTag":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "BatterieLadestrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarstrom"].")";
            break;

          case "Solarspannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarspannung"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "4") {
        switch ($Bezeichnung) {

          case "BatterieLadestatus":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Ladestatus"].")";
            break;

          case "BatteriestatusText":
            switch ($aktuelleDaten["Ladestatus"]) {

              case 0:
                $VarText = "Keine_Ladung";
                break;

              case 1:
                $VarText = "Unbeannt";
                break;

              case 2:
                $VarText = "Fehler";
                break;

              case 3:
                $VarText = "Normale_Ladung";
                break;

              case 4:
                $VarText = "Nachladung";
                break;

              case 5:
                $VarText = "Erhaltungsladung";
                break;

              default:
                $VarText = "Unbekannt";
                break;
            }
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State('".$VarText."')";
            break;

          case "Batteriespannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".round( $aktuelleDaten["Batteriespannung"], 2 ).")";
            break;

          case "Solarleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarleistung"].")";
            break;

          case "SolarleistungTag":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "BatterieLadestrom":
            if (isset($aktuelleDaten["Solarstrom"])) {
              $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarstrom"].")";
            }
            else {
              $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batterieladestrom"].")";
            }
            break;

          case "Solarspannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarspannung"].")";
            break;

          case "Temperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Temperatur"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "5" or $Regler == "10" or $Regler == "11" or $Regler == "12") {
        switch ($Bezeichnung) {

          case "Geraetestatus":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Geraetestatus"].")";
            break;

          case "Solarspannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarspannung"].")";
            break;

          case "Solarstrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarstrom"].")";
            break;

          case "Solarleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarleistung"].")";
            break;

          case "acAusgangsspannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Ausgangsspannung"].")";
            break;

          case "acAusgangsstrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Ausgangsstrom"].")";
            break;

          case "acWirkleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung"].")";
            break;

          case "Einspeisung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Einspeisung"].")";
            break;

          case "Verbrauch":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Verbrauch"].")";
            break;

          case "Bezug":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Bezug"].")";
            break;

          case "LadestatusProzent":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Akkustand_SOC"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          case "SolarleistungTag":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "6") {
        switch ($Bezeichnung) {

          case "Batteriespannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriespannung"].")";
            break;

          case "Batteriestrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriestrom"].")";
            break;

          case "SOC":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["SOC"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          case "SolarleistungTag":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "7" or $Regler == "8" or $Regler == "23") {
        switch ($Bezeichnung) {

          case "Batteriespannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriespannung"].")";
            break;

          case "Solarleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarleistung"].")";
            break;

          case "SolarleistungTag":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "Netzspannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Netzspannung"].")";
            break;

          case "Netzfrequenz":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Netzfrequenz"].")";
            break;

          case "acAusgangsspannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Ausgangsspannung"].")";
            break;

          case "acAusgangsfrequenz":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Ausgangsfrequenz"].")";
            break;

          case "acAusgangsstrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Strom"].")";
            break;

          case "acScheinleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Scheinleistung"].")";
            break;

          case "acWirkleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung"].")";
            break;

          case "Batteriekapazitaet":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriekapazitaet"].")";
            break;

          case "Temperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Temperatur"].")";
            break;

          case "BatterieLadestrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarstrom"].")";
            break;

          case "Solarspannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarspannung"].")";
            break;

          case "Betriebsart":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Geraetestatus"].")";
            break;

          case "Relaisstatus":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Powerstatus"].")";
            break;

          case "Status":
            if ($Regler == 23) {
              $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State('".$aktuelleDaten["Status"]."')";
            }
            else {
              $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Status"].")";
            }
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "9") {
        switch ($Bezeichnung) {

          case "WattstundenGesamtHeute":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "Wirkleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Wirkleistung"].")";
            break;

          case "Scheinleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Scheinleistung"].")";
            break;

          case "Eingangsleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Eingangsleistung"].")";
            break;

          case "AC_Spannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Ausgangsspannung_R"].")";
            break;

          case "AC_Frequenz":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Ausgangsfrequenz"].")";
            break;

          case "Batteriespannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriespannung"].")";
            break;

          case "Batteriestrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriestrom"].")";
            break;

          case "Batteriestromrichtung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriestromrichtung"].")";
            break;

          case "Solarleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarleistung"].")";
            break;

          case "Solarspannung1":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarspannung1"].")";
            break;

          case "Solarspannung2":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarspannung2"].")";
            break;

          case "Batteriekapazitaet":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriekapazitaet"].")";
            break;

          case "Temperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Temperatur"].")";
            break;

          case "Betriebsart":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Modus"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "13") {
        switch ($Bezeichnung) {

          case "Strom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Strom"].")";
            break;

          case "Spannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Spannung"].")";
            break;

          case "Fehlercode":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Fehlercode"].")";
            break;

          case "SOC":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["SOC"].")";
            break;

          case "Kapazitaet":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Kapazitaet"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "15") {
        for ($n = 1; $n <= $aktuelleDaten["Packs"]; $n++) {
          switch ($Bezeichnung) {

            case "Pack".$n."_Strom":
              $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Pack".$n."_Strom"].")";
              break 2;

            case "Pack".$n."_Spannung":
              $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Pack".$n."_Spannung"].")";
              break 2;

            case "Pack".$n."_Ah_left":
              $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Pack".$n."_Ah_left"].")";
              break 2;

            default:
              break;
          }
          // $funktionen->log_schreiben("Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter."  ".$Bezeichnung,"   ",5);
        }
        $DataString .= "&";
      }
      elseif ($Regler == "16" or $Regler == "20") {
        switch ($Bezeichnung) {

          case "Einspeisung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Einspeisung"].")";
            break;

          case "Verbrauch":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Verbrauch"].")";
            break;

          case "Bezug":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Bezug"].")";
            break;

          case "Solarleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["DC_Leistung"].")";
            break;

          case "AC_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung"].")";
            break;

          case "AC_Spannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["M1_AC_Spannung"].")";
            break;

          case "Solarspannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["DC_Spannung"].")";
            break;

          case "Solarstrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["DC_Strom"].")";
            break;

          case "Temperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Temperatur"].")";
            break;

          case "LeistungTag":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "17" or $Regler == "21") {
        switch ($Bezeichnung) {

          case "Ausgangslast":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Ausgangslast"].")";
            break;

          case "Verbrauch":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Verbrauch"].")";
            break;

          case "Einspeisung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Einspeisung"].")";
            break;

          case "SOC":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["SOC"].")";
            break;

          case "Batteriespannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriespannung"].")";
            break;

          case "Batteriestrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriestrom"].")";
            break;

          case "Solarspannung1":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["PV1_Spannung"].")";
            break;

          case "Solarspannung2":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["PV2_Spannung"].")";
            break;

          case "Solarspannung3":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["PV3_Spannung"].")";
            break;

          case "AC_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung"].")";
            break;

          case "AC_Spannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Spannung_R"].")";
            break;

          case "Solarleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["PV_Leistung"].")";
            break;

          case "LeistungTag":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "18") {
        switch ($Bezeichnung) {

          case "Wallbox":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung_Wallbox"].")";
            break;

          case "Verbrauch":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Verbrauch"].")";
            break;

          case "Bezug":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Bezug"].")";
            break;

          case "Solarleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["PV_Leistung"].")";
            break;

          case "Batterieladung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batterie_Leistung"].")";
            break;

          case "String1_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["DC_String1_Leistung"].")";
            break;

          case "String2_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["DC_String2_Leistung"].")";
            break;

          case "String3_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["DC_String3_Leistung"].")";
            break;

          case "LeistungTag":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "SOC":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["SOC"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "25") {
        switch ($Bezeichnung) {

          case "Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Ausgangsleistung"].")";
            break;

          case "Verbrauch":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Verbrauch"].")";
            break;

          case "Einspeisung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Einspeisung"].")";
            break;

          case "SOC":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["SOC"].")";
            break;

          case "Batteriespannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriespannung"].")";
            break;

          case "Batterieladung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batterieladung"].")";
            break;

          case "Netzladung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batterie_Netzladung"].")";
            break;

          case "PV_Ladung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batterie_PV_Ladung"].")";
            break;

          case "Batterieentladung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batterieentladung"].")";
            break;

          case "Einspeisung_Bezug":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Einspeisung_Bezug"].")";
            break;

          case "Bezug":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Bezug"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "26" or $Regler == "77") {
        switch ($Bezeichnung) {

          case "Batteriespannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriespannung"].")";
            break;

          case "Solarleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarleistung"].")";
            break;

          case "SolarleistungTag":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "Netzspannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Netzspannung"].")";
            break;

          case "Netzfrequenz":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Netzfrequenz"].")";
            break;

          case "acAusgangsspannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Ausgangsspannung"].")";
            break;

          case "acAusgangsfrequenz":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Ausgangsfrequenz"].")";
            break;

          case "acWirkleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Wirkleistung"].")";
            break;

          case "Batteriekapazitaet":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriekapazitaet"].")";
            break;

          case "Temperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Temperatur"].")";
            break;

          case "BatterieLadestrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarstrom"].")";
            break;

          case "BatterieEntladestrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batterieentladestrom"].")";
            break;

          case "DeviceStatus":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["DeviceStatus"].")";
            break;

          case "Solarspannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarspannung"].")";
            break;

          case "Status":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Status"].")";
            break;

          case "Fehlermeldung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Fehlermeldung"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "27") {
        switch ($Bezeichnung) {

          case "Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung"].")";
            break;

          case "Einspeisung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung_Einspeisung"].")";
            break;

          case "Temperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Temperatur"].")";
            break;

          case "DC_Leistung1":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["DC_Leistung1"].")";
            break;

          case "DC_Leistung2":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["DC_Leistung2"].")";
            break;

          case "Bezug":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung_Bezug"].")";
            break;

          case "Geraetestatus":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Geraetestatus"].")";
            break;

          case "LeistungTag":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "AC_Spannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Spannung_R"].")";
            break;

          case "SOC":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["SOC"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "29") {
        switch ($Bezeichnung) {

          case "aktive_Karte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["uby"].")";
            break;

          case "Gesamtleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["eto"].")";
            break;

          case "Spannung_R":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["nrg"][0].")";
            break;

          case "Spannung_S":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["nrg"][1].")";
            break;

          case "Spannung_T":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["nrg"][2].")";
            break;

          case "Strom_R":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["nrg"][4].")";
            break;

          case "Strom_S":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["nrg"][5].")";
            break;

          case "Strom_T":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["nrg"][6].")";
            break;

          case "Leistung_R":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["nrg"][7].")";
            break;

          case "Leistung_S":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["nrg"][8].")";
            break;

          case "Leistung_T":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["nrg"][9].")";
            break;

          case "Leistung_gesamt":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["nrg"][10].")";
            break;

          case "Karte1_Wh":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["eca"].")";
            break;

          case "Karte2_Wh":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["ecr"].")";
            break;

          case "Karte3_Wh":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["ecd"].")";
            break;

          case "Karte4_Wh":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["ec4"].")";
            break;

          case "Karte5_Wh":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["ec5"].")";
            break;

          case "Status":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["car"].")";
            break;

          case "Ladestrom_max":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["amp"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "31") {
        switch ($Bezeichnung) {

          case "Einspeisung_R":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wh_EinspeisungGesamt_R"].")";
            break;

          case "Einspeisung_S":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wh_EinspeisungGesamt_S"].")";
            break;

          case "Einspeisung_T":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wh_EinspeisungGesamt_T"].")";
            break;

          case "Verbrauch_R":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wh_VerbrauchGesamt_R"].")";
            break;

          case "Verbrauch_S":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wh_VerbrauchGesamt_S"].")";
            break;

          case "Verbrauch_T":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wh_VerbrauchGesamt_T"].")";
            break;

          case "AC_Spannung_R":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Spannung_R"].")";
            break;

          case "AC_Spannung_S":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Spannung_S"].")";
            break;

          case "AC_Spannung_T":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Spannung_T"].")";
            break;

          case "AC_Leistung_R":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wirkleistung_R"].")";
            break;

          case "AC_Leistung_S":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wirkleistung_S"].")";
            break;

          case "AC_Leistung_T":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wirkleistung_T"].")";
            break;

          case "AC_Strom_R":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Strom_R"].")";
            break;

          case "AC_Strom_S":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Strom_S"].")";
            break;

          case "AC_Strom_T":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Strom_T"].")";
            break;

          case "Wirkleistung_Gesat":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["LeistungGesamt"].")";
            break;

          case "Relaisstatus":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Relaisstatus"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "32") {
        switch ($Bezeichnung) {

          case "AC_Ausgangsspannung_R":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Ausgangsspannung_R"].")";
            break;

          case "AC_Ausgangsspannung_S":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Ausgangsspannung_S"].")";
            break;

          case "AC_Ausgangsspannung_T":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Ausgangsspannung_T"].")";
            break;

          case "AC_Ausgangsstrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Ausgangsstrom"].")";
            break;

          case "AC_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung"].")";
            break;

          case "AC_Frequenz":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Frequenz"].")";
            break;

          case "AC_Leistungsfaktor":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistungsfaktor"].")";
            break;

          case "WattstundenGesamtHeute":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "Solarspannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarspannung"].")";
            break;

          case "Solarleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarleistung"].")";
            break;

          case "Solarstrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarstrom"].")";
            break;

          case "Temperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Temperatur"].")";
            break;

          case "Status":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Status"].")";
            break;

          case "Warnungen":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Warnungen"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "34" or $Regler == "49" or $Regler == "50" or $Regler == "51" or $Regler == "53" or $Regler == "54") {
        switch ($Bezeichnung) {

          case "AC_Spannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Spannung"].")";
            break;

          case "AC_Strom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Strom"].")";
            break;

          case "AC_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung"].")";
            break;

          case "Wh_Einspeisung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wh_Einspeisung"].")";
            break;

          case "Wh_Bezug":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wh_Bezug"].")";
            break;

          case "Einspeisung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Einspeisung"].")";
            break;

          case "Bezug":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Bezug"].")";
            break;

          case "AC_Leistung_R":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung_R"].")";
            break;

          case "AC_Leistung_S":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung_S"].")";
            break;

          case "AC_Leistung_T":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung_T"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "38") {
        switch ($Bezeichnung) {

          case "Temperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Temperatur"].")";
            break;

          case "PV_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["PV_Leistung"].")";
            break;

          case "Batteriespannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriespannung"].")";
            break;

          case "Batterieleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batterieleistung"].")";
            break;

          case "Batteriestrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriestrom"].")";
            break;

          case "Batteriemodus":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriemodus"].")";
            break;

          case "AC_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung"].")";
            break;

          case "Einspeisung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["NetzeinspeisungGesamt"].")";
            break;

          case "Bezug":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["NetzbezugGesamt"].")";
            break;

          case "BatterieSOC":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["SOC"].")";
            break;

          case "Wh_GesamtHeute":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "40" or $Regler == "42") {
        switch ($Bezeichnung) {

          case "Netzleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Netzleistung"].")";
            break;

          case "AC_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Ausgangsleistung"].")";
            break;

          case "Batteriespannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriespannung"].")";
            break;

          case "Batterieleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batterieleistung"].")";
            break;

          case "Batteriestrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batterie_Strom"].")";
            break;

          case "BatterieRelais":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Bat_Relay"].")";
            break;

          case "PV_Spannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarspannung"].")";
            break;

          case "Ausgangslast":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Ausgangslast"].")";
            break;

          case "MPPTStatus":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["MPPT_Status"].")";
            break;

          case "Geraetestatus":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WR_Status"].")";
            break;

          case "Wh_GesamtHeute":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "43") {
        switch ($Bezeichnung) {

          case "DC_Strom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Gesamtstrom"].")";
            break;

          case "AC_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung"].")";
            break;

          case "AC_Frequenz":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Frequenz"].")";
            break;

          case "PV_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["PV_Leistung"].")";
            break;

          case "WattstundenGesamtHeute":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "BezugGesamt":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["NetzbezugGesamt"].")";
            break;

          case "EinspeisungGesamt":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["EinspeisungGesamt"].")";
            break;

          case "PVLeistungGesamt":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["PV_Gesamtleistung"].")";
            break;

          case "VerbrauchGesamt":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["VerbrauchGesamt"].")";
            break;

          case "Batterie_SOC":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Bat_SOC"].")";
            break;

          case "Einspeisung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Einspeisung"].")";
            break;

          case "Bezug":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Bezug"].")";
            break;

          case "Hausverbrauch":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Hausverbrauch"].")";
            break;

          case "Status":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Status"].")";
            break;

          case "Batterie_Spannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Bat_Spannung"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "45") {
        switch ($Bezeichnung) {

          case "AC_Ausgangslast":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Ausgangslast"].")";
            break;

          case "AC_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Ausgangsleistung"].")";
            break;

          case "Batteriespannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batteriespannung"].")";
            break;

          case "Batterie_SOC":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["SOC"].")";
            break;

          case "Batteriestrom":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Batterie_Strom"].")";
            break;

          case "PV_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["PV_Leistung"].")";
            break;

          case "PV_Spannung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Solarspannung"].")";
            break;

          case "ReglerMode":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Regler_Mode"].")";
            break;

          case "Geraetstatus":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Modus"].")";
            break;

          case "Wh_GesamtHeute":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "56" or $Regler == "62" or $Regler == "65" or $Regler == "70") {
        switch ($Bezeichnung) {

          case "AC_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung"].")";
            break;

          case "AC_Frequenz":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Frequenz"].")";
            break;

          case "Batterie_SOC":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["SOC"].")";
            break;

          case "PV_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["PV_Leistung"].")";
            break;

          case "Einspeisung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Einspeisung"].")";
            break;

          case "Bezug":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Bezug"].")";
            break;

          case "Hausverbrauch":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Hausverbrauch"].")";
            break;

          case "WattstundenGesamtHeute":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "Status":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["DeviceStatus"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      elseif ($Regler == "52" or $Regler == "64") {
        // Goodwe Wechselrichter
        switch ($Bezeichnung) {

          case "AC_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AC_Leistung"].")";
            break;

          case "AC_Frequenz":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Netzfrequenz"].")";
            break;

          case "Batterie_SOC":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["SOC"].")";
            break;

          case "PV_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["PV_Leistung"].")";
            break;

          case "Einspeisung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Einspeisung_Bezug"].")";
            break;

          case "Bezug":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Einspeisung_Bezug"].")";
            break;

          case "Hausverbrauch":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Verbrauch"].")";
            break;

          case "WattstundenGesamtHeute":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "Status":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["BMS_Status"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
      else { // Default Variablen
        switch ($Bezeichnung) {

          case "Solarleistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["HM_Solarleistung"].")";
            break;

          case "AC_Leistung":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["HM_AC_Leistung"].")";
            break;

          case "Wh_GesamtHeute":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["WattstundenGesamtHeute"].")";
            break;

          case "Temperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["HM_Temperatur"].")";
            break;

          case "Wind":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wind"].")";
            break;

          case "Wolkendichte":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["Wolkendichte"].")";
            break;

          case "AussenTemperatur":
            $DataString .= "Antwort".$i."=dom.GetObject('".$Parameter."').State(".$aktuelleDaten["AussenTemperatur"].")";
            break;

          default:
            $funktionen->log_schreiben( "Es gibt Probleme mit den HomeMatic Variablen! Bitte prüfen ob diese Variable auch zu Ihrem Regler gehört: ".$Parameter, "   ", 5 );
            break;
        }
        $DataString .= "&";
      }
    }
  }

  /*************************************************************************
  //
  //  HomeMatic      HomeMatic      HomeMatic      HomeMatic      HomeMatic
  //  Auslesen       Auslesen       Auslesen       Auslesen       Auslesen
  //
  *************************************************************************/
  if ($HM_auslesen) {
    for ($i = 0; $i < count( $HM ); $i++) {
      if (isset($HM[$i]["Datenpunkt"])) {
        $DataString .= $HM[$i]["Variable"]."=dom.GetObject('".$HM[$i]["Interface"].".".$HM[$i]["Seriennummer"].".".$HM[$i]["Datenpunkt"]."').Value()&";
      }
      elseif (isset($HM[$i]["Systemvariable"])) {
        $DataString .= $HM[$i]["Variable"]."=dom.GetObject('".$HM[$i]["Systemvariable"]."').Value()&";
      }
    }
  }
  $DataString = substr( $DataString, 0, - 1 );
  $funktionen->log_schreiben( "DatenString: ".$DataString, "   ", 9 );

  $ch = curl_init( );
  curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5 );
  curl_setopt( $ch, CURLOPT_TIMEOUT, 20 );
  curl_setopt( $ch, CURLOPT_URL, "http://".$Homematic_IP."/rega.exe?".$DataString );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
  curl_setopt( $ch, CURLOPT_PORT, 8181 );
  //  In $result wird ein XML Dokument zurück gegeben!
  //  Dort steht drin ob die Zentrale den Wert übernommen hat.
  $result = curl_exec( $ch );
  $rc_info = curl_getinfo( $ch );
  if ($rc_info["http_code"] == 401) {
    $funktionen->log_schreiben( "Fehler! Kein Zugriff. Ist die Firewall der HomeMatic richtig eingestellt? ", "   ", 5 );
  }
  elseif ($rc_info["http_code"] != 200) {
    $funktionen->log_schreiben( "Daten nicht gesendet! info: ".var_export( $rc_info, 1 ), "   ", 5 );
  }
  else {
    $funktionen->log_schreiben( "http://".$Homematic_IP."/rega.exe?".$DataString, "   ", 9 );
    $funktionen->log_schreiben( "Daten zur HomeMatic Zentrale gesendet. \n Antwort: ".$result, "   ", 9 );
  }
  if ($result) {
    $Ergebnis = new SimpleXMLElement( $result );
    if ($Ergebnis->Antwort0 != "true" and isset($EinzelVar[0])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[0]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort1 != "true" and isset($EinzelVar[1])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[1]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort2 != "true" and isset($EinzelVar[2])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[2]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort3 != "true" and isset($EinzelVar[3])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[3]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort4 != "true" and isset($EinzelVar[4])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[4]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort5 != "true" and isset($EinzelVar[5])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[5]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort6 != "true" and isset($EinzelVar[6])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[6]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort7 != "true" and isset($EinzelVar[7])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[7]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort8 != "true" and isset($EinzelVar[8])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[8]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort9 != "true" and isset($EinzelVar[9])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[9]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort10 != "true" and isset($EinzelVar[10])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[10]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort11 != "true" and isset($EinzelVar[11])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[11]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort12 != "true" and isset($EinzelVar[12])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[12]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort13 != "true" and isset($EinzelVar[13])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[13]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort14 != "true" and isset($EinzelVar[14])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[14]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort15 != "true" and isset($EinzelVar[15])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[15]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort16 != "true" and isset($EinzelVar[16])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[16]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort17 != "true" and isset($EinzelVar[17])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[17]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort18 != "true" and isset($EinzelVar[18])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[18]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort19 != "true" and isset($EinzelVar[19])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[19]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
    elseif ($Ergebnis->Antwort20 != "true" and isset($EinzelVar[20])) {
      $funktionen->log_schreiben( "Die Systemvariable '".$EinzelVar[20]."' ist in der HomeMatic eventuell nicht vorhanden", "   ", 5 );
    }
  }
  else {
    $funktionen->log_schreiben( "Verbindung zur HomeMatic war nicht erfolgreich! Daten nicht gesendet", "   ", 5 );
  }
  $funktionen->log_schreiben( "Daten zur HomeMatic gesendet. ", "   ", 5 );

  /*************************************************************************
  //
  //  Wenn der Status von Geräten aus der HomeMatic ausgelesen wurden,
  //  dann das Ergebnis in die INFLUX Datenbank speichern
  //
  *************************************************************************/
  if ($HM_auslesen) {
    $funktionen->log_schreiben( "HomeMatic Gerätestatus in die InfluxDB speichern.", "   ", 5 );
    $query = "Homematic ";
    for ($i = 0; $i < count( $HM ); $i++) {
      if ($i > 0) {
        $query .= ",";
      }
      $funktionen->log_schreiben( "Ergebnis: ".trim( $Ergebnis->{
        $HM[$i]["Variable"]
      }
      ), "   ", 9 );
      if (trim( $Ergebnis->{ $HM[$i]["Variable"] } ) == 'true') {
        // Gerät ist eingeschaltet
        $query .= $HM[$i]["Variable"]."=1";
      }
      elseif (trim( $Ergebnis->{ $HM[$i]["Variable"] } ) == 'false') {
        // Gerät ist ausgeschaltet
        $query .= $HM[$i]["Variable"]."=0";
      }
      elseif (trim( $Ergebnis->{ $HM[$i]["Variable"] } ) == 'null') {
        // Gerät ist ausgeschaltet
        $funktionen->log_schreiben( "Variable liefert falsche Werte!", "   ", 5 );
      }
      else {
        $query .= $HM[$i]["Variable"]."=".trim( $Ergebnis->{
          $HM[$i]["Variable"]
        }
        );
      }
    }
    if ($InfluxDB_remote) {
      $ch = curl_init( 'http://'.$aktuelleDaten["InfluxAdresse"].'/write?db='.$aktuelleDaten["InfluxDBName"].'&precision=s' );
      $k = 1;
      if (!$InfluxDB_local) {
        $k = 2;
      }
    }
    else {
      $ch = curl_init( 'http://localhost/write?db='.$aktuelleDaten["InfluxDBLokal"].'&precision=s' );
      $aktuelleDaten["InfluxUser"] = "";
      $aktuelleDaten["InfluxPort"] = "8086";
      $k = 2;
    }
    do {
      $i = 1;
      if ($k == 1) {
        $funktionen->log_schreiben( "InfluxDB  => [ speichern DB lokal ] ".$query, "   ", 8 );
      }
      if ($k == 2) {
        $funktionen->log_schreiben( "InfluxDB  => [ speichern DB remote / lokal ] ".$query, "   ", 8 );
      }
      do {
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 25 ); //timeout in second s
        curl_setopt( $ch, CURLOPT_PORT, $aktuelleDaten["InfluxPort"] );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $query );
        if (!empty($aktuelleDaten["InfluxUser"]) and !empty($aktuelleDaten["InfluxPassword"])) {
          curl_setopt( $ch, CURLOPT_USERPWD, $aktuelleDaten["InfluxUser"].":".$aktuelleDaten["InfluxPassword"] );
        }
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        $result = curl_exec( $ch );
        $rc_info = curl_getinfo( $ch );
        $Ausgabe = json_decode( $result, true );
        if (curl_errno( $ch )) {
          $funktionen->log_schreiben( "Curl Fehler! Daten nicht zur entfernten InfluxDB gesendet! Curl ErrNo. ".curl_errno( $ch ), "   ", 5 );
        }
        if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
          $funktionen->log_schreiben( "Daten zur InfluxDB  gesendet. ", "*  ", 9 );
          break;
        }
        elseif ($rc_info["http_code"] == 401) {
          $funktionen->log_schreiben( "Influx UserID oder Kennwort ist falsch.", "*  ", 5 );
          break;
        }
        elseif (empty($Ausgabe["error"])) {
          $funktionen->log_schreiben( "InfluxDB Fehler -> nochmal versuchen.", "   ", 5 );
          $i++;
          continue;
        }
        $funktionen->log_schreiben( "InfluxDB  => [ ".$query." ]", "   ", 9 );
        $funktionen->log_schreiben( "Daten => [ ".print_r( $aktuelleDaten, 1 )." ]", "   ", 9 );
        $funktionen->log_schreiben( "Daten nicht zur InfluxDB gesendet! info: ".var_export( $rc_info, 1 ), "   ", 5 );
        $i++;
        sleep( 1 );
      } while ($i < 3);
      // Jetzt noch alles in die lokale Datenbank speichern
      $ch = curl_init( 'http://localhost/write?db='.$aktuelleDaten["InfluxDBLokal"].'&precision=s' );
      $aktuelleDaten["InfluxUser"] = "";
      $aktuelleDaten["InfluxPort"] = "8086";
      $k++;
    } while ($k < 3);
    curl_close( $ch );
    unset($ch);
  }
  $funktionen->log_schreiben( print_r( $Ergebnis, 1 ), "   ", 10 );
}
$Tracelevel = $Tracelevel_original;
?>
