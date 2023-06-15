<?php

/*************************************************************
//                                       (c) Ulrich Kunz 2018
//  Wetterdaten sammeln für die Solaranzeige
//  Die Konfiguration steht in der user.config.php
//  Bei einer Multi-Regler-Version steht sie in
//  1.user.config.php
//  Die Wetterdaten werden nur einmal pro Minute geholt und
//  nur in eine Datenbank geschrieben!
//  Entweder user.config.php oder 1.user.config.php
//
*************************************************************/
setlocale( LC_TIME, NULL ); // Damit der Wochentag in Deutsch geschrieben wird.

$basedir = dirname(__FILE__,2);
require($basedir."/library/base.inc.php");

if (is_file($basedir."/config/1.user.config.php")) {
  require($basedir."/config/1.user.config.php");
} else {
  require($basedir."/config/user.config.php");
}

$Tracelevel = 7; //  1 bis 10  10 = Debug
$Ergebnis = array();
$funktionen = new funktionen( );
usleep(rand(1000000, 45000000)); // 0-45s
$funktionen->log_schreiben( "---------------- Start wetterdaten.php --------------------", "|--", 6 );

/*********************************************************************
//  WETTERDATEN     WETTERDATEN     WETTERDATEN     WETTERDATEN
*********************************************************************/
if ($Wetterdaten === true and strlen( $APPID ) == 32 and $StandortID > 0) {

  /*************************************************************
  //  aktuelles Wetter
  *************************************************************/
  $URL = "http://api.openweathermap.org/data/2.5/weather?id=".$StandortID."&APPID=".$APPID."&lang=de&units=metric";
  $ch = curl_init( $URL );
  curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "GET" );
  curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 9 );
  curl_setopt( $ch, CURLOPT_TIMEOUT, 100 );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
  for ($i = 1; $i < 4; $i++) {
    $result = curl_exec( $ch );
    $rc_info = curl_getinfo( $ch );
    if ($rc_info["http_code"] == 200) {
      break;
    }
    $funktionen->log_schreiben( "Verbindung zum Wetterserver 'openweathermap.org' zur Zeit gestört. 6 Sekunden warten. i: ".$i, 6 );
    $funktionen->log_schreiben( var_export( $rc_info, 1 ), 6 );
    sleep( 6 );
  }
  if ($i >= 4) {
    $funktionen->log_schreiben( "---------------- Exit 1 wetterdaten.php -------------------", "|--", 6 );
    exit;
  }
  $Ergebnis = json_decode( $result, true );
  $aktuellesWetter["Datum"] = date( "d.m.Y H:i", $Ergebnis["dt"] );
  $aktuellesWetter["Wolkendichte"] = $Ergebnis["clouds"]["all"];
  $aktuellesWetter["Temperatur"] = $Ergebnis["main"]["temp"];
  $aktuellesWetter["Luftdruck"] = $Ergebnis["main"]["pressure"];
  $aktuellesWetter["Luftfeuchte"] = $Ergebnis["main"]["humidity"];
  $aktuellesWetter["Himmel"] = $Ergebnis["weather"]["0"]["description"];
  $aktuellesWetter["Wind"] = substr( $Ergebnis["wind"]["speed"], 0, 6 );
  if (isset($Ergebnis["wind"]["deg"]))
    $aktuellesWetter["Windrichtung"] = $Ergebnis["wind"]["deg"];
  else
    $aktuellesWetter["Windrichtung"] = 0;
  $aktuellesWetter["Sonnenaufgang"] = date( "H:i", $Ergebnis["sys"]["sunrise"] );
  $aktuellesWetter["Sonnenuntergang"] = date( "H:i", $Ergebnis["sys"]["sunset"] );
  $aktuellesWetter["Ort"] = $Ergebnis["name"];
  if (isset($Ergebnis["rain"]["3h"]))
    $aktuellesWetter["Regenmenge"] = round( substr( $Ergebnis["rain"]["3h"], 0, 5 ), 2 );
  elseif (isset($Ergebnis["rain"]["1h"]))
    $aktuellesWetter["Regenmenge"] = $Ergebnis["rain"]["1h"];
  else
    $aktuellesWetter["Regenmenge"] = 0;
  if (isset($Ergebnis["snow"]["3h"]))
    $aktuellesWetter["Schnee"] = $Ergebnis["snow"]["3h"];
  elseif (isset($Ergebnis["snow"]["1h"]))
    $aktuellesWetter["Schnee"] = $Ergebnis["snow"]["1h"];
  else
    $aktuellesWetter["Schnee"] = 0;
  $funktionen->log_schreiben( "Ort: ".$Ergebnis["name"].",  ".$Ergebnis["coord"]["lat"]." N,  ".$Ergebnis["coord"]["lon"]." O,  ID: ".$Ergebnis["id"], "0+ ", 7 );
  $funktionen->log_schreiben( "Aktuelles Wetter\n".var_export( $aktuellesWetter, 1 ), "   ", 9 );
  $funktionen->log_schreiben( "Bericht: ".$Ergebnis["dt"], "   ", 9 );
  $funktionen->log_schreiben( "Sonnenaufgang: ".$Ergebnis["sys"]["sunrise"], "   ", 9 );
  $funktionen->log_schreiben( "Sonnenuntergang: ".$Ergebnis["sys"]["sunset"], "   ", 9 );
  $funktionen->log_schreiben( "\nAktuelles Wetter\n".var_export( $Ergebnis, 1 ), "   ", 10 );

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
  $aktuelleDaten["Query"] = "aktuellesWetter ";
  $aktuelleDaten["Query"] .= "Datum=\"".$aktuellesWetter['Datum']."\",Sonnenaufgang=\"".$aktuellesWetter['Sonnenaufgang']."\"";
  $aktuelleDaten["Query"] .= ",Sonnenuntergang=\"".$aktuellesWetter['Sonnenuntergang']."\",Himmel=\"".$aktuellesWetter['Himmel']."\"";
  $aktuelleDaten["Query"] .= ",Wolkendichte=".$aktuellesWetter['Wolkendichte'].",Temperatur=".$aktuellesWetter['Temperatur'];
  $aktuelleDaten["Query"] .= ",Luftdruck=".$aktuellesWetter['Luftdruck'].",Luftfeuchte=".$aktuellesWetter['Luftfeuchte'];
  $aktuelleDaten["Query"] .= ",Wind=".$aktuellesWetter['Wind'].",Windrichtung=".$aktuellesWetter['Windrichtung'];
  $aktuelleDaten["Query"] .= ",Regenmenge=".$aktuellesWetter['Regenmenge'].",Schnee=".$aktuellesWetter['Schnee'];

  /*********************************************************************
  //  Daten werden in die Influx Datenbank gespeichert.
  //  Lokal und Remote bei Bedarf.
  *********************************************************************/
  if ($InfluxDB_remote) {
    $rc = wetterdaten_speichern( $aktuelleDaten );
    $funktionen->log_schreiben( "Remote: ".$rc, "|  ", 7 );
    if ($InfluxDB_local) {
      $aktuelleDaten["InfluxAdresse"] = "localhost";
      $aktuelleDaten["InfluxPort"] = "8086";
      $aktuelleDaten["InfluxDBName"] = $InfluxDBLokal;
      $aktuelleDaten["InfluxSSL"] = false;
      $rc = wetterdaten_speichern( $aktuelleDaten );
      $funktionen->log_schreiben( "Lokal: ".$rc, "|* ", 7 );
    }
  }
  else {
    $aktuelleDaten["InfluxAdresse"] = "localhost";
    $aktuelleDaten["InfluxPort"] = "8086";
    $aktuelleDaten["InfluxDBName"] = $InfluxDBLokal;
    $aktuelleDaten["InfluxSSL"] = false;
    $rc = wetterdaten_speichern( $aktuelleDaten );
    $funktionen->log_schreiben( "Lokal: ".$rc, "|**", 7 );
  }
  $aktuelleDaten = array();
}
else {
  $funktionen->log_schreiben( "Wetterdaten ausgeschaltet.", "o--", 6 );
}

