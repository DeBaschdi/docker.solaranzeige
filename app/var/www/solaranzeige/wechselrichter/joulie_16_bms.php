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
//  Es dient dem Auslesen des Joulie-16 BMS über die USB Schnittstelle
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//  Achtung! Der Regler sendet zwischendurch immer wieder asynchrone Daten!
//
*****************************************************************************/
$Tracelevel = 7;  //  1 bis 10  10 = Debug
$Device = "BMS"; // BMS = Batteriemanagementsystem
$Version = ""; 
$Start = time();  // Timestamp festhalten
Log::write("---------   Start  joulie_16_bms.php   ----------------- ","|--",6);

Log::write("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;

setlocale(LC_TIME,"de_DE.utf8");
$RemoteDaten = true;


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
Log::write("Hardware Version: ".$Version,"o  ",9);

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


/************************************************************************************
//  Sollen Befehle an den Wechselrichter gesendet werden?
//
************************************************************************************/
if (file_exists("/var/www/pipe/".$GeraeteNummer.".befehl.steuerung")) {

    Log::write("Steuerdatei '".$GeraeteNummer.".befehl.steuerung' vorhanden----","|- ",5);
    $Inhalt = file_get_contents("/var/www/pipe/".$GeraeteNummer.".befehl.steuerung");
    $Befehle = explode("\n",trim($Inhalt));
    Log::write("Befehle: ".print_r($Befehle,1),"|- ",9);

    for ($i = 0; $i < count($Befehle); $i++) {

      if ($i > 10) {
        //  Es werden nur maximal 10 Befehle pro Datei verarbeitet!
        break;
      }
      /*********************************************************************************
      //  In der Datei "befehle.ini.php" müssen alle gültigen Befehle aufgelistet
      //  werden, die man benutzen möchte.
      //  Achtung! Genau darauf achten, dass der Befehl richtig geschrieben wird,
      //  damit das Gerät keinen Schaden nimmt.
      //  Siehe Dokument:  Befehle_senden.pdf
      *********************************************************************************/
      if (file_exists($basedir."/config/befehle.ini")) {

        Log::write("Die Befehlsliste 'befehle.ini.php' ist vorhanden----","|- ",9);
        $INI_File =  parse_ini_file($basedir."/config/befehle.ini", true);
        $Regler13 = $INI_File["Regler13"];
        Log::write("Befehlsliste: ".print_r($Regler13,1),"|- ",10);
        $Subst = $Befehle[$i];

        foreach ($Regler13 as $Template) {
          $Subst = $Befehle[$i];
          $l = strlen($Template);
          for ($p =1; $p < $l; ++$p) {
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

      $Wert = false;
      $Antwort = "";
      /************************************************************************
      //  Ab hier wird der Befehl gesendet.
      ************************************************************************/
      Log::write("Befehl zur Ausführung: ".strtoupper($Befehle[$i]),"|- ",3);

      if (strtoupper($Befehle[$i]) == "RELAY_ON") {
        $rc = Joulie::joulie_auslesen($USB1,"login LORY27BA");
        if (!empty($rc[0])) {
          Log::write($rc[0],"|- ",3);
        }
        $rc = Joulie::joulie_auslesen($USB1,"stopb");
        Log::write($rc[0],"|- ",3);
        $rc = Joulie::joulie_auslesen($USB1,"unlock MAS184CU");
        Log::write($rc[0],"|- ",3);
        $rc = Joulie::joulie_auslesen($USB1,"relay on");
        Log::write($rc[0],"|- ",3);
        $rc = Joulie::joulie_auslesen($USB1,"startb");
        Log::write($rc[0],"|- ",3);
        sleep(1);
      }


      if (strtoupper($Befehle[$i]) == "RELAY_OFF") {
        $rc = Joulie::joulie_auslesen($USB1,"login LORY27BA");
        if (!empty($rc[0])) {
          Log::write($rc[0],"|- ",3);
        }
        $rc = Joulie::joulie_auslesen($USB1,"stopb");
        Log::write($rc[0],"|- ",3);
        $rc = Joulie::joulie_auslesen($USB1,"unlock MAS184CU");
        Log::write($rc[0],"|- ",3);
        $rc = Joulie::joulie_auslesen($USB1,"relay off");
        Log::write($rc[0],"|- ",3);
        // $rc = Joulie::joulie_auslesen($USB1,"startb");
        // Log::write($rc[0],"|- ",3);
        sleep(1);
      }

      if (strtoupper($Befehle[$i]) == "START_BALANCING") {
        $rc = Joulie::joulie_auslesen($USB1,"login LORY27BA");
        if (!empty($rc[0])) {
          Log::write($rc[0],"|- ",3);
        }
        $rc = Joulie::joulie_auslesen($USB1,"startb");
        Log::write($rc[0],"|- ",3);
        sleep(1);
      }

      if (strtoupper($Befehle[$i]) == "STOP_BALANCING") {
        $rc = Joulie::joulie_auslesen($USB1,"login LORY27BA");
        if (!empty($rc[0])) {
          Log::write($rc[0],"|- ",3);
        }
        $rc = Joulie::joulie_auslesen($USB1,"stopb");
        Log::write($rc[0],"|- ",3);
        sleep(1);
      }

    }
    $rc = unlink("/var/www/pipe/".$GeraeteNummer.".befehl.steuerung");
    if ($rc) {
      Log::write("Datei  /pipe/".$GeraeteNummer.".befehl.steuerung  gelöscht.","    ",8);
    }
}
else {
  Log::write("Steuerdatei '".$GeraeteNummer.".befehl.steuerung' nicht vorhanden----","|- ",9);
}
/*******************************************************************************
//
//  Befehle senden Ende
//
//  Hier beginnt das Auslesen der Daten
//
*******************************************************************************/

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
  //  $aktuelleDaten["Batteriestrom"]
  //  $aktuelleDaten["KilowattstundenGesamt"]
  //  $aktuelleDaten["AmperestundenGesamt"]
  //  $aktuelleDaten["Temperatur"]
  //  $aktuelleDaten["SOC"]
  //  $aktuelleDaten["TTG"]
  //  $aktuelleDaten["Leistung"]
  //
  ****************************************************************************/
  $aktuelleDaten["Firmware"] = "unbekannt";
  $aktuelleDaten["Balancing"] = 1;
  $rc =  Joulie::joulie_auslesen($USB1,"trace");
  if (empty($rc)) {
    $aktuelleDaten["Balancing"] = 0;
    $rc =  Joulie::joulie_auslesen($USB1,"login LORY27BA");
    if (isset($rc[99])) {
      Log::write($rc[3],"+  ",3);
    }
    $rc =  Joulie::joulie_auslesen($USB1,"fwtrace on");
    if (isset($rc[99])) {
      Log::write($rc[3],"+  ",3);
    }
    Log::write($rc[0],"+  ",3);
    $rc =  Joulie::joulie_auslesen($USB1,"outb");
    if (isset($rc[99])) {
      Log::write($rc[3],"+  ",3);
    }
    Log::write($rc[0],"+  ",3);

    $rc = Joulie::joulie_outb($rc[1]);

    // print_r($rc);

  }
  elseif (count($rc) == 38)  {
    // OK. Die richtige Anzahl Werte ist vorhanden.
    $aktuelleDaten["Firmware"] = "1.14";
  }
  elseif (count($rc) == 42)  {
    // OK. Die richtige Anzahl Werte ist vorhanden.
    $aktuelleDaten["Firmware"] = "1.18";
  }
  else {
    // Falsch. Keine gültigen Daten.
    break;
  }
  // Es kommen normale Trace Daten...  In die Datenbank abspeichern..
  Log::write(print_r($rc,1),"   ",9);

  if (isset($rc[99])) {
    Log::write($rc[3],"+  ",3);
  }


  // print_r($rc);


  $aktuelleDaten["Zelle1Volt"] = round(($rc[1]/1000),2);
  $aktuelleDaten["Zelle1Status"] = Joulie::joulie_zahl($rc[2]);
  $aktuelleDaten["Zelle2Volt"] = round(($rc[3]/1000),2);
  $aktuelleDaten["Zelle2Status"] = Joulie::joulie_zahl($rc[4]);
  $aktuelleDaten["Zelle3Volt"] = round(($rc[5]/1000),2);
  $aktuelleDaten["Zelle3Status"] = Joulie::joulie_zahl($rc[6]);
  $aktuelleDaten["Zelle4Volt"] = round(($rc[7]/1000),2);
  $aktuelleDaten["Zelle4Status"] = Joulie::joulie_zahl($rc[8]);
  $aktuelleDaten["Zelle5Volt"] = round(($rc[9]/1000),2);
  $aktuelleDaten["Zelle5Status"] = Joulie::joulie_zahl($rc[10]);
  $aktuelleDaten["Zelle6Volt"] = round(($rc[11]/1000),2);
  $aktuelleDaten["Zelle6Status"] = Joulie::joulie_zahl($rc[12]);
  $aktuelleDaten["Zelle7Volt"] = round(($rc[13]/1000),2);
  $aktuelleDaten["Zelle7Status"] = Joulie::joulie_zahl($rc[14]);
  $aktuelleDaten["Zelle8Volt"] = round(($rc[15]/1000),2);
  $aktuelleDaten["Zelle8Status"] = Joulie::joulie_zahl($rc[16]);
  $aktuelleDaten["Zelle9Volt"] = round(($rc[17]/1000),2);
  $aktuelleDaten["Zelle9Status"] = Joulie::joulie_zahl($rc[18]);
  $aktuelleDaten["Zelle10Volt"] = round(($rc[19]/1000),2);
  $aktuelleDaten["Zelle10Status"] = Joulie::joulie_zahl($rc[20]);
  $aktuelleDaten["Zelle11Volt"] = round(($rc[21]/1000),2);
  $aktuelleDaten["Zelle11Status"] = Joulie::joulie_zahl($rc[22]);
  $aktuelleDaten["Zelle12Volt"] = round(($rc[23]/1000),2);
  $aktuelleDaten["Zelle12Status"] = Joulie::joulie_zahl($rc[24]);
  $aktuelleDaten["Zelle13Volt"] = round(($rc[25]/1000),2);
  $aktuelleDaten["Zelle13Status"] = Joulie::joulie_zahl($rc[26]);
  $aktuelleDaten["Zelle14Volt"] = round(($rc[27]/1000),2);
  $aktuelleDaten["Zelle14Status"] = Joulie::joulie_zahl($rc[28]);
  $aktuelleDaten["Zelle15Volt"] = round(($rc[29]/1000),2);
  $aktuelleDaten["Zelle15Status"] = Joulie::joulie_zahl($rc[30]);
  $aktuelleDaten["Zelle16Volt"] = round(($rc[31]/1000),2);
  $aktuelleDaten["Zelle16Status"] = Joulie::joulie_zahl($rc[32]);
  $aktuelleDaten["Strom"] = round(($rc[33]/1000),2);
  $aktuelleDaten["Kapazitaet"] = round($rc[34],2);
  $aktuelleDaten["SOC"] = round(($rc[35]/1000),1);
  $aktuelleDaten["Spannung"] = round(($rc[36]/1000),2);
  $aktuelleDaten["Fehlercode"] = $rc[37];
  if (count($rc) == 42)  {
    $aktuelleDaten["MaxLadespannung"] = round(($rc[38]/1000),2);
    $aktuelleDaten["MaxLadestrom"] = round(($rc[39]/1000),2);
    $aktuelleDaten["MaxEntladespannung"] = round(($rc[40]/1000),2);
    $aktuelleDaten["MaxEntladestrom"] = round(($rc[41]/1000),2);
  }

  /**************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  **************************************************************************/
  $FehlermeldungText = "";
  //  Dummy Wert.
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;


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
  if ( file_exists($basedir."/custom/joulie_16_bms_math.php")) {
    include $basedir.'/custom/joulie_16_bms_math.php';  // Falls etwas neu berechnet werden muss.
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

  Log::write(print_r($aktuelleDaten,1),"** ",8);



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
    sleep(floor((55 - (time() - $Start))/($Wiederholungen-$i+1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    Log::write("OK. Daten gelesen.","   ",9);
    Log::write("Schleife ".$i." Ausgang...","   ",8);
    break;
  }
  
  $i++;
} while (($Start + 55) > time());




if (isset($aktuelleDaten["Zelle1Status"]) and isset($aktuelleDaten["Regler"])) {


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

Log::write("---------   Stop   joulie_16_bms.php   ----------------- ","|--",6);

return;


?>