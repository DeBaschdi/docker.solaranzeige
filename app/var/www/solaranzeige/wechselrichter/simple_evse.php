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
//  Es dient dem Auslesen der Simple EVSE WiFi Wallbox über das LAN.
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}

$Tracelevel = 7;  //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$Antwort="";
$Subst = "";
$Start = time();  // Timestamp festhalten
Log::write("-------------   Start  simple_evse.php   _--------------------- ","|--",6);

Log::write("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");



//  Hardware Version ermitteln.
$Teile =  explode(" ",$Platine);
if ($Teile[1] == "Pi") {
  $Version = trim($Teile[2]);
  if ($Teile[3] == "Model") {
    $Version .= trim($Teile[4]);
    if ($Teile[5] == "Plus") {
      $Version .= trim($Teile[5]);
    }
  }
}
Log::write("Hardware Version: ".$Version,"o  ",8);

switch($Version) {
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



$COM1 = fsockopen($WR_IP, $WR_Port, $errno, $errstr, 5);
if (!is_resource($COM1)) {
  Log::write("Kein Kontakt zur Wallbox ".$WR_IP."  Port: ".$WR_Port,"XX ",3);
  Log::write("Exit.... ","XX ",3);
  goto Ausgang;
}



/***************************************************************************
//  Einen Befehl an die Wallbox senden
//
//  
//
***************************************************************************/
if (file_exists("/var/www/pipe/".$GeraeteNummer.".befehl.steuerung")) {

  Log::write("Steuerdatei '".$GeraeteNummer.".befehl.steuerung' vorhanden----","|- ",5);
  $Inhalt = file_get_contents("/var/www/pipe/".$GeraeteNummer.".befehl.steuerung");
  $Befehle = explode("\n",trim($Inhalt));
  Log::write("Befehle: ".print_r($Befehle,1),"|- ",9);

  for ($i = 0; $i < count($Befehle); $i++) {

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
    if (file_exists($basedir."/config/befehle.ini")) {
      Log::write("Die Befehlsliste 'befehle.ini.php' ist vorhanden----","|- ",9);
      $INI_File =  parse_ini_file($basedir."/config/befehle.ini", true);
      $Regler37 = $INI_File["Regler37"];
      Log::write("Befehlsliste: ".print_r($Regler37,1),"|- ",7);


      foreach ($Regler37 as $Template) {
        $Subst = $Befehle[$i];
        $l = strlen($Template);
        for ($p = 1; $p < $l; ++$p) {
          Log::write("Template: ".$Template." Subst: ".$Subst." l: ".$l,"|- ",10);
          if ($Template[$p] == "#") {
            $Subst[$p] = "#";
          }
        }
        if ($Template == $Subst) {
          break;
        }
      }
      if ($Template != $Subst) {
        Log::write("Dieser Befehl ist nicht zugelassen. ".$Befehle[$i],"|o ",3);
        Log::write("Die Verarbeitung der Befehle wird abgebrochen.","|o ",3);
        break;
      }
    }
    else {
      Log::write("Die Befehlsliste 'befehle.ini.php' ist nicht vorhanden----","|- ",3);
      break;
    }


    $Teile = explode("_",$Befehle[$i]);
    $Antwort = "";
    // Hier wird der Befehl gesendet...
    // 

    if ($Teile[0] == "current") {
      $URL  = "setCurrent?".$Teile[0]."=".$Teile[1];
    }
    if ($Teile[0] == "active") {
      $URL  = "setStatus?".$Teile[0]."=".$Teile[1];
    }

    $Daten = Utils::read($WR_IP,$WR_Port,$URL);

    Log::write("Befehl: '".$Teile[0]."=".$Teile[1]."' gesendet!","    ",7);
    Log::write("URL: ".$URL,"    ",8);

  }
  $rc = unlink("/var/www/pipe/".$GeraeteNummer.".befehl.steuerung");
  if ($rc) {
    Log::write("Datei  /../pipe/".$GeraeteNummer.".befehl.steuerung  gelöscht.","    ",9);
  }
  sleep(3);
}
else {
  Log::write("Steuerdatei '".$GeraeteNummer.".befehl.steuerung' nicht vorhanden----","|- ",9);
}



$i = 1;
do {
  Log::write("Die Daten werden ausgelesen...","+  ",9);

  /****************************************************************************
  //  Ab hier wird die Wallbox ausgelesen.
  //
  ****************************************************************************/

  $URL  = "getParameters";

  $Daten = Utils::read($WR_IP,$WR_Port,$URL);

  if ($Daten === false) {
    Log::write("Parameter sind falsch... nochmal lesen.","   ",3);
    if ($i >= 2) {
      Log::write(var_export(Utils::read($WR_IP,$WR_Port,$URL),1),"o=>",9   );
      break;
    }
    $i++;
    continue;
  }

  $aktuelleDaten["Stationsstatus"] = $Daten["list"][0]["vehicleState"];
  if ($Daten["list"][0]["evseState"] == true) {
    $aktuelleDaten["freigeschaltet"] = "1";
  } 
  else {
    $aktuelleDaten["freigeschaltet"] = "0";
  }
  $aktuelleDaten["MaxAmpere"] = $Daten["list"][0]["maxCurrent"];
  $aktuelleDaten["aktuelleStromvorgabe"] = $Daten["list"][0]["actualCurrent"];
  $aktuelleDaten["aktuelleLeistung"] = ($Daten["list"][0]["actualPower"]*1000);
  $aktuelleDaten["Ladedauer"] = round(($Daten["list"][0]["duration"]/60000),0);
  if ($Daten["list"][0]["alwaysActive"] == true) {
    $aktuelleDaten["immerAktiv"] = "1";
  }
  else {
    $aktuelleDaten["immerAktiv"] = "0";
  }
  $aktuelleDaten["letzterUser"] = $Daten["list"][0]["lastActionUser"];
  $aktuelleDaten["letzteUID"] = $Daten["list"][0]["lastActionUID"];
  $aktuelleDaten["Wh_Ladevorgang"] = ($Daten["list"][0]["energy"]*1000);
  $aktuelleDaten["Km_Ladevorgang"] = $Daten["list"][0]["mileage"];
  $aktuelleDaten["Wh_Meter"] = ($Daten["list"][0]["meterReading"]*1000);
  $aktuelleDaten["Strom_R"] = $Daten["list"][0]["currentP1"];
  $aktuelleDaten["Strom_S"] = $Daten["list"][0]["currentP2"];
  $aktuelleDaten["Strom_T"] = $Daten["list"][0]["currentP3"];

  if ($aktuelleDaten["aktuelleLeistung"] == 51430 ) {
    // Das kommt vor...  Firmwarefehler?
    Log::write("Parameter sind falsch... nochmal lesen.","   ",3);
    if ($i >= 2) {
      Log::write(var_export(Utils::read($WR_IP,$WR_Port,$URL),1),"o=>",9   );
      break;
    }
    $i++;
    continue;
  }



  $URL  = "getLog";

  $Daten = Utils::read($WR_IP,$WR_Port,$URL);

  if ($Daten === false) {
    Log::write("Parameter sind falsch... nochmal lesen.","   ",3);
    if ($i >= 2) {
      Log::write(var_export(Utils::read($WR_IP,$WR_Port,$URL),1),"o=>",9   );
      break;
    }
    $i++;
    continue;
  }

  $i = 0;
  do {
    $aktuelleDaten["uid"] = $Daten["list"][$i]["uid"];
    $aktuelleDaten["username"] = $Daten["list"][$i]["username"];
    $aktuelleDaten["energy"] = $Daten["list"][$i]["energy"];
    $aktuelleDaten["timestamp"] = $Daten["list"][$i]["timestamp"];
    $aktuelleDaten["duration"] = $Daten["list"][$i]["duration"];
    $i++;
  }
  while (isset($Daten["list"][$i]));



  /***************************************************************************
  //  Ende Laderegler auslesen
  ***************************************************************************/



  $FehlermeldungText = "";


  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Firmware"] = "1.0";
  $aktuelleDaten["Produkt"] = "SimpleEVSE";
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


  if ($i == 1) 
    Log::write(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists($basedir."/custom/simple_evse_math.php")) {
    include $basedir.'/custom/simple_evse_math.php';  // Falls etwas neu berechnet werden muss.
  }


  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT) {
    Log::write("MQTT Daten zum [ $MQTTBroker ] senden.","   ",1);
    require($basedir."/services/mqtt_senden.php");
  }

  /****************************************************************************
  //  Zeit und Datum
  ****************************************************************************/
  //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
  $aktuelleDaten["Timestamp"] = time();
  $aktuelleDaten["Monat"]     = date("n");
  $aktuelleDaten["Woche"]     = date("W");
  $aktuelleDaten["Wochentag"] = strftime("%A",time());
  $aktuelleDaten["Datum"]     = date("d.m.Y");
  $aktuelleDaten["Uhrzeit"]   = date("H:i:s");



  /****************************************************************************
  //  InfluxDB  Zugangsdaten ...stehen in der user.config.php
  //  falls nicht, sind das hier die default Werte.
  ****************************************************************************/
  $aktuelleDaten["InfluxAdresse"] = $InfluxAdresse;
  $aktuelleDaten["InfluxPort"] = $InfluxPort;
  $aktuelleDaten["InfluxUser"] =  $InfluxUser;
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
      $rc = InfluxDB::influx_remote_test();
      if ($rc) {
        $rc = InfluxDB::influx_remote($aktuelleDaten);
        if ($rc) {
          $RemoteDaten = false;
        }
      }
      else {
        $RemoteDaten = false;
      }
    }
    if ($InfluxDB_local) {
      $rc = InfluxDB::influx_local($aktuelleDaten);
    }
  }
  else {
    $rc = InfluxDB::influx_local($aktuelleDaten);
  }




  if (is_file($basedir."/config/1.user.config.php")) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (7 - (time() - $Start));
    Log::write("Multi-Regler-Ausgang. ".$Zeitspanne,"   ",2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    Log::write("Schleife: ".($i)." Zeitspanne: ".(floor((56 - (time() - $Start))/($Wiederholungen-$i+1))),"   ",9);
    sleep(floor((56 - (time() - $Start))/($Wiederholungen-$i+1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write("Schleife ".$i." Ausgang...","   ",5);
    break;
  }

  $i++;
} while (($Start + 54) > time());


if (1 == 1) {


  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $aktuelleDaten["Solarspannung"] = $aktuelleDaten["Solarspannung1"];
    Log::write("Daten werden zur HomeMatic übertragen...","   ",8);
    require($basedir."/services/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    Log::write("Nachrichten versenden...","   ",8);
    require($basedir."/services/meldungen_senden.php");
  }

  Log::write("OK. Datenübertragung erfolgreich.","   ",7);
}
else {
  Log::write("Keine gültigen Daten empfangen.","!! ",6);
}


fclose($COM1);

Ausgang:

Log::write("-------------   Stop   simple_evse.php   ---------------------- ","|--",6);

return;






?>
