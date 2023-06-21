<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2020]  [Ulrich Kunz]
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
//  Es dient dem Auslesen des Ahoy DTU über das LAN.
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
$DTU_User = "admin";
$DTU_Kennwort = "openDTU42";
$SpeichernNachts = false;
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "DTU"; // DTU
$aktuelleDaten = array();
$Start = time( ); // Timestamp festhalten
Log::write( "----------------   Start  opendtu.php   --------------------- ", "|--", 6 );
Log::write( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten["Info"]["zentralerTimestamp"] = $zentralerTimestamp;
$Version = "";
//$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
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
Log::write( "Hardware Version: ".$Platine, "o  ", 1 );
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


$COM = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 5 );
if (!is_resource( $COM )) {
  Log::write( "Kein Kontakt zur OpenDTU ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
  Log::write( "Exit.... ", "XX ", 3 );
  goto Ausgang;
}


/************************************************************************************
//  Sollen Befehle an den Wechselrichter gesendet werden?
//
************************************************************************************/
if (file_exists( "/var/www/pipe/".$GeraeteNummer.".befehl.steuerung" )) {
  Log::write( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' vorhanden----", "|- ", 5 );
  $Inhalt = file_get_contents( "/var/www/pipe/".$GeraeteNummer.".befehl.steuerung" );
  $Befehle = explode( "\n", trim( $Inhalt ));
  Log::write( "Befehle: ".print_r( $Befehle, 1 ), "|- ", 9 );
  for ($i = 0; $i < count( $Befehle ); $i++) {
    if ($i > 10) {
      //  Es werden nur maximal 10 Befehle pro Datei verarbeitet!
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
      $Regler89 = $INI_File["Regler89"];
      Log::write( "Befehlsliste: ".print_r( $Regler89, 1 ), "|- ", 10 );
      $Subst = $Befehle[$i];
      foreach ($Regler89 as $Template) {
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
    $http_daten = array();
    /************************************************************************
    //  Ab hier wird der Befehl gesendet.
    //  $Befehle[$i] = aktueller Befehl
    ************************************************************************/

    $Teile = explode( "_", $Befehle[$i] );

    $InverterNummer = ((int)substr($Befehle[$i],1,2)-1);
    $SetWatt = $Teile[1];


    $URL = "api/livedata/status";
    $Daten = Utils::read( $WR_IP, $WR_Port, $URL );
 
    for ($a=0; $a <= $InverterNummer; $a++) {
      $InverterSN = $Daten["inverters"][$a]["serial"];
    }
    if (empty($InverterSN)) {
      Log::write( "Unbekannter Inverter.", "   ", 1 );
    }
    else {
      Log::write( "Befehl geht an Inverter: ".$InverterSN, "   ", 1 );

      if (substr(strtoupper($Befehle[$i]),3,1) == "W"  ) {
        // Limiteingabe in Watt  (Permanent)   0 = absolut in Watt  1 = relativ in Prozent
        $http_daten = array("Benutzer" => $DTU_User.":".$DTU_Kennwort, "URL" => "http://".$WR_IP."/api/limit/config", "Request" => "POST", "Port" => $WR_Port, "Data" => "data={'serial':'".$InverterSN."', 'limit_type':0, 'limit_value':".$SetWatt."}");
      }
      elseif (substr(strtoupper($Befehle[$i]),3,1) == "P" ) {
        // Limiteingabe in Prozent  (Permanent)   0 = absolut in Watt  1 = relativ in Prozent
        $http_daten = array("Benutzer" => $DTU_User.":".$DTU_Kennwort, "URL" => "http://".$WR_IP."/api/limit/config", "Request" => "POST", "Port" => $WR_Port, "Data" => "data={'serial':'".$InverterSN."', 'limit_type':1, 'limit_value':".$SetWatt."}");
      }
      else {
        Log::write('Fehler im Befehl: '.$Befehle[$i] , "   ", 1);
      }
      $Daten = Utils::http_read( $http_daten );
      if ($Daten["type"] == "success") {
        Log::write("Befehl ".$Befehle[$i]." erfolgreich ausgeführt" , "   ", 1);
      }
      else {
        Log::write("Befehl ".$Befehle[$i]." nicht ausgeführt" , "   ", 1);
      }
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



$k = 1;
do {
  Log::write( "Die Daten werden ausgelesen...", "+  ", 9 );

  /****************************************************************************
  //  Ab hier wird die OpenDTU ausgelesen.
  //
  ****************************************************************************/


  $aktuelleDaten["DTU"]["DC_Leistung"] = 0;
  $aktuelleDaten["DTU"]["Produktion"] = 0;


  $URL = "api/system/status";
  $Daten = Utils::read( $WR_IP, $WR_Port, $URL );
  if ($Daten === false) {
    Log::write( "Parameter sind falsch... nochmal lesen.", "   ", 3 );
    if ($i >= 2) {
      Log::write( var_export( Utils::read( $WR_IP, $WR_Port, $URL ), 1 ), "o=>", 9 );
      break;
    }
    $i++;
    continue;
  }


  $aktuelleDaten["Info"]["DeviceName.Text"] = $Daten["hostname"];
  $aktuelleDaten["Info"]["Firmware.Text"] = $Daten["sdkversion"];



  $URL = "api/livedata/status";
  $Daten = Utils::read( $WR_IP, $WR_Port, $URL );
  if ($Daten === false) {
    Log::write( "Parameter sind falsch... nochmal lesen.", "   ", 3 );
    if ($k >= 2) {
      Log::write( var_export( Utils::read( $WR_IP, $WR_Port, $URL ), 1 ), "o=>", 9 );
      break;
    }
    $i++;
    continue;
  }


  $Anz_Inverter = count($Daten["inverters"]);

  for ($i=0; $i < $Anz_Inverter; $i++) {
    $Anz_Channels = count($Daten["inverters"][$i]["DC"]);

    $Measurement = $Daten["inverters"][$i]["serial"];
    Log::write( "Inverter Seriennummer: ".$Measurement, "   ", 6);
    $aktuelleDaten["DTU"]["Anz_Inverter"] = $Anz_Inverter;
    $aktuelleDaten[$Measurement]["limit_absolute"] = $Daten["inverters"][$i]["limit_absolute"];
    $aktuelleDaten[$Measurement]["Seriennummer"] = $Daten["inverters"][$i]["serial"];
    $aktuelleDaten[$Measurement]["Aktiv"] = (int) $Daten["inverters"][$i]["reachable"];
    $aktuelleDaten[$Measurement]["Anz_Channel"] = $Anz_Channels;
    $aktuelleDaten[$Measurement]["LimitPower"] = $Daten["inverters"][$i]["limit_relative"];
    $aktuelleDaten[$Measurement]["Name.Text"] = $Daten["inverters"][$i]["name"];
    $aktuelleDaten[$Measurement]["Status.Text"] = (int)$Daten["inverters"][$i]["producing"];
    $aktuelleDaten[$Measurement]["AC_Spannung"] = round($Daten["inverters"][$i]["AC"][0]["Voltage"]["v"],1);
    $aktuelleDaten[$Measurement]["AC_Strom"] = round($Daten["inverters"][$i]["AC"][0]["Current"]["v"],2);
    $aktuelleDaten[$Measurement]["AC_Leistung"] = round($Daten["inverters"][$i]["AC"][0]["Power"]["v"],2);
    $aktuelleDaten[$Measurement]["AC_Scheinleistung"] = round($Daten["inverters"][$i]["AC"][0]["ReactivePower"]["v"],1);
    $aktuelleDaten[$Measurement]["Frequenz"] = round($Daten["inverters"][$i]["AC"][0]["Frequency"]["v"],1);
    $aktuelleDaten[$Measurement]["PF"] = $Daten["inverters"][$i]["AC"][0]["PowerFactor"]["v"];
    $aktuelleDaten[$Measurement]["Temperatur"] = round($Daten["inverters"][$i]["INV"][0]["Temperature"]["v"],1);
    $aktuelleDaten[$Measurement]["Energie_Inverter_Heute"] = $Daten["inverters"][$i]["AC"][0]["YieldDay"]["v"];
    $aktuelleDaten[$Measurement]["Energie_Inverter_Total"] = round($Daten["inverters"][$i]["AC"][0]["YieldTotal"]["v"],2);;
    $aktuelleDaten[$Measurement]["Effizienz"] = round($Daten["inverters"][$i]["AC"][0]["Efficiency"]["v"],1);


    $aktuelleDaten["DTU"]["AC_Leistung"] = round($Daten["total"]["Power"]["v"],2);
    $aktuelleDaten["DTU"]["Energie_Inverter_Heute"] = $Daten["total"]["YieldDay"]["v"];
    $aktuelleDaten["DTU"]["Energie_Inverter_Total"] = round(($Daten["total"]["YieldTotal"]["v"]*1000),2);
    $aktuelleDaten["DTU"]["Temperatur"] = round($Daten["inverters"][$i]["INV"][0]["Temperature"]["v"],1);
    $aktuelleDaten["DTU"]["Produktion"] = (int)$Daten["inverters"][$i]["producing"];



    for ($j = 0; $j < $Anz_Channels; $j++) {
      if (strlen($Daten["inverters"][$i]["DC"][$j]["name"]["u"]) == 0) {
        $Measurement = "Inv".($i+1)."Port".$j;
      }
      elseif (is_numeric($Daten["inverters"][$i]["DC"][$j]["name"]["u"])) {
        $Measurement = "Inv".($i+1)."Port".$Daten["inverters"][$i]["DC"][$j]["name"]["u"];
      }
      else {
        $Measurement = str_replace(" ", "", $Daten["inverters"][$i]["DC"][$j]["name"]["u"]);
      }
      $aktuelleDaten[$Measurement]["Portnummer"] = (int)($i.$j);
      $aktuelleDaten[$Measurement]["Name.Text"] = $Daten["inverters"][$i]["DC"][$j]["name"]["u"];
      $aktuelleDaten[$Measurement]["PV_Spannung"] = round($Daten["inverters"][$i]["DC"][$j]["Voltage"]["v"],2);
      $aktuelleDaten[$Measurement]["PV_Strom"] = round($Daten["inverters"][$i]["DC"][$j]["Current"]["v"],2);
      $aktuelleDaten[$Measurement]["PV_Leistung"] = round($Daten["inverters"][$i]["DC"][$j]["Power"]["v"],2);
      $aktuelleDaten[$Measurement]["PV_Energie_Heute"] = round($Daten["inverters"][$i]["DC"][$j]["YieldDay"]["v"],2);
      $aktuelleDaten[$Measurement]["PV_Energie_Total"] = round(($Daten["inverters"][$i]["DC"][$j]["YieldTotal"]["v"]*1000),2);
      if ($i == 0) {
        $aktuelleDaten["DTU"]["PV".$j."_Leistung"] = round($Daten["inverters"][$i]["DC"][$j]["Power"]["v"],2);
      }
      $aktuelleDaten["DTU"]["DC_Leistung"] = round(($aktuelleDaten["DTU"]["DC_Leistung"] + $Daten["inverters"][$i]["DC"][$j]["Power"]["v"]), 1 );
      Log::write( "Measurement: ".$Measurement, "   ", 7);
    }

  }


  if ($aktuelleDaten["DTU"]["Produktion"] == 0 and $SpeichernNachts === false) {
    // Ein Inverter ist nicht mehr aktiv
    Log::write( "Keine Produktion." , "   ", 6);
    goto Ausgang;
  }


  Log::write( print_r($Daten,1) , "   ", 10);


  /***************************************************************************
  //  Ende Laderegler auslesen
  ***************************************************************************/
  $FehlermeldungText = "";

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Info"]["Objekt.Text"] = $Objekt;
  $aktuelleDaten["Info"]["Modell.Text"] = "OpenDTU";
  $aktuelleDaten["Info"]["zentralerTimestamp"] = ($aktuelleDaten["Info"]["zentralerTimestamp"] + 10);

  Log::write( var_export( $aktuelleDaten, 1 ), "   ", 10 );


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists($basedir."/custom/opendtu_math.php" )) {
    include $basedir.'/custom/opendtu_math.php'; // Falls etwas neu berechnet werden muss.
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
    Log::write( "Schleife: ".($k)." Zeitspanne: ".(floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $k + 1))), "   ", 9 );
    sleep( floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $k + 1)));
  }
  if ($Wiederholungen <= $k or $k >= 6) {
    Log::write( "Schleife ".$k." Ausgang...", "   ", 5 );
    break;
  }
  $k++;
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

/******/
Ausgang:
/******/


Log::write( "----------------   Stop   opendtu.php   ---------------------- ", "|--", 6 );
return;
?>
