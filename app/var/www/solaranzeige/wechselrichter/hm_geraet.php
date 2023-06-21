<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016 - 2022] [Ulrich Kunz]
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
//  Es dient dem Auslesen von Geräten, angeschlossen an die HomeMatic
//  Das Gerät wird wie ein Wechselrichter konfiguriert. Bitte in der
//  user.config.php die IP Adresse der HomeMatic und den Port = 80 eintragen.
//
//  Die Geräte, die ausgelesen werden sollen, werden in der user.config.php
//  eingetragen.
//
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************
$aktuelleDaten = array();
$HM_Geraetetyp = array();
$HM_Seriennummer = array();

$HM_Geraetetyp[1] = "HM-CC-RT-DN";     // Heizungsthermostat
$HM_Seriennummer[1] = "OEQ2419985";    // Wohnzimmer

$HM_Geraetetyp[2] = "HmIP-eTRV-B";     // Heizungsthermostat
$HM_Seriennummer[2] = "00201D89A8A446";// Badezimmer

$HM_Geraetetyp[3] = "HmIP-STHD";       // Wandthermostat
$HM_Seriennummer[3] = "000E9BE9967967";// Badezimmer

$HM_Geraetetyp[4] = "HM-CC-RT-DN";     // Heizungsthermostat
$HM_Seriennummer[4] = "OEQ2421488";    // Küche

****************************************************************************/
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$aktuelleDaten = array();
$Device = "HM"; // HM = HomeMatic
$Start = time( ); // Timestamp festhalten
$Version = "";
Log::write( "-------------   Start  hm_geraet.php    -------------------------- ", "|--", 6 );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten["WattstundenGesamtHeute"] = 0;
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
Log::write( "HomeMatic: ".$WR_IP." Port: ".$WR_Port." GeräteID: ".$WR_Adresse, "   ", 7 );


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

