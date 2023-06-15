#!/usr/bin/php
<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2015-2020]  [Ulrich Kunz]
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
//  Es dient dem Auslesen des Wechselrichters Deye Hybrid über eine RS485
//  Schnittstelle mit USB Adapter.
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
$path_parts = pathinfo($argv[0]);
$Pfad = $path_parts['dirname'];
if (!is_file($Pfad."/1.user.config.php")) {
  // Handelt es sich um ein Multi Regler System?
  require ($Pfad."/user.config.php");
}
require_once ($Pfad."/phpinc/funktionen.inc.php");
if (!isset($funktionen)) {
  $funktionen = new funktionen();
}
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Start = time(); // Timestamp festhalten
$funktionen->log_schreiben("----------------------   Start  deye_wr.php   --------------------- ", "|--", 6);
$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale(LC_TIME, "de_DE.utf8");
//  Hardware Version ermitteln.
//  $Platine = "Raspberry Pi Model B Plus Rev 1.2";
$Teile = explode(" ", $Platine);
if ($Teile[1] == "Pi") {
  $funktionen->log_schreiben("Hardware Version: ".$Platine, "o  ", 8);
  $Version = trim($Teile[2]);
  if ($Teile[3] == "Model") {
    $Version .= trim($Teile[4]);
    if ($Teile[5] == "Plus") {
      $Version .= trim($Teile[5]);
    }
  }
}
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
if (empty($WR_Adresse)) {
  $WR_ID = "01";
}
elseif (strlen($WR_Adresse) == 1) {
  $WR_ID = str_pad(dechex($WR_Adresse), 2, "0", STR_PAD_LEFT);
}
elseif (strlen($WR_Adresse) == 2) {
  $WR_ID = str_pad(dechex(substr($WR_Adresse, - 2)), 2, "0", STR_PAD_LEFT);
}
else {
  $WR_ID = dechex($WR_Adresse);
}
$funktionen->log_schreiben("WR_ID: ".$WR_ID, "+  ", 8);
$USB1 = $funktionen->openUSB($USBRegler);
if (!is_resource($USB1)) {
  $funktionen->log_schreiben("USB Port kann nicht geöffnet werden. [1]", "XX ", 7);
  $funktionen->log_schreiben("Exit.... ", "XX ", 7);
  goto Ausgang;
}
$i = 1;
do {
  $funktionen->log_schreiben("Die Daten werden ausgelesen...", "+  ", 9);
  $Timer = 100000; // Milli Sekunden
  /****************************************************************************
  //  Ab hier wird der Regler per MODBUS RTU ausgelesen.
  //  Deye Hybrid Wechselrichter
  //
  ****************************************************************************/
  $Befehl["DeviceID"] = $WR_ID;
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterAddress"] = "0000"; // in HEX
  $Befehl["RegisterCount"] = "0003";
  $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl, $Timer);
  $aktuelleDaten["Info"]["Geraetetyp.Text"] = substr($rc["data"], 0, 4);
  $aktuelleDaten["Info"]["Firmware.Text"] = substr($rc["data"], 8, 4);
  if ($rc["ok"] == false ) {
    $funktionen->log_schreiben("Der Wechselrichter sendet keine Daten. Register 0", "   ", 5);
    goto Ausgang;
  }
  $funktionen->log_schreiben("Gerätetyp: ".$aktuelleDaten["Info"]["Geraetetyp.Text"], "   ", 5);
  $funktionen->log_schreiben("Firmware: ".$aktuelleDaten["Info"]["Firmware.Text"], "   ", 5);
  /*****/
  $Befehl["RegisterAddress"] = "0003"; // in HEX  Dez = 3
  $Befehl["RegisterCount"] = "0005";
  $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
  if ($rc["ok"] == false ) {
    $funktionen->log_schreiben("Der Wechselrichter sendet keine Daten. Register 3", "   ", 5);
    goto Ausgang;
  }
  $aktuelleDaten["Info"]["Seriennummer"] = $funktionen->hex2string($rc["data"]);

  /*****/
  $Befehl["RegisterAddress"] = "0014"; // in HEX  Dez = 3
  $Befehl["RegisterCount"] = "0005";
  $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
  if ($rc["ok"] == false ) {
    $funktionen->log_schreiben("Der Wechselrichter sendet keine Daten. Register 20", "   ", 5);
    goto Ausgang;
  }
  $aktuelleDaten["Service"]["Nennleistung"] = $funktionen->hexdecs(substr($rc["data"], 4, 4).substr($rc["data"], 0, 4)) / 10;
  $aktuelleDaten["Service"]["Anz_MPPT"] = hexdec(substr($rc["data"], 8, 2));
  $aktuelleDaten["Service"]["Anz_Phasen"] = hexdec(substr($rc["data"], 10, 2));

  /*****/
  $Befehl["RegisterAddress"] = "01F4"; // in HEX Dez = 500
  $Befehl["RegisterCount"] = "0003";
  $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl, $Timer);
  if ($rc["ok"] == false ) {
    $funktionen->log_schreiben("Der Wechselrichter sendet keine Daten. Register 500", "   ", 5);
    goto Ausgang;
  }
  $aktuelleDaten["Service"]["Status"] = hexdec(substr($rc["data"], 0, 4));
  // 0000  Standby-Standby
  // 0001  Selbstkontrolle
  // 0002  normal
  // 0003  Alarmalarm
  // 0004  Fehler
  $aktuelleDaten["Summen"]["WattstundenGesamtHeute"] = $funktionen->hexdecs(substr($rc["data"], 8, 4).substr($rc["data"], 4, 4)) * 100;
  $Befehl["RegisterAddress"] = "0200"; // in HEX Dez = 512
  $Befehl["RegisterCount"] = "0018";
  $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl, $Timer);
  if ($rc["ok"] == false ) {
    $funktionen->log_schreiben("Der Wechselrichter sendet keine Daten. Register 512", "   ", 5);
    goto Ausgang;
  }
  $aktuelleDaten["Summen"]["BetriebsstundenGesamt"] = hexdec(substr($rc["data"], 4, 4).substr($rc["data"], 0, 4)); // 512-513
  $aktuelleDaten["Summen"]["LadungBatterieHeute"] = $funktionen->hexdecs(substr($rc["data"], 8, 4)) * 100; // 514
  $aktuelleDaten["Summen"]["EntladungBatterieHeute"] = $funktionen->hexdecs(substr($rc["data"], 12, 4)) * 100; // 515
  $aktuelleDaten["Summen"]["LadungBatterieGesamt"] = hexdec(substr($rc["data"], 20, 4).substr($rc["data"], 16, 4)) * 100; // 516-517
  $aktuelleDaten["Summen"]["EntladungBatterieGesamt"] = hexdec(substr($rc["data"], 28, 4).substr($rc["data"], 24, 4)) * 100; // 518-519
  $aktuelleDaten["Summen"]["BezugHeute"] = hexdec(substr($rc["data"], 32, 4)) * 100; // 520
  $aktuelleDaten["Summen"]["EinspeisungHeute"] = hexdec(substr($rc["data"], 36, 4)) * 100; // 521
  $aktuelleDaten["Summen"]["BezugGesamt"] = hexdec(substr($rc["data"], 44, 4).substr($rc["data"], 40, 4)) * 100; // 522-523
  $aktuelleDaten["Summen"]["EinspeisungGesamt"] = hexdec(substr($rc["data"], 52, 4).substr($rc["data"], 48, 4)) * 100; // 524-525
  $aktuelleDaten["Summen"]["StromverbrauchHeute"] = $funktionen->hexdecs(substr($rc["data"], 56, 4)) * 100; // 526
  $aktuelleDaten["Summen"]["StromverbrauchGesamt"] = hexdec(substr($rc["data"], 64, 4).substr($rc["data"], 60, 4)) * 100; // 527-528
  $aktuelleDaten["Summen"]["PV_ErzeugungHeute"] = hexdec(substr($rc["data"], 68, 4)) * 100; // 529
  $aktuelleDaten["Summen"]["PV_LeistungGesamt"] = hexdec(substr($rc["data"], 92, 4).substr($rc["data"], 88, 4)) * 100; // 534 + 535

  /*****/
  $Befehl["RegisterAddress"] = "021C"; // in HEX Dez = 540
  $Befehl["RegisterCount"] = "0002";
  $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl, $Timer);
  if ($rc["ok"] == false ) {
    $funktionen->log_schreiben("Der Wechselrichter sendet keine Daten. Register 540", "   ", 5);
    goto Ausgang;
  }
  $aktuelleDaten["Service"]["Transformator_Temperatur"] = ($funktionen->hexdecs(substr($rc["data"], 0, 4)) - 1000) / 10; // 540
  $aktuelleDaten["Service"]["Kuehlkoerper_Temperatur"] = ($funktionen->hexdecs(substr($rc["data"], 4, 4)) - 1000) / 10; // 541

  /*****/
  $Befehl["RegisterAddress"] = "024A"; // in HEX Dez = 586
  $Befehl["RegisterCount"] = "0010";
  $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl, $Timer);
  if ($rc["ok"] == false ) {
    $funktionen->log_schreiben("Der Wechselrichter sendet keine Daten. Register 586", "   ", 5);
    goto Ausgang;
  }
  // Die Batteriespannung, Strom und Leistung können auch einen Faktor haben
  $aktuelleDaten["Batterie"]["Batterie1_Temperatur"] = ($funktionen->hexdecs(substr($rc["data"], 0, 4)) - 1000) / 10; // 586
  $aktuelleDaten["Batterie"]["Batterie1_Spannung"] = $funktionen->hexdecs(substr($rc["data"], 4, 4)) / 100; // 587
  $aktuelleDaten["Batterie"]["Batterie1_SOC"] = $funktionen->hexdecs(substr($rc["data"], 8, 4)); // 588
  $aktuelleDaten["Batterie"]["Batterie2_SOC"] = $funktionen->hexdecs(substr($rc["data"], 12, 4)); // 589
  $aktuelleDaten["Batterie"]["Batterie_Leistung"] = $funktionen->hexdecs(substr($rc["data"], 16, 4)); // 590
  $aktuelleDaten["Batterie"]["Batterie1_Strom"] = $funktionen->hexdecs(substr($rc["data"], 20, 4)) / 100; // 591
  $aktuelleDaten["Batterie"]["Restkapazitaet"] = hexdec(substr($rc["data"], 24, 4)); // 592
  $aktuelleDaten["Batterie"]["Batterie2_Spannung"] = $funktionen->hexdecs(substr($rc["data"], 28, 4)) / 100; // 593
  $aktuelleDaten["Batterie"]["Batterie2_Strom"] = $funktionen->hexdecs(substr($rc["data"], 32, 4)) / 100; // 594
  $aktuelleDaten["Batterie"]["Batterie2_Temperatur"] = $funktionen->hexdecs(substr($rc["data"], 40, 4)); // 596

  /*****/
  $Befehl["RegisterAddress"] = "0256"; // in HEX Dez = 598
  $Befehl["RegisterCount"] = "0003";
  $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl, $Timer);
  if ($rc["ok"] == false ) {
    $funktionen->log_schreiben("Der Wechselrichter sendet keine Daten. Register 598", "   ", 5);
    goto Ausgang;
  }
  $aktuelleDaten["GRID"]["Netzspannung_R"] = hexdec(substr($rc["data"], 0, 4)) / 10; // 598
  $aktuelleDaten["GRID"]["Netzspannung_S"] = hexdec(substr($rc["data"], 4, 4)) / 10; // 599
  $aktuelleDaten["GRID"]["Netzspannung_T"] = hexdec(substr($rc["data"], 8, 4)) / 10; // 600

  /*****/
  $Befehl["RegisterAddress"] = "0268"; // in HEX Dez = 616
  $Befehl["RegisterCount"] = "0009";
  $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl, $Timer);
  if ($rc["ok"] == false ) {
    $funktionen->log_schreiben("Der Wechselrichter sendet keine Daten. Register 616", "   ", 5);
    goto Ausgang;
  }
  $aktuelleDaten["GRID"]["OUT-Leistung_R"] = $funktionen->hexdecs(substr($rc["data"], 0, 4)); // 616
  $aktuelleDaten["GRID"]["OUT-Leistung_S"] = $funktionen->hexdecs(substr($rc["data"], 4, 4)); // 617
  $aktuelleDaten["GRID"]["OUT-Leistung_T"] = $funktionen->hexdecs(substr($rc["data"], 8, 4)); // 618
  $aktuelleDaten["GRID"]["OUT-Gesamtleistung"] = $funktionen->hexdecs(substr($rc["data"], 12, 4)); // 619
  $aktuelleDaten["GRID"]["Netzseite-Gesamtleistung"] = $funktionen->hexdecs(substr($rc["data"], 36, 4)); // 625
  
  /*****/
  $Befehl["RegisterAddress"] = "0273"; // in HEX Dez = 627
  $Befehl["RegisterCount"] = "0010";
  $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl, $Timer);
  if ($rc["ok"] == false ) {
    $funktionen->log_schreiben("Der Wechselrichter sendet keine Daten. Register 627", "   ", 5);
    goto Ausgang;
  }
  $aktuelleDaten["INV"]["Spannung_R"] = $funktionen->hexdecs(substr($rc["data"], 0, 4)) / 10; // 627
  $aktuelleDaten["INV"]["Spannung_S"] = $funktionen->hexdecs(substr($rc["data"], 4, 4)) / 10; // 628
  $aktuelleDaten["INV"]["Spannung_T"] = $funktionen->hexdecs(substr($rc["data"], 8, 4)) / 10; // 629
  $aktuelleDaten["INV"]["Strom_R"] = $funktionen->hexdecs(substr($rc["data"], 12, 4)) / 100; // 630
  $aktuelleDaten["INV"]["Strom_S"] = $funktionen->hexdecs(substr($rc["data"], 16, 4)) / 100; // 631
  $aktuelleDaten["INV"]["Strom_T"] = $funktionen->hexdecs(substr($rc["data"], 20, 4)) / 100; // 632
  $aktuelleDaten["INV"]["Leistung_R"] = $funktionen->hexdecs(substr($rc["data"], 24, 4)); // 633
  $aktuelleDaten["INV"]["Leistung_S"] = $funktionen->hexdecs(substr($rc["data"], 28, 4)); // 634
  $aktuelleDaten["INV"]["Leistung_T"] = $funktionen->hexdecs(substr($rc["data"], 32, 4)); // 635
  $aktuelleDaten["INV"]["Leistung"] = $funktionen->hexdecs(substr($rc["data"], 36, 4)); // 636
  $aktuelleDaten["INV"]["Frequenz"] = hexdec(substr($rc["data"], 44, 4)) / 100; // 638

  /*****/
  $Befehl["RegisterAddress"] = "0280"; // in HEX Dez = 640
  $Befehl["RegisterCount"] = "0006";
  $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl);
  if ($rc["ok"] == false ) {
    $funktionen->log_schreiben("Der Wechselrichter sendet keine Daten. Register 640", "   ", 5);
    goto Ausgang;
  }
  $aktuelleDaten["USV"]["Leistung_R"] = $funktionen->hexdecs(substr($rc["data"], 0, 4)); // 640
  $aktuelleDaten["USV"]["Leistung_S"] = $funktionen->hexdecs(substr($rc["data"], 4, 4)); // 641
  $aktuelleDaten["USV"]["Leistung_T"] = $funktionen->hexdecs(substr($rc["data"], 8, 4)); // 642
  $aktuelleDaten["USV"]["Leistung"] = $funktionen->hexdecs(substr($rc["data"], 12, 4)); // 643

  /*****/
  $Befehl["RegisterAddress"] = "0284"; // in HEX Dez = 644
  $Befehl["RegisterCount"] = "0010";
  $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl, $Timer);
  if ($rc["ok"] == false ) {
    $funktionen->log_schreiben("Der Wechselrichter sendet keine Daten. Register 644", "   ", 5);
    goto Ausgang;
  }
  $aktuelleDaten["LOAD"]["Spannung_R"] = $funktionen->hexdecs(substr($rc["data"], 0, 4)) / 10; // 644
  $aktuelleDaten["LOAD"]["Spannung_S"] = $funktionen->hexdecs(substr($rc["data"], 4, 4)) / 10; // 645
  $aktuelleDaten["LOAD"]["Spannung_T"] = $funktionen->hexdecs(substr($rc["data"], 8, 4)) / 10; // 646
  $aktuelleDaten["LOAD"]["Strom_R"] = $funktionen->hexdecs(substr($rc["data"], 12, 4)) / 100; // 647
  $aktuelleDaten["LOAD"]["Strom_S"] = $funktionen->hexdecs(substr($rc["data"], 16, 4)) / 100; // 648
  $aktuelleDaten["LOAD"]["Strom_T"] = $funktionen->hexdecs(substr($rc["data"], 20, 4)) / 100; // 649
  $aktuelleDaten["LOAD"]["Leistung_R"] = $funktionen->hexdecs(substr($rc["data"], 48, 4).substr($rc["data"], 24, 4)); // 650 + 656
  $aktuelleDaten["LOAD"]["Leistung_S"] = $funktionen->hexdecs(substr($rc["data"], 52, 4).substr($rc["data"], 28, 4)); // 651 + 657
  $aktuelleDaten["LOAD"]["Leistung_T"] = $funktionen->hexdecs(substr($rc["data"], 56, 4).substr($rc["data"], 32, 4)); // 652 + 658
  $aktuelleDaten["LOAD"]["Leistung"] = $funktionen->hexdecs(substr($rc["data"], 60, 4).substr($rc["data"], 36, 4)); // 653 + 659
  $aktuelleDaten["LOAD"]["Frequenz"] = hexdec(substr($rc["data"], 44, 4)) / 100; // 655

  /*****/
  $Befehl["RegisterAddress"] = "0295"; // in HEX Dez = 661
  $Befehl["RegisterCount"] = "000B";
  $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl, $Timer);
  if ($rc["ok"] == false ) {
    $funktionen->log_schreiben("Der Wechselrichter sendet keine Daten. Register 661", "   ", 5);
    goto Ausgang;
  }
  $aktuelleDaten["GEN"]["Spannung_R"] = $funktionen->hexdecs(substr($rc["data"], 0, 4)) / 10; // 661
  $aktuelleDaten["GEN"]["Spannung_S"] = $funktionen->hexdecs(substr($rc["data"], 4, 4)) / 10; // 662
  $aktuelleDaten["GEN"]["Spannung_T"] = $funktionen->hexdecs(substr($rc["data"], 8, 4)) / 10; // 663
  $aktuelleDaten["GEN"]["Leistung_R"] = $funktionen->hexdecs(substr($rc["data"], 28, 4).substr($rc["data"], 12, 4)); // 664 + 668
  $aktuelleDaten["GEN"]["Leistung_S"] = $funktionen->hexdecs(substr($rc["data"], 32, 4).substr($rc["data"], 16, 4)); // 665 + 657
  $aktuelleDaten["GEN"]["Leistung_T"] = $funktionen->hexdecs(substr($rc["data"], 36, 4).substr($rc["data"], 20, 4)); // 666 + 658
  $aktuelleDaten["GEN"]["Leistung"] = $funktionen->hexdecs(substr($rc["data"], 40, 4).substr($rc["data"], 24, 4)); // 667 + 659

  /*****/
  $Befehl["RegisterAddress"] = "02A0"; // in HEX Dez 672
  $Befehl["RegisterCount"] = "000B";
  $rc = $funktionen->phocos_pv18_auslesen($USB1, $Befehl, $Timer);
  if ($rc["ok"] == false ) {
    $funktionen->log_schreiben("Der Wechselrichter sendet keine Daten. Register 672", "   ", 5);
    goto Ausgang;
  }
  $aktuelleDaten["PV"]["PV1_Leistung"] = hexdec(substr($rc["data"], 0, 4)); // 672
  $aktuelleDaten["PV"]["PV2_Leistung"] = hexdec(substr($rc["data"], 4, 4)); // 673
  $aktuelleDaten["PV"]["PV1_Spannung"] = hexdec(substr($rc["data"], 16, 4)) / 10; // 676
  $aktuelleDaten["PV"]["PV1_Strom"] = hexdec(substr($rc["data"], 20, 4)) / 10; // 677
  $aktuelleDaten["PV"]["PV2_Spannung"] = hexdec(substr($rc["data"], 24, 4)) / 10; // 678
  $aktuelleDaten["PV"]["PV2_Strom"] = hexdec(substr($rc["data"], 28, 4)) / 10; // 679
  if ( $aktuelleDaten["Service"]["Anz_MPPT"] > 2) {
    $aktuelleDaten["PV"]["PV3_Leistung"] = hexdec(substr($rc["data"], 8, 4)); // 674
    $aktuelleDaten["PV"]["PV4_Leistung"] = hexdec(substr($rc["data"], 12, 4)); // 675
    $aktuelleDaten["PV"]["PV3_Spannung"] = hexdec(substr($rc["data"], 32, 4)) / 10; // 680
    $aktuelleDaten["PV"]["PV3_Strom"] = hexdec(substr($rc["data"], 36, 4)) / 10; // 681
    $aktuelleDaten["PV"]["PV4_Spannung"] = hexdec(substr($rc["data"], 40, 4)) / 10; // 682
    $aktuelleDaten["PV"]["PV4_Strom"] = hexdec(substr($rc["data"], 44, 4)) / 10; // 683
  }
  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/

  /**************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  **************************************************************************/
  $FehlermeldungText = "";

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Info"]["Objekt.Text"] = $Objekt;
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);

  if (date("Y.W") <= "2023.23") {
    // Woche 22
    $funktionen->log_schreiben(print_r($aktuelleDaten, 1), "   ", 5);
  }
  else {
    $funktionen->log_schreiben(print_r($aktuelleDaten, 1), "   ", 8);
  }
  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists("/var/www/html/deye_wr_math.php")) {
    include 'deye_wr_math.php'; // Falls etwas neu berechnet werden muss.
  }

  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT and strtoupper($MQTTAuswahl) != "OPENWB") {
    $funktionen->log_schreiben("MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1);
    require ($Pfad."/mqtt_senden.php");
  }

  /****************************************************************************
  //  Zeit und Datum
  ****************************************************************************/
  //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
  $aktuelleDaten["Timestamp"] = time();
  $aktuelleDaten["Monat"] = date("n");
  $aktuelleDaten["Woche"] = date("W");
  $aktuelleDaten["Wochentag"] = strftime("%A", time());
  $aktuelleDaten["Datum"] = date("d.m.Y");
  $aktuelleDaten["Uhrzeit"] = date("H:i:s");

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
      $rc = $funktionen->influx_remote_test();
      if ($rc) {
        $rc = $funktionen->influx_remote($aktuelleDaten);
        if ($rc) {
          $RemoteDaten = false;
        }
      }
      else {
        $RemoteDaten = false;
      }
    }
    if ($InfluxDB_local) {
      $rc = $funktionen->influx_local($aktuelleDaten);
    }
  }
  else {
    $rc = $funktionen->influx_local($aktuelleDaten);
  }
  if (is_file($Pfad."/1.user.config.php")) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (9 - (time() - $Start));
    $funktionen->log_schreiben("Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    $funktionen->log_schreiben("Schleife: ".($i)." Zeitspanne: ".(floor((56 - (time() - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9);
    sleep(floor((56 - (time() - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben("Schleife ".$i." Ausgang...", "   ", 8);
    break;
  }
  $i++;
} while (($Start + 54) > time());
if (isset($aktuelleDaten["Info"]["Seriennummer"])) {

  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $funktionen->log_schreiben("Daten werden zur HomeMatic übertragen...", "   ", 8);
    require ($Pfad."/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    $funktionen->log_schreiben("Nachrichten versenden...", "   ", 8);
    require ($Pfad."/meldungen_senden.php");
  }
  $funktionen->log_schreiben("OK. Datenübertragung erfolgreich.", "   ", 7);
}
else {
  $funktionen->log_schreiben("Keine gültigen Daten empfangen.", "!! ", 6);
}
Ausgang:$funktionen->log_schreiben("----------------------   Stop   deye_wr.php   --------------------- ", "|--", 6);
return;
?>