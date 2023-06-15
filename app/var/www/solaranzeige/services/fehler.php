#!/usr/bin/php
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
$path_parts = pathinfo($argv[0]);
$Pfad = $path_parts['dirname'];
// Handelt es sich um ein Multi Regler System?
require($Pfad."/user.config.php");


require_once($Pfad."/phpinc/funktionen.inc.php");
if (!isset($funktionen)) {
  $funktionen = new funktionen();
}

$Tracelevel = 10;  //  1 bis 10  10 = Debug
setlocale(LC_TIME,"de_DE.utf8");


$funktionen->log_schreiben("------  Fehler!    Fehler!    Fehler!    Fehler!  ------------ ","|--",1);


$funktionen->log_schreiben("Es ist kein Laderegler / Wechselrichter / sonstiges Gerät  ausgewählt.","   ",1);
$funktionen->log_schreiben("Die Konfiguration wurde nicht richtig durchgeführt.","   ",1);

$funktionen->log_schreiben("------  Fehler!    Fehler!    Fehler!    Fehler!  ------------ ","|--",1);

exit;

?>
