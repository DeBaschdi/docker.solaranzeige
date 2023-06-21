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
//  Es dient dem Auslesen des Renogy Ladereglers  über die USB
//  Schnittstelle. Das Auslesen wird hier mit einer Schleife durchgeführt.
//  Wie oft die Daten ausgelesen und gespeichert werden steht in der
//  user.config.php
//
//
*****************************************************************************/
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$Version = "";
$Device = "LR"; // LR = Laderegler
$RemoteDaten = true;
$Befehl = array("DeviceID" => "01", "BefehlFunctionCode" => "03", "RegisterAddress" => "0014", "RegisterCount" => "0004");
$Start = time( ); // Timestamp festhalten
Log::write( "---------   Start  rover_regler.php  ------------------------ ", "|--", 6 );
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
//  Nach em Öffnen des Port muss sofort der Regler ausgelesen werden, sonst
//  sendet er asynchrone Daten!
$USB1 = USB::openUSB( $USBRegler );
if (!is_resource( $USB1 )) {
  Log::write( "USB Port kann nicht geöffnet werden. [1]", "XX ", 7 );
  Log::write( "Exit.... ", "XX ", 7 );
  goto Ausgang;
}

/***************************************************************************
//  Einen Befehl an den Laderegler senden
//
//  Per MODBUS Befehl
//
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
    //  curr_6000 ist nur zum Testen ...
    //  Siehe Dokument:  Befehle_senden.pdf
    *********************************************************************************/
    if (file_exists( $basedir."/config/befehle.ini" )) {
      Log::write( "Die Befehlsliste 'befehle.ini.php' ist vorhanden----", "|- ", 9 );
      $INI_File = parse_ini_file( $basedir."/config/befehle.ini", true );
      $Regler14 = $INI_File["Regler14"];
      Log::write( "Befehlsliste: ".print_r( $Regler14, 1 ), "|- ", 9 );
      foreach ($Regler14 as $Template) {
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

    /***********************************************************************
    // Hier wird der Befehl gesendet...
    //
    ***********************************************************************/
    if (strtoupper( $Teile[0] ) == "LOAD") {
      if (strtoupper( $Teile[1] ) == "ON") {
        //  Load einschalten
        //  Load einschalten
        $Befehl = array("DeviceID" => "01", "BefehlFunctionCode" => "06", "RegisterAddress" => "010A", "RegisterCount" => "0001");
        $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
        Log::write( "010A : ".$rc, "   ", 7 );
        if ($rc == false) {
          Log::write( "Fehler! Load Ausgang konnte nicht eingeschaltet werden.", "   ", 1 );
        }
        else {
          Log::write( "Load Ausgang wird eingeschaltet.", "   ", 1 );
        }
      }
      if (strtoupper( $Teile[1] ) == "OFF") {
        //  Load ausschalten
        //  Load ausschalten
        $Befehl = array("DeviceID" => "01", "BefehlFunctionCode" => "06", "RegisterAddress" => "010A", "RegisterCount" => "0000");
        $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
        Log::write( "010A : ".$rc, "   ", 7 );
        if ($rc == false) {
          Log::write( "Fehler! Load Ausgang konnte nicht ausgeschaltet werden.", "   ", 1 );
        }
        else {
          Log::write( "Load Ausgang wird ausgeschaltet.", "   ", 1 );
        }
      }
      if (strtoupper( $Teile[1] ) == "MAN") {
        //  Load Mode auf Manual umschalten
        //  Load Mode auf Manual umschalten
        $Befehl = array("DeviceID" => "01", "BefehlFunctionCode" => "06", "RegisterAddress" => "E01D", "RegisterCount" => "000F");
        $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
        Log::write( "010A : ".$rc, "   ", 7 );
        if ($rc == false) {
          Log::write( "Fehler! Load Ausgang konnte nicht ausgeschaltet werden.", "   ", 1 );
        }
        else {
          Log::write( "Load Ausgang wird ausgeschaltet.", "   ", 1 );
        }
      }
    }
    sleep( 2 );
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
  Log::write( "Die Daten werden ausgelesen...".print_r( $Befehl, 1 ), ">  ", 9 );

  /**************************************************************************
  //  Ab hier wird der Regler ausgelesen.
  //
  //  Ergebniswerte:
  //  $aktuelleDaten["Modell"]
  //  $aktuelleDaten["Firmware"]
  //  $aktuelleDaten["Produkt"]
  //  $aktuelleDaten["Objekt"]
  //  $aktuelleDaten["Datum"]
  //  $aktuelleDaten["Batteriespannung"]
  //  $aktuelleDaten["Solarstrom"]
  //  $aktuelleDaten["Solarspannung"]
  //  $aktuelleDaten["Batterieentladestrom"]
  //  $aktuelleDaten["WattstundenGesamt"]
  //  $aktuelleDaten["Temperatur"]
  //  $aktuelleDaten["BatterieTemperatur"]
  //  $aktuelleDaten["Batterieladestrom"]
  //  $aktuelleDaten["Batterieentladeleistung"]
  //  $aktuelleDaten["BatterieMaxVoltHeute"]
  //  $aktuelleDaten["BatterieMinVoltHeute"]
  //  $aktuelleDaten["BatterieSOC"]
  //  $aktuelleDaten["Solarleistung"]
  //  $aktuelleDaten["SolarstromMaxHeute"]
  //  $aktuelleDaten["Ladestatus"]
  //  $aktuelleDaten["WattstundenGesamtHeute"]
  //  $aktuelleDaten["VerbrauchGesamtHeute"]
  //  $aktuelleDaten["VerbrauchGesamt"]
  //
  **************************************************************************/
  $aktuelleDaten["Ladestatus"] = 0;

  /****************************************************************************
  //  Ab hier wird der Regler ausgelesen.
  //
  ****************************************************************************/
  $Befehl["RegisterAddress"] = "000C";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0008";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "000C : ".$rc, "   ", 7 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Modell"] = trim( Utils::Hex2String( Renogy::renogy_daten( $rc, false, false )));
  //  Firmware Version     Firmware Version     Firmware Version     Firmware
  //  Firmware Version     Firmware Version     Firmware Version     Firmware
  $Befehl["RegisterAddress"] = "0014";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0004";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "0014 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $Version = Renogy::renogy_daten( $rc, false, false );
  $aktuelleDaten["Firmware"] = substr( $Version, 2, 8 );
  //  SOC      SOC      SOC      SOC      SOC      SOC      SOC      SOC
  //  SOC      SOC      SOC      SOC      SOC      SOC      SOC      SOC
  $Befehl["RegisterAddress"] = "0100";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "0100 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["BatterieSOC"] = Renogy::renogy_daten( $rc, true, false );
  //  Batteriespannung        Batteriespannung        Batteriespannung
  //  Batteriespannung        Batteriespannung        Batteriespannung
  $Befehl["RegisterAddress"] = "0101";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "0101 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Batteriespannung"] = (Renogy::renogy_daten( $rc, true, false ) / 10);
  //  Temperatur     Temperatur     Temperatur     Temperatur
  //  Temperatur     Temperatur     Temperatur     Temperatur
  $Befehl["RegisterAddress"] = "0103";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "0103 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Temperatur"] = Utils::hexdecs( substr( Renogy::renogy_daten( $rc, false, false ), 0, 2 ));
  $aktuelleDaten["BatterieTemperatur"] = Utils::hexdecs( substr( Renogy::renogy_daten( $rc, false, false ), 2, 2 ));
  //  Batterieladestrom       Batterieladestrom       Batterieladestrom
  //  Batterieladestrom       Batterieladestrom       Batterieladestrom
  $Befehl["RegisterAddress"] = "0102";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "0102 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Batterieladestrom"] = Renogy::renogy_daten( $rc, true, true );
  //  Batterieentladestrom       Batterieentladestrom       Batterieentladestrom
  //  Batterieentladestrom       Batterieentladestrom       Batterieentladestrom
  $Befehl["RegisterAddress"] = "0105";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "0105 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Batterieentladestrom"] = Renogy::renogy_daten( $rc, true, true );
  //  Batteriespannung max       Batteriespannung max       Batteriespannung max
  //  Batteriespannung max       Batteriespannung max       Batteriespannung max
  $Befehl["RegisterAddress"] = "010C";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "010C : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["BatterieMaxVoltHeute"] = (Renogy::renogy_daten( $rc, true, false ) / 10);
  //  Batteriespannung min       Batteriespannung min       Batteriespannung min
  //  Batteriespannung min       Batteriespannung min       Batteriespannung min
  $Befehl["RegisterAddress"] = "010B";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "010B : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["BatterieMinVoltHeute"] = (Renogy::renogy_daten( $rc, true, false ) / 10);
  //  Batterieentladeleistung     Batterieentladeleistung   Batterieentladeleistung
  //  Batterieentladeleistung     Batterieentladeleistung   Batterieentladeleistung
  $Befehl["RegisterAddress"] = "0106";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "0106 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Batterieentladeleistung"] = Renogy::renogy_daten( $rc, true, false );
  //  Solarspannung        Solarspannung        Solarspannung
  //  Solarspannung        Solarspannung        Solarspannung
  $Befehl["RegisterAddress"] = "0107";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "0107 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Solarspannung"] = (Renogy::renogy_daten( $rc, true, false ) / 10);
  //  Solarstrom        Solarstrom        Solarstrom       Solarstrom
  //  Solarstrom        Solarstrom        Solarstrom       Solarstrom
  $Befehl["RegisterAddress"] = "0108";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "0108 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Solarstrom"] = Renogy::renogy_daten( $rc, true, true );
  //  LOAD Ausgang ON/OFF      LOAD Ausgang ON/OFF      LOAD Ausgang ON/OFF
  //  LOAD Ausgang ON/OFF      LOAD Ausgang ON/OFF      LOAD Ausgang ON/OFF
  $Befehl["RegisterAddress"] = "010A";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "010A : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["LOAD_Ausgang"] = Renogy::renogy_daten( $rc, true, false );
  if ($aktuelleDaten["LOAD_Ausgang"] == 0) {
    Log::write( "Load Ausgang ist ausgeschaltet.", "   ", 7 );
  }
  else {
    Log::write( "Load Ausgang ist eingeschaltet.", "   ", 7 );
  }
  //  Solarstrom max     Solarstrom max    Solarstrom max
  //  Solarstrom max     Solarstrom max    Solarstrom max
  $Befehl["RegisterAddress"] = "010D";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "010D : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["SolarstromMaxHeute"] = Renogy::renogy_daten( $rc, true, true );
  //  Solarleistung        Solarleistung       Solarleistung
  //  Solarleistung        Solarleistung       Solarleistung
  $Befehl["RegisterAddress"] = "0109";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "0109 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Solarleistung"] = Renogy::renogy_daten( $rc, true, false );
  if ($aktuelleDaten["Solarleistung"] > 100000) {
    Log::write( "Fehler!: ".$rc, "   ", 7 );
    goto Ausgang;
  }
  //  Ladestatus      Ladestatus      Ladestatus     Ladestatus
  //  Ladestatus      Ladestatus      Ladestatus     Ladestatus
  $Befehl["RegisterAddress"] = "0120";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "0120 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Ladestatus"] = substr( Renogy::renogy_daten( $rc, false, false ), 2, 2 );
  if ($aktuelleDaten["Ladestatus"] == "") {
    Log::write( "Fehler!: ".$rc, "   ", 7 );
    goto Ausgang;
  }
  //  Fehlercode     Fehlercode     Fehlercode     Fehlercode
  //  Fehlercode     Fehlercode     Fehlercode     Fehlercode
  $Befehl["RegisterAddress"] = "0121";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "0121 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["Fehlercode"] = substr( Renogy::renogy_daten( $rc, false, false ), 2, 2 );
  //  Wattstunden Gesamt Heute   Wattstunden Gesamt Heute   Wattstunden
  //  Wattstunden Gesamt Heute   Wattstunden Gesamt Heute   Wattstunden
  $Befehl["RegisterAddress"] = "0113";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "0113 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["WattstundenGesamtHeute"] = Renogy::renogy_daten( $rc, true, false );
  //  Wattstunden Gesamt   Wattstunden Gesamt    Wattstunden Gesamt
  //  Wattstunden Gesamt   Wattstunden Gesamt    Wattstunden Gesamt
  $Befehl["RegisterAddress"] = "011C";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "011C : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["WattstundenGesamt"] = Renogy::renogy_daten( $rc, true, false );
  //  Verbrauch Gesamt Heute   Verbrauch Gesamt Heute   Verbrauch Gesamt
  //  Verbrauch Gesamt Heute   Verbrauch Gesamt Heute   Verbrauch Gesamt
  $Befehl["RegisterAddress"] = "0114";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "0113 : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["VerbrauchGesamtHeute"] = Renogy::renogy_daten( $rc, true, false );
  //  Verbrauch Gesamt   Verbrauch Gesamt    Verbrauch Gesamt
  //  Verbrauch Gesamt   Verbrauch Gesamt    Verbrauch Gesamt
  $Befehl["RegisterAddress"] = "011E";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0002";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "011C : ".$rc, "   ", 8 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["VerbrauchGesamt"] = Renogy::renogy_daten( $rc, true, false );
  //  Working Mode      Working Mode      Working Mode
  //  Working Mode      Working Mode      Working Mode
  $Befehl["RegisterAddress"] = "E01D";
  $Befehl["BefehlFunctionCode"] = "03";
  $Befehl["RegisterCount"] = "0001";
  $rc = Renogy::renogy_auslesen( $USB1, $Befehl );
  Log::write( "E01D : ".$rc, "   ", 7 );
  if ($rc === false) {
    continue;
  }
  $aktuelleDaten["WorkingMode"] = Renogy::renogy_daten( $rc, true, false );

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Produkt"] = "Rover/Toyo/SRNE";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);

  /**************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  **************************************************************************/
  $FehlermeldungText = "";
  Log::write( var_export( $aktuelleDaten, 1 ), "   ", 8 );

  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists($basedir."/custom/rover_renogy_math.php" )) {
    include $basedir.'/custom/rover_renogy_math.php'; // Falls etwas neu berechnet werden muss.
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
  //  Zeit und Datum
  ****************************************************************************/
  //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
  $aktuelleDaten["Timestamp"] = time( );
  $aktuelleDaten["Monat"] = date( "n" );
  $aktuelleDaten["Woche"] = date( "W" );
  $aktuelleDaten["Wochentag"] = strftime( "%A", time( ));
  $aktuelleDaten["Datum"] = date( "d.m.Y" );
  $aktuelleDaten["Uhrzeit"] = date( "H:i:s" );

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
    Log::write( "OK. Daten gelesen.", "   ", 9 );
    Log::write( "Schleife ".$i." Ausgang...", "   ", 8 );
    break;
  }
  $i++;
} while (($Start + 56) > time( ));
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
Ausgang:Log::write( "---------   Stop   rover_regler.php    ---------------------- ", "|--", 6 );
return;
?>