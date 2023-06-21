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
//  Es dient dem Auslesen der Nilan Wärmepumpe CTS602 über eine RS485
//  Schnittstelle mit USB Adapter.
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//  Measurements / Register Gruppen
//  ------------  -----------------
//  Device        000
//  DiscreteIO    100
//  AnalogIO      200
//  Time          300
//  Alarm         400
//  WeekProgram   500
//  User          600
//  Data          700
//  Control      1000
//  Airflow      1100
//  AirTemp      1200
//  AirBypass    1300
//  AirHeat      1400
//  Compressor   1500
//  Defrost      1600
//  HotWater     1700
//  CentHeat     1800
//  AirQual      1900
//  UserPanel    2000
//  PreHeat      2100
//  DPT          2200
//
//
***************************************************************************/
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Start = time( ); // Timestamp festhalten
Log::write( "----------------------   Start  nilan_wp.php   --------------------- ", "|--", 6 );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
//  Hardware Version ermitteln.
//  $Platine = "Raspberry Pi Model B Plus Rev 1.2";
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
  $WR_ID = "01";
}
elseif (strlen( $WR_Adresse ) == 1) {
  $WR_ID = str_pad( dechex( $WR_Adresse ), 2, "0", STR_PAD_LEFT );
}
elseif (strlen( $WR_Adresse ) == 2) {
  $WR_ID = str_pad( dechex( substr( $WR_Adresse, - 2 )), 2, "0", STR_PAD_LEFT );
}
else {
  $WR_ID = dechex( $WR_Adresse );
}
Log::write( "WR_ID: ".$WR_ID, "+  ", 8 );

$USB1 = USB::openUSB( $USBRegler );
if (!is_resource( $USB1 )) {
  Log::write( "USB Port kann nicht geöffnet werden. [1]", "XX ", 7 );
  Log::write( "Exit.... ", "XX ", 7 );
  goto Ausgang;
}


