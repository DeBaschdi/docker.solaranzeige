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
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$aktuelleDaten = array("Firmware" => 0, "Produkt" => 0, "Geraetestatus" => 0, "Solarspannung" => 0, "Solarstrom" => 0, "Solarleistung" => 0, "AC_Ausgangsspannung" => 0, "AC_Ausgangsstrom" => 0, "AC_Leistung" => 0, "Temperatur" => 0);
$Start = time( ); // Timestamp festhalten
Log::write( "---------   Start  aec_wechselrichter.php    ----------------- ", "|--", 6 );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
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
Log::write( "Hardware Version: ".$Version, "o  ", 8 );
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

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//
*****************************************************************************/
$StatusFile = $basedir."/database/".$GeraeteNummer.".WhProTag.txt";
if (file_exists( $StatusFile )) {

  /***************************************************************************
  //  Daten einlesen ...
  ***************************************************************************/
  $aktuelleDaten["WattstundenGesamtHeute"] = file_get_contents( $StatusFile );
  Log::write( "WattstundenGesamtHeute: ".round( $aktuelleDaten["WattstundenGesamtHeute"], 2 ), "   ", 8 );
  if (empty($aktuelleDaten["WattstundenGesamtHeute"])) {
    $aktuelleDaten["WattstundenGesamtHeute"] = 0;
  }
  if (date( "H:i" ) == "00:00" or date( "H:i" ) == "00:01") { // Jede Nacht 0 Uhr
    $aktuelleDaten["WattstundenGesamtHeute"] = 0; //  Tageszähler löschen
    $rc = file_put_contents( $StatusFile, "0" );
    Log::write( "WattstundenGesamtHeute gelöscht.", "    ", 5 );
  }
}
else {

  /***************************************************************************
  //  Inhalt der Status Datei anlegen.
  ***************************************************************************/
  $rc = file_put_contents( $StatusFile, "0" );
  if ($rc === false) {
    Log::write( "Konnte die Datei whProTag_aec.txt nicht anlegen.", "XX ", 5 );
  }
}
if ($HF2211) {
  // HF2211 WLAN Gateway wird benutzt
  $USB1 = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 5 ); // 5 Sekunden Timeout
  if ($USB1 === false)   {
    Log::write( "Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
    Log::write( "Exit.... ", "XX ", 3 );
    goto Ausgang;
  }
}
else {
  //  Nach dem Öffnen des Port muss sofort der Regler ausgelesen werden, sonst
  //  sendet er asynchrone Daten!
  $USB1 = USB::openUSB( $USBRegler );
  if (!is_resource( $USB1 )) {
    Log::write( "USB Port kann nicht geöffnet werden. [1]", "XX ", 7 );
    Log::write( "Exit.... ", "XX ", 7 );
    goto Ausgang;
  }
}

