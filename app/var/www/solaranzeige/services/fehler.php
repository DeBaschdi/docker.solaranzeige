<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2015-2016]  [Ulrich Kunz]
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
//  Es dient dem Auslesen des HRDi marlec Laderegler über den
//  Seriell - USB Adapter.
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
$basedir = dirname(__FILE__,2);
require_once($basedir."/library/base.inc.php");
require_once($basedir."/config/user.config.php");

if (!isset($InfluxDBLokal)) {
  $InfluxDBLokal = "solaranzeige";
}

$Tracelevel = 10;  //  1 bis 10  10 = Debug
setlocale(LC_TIME,"de_DE.utf8");


Log::write("------  Fehler!    Fehler!    Fehler!    Fehler!  ------------ ","|--",1);

Log::write("Es ist kein Laderegler / Wechselrichter / sonstiges Gerät  ausgewählt.","   ",1);
Log::write("Die Konfiguration wurde nicht richtig durchgeführt.","   ",1);
Log::write("Wert für SA_REGLER ist nicht gesetzt oder unbekannt: '".getEnvAsString("SA_REGLER","0")."'","   ",1);

Log::write("------  Fehler!    Fehler!    Fehler!    Fehler!  ------------ ","|--",1);

exit;

?>
