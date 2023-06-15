<?php

/******************************************************************************
//  Die Befehle werden mit diesem Script in die Datei
//  /var/www/pipe/[x].befehl.steuerung geschrieben. Mit jedem Aufruf ein
//  Befehl.
//  Es können mehrere Befehle gespeichert werden. In jeder Zeile ein Befehl.
//  Diese Datei wird in den Ausleseroutinen der jeweiligen Regler /
//  Wechselrichter verarbeitet. Am Anfang, nach dem Öffnen des USB Ports.
//
//  Auf Groß und Kleinschreibung der Befehle achten!
//  Die Datei "[x].befehl.steuerung" kann mit diesem Script oder
//  auch mit dem Script  mqtt.prozess.php erstellt werden.
//  Ist der Parameter url=1 angegeben mach der Browser ein reload.
//  In einer URL können maximal 10 Befehle übertragen werden.
******************************************************************************/
$Pfad = ".";
require ("phpinc/funktionen.inc.php");
$Tracelevel = 7; //  1 bis 10  10 = Debug
$funktionen = new funktionen( );
$url = false;
$funktionen->log_schreiben( "GET: ".print_r( $_GET, 1 ), "X- ", 10 );
if (isset($_GET['id'])) {
  $GeraeteID = $_GET['id'];
}
elseif (isset($_GET['config'])) {
  $GeraeteID = $_GET['config'];
}
else {
  $GeraeteID = "1";
}
if (isset($_GET['url'])) {
  $url = true;
}
$n = 0;
for ($k = 0; $k < 10; $k++) {
  if ($k == 0) {
    $befehl = $_GET['befehl'];
  }
  else {
    $befehl = $_GET['befehl'.$k];
  }
  if (empty($befehl)) {
    break;
  }
  $funktionen->log_schreiben( "Es wird der Befehl '".$befehl."' zur Ausführung gespeichert.", "X- ", 3 );
  $funktionen->log_schreiben( "URL: ".print_r( $_GET, 1 ), "X- ", 10 );
  $i = 0;
  do {
    $fh = fopen( "/var/www/pipe/".$GeraeteID.".befehl.steuerung", 'a' );
    fwrite( $fh, $befehl."\n" );
    fclose( $fh );
    $i++;
  } while ($fh === false and $i < 3);
  if (count( $_GET ) == $n) {
    break;
  }
  $n++;
}
if ($url == true) {
  $funktionen->log_schreiben( "URL OK.", "X- ", 1 );
  header( 'Location:'.$_GET['url'] );
}
exit;
?>