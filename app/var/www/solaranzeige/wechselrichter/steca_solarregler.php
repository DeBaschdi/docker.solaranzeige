<?php
/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2015-2016]  [Ulrich Kunz]
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
//  Es dient dem Auslesen der Steca Regler über die USB Schnittstelle
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
$ReglerTyp = "MPPT6000";
$Device = "LR"; // LR = Laderegler
$path_parts = pathinfo($argv[0]);
$Version = "";
$Start = time();  // Timestamp festhalten
Log::write("------------   Start  steca_solarregler.php   ----------------- ","|--",6);

Log::write("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");
$RemoteDaten = true;
$Internet = false;
$ReglerKommunikation = 'NO';
$FrameWrite = array(
    "StartFrame" => "02",
    "StartHeader" => "01",
    "FrameLaenge" => "0000",
    "Empfaenger"  => "01",
    "Sender"      => "63",
    "CRCHeader"   => "00",
    "D_ServiceID" => "00",
    "D_Priority"  => "03",
    "D_DatenLaenge" => "0000",
    "D_ServiceCode" => "00",
    "D_Daten"       => "",
    "CRCDaten"      => "00",
    "CRCFrame"      => "0000",
    "StopFrame"     => "03"
);


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


//  Nach em Öffnen des Port muss sofort der Regler ausgelesen werden, sonst
//  sendet er asynchrone Daten!
$USB1 = USB::openUSB($USBRegler);
if (!is_resource($USB1)) {
  Log::write("USB Port kann nicht geöffnet werden. [1]","XX ",5);
  Log::write("Exit.... ","XX ",7);
  goto Ausgang;
}

