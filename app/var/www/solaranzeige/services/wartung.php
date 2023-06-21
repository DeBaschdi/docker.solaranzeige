<?php

/************************************************************************
//  Solaranzeige Projekt         Copyright (C) [2016-2021]  [Ulrich Kunz]
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
//  Wartungsroutinen, die jeden Tag um 23:55 Uhr ausgeführt werden
//  Dieser Script kann jederzeit erweitert werden. Z.B. um ein Backup
//  der Influx Datenbanken zu machen.
//  Bei Multi-Regler-Versionen muss diese Datei immer angepasst werden.
//  Eigene Wartungsroutinen bitte in eine Datei "user.wartung.php"
//  schreiben und hier in das Verzeichnis ablegen. Dieser Datei wird
//  am Anschluß durchlaufen. Vorhandenes Dokument: Wartung.pdf
*************************************************************************/
// 365 = 1 Jahr : 730 = 2 Jahre : 1095 = 3 Jahre : 1461 = 4 Jahre
$Tage = 4*365; //  Nach spätestens 4 Jahren sollten ältere Daten
//  gelöscht werden.
//  0 = Löschen ausgeschaltet

$basedir = dirname(__FILE__,2);
require($basedir."/library/base.inc.php");

$Tracelevel = 3; //  1 bis 10  10 = Debug
Log::write( "- - - - - - - - - - - - - - - - - - - - - - - - - - - - -", "|-->", 1 );
Log::write( "Wartung wird durchgeführt....", "", 3 );
if (file_exists( $basedir."/custom/user.wartung.php" )) {
  Log::write( "Die user.wartung.php wird ausgeführt.....", "", 2 );  
  include ($basedir."/custom/user.wartung.php");

}
if (date( "N" ) == 7 and $Tage > 0) { // Jeden Sonntag im Monat
  // Alle Daten die älter als xx Tage sind werden gelöscht.
  // Siehe Zeile 29   >> 0 = ausgeschaltet
  $Datum = date( "Y-m-d", time( ) - ($Tage * 24 * 60 * 60));
  //  $Datenbank[1]            Bitte alle Datenbanken hier aufführen die
  //  ...                      überprüft werden sollen.
  //  $Datenbank[6]
  $Datenbank[1] = "solaranzeige";
  for ($i = 1; $i <= count( $Datenbank ); $i++) {
    Log::write( "Datenbank: '".$Datenbank[$i]."'. Daten älter als dem ".$Datum." werden gelöscht.", "", 4 );
    $ch = curl_init( "http://localhost/query?db=$Datenbank[$i]&precision=s&q=".urlencode( "delete where time < '$Datum'" ));
    $rc = datenbank( $ch );
    if ($rc["rc_info"]["http_code"] == 200) {
      Log::write( "Datenbank: '".$Datenbank[$i]."'. Daten älter als dem ".$Datum." wurden gelöscht.", "", 2 );
    }
  }
}
Log::write( "- - - - - - - - - - - - - - - - - - - - - - - - - - - - -", "|-->", 1 );
exit;

/**************************************************************************
//
//    Funktionen       Funktionen       Funktionen       Funktionen
//
**************************************************************************/

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
?>