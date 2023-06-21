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
//  Es dient dem Auslesen des go-eCharger über das LAN.
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
//  Ändern falls default in der Wallbox geändert wurde.
$User = "admin";
$KW = "";

//---------------------------------------------------------------------------

$zentralerTimestamp = time();

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
Log::write("-----------------   Start  cFos_wb.php   --------------------- ","|--",6);

Log::write("Zentraler Timestamp: ".$zentralerTimestamp,"   ",7);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;


setlocale(LC_TIME,"de_DE.utf8");


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
if (!is_file($basedir."/config/1.user.config.php")) {
  Log::write("Hardware Version: ".$Version,"o  ",7);

  switch(trim($Teile[4])) {
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
//  Per MQTT  alw = 0
//  Per HTTP  alw_0
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
      $Regler63 = $INI_File["Regler63"];
      Log::write("Befehlsliste: ".print_r($Regler63,1),"|- ",1);


      foreach ($Regler63 as $Template) {
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
    //------------------------------------------------------
    if ($Teile[0] == "start") {
      $URL  = "/cnf?".urlencode("cmd=modbus&device=evse&write=8092&value=2&write=8094&value=1");
      $Daten = Utils::read($WR_IP,$WR_Port,$URL,"",$User.":".$KW);
    }

    elseif ($Teile[0] == "stop") {
      $URL  = "/cnf?".urlencode("cmd=modbus&device=evse&write=8094&value=0");
      $Daten = Utils::read($WR_IP,$WR_Port,$URL,"",$User.":".$KW);
    }

    elseif ($Teile[0] == "amp") {
      $URL  = "/cnf?".urlencode("cmd=modbus&device=evse&write=8093&value=".$Teile[1]);
      $Daten = Utils::read($WR_IP,$WR_Port,$URL,"",$User.":".$KW);
    }
    // -----------------------------------------------------




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




  $URL  = "cnf?cmd=get_dev_info";

  $Daten = Utils::read($WR_IP,$WR_Port,$URL);

  Log::write("GetDevInfo: ".print_r($Daten,1),"   ",8);

  if ($Daten == false) {
    goto Ausgang;
  }

  $aktuelleDaten["Produkt"] = $Daten["params"]["title"];
  $aktuelleDaten["HardwareLimitAmp"] = $Daten["params"]["max_total_power"];
  $aktuelleDaten["KabelLimitAmp"] = $Daten["params"]["max_total_evse_power"];
  $aktuelleDaten["Firmware"] = $Daten["params"]["version"];
  $aktuelleDaten["Seriennummer"] = $Daten["params"]["vsn"]["serialno"];


  for ($i = 0; $i < 10; $i++)  {

    if (isset($Daten["devices"][$i])) {
      if ($Daten["devices"][$i]["dev_id"] == "E1" ) {
        $aktuelleDaten["Beschreibung"] = $Daten["devices"][$i]["desc"];
        $aktuelleDaten["AnzPhasenBitweise"] = $Daten["devices"][$i]["phases"];
        $aktuelleDaten["WB_Status"] = $Daten["devices"][$i]["state"];
        $aktuelleDaten["Leistung"] = $Daten["devices"][$i]["cur_charging_power"];
        $aktuelleDaten["GesamtEnergie"] = $Daten["devices"][$i]["total_energy"];
        $aktuelleDaten["EVSEStatusText"] = $Daten["devices"][$i]["evse"]["cp_state"];
        $aktuelleDaten["Kabelstatus"] = $Daten["devices"][$i]["evse"]["pp_state"];
        $aktuelleDaten["LadungAktiv"] = $Daten["devices"][$i]["evse"]["charging"];
        $aktuelleDaten["AktuelleStromvorgabe"] = $Daten["devices"][$i]["evse"]["current"];
        $aktuelleDaten["EVSEAktiv"] = $Daten["devices"][$i]["evse"]["enabled"];
        if (empty($aktuelleDaten["LadungAktiv"])) {
          $aktuelleDaten["LadungAktiv"] = 0;
        }
      }
    }
  }

  switch ($aktuelleDaten["EVSEStatusText"]) {

    case "Charging":
      $aktuelleDaten["EVSEStatus"] = 3;
    break;

    case "Standby":
      $aktuelleDaten["EVSEStatus"] = 0;
    break;

    case "Vehicle dedected":
      $aktuelleDaten["EVSEStatus"] = 1;
    break;

    default:

    break;

  }

  // Umrechnung von Bitweise auf Anzahl Phasen
  switch($aktuelleDaten["AnzPhasenBitweise"]) {
    case 0:
      $aktuelleDaten["Anz_Phasen"] = 0;
    break;
    case 1:
      $aktuelleDaten["Anz_Phasen"] = 1;
    break;
    case 2:
      $aktuelleDaten["Anz_Phasen"] = 1;
    break;
    case 3:
      $aktuelleDaten["Anz_Phasen"] = 2;
    break;
    case 4:
      $aktuelleDaten["Anz_Phasen"] = 1;
    break;
    case 5:
      $aktuelleDaten["Anz_Phasen"] = 2;
    break;
    case 6:
      $aktuelleDaten["Anz_Phasen"] = 2;
    break;
    case 7:
      $aktuelleDaten["Anz_Phasen"] = 3;
    break;
  }



  $URL  = "/cnf?".urlencode("cmd=modbus&device=evse&read=all");

  $Daten = Utils::read($WR_IP,$WR_Port,$URL,"",$User.":".$KW);

  Log::write("evse: ".print_r($Daten,1),"   ",8);

  if ($Daten == false) {
    // goto Ausgang;
  }


  // 0 = A (warten)  1 = B (Fahrzeug erkannt)  2 = C (laden)  
  // 3 = D (laden mit Kühlung)  4 = E (kein Strom)  5 = F (Fehler)
  $aktuelleDaten["Ladestatus"] = $Daten["8092"];
  $aktuelleDaten["AktuelleStromvorgabe"] = $Daten["8093"];
  $aktuelleDaten["AktuellerLadestrom"] = ($Daten["8095"]/10);
  $aktuelleDaten["LadungEinAus"] = $Daten["8094"];



  $aktuelleDaten["RFID"] = $Daten["8096s"];  // Hex String


  $URL  = "/cnf?".urlencode("cmd=modbus&device=meter1&read=all");

  $Daten = Utils::read($WR_IP,$WR_Port,$URL,"",$User.":".$KW);

  Log::write("meter1: ".print_r($Daten,1),"   ",8);

  if ($Daten == false) {
    // goto Ausgang;
  }


  $aktuelleDaten["Spannung_R"] = $Daten["8045"];
  $aktuelleDaten["Spannung_S"] = $Daten["8046"];
  $aktuelleDaten["Spannung_T"] = $Daten["8047"];

  $aktuelleDaten["Strom_R"] = ($Daten["8064d"]*100);
  $aktuelleDaten["Strom_S"] = ($Daten["8066d"]*100);
  $aktuelleDaten["Strom_T"] = ($Daten["8068d"]*100);

  if (empty($aktuelleDaten["RFID"])) {
    $aktuelleDaten["RFID"] = "";
  }

  if (empty($aktuelleDaten["Spannung_R"])) {
    $aktuelleDaten["Spannung_R"] = 0;
    $aktuelleDaten["Spannung_S"] = 0;
    $aktuelleDaten["Spannung_T"] = 0;
  }

  if (empty($aktuelleDaten["EVSEAktiv"])) {
    $aktuelleDaten["EVSEAktiv"] = 0;
  }
  if (empty($aktuelleDaten["EVSEStatus"])) {
    $aktuelleDaten["EVSEStatus"] = 0;
  }

  /***************************************************************************
  //  Ende Laderegler auslesen
  ***************************************************************************/


  if ($aktuelleDaten["LadungAktiv"] == 0 and $aktuelleDaten["WattstundenProLadung"] > 0) {
    $aktuelleDaten["WattstundenProLadung"] = 0; // Zähler pro Ladung zurücksetzen
    $rc = file_put_contents( $StatusFile, "0" );
    Log::write( "WattstundenProLadung gelöscht.", "    ", 5 );
  }

  $FehlermeldungText = "";


  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Produkt"] = $aktuelleDaten["Beschreibung"];
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;    // Dummy

  Log::write(print_r($aktuelleDaten,1),"*- ",7);

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists($basedir."/custom/cFos_wb_math.php")) {
    include $basedir.'/custom/cFos_wb_math.php';  // Falls etwas neu berechnet werden muss.
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

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Ladeleistung der Wallbox
//  pro Ladung zu speichern.
*****************************************************************************/
if (file_exists( $StatusFile ) and $aktuelleDaten["LadungAktiv"] == 1) {

  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Ladung = Wh
  ***************************************************************************/
  $whProLadung = file_get_contents( $StatusFile );
  $whProLadung = ($whProLadung + ($aktuelleDaten["Leistung"] / 60));
  $rc = file_put_contents( $StatusFile, $whProLadung );
  Log::write( "WattstundenProLadung: ".round( $whProLadung ), "   ", 5 );
}




Ausgang:

Log::write("-----------------   Stop   cFos_wb.php   --------------------- ","|--",6);

return;






?>
