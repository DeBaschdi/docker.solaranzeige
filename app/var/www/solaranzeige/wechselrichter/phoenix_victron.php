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

$Tracelevel = 7;  //  1 bis 10  10 = Debug
$Device = "WR"; // WR = Wechselrichter
$Reglermodelle = array("0300","A042","A043","A04C","A053","A054","A055");
$Version = ""; 
$Start = time();  // Timestamp festhalten
Log::write("---------   Start  victron_phoenix.php   ----------------- ","|--",6);

Log::write("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");
$RemoteDaten = true;



/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//
*****************************************************************************/
$StatusFile = $basedir."/database/".$GeraeteNummer.".WhProTag.txt";
if (file_exists($StatusFile)) {
  /***************************************************************************
  //  Daten einlesen ...
  ***************************************************************************/
  $aktuelleDaten["WattstundenGesamtHeute"] = file_get_contents($StatusFile);
  Log::write("WattstundenGesamtHeute: ".round($aktuelleDaten["WattstundenGesamtHeute"],2),"   ",8);
  if (empty($aktuelleDaten["WattstundenGesamtHeute"])){
      $aktuelleDaten["WattstundenGesamtHeute"] = 0;
  }
  if (date("H:i") == "00:00" or date("H:i") == "00:01") {   // Jede Nacht 0 Uhr
    $aktuelleDaten["WattstundenGesamtHeute"] = 0;       //  Tageszähler löschen
    $rc = file_put_contents($StatusFile,"0");
    Log::write("WattstundenGesamtHeute gelöscht.","    ",5);
  }
}
else {
  /***************************************************************************
  //  Inhalt der Status Datei anlegen.
  ***************************************************************************/
  $rc = file_put_contents($StatusFile,"0");
  if ($rc === false) {
    Log::write("Konnte die Datei whProTag_pho.txt nicht anlegen.","XX ",5);
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
  Log::write("USB Port kann nicht geöffnet werden. [1]","XX ",7);
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
  //  $aktuelleDaten["AC_Spannung"]
  //  $aktuelleDaten["AC_Strom"]
  //  $aktuelleDaten["Mode"]
  //  $aktuelleDaten["Warnungen"]
  //
  //
  ****************************************************************************/

  $Befehl = "1";  // Firmware
  $rc = VE::ve_regler_auslesen($USB1,":".$Befehl.Utils::VE_CRC($Befehl));
  if (Utils::VE_CRC(substr(trim($rc),1,-2)) != substr(trim($rc),-2)) {
    //  Hier kann es vorkommen, dass der Regler zwischendurch asynchrone 
    //  Daten sendet.
    Log::write("Firmware".trim($rc),"!!  ",9);
    $m++;
    if ($m > 2) {
      break;
    }
    continue;  // Fehler beim Auslesen aufgetreten. Nochmal...

  }
  $aktuelleDaten = array_merge($aktuelleDaten,VE::ve_ergebnis_auswerten($rc));

  $Befehl = "4";  // Produkt
  $rc = VE::ve_regler_auslesen($USB1,":".$Befehl.Utils::VE_CRC($Befehl));
  if (Utils::VE_CRC(substr(trim($rc),1,-2)) != substr(trim($rc),-2)) {
    Log::write("Produkt".trim($rc),"!!  ",5);
    continue;  // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge($aktuelleDaten,VE::ve_ergebnis_auswerten($rc));


  $Befehl = "7000200";  //  Mode
  $rc = VE::ve_regler_auslesen($USB1,":".$Befehl.Utils::VE_CRC($Befehl));
  if (Utils::VE_CRC(substr(trim($rc),1,-2)) != substr(trim($rc),-2)) {
    Log::write("Optionen".trim($rc),"!!  ",5);
    continue;  // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge($aktuelleDaten,VE::ve_ergebnis_auswerten($rc));
  Log::write("Mode:".trim($rc),"!!  ",9);


  $Befehl = "71C0300";  //  Warnungen
  $rc = VE::ve_regler_auslesen($USB1,":".$Befehl.Utils::VE_CRC($Befehl));
  if (Utils::VE_CRC(substr(trim($rc),1,-2)) != substr(trim($rc),-2)) {
    Log::write("Optionen".trim($rc),"!!  ",5);
    continue;  // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge($aktuelleDaten,VE::ve_ergebnis_auswerten($rc));
  Log::write("Mode:".trim($rc),"!!  ",9);


  $Befehl = "78DED00";  //  Batteriespannung
  $rc = VE::ve_regler_auslesen($USB1,":".$Befehl.Utils::VE_CRC($Befehl));
  if (Utils::VE_CRC(substr(trim($rc),1,-2)) != substr(trim($rc),-2)) {
    Log::write("Batteriespannung".trim($rc),"!!  ",5);
    continue;  // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge($aktuelleDaten,VE::ve_ergebnis_auswerten($rc));
  Log::write("Mode:".trim($rc),"!!  ",9);

  $Befehl = "7002200";  //  AC_Spannung
  $rc = VE::ve_regler_auslesen($USB1,":".$Befehl.Utils::VE_CRC($Befehl));
  if (Utils::VE_CRC(substr(trim($rc),1,-2)) != substr(trim($rc),-2)) {
    Log::write("Batteriespannung".trim($rc),"!!  ",5);
    continue;  // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge($aktuelleDaten,VE::ve_ergebnis_auswerten($rc));
  Log::write("Mode:".trim($rc),"!!  ",9);

  $Befehl = "7012200";  //  AC_Strom
  $rc = VE::ve_regler_auslesen($USB1,":".$Befehl.Utils::VE_CRC($Befehl));
  if (Utils::VE_CRC(substr(trim($rc),1,-2)) != substr(trim($rc),-2)) {
    Log::write("Batteriespannung".trim($rc),"!!  ",5);
    continue;  // Fehler beim Auslesen aufgetreten. Nochmal...
  }
  $aktuelleDaten = array_merge($aktuelleDaten,VE::ve_ergebnis_auswerten($rc));
  Log::write("Mode:".trim($rc),"!!  ",9);


  $aktuelleDaten["AC_Leistung"] = $aktuelleDaten["AC_Ausgangsspannung"]*$aktuelleDaten["AC_Ausgangsstrom"];


  Log::write(var_export($aktuelleDaten,1),"   ",9);



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
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);

  Log::write(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists($basedir."/custom/phoenix_victron_math.php")) {
    include $basedir.'/custom/phoenix_victron_math.php';  // Falls etwas neu berechnet werden muss.
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
  //
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


/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//  Leistung in Watt / 60 Minuten, da 60 mal in der Stunde addiert wird.
*****************************************************************************/
if (file_exists($StatusFile) and isset($aktuelleDaten["Firmware"])) {
  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $whProTag = file_get_contents($StatusFile);

  // aktuellen Wert in die Datei schreiben:
  $whProTag = ($whProTag + ($aktuelleDaten["AC_Leistung"]/60));

  $rc = file_put_contents($StatusFile,$whProTag);
  Log::write("WattstundenGesamtHeute: ".round($whProTag,2),"   ",5);
}

Ausgang:

Log::write("---------   Stop   victron_phoenix.php   ----------------- ","|--",6);

return;



?>