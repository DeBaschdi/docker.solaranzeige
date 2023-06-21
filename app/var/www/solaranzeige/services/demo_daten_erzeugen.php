#!/usr/bin/php
<?php
/******************************************************************************
//  Mit diesem Script werden ein paar Demo Daten erzeugt,
//  damit die Grafana Anzeige schon einmal etwas zeigt.
//  
******************************************************************************/
$basedir = dirname(__FILE__,2);
require_once($basedir."/library/base.inc.php");

for ($i=1;$i<5;$i++) {

  $aktuelleDaten = InfluxDB::demo_daten_erzeugen($Regler);
  $rc = InfluxDB::influx_local($aktuelleDaten);

  sleep(2);
}

exit;
?>