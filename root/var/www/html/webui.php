<?php

/*********************************************************************************
//  Tracelevel:
//  0 = keine LOG Meldungen
//  1 = Nur Fehlermeldungen
//  2 = Fehlermeldungen und Warnungen
//  3 = Fehlermeldungen, Warnungen und Informationen
//  4 = Debugging
//
//  Folgende Parameter sollten übergeben werden:
//  --------------------------------------------
//  WallboxSteuerung0  (0-3)
//  uid=<UID des Dashboards>
//  config=<Nummer der x.user.config.php>  der Wallbox config Datei
//  Beispiel:
//  http://solaranzeige.local/webui.php?WallboxSteuerung0&uid=99openWB001&config=2
//
**********************************************************************************/
$Tracelevel = 3;
$Pfad = dirname( __FILE__ );
$Datenbankname = "steuerung";
$Measurement = "Wallbox";
// error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE | E_STRICT);
if (isset($_GET['config'])) {
  // &config=1  1-6
  // Das muss noch eingeschaltet werden, wenn mehrere Wallboxen an einem
  // Raspberry gesteuert werden sollen.
  // $Measurement = "Wallbox".$_GET['config'];    // Wallbox1 bis Wallbox6
}
// Parameter "wbSteuerung1" bis "wbSteuerung100"
if (strpos( strstr( $_SERVER['REQUEST_URI'], '?' ), "&" )) {
  $Steuerung = substr( strstr( $_SERVER['REQUEST_URI'], '?' ), 1, strpos( strstr( $_SERVER['REQUEST_URI'], '?' ), "&" ) - 1 );
}
else {
  $Steuerung = substr( strstr( $_SERVER['REQUEST_URI'], '?' ), 1 );
}

log_schreiben( "Variable: ".print_r($_GET,1), "    ", 2 );

/***************************************************************************
//  Zuerst wird die Datenbank ausgelesen, wie gerade der Status ist.
//
***************************************************************************/
$ch = curl_init( 'http://localhost/query?db='.$Datenbankname.'&precision=s&q='.urlencode( 'select * from '.$Measurement.' order by time desc limit 1' ));
$rc = datenbank( $ch );
if (!isset($rc["JSON_Ausgabe"]["results"][0]["series"])) {
  log_schreiben( "Die Datenbank 'Steuerung' gibt es nicht oder sie ist leer. Fehler:  ".$rc["JSON_Ausgabe"]["results"][0]["error"], "", 1 );
  if (!isset($rc["JSON_Ausgabe"]["results"][0]["error"])) {
    $query = $Measurement." wbSteuerung1=0,wbSteuerung2=0,wbSteuerung3=0\n";
    $ch = curl_init( 'http://localhost/write?db='.$Datenbankname.'&precision=s' );
    $rc = datenbank( $ch, $query );
  }
}
else {
  switch ($Steuerung) {

    case "WallboxSteuerung0":
      // Wenn "WB Steuerung aus" gedrückt wurde, dann bei der openWB Wallbox
      // ChargeMode = 3 setzen
      if (isset($_GET['config'])) {
        log_schreiben( "Variable: config: ".$_GET['config'], "    ", 3 );
        $fh = fopen( "/var/www/pipe/".$_GET['config'].".befehl.steuerung", 'a' );
        fwrite( $fh, "stop\n" ); // Nur bei der openWB und go-echarger nötig
        fclose( $fh );
      }
      $query = $Measurement." wbSteuerung1=0,wbSteuerung2=0,wbSteuerung3=0,stromquelle=0";
      break;

    case "WallboxSteuerung1":
      $query = $Measurement." wbSteuerung1=1,wbSteuerung2=0,wbSteuerung3=0,stromquelle=1";
      break;

    case "WallboxSteuerung2":
      $query = $Measurement." wbSteuerung1=0,wbSteuerung2=1,wbSteuerung3=0,stromquelle=2";
      break;

    case "WallboxSteuerung3":
      $query = $Measurement." wbSteuerung1=0,wbSteuerung2=0,wbSteuerung3=1,stromquelle=3";
      break;

    default:
      $query = $Measurement." wbSteuerung1=0,wbSteuerung2=0,wbSteuerung3=0,stromquelle=0";
      break;
  }
  $ch = curl_init( 'http://localhost/write?db='.$Datenbankname.'&precision=s' );
  $rc = datenbank( $ch, $query );
  log_schreiben( print_r( $rc, 1 ), "", 4 );
}
if (isset($_GET['uid'])) {
  log_schreiben( "Location:http://".$_SERVER['SERVER_NAME'].":3000/d/".$_GET["uid"], "    ", 3 );
  header( "Location:http://".$_SERVER['SERVER_NAME'].":3000/d/".$_GET["uid"] );
  exit;
}
header( "Location:http://".$_SERVER['SERVER_NAME'].":3000" );
exit;
/********************************/
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

/**************************************************************************
//  Log Eintrag in die Logdatei schreiben
//  $LogMeldung = Die Meldung ISO Format
//  $Loglevel=2   Loglevel 1-4   4 = Trace
**************************************************************************/
function log_schreiben( $LogMeldung, $Titel = "", $Loglevel = 3, $UTF8 = 0 ) {
  global $Tracelevel, $Pfad;
  $LogDateiName = $Pfad."/../log/webui.log";
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
      //  Schreibe in die ge�ffnete Datei.
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
?>