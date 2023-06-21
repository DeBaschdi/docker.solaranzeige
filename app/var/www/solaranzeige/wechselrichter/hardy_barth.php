<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2022]  [Ulrich Kunz]
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
//  Es dient dem Auslesen der Hardy Barth eCB1 Wallbox über das LAN.
//  Das Salia Modell ist noch nicht fertig integriert!
//  ----------------          ------------       
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Header ="";
$Device = "WB"; // WB = Wallbox
$Start = time( ); // Timestamp festhalten
Log::write( "-------------   Start  hardy_barth.php   --------------------- ", "|--", 6 );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
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
//  Die Status Datei wird dazu benutzt, um die Ladeleistung der Wallbox
//  pro Ladung zu speichern.
//
*****************************************************************************/
$StatusFile = $basedir."/database/".$GeraeteNummer.".WhProLadung.txt";
if (file_exists( $StatusFile )) {

  /***************************************************************************
  //  Daten einlesen ...
  ***************************************************************************/
  $aktuelleDaten["WattstundenProLadung"] = file_get_contents( $StatusFile );
  Log::write( "WattstundenProLadung: ".round( $aktuelleDaten["WattstundenProLadung"], 2 ), "   ", 8 );
  if (empty($aktuelleDaten["WattstundenProLadung"])) {
    $aktuelleDaten["WattstundenProLadung"] = 0;
  }
}
else {

  /***************************************************************************
  //  Inhalt der Status Datei anlegen.
  ***************************************************************************/
  $rc = file_put_contents( $StatusFile, "0" );
  if ($rc === false) {
    Log::write( "Konnte die Datei WhProLadung.txt nicht anlegen.", "XX ", 5 );
  }
}
if (empty($WR_Adresse)) {
  $WBID = "1";
}
else {
  $WBID = (intval( $WR_Adresse ));
}
$COM = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 5 );
if (!is_resource( $COM )) {
  Log::write( "Kein Kontakt zur Wallbox ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
  Log::write( "Exit.... ", "XX ", 3 );
  goto Ausgang;
}

/***************************************************************************
//  Einen Befehl an die Wallbox senden
//
//
//
***************************************************************************/
if (file_exists( "/var/www/pipe/".$GeraeteNummer.".befehl.steuerung" )) {
  Log::write( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' vorhanden----", "|- ", 5 );
  $Inhalt = file_get_contents( "/var/www/pipe/".$GeraeteNummer.".befehl.steuerung" );
  $Befehle = explode( "\n", trim( $Inhalt ));
  Log::write( "Befehle: ".print_r( $Befehle, 1 ), "|- ", 9 );
  for ($i = 0; $i < count( $Befehle ); $i++) {
    if ($i > 6) {
      //  Es werden nur maximal 6 Befehle pro Datei verarbeitet!
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
      $Regler60 = $INI_File["Regler60"];
      Log::write( "Befehlsliste: ".print_r( $Regler60, 1 ), "|- ", 8 );
      foreach ($Regler60 as $Template) {
        $Subst = $Befehle[$i];
        $l = strlen( $Template );
        for ($p = 1; $p < $l;++$p) {
          Log::write( "Template: ".$Template." Subst: ".$Subst." l: ".$l, "|- ", 10 );
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
    $Teile = explode( "_", $Befehle[$i] );
    $Antwort = "";
    // Auf "manual" Mode umschalten! Damit Befehle entgegemngenommen werden.
    $http_daten = array("URL" => "http://".$WR_IP."/api/v1/pvmode", "Port" => $WR_Port, "Header" => array('Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'), "Data" => "pvmode=manual");
    $Daten = Utils::http_read( $http_daten );
    Log::write( "Wallbox auf PVMode = manual  umschalten", "   ", 7 );
    // Hier wird der Befehl gesendet...
    // -----------------------------------------------------
    // $Teile[0] , $Teile[1]
    if ($Teile[0] == "start") {
      $http_daten = array("URL" => "http://".$WR_IP."/api/v1/chargecontrols/".$WBID."/start", "Port" => $WR_Port, "Header" => array('Content-Type: application/json', 'Accept: application/json', 'Content-length: 0'), "Data" => "");
      $Daten = Utils::http_read( $http_daten );
      if (isset($Daten["errors"]["message"])) {
        Log::write( "Fehler!: ".$Daten["errors"]["message"], "   ", 1 );
      }
      Log::write( "Wallbox: Ladung gestartet.", "   ", 7 );
    }
    elseif ($Teile[0] == "stop") {
      $http_daten = array("URL" => "http://".$WR_IP."/api/v1/chargecontrols/".$WBID."/stop", "Port" => $WR_Port, "Header" => array('Content-Type: application/json', 'Accept: application/json', 'Content-length: 0'), "Data" => "");
      $Daten = Utils::http_read( $http_daten );
      if (isset($Daten["errors"]["message"])) {
        Log::write( "Fehler!: ".$Daten["errors"]["message"], "   ", 1 );
      }
      Log::write( "Wallbox: Ladung unterbrochen.", "   ", 7 );
    }
    elseif ($Teile[0] == "ampere") {
      $http_daten = array("URL" => "http://".$WR_IP."/api/v1/pvmode/manual/ampere", "Port" => $WR_Port, "Header" => array('Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'), "Data" => "manualmodeamp=".$Teile[1]);
      $Daten = Utils::http_read( $http_daten );
      Log::write( "Wallbox: Stromstärke eingestellt auf ".$Teile[1]." Ampere", "   ", 7 );
    }
    // -----------------------------------------------------
  }
  $rc = unlink( "/var/www/pipe/".$GeraeteNummer.".befehl.steuerung" );
  if ($rc) {
    Log::write( "Datei  /../pipe/".$GeraeteNummer.".befehl.steuerung  gelöscht.", "    ", 9 );
  }
  sleep( 3 );
}
else {
  Log::write( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' nicht vorhanden----", "|- ", 9 );
}
$i = 1;
do {
  Log::write( "Die Daten werden ausgelesen...", "+  ", 9 );

  /****************************************************************************
  //  Ab hier wird die Wallbox ausgelesen.
  //
  ****************************************************************************/
  $URL = "api/v1/meters/".$WBID."/";
  $Daten = Utils::read( $WR_IP, $WR_Port, $URL );
  if ($Daten === false) {
    Log::write( "Parameter sind falsch... nochmal lesen.", "   ", 3 );
    if ($i >= 2) {
      Log::write( var_export( Utils::read( $WR_IP, $WR_Port, $URL, $Header ), 1 ), "o=>", 9 );
      break;
    }
    $i++;
    continue;
  }
  if (!isset($Daten["meter"]["name"])) {
    // Es ist ein Salia Modell
    $aktuelleDaten["Name"] = $Daten["device"]["modelname"];
    $aktuelleDaten["wbsaldoactive"] = 0;
    $aktuelleDaten["Wh_Leistung_aktuell"] = 
    $aktuelleDaten["Wh_Gesamtleistung"] = $Daten["secc"]["port0"]["metering"]["energy"]["active_total"]["actual"];
    $aktuelleDaten["Leistungsfaktor"] = 0;
    $aktuelleDaten["Leistung_R"] = $Daten["secc"]["port0"]["metering"]["power"]["active"]["ac"]["l1"]["actual"];
    $aktuelleDaten["Strom_R"] = ($Daten["secc"]["port0"]["metering"]["current"]["ac"]["l1"]["actual"] / 100);
    $aktuelleDaten["Spannung_R"] = 230;
    $aktuelleDaten["Leistungsfaktor_R"] = 0;
    $aktuelleDaten["Leistung_S"] = $Daten["secc"]["port0"]["metering"]["power"]["active"]["ac"]["l2"]["actual"];
    $aktuelleDaten["Strom_S"] = ($Daten["secc"]["port0"]["metering"]["current"]["ac"]["l2"]["actual"] / 100);
    $aktuelleDaten["Spannung_S"] = 230;
    $aktuelleDaten["Leistungsfaktor_S"] = 0;
    $aktuelleDaten["Leistung_T"] = $Daten["secc"]["port0"]["metering"]["power"]["active"]["ac"]["l3"]["actual"];
    $aktuelleDaten["Strom_T"] = ($Daten["secc"]["port0"]["metering"]["current"]["ac"]["l3"]["actual"] / 100);
    $aktuelleDaten["Spannung_T"] = 230;
    $aktuelleDaten["Leistungsfaktor_T"] = 0;
    $aktuelleDaten["Seriennummer"] = $Daten["device"]["serial"];
    $aktuelleDaten["Protokoll-Version"] = $Daten["device"]["software_version"];
    $aktuelleDaten["Mode"] = $Daten["secc"]["port0"]["charging"];
    $aktuelleDaten["LadungAmpere"] = ($aktuelleDaten["Strom_R"] + $aktuelleDaten["Strom_S"] + $aktuelleDaten["Strom_T"]);
    $aktuelleDaten["Status"] = $Daten["secc"]["port0"]["ci"]["charge"]["cp"]["status"];
    $aktuelleDaten["AmpereVorgabe"] = $Daten["secc"]["port0"]["ci"]["evse"]["basic"]["grid_current_limit"]["actual"];
    $aktuelleDaten["MaxAmpere"] = $Daten["secc"]["port0"]["ci"]["evse"]["basic"]["offered_current_limit"];
    $aktuelleDaten["Anz_Phasen"] = $Daten["secc"]["port0"]["ci"]["evse"]["basic"]["phase_count"];
    $aktuelleDaten["Hersteller"] = $Daten["device"]["modelname"];
    $aktuelleDaten["ModeID"] = 0;
    $aktuelleDaten["StateID"] = $Daten["secc"]["port0"]["cp"]["pwm_state"]["actual"];
    $aktuelleDaten["Wh_Leistung_aktuell"] = $Daten["secc"]["port0"]["metering"]["power"]["active_total"]["actual"];;

    if ($Daten["secc"]["port0"]["ci"]["charge"]["plug"]["status"] == "locked") {
      $aktuelleDaten["Connected"] = 1;
    }
    else {
      $aktuelleDaten["Connected"] = 0;
    }
    $aktuelleDaten["PVMode"] = $Daten["secc"]["port0"]["salia"]["chargemode"];

    Log::write( var_export( Utils::read( $WR_IP, $WR_Port, "api/", $Header ), 1 ), "o=>", 9 );

  }
  else {
    $aktuelleDaten["Name"] = $Daten["meter"]["name"];
    $aktuelleDaten["wbsaldoactive"] = $Daten["meter"]["data"]["lgwb"];
    $aktuelleDaten["Wh_Leistung_aktuell"] = $Daten["meter"]["data"]["1-0:1.4.0"];
    $aktuelleDaten["Wh_Gesamtleistung"] = $Daten["meter"]["data"]["1-0:1.8.0"] * 1000;
    $aktuelleDaten["Leistungsfaktor"] = $Daten["meter"]["data"]["1-0:13.4.0"];
    $aktuelleDaten["Leistung_R"] = $Daten["meter"]["data"]["1-0:21.4.0"];
    $aktuelleDaten["Strom_R"] = $Daten["meter"]["data"]["1-0:31.4.0"];
    $aktuelleDaten["Spannung_R"] = $Daten["meter"]["data"]["1-0:32.4.0"];
    $aktuelleDaten["Leistungsfaktor_R"] = $Daten["meter"]["data"]["1-0:33.4.0"];
    $aktuelleDaten["Leistung_S"] = $Daten["meter"]["data"]["1-0:41.4.0"];
    $aktuelleDaten["Strom_S"] = $Daten["meter"]["data"]["1-0:51.4.0"];
    $aktuelleDaten["Spannung_S"] = $Daten["meter"]["data"]["1-0:52.4.0"];
    $aktuelleDaten["Leistungsfaktor_S"] = $Daten["meter"]["data"]["1-0:53.4.0"];
    $aktuelleDaten["Leistung_T"] = $Daten["meter"]["data"]["1-0:61.4.0"];
    $aktuelleDaten["Strom_T"] = $Daten["meter"]["data"]["1-0:71.4.0"];
    $aktuelleDaten["Spannung_T"] = $Daten["meter"]["data"]["1-0:72.4.0"];
    $aktuelleDaten["Leistungsfaktor_T"] = $Daten["meter"]["data"]["1-0:73.4.0"];
    $aktuelleDaten["Seriennummer"] = $Daten["meter"]["serial"];
    $aktuelleDaten["Protokoll-Version"] = $Daten["protocol-version"];
    $URL = "api/v1/chargecontrols/".$WBID;
    $Daten = Utils::read( $WR_IP, $WR_Port, $URL );
    if ($Daten === false) {
      Log::write( "Parameter sind falsch... nochmal lesen.", "   ", 3 );
      if ($i >= 2) {
        Log::write( var_export( Utils::read( $WR_IP, $WR_Port, $URL, $Header ), 1 ), "o=>", 9 );
        break;
      }
      $i++;
      continue;
    }
    //  Status:
    //  A = Ready
    //  B = Ladung aktiv
    //  C = Fehler
    //  D = Fehler
    //  Kein Strom
    $aktuelleDaten["Mode"] = $Daten["chargecontrol"]["mode"];
    $aktuelleDaten["LadungAmpere"] = $Daten["chargecontrol"]["currentpwmamp"];
    $aktuelleDaten["Status"] = $Daten["chargecontrol"]["state"];
    $aktuelleDaten["AmpereVorgabe"] = $Daten["chargecontrol"]["manualmodeamp"];
    $aktuelleDaten["MaxAmpere"] = $Daten["chargecontrol"]["supplylinemaxamp"];
    $aktuelleDaten["Connected"] = $Daten["chargecontrol"]["connected"];
    $aktuelleDaten["ModeID"] = $Daten["chargecontrol"]["modeid"];
    $aktuelleDaten["StateID"] = $Daten["chargecontrol"]["stateid"];
    $aktuelleDaten["Hersteller"] = $Daten["chargecontrol"]["vendor"];
    if ($Daten["chargecontrol"]["connected"] <> 1) {
      $aktuelleDaten["Connected"] = 0;
    }
    else {
      $aktuelleDaten["Connected"] = 1;
    }
    $URL = "api/v1/rfidtags";
    $Daten = Utils::read( $WR_IP, $WR_Port, $URL );
    if ($Daten === false) {
      Log::write( "Parameter sind falsch... nochmal lesen.", "   ", 3 );
      if ($i >= 2) {
        Log::write( var_export( Utils::read( $WR_IP, $WR_Port, $URL, $Header ), 1 ), "o=>", 9 );
        break;
      }
      $i++;
      continue;
    }
    $URL = "api/v1/pvmode";
    $Daten = Utils::read( $WR_IP, $WR_Port, $URL );
    if ($Daten === false) {
      Log::write( "Parameter sind falsch... nochmal lesen.", "   ", 3 );
      if ($i >= 2) {
        Log::write( var_export( Utils::read( $WR_IP, $WR_Port, $URL, $Header ), 1 ), "o=>", 9 );
        break;
      }
      $i++;
      continue;
    }
    $aktuelleDaten["PVMode"] = $Daten["pvmode"]; //  eco, quick, manual
  }

  /***************************************************************************
  //  Ende Laderegler auslesen
  ***************************************************************************/
  $FehlermeldungText = "";

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Firmware"] = $aktuelleDaten["Protokoll-Version"];
  $aktuelleDaten["Produkt"] = "Hardy Barth";
  $aktuelleDaten["Wh_Ladevorgang"] = round( $aktuelleDaten["WattstundenProLadung"] );
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);

  Log::write( var_export( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists($basedir."/custom/hardy_barth_math.php" )) {
    include $basedir.'/custom/hardy_barth_math.php'; // Falls etwas neu berechnet werden muss.
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
  if ($aktuelleDaten["Connected"] == 0 and $aktuelleDaten["WattstundenProLadung"] > 0) {
    $aktuelleDaten["WattstundenProLadung"] = 0; // Zähler pro Ladung zurücksetzen
    $rc = file_put_contents( $StatusFile, "0" );
    Log::write( "WattstundenProLadung gelöscht.", "    ", 5 );
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
  $aktuelleDaten["WattstundenGesamtHeute"] = 0; // Dummy

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
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write( "Schleife ".$i." Ausgang...", "   ", 5 );
    break;
  }
  $i++;
} while (($Start + 54) > time( ));
if (1 == 1) {

  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $aktuelleDaten["Solarspannung"] = $aktuelleDaten["Solarspannung1"];
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
fclose( $COM );

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Ladeleistung der Wallbox
//  pro Ladung zu speichern.
*****************************************************************************/
if (file_exists( $StatusFile ) and $aktuelleDaten["Connected"] == 1) {

  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Ladung = Wh
  ***************************************************************************/
  $whProLadung = file_get_contents( $StatusFile );
  $whProLadung = ($whProLadung + ($aktuelleDaten["Wh_Leistung_aktuell"] / 60));
  $rc = file_put_contents( $StatusFile, $whProLadung );
  Log::write( "WattstundenProLadung: ".round( $whProLadung ), "   ", 5 );
}
Ausgang:Log::write( "-------------   Stop   hardy_arth.php   ---------------------- ", "|--", 6 );
return;
?>
