#!/usr/bin/php
<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2020]  [Ulrich Kunz]
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
//  Es dient dem Auslesen des SMARTPI Zählers über das LAN.
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
$Device = "HZ"; // HZ = Heizung
$Start = time( ); // Timestamp festhalten
$Version = "";
$funktionen->log_schreiben( "-------------   Start  oekofen.php   --------------------- ", "|--", 6 );
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
$funktionen->log_schreiben( "Hardware Version: ".$Platine, "o  ", 1 );
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


$COM = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 5 );
if (!is_resource( $COM )) {
  $funktionen->log_schreiben( "Kein Kontakt zur Wallbox ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
  $funktionen->log_schreiben( "Exit.... ", "XX ", 3 );
  goto Ausgang;
}


$i = 1;
do {
  $funktionen->log_schreiben( "Die Daten werden ausgelesen...", "+  ", 9 );

  /****************************************************************************
  //  Ab hier wird die Pelletheizung ausgelesen.
  //
  ****************************************************************************/

  $URL = $WR_Adresse."/all";
  $Daten = $funktionen->read( $WR_IP, $WR_Port, $URL );
  if ($Daten === false) {
    $funktionen->log_schreiben( "Parameter sind falsch... nochmal lesen.", "   ", 3 );
    if ($i >= 2) {
      $funktionen->log_schreiben( var_export( $funktionen->read( $WR_IP, $WR_Port, $URL, $Header ), 1 ), "o=>", 9 );
      break;
    }
    $i++;
    continue;
  }



  $aktuelleDaten["System"]["Aussentemperatur"] = $Daten["system"]["L_ambient"]/10;
  $aktuelleDaten["System"]["Anz_Fehler"] = $Daten["system"]["L_errors"];
  $aktuelleDaten["System"]["USB_Stick"] = $Daten["system"]["L_usb_stick"];
  $aktuelleDaten["System"]["Boiler"] = $Daten["system"]["L_existing_boiler"];


  // Heizkreis 1

  $aktuelleDaten["Heizkreislauf1"]["Vorlauftemp_Raum_ist"] = $Daten["hk1"]["L_roomtemp_act"]/10;
  $aktuelleDaten["Heizkreislauf1"]["Vorlauftemp_Raum_soll"] = $Daten["hk1"]["L_roomtemp_set"]/10;
  $aktuelleDaten["Heizkreislauf1"]["Vorlauftemp_Boden_ist"] = $Daten["hk1"]["L_flowtemp_act"]/10;
  $aktuelleDaten["Heizkreislauf1"]["Vorlauftemp_Boden_soll"] = $Daten["hk1"]["L_flowtemp_set"]/10;

  $aktuelleDaten["Heizkreislauf1"]["Komforttemperatur"] = $Daten["hk1"]["L_comfort"]/10;
  $aktuelleDaten["Heizkreislauf1"]["Status"] = $Daten["hk1"]["L_state"];
  $aktuelleDaten["Heizkreislauf1"]["StatusText.Text"] = $Daten["hk1"]["L_statetext"];
  $aktuelleDaten["Heizkreislauf1"]["Pumpe"] = $Daten["hk1"]["L_pump"];
  $aktuelleDaten["Heizkreislauf1"]["Mode_Auto"] = $Daten["hk1"]["mode_auto"];
  $aktuelleDaten["Heizkreislauf1"]["oekomode"] = $Daten["hk1"]["oekomode"];
  $aktuelleDaten["Heizkreislauf1"]["Temperatur_Urlaub"] = $Daten["hk1"]["temp_vacation"]/10;
  $aktuelleDaten["Heizkreislauf1"]["Nachtabsenkung_soll"] = $Daten["hk1"]["temp_setback"]/10;
  $aktuelleDaten["Heizkreislauf1"]["Raumtemperatur_soll"] = $Daten["hk1"]["temp_heat"]/10;

  if (isset($Daten["hk2"]["L_roomtemp"])) {

    $aktuelleDaten["Heizkreislauf2"]["Raumtemperatur_ist"] = $Daten["hk2"]["L_roomtemp_act"]/10;
    $aktuelleDaten["Heizkreislauf2"]["Raumtemperatur_soll"] = $Daten["hk2"]["L_roomtemp_set"]/10;
    $aktuelleDaten["Heizkreislauf2"]["Vorlauftemp_Boden_ist"] = $Daten["hk2"]["L_flowtemp_act"]/10;
    $aktuelleDaten["Heizkreislauf2"]["Vorlauftemp_Boden_soll"] = $Daten["hk2"]["L_flowtemp_set"]/10;
    $aktuelleDaten["Heizkreislauf2"]["Komforttemperatur"] = $Daten["hk2"]["L_comfort"]/10;
    $aktuelleDaten["Heizkreislauf2"]["Status"] = $Daten["hk2"]["L_state"];
    $aktuelleDaten["Heizkreislauf2"]["StatusText.Text"] = $Daten["hk2"]["L_statetext"];
    $aktuelleDaten["Heizkreislauf2"]["Pumpe"] = $Daten["hk2"]["L_pump"];
    $aktuelleDaten["Heizkreislauf2"]["Mode_Auto"] = $Daten["hk2"]["mode_auto"];
    $aktuelleDaten["Heizkreislauf2"]["oekomode"] = $Daten["hk2"]["oekomode"];
    $aktuelleDaten["Heizkreislauf2"]["Temperatur_Urlaub"] = $Daten["hk2"]["temp_vacation"]/10;
    $aktuelleDaten["Heizkreislauf2"]["Nachtabsenkung_soll"] = $Daten["hk2"]["temp_setback"]/10;
    $aktuelleDaten["Heizkreislauf2"]["Raumtemperatur_soll"] = $Daten["hk2"]["temp_heat"]/10;
  }


  // Puffer 1

  $aktuelleDaten["Puffer1"]["Pufferfuehler_oben_ist"] = $Daten["pu1"]["L_tpo_act"]/10;
  $aktuelleDaten["Puffer1"]["Pufferfuehler_oben_soll"] = $Daten["pu1"]["L_tpo_set"]/10;
  $aktuelleDaten["Puffer1"]["Pufferfuehler_mitte_ist"] = $Daten["pu1"]["L_tpm_act"]/10;
  $aktuelleDaten["Puffer1"]["Pufferfuehler_mitte_soll"] = $Daten["pu1"]["L_tpm_set"]/10;
  $aktuelleDaten["Puffer1"]["Pumpe_Release"] = $Daten["pu1"]["L_pump_release"];
  $aktuelleDaten["Puffer1"]["Pumpe"] = $Daten["pu1"]["L_pump"];
  $aktuelleDaten["Puffer1"]["Status"] = $Daten["pu1"]["L_state"];
  $aktuelleDaten["Puffer1"]["StatusText.Text"] = $Daten["pu1"]["L_statetext"];

  if (isset($Daten["pu2"]["L_tpo_act"])) {

    $aktuelleDaten["Puffer2"]["Pufferfuehler_oben_ist"] = $Daten["pu2"]["L_tpo_act"]/10;
    $aktuelleDaten["Puffer2"]["Pufferfuehler_oben_soll"] = $Daten["pu2"]["L_tpo_set"]/10;
    $aktuelleDaten["Puffer2"]["Pufferfuehler_mitte_ist"] = $Daten["pu2"]["L_tpm_act"]/10;
    $aktuelleDaten["Puffer2"]["Pufferfuehler_mitte_soll"] = $Daten["pu2"]["L_tpm_set"]/10;
    $aktuelleDaten["Puffer2"]["Pumpe_Release"] = $Daten["pu2"]["L_pump_release"];
    $aktuelleDaten["Puffer2"]["Pumpe"] = $Daten["pu2"]["L_pump"];
    $aktuelleDaten["Puffer2"]["Status"] = $Daten["pu2"]["L_state"];
    $aktuelleDaten["Puffer2"]["StatusText.Text"] = $Daten["pu2"]["L_statetext"];

  }

  //  Warmwasserboiler 1

  $aktuelleDaten["Warmwasser1"]["Temperatur_ist"] = $Daten["ww1"]["L_temp_set"]/10;
  $aktuelleDaten["Warmwasser1"]["Temperatur_an_soll"] = $Daten["ww1"]["L_ontemp_act"]/10;
  $aktuelleDaten["Warmwasser1"]["Temperatur_aus_soll"] = $Daten["ww1"]["L_offtemp_act"]/10;
  $aktuelleDaten["Warmwasser1"]["Pumpe"] = $Daten["ww1"]["L_pump"];
  $aktuelleDaten["Warmwasser1"]["Status"] = $Daten["ww1"]["L_state"];
  $aktuelleDaten["Warmwasser1"]["StatusText.Text"] = $Daten["ww1"]["L_statetext"];
  $aktuelleDaten["Warmwasser1"]["Sensor_an"] = $Daten["ww1"]["sensor_on"];
  $aktuelleDaten["Warmwasser1"]["Sensor_aus"] = $Daten["ww1"]["sensor_off"];
  $aktuelleDaten["Warmwasser1"]["Mode_auto"] = $Daten["ww1"]["mode_auto"];
  $aktuelleDaten["Warmwasser1"]["Mode_dhw"] = $Daten["ww1"]["mode_dhw"];
  $aktuelleDaten["Warmwasser1"]["Min_Temperatur_soll"] = $Daten["ww1"]["temp_min_set"]/10;
  $aktuelleDaten["Warmwasser1"]["Max_Temperatur_soll"] = $Daten["ww1"]["temp_max_set"]/10;
  $aktuelleDaten["Warmwasser1"]["Boiler_Vorrang_einmalig"] = $Daten["ww1"]["heat_once"];
  $aktuelleDaten["Warmwasser1"]["Startvorgang"] = $Daten["ww1"]["smartstart"];
  $aktuelleDaten["Warmwasser1"]["Boiler_Vorrang"] = $Daten["ww1"]["use_boiler_heat"];

  if (isset($Daten["ww2"]["L_temp_set"])) {

    $aktuelleDaten["Warmwasser2"]["Temperatur_ist"] = $Daten["ww2"]["L_temp_set"]/10;
    $aktuelleDaten["Warmwasser2"]["Temperatur_an_soll"] = $Daten["ww2"]["L_ontemp_act"]/10;
    $aktuelleDaten["Warmwasser2"]["Temperatur_aus_soll"] = $Daten["ww2"]["L_offtemp_act"]/10;
    $aktuelleDaten["Warmwasser2"]["Pumpe"] = $Daten["ww2"]["L_pump"];
    $aktuelleDaten["Warmwasser2"]["Status"] = $Daten["ww2"]["L_state"];
    $aktuelleDaten["Warmwasser2"]["StatusText.Text"] = $Daten["ww2"]["L_statetext"];
    $aktuelleDaten["Warmwasser2"]["Sensor_an"] = $Daten["ww2"]["sensor_on"];
    $aktuelleDaten["Warmwasser2"]["Sensor_aus"] = $Daten["ww2"]["sensor_off"];
    $aktuelleDaten["Warmwasser2"]["Mode_auto"] = $Daten["ww2"]["mode_auto"];
    $aktuelleDaten["Warmwasser2"]["Mode_dhw"] = $Daten["ww2"]["mode_dhw"];
    $aktuelleDaten["Warmwasser2"]["Min_Temperatur_soll"] = $Daten["ww2"]["temp_min_set"]/10;
    $aktuelleDaten["Warmwasser2"]["Max_Temperatur_soll"] = $Daten["ww2"]["temp_max_set"]/10;
    $aktuelleDaten["Warmwasser2"]["Boiler_Vorrang_einmalig"] = $Daten["ww2"]["heat_once"];
    $aktuelleDaten["Warmwasser2"]["Startvorgang"] = $Daten["ww2"]["smartstart"];
    $aktuelleDaten["Warmwasser2"]["Boiler_Vorrang"] = $Daten["ww2"]["use_boiler_heat"];

  }

  // Pellematic Messwerte

  $aktuelleDaten["Pellematic1"]["Kesseltemperatur_ist"] = $Daten["pe1"]["L_temp_act"]/10;
  $aktuelleDaten["Pellematic1"]["Kesseltemperatur_soll"] = $Daten["pe1"]["L_temp_set"]/10;
  $aktuelleDaten["Pellematic1"]["Flammraumtemperatur_ist"] = $Daten["pe1"]["L_frt_temp_act"]/10;
  $aktuelleDaten["Pellematic1"]["Flammraumtemperatur_soll"] = $Daten["pe1"]["L_frt_temp_set"]/10;
  $aktuelleDaten["Pellematic1"]["Status"] = $Daten["pe1"]["L_state"];
  $aktuelleDaten["Pellematic1"]["StatusText.Text"] = $Daten["pe1"]["L_statetext"];
  $aktuelleDaten["Pellematic1"]["Anz_Zuendung"] = $Daten["pe1"]["L_starts"];
  $aktuelleDaten["Pellematic1"]["Laufzeit"] = $Daten["pe1"]["L_runtime"];
  $aktuelleDaten["Pellematic1"]["Brennerkontakt"] = $Daten["pe1"]["L_br"];
  $aktuelleDaten["Pellematic1"]["Brenner_ak"] = $Daten["pe1"]["L_ak"];
  $aktuelleDaten["Pellematic1"]["Brenner_not"] = $Daten["pe1"]["L_not"];
  $aktuelleDaten["Pellematic1"]["Brenner_stb"] = $Daten["pe1"]["L_stb"];
  $aktuelleDaten["Pellematic1"]["Brennerlaufzeit"] = $Daten["pe1"]["L_runtimeburner"];
  $aktuelleDaten["Pellematic1"]["Brennerrestlaufzeit"] = $Daten["pe1"]["L_resttimeburner"];
  $aktuelleDaten["Pellematic1"]["Luftzirkulation"] = $Daten["pe1"]["L_currentairflow"];
  $aktuelleDaten["Pellematic1"]["Niederdruck_ist"] = $Daten["pe1"]["L_lowpressure"];
  $aktuelleDaten["Pellematic1"]["Niederdruck_soll"] = $Daten["pe1"]["L_lowpressure_set"];
  $aktuelleDaten["Pellematic1"]["Fluegas"] = $Daten["pe1"]["L_fluegas"];
  $aktuelleDaten["Pellematic1"]["Mittlere_Laufzeit"] = $Daten["pe1"]["L_avg_runtime"];
  $aktuelleDaten["Pellematic1"]["Umwaelzpumpe_Drehzahl"] = $Daten["pe1"]["L_uw_speed"];
  $aktuelleDaten["Pellematic1"]["Umwaelzpumpe"] = $Daten["pe1"]["L_uw"];
  $aktuelleDaten["Pellematic1"]["Umwaelzpumpe_release"] = $Daten["pe1"]["L_uw_release"];
  $aktuelleDaten["Pellematic1"]["Pellet_Vorrat"] = $Daten["pe1"]["L_storage_fill"];
  $aktuelleDaten["Pellematic1"]["Pellet_Min"] = $Daten["pe1"]["L_storage_min"];
  $aktuelleDaten["Pellematic1"]["Pellet_Max"] = $Daten["pe1"]["L_storage_max"];
  $aktuelleDaten["Pellematic1"]["Pellet_Zwischenbehaelter"] = $Daten["pe1"]["L_storage_popper"];
  $aktuelleDaten["Pellematic1"]["Pellet_Verbrauch_Heute"] = $Daten["pe1"]["storage_fill_today"];
  $aktuelleDaten["Pellematic1"]["Pellet_Verbrauch_Gestern"] = $Daten["pe1"]["storage_fill_yesterday"];
  $aktuelleDaten["Pellematic1"]["Brenner_Modulation"] = $Daten["pe1"]["L_modulation"];  // Prozent
  $aktuelleDaten["Pellematic1"]["Mode"] = $Daten["pe1"]["mode"];


  //print_r($aktuelleDaten);


  /***************************************************************************
  //  Ende Laderegler auslesen
  ***************************************************************************/
  $FehlermeldungText = "";

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Info"]["Objekt.Text"] = $Objekt;
  $aktuelleDaten["Info"]["Firmware.Text"] = "1";
  $aktuelleDaten["Info"]["Modell.Text"] = "Pellematic";
  $aktuelleDaten["zentralerTimestamp"] = ( $aktuelleDaten["zentralerTimestamp"] + 10);

  $funktionen->log_schreiben( var_export( $aktuelleDaten, 1 ), "   ", 8 );



  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/oekofen_math.php" )) {
    include 'oekofen_math.php'; // Falls etwas neu berechnet werden muss.
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
  else {
    $rc = $funktionen->influx_local( $aktuelleDaten );
  }
  if (is_file( $Pfad."/1.user.config.php" )) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (7 - (time( ) - $Start));
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
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben( "Schleife ".$i." Ausgang...", "   ", 5 );
    break;
  }
  $i++;
} while (($Start + 54) > time( ));
if (1 == 1) {

  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $aktuelleDaten["Solarspannung"] = $aktuelleDaten["Solarspannung1"];
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
fclose( $COM );

/******/
Ausgang:
/******/


$funktionen->log_schreiben( "-------------   Stop   oekofen.php   ---------------------- ", "|--", 6 );
return;
?>
