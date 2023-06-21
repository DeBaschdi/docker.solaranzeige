<?php

/*************************************************************
//                                 (c) Ulrich Kunz 2016 - 2021
//  Stromdaten von der Strombörse sammeln für die Solaranzeige
//  Die Konfiguration steht in der user.config.php
//  Bei einer Multi-Regler-Version steht sie in
//  1.user.config.php
//  Die Stromdaten werden nur einmal pro Minute geholt und
//  nur in eine Datenbank geschrieben!
//  Entweder user.config.php oder 1.user.config.php
//
*************************************************************/
setlocale( LC_TIME, NULL ); // Damit der Wochentag in Deutsch geschrieben wird.

$basedir = dirname(__FILE__,2);
require($basedir."/library/base.inc.php");

$Tracelevel = 9; //  1 bis 10  10 = Debug
$Ergebnis = array();
$funktionen = new funktionen( );
Log::write( "-------------------- Start awattar.php --------------------", "|--", 6 );

/*********************************************************************
//  aWATTar Preise holen   aWATTar Preise holen   aWATTar Preise holen
*********************************************************************/
if ($aWATTar === true) {

  /*************************************************************
  //  Preise holen
  *************************************************************/
  if(isset($aWATTarLand) and strtoupper($aWATTarLand) == "AT") {
    $URL = "https://api.awattar.at/v1/marketdata";
  }
  else {
    $URL = "https://api.awattar.de/v1/marketdata";
  }
  $ch = curl_init( $URL );
  curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "GET" );
  curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 9 );
  curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
  for ($i = 1; $i < 4; $i++) {
    $result = curl_exec( $ch );
    $rc_info = curl_getinfo( $ch );
    if ($rc_info["http_code"] == 200) {
      break;
    }
    Log::write( "Verbindung zum aWATTar Server zur Zeit gestört. 6 Sekunden warten. i: ".$i, 6 );
    Log::write( var_export( $rc_info, 1 ), 6 );
    sleep( 6 );
  }
  if ($i >= 4) {
    Log::write( "-------------------- Exit 1 aWATTar.php -------------------", "|--", 6 );
    exit;
  }
  $Ergebnis = json_decode( $result, true );
  if (date( "H" ) == "00") {
    // Es werden alle Preise der nächsten 24 Stunden gespeichert, jedoch ohne Sortierung
    for ($i = 0; $i < count( $Ergebnis["data"] ); $i++) {
      $Daten[$i]["Timestamp"] = substr( $Ergebnis["data"][$i]["start_timestamp"], 0, - 3 );
      $Daten[$i]["Preis_kWh"] = ($Ergebnis["data"][$i]["marketprice"] / 1000);
      $Daten[$i]["Stunde"] = date( "H", substr( $Ergebnis["data"][$i]["start_timestamp"], 0, - 3 ));
      $Daten[$i]["Sortierung"] = 0;
      $Daten[$i]["Query"] = "awattarPreise Datum=\"".date( "d.m.Y H:i:s", $Daten[$i]["Timestamp"] )."\",Preis_kWh=".$Daten[$i]['Preis_kWh'].",Sortierung=".$Daten[$i]['Sortierung'].",Stunde=".$Daten[$i]['Stunde']."  ".($Daten[$i]['Timestamp']);
    }
    $Anzahl = count( $Ergebnis["data"] );
  }
  else {
    //  Maximal 12 Stunden!
    for ($i = 0; $i < count( $Ergebnis["data"] ); $i++) {
      $Daten[$i]["Timestamp"] = substr( $Ergebnis["data"][$i]["start_timestamp"], 0, - 3 );
      $Daten[$i]["Preis_kWh"] = ($Ergebnis["data"][$i]["marketprice"] / 1000);
      $Daten[$i]["Stunde"] = date( "H", substr( $Ergebnis["data"][$i]["start_timestamp"], 0, - 3 ));
      if ($i > 12)
        break;
    }
    $DatenSort = sort_col( $Daten, "Preis_kWh" );
    $k = 0;
    for ($i = 13; $i >= 0; $i--) {
      if (count( $Ergebnis["data"] ) < $i) {
        continue;
      }
      $Anzahl = $k;
      $DatenSort[$i]["Sortierung"] = $k;
      $DatenSort[$i]["Query"] = "awattarPreise Datum=\"".date( "d.m.Y H:i:s", $DatenSort[$i]["Timestamp"] )."\",Preis_kWh=".$DatenSort[$i]['Preis_kWh'].",Sortierung=".$DatenSort[$i]['Sortierung'].",Stunde=".$DatenSort[$i]['Stunde']."  ".($DatenSort[$i]['Timestamp']);
      $k++;
    }
    $Daten = sort_col( $DatenSort, "Stunde" );
  }
  Log::write( "Anzahl: ".$Anzahl, "   ", 6 );
  for ($i = 0; $i < $Anzahl; $i++) {
    $aktuelleDaten["Query"] = $Daten[$i]["Query"];
    if (empty($aktuelleDaten["Query"])) {
      continue;
    }
    Log::write( $aktuelleDaten["Query"], "   ", 6 );

    /************************************************************************
    //  InfluxDB  Zugangsdaten ...stehen in der user.config.php
    //  falls nicht, sind das hier die default Werte.
    ************************************************************************/
    $aktuelleDaten["InfluxAdresse"] = $InfluxAdresse;
    $aktuelleDaten["InfluxPort"] = $InfluxPort;
    $aktuelleDaten["InfluxUser"] = $InfluxUser;
    $aktuelleDaten["InfluxPassword"] = $InfluxPassword;
    $aktuelleDaten["InfluxDBName"] = $InfluxDBName;
    $aktuelleDaten["InfluxDaylight"] = $InfluxDaylight;
    $aktuelleDaten["InfluxDBLokal"] = $InfluxDBLokal;
    $aktuelleDaten["InfluxSSL"] = $InfluxSSL;

    /***************************************************************
    //  Daten werden in die Influx Datenbank gespeichert.
    //  Lokal und Remote bei Bedarf.
    ***************************************************************/
    if ($InfluxDB_remote) {
      $rc = daten_speichern( $aktuelleDaten );
      Log::write( "Remote: ".$rc, "|  ", 7 );
      if ($InfluxDB_local) {
        $aktuelleDaten["InfluxAdresse"] = "localhost";
        $aktuelleDaten["InfluxPort"] = "8086";
        $aktuelleDaten["InfluxDBName"] = $InfluxDBLokal;
        $aktuelleDaten["InfluxSSL"] = false;
        $rc = daten_speichern( $aktuelleDaten );
        Log::write( "Lokal: ".$rc, "|* ", 7 );
      }
    }
    else {
      $aktuelleDaten["InfluxAdresse"] = "localhost";
      $aktuelleDaten["InfluxPort"] = "8086";
      $aktuelleDaten["InfluxDBName"] = $InfluxDBLokal;
      $aktuelleDaten["InfluxSSL"] = false;
      $rc = daten_speichern( $aktuelleDaten );
      Log::write( "Lokal: ".$rc, "|**", 7 );
    }
  }
  $aktuelleDaten = array();
  Ausgang:
}
else {
  Log::write( "aWATTar Preise ausgeschaltet.", "o--", 6 );
}
Log::write( "-------------------- Stop  awattar.php ---------------------", "|--", 6 );
function daten_speichern( $daten ) {
  if (isset($daten["InfluxSSL"]) and $daten["InfluxSSL"] == true) {
    $ch = curl_init( 'https://'.$daten["InfluxAdresse"].'/write?db='.$daten["InfluxDBName"].'&precision=s' );
  }
  else {
    $ch = curl_init( 'http://'.$daten["InfluxAdresse"].'/write?db='.$daten["InfluxDBName"].'&precision=s' );
  }
  $i = 1;
  do {
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 9 ); //timeout in second s
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
function sort_col( $table, $colname ) {
  $tn = $ts = $temp_num = $temp_str = array();
  foreach ($table as $key => $row) {
    if (is_numeric( substr( $row[$colname], 0, 1 ))) {
      $tn[$key] = $row[$colname];
      $temp_num[$key] = $row;
    }
    else {
      $ts[$key] = $row[$colname];
      $temp_str[$key] = $row;
    }
  }
  unset($table);
  array_multisort( $tn, SORT_ASC, SORT_NUMERIC, $temp_num );
  array_multisort( $ts, SORT_ASC, SORT_STRING, $temp_str );
  return array_merge( $temp_num, $temp_str );
}
?>