/************************************************************
//  Prüfen ob dir Homematic Zentrale erreichbar ist.
//
************************************************************/
$rCurlHandle = curl_init( "http://".$WR_IP );
curl_setopt( $rCurlHandle, CURLOPT_CONNECTTIMEOUT, 10 );
curl_setopt( $rCurlHandle, CURLOPT_HEADER, TRUE );
curl_setopt( $rCurlHandle, CURLOPT_NOBODY, TRUE );
curl_setopt( $rCurlHandle, CURLOPT_RETURNTRANSFER, TRUE );
$strResponse = curl_exec( $rCurlHandle );
$Connect = curl_errno( $rCurlHandle );
Log::write( print_r( curl_getinfo( $rCurlHandle ), 1 ), "1  ", 9 );
curl_close( $rCurlHandle );
if ($Connect == 0) {
  //  Verbindung ist OK
  $HM_Verbindung = true;
  Log::write( "Verbindung zur Homematic Zentrale besteht. IP: ".$WR_IP, "   ", 8 );
}
else {
  Log::write( "Keine Verbindung zur Homematic Zentrale! IP: ".$WR_IP." Fehlernummer: [ ".$Connect." ]", "   ", 4 );
  $HM_Verbindung = false;
  goto Ausgang;
}
$i = 1;
error_reporting( E_ALL & ~ E_NOTICE & ~ E_DEPRECATED );
do {
  Log::write( "Die Daten werden ausgelesen...", "+  ", 7 );

  /****************************************************************************
  //  Ab hier wird die HomeMatic ausgelesen.
  //  Es können mehrere Geräte hintereinander ausgelesen werden.
  //  Das Auslesen aller Geräte darf nicht länger als 9 Sekunden dauern.
  ****************************************************************************/
  if ($HM_Verbindung) {
    for ($s = 1; $s <= count( $HM_Seriennummer ); $s++) {

      /************************************************************
      //  Geräte auslesen.
      //
      ************************************************************/
      $rCurlHandle1 = curl_init( "http://".$WR_IP."/config/xmlapi/devicelist.cgi" );
      curl_setopt( $rCurlHandle1, CURLOPT_CUSTOMREQUEST, "GET" );
      curl_setopt( $rCurlHandle1, CURLOPT_TIMEOUT, 20 );
      curl_setopt( $rCurlHandle1, CURLOPT_PORT, 80 );
      curl_setopt( $rCurlHandle1, CURLOPT_RETURNTRANSFER, TRUE );
      $strResponse = curl_exec( $rCurlHandle1 );
      $rc_info = curl_getinfo( $rCurlHandle1 );
      if (curl_errno( $rCurlHandle1 )) {
        Log::write( "Curl Fehler! HomeMatic wurde nicht gelesen! No. ".curl_errno( $ch ), "   ", 5 );
      }
      if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
        Log::write( "HomeMatic Daten gelesen. ", "*  ", 8 );
      }
      Log::write( "Gerät ".$s." => ".$HM_Seriennummer[$s], "*  ", 8 );
      $xml = XMLReader::xml( $strResponse );
      while ($xml->name !== 'deviceList') {
        $xml->read( );
      }
      $dom = $xml->expand( new DOMDocument( ));
      $doc = (array) simplexml_import_dom( $dom );
      for ($i = 0; $i < count( $doc["device"] ); $i++) {
        if ((string) $doc["device"][$i]->attributes( ) ["device_type"] == $HM_Geraetetyp[$s]) {
          if ((string) $doc["device"][$i]->attributes( ) ["address"] == $HM_Seriennummer[$s]) {
            $aktuelleDaten[$HM_Seriennummer[$s]]["Device_ID"] = (string) $doc["device"][$i]->attributes( ) ["ise_id"];
            $aktuelleDaten[$HM_Seriennummer[$s]]["Seriennummer"] = (string) $doc["device"][$i]->attributes( ) ["address"];
            $aktuelleDaten[$HM_Seriennummer[$s]]["Typ"] = (string) $doc["device"][$i]->attributes( ) ["device_type"];
            $aktuelleDaten[$HM_Seriennummer[$s]]["Bezeichnung"] = (string) $doc["device"][$i]->attributes( ) ["name"];
            if ((string) $doc["device"][$i]->attributes( ) ["address"] == $HM_Seriennummer[$s]) {
              break;
            }
          }
        }
      }
      curl_close( $rCurlHandle1 );
      unset($xml);
      unset($dom);
      unset($doc);
      $aktuelleDaten["Measurement".$s] = $aktuelleDaten[$HM_Seriennummer[$s]]["Seriennummer"];
      $rCurlHandle2 = curl_init( "http://".$WR_IP."/config/xmlapi/state.cgi?device_id=".$aktuelleDaten[$HM_Seriennummer[$s]]["Device_ID"] );
      curl_setopt( $rCurlHandle2, CURLOPT_CUSTOMREQUEST, "GET" );
      curl_setopt( $rCurlHandle2, CURLOPT_TIMEOUT, 20 );
      curl_setopt( $rCurlHandle2, CURLOPT_PORT, 80 );
      curl_setopt( $rCurlHandle2, CURLOPT_RETURNTRANSFER, TRUE );
      $strResponse = curl_exec( $rCurlHandle2 );
      $rc_info = curl_getinfo( $rCurlHandle2 );
      if (curl_errno( $rCurlHandle2 )) {
        Log::write( "Curl Fehler! HomeMatic konnte nicht gelesen werden! No. ".curl_errno( $ch ), "   ", 5 );
      }
      if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
        Log::write( "HomeMatic Daten gelesen. ", "*  ", 8 );
      }
      $xml = XMLReader::xml( $strResponse );
      while ($xml->name !== 'state') {
        $xml->read( );
      }
      $dom = $xml->expand( new DOMDocument( ));
      $doc = (array) simplexml_import_dom( $dom );
      Log::write( print_r( (array) $doc["device"], 1 ), "   ", 8 );
      for ($i = 0; $i < count( $doc["device"] ); $i++) {
        for ($k = 0; $k < 30; $k++) {
          Log::write( $i." ".$k." ".(string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"], "   ", 8 );
          if (strtoupper( substr( $HM_Geraetetyp[$s], 0, 8 )) == "HMIP-STH") {
            // Thermostat
            Log::write( $i." ".$k." ".(string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"], "   ", 8 );
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "ACTUAL_TEMPERATURE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Temperatur"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Temperatur_Unit"] = "°C";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "WINDOW_STATE") {
              // 0 = geschlossen  10 = offen
              $aktuelleDaten["HM_Seriennummer".$s]["Fenster_offen"] = (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] * 10;
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "OPERATING_VOLTAGE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung_Unit"] = "V";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "HUMIDITY") {
              $aktuelleDaten["HM_Seriennummer".$s]["Luftfeuchte"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Luftfeuchte_Unit"] = "%";
            }

          }
          elseif (strtoupper( substr( $HM_Geraetetyp[$s], 0, 8 )) == "HMIP-WTH") {
            // Einstellbares Raum Thermostat
            Log::write( $i." ".$k." ".(string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"], "   ", 8 );
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "ACTUAL_TEMPERATURE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Temperatur"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Temperatur_Unit"] = "°C";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "WINDOW_STATE") {
              // 0 = geschlossen  10 = offen
              $aktuelleDaten["HM_Seriennummer".$s]["Fenster_offen"] = (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] * 10;
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "OPERATING_VOLTAGE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung_Unit"] = "V";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "HUMIDITY") {
              $aktuelleDaten["HM_Seriennummer".$s]["Luftfeuchte"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Luftfeuchte_Unit"] = "%";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "HEATING_COOLING") {
              $aktuelleDaten["HM_Seriennummer".$s]["Heizen_Kuehlen"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "PARTY_MODE") {
              if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] == "true") {
                $aktuelleDaten["HM_Seriennummer".$s]["PartyMode"] = 1;
              }
              else {
                $aktuelleDaten["HM_Seriennummer".$s]["PartyMode"] = 0;
              }
            }
          }
          elseif (strtoupper( substr( $HM_Geraetetyp[$s], 0, 13 )) == "HMIP-STE2-PCB") {
            // 2 x Thermometer
            Log::write( $i." ".$k." ".(string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"], "   ", 8 );
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "ACTUAL_TEMPERATURE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Temperatur".$i] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Temperatur".$i."_Unit"] = "°C";
            }
          }
          elseif (substr( $HM_Geraetetyp[$s], 0, 8 ) == "HmIP-DLD") {
            // Schließanlage
            Log::write( $i." ".$k." ".(string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"], "   ", 8 );
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "LOCK_STATE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Status"] = (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"];
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "OPERATING_VOLTAGE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung_Unit"] = "V";
            }
          }
          elseif (strtoupper(substr( $HM_Geraetetyp[$s], 0, 9 )) == "HMIP-ETRV" or substr( $HM_Geraetetyp[$s], 0, 11) == "HM-CC-RT-DN") {
            // HomeMatic Heizungsregler
            Log::write( $i." ".$k." ".(string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"], "   ", 8 );
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "OPERATING_VOLTAGE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung_Unit"] = "V";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "WINDOW_STATE") {
              // 0 = geschlossen  10 = offen
              $aktuelleDaten["HM_Seriennummer".$s]["Fenster_offen"] = (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] * 10;
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "ACTUAL_TEMPERATURE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Temperatur"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Temperatur_Unit"] = "°C";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "WINDOW_STATE") {
              // 0 = geschlossen  10 = offen
              $aktuelleDaten["HM_Seriennummer".$s]["Fenster_offen"] = (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] * 10;
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "VALVE_STATE" and substr( $HM_Geraetetyp[$s], 0, 11) == "HM-CC-RT-DN") {
              $aktuelleDaten["HM_Seriennummer".$s]["Ventil-Oeffnungsgrad"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 0 );
              $aktuelleDaten["HM_Seriennummer".$s]["Ventil_Unit"] = "%";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "BATTERY_STATE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung_Unit"] = "V";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "LEVEL" and strtoupper(substr( $HM_Geraetetyp[$s], 0, 9 )) == "HMIP-ETRV") {
              $aktuelleDaten["HM_Seriennummer".$s]["Ventil-Oeffnungsgrad"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] * 100, 0 );
              $aktuelleDaten["HM_Seriennummer".$s]["Ventil_Unit"] = "%";
            }
          }
          elseif (strtoupper(substr( $HM_Geraetetyp[$s], 0, 8 )) == "HMIP-SLO") {
            // HomeMatic Sonnensensor
            Log::write( $i." ".$k." ".(string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"], "   ", 8 );
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "OPERATING_VOLTAGE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung_Unit"] = "V";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "CURRENT_ILLUMINATION") {
              $aktuelleDaten["HM_Seriennummer".$s]["Helligkeit_aktuell"] = (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"];
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "AVERAGE_ILLUMINATION") {
              $aktuelleDaten["HM_Seriennummer".$s]["Helligkeit_durchschnitt"] = (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"];
            }
          }
          elseif (strtoupper( substr( $HM_Geraetetyp[$s], 0, 9 )) == "HMIP-SWDO" or substr( $HM_Geraetetyp[$s], 0, 10 ) == "HM-Sec-SCo") {
            // HomeMatic Türkontakte
            Log::write( $i." ".$k." ".(string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"], "   ", 8 );
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "STATE") {
              if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] == "true") {
                $aktuelleDaten["HM_Seriennummer".$s]["Kontakt"] = 1;
              }
              else {
                $aktuelleDaten["HM_Seriennummer".$s]["Kontakt"] = 0;
              }
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "LOWBAT") {
              if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] == "true") {
                $aktuelleDaten["HM_Seriennummer".$s]["Batterie_leer"] = 1;
              }
              else {
                $aktuelleDaten["HM_Seriennummer".$s]["Batterie_leer"] = 0;
              }
            }
          }
          elseif (strtoupper( substr( $HM_Geraetetyp[$s], 0, 8 )) == "HMIP-PSM" or strtoupper(substr($HM_Geraetetyp[$s], 0, 8)) == "HMIP-FSM") {
            //  Steckdose mit Messfunktion / Schalt-Mess-Aktor
            Log::write( $i." ".$k." ".(string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"], "   ", 8 );
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "POWER") {
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Leistung"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Leistung_Unit"] = "W";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "VOLTAGE") {
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Spannung"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Spannung_Unit"] = "V";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "CURRENT") {
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Strom"] = (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] / 1000;
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Strom_Unit"] = "A";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "FREQUENCY") {
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Frequenz"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Frequenz_Unit"] = "Hz";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "ENERGY_COUNTER") {
              $aktuelleDaten["HM_Seriennummer".$s]["WattstundenGesamt"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 3 );
              $aktuelleDaten["HM_Seriennummer".$s]["WattstundenGesamt_Unit"] = "Wh";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "STATE") {
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Status"] = '"'.(string) $doc["device"][0]->channel[3]->datapoint[$k]["value"].'"';
            }
          }
          elseif (strtoupper(substr( $HM_Geraetetyp[$s], 0, 7 )) == "HMIP-PS") {
            // Steckdose
            Log::write( $i." ".$k." ".(string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"], "   ", 8 );
            if ((string) $doc["device"][0]->channel[3]->datapoint[$k]["type"] == "STATE") {
              Log::write( print_r( (array) $doc["device"][0]->channel[3]->datapoint[$k], 1 ), "   ", 8 );
              if ((string) $doc["device"][0]->channel[3]->datapoint[$k]["value"] == "true") {
                $aktuelleDaten["HM_Seriennummer".$s]["Kontakt"] = 1;
                //  (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"]              }
              }
              else {
                $aktuelleDaten["HM_Seriennummer".$s]["Kontakt"] = 0;
              }
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "OPERATING_VOLTAGE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung_Unit"] = "V";
            }
          }
          elseif ($HM_Geraetetyp[$s] == "HM-ES-TX-WM") {
            // Gas und Stromzähler
            Log::write( $i." ".$k." ".(string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"], "   ", 8 );
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "OPERATING_VOLTAGE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Batteriespannung_Unit"] = "V";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "GAS_ENERGY_COUNTER") {
              $aktuelleDaten["HM_Seriennummer".$s]["Kubikmeter_Gas"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Gas_Unit"] = "m³";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "ENERGY_COUNTER") {
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Verbrauch"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] * 1000, 0 );
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Verbrauch_Unit"] = "Wh";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "POWER") {
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Verbrauch_Leistung"] = (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"];
              $aktuelleDaten["HM_Seriennummer".$s]["AC_Verbrauch_Leistung_Unit"] = "W";
            }
            //Datenpunkte von Kanal 1 und 2 haben beim IEC-Sensor identische Bezeichnungen
            //Kanal 1 (=Bezug)
            if ($i == 1) {
                if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "IEC_ENERGY_COUNTER") {
                  $aktuelleDaten["HM_Seriennummer".$s]["Wh_BezugGesamt"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] * 1000, 0 );
                  $aktuelleDaten["HM_Seriennummer".$s]["Wh_BezugGesamt_Unit"] = "Wh";
                }
                if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "IEC_POWER") {
                  $aktuelleDaten["HM_Seriennummer".$s]["W_Bezug_Leistung"] = (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"];
                  $aktuelleDaten["HM_Seriennummer".$s]["W_Bezug_Leistung_Unit"] = "W";
                }
            }
            //Kanal 2 (= Einspeisung)
            if ($i == 2) {
                if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "IEC_ENERGY_COUNTER") {
                  $aktuelleDaten["HM_Seriennummer".$s]["Wh_EinspeisungGesamt"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] * 1000, 0 );
                  $aktuelleDaten["HM_Seriennummer".$s]["Wh_EinspeisungGesamt_Unit"] = "Wh";
                }
            }
          }
          elseif (strtoupper( substr( $HM_Geraetetyp[$s], 0, 10 )) == "HMIP-SWO-P" or strtoupper( substr( $HM_Geraetetyp[$s], 0, 8 )) == "HMIP-SRD") {
            //  Wetterstation
            Log::write( $i." ".$k." ".(string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"], "   ", 8 );
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "ACTUAL_TEMPERATURE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Temperatur"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Temperatur_Unit"] = "°C";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "HUMIDITY") {
              $aktuelleDaten["HM_Seriennummer".$s]["Feuchtigkeit"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Feuchtigkeit_Unit"] = "% rF";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "ILLUMINATION") {
              $aktuelleDaten["HM_Seriennummer".$s]["Helligkeit"] = (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"];
              $aktuelleDaten["HM_Seriennummer".$s]["Helligkeit_Unit"] = "";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "RAINING")    {
              if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] == "true") {
                $aktuelleDaten["HM_Seriennummer".$s]["Regenbeginn.Text"] = 1;
              }
              else {
                $aktuelleDaten["HM_Seriennummer".$s]["Regenbeginn.Text"] = 0;
              }
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "RAIN_COUNTER") {
              $aktuelleDaten["HM_Seriennummer".$s]["Regenmenge"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Regenmenge_Unit"] = "mm";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "SUNSHINEDURATION") {
              $aktuelleDaten["HM_Seriennummer".$s]["Sonnenscheindauer"] = (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"];
              $aktuelleDaten["HM_Seriennummer".$s]["Sonnenscheindauer_Unit"] = "Min";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "WIND_DIR") {
              $aktuelleDaten["HM_Seriennummer".$s]["Windrichtung"] = round((string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"],0 );
              $aktuelleDaten["HM_Seriennummer".$s]["Windrichtung_Unit"] = "Grad";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "WIND_SPEED") {
              $aktuelleDaten["HM_Seriennummer".$s]["Windgeschwindigkeit"] = round((string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"],1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Windgeschwindigkeit_Unit"] = "km/h";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "WIND_DIR_RANGE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Windrichtung_Schwankung"] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 2 );
              $aktuelleDaten["HM_Seriennummer".$s]["Windrichtung_Schwankung_Unit"] = "Grad";
            }
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "HEATER_STATE") {
              if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] == "true") {
                $aktuelleDaten["HM_Seriennummer".$s]["HeaterState.Text"] = 1;
              }
              else {
                $aktuelleDaten["HM_Seriennummer".$s]["HeaterState.Text"] = 0;
              }
            }
          }
          elseif ($HM_Geraetetyp[$s] == "HM-LC-Sw1-FM") {
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "STATE") {
              if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"] == "true") {
                $aktuelleDaten["HM_Seriennummer".$s]["Kontakt".$i] = 1;
              }
              else {
                $aktuelleDaten["HM_Seriennummer".$s]["Kontakt".$i] = 0;
              }
            }
          }
          elseif ($HM_Geraetetyp[$s] == "HM-WDS30-OT2-SM") {
            // Temperatur Differenz Sensor 5 fach
            Log::write( $i." ".$k." ".(string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"], "   ", 8 );
            if ((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"] == "TEMPERATURE") {
              $aktuelleDaten["HM_Seriennummer".$s]["Temperatur".$i] = round( (string) $doc["device"][0]->channel[$i]->datapoint[$k]["value"], 1 );
              $aktuelleDaten["HM_Seriennummer".$s]["Temperatur".$i."_Unit"] = "°C";
            }
          }
          else {
            Log::write( "Gerätetyp noch unbekannt: ".$HM_Geraetetyp[$s]." Bitte melden: support@solaranzeige.de", "   ", 5 );
            Log::write( $i." ".$k." ".(string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"], "   ", 7 );
          }
          if (empty((string) $doc["device"][0]->channel[$i]->datapoint[$k]["type"])) {
            break;
          }
        }
      }
      curl_close( $rCurlHandle2 );
      unset($xml);
      unset($dom);
      unset($doc);
    }
    $aktuelleDaten["Anzahl_Geraete"] = count( $HM_Seriennummer );
    $aktuelleDaten["Anzahl_Variablen"] = 0;
    if (isset($HM_Systemvariable)) {
      for ($s = 1; $s <= count( $HM_Systemvariable ); $s++) {

      /************************************************************
      //  Systemvariablen auslesen.
      //
      ************************************************************/
      $rCurlHandle3 = curl_init( "http://".$WR_IP."/config/xmlapi/sysvarlist.cgi" );
      curl_setopt( $rCurlHandle3, CURLOPT_CUSTOMREQUEST, "GET" );
      curl_setopt( $rCurlHandle3, CURLOPT_TIMEOUT, 20 );
      curl_setopt( $rCurlHandle3, CURLOPT_PORT, 80 );
      curl_setopt( $rCurlHandle3, CURLOPT_RETURNTRANSFER, TRUE );
      $strResponse = curl_exec( $rCurlHandle3 );
      $rc_info = curl_getinfo( $rCurlHandle3 );
      if (curl_errno( $rCurlHandle3 )) {
        Log::write( "Curl Fehler! HomeMatic wurde nicht gelesen! No. ".curl_errno( $ch ), "   ", 5 );
      }
      if ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
        Log::write( "HomeMatic Daten gelesen. ", "*  ", 8 );
      }
      Log::write( "Gerät ".$s." => ".$HM_Systemvariable[$s], "*  ", 8 );
      $xml = XMLReader::xml( $strResponse );

      while ($xml->name !== 'systemVariables') {
        $xml->read( );
      }
      $dom = $xml->expand( new DOMDocument( ));
      $doc = (array) simplexml_import_dom( $dom );
      Log::write( print_r( (array) $doc, 1 ), "   ", 8 );

      for ($i = 0; $i < count( $doc["systemVariable"] ); $i++) {
        Log::write( print_r( (array) $doc["systemVariable"][$i], 1 ), "   ", 8 );

        if ((string) $doc["systemVariable"][$i]->attributes( ) ["name"] == $HM_Systemvariable[$s]) {
            Log::write( print_r( (array) $doc["systemVariable"][$i], 1 ), "   ", 8 );
            $aktuelleDaten["HM_Systemvariable".$s]["Name"] = (string) $doc["systemVariable"][$i]->attributes( ) ["name"];
            $aktuelleDaten["HM_Systemvariable".$s]["Wert"] = (string) $doc["systemVariable"][$i]->attributes( ) ["value"];
            $aktuelleDaten["HM_Systemvariable".$s]["Typ"]  = (string) $doc["systemVariable"][$i]->attributes( ) ["type"];
            $aktuelleDaten["HM_Systemvariable".$s]["Unit"] = (string) $doc["systemVariable"][$i]->attributes( ) ["unit"];
            if ((string) $doc["systemVariable"][$i]->attributes( ) ["name"] == $HM_Systemvariable[$s]) {
              $aktuelleDaten["Anzahl_Variablen"] = $aktuelleDaten["Anzahl_Variablen"] + 1;
              break;
            }
        }
      }

      curl_close( $rCurlHandle3 );

      }
    }
  }
  else {
    goto Ausgang;
  }

  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/

  /****************************************************************************
  //  Ab hier werden Werte in die HomeMatic geschrieben.
  //  Es können mehrere Werte hintereinander geschrieben werden.
  //  Das Auslesen und Schreiben aller Werte darf nicht länger als 9 Sekunden
  //  dauern.
  ****************************************************************************/

  /****************************************************************************
  //  ENDE WERTE SCHREIBEN      ENDE WERTE SCHREIBEN      ENDE WERTE SCHREIBEN
  ****************************************************************************/

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Produkt"] = "HomeMatic";
  $aktuelleDaten["Firmware"] = "unbekannt";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  $aktuelleDaten["WattstundenGesamtHeute"] = 0; // dummy
  Log::write( var_export( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists($basedir."/custom/hm_geraet_math.php" )) {
    include $basedir.'/custom/hm_geraet_math.php'; // Falls etwas neu berechnet werden muss.
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
    $Zeitspanne = (7 - (time( ) - $Start));
    Log::write( "Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2 );
    if ($Zeitspanne > 0) {
      sleep( $Zeitspanne );
    }
    break;
  }
  else {
    Log::write( "Schleife: ".($s)." Zeitspanne: ".(floor( (56 - (time( ) - $Start)) / $Wiederholungen )), "   ", 7 );
    sleep( floor( (56 - (time( ) - $Start)) / $Wiederholungen ));
  }
  if ($Wiederholungen <= $i or $i >= 1) {
    //  Die RCT Wechselrichter dürfen nur einmal pro Minute ausgelesen werden!
    Log::write( "Schleife ".$i." Ausgang...", "   ", 5 );
    break;
  }
  $i++;
} while (($Start + 54) > time( ));

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

/*************************/
Ausgang:

/*************************/
error_reporting( E_ALL );
Log::write( "-------------   Stop   hm_geraet.php    -------------------------- ", "|--", 6 );
return;
?>