/******************************************************************************
//  Zuerst aus der Seriennummer die Inverteradresse errechnen > (Modulo 32) + 1
******************************************************************************/
$Zahl = substr( $Seriennummer, - 5 );
$InverterAdresse = ((int)$Zahl%32) + 1;
if ($InverterAdresse < 10) {
  $Reglertyp = AEC::aec_inverter_lesen( $USB1, "#0".$InverterAdresse."9" );
  $InverterAdresse = "0".$InverterAdresse;
}
else {
  $Reglertyp = AEC::aec_inverter_lesen( $USB1, "#".$InverterAdresse."9" );
}
if (empty($Reglertyp)) {
  Log::write( "Der Wechselrichter kann zur Zeit nicht abgefragt werden.", "   ", 4 );
  Log::write( "Die Spannung von den Solarpanelen ist wahrscheinlich zu gering.", "   ", 9 );
}
else {
  $Reglertyp = substr( $Reglertyp, 5, - 3 );
  Log::write( "Reglertyp: ".$Reglertyp, "   ", 9 );
  Log::write( "Die Inverteradresse = ".$InverterAdresse, "   ", 9 );

  /***************************************************************************
  //  Einen Befehl an den Wechselrichter senden
  //
  //    Spannungsmodus mit 40V Mindestspannung:
  //        string: #16B 2 40.0<CR>
  //
  //    Maximalstrom von 1A:
  //        string: #16S 01.0<CR>
  //
  //  Gibt es mehr als 5 Minuten kein Zugriff auf den Wechselrichter
  //  werden die Werte wieder zurück gesetzt.
  ***************************************************************************/
  if (file_exists( "/var/www/pipe/".$GeraeteNummer.".befehl.steuerung" )) {
    Log::write( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' vorhanden----", "|- ", 5 );
    $Inhalt = file_get_contents( "/var/www/pipe/".$GeraeteNummer.".befehl.steuerung" );
    $Befehle = explode( "\n", trim( $Inhalt ));
    Log::write( "Befehle: ".print_r( $Befehle, 1 ), "|- ", 9 );
    for ($i = 0; $i < count( $Befehle ); $i++) {
      if ($i >= 4) {
        //  Es werden nur maximal 5 Befehle pro Datei verarbeitet!
        break;
      }

      /*********************************************************************************
      //  In der Datei "befehle.ini.php" müssen alle gültigen Befehle aufgelistet
      //  werden, die man benutzen möchte.
      //  Achtung! Genau darauf achten, dass der Befehl richtig geschrieben wird,
      //  damit das Gerät keinen Schaden nimmt.
      //  QPI ist nur zum Testen ...
      //  Siehe Dokument:  Befehle_senden.pdf
      *********************************************************************************/
      if (file_exists( $basedir."/config/befehle.ini" )) {
        Log::write( "Die Befehlsliste 'befehle.ini.php' ist vorhanden----", "|- ", 9 );
        $INI_File = parse_ini_file( $basedir."/config/befehle.ini", true );
        $Regler5 = $INI_File["Regler5"];
        Log::write( "Befehlsliste: ".print_r( $Regler5, 1 ), "|- ", 10 );
        foreach ($Regler5 as $Template) {
          $Subst = $Befehle[$i];
          $l = strlen( $Template );
          for ($p = 1; $p < $l;++$p) {
            if ($Template[$p] == "#") {
              $Subst[$p] = "#";
            }
          }
          if ($Template == $Subst) {
            break;
          }
        }
        if ($Template != $Subst) {
          Log::write( "Dieser Befehl ist nicht zugelassen. ".$Befehle[$i], "|o ", 3 );
          Log::write( "Die Verarbeitung der Befehle wird abgebrochen.", "|o ", 3 );
          break;
        }
      }
      else {
        Log::write( "Die Befehlsliste 'befehle.ini.php' ist nicht vorhanden----", "|- ", 3 );
        break;
      }
      $Wert = false;
      $Antwort = "";
      // Hier wird der Befehl gesendet...
      //
      //
      // $USB1 als device gesetzt
      // reicht Befehl aus /pipe/1.befehl.steuerung durch
      // alt:  $Wert = AEC::aec_inverter_lesen($USB,"#".$InverterAdresse."L ".str_pad(substr($Befehle[$i],1), 3, '0', STR_PAD_LEFT ));
      $Wert = AEC::aec_inverter_lesen( $USB1, "#".$InverterAdresse.$Befehle[$i] );
      if ($Wert === false) {
        //
        // alt: Log::write("Befehlsausführung erfolglos! #".$InverterAdresse."L ".str_pad(substr($Befehle[$i],1), 3, '0', STR_PAD_LEFT ),"    ",5);
        Log::write( "Befehlsausführung erfolglos! #".$InverterAdresse.$Befehle[$i], "    ", 5 );
      }
      else {
        // alt: Log::write("Befehl ".$Befehle[$i]." erfolgreich gesendet!","    ",7);
        Log::write( "Befehl ".$Befehle[$i]." erfolgreich gesendet! WR-Antwort: ".$Wert, "    ", 7 );
        // alt: Log::write("Befehlsausführung OK. #".$InverterAdresse."L ".str_pad(substr($Befehle[$i],1), 3, '0', STR_PAD_LEFT ),"    ",8);
        Log::write( "Befehlsausführung OK. #".$InverterAdresse.$Befehle[$i], "    ", 8 );
      }
    }
    $rc = unlink( "/var/www/pipe/".$GeraeteNummer.".befehl.steuerung" );
    if ($rc) {
      Log::write( "Datei  /../pipe/".$GeraeteNummer.".befehl.steuerung  gelöscht.", "    ", 9 );
    }
  }
  else {
    Log::write( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' nicht vorhanden----", "|- ", 9 );
  }
  $i = 1;
  do {
    Log::write( "Die Daten werden ausgelesen...", "+  ", 9 );

    /**************************************************************************
    //  Ab hier wird der Regler ausgelesen.
    //
    //  Ergebniswerte:
    //  $aktuelleDaten["Firmware"]
    //  $aktuelleDaten["Produkt"]
    //  $aktuelleDaten["Batteriespannung"]
    //  $aktuelleDaten["Solarstrom"]
    //  $aktuelleDaten["Solarspannung"]
    //  $aktuelleDaten["Verbraucherstrom"]
    //  $aktuelleDaten["KilowattstundenGesamt"]
    //  $aktuelleDaten["Temperatur"]
    //
    **************************************************************************/
    do { // Die zurückgelieferten Daten müssen 58 Zeichen haben! Könnte sich einmal ändern!
      $Daten = AEC::aec_inverter_lesen( $USB1, "#".$InverterAdresse."0" );
      if (strlen( $Daten ) < 58) {
        // Wenn der Datenstring nicht komplett ist, kann man damit nichts anfangen.... Exit...
        Log::write( "Fehler! Datenlänge: ".strlen( $Daten )." Daten: ".$Daten." Loop: ".$i, "   ", 2 );
        Log::write( "Exit....", "   ", 2 );
        goto Ausgang;
      }
      $rc = AEC::aec_inverter_lesen( $USB1 );
    } while (($Daten === false or strlen( $Daten ) < 58) and ($Start + 55) > time( ));
    // } while ($Daten === false);
    Log::write( "Datenlänge: ".strlen( $Daten ), "   ", 9 );
    $Daten = trim( $Daten );
    Log::write( substr( $Daten, 0, - 2 ), "   ", 10 );
    if ($i == 1) {
      Log::write( substr( $Daten, 0, - 2 ), "   ", 6 );
    }
    $aktuelleDaten["Firmware"] = 0;
    $aktuelleDaten["Produkt"] = $Reglertyp;
    $aktuelleDaten["Geraetestatus"] = trim( substr( $Daten, 5, 3 )); // Status
    $aktuelleDaten["Solarspannung"] = trim( substr( $Daten, 9, 5 )); // Solarspannung
    $aktuelleDaten["Solarstrom"] = trim( substr( $Daten, 15, 5 )); // Solarstrom
    $aktuelleDaten["Solarleistung"] = trim( substr( $Daten, 21, 5 )); // Solarleistung
    $aktuelleDaten["AC_Ausgangsspannung"] = trim( substr( $Daten, 27, 5 )); // Netzspannung
    $aktuelleDaten["AC_Ausgangsstrom"] = trim( substr( $Daten, 33, 5 )); // Netzstrom
    $aktuelleDaten["AC_Leistung"] = trim( substr( $Daten, 39, 5 )); // eingespeiste Leistung  in Watt!
    $aktuelleDaten["Temperatur"] = trim( substr( $Daten, 45, 3 )); // Gerätetemperatur
    $aktuelleDaten["WattstundenGesamtHeute"] = trim( substr( $Daten, 49, 6 )); // Tagesenergie
    do { // Die zurückgelieferten Daten müssen 73 Zeichen haben! Könnte sich einmal ändern!
      $Daten = AEC::aec_inverter_lesen( $USB1, "#".$InverterAdresse."F" );
      if (strlen( $Daten ) < 73) {
        // Wenn der Datenstring nicht komplett ist, kann man damit nichts anfangen.... Exit...
        Log::write( "Fehler! Datenlänge: ".strlen( $Daten )." Daten: ".$Daten." Loop: ".$i, "   ", 2 );
        Log::write( "Exit....", "   ", 2 );
        goto Ausgang;
      }
      $rc = AEC::aec_inverter_lesen( $USB1 );
    } while (($Daten === false or strlen( $Daten ) < 73) and ($Start + 55) > time( ));
    // } while ($Daten === false );
    $aktuelleDaten["FehlerCode"] = trim( substr( $Daten, 61, 3 ));
    $FehlerZeit = trim( substr( $Daten, 65, 5 )); // Zeit seit Fehler in Sekunden
    $aktuelleDaten["EinschaltTimestamp"] = (time( ) - trim( substr( $Daten, 5, 5 )));

    /**************************************************************************
    //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
    //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
    //  Die Funktion ist noch nicht überall implementiert.
    **************************************************************************/
    $FehlermeldungText = "";
    if ((trim( substr( $Daten, 5, 5 )) - $FehlerZeit) < 60) {
      // Der Fehler ist in der letzten Minute aufgetreten.
      $FehlermeldungText = "Allgemeiner Fehler aufgetreten. Fehler Nummer: ".$aktuelleDaten["FehlerCode"];
      Log::write( $FehlermeldungText, "** ", 5 );
    }

    /**************************************************************************
    //  Die Daten werden für die Speicherung vorbereitet.
    **************************************************************************/
    $aktuelleDaten["Regler"] = $Regler;
    $aktuelleDaten["Objekt"] = $Objekt;
    $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
    if ($i == 1)
      Log::write( var_export( $aktuelleDaten, 1 ), "   ", 8 );

    /**************************************************************************
    //  User PHP Script, falls gewünscht oder nötig
    **************************************************************************/
    if (file_exists($basedir."/custom/aec_wechselrichter_meter_math.php" )) {
      include $basedir.'/custom/aec_wechselrichter_math.php'; // Falls etwas neu berechnet werden muss.
    }

    /**************************************************************************
    //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
    //  an den mqtt-Broker Mosquitto gesendet.
    **************************************************************************/
    if ($MQTT and strtoupper($MQTTAuswahl) != "OPENWB") {
      Log::write( "MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1 );
      require($basedir."/services/mqtt_senden.php");
    }

    /**************************************************************************
    //  Zeit und Datum
    **************************************************************************/
    //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
    $aktuelleDaten["Timestamp"] = time( );
    $aktuelleDaten["Monat"] = date( "n" );
    $aktuelleDaten["Woche"] = date( "W" );
    $aktuelleDaten["Wochentag"] = strftime( "%A", time( ));
    $aktuelleDaten["Datum"] = date( "d.m.Y" );
    $aktuelleDaten["Uhrzeit"] = date( "H:i:s" );

    /**************************************************************************
    //  InfluxDB  Zugangsdaten ...stehen in der user.config.php
    //  falls nicht, sind das hier die default Werte.
    **************************************************************************/
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
      sleep( floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1)));
    }
    if ($Wiederholungen <= $i or $i >= 6) {
      Log::write( "OK. Daten gelesen.", "   ", 8 );
      Log::write( "Schleife ".$i." Ausgang...", "   ", 8 );
      break;
    }
    $i++;
  } while (($Start + 56) > time( ));
}
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
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $whProTag = file_get_contents( $StatusFile );
  if ($aktuelleDaten["WattstundenGesamtHeute"] >= $whProTag) {
    $whProTag = $aktuelleDaten["WattstundenGesamtHeute"];
  }
  else {
    // aktuellen Wert in die Datei schreiben:
    $whProTag = ($whProTag + ($aktuelleDaten["AC_Leistung"] / 60));
  }
  $rc = file_put_contents( $StatusFile, $whProTag );
  Log::write( "WattstundenGesamtHeute: ".round( $whProTag, 2 ), "   ", 5 );
}
Ausgang:Log::write( "---------   Stop   aec_wechselrichter.php    ----------------- ", "|--", 6 );
return;
?>