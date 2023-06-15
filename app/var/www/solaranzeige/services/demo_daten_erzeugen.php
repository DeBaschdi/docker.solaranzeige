#!/usr/bin/php
<?php
/******************************************************************************
//  Mit diesem Script werden ein paar Demo Daten erzeugt,
//  damit die Grafana Anzeige schon einmal etwas zeigt.
//  
******************************************************************************/
$path_parts = pathinfo($argv[0]);
$Pfad = $path_parts['dirname'];
require($Pfad."/user.config.php");
require($Pfad."/phpinc/funktionen.inc.php");

$funktionen = new funktionen();

for ($i=1;$i<5;$i++) {

  $aktuelleDaten = $funktionen->demo_daten_erzeugen($Regler);
  $rc = $funktionen->influx_local($aktuelleDaten);

  sleep(2);
}

exit;
?>