/************************************************************************************
//  Sollen Befehle an die Wärmepumpe gesendet werden?
//  Alle Holding register können geändert werden.
************************************************************************************/
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

    /**************************************************************************
    //  In der Datei "befehle.ini.php" müssen alle gültigen Befehle aufgelistet
    //  werden, die man benutzen möchte.
    //  Achtung! Genau darauf achten, dass der Befehl richtig geschrieben wird,
    //  damit das Gerät keinen Schaden nimmt.
    //  QPI ist nur zum Testen ...
    //  Siehe Dokument:  Befehle_senden.pdf
    **************************************************************************/
    if (file_exists( $basedir."/config/befehle.ini" )) {
      Log::write( "Die Befehlsliste 'befehle.ini.php' ist vorhanden----", "|- ", 9 );
      $INI_File = parse_ini_file( $basedir."/config/befehle.ini", true );
      $Regler90 = $INI_File["Regler90"];
      Log::write( "Befehlsliste: ".print_r( $Regler90, 1 ), "|- ", 10 );
      foreach ($Regler90 as $Template) {
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
    if (strlen( $Befehle[$i] ) > 4) {
      $Teile = explode( "_", $Befehle[$i] );
      $RegWert = str_pad( dechex( $Teile[1] ), 4, "0", STR_PAD_LEFT );
      $Befehl["DeviceID"] = $WR_ID;
      $Befehl["BefehlFunctionCode"] = "10";
      $Befehl["RegisterAddress"] = str_pad( dechex( substr($Teile[0],1)), 4, "0", STR_PAD_LEFT );
      $Befehl["RegisterCount"] = "0001";
      $Befehl["Befehl"] = $RegWert;
      Log::write( "Befehl: ".print_r( $Befehl, 10 ), "    ", 1 );
      $rc = Phocos::phocos_pv18_auslesen( $USB1, $Befehl );
      if ($rc["ok"] == true) {
        $wert = true;
        Log::write( "Befehlsausführung war erfolgreich.", "   ", 7 );
        Log::write( "Register ".hexdec( $Befehl["RegisterAddress"] )." Wert: ".$Befehl["Befehl"], "   ", 2 );
      }
      else {
        $Wert = false;
        Log::write( "Befehlsausführung war nicht erfolgreich! ", "XX ", 2 );
        Log::write( "Register ".hexdec( $Befehl["RegisterAddress"] )." Wert: ".$Befehl["Befehl"], "XX ", 2 );
      }
    }
    else {
      Log::write( "Befehl ungültig: ".$Befehle[$i], "    ", 2 );
    }
  }
  $rc = unlink( "/var/www/pipe/".$GeraeteNummer.".befehl.steuerung" );
  if ($rc) {
    Log::write( "Datei  /pipe/".$GeraeteNummer.".befehl.steuerung  gelöscht.", "    ", 8 );
  }
}
else {
  Log::write( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' nicht vorhanden----", "|- ", 9 );
}


$i = 1;
do {
  Log::write( "Die Daten werden ausgelesen...", "+  ", 9 );

  /****************************************************************************
  //  Ab hier wird die Wärmepumpe ausgelesen.
  //  
  ****************************************************************************/

  $Befehl["DeviceID"] = $WR_ID;               // In HEX
  $Befehl["RegisterAddress"] = dechex(200);   // In Dezimal
  $Befehl["BefehlFunctionCode"] = "04";       // in HEX
  $Befehl["RegisterCount"] = "0013";          // in HEX
  $Befehl["Datentyp"] = "";                // String
  $rc = ModBus::modbus_rtu_auslesen($USB1,$Befehl);
  if ($rc == false) {
    Log::write("Falsches Protokoll ... Exit.... ","!! ",5);
    goto Ausgang;
  }

  $aktuelleDaten["AnalogIO"]["T0_Controler"] = Utils::hexdecs(substr($rc["Wert"],0,4))/100;
  $aktuelleDaten["AnalogIO"]["T1_Intake"] = Utils::hexdecs(substr($rc["Wert"],4,4))/100;
  $aktuelleDaten["AnalogIO"]["T2_Inlet"] = Utils::hexdecs(substr($rc["Wert"],8,4))/100;
  $aktuelleDaten["AnalogIO"]["T3_Exhaust"] = Utils::hexdecs(substr($rc["Wert"],12,4))/100;
  $aktuelleDaten["AnalogIO"]["T4_Outlet"] = Utils::hexdecs(substr($rc["Wert"],16,4))/100;
  $aktuelleDaten["AnalogIO"]["T5_Cond"] = Utils::hexdecs(substr($rc["Wert"],20,4))/100;
  $aktuelleDaten["AnalogIO"]["T6_Evap"] = Utils::hexdecs(substr($rc["Wert"],24,4))/100;
  $aktuelleDaten["AnalogIO"]["T7_Inlet"] = Utils::hexdecs(substr($rc["Wert"],28,4))/100;
  $aktuelleDaten["AnalogIO"]["T8_Outdoor"] = Utils::hexdecs(substr($rc["Wert"],32,4))/100;
  $aktuelleDaten["AnalogIO"]["T9_Heater"] = Utils::hexdecs(substr($rc["Wert"],36,4))/100;
  $aktuelleDaten["AnalogIO"]["T10_Extern"] = Utils::hexdecs(substr($rc["Wert"],40,4))/100;
  $aktuelleDaten["AnalogIO"]["T11_Top"] = Utils::hexdecs(substr($rc["Wert"],44,4))/100;
  $aktuelleDaten["AnalogIO"]["T12_Bottom"] = Utils::hexdecs(substr($rc["Wert"],48,4))/100;
  $aktuelleDaten["AnalogIO"]["T13_Return"] = Utils::hexdecs(substr($rc["Wert"],52,4))/100;
  $aktuelleDaten["AnalogIO"]["T14_Supply"] = Utils::hexdecs(substr($rc["Wert"],56,4))/100;
  $aktuelleDaten["AnalogIO"]["T15_Room"] = Utils::hexdecs(substr($rc["Wert"],60,4))/100;
  $aktuelleDaten["AnalogIO"]["T17_PreHeat"] = Utils::hexdecs(substr($rc["Wert"],68,4))/100;
  $aktuelleDaten["AnalogIO"]["T18_PresPibe"] = Utils::hexdecs(substr($rc["Wert"],72,4))/100;


  $Befehl["RegisterAddress"] = dechex(1000);   // In Dezimal
  $Befehl["BefehlFunctionCode"] = "04";       // in HEX
  $Befehl["RegisterCount"] = "0004";          // in HEX
  $Befehl["Datentyp"] = "";                // String
  $rc = ModBus::modbus_rtu_auslesen($USB1,$Befehl);
  if ($rc == false) {
    Log::write("Falsches Protokoll ... Exit.... ","!! ",5);
    goto Ausgang;
  }

  $aktuelleDaten["Control"]["RunAct"] = hexdec(substr($rc["Wert"],0,4));
  $aktuelleDaten["Control"]["ModeAct"] = hexdec(substr($rc["Wert"],4,4));
  $aktuelleDaten["Control"]["StateDisplay"] = hexdec(substr($rc["Wert"],8,4));
  $aktuelleDaten["Control"]["SecInState"] = hexdec(substr($rc["Wert"],12,4));


  $Befehl["RegisterAddress"] = dechex(1100);   // In Dezimal
  $Befehl["BefehlFunctionCode"] = "04";       // in HEX
  $Befehl["RegisterCount"] = "0005";          // in HEX
  $Befehl["Datentyp"] = "";                // String
  $rc = ModBus::modbus_rtu_auslesen($USB1,$Befehl);
  if ($rc == false) {
    Log::write("Falsches Protokoll ... Exit.... ","!! ",5);
    goto Ausgang;
  }

  $aktuelleDaten["AirFlow"]["VentSet"] = hexdec(substr($rc["Wert"],0,4));
  $aktuelleDaten["AirFlow"]["InletAct"] = hexdec(substr($rc["Wert"],4,4));
  $aktuelleDaten["AirFlow"]["ExhaustAct"] = hexdec(substr($rc["Wert"],8,4));
  $aktuelleDaten["AirFlow"]["SinceFiltDay"] = hexdec(substr($rc["Wert"],12,4));
  $aktuelleDaten["AirFlow"]["ToFiltDay"] = hexdec(substr($rc["Wert"],16,4));



  $Befehl["RegisterAddress"] = dechex(1200);   // In Dezimal
  $Befehl["BefehlFunctionCode"] = "04";       // in HEX
  $Befehl["RegisterCount"] = "0004";          // in HEX
  $Befehl["Datentyp"] = "";                // String
  $rc = ModBus::modbus_rtu_auslesen($USB1,$Befehl);
  if ($rc == false) {
    Log::write("Falsches Protokoll ... Exit.... ","!! ",5);
    goto Ausgang;
  }

  $aktuelleDaten["AirTemp"]["IsSummer"] = hexdec(substr($rc["Wert"],0,4));
  $aktuelleDaten["AirTemp"]["TempInletSet"] = Utils::hexdecs(substr($rc["Wert"],4,4))/100;
  $aktuelleDaten["AirTemp"]["TempControl"] = Utils::hexdecs(substr($rc["Wert"],8,4))/100;
  $aktuelleDaten["AirTemp"]["TempRoom"] = Utils::hexdecs(substr($rc["Wert"],12,4))/100;




  $Befehl["RegisterAddress"] = dechex(1000);   // In Dezimal
  $Befehl["BefehlFunctionCode"] = "03";       // in HEX
  $Befehl["RegisterCount"] = "0008";          // in HEX
  $Befehl["Datentyp"] = "";                // String
  $rc = ModBus::modbus_rtu_auslesen($USB1,$Befehl);
  if ($rc == false) {
    Log::write("Falsches Protokoll ... Exit.... ","!! ",5);
    goto Ausgang;
  }

  $aktuelleDaten["Control"]["Type"] = hexdec(substr($rc["Wert"],0,4));
  $aktuelleDaten["Control"]["RunSet"] = hexdec(substr($rc["Wert"],4,4));
  $aktuelleDaten["Control"]["ModeSet"] = hexdec(substr($rc["Wert"],8,4));
  $aktuelleDaten["Control"]["VentSet"] = hexdec(substr($rc["Wert"],12,4));
  $aktuelleDaten["Control"]["TempSet"] = Utils::hexdecs(substr($rc["Wert"],16,4))/100;
  $aktuelleDaten["Control"]["ServiceMode"] = hexdec(substr($rc["Wert"],20,4));
  $aktuelleDaten["Control"]["ServicePct"] = Utils::hexdecs(substr($rc["Wert"],24,4))/100;
  $aktuelleDaten["Control"]["Preset"] = hexdec(substr($rc["Wert"],28,4));



  Log::write( "Auslesen des Gerätes beendet.", "   ", 8 );


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
  $aktuelleDaten["Info"]["Produkt.Text"] = "NILAN CTS602";
  $aktuelleDaten["zentralerTimestamp"] = ( $aktuelleDaten["zentralerTimestamp"] + 10 );  
  Log::write( print_r( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists($basedir."/custom/nilan_wp_math.php" )) {
    include $basedir.'/custom/nilan_wp_math.php'; // Falls etwas neu berechnet werden muss.
  }

  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT and strtoupper( $MQTTAuswahl ) != "OPENWB") {
    Log::write( "MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1 );
    require($basedir."/services/mqtt_senden.php");
  }


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
    $Zeitspanne = (9 - (time( ) - $Start));
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
    Log::write( "Schleife ".$i." Ausgang...", "   ", 8 );
    break;
  }
  $i++;
} while (($Start + 54) > time( ));
if (isset($aktuelleDaten["AnalogIO"]["T0_Controler"])) {

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

/***********/
Ausgang:

/***********/
Log::write( "----------------------   Stop   nilan_wp.php   --------------------- ", "|--", 6 );
return;
?>