/*********************************************************************
//  WETTERPROGNOSE  WETTERPROGNOSE  WETTERPROGNOSE  WETTERPROGNOSE
//  Nötige Daten:
//  $AccessToken = ""
//  $PrognoseItem = "inverter"
//  $PrognoseID = "0"    Item und ID gehören zusammen.
//  Der Script muss unter anderem 20 Minuten nach jeder voller Stunde
//  gestartet werden
*********************************************************************/
if (date( "G" ) > 4 and date( "G" ) < 19 and date( "i" ) == 20) {
  //if(1==1) {  // Wird zum Testen benutzt..
  // Nur zwischen 4 und 19 werden die Prognosen ein mal pro Stunde gespeichert.
  if ((strtoupper( $Prognosedaten ) == "API" or strtoupper( $Prognosedaten ) == "BEIDE") and strlen( $AccessToken ) == 32 and $PrognoseID > 0) {
    $URL = "https://www.solarprognose.de/web/solarprediction/api/v1?access-token=".$AccessToken."&item=".$PrognoseItem."&id=".$PrognoseID."&type=hourly&_format=json&algorithm=".$Algorithmus."&project=solaranzeige.de";
    $ch = curl_init( $URL );
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "GET" );
    curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 15 );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 100 );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    for ($i = 1; $i < 3; $i++) {

      $result = curl_exec( $ch );
      $rc_info = curl_getinfo( $ch );
      if ($rc_info["http_code"] == 200) {
        break;
      }
      $funktionen->log_schreiben( "Verbindung zum Wetterserver 'Solarprognose.de' zur Zeit gestört. x Sekunden warten. i: ".$i, 6 );
      sleep(rand(5, 100));     // 5-100s
    }
    if ($i >= 3) {
      $funktionen->log_schreiben( "---------------- Exit 3 wetterdaten.php -------------------", "|--", 6 );
      $funktionen->log_schreiben( var_export( $rc_info, 1 ), 6 );
      exit;
    }
    $Einzelwerte = json_decode( $result, true );
    if ($Einzelwerte["status"] <> 0) {
      $funktionen->log_schreiben( "Fehler! Solarprognose: ".$Einzelwerte["message"], "   ", 6 );
    }
    else {
      $funktionen->log_schreiben( "Verbindung zum Wetterserver 'Solarprognose.de' erfolgreich.", "   ", 7 );
    }
    if (isset($Einzelwerte["data"])) {
      $Keys = array_keys( $Einzelwerte["data"] );
      $aktuelleDaten["Query"] = "";
      for ($i = 0; $i < count( $Einzelwerte["data"] ); $i++) {
        // Sommer und Winterzeit wird schon vom Prognose Server umgeschaltet!
        if (date("I") == 1) {
          $aktuelleDaten["Timestamp"] = (strval( intval( $Keys[$i] )));
          $funktionen->log_schreiben( "Sommerzeit!", "   ", 7 );
        }
        else {
          $funktionen->log_schreiben( "Winterzeit!", "   ", 7 );
          //
          $aktuelleDaten["Timestamp"] = (strval( intval( $Keys[$i] )) + 3600);   // Neu 4.3.2023
          //  $aktuelleDaten["Timestamp"] = (strval( intval( $Keys[$i] )));
        }
        $aktuelleDaten["Prognose_Watt"] = $Einzelwerte["data"][$Keys[$i]][0] * 1000;
        $aktuelleDaten["Prognose_kWh"] = $Einzelwerte["data"][$Keys[$i]][1] * 1000;
        $aktuelleDaten["Query"] = "Wetterprognose Datum=\"".date( "d.m.Y", $aktuelleDaten["Timestamp"] )."\",Prognose_W=".$aktuelleDaten['Prognose_Watt'].",Prognose_Wh=".$aktuelleDaten['Prognose_kWh']." ".$aktuelleDaten["Timestamp"];
        $funktionen->log_schreiben( print_r( $aktuelleDaten, 1 ), "   ", 8 );

        /*********************************************************************
        //  Daten werden in die Influx Datenbank gespeichert.
        //  Lokal und Remote bei Bedarf.
        *********************************************************************/

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
        if ($InfluxDB_remote) {
          $rc = wetterdaten_speichern( $aktuelleDaten );
          $funktionen->log_schreiben( "Remote: ".$rc, "|  ", 7 );
          if ($InfluxDB_local) {
            $aktuelleDaten["InfluxAdresse"] = "localhost";
            $aktuelleDaten["InfluxPort"] = "8086";
            $aktuelleDaten["InfluxDBName"] = $InfluxDBLokal;
            $aktuelleDaten["InfluxSSL"] = false;
            $rc = wetterdaten_speichern( $aktuelleDaten );
            $funktionen->log_schreiben( "Lokal: ".$rc, "|* ", 7 );
          }
        }
        else {
          $aktuelleDaten["InfluxAdresse"] = "localhost";
          $aktuelleDaten["InfluxPort"] = "8086";
          $aktuelleDaten["InfluxDBName"] = $InfluxDBLokal;
          $aktuelleDaten["InfluxSSL"] = false;
          $rc = wetterdaten_speichern( $aktuelleDaten );
          $funktionen->log_schreiben( "Remote: ".$rc, "|**", 7 );
        }
      }
    }
  }
  elseif (strtoupper( $Prognosedaten ) == "USER" or strtoupper( $Prognosedaten ) == "USER") {
    if (is_file( $Pfad."/prognose.php" )) {
      $funktionen->log_schreiben( "Die eigene Wetterprognose wird benutzt", "   ", 7 );
      // Wird eine eigene Prognose genutzt?
      require ($Pfad."/prognose.php");
    }
  }
  else {
    $funktionen->log_schreiben( "AccessToken ist: ".strlen( $AccessToken )." Zeichen lang. (Nötig sind 32 Zeichen)", "   ", 6 );
    $funktionen->log_schreiben( "PrognoseID: ".$PrognoseID, "   ", 6 );
    $funktionen->log_schreiben( "Wetterprognose ausgeschaltet.", "   ", 5 );
  }
}
$funktionen->log_schreiben( "---------------- Stop  wetterdaten.php ---------------------", "|--", 6 );
exit;
function wetterdaten_speichern( $daten ) {
  if (isset($daten["InfluxSSL"]) and $daten["InfluxSSL"] == true) {
    $ch = curl_init( 'https://'.$daten["InfluxAdresse"].'/write?db='.$daten["InfluxDBName"].'&precision=s' );
  }
  else {
    $ch = curl_init( 'http://'.$daten["InfluxAdresse"].'/write?db='.$daten["InfluxDBName"].'&precision=s' );
  }
  $i = 1;
  do {
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 15 ); //timeout in second s
    curl_setopt( $ch, CURLOPT_PORT, $daten["InfluxPort"] );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $daten["Query"] );
    if (!empty($daten["InfluxUser"]) and !empty($daten["InfluxPassword"])) {
      curl_setopt( $ch, CURLOPT_USERPWD, $daten["InfluxUser"].":".$daten["InfluxPassword"] );
    }
    if (isset($daten["InfluxSSL"]) and $daten["InfluxSSL"] == true) {
      curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
      curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    }
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    $result = curl_exec( $ch );
    $rc_info = curl_getinfo( $ch );
    $Ausgabe = json_decode( $result, true );
    if (curl_errno( $ch )) {
      $Meldung = "Curl Fehler! Daten nicht zur InfluxDB gesendet! Curl ErrNo. ".curl_errno( $ch );
    }
    elseif ($rc_info["http_code"] == 200 or $rc_info["http_code"] == 204) {
      $Meldung = "OK. Daten zur InfluxDB  gesendet.";
      break;
    }
    elseif (empty($Ausgabe["error"])) {
      $i++;
      continue;
    }
    else {
      $Meldung = "Daten nicht zur InfluxDB gesendet! info: ".print_r( $rc_info, 1 );
    }
    $i++;
    sleep( 2 );
  } while ($i < 3);
  curl_close( $ch );
  unset($ch);
  return $Meldung;
}
?>