$i = 1;
do {
  Log::write("Die Daten werden ausgelesen...","+  ",9);

  /****************************************************************************
  //  Ab hier wird der Regler ausgelesen.
  //
  //  Ergebniswerte:
  //  $aktuelleDaten["Firmware"]
  //  $aktuelleDaten["Produkt"]
  //  $aktuelleDaten["Batteriespannung"]
  //  $aktuelleDaten["Batterieentladestrom"]
  //  $aktuelleDaten["Batterieladestrom"]
  //  $aktuelleDaten["Ladestatus"]
  //  $aktuelleDaten["Solarstrom"]
  //  $aktuelleDaten["Solarspannung"]
  //  $aktuelleDaten["Solarleistung"]
  //  $aktuelleDaten["KilowattstundenGesamt"]
  //  $aktuelleDaten["KilowattstundenGesamtHeute"]
  //  $aktuelleDaten["KilowattstundenGesamtGestern"]
  //  $aktuelleDaten["maxWattHeute"]
  //  $aktuelleDaten["maxAmpHeute"]
  //  $aktuelleDaten["Temperatur"]
  //  $aktuelleDaten["Optionen"]
  //  $aktuelleDaten["ErrorCodes"]
  //
  ****************************************************************************/
  $DatenArray = array();
  $Anzeige = array();
  $Frame = array();
  $Ergebnis = array();

  /**************************************************************************
  //  Zuerst auslesen um welchen Reglertyp es sich handelt
  //  PPT6000 ?
  **************************************************************************/
  $FrameWrite["D_ServiceID"] = "20";
  $FrameWrite["D_DatenLaenge"] = "0000";


  $Frame = STECA::steca_FrameErstellen($FrameWrite);


  $Ergebnis = STECA::steca_auslesen($USB1,$Frame);
  // Log::write($Ergebnis,10);

  if ($Ergebnis) {
    $Anzeige = STECA::steca_daten($Ergebnis);
    Log::write(var_export($Anzeige,1),"   ",9);
    if ($Anzeige["D_AntwortCode"] == 0) {
      $DatenArray = STECA::steca_entschluesseln($Anzeige["D_ServiceID"],$Anzeige["D_ServiceCode"],$Anzeige["D_Daten"]);
      if ($DatenArray["Valid"] == true) {
        $aktuelleDaten = array_merge($aktuelleDaten,$DatenArray);
      }
      $ReglerModell = explode("\0",Utils::hex2str($DatenArray["Text"]));
      Log::write(var_export($ReglerModell,1),"   ",10);
      Log::write("Regler kann ausgelesen werden.  Typ: ".$ReglerModell[0],"   ",8);
    }
  }
  else {
    $i++;
    usleep(100000);
    $ReglerKommunikation = 'NO';
    continue;
  }


  /**************************************************************************
  //  Datum und Uhrzeit auslesen, falls kein Internetanschluss vorhanden ist.
  **************************************************************************/
  $FrameWrite["D_ServiceID"] = "64";
  $FrameWrite["D_DatenLaenge"] = "0001";
  $FrameWrite["D_ServiceCode"] = "05";
  $Frame = STECA::steca_FrameErstellen($FrameWrite);
  $Ergebnis = STECA::steca_auslesen($USB1,$Frame);
  if ($Ergebnis) {
    $Anzeige = STECA::steca_daten($Ergebnis);
    Log::write("Anzeige: ".var_export($Anzeige,1),"   ",10);
    if ($Anzeige["D_AntwortCode"] == 0) {

      $DatenArray = STECA::steca_entschluesseln($Anzeige["D_ServiceID"],$Anzeige["D_ServiceCode"],$Anzeige["D_Daten"]);
      // $DatenArray["FehlerCode"] =>  0 = Fehler   1 = OK
      if ($DatenArray["Valid"] == true) {
        Log::write("DatenArray: ".var_export($DatenArray,1),"   ",9);

        $aktuelleDaten["Jahr"] = $DatenArray["Jahr"];
        $aktuelleDaten["Monat"] = substr("0".$DatenArray["Monat"],-2);
        $aktuelleDaten["Tag"] = substr("0".$DatenArray["Tag"],-2);
        $aktuelleDaten["Stunden"] = substr("0".$DatenArray["Stunden"],-2);
        $aktuelleDaten["Minuten"] = substr("0".$DatenArray["Minuten"],-2);
        $aktuelleDaten["Sekunden"] = substr("0".$DatenArray["Sekunden"],-2);
      }
    }
  }



  /****************************************************************************
  //  Hier die nächste Abfrage des Reglers.
  // Service ID = 64  und Service Code E4
  ****************************************************************************/

  $FrameWrite["D_ServiceID"] = "64";
  $FrameWrite["D_DatenLaenge"] = "0001";
  $FrameWrite["D_ServiceCode"] = "E4";

  $Frame = STECA::steca_FrameErstellen($FrameWrite);
  $Ergebnis = STECA::steca_auslesen($USB1,$Frame);
  if ($Ergebnis) {
    $Anzeige = STECA::steca_daten($Ergebnis);
    Log::write("1 => ".var_export($Anzeige,1),"   ",10);
    if ($Anzeige["D_AntwortCode"] == 0) {
      Log::write($Anzeige["D_Daten"],"   ",10);
      $DatenArray = STECA::steca_entschluesseln($Anzeige["D_ServiceID"],$Anzeige["D_ServiceCode"],$Anzeige["D_Daten"],trim($ReglerModell[0]));
      // $DatenArray["FehlerCode"] =>  0 = Fehler   1 = OK
      if ($DatenArray["Valid"] == true)
        Log::write("2 => ".var_export($DatenArray,1),"   ",9);
        $aktuelleDaten = array_merge($aktuelleDaten,$DatenArray);
    }
    elseif ($Anzeige["D_AntwortCode"] == 01) {
      Log::write("Service not supportet! Firmware Version?","   ",5);
    }
  }
  else {
    $i++;
    usleep(100000);
    $ReglerKommunikation = 'NO';
    continue;
  }


  if (!isset($aktuelleDaten["Text"])) {
    // Es fehlen Daten.
    break;
  }


  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Produkt"] = $ReglerModell[0];
  $aktuelleDaten["Firmware"] = "1.0";
  $aktuelleDaten["WattstundenGesamtHeute"] = ($aktuelleDaten["EnergieEingangGesamt"] * $aktuelleDaten["Solarspannung1"]);
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


  if ($i == 1) 
    Log::write(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists($basedir."/custom/steca_solarregler_math.php")) {
    include $basedir.'/custom/steca_solarregler_math.php';  // Falls etwas neu berechnet werden muss.
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
  //  Falls der Regler keine interne Uhr hat!
  $aktuelleDaten["Timestamp"] = time();
  $aktuelleDaten["Monat"]     = date("n");
  $aktuelleDaten["Woche"]     = date("W");
  $aktuelleDaten["Wochentag"] = strftime("%A",time());
  $aktuelleDaten["Datum"]     = date("d.m.Y");
  $aktuelleDaten["Uhrzeit"]      = date("H:i:s");



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


  Log::write(var_export($aktuelleDaten,1),"   ",9);



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
  if ($Wiederholungen <= $i) {
    Log::write("OK. Daten gelesen.","   ",9);
    Log::write("Schleife ".$i." Ausgang...","   ",8);
    break;
  }


  $i++;
} while (($Start + 55) > time());


if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {


  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
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


Ausgang:

Log::write("-------------   Stop   steca_solarregler.php   ----------------- ","|--",6);

return;



?>