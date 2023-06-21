<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class STECA {

  /****************************************************************************
  //  STECA      STECA      STECA      STECA      STECA      STECA      STECA
  //
  //  Funktionen für die STECA Solarregler
  //
  ****************************************************************************/
  public static function steca_daten($HEXDaten) {
    $Daten = array();
    $Daten["StartFrame"] = substr($HEXDaten, 0, 2);
    $Daten["StartHeader"] = substr($HEXDaten, 2, 2);
    $Daten["FrameLaenge"] = substr($HEXDaten, 4, 4);
    $Daten["Empfaenger"] = substr($HEXDaten, 8, 2);
    $Daten["Sender"] = substr($HEXDaten, 10, 2);
    $Daten["CRCHeader"] = substr($HEXDaten, 12, 2);
    $Daten["D_ServiceID"] = substr($HEXDaten, 14, 2);
    $Daten["D_AntwortCode"] = substr($HEXDaten, 16, 2);
    if (hexdec($Daten["FrameLaenge"]) > 12) {
      $Daten["D_DatenLaenge"] = substr($HEXDaten, 18, 4);
      $Daten["D_ServiceCode"] = substr($HEXDaten, 22, 2);
      $Daten["D_Daten"] = substr($HEXDaten, 24, (hexdec($Daten["D_DatenLaenge"]) * 2) - 2);
    }
    $Daten["CRCDaten"] = substr($HEXDaten, - 8, 2);
    $Daten["CRCFrame"] = substr($HEXDaten, - 6, 4);
    $Daten["StopFrame"] = substr($HEXDaten, - 2);
    return $Daten;
  }

  /*****************************************************************************
  //  Hier wird das Datenfeld entschlüsselt. Dazu wird die D_ServiceID,
  //  der D_ServiceCode und die eigentlichen D_Daten benötigt.
  //
  *****************************************************************************/
  public static function steca_FrameErstellen($FrameDaten) {
    $Frame = "";
    $Frame .= $FrameDaten["StartFrame"];
    $Frame .= $FrameDaten["StartHeader"];
    $Frame .= $FrameDaten["FrameLaenge"];
    $Frame .= $FrameDaten["Empfaenger"];
    $Frame .= $FrameDaten["Sender"];
    $Frame .= $FrameDaten["CRCHeader"];
    $Frame .= $FrameDaten["D_ServiceID"];
    $Frame .= $FrameDaten["D_Priority"];
    if ($FrameDaten["D_DatenLaenge"] > "0000") {
      $Frame .= $FrameDaten["D_DatenLaenge"];
      $Frame .= $FrameDaten["D_ServiceCode"];
      $Frame .= $FrameDaten["D_Daten"];
      $Frame .= $FrameDaten["CRCDaten"];
    }
    $Frame .= $FrameDaten["StopFrame"];
    $FrameDaten["FrameLaenge"] = substr("0000".dechex(strlen($Frame.$FrameDaten["CRCFrame"]) / 2), - 4);
    $FrameDaten["CRCHeader"] = Utils::crc8($FrameDaten["StartFrame"].$FrameDaten["StartHeader"].$FrameDaten["FrameLaenge"].$FrameDaten["Empfaenger"].$FrameDaten["Sender"]);
    $FrameDaten["CRCDaten"] = Utils::crc8Data($FrameDaten["D_ServiceCode"].$FrameDaten["D_Daten"]);
    $Frame = "";
    $Frame .= $FrameDaten["StartFrame"];
    $Frame .= $FrameDaten["StartHeader"];
    $Frame .= $FrameDaten["FrameLaenge"];
    $Frame .= $FrameDaten["Empfaenger"];
    $Frame .= $FrameDaten["Sender"];
    $Frame .= $FrameDaten["CRCHeader"];
    $Frame .= $FrameDaten["D_ServiceID"];
    $Frame .= $FrameDaten["D_Priority"];
    if ($FrameDaten["D_DatenLaenge"] > "0000") {
      $Frame .= $FrameDaten["D_DatenLaenge"];
      $Frame .= $FrameDaten["D_ServiceCode"];
      $Frame .= $FrameDaten["D_Daten"];
      $Frame .= $FrameDaten["CRCDaten"];
    }
    $Frame .= $FrameDaten["StopFrame"];
    $FrameDaten["CRCFrame"] = Utils::crc16_steca($Frame);
    $Frame = "";
    $Frame .= $FrameDaten["StartFrame"];
    $Frame .= $FrameDaten["StartHeader"];
    $Frame .= $FrameDaten["FrameLaenge"];
    $Frame .= $FrameDaten["Empfaenger"];
    $Frame .= $FrameDaten["Sender"];
    $Frame .= $FrameDaten["CRCHeader"];
    $Frame .= $FrameDaten["D_ServiceID"];
    $Frame .= $FrameDaten["D_Priority"];
    if ($FrameDaten["D_DatenLaenge"] > "0000") {
      $Frame .= $FrameDaten["D_DatenLaenge"];
      $Frame .= $FrameDaten["D_ServiceCode"];
      $Frame .= $FrameDaten["D_Daten"];
      $Frame .= $FrameDaten["CRCDaten"];
    }
    $Frame .= $FrameDaten["CRCFrame"];
    $Frame .= $FrameDaten["StopFrame"];
    return $Frame;
  }

  /*****************************************************************************
  //  Hier wird das Datenfeld entschlüsselt. Dazu wird die D_ServiceID,
  //  der D_ServiceCode und die eigentlichen D_Daten benötigt.
  //
  *****************************************************************************/
  public static function steca_entschluesseln($ServiceID, $ServiceCode, $StecaDaten, $ReglerModell = "MPPT6000") {
    $Daten = array();
    $Daten["Valid"] = false;
    switch ($ServiceID) {
      case "21":
        $Daten["Valid"] = true;
        $Daten["Text"] = $ServiceCode.$StecaDaten;
        break;
      case "65":
        //  Datum und Uhrzeit entschlüsseln
        if ($ServiceCode == "05") {
          $Daten["Jahr"] = "20".hexdec(substr($StecaDaten, 0, 2));
          $Daten["Monat"] = hexdec(substr($StecaDaten, 2, 2));
          $Daten["Tag"] = hexdec(substr($StecaDaten, 4, 2));
          $Daten["Stunden"] = hexdec(substr($StecaDaten, 6, 2));
          $Daten["Minuten"] = hexdec(substr($StecaDaten, 8, 2));
          $Daten["Sekunden"] = hexdec(substr($StecaDaten, 10, 2));

          /*******************************************************************
          0 = Time invalid
          1 = Time valid
          2 = Time set in last 24 hours
          *******************************************************************/
          $Daten["Valid"] = hexdec(substr($StecaDaten, 12, 2));
        }
        elseif ($ServiceCode == "e4") {
          // $Daten["Text_Daten"] = $StecaDaten;
          $Daten["Solarstrom"] = (hexdec(substr($StecaDaten, 6, 2).substr($StecaDaten, 4, 2).substr($StecaDaten, 2, 2).substr($StecaDaten, 0, 2)) / 1000);
          $Daten["Batteriespannung"] = (hexdec(substr($StecaDaten, 14, 2).substr($StecaDaten, 12, 2).substr($StecaDaten, 10, 2).substr($StecaDaten, 8, 2)) / 1000);
          $Daten["Batterieladestrom"] = (hexdec(substr($StecaDaten, 22, 2).substr($StecaDaten, 20, 2).substr($StecaDaten, 18, 2).substr($StecaDaten, 16, 2)) / 1000);
          $Daten["SOCProzent"] = hexdec(substr($StecaDaten, 24, 2));
          if (substr($ReglerModell, 0, 10) == "Tarom 4545") {
            $Daten["Batterieentladestrom"] = (hexdec(substr($StecaDaten, 32, 2).substr($StecaDaten, 30, 2).substr($StecaDaten, 28, 2).substr($StecaDaten, 26, 2)) / 1000);
            $Daten["Solarspannung1"] = (hexdec(substr($StecaDaten, 40, 2).substr($StecaDaten, 38, 2).substr($StecaDaten, 36, 2).substr($StecaDaten, 34, 2)) / 1000);
            $Daten["Betriebsstunden"] = (hexdec(substr($StecaDaten, 48, 2).substr($StecaDaten, 46, 2).substr($StecaDaten, 44, 2).substr($StecaDaten, 42, 2)));
            $Daten["Temperatur"] = (hexdec(substr($StecaDaten, 56, 2).substr($StecaDaten, 54, 2).substr($StecaDaten, 52, 2).substr($StecaDaten, 50, 2)) / 1000);
            $Daten["BatteriestromGesamt"] = (Utils::hexdecs(substr($StecaDaten, 64, 2).substr($StecaDaten, 62, 2).substr($StecaDaten, 60, 2).substr($StecaDaten, 58, 2)) / 1000);
            $Daten["GesamtEntladestrom"] = (hexdec(substr($StecaDaten, 72, 2).substr($StecaDaten, 70, 2).substr($StecaDaten, 68, 2).substr($StecaDaten, 66, 2)) / 1000);
            $Daten["GesamtLadestrom"] = (hexdec(substr($StecaDaten, 80, 2).substr($StecaDaten, 78, 2).substr($StecaDaten, 76, 2).substr($StecaDaten, 74, 2)) / 1000);
            $Daten["Fehlerstatus"] = hexdec(substr($StecaDaten, 82, 2));
            $Daten["Lademodus"] = substr($StecaDaten, 84, 2);
            $Daten["StatusLast"] = substr($StecaDaten, 86, 2);
            $Daten["Relais1"] = substr($StecaDaten, 88, 2);
            $Daten["Relais2"] = substr($StecaDaten, 90, 2);
            $Daten["EnergieEingangTag"] = hexdec(substr($StecaDaten, 106, 2).substr($StecaDaten, 104, 2).substr($StecaDaten, 102, 2).substr($StecaDaten, 100, 2).substr($StecaDaten, 98, 2).substr($StecaDaten, 96, 2).substr($StecaDaten, 94, 2).substr($StecaDaten, 92, 2));
            $Daten["EnergieEingangGesamt"] = hexdec(substr($StecaDaten, 122, 2).substr($StecaDaten, 120, 2).substr($StecaDaten, 118, 2).substr($StecaDaten, 116, 2).substr($StecaDaten, 114, 2).substr($StecaDaten, 112, 2).substr($StecaDaten, 110, 2).substr($StecaDaten, 108, 2));
            $Daten["EnergieAusgangTag"] = hexdec(substr($StecaDaten, 138, 2).substr($StecaDaten, 136, 2).substr($StecaDaten, 134, 2).substr($StecaDaten, 132, 2).substr($StecaDaten, 130, 2).substr($StecaDaten, 128, 2).substr($StecaDaten, 126, 2).substr($StecaDaten, 124, 2));
            $Daten["EnergieAusgangGesamt"] = hexdec(substr($StecaDaten, 154, 2).substr($StecaDaten, 152, 2).substr($StecaDaten, 150, 2).substr($StecaDaten, 148, 2).substr($StecaDaten, 146, 2).substr($StecaDaten, 144, 2).substr($StecaDaten, 142, 2).substr($StecaDaten, 140, 2));
            $Daten["Derating"] = substr($StecaDaten, 156, 2);
            $Daten["TemperaturPowerunit1"] = (hexdec(substr($StecaDaten, 164, 2).substr($StecaDaten, 162, 2).substr($StecaDaten, 160, 2).substr($StecaDaten, 158, 2)) / 1000);
            $Daten["TemperaturPowerunit2"] = (hexdec(substr($StecaDaten, 172, 2).substr($StecaDaten, 170, 2).substr($StecaDaten, 168, 2).substr($StecaDaten, 166, 2)) / 1000);
            $Daten["TagNacht"] = substr($StecaDaten, 174, 2);
            $Daten["Solarspannung2"] = "0.0";
          }
          else {
            $Daten["SOH"] = hexdec(substr($StecaDaten, 28, 2).substr($StecaDaten, 26, 2));
            $Daten["Solarspannung1"] = (hexdec(substr($StecaDaten, 36, 2).substr($StecaDaten, 34, 2).substr($StecaDaten, 32, 2).substr($StecaDaten, 30, 2)) / 1000);
            $Daten["Solarspannung2"] = (hexdec(substr($StecaDaten, 44, 2).substr($StecaDaten, 42, 2).substr($StecaDaten, 40, 2).substr($StecaDaten, 38, 2)) / 1000);
            $Daten["SolarleistungGesamt"] = (hexdec(substr($StecaDaten, 52, 2).substr($StecaDaten, 50, 2).substr($StecaDaten, 48, 2).substr($StecaDaten, 46, 2)) / 1000);
            $Daten["Solarleistung1"] = (hexdec(substr($StecaDaten, 60, 2).substr($StecaDaten, 58, 2).substr($StecaDaten, 56, 2).substr($StecaDaten, 54, 2)) / 1000);
            $Daten["Solarleistung2"] = (hexdec(substr($StecaDaten, 68, 2).substr($StecaDaten, 66, 2).substr($StecaDaten, 64, 2).substr($StecaDaten, 62, 2)) / 1000);
            $Daten["Betriebsstunden"] = hexdec(substr($StecaDaten, 76, 2).substr($StecaDaten, 74, 2).substr($StecaDaten, 72, 2).substr($StecaDaten, 70, 2));
            $Daten["Temperatur"] = (hexdec(substr($StecaDaten, 84, 2).substr($StecaDaten, 82, 2).substr($StecaDaten, 80, 2).substr($StecaDaten, 78, 2)) / 1000);
            $Daten["Gesamtstrom"] = (hexdec(substr($StecaDaten, 92, 2).substr($StecaDaten, 90, 2).substr($StecaDaten, 88, 2).substr($StecaDaten, 86, 2)) / 1000);
            $Daten["Entladestrom"] = (hexdec(substr($StecaDaten, 100, 2).substr($StecaDaten, 98, 2).substr($StecaDaten, 96, 2).substr($StecaDaten, 94, 2)) / 1000);
            $Daten["Batterieleistung"] = (hexdec(substr($StecaDaten, 108, 2).substr($StecaDaten, 106, 2).substr($StecaDaten, 104, 2).substr($StecaDaten, 102, 2)) / 1000);
            $Daten["Batterieladestrom"] = (hexdec(substr($StecaDaten, 116, 2).substr($StecaDaten, 114, 2).substr($StecaDaten, 112, 2).substr($StecaDaten, 110, 2)) / 1000);
            $Daten["Fehler"] = substr($StecaDaten, 118, 2);
            $Daten["Lademodus"] = substr($StecaDaten, 120, 2);
            $Daten["Relais1"] = substr($StecaDaten, 122, 2);
            $Daten["Relais2"] = substr($StecaDaten, 124, 2);
            $Daten["Relais3"] = substr($StecaDaten, 126, 2);
            $Daten["EnergieEingangTag"] = hexdec(substr($StecaDaten, 134, 2).substr($StecaDaten, 132, 2).substr($StecaDaten, 130, 2).substr($StecaDaten, 128, 2));
            $Daten["EnergieEingangGesamt"] = hexdec(substr($StecaDaten, 142, 2).substr($StecaDaten, 140, 2).substr($StecaDaten, 138, 2).substr($StecaDaten, 136, 2));
            $Daten["EnergieAusgangTag"] = hexdec(substr($StecaDaten, 150, 2).substr($StecaDaten, 148, 2).substr($StecaDaten, 146, 2).substr($StecaDaten, 144, 2));
            $Daten["EnergieAusgangGesamt"] = hexdec(substr($StecaDaten, 158, 2).substr($StecaDaten, 156, 2).substr($StecaDaten, 154, 2).substr($StecaDaten, 152, 2));
            $Daten["Derating"] = substr($StecaDaten, 160, 2);
            $Daten["TemperaturPowerunit1"] = (hexdec(substr($StecaDaten, 168, 2).substr($StecaDaten, 166, 2).substr($StecaDaten, 164, 2).substr($StecaDaten, 162, 2)) / 1000);
            $Daten["TemperaturPowerunit2"] = (hexdec(substr($StecaDaten, 176, 2).substr($StecaDaten, 174, 2).substr($StecaDaten, 172, 2).substr($StecaDaten, 170, 2)) / 1000);
            $Daten["TagNacht"] = substr($StecaDaten, 178, 2);
            $Daten["AuxIO"] = substr($StecaDaten, 180, 2);
          }
          $Daten["Valid"] = true;
        }
        break;
      case "69":
        break;
    }
    return $Daten;
  }

  /****************************************************************************
  //  Hier wird der RS845 Bus ausgelesen. Diese Routine ist sehr zeitkritisch
  //  Bitte die usleep() Funktionen nicht verändern, zumindest erst nach
  //  längeren Testreihen.
  //  Bei schnelleren Raspberry Pi Rechnern müssen die zeitlichen Funktionen
  //  eventuell angepasst werden.
  ****************************************************************************/
  public static function steca_auslesen($USB, $Befehl) {
    stream_set_blocking($USB, false);
    // Befehl in HEX!
    $buffer = "";
    $Befehl = strtolower($Befehl);
    $Laenge = strlen($Befehl);
    // echo "Befehl: ".$Befehl." Länge: ".$Laenge."\n";
    fputs($USB, hex2bin($Befehl));
    //  Es wird ein Echo erwartet. Das wird in der 1. Runde
    //  erwartet. Das Echo muss im Adapter eingeschaltet sein.
    for ($i = 1; $i < 50; $i++) {
      $buffer .= fgets($USB, 64);
      usleep(1800); // 1800 ist ein guter Wert 30.3.2016
      if (substr(bin2hex($buffer), 0, 2) == "00") {
        $buffer = substr($buffer, 1);
        continue;
      }
      if (substr(bin2hex($buffer), 0, $Laenge) == $Befehl) {
        // Echo erhalten, Runde verlassen... (8 - 10 Runden)
        // echo "Echo erhalten: ".$i."\n";
        break;
      }
      // if (!empty($buffer))
      //  echo bin2hex($buffer)."\n";
    }
    if ($i >= 100) {
      // Kein Echo erhalten Fehler!
      // echo "Ausgang 1\n";
      return false;
    }
    $buffer = "";
    $i = 1;
    //  Hier werden erst die richtigen Daten erwartet.
    //
    for ($i = 1; $i < 200; $i++) {
      $buffer .= fgets($USB, 1024);
      usleep(1800); // 1800 ist ein guter Wert 30.3.2016
      if (substr(bin2hex($buffer), 0, 2) == "00") {
        $buffer = substr($buffer, 1);
        continue;
      }
      if (strlen($buffer) > 8) {
        $Laenge = hexdec(substr(bin2hex($buffer), 4, 4));
        if (strlen(bin2hex($buffer)) >= $Laenge * 2) {
          // Die erwartete Datenlänge ist erreicht.
          // echo "Länge erreicht: ".$Laenge."  i:".$i."\n";
          break;
        }
      }
      // if (!empty($buffer))
      //   echo bin2hex($buffer)."\n";
    }
    if ($i >= 200) {
      // echo "Ausgang 2 - falsch\n";
      return false;
    }
    stream_set_blocking($USB, true);
    // echo "\nErgebnis: ".bin2hex($buffer);
    return bin2hex($buffer);
  }
}
?>