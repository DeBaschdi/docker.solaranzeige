<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016 - 2021] [Ulrich Kunz]
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
//  Es dient dem Auslesen der VARTA Storage Modelle wie z.B.
//  VARTA element, VARTA pulse, VARTA link, VARTA flex storage usw.
//  mit LAN Schnittstelle.
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
//
*****************************************************************************/
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$Start = time( ); // Timestamp festhalten
Log::write( "-------------   Start  varta_pulse.php    -------------------------- ", "|--", 6 );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
//  Hardware Version ermitteln.
$Teile = explode( " ", $Platine );
if ($Teile[1] == "Pi") {
  Log::write( "Hardware Version: ".$Platine, "o  ", 8 );
  $Version = trim( $Teile[2] );
  if ($Teile[3] == "Model") {
    $Version .= trim( $Teile[4] );
    if ($Teile[5] == "Plus") {
      $Version .= trim( $Teile[5] );
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
  $WR_ID = "FF";
}
elseif(strlen($WR_Adresse) == 1)  {
  $WR_ID = str_pad(dechex($WR_Adresse),2,"0",STR_PAD_LEFT);
}
else {
  $WR_ID = str_pad(dechex($WR_Adresse),2,"0",STR_PAD_LEFT);
}

Log::write("WR_ID: ".$WR_ID,"+  ",8);


/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//
*****************************************************************************/
$StatusFile = $basedir."/database/".$GeraeteNummer.".WhProTag.txt";
if (!file_exists( $StatusFile )) {

  /***************************************************************************
  //  Inhalt der Status Datei anlegen, wenn nicht existiert.
  ***************************************************************************/
  $rc = file_put_contents( $StatusFile, "0" );
  if ($rc === false) {
    Log::write( "Konnte die Datei whProTag_delta.txt nicht anlegen.", 5 );
  }
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;
}
else {
  $aktuelleDaten["WattstundenGesamtHeute"] = file_get_contents( $StatusFile );
  Log::write( "WattstundenGesamtHeute: ".$aktuelleDaten["WattstundenGesamtHeute"], "   ", 8 );
}
$COM1 = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 10 ); // 10 = Timeout in Sekunden
if (!is_resource( $COM1 )) {
  Log::write( "Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
  Log::write( "Exit.... ", "XX ", 9 );
  goto Ausgang;
}

$i = 1;
do {
  Log::write( "Die Daten werden ausgelesen...", "+  ", 7 );

  /****************************************************************************
  //  Ab hier wird der Wechselrichter ausgelesen.
  //
  //  $aktuelleDaten["Firmware"]
  //  $aktuelleDaten["PV_Leistung"]
  //  $aktuelleDaten["Netz_Leistung"]
  //  $aktuelleDaten["Einspeisung"]
  //  $aktuelleDaten["Bezug"]
  //  $aktuelleDaten["Tableversion"]
  //  $aktuelleDaten["Frequenz"]
  //  $aktuelleDaten["SOC"]
  //  $aktuelleDaten["Energie_installiert"]
  //  $aktuelleDaten["DC_Leistung"]
  //  $aktuelleDaten["verfuegbare_Ladeleistung"]
  //  $aktuelleDaten["verfuegbare_Entladeleistung"]
  //  $aktuelleDaten["verfuegbare_Ladeenergie"]
  //  $aktuelleDaten["verfuegbare_Entladeenergie"]
  //  $aktuelleDaten["AnzBatterieModule"]
  //  $aktuelleDaten["Status"]
  //  $aktuelleDaten["Seriennummer"]
  //  $aktuelleDaten["WattstundenGesamtHeute"]
  //  $aktuelleDaten["WattstundenGesamt"]
  //
  //
  //  function modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp )
  //
  //
  ****************************************************************************/
  $aktuelleDaten["Firmware"] = "";
  $aktuelleDaten["Seriennummer"] = "";
  $GeraeteAdresse = $WR_ID;
  $FunktionsCode = "03";
  $RegisterAdresse = "1000";
  $RegisterAnzahl = "0001";
  $DatenTyp = "ASCII";
  $Timebase = 3000; //  Wie lange soll auf eine Antwort gewartet werden?
  for ($j = 0; $j < 17; $j++) {
    // modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp )
    $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, ($RegisterAdresse + $j), $RegisterAnzahl, $DatenTyp, $Timebase );
    if (is_array( $Ergebnis )) {
      if (trim( $Ergebnis["Wert"] ) == "") {
        break;
      }
      $aktuelleDaten["Firmware"] .= $Ergebnis["Wert"];
    }
    else {
      Log::write( "Lesefehler => Ausgang.", "   ", 5 );
      goto Ausgang;
    }
  }
  Log::write( "Firmware: ".$aktuelleDaten["Firmware"], "   ", 5 );
  $RegisterAdresse = "1051";
  $DatenTyp = "U16";
  $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Tableversion"] = $Ergebnis["Wert"];
  }
  else {
    Log::write( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }
  Log::write( "Modbus Table Version: ".$aktuelleDaten["Tableversion"], "   ", 5 );
  $RegisterAdresse = "1054";
  $DatenTyp = "ASCII";
  for ($j = 0; $j < 10; $j++) {
    $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, ($RegisterAdresse + $j), $RegisterAnzahl, $DatenTyp, $Timebase );
    if (is_array( $Ergebnis )) {
      if (trim( $Ergebnis["Wert"] ) == "") {
        break;
      }
      $aktuelleDaten["Seriennummer"] .= $Ergebnis["Wert"];
    }
    else {
      Log::write( "Lesefehler => Ausgang.", "   ", 5 );
      goto Ausgang;
    }
  }
  $RegisterAdresse = "1064";
  $DatenTyp = "U16";
  $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["AnzBatterieModule"] = $Ergebnis["Wert"];
  }
  else {
    Log::write( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }
  $RegisterAdresse = "1065";
  $DatenTyp = "U16";
  $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Status"] = $Ergebnis["Wert"];
  }
  else {
    Log::write( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }
  $RegisterAdresse = "1066";
  $DatenTyp = "I16";
  $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["DC_Leistung"] = $Ergebnis["Wert"];
    if ($aktuelleDaten["DC_Leistung"] >= 0) {
      $aktuelleDaten["PV_Leistung"] = $Ergebnis["Wert"];
    }
    else {
      $aktuelleDaten["PV_Leistung"] = 0;
    }
  }
  else {
    Log::write( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }
  $RegisterAdresse = "1068";
  $DatenTyp = "U16";
  $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["SOC"] = $Ergebnis["Wert"];
  }
  else {
    Log::write( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }
  $RegisterAdresse = "1069";
  $DatenTyp = "Hex";
  $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $Wh_Gesamt1 = $Ergebnis["Wert"];
  }
  else {
    Log::write( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }
  $RegisterAdresse = "1070";
  $DatenTyp = "Hex";
  $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["WattstundenGesamt"] = hexdec( $Ergebnis["Wert"].$Wh_Gesamt1 );
  }
  else {
    Log::write( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }
  $RegisterAdresse = "1071";
  $DatenTyp = "U16";
  $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Energie_installiert"] = $Ergebnis["Wert"];
  }
  else {
    Log::write( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }
  $RegisterAdresse = "1078";
  $DatenTyp = "I16";
  $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Netz_Leistung"] = $Ergebnis["Wert"];
  }
  else {
    Log::write( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }
  if ($aktuelleDaten["Tableversion"] >= 13) {
    $RegisterAdresse = "1082";
    $DatenTyp = "U16";
    $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
    if (is_array( $Ergebnis )) {
      $aktuelleDaten["Frequenz"] = $Ergebnis["Wert"];
    }
    else {
      Log::write( "Lesefehler => Ausgang.", "   ", 5 );
      goto Ausgang;
    }
    $RegisterAdresse = "1083";
    $DatenTyp = "U16";
    $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
    if (is_array( $Ergebnis )) {
      $aktuelleDaten["verfuegbare_Ladeleistung"] = $Ergebnis["Wert"];
    }
    else {
      Log::write( "Lesefehler => Ausgang.", "   ", 5 );
      goto Ausgang;
    }
    $RegisterAdresse = "1084";
    $DatenTyp = "I16";
    $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
    if (is_array( $Ergebnis )) {
      $aktuelleDaten["verfuegbare_Entladeleistung"] = $Ergebnis["Wert"];
    }
    else {
      Log::write( "Lesefehler => Ausgang.", "   ", 5 );
      goto Ausgang;
    }
    $RegisterAdresse = "1085";
    $DatenTyp = "U16";
    $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
    if (is_array( $Ergebnis )) {
      $aktuelleDaten["verfuegbare_Ladeenergie"] = $Ergebnis["Wert"];
    }
    else {
      Log::write( "Lesefehler => Ausgang.", "   ", 5 );
      goto Ausgang;
    }
    $RegisterAdresse = "1086";
    $DatenTyp = "U16";
    $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
    if (is_array( $Ergebnis )) {
      $aktuelleDaten["verfuegbare_Entladeenergie"] = $Ergebnis["Wert"];
    }
    else {
      Log::write( "Lesefehler => Ausgang.", "   ", 5 );
      goto Ausgang;
    }
    $RegisterAdresse = "1102";
    $DatenTyp = "U16";
    $Ergebnis = ModBus::modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
    if (is_array( $Ergebnis )) {
      $aktuelleDaten["PV_Leistung"] = $Ergebnis["Wert"];
    }
    else {
      Log::write( "Lesefehler => Ausgang.", "   ", 5 );
      goto Ausgang;
    }
  }

  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/
  if ($aktuelleDaten["Netz_Leistung"] >= 0) {
    $aktuelleDaten["Einspeisung"] = $aktuelleDaten["Netz_Leistung"];
    $aktuelleDaten["Bezug"] = 0;
  }
  else {
    $aktuelleDaten["Bezug"] = abs( $aktuelleDaten["Netz_Leistung"] );
    $aktuelleDaten["Einspeisung"] = 0;
  }

  if (substr($aktuelleDaten["Seriennummer"],0,4) == "1301") {
    $aktuelleDaten["Produkt"] = "VARTA Pulse Neo";
  }
  elseif (substr($aktuelleDaten["Seriennummer"],0,4) == "1300") {
    $aktuelleDaten["Produkt"] = "VARTA Pulse";
  }
  elseif (substr($aktuelleDaten["Seriennummer"],0,4) == "1302") {
    $aktuelleDaten["Produkt"] = "VARTA Pulse";
  }
  elseif (substr($aktuelleDaten["Seriennummer"],0,3) == "123") {
    $aktuelleDaten["Produkt"] = "VARTA Element";
  }
  elseif (substr($aktuelleDaten["Seriennummer"],0,3) == "125") {
    $aktuelleDaten["Produkt"] = "VARTA Element";
  }
  elseif (substr($aktuelleDaten["Seriennummer"],0,3) == "126") {
    $aktuelleDaten["Produkt"] = "VARTA Element";
  }
  elseif (substr($aktuelleDaten["Seriennummer"],0,3) == "127") {
    $aktuelleDaten["Produkt"] = "VARTA Element";
  }
  else {
    Log::write( "Modell unbekannt: ".substr($aktuelleDaten["Seriennummer"],0,4), "   ", 8 );
    $aktuelleDaten["Produkt"] = "unbekannt";
  }


  /***************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  ***************************************************************************/
  $FehlermeldungText = "";

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  Log::write( var_export( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists($basedir."/custom/rct_wr_math.php" )) {
    include $basedir.'/custom/rct_wr_math.php'; // Falls etwas neu berechnet werden muss.
  }

  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT) {
    Log::write( "MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1 );
    require($basedir."/services/mqtt_senden.php");
  }

  /****************************************************************************
  //  Zeit und Datum
  ****************************************************************************/
  $aktuelleDaten["Timestamp"] = time( );
  $aktuelleDaten["Monat"] = date( "n" );
  $aktuelleDaten["Woche"] = date( "W" );
  $aktuelleDaten["Wochentag"] = strftime( "%A", time( ));
  $aktuelleDaten["Datum"] = date( "d.m.Y" );
  $aktuelleDaten["Uhrzeit"] = date( "H:i:s" );

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
      $rc = InfluxDB::influx_remote_test( );
      if ($rc) {
        $rc = InfluxDB::influx_remote( $aktuelleDaten );
        if ($rc) {
          $RemoteDaten = false;
        }
      }
      else {
        $RemoteDaten = false;
      }
    }
    if ($InfluxDB_local) {
      $rc = InfluxDB::influx_local( $aktuelleDaten );
    }
  }
  else {
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
    sleep( floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 1) {
    //  Die RCT Wechselrichter dürfen nur einmal pro Minute ausgelesen werden!
    Log::write( "Schleife ".$i." Ausgang...", "   ", 5 );
    break;
  }
  $i++;
} while (($Start + 54) > time( ));

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

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Der Aufwand wird betrieben, da der Wechselrichter mit sehr wenig Licht
//  tagsüber sich ausschaltet und der Zähler sich zurück setzt.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//  Leistung in Watt / 60 Minuten, da 60 mal in der Stunde addiert wird.
*****************************************************************************/
if (file_exists( $StatusFile )) {

  /***************************************************************************
  //  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
  //  pro Tag zu speichern.
  //  Jede Nacht 0 Uhr Tageszähler auf 0 setzen
  ***************************************************************************/
  if (date( "H:i" ) == "00:00" or date( "H:i" ) == "00:01") {
    $rc = file_put_contents( $StatusFile, "0" );
    Log::write( "WattstundenGesamtHeute  gesetzt.", "o- ", 5 );
  }

  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $whProTag = file_get_contents( $StatusFile );
  $whProTag = ($whProTag + ($aktuelleDaten["Einspeisung"]) / 60);
  $rc = file_put_contents( $StatusFile, round( $whProTag, 2 ));
  Log::write( "WattstundenGesamtHeute: ".round( $whProTag, 2 ), "   ", 5 );
}
Ausgang:fclose( $COM1 );
Log::write( "-------------   Stop   varta_pulse.php    -------------------------- ", "|--", 6 );
return;
?>
