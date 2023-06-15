#!/usr/bin/php
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
//  Es dient dem Auslesen des Daly BMS über eine RS485 zu USB Schnittstelle
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//  Achtung! Der Regler sendet zwischendurch immer wieder asynchrone Daten!
//
*****************************************************************************/
$path_parts = pathinfo( $argv[0] );
$Pfad = $path_parts['dirname'];
if (!is_file( $Pfad."/1.user.config.php" )) {
  // Handelt es sich um ein Multi Regler System?
  require ($Pfad."/user.config.php");
}
require_once ($Pfad."/phpinc/funktionen.inc.php");
if (!isset($funktionen)) {
  $funktionen = new funktionen( );
}
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$aktuelleDaten = array();
$aktuelleDaten["WattstundenGesamtHeute"] = 0; //Dummy
$Start = time( ); // Timestamp festhalten
$funktionen->log_schreiben( "---------   Start  daly_bms.php    ---------------------------- ", "|--", 6 );
$funktionen->log_schreiben( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
//  Hardware Version ermitteln.
$Teile = explode( " ", $Platine );
if ($Teile[1] == "Pi") {
  $funktionen->log_schreiben( "Hardware Version: ".$Platine, "o  ", 7 );
  $Version = trim( $Teile[2] );
  if ($Teile[3] == "Model") {
    $Version .= trim( $Teile[4] );
    if ($Teile[5] == "Plus") {
      $Version .= trim( $Teile[5] );
    }
  }
}
$funktionen->log_schreiben( "Hardware Version: ".$Version, "o  ", 9 );
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
if ($HF2211) {
  // HF2211 WLAN Gateway wird benutzt
  $USB1 = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 5 ); // 5 Sekunden Timeout
  if ($USB1 === false) {
    $funktionen->log_schreiben( "Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
    $funktionen->log_schreiben( "Exit.... ", "XX ", 3 );
    goto Ausgang;
  }
}
else {
  $USB1 = $funktionen->openUSB( $USBRegler );
  if (!is_resource( $USB1 )) {
    $funktionen->log_schreiben( "USB Port kann nicht geöffnet werden. [1]", "XX ", 7 );
    $funktionen->log_schreiben( "Exit.... ", "XX ", 7 );
    goto Ausgang;
  }
}
$funktionen->log_schreiben( "USB Port: ".$USBRegler, "   ", 8 );
$StartByte = "a5";
$DeviceType = "01"; // MPPT Controler
if (1 == 1) {
  $i = 1;
  do {
    $funktionen->log_schreiben( "Die Daten werden ausgelesen...", "+  ", 9 );

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
    // Test $Datenstring = "a58091080000000000000000be";
    //
    **************************************************************************/
    $CommandID = "90"; // Get some Data
    $Datenlaenge = "08"; // in HEX
    $Daten = "0000000000000000";
    $Adresse = $WR_Adresse; // 80 = BMS Adresse
    $Datenstring = $StartByte.$Adresse.$CommandID.$Datenlaenge.$Daten;
    $CRC = substr( dechex( hexdec( $StartByte ) + hexdec( $Adresse ) + hexdec( $CommandID ) + hexdec( $Datenlaenge )), - 2 );
    $rc = $funktionen->eSmart3_auslesen( $USB1, $Datenstring.$CRC );
    if ($rc === false) {
      $funktionen->log_schreiben( "Datenfehler, nochmal... ".$Datenstring, "!! ", 7 );
      continue;
    }
    $aktuelleDaten["Batteriespannung"] = (hexdec( substr( $rc, 0, 4 )) / 10);
    $aktuelleDaten["Spannung_Entladen"] = (hexdec( substr( $rc, 4, 4 )) / 10);
    $aktuelleDaten["Ampere"] = ((hexdec( substr( $rc, 8, 4 )) - 30000) / 10);
    $aktuelleDaten["SOC"] = (hexdec( substr( $rc, 12, 4 )) / 10);
    if ($aktuelleDaten["Ampere"] == - 3000) {
      $aktuelleDaten["Ampere"] = 0;
    }
    if ($aktuelleDaten["Ampere"] < 0) {
      $aktuelleDaten["Ladestrom"] = abs( $aktuelleDaten["Ampere"] );
      $aktuelleDaten["Entladestrom"] = 0;
    }
    else {
      $aktuelleDaten["Entladestrom"] = $aktuelleDaten["Ampere"];
      $aktuelleDaten["Ladestrom"] = 0;
    }
    $CommandID = "91";
    $Datenstring = $StartByte.$Adresse.$CommandID.$Datenlaenge.$Daten;
    $CRC = substr( dechex( hexdec( $StartByte ) + hexdec( $Adresse ) + hexdec( $CommandID ) + hexdec( $Datenlaenge )), - 2 );
    $rc = $funktionen->eSmart3_auslesen( $USB1, $Datenstring.$CRC );
    if ($rc === false) {
      $funktionen->log_schreiben( "Datenfehler, nochmal... ".$Datenstring, "!! ", 7 );
      continue;
    }
    $aktuelleDaten["Max_Spannung"] = (hexdec( substr( $rc, 0, 4 )) / 1000);
    $aktuelleDaten["Max_Spannung_ZellenNr"] = (hexdec( substr( $rc, 4, 2 )));
    $aktuelleDaten["Min_Spannung"] = ((hexdec( substr( $rc, 6, 4 )) / 1000));
    $aktuelleDaten["Min_Spannung_ZellenNr"] = (hexdec( substr( $rc, 10, 2 )));
    $CommandID = "92"; // Get some Data
    $Datenstring = $StartByte.$Adresse.$CommandID.$Datenlaenge.$Daten;
    $CRC = substr( dechex( hexdec( $StartByte ) + hexdec( $Adresse ) + hexdec( $CommandID ) + hexdec( $Datenlaenge )), - 2 );
    $rc = $funktionen->eSmart3_auslesen( $USB1, $Datenstring.$CRC );
    if ($rc === false) {
      $funktionen->log_schreiben( "Datenfehler, nochmal... ".$Datenstring, "!! ", 7 );
      continue;
    }
    $aktuelleDaten["Max_Temperatur"] = (hexdec( substr( $rc, 0, 2 )) - 40);
    $aktuelleDaten["MaxTemp_ZellenNr"] = (hexdec( substr( $rc, 2, 2 )));
    $aktuelleDaten["Min_Temperatur"] = ((hexdec( substr( $rc, 4, 2 )) - 40));
    $aktuelleDaten["MinTemp_ZellenNr"] = (hexdec( substr( $rc, 6, 2 )));
    $CommandID = "93"; // Get some Data
    $Datenstring = $StartByte.$Adresse.$CommandID.$Datenlaenge.$Daten;
    $CRC = substr( dechex( hexdec( $StartByte ) + hexdec( $Adresse ) + hexdec( $CommandID ) + hexdec( $Datenlaenge )), - 2 );
    $rc = $funktionen->eSmart3_auslesen( $USB1, $Datenstring.$CRC );
    if ($rc === false) {
      $funktionen->log_schreiben( "Datenfehler, nochmal... ".$Datenstring, "!! ", 7 );
      continue;
    }
    $aktuelleDaten["Ladung-Entladung"] = (hexdec( substr( $rc, 0, 2 )));
    $aktuelleDaten["Ladung_MOS_Status"] = (hexdec( substr( $rc, 2, 2 )));
    $aktuelleDaten["Entladung_MOS_Status"] = ((hexdec( substr( $rc, 4, 2 ))));
    $aktuelleDaten["BMS_Zyklen"] = (hexdec( substr( $rc, 6, 2 )));
    $aktuelleDaten["Ah_Rest"] = (hexdec( substr( $rc, 8, 16 )) / 1000);
    $CommandID = "94";
    $Datenstring = $StartByte.$Adresse.$CommandID.$Datenlaenge.$Daten;
    $CRC = substr( dechex( hexdec( $StartByte ) + hexdec( $Adresse ) + hexdec( $CommandID ) + hexdec( $Datenlaenge )), - 2 );
    $rc = $funktionen->eSmart3_auslesen( $USB1, $Datenstring.$CRC );
    if ($rc === false) {
      $funktionen->log_schreiben( "Datenfehler, nochmal... ".$Datenstring, "!! ", 7 );
      continue;
    }
    $funktionen->log_schreiben( "Daten: ".$rc, "!! ", 7 );
    $aktuelleDaten["Zellenanzahl"] = (hexdec( substr( $rc, 0, 2 )));
    $aktuelleDaten["Anz_TempSensoren"] = (hexdec( substr( $rc, 2, 2 )));
    $aktuelleDaten["Entladestatus"] = ((hexdec( substr( $rc, 4, 2 ))));
    $aktuelleDaten["Ladestatus"] = (hexdec( substr( $rc, 6, 2 )));
    $aktuelleDaten["Lade/Entlade_Zyklen"] = (hexdec( substr( $rc, 10, 4 )));
    /******/
    // Falls nicht alle Spannungen geliefert werden.
    for ($f=1;$f <= $aktuelleDaten["Zellenanzahl"]; $f++) {
      $aktuelleDaten["Spannung_Zelle".$f] = 0;
    }
    $CommandID = "95";
    $Datenstring = $StartByte.$Adresse.$CommandID.$Datenlaenge.$Daten;
    $CRC = substr( dechex( hexdec( $StartByte ) + hexdec( $Adresse ) + hexdec( $CommandID ) + hexdec( $Datenlaenge )), - 2 );
    $rc = $funktionen->eSmart3_auslesen( $USB1, $Datenstring.$CRC );
    if ($rc === false) {
      $funktionen->log_schreiben( "Datenfehler, nochmal... ".$Datenstring, "!! ", 7 );
      continue;
    }
    $r = 1;
    $funktionen->log_schreiben( "Daten: ".$rc, "!! ", 7 );
    do {
      $Teil = substr( $rc, 0, 16 );
      $rc = substr( $rc, 16 );
      $aktuelleDaten["FrameNr"] = hexdec( substr( $Teil, 0, 2 ));
      $aktuelleDaten["Spannung_Zelle".$r] = (hexdec( substr( $Teil, 2, 4 )) / 1000);
      $r++;
      $aktuelleDaten["Spannung_Zelle".$r] = (hexdec( substr( $Teil, 6, 4 )) / 1000);
      $r++;
      $aktuelleDaten["Spannung_Zelle".$r] = (hexdec( substr( $Teil, 10, 4 )) / 1000);
      $r++;
    } while (strlen( $Teil ) > 0);
    $CommandID = "96";
    $Datenstring = $StartByte.$Adresse.$CommandID.$Datenlaenge.$Daten;
    $CRC = substr( dechex( hexdec( $StartByte ) + hexdec( $Adresse ) + hexdec( $CommandID ) + hexdec( $Datenlaenge )), - 2 );
    $rc = $funktionen->eSmart3_auslesen( $USB1, $Datenstring.$CRC );
    if ($rc === false) {
      $funktionen->log_schreiben( "Datenfehler, nochmal... ".$Datenstring, "!! ", 7 );
      continue;
    }
    $r = 1;
    do {
      $Teil = substr( $rc, 0, 16 );
      $rc = substr( $rc, 16 );
      $aktuelleDaten["FrameNr"] = hexdec( substr( $Teil, 0, 2 ));
      $aktuelleDaten["Temperatur".$r] = (hexdec( substr( $Teil, 2, 2 )) - 40);
      $r++;
      $aktuelleDaten["Temperatur".$r] = (hexdec( substr( $Teil, 4, 2 )) - 40);
      $r++;
      $aktuelleDaten["Temperatur".$r] = (hexdec( substr( $Teil, 6, 2 )) - 40);
      $r++;
      $aktuelleDaten["Temperatur".$r] = (hexdec( substr( $Teil, 8, 2 )) - 40);
      $r++;
      $aktuelleDaten["Temperatur".$r] = (hexdec( substr( $Teil, 10, 2 )) - 40);
      $r++;
      $aktuelleDaten["Temperatur".$r] = (hexdec( substr( $Teil, 12, 2 )) - 40);
      $r++;
      $aktuelleDaten["Temperatur".$r] = (hexdec( substr( $Teil, 14, 2 )) - 40);
      $r++;
    } while (strlen( $rc ) > 0);
    $CommandID = "98";
    $Datenstring = $StartByte.$Adresse.$CommandID.$Datenlaenge.$Daten;
    $CRC = substr( dechex( hexdec( $StartByte ) + hexdec( $Adresse ) + hexdec( $CommandID ) + hexdec( $Datenlaenge )), - 2 );
    $rc = $funktionen->eSmart3_auslesen( $USB1, $Datenstring.$CRC );
    if ($rc === false) {
      $funktionen->log_schreiben( "Datenfehler, nochmal... ".$Datenstring, "!! ", 7 );
      continue;
    }
    $aktuelleDaten["FehlerCode"] = $rc;

    /****************************************************************************
    //  Die Daten werden für die Speicherung vorbereitet.
    ****************************************************************************/
    $aktuelleDaten["Regler"] = $Regler;
    $aktuelleDaten["Objekt"] = $Objekt;
    $aktuelleDaten["Firmware"] = "1.0";
    $aktuelleDaten["Produkt"] = "Daly-BMS";
    $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
    $funktionen->log_schreiben( var_export( $aktuelleDaten, 1 ), "   ", 8 );

    /**************************************************************************
    //  User PHP Script, falls gewünscht oder nötig
    **************************************************************************/
    if (file_exists( "/var/www/html/daly_bms_math.php" )) {
      include 'daly_bms_math.php'; // Falls etwas neu berechnet werden muss.
    }

    /**************************************************************************
    //
    //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
    //  an den mqtt-Broker Mosquitto gesendet.
    **************************************************************************/
    if ($MQTT) {
      $funktionen->log_schreiben( "MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1 );
      require ($Pfad."/mqtt_senden.php");
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
        $rc = $funktionen->influx_remote_test( );
        if ($rc) {
          $rc = $funktionen->influx_remote( $aktuelleDaten );
          if ($rc) {
            $RemoteDaten = false;
          }
        }
        else {
          $RemoteDaten = false;
        }
      }
      if ($InfluxDB_local) {
        $rc = $funktionen->influx_local( $aktuelleDaten );
      }
    }
    elseif ($InfluxDB_local) {   
      $rc = $funktionen->influx_local( $aktuelleDaten );
    }
    if (is_file( $Pfad."/1.user.config.php" )) {
      // Ausgang Multi-Regler-Version
      $Zeitspanne = (7 - (time( ) - $Start));
      $funktionen->log_schreiben( "Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2 );
      if ($Zeitspanne > 0) {
        sleep( $Zeitspanne );
      }
      break;
    }
    else {
      $funktionen->log_schreiben( "Schleife: ".($i)." Zeitspanne: ".(floor( (50 - (time( ) - $Start)) / ($Wiederholungen - $i + 1))), "   ", 8 );
      sleep( floor( (50 - (time( ) - $Start)) / ($Wiederholungen - $i + 1)));
    }
    if ($Wiederholungen <= $i or $i >= 6) {
      $funktionen->log_schreiben( "OK. Daten gelesen.", "   ", 8 );
      $funktionen->log_schreiben( "Schleife ".$i." Ausgang...", "   ", 8 );
      break;
    }
    $i++;
  } while (($Start + 54) > time( ));
}
if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {

  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $funktionen->log_schreiben( "Daten werden zur HomeMatic übertragen...", "   ", 8 );
    require ($Pfad."/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    $funktionen->log_schreiben( "Nachrichten versenden...", "   ", 8 );
    require ($Pfad."/meldungen_senden.php");
  }
  $funktionen->log_schreiben( "OK. Datenübertragung erfolgreich.", "   ", 7 );
}
else {
  $funktionen->log_schreiben( "Keine gültigen Daten empfangen.", "!! ", 6 );
}
Ausgang:$funktionen->log_schreiben( "---------   Stop   daly_bms.php    ---------------------------- ", "|--", 6 );
return;
?>