<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2021]  [Ulrich Kunz]
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
//  Es dient dem Auslesen des Victron-energy Reglers über die USB Schnittstelle
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//  Achtung! Der Regler sendet zwischendurch immer wieder asynchrone Daten!
//
*****************************************************************************/
$Tracelevel = 7; //  1 bis 10  10 = Debug
// $Reglermodelle = array("0300","A042","A043","A04C","A053","A054","A055");
$Device = "BMS"; // BMS = Batteriemanagementsystem
$Version = "";
$Start = time( ); // Timestamp festhalten
Log::write( "---------   Start  bmv_serie.php   ----------------- ", "|--", 6 );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
$RemoteDaten = true;
//  Hardware Version ermitteln.
$Teile = explode( " ", $Platine );
if ($Teile[1] == "Pi") {
  $Version = trim( $Teile[2] );
  if ($Teile[3] == "Model") {
    $Version .= trim( $Teile[4] );
    if ($Teile[5] == "Plus") {
      $Version .= trim( $Teile[5] );
    }
  }
}
Log::write( "Hardware Version: ".$Version, "o  ", 9 );
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
//  Nach em Öffnen des Port muss sofort der Regler ausgelesen werden, sonst
//  sendet er asynchrone Daten!
$USB1 = USB::openUSB( $USBRegler );
if (!is_resource( $USB1 )) {
  Log::write( "USB Port kann nicht geöffnet werden. [1]", "XX ", 7 );
  Log::write( "Exit.... ", "XX ", 7 );
  goto Ausgang;
}
$i = 0;
do {
  $i++;
  Log::write( "Die Daten werden ausgelesen...", "+  ", 9 );

  /****************************************************************************
  //  Ab hier wird der Regler ausgelesen.
  //
  //  Ergebniswerte:
  //  $aktuelleDaten["Firmware"]
  //  $aktuelleDaten["Produkt"]
  //  $aktuelleDaten["Batteriespannung"]
  //  $aktuelleDaten["Batteriestrom"]
  //  $aktuelleDaten["KilowattstundenGesamt"]
  //  $aktuelleDaten["AmperestundenGesamt"]
  //  $aktuelleDaten["Temperatur"]
  //  $aktuelleDaten["SOC"]
  //  $aktuelleDaten["TTG"]
  //  $aktuelleDaten["Leistung"]
  //
  ****************************************************************************/
  $Befehl = "1"; // Firmware
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    if ($i < 5) {
      Log::write( "Firmware".trim( $rc ), "!!  ", 5 );
      // echo $i."\n";
      continue; // Fehler beim Auslesen aufgetreten. Nochmal...
    }
    else {
      Log::write( "Firmware".trim( $rc ), "!!  ", 5 );
      goto Ausgang;
    }
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));

  $Befehl = "4"; // Produkt
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "Produkt".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  Log::write( "Produkt: ".$aktuelleDaten["Produkt"], "   ", 1 );

  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));

  $Befehl = "78DED00"; // Main Voltage   ED8D
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "Main Voltage".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7FF0F00"; // SOC 0FFF
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "SOC".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "78FED00"; // Current  ED8F
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "Optionen".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7100300"; // Cumulative W Hours  Ladung 0310
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "WGesamtLadung".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7110300"; // Cumulative W Hours  Entladung 0311
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "WGesamtEntladung".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7ECED00"; //  Temperatur   EDEC -> in Kelvin! wird umgerechnet in Celsius
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "Temperatur".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7FE0F00"; // TTG  0FFE
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "TTG".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "78EED00"; // Leistung ED8E
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "Leistung".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7080300"; // Zeit seit Vollladung 0308
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "Volladung".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7FFEE00"; // Consumed Energy  Ah  EEFF
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "Consumed Energy".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7B6EE00"; // State of Charge EEB6
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "State of Charge".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7020300"; // Depth last discharge    0302
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "Depth last discharge".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7030300"; // Number of charge cycles 0303
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "Charge cycles".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7040300"; // Number of full discharges 0304
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "Full discharges".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7050300"; // Cumulative Amp Hours 0305
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "Volladung".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7090300"; // Number of automatic synchronizations 0309
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "Volladung".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "7FCEE00"; // Alarm on/off EEFC
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "Volladung".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "74E0300"; // Relais on/off 034E
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "Volladung".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  Log::write( var_export( $rc, 1 ), "   ", 9 );
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));
  $Befehl = "77DED00"; // Aux Voltage   ED7D
  $rc = VE::ve_regler_auslesen( $USB1, ":".$Befehl.Utils::VE_CRC( $Befehl ));
  if (Utils::VE_CRC( substr( trim( $rc ), 1, - 2 )) != substr( trim( $rc ), - 2 )) {
    Log::write( "Aux Voltage".trim( $rc ), "!!  ", 5 );
    continue; // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge( $aktuelleDaten, VE::ve_ergebnis_auswerten( $rc ));

  if ($aktuelleDaten["TTG"] >= 0) {
    Log::write( "Restlaufzeit: ".($aktuelleDaten["TTG"]/60)." Stunden.", "   ", 5 );
  }

  Log::write( print_r( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);

  //  User PHP Script, falls gewünscht oder nötig
  /***************************************************************************/
  if (file_exists($basedir."/custom/bmv_serie_math.php" )) {
    include $basedir.'/custom/bmv_serie_math.php'; // Falls etwas neu berechnet werden muss.
  }

  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT and strtoupper($MQTTAuswahl) != "OPENWB") {
    Log::write( "MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1 );
    require($basedir."/services/mqtt_senden.php");
  }

  /****************************************************************************
  //  Zeit und Datum
  ****************************************************************************/
  //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
  $aktuelleDaten["Timestamp"] = time( );
  $aktuelleDaten["Monat"] = date( "n" );
  $aktuelleDaten["Woche"] = date( "W" );
  $aktuelleDaten["Wochentag"] = strftime( "%A", time( ));
  $aktuelleDaten["Datum"] = date( "d.m.Y" );
  $aktuelleDaten["Uhrzeit"] = date( "H:i:s" );

  /**************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  **************************************************************************/
  $FehlermeldungText = "";
  //  Dummy Wert.
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;

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
    if ($RemoteDaten) {
      $rc = InfluxDB::influx_remote( $aktuelleDaten );
      if ($rc) {
        $RemoteDaten = false;
      }
    }
    if ($InfluxDB_local) {
      $rc = InfluxDB::influx_local( $aktuelleDaten );
    }
  }
  elseif ($InfluxDB_local) {
    $rc = InfluxDB::influx_local( $aktuelleDaten );
  }
  if (is_file( $basedir."/config/1.user.config.php" )) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (7 - (time( ) - $Start));
    Log::write( "Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2 );
    if ($Zeitspanne > 0) {
      sleep( $Zeitspanne );
    }
    break;
  }
  else {
    Log::write( "Schleife: ".($i)." Zeitspanne: ".(floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9 );
    sleep( floor( (55 - (time( ) - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write( "OK. Daten gelesen.", "   ", 8 );
    Log::write( "Schleife ".$i." Ausgang...", "   ", 8 );
    break;
  }

} while (($Start + 55) > time( ));
if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {

  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    Log::write( "Daten werden zur HomeMatic übertragen...", "   ", 8 );
    require($basedir."/services/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    Log::write( "Nachrichten versenden...", "   ", 8 );
    require($basedir."/services/meldungen_senden.php");
  }
  Log::write( "OK. Datenübertragung erfolgreich.", "   ", 7 );
}
else {
  Log::write( "Keine gültigen Daten empfangen.", "!! ", 6 );
}

Ausgang:

Log::write( "---------   Stop   bmv_serie.php   ----------------- ", "|--", 6 );
return;
?>