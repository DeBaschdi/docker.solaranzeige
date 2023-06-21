<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class VE {
  public static function VE_CRC($Daten) {
    $Dezimal = 85; //  HEX 55
    $Daten = "0".$Daten;
    for ($i = 0; $i < strlen($Daten) - 1; $i += 2) {
      $Dezimal = ($Dezimal - hexdec($Daten[$i].$Daten[$i + 1]));
    }
    $CRC = strtoupper(substr("0".dechex($Dezimal), - 2));
    return $CRC;
  }
  
  /****************************************************************************
  //  Victron Geräte    Victron Geräte    Victron Geräte    Victron Geräte
  //  Hier wird das VE.Direct Protokoll ausgelesen.
  //  Bitte die usleep() Funktionen nicht verändern, zumindest erst nach
  //  längeren Testreihen.
  //  Bei schnelleren Raspberry Pi Rechnern müssen die zeitlichen Funktionen
  //  eventuell angepasst werden.
  //
  ****************************************************************************/
  public static function ve_regler_auslesen($USB, $Input) {
    stream_set_blocking($USB, false);
    $Timestamp = time();
    $Ergebnis = "";
    $Antwort = "";
    $Laenge = (strlen($Input) - 2);
    $Dauer = 2; // Sekunden
    // Befehl senden...
    // echo "Befehl: ".$Input." Länge: ".$Laenge."\n";
    for ($i = 1; $i < 100; $i++) {
      $rc = fgets($USB, 64); // Alle Daten   die noch nicht entfernt sind hier entfernen.
      Log::write("Restdaten: ".$rc, "   ", 10);
      if ($rc == "") {
        break;
      }
      usleep(1000);
    }
    fputs($USB, $Input."\n");
    //usleep( 1000 ); //  vorher 110000  23.1.2018
    do {
      $Antwort = fgets($USB, 4096); // 4096
      if (trim($Antwort) == "") {
        // echo "Nichts empfangen .. \n";
        usleep(200000); // vorher 120000
        $Antwort = "";
        continue;
      }
      elseif (trim($Antwort) == $Input) {
        // echo "Echo empfangen .. \n";
        usleep(500);
        $Antwort = "";
      }
      elseif (substr($Antwort, 0, 2) == ":A") {
        continue;
      }
      else {
        Log::write(" Sek. ".date("s")." Antwort: ".trim($Antwort)." ".strpos($Antwort, ":"), "   ", 8);
        if (strpos($Antwort, ":") === false) {
          // Es kommen asynchrone Nachrichten. Pause machen!
          // usleep( 10000 );   // Geändert 6.11.2021
          continue;
        }
        else {
          //  Das richtige Ergebnis...  jetzt noch
          //  kleinere Korrekturen
          //  Der Doppelpunkt muss am Anfang stehen und es darf nur ein
          //  Doppelpunkt vorhanden sein.
          if (strrpos($Antwort, ":") > 1) {
            $Antwort = substr($Antwort, (strrpos($Antwort, ":")));
            Log::write(" Korrektur: ".trim($Antwort), "   ", 8);
          }
          $Ergebnis .= substr($Antwort, strpos($Antwort, ":"), strpos($Antwort, "\n"));
          if (substr($Antwort, 0, 2) == ":A") {
            //  Antworten mit :A beginnend sind unerwünscht.
            $Ergebnis = "";
            $Antwort = "";
            //usleep( 100000 );
            break; // continue;
          }
          // echo "E: ".$Ergebnis."\n";
          if (substr($Ergebnis, 0, $Laenge) == substr($Input, 0, $Laenge)) {
            break;
          }
          elseif (substr($Ergebnis, 0, 2) == ":1") {
            //  Antwort  Version
            break;
          }
          elseif (substr($Ergebnis, 0, 2) == ":4") {
            // Antwort  Device ID  oder Framefehler
            break;
          }
          elseif (substr($Ergebnis, 0, 2) == ":5") {
            //  Ping Antwort
            break;
          }
        }
      }
    } while (($Timestamp + $Dauer) > time());
    stream_set_blocking($USB, true);
    Log::write(" Return: ".trim($Ergebnis), "   ", 8);
    return $Ergebnis;
  }

  /**************************************************************************
  //  Hier wird das VE.Direct Protokoll entschlüsselt.
  //
  **************************************************************************/
  public static function ve_ergebnis_auswerten($Daten) {
    $Ergebnis = array();
    $Response = substr($Daten, 1, 1);
    switch ($Response) {
      case 1:
        $Ergebnis["Produkt"] = substr($Daten, 4, 2).substr($Daten, 2, 2);
        break;
      case 4:
        $Ergebnis["Framefehler"] = substr($Daten, 2, 4);
        break;
      case 5:
        $Ergebnis["Firmware"] = substr($Daten, 5, 1).".".substr($Daten, 2, 2);
        break;
      case 6:
        $Ergebnis["Reboot"] = 1;
        // Reboot des Reglers -> Keine Antwort
        break;
      case 7:
        if (substr($Daten, 2, 4) == "8DED") { // Main Voltage
          $Ergebnis["Batteriespannung"] = (Utils::hexdecs(substr($Daten, 10, 2).substr($Daten, 8, 2)) / 100);
        }
        elseif (substr($Daten, 2, 4) == "FF0F") { // SOC
          $Ergebnis["SOC"] = (hexdec(substr($Daten, 10, 2).substr($Daten, 8, 2)) / 100);
        }
        elseif (substr($Daten, 2, 4) == "8FED") { // Strom +/-
          $Ergebnis["Batteriestrom"] = (Utils::hexdecs(substr($Daten, 10, 2).substr($Daten, 8, 2)) / 10);
        }
        elseif (substr($Daten, 2, 4) == "8EED") { // Power
          $Ergebnis["Leistung"] = (Utils::hexdecs(substr($Daten, 10, 2).substr($Daten, 8, 2)));
        }
        elseif (substr($Daten, 2, 4) == "FE0F") {
          $Ergebnis["TTG"] = Utils::hexdecs(substr($Daten, 10, 2).substr($Daten, 8, 2));
        }
        elseif (substr($Daten, 2, 4) == "0503") { // Cumulative Amp Hours
          $Ergebnis["AmperestundenGesamt"] = (Utils::hexdecs(substr($Daten, 14, 2).substr($Daten, 12, 2).substr($Daten, 10, 2).substr($Daten, 8, 2)) / 10);
        }
        elseif (substr($Daten, 2, 4) == "1003") {
          $Ergebnis["WattstundenGesamtEntladung"] = (hexdec(substr($Daten, 14, 2).substr($Daten, 12, 2).substr($Daten, 10, 2).substr($Daten, 8, 2)) * 10);
        }
        elseif (substr($Daten, 2, 4) == "1103") {
          $Ergebnis["WattstundenGesamtLadung"] = (hexdec(substr($Daten, 14, 2).substr($Daten, 12, 2).substr($Daten, 10, 2).substr($Daten, 8, 2)) * 10);
        }
        elseif (substr($Daten, 2, 4) == "ECED") {
          if (substr($Daten, 8, 4) == "FFFF") {
            $Ergebnis["Temperatur"] = 0;
          }
          else {
            $Ergebnis["Temperatur"] = Utils::kelvinToCelsius(hexdec(substr($Daten, 10, 2).substr($Daten, 8, 2)) / 100);
          }
        }
        elseif (substr($Daten, 2, 4) == "0803") { // Days since last full charge
          $Ergebnis["ZeitVollladung"] = (hexdec(substr($Daten, 14, 2).substr($Daten, 12, 2).substr($Daten, 10, 2).substr($Daten, 8, 2)));
        }
        elseif (substr($Daten, 2, 4) == "FFEE") {
          $Ergebnis["Amperestunden"] = (Utils::hexdecs(substr($Daten, 14, 2).substr($Daten, 12, 2).substr($Daten, 10, 2).substr($Daten, 8, 2)) / 10);
        }
        elseif (substr($Daten, 2, 4) == "B6EE") {
          $Ergebnis["Ladestatus"] = hexdec(substr($Daten, 8, 2));
        }
        elseif (substr($Daten, 2, 4) == "0203") {
          $Ergebnis["EntladetiefeDurchschnitt"] = (Utils::hexdecs(substr($Daten, 14, 2).substr($Daten, 12, 2).substr($Daten, 10, 2).substr($Daten, 8, 2)) / 10);
        }
        elseif (substr($Daten, 2, 4) == "0303") {
          $Ergebnis["Ladezyklen"] = (hexdec(substr($Daten, 14, 2).substr($Daten, 12, 2).substr($Daten, 10, 2).substr($Daten, 8, 2)));
        }
        elseif (substr($Daten, 2, 4) == "0403") {
          $Ergebnis["EntladungMax"] = (hexdec(substr($Daten, 14, 2).substr($Daten, 12, 2).substr($Daten, 10, 2).substr($Daten, 8, 2)));
        }
        elseif (substr($Daten, 2, 4) == "0903") {
          $Ergebnis["AnzahlSynchronisationen"] = (hexdec(substr($Daten, 14, 2).substr($Daten, 12, 2).substr($Daten, 10, 2).substr($Daten, 8, 2)));
        }
        elseif (substr($Daten, 2, 4) == "FCEE") {
          $Ergebnis["Alarm"] = hexdec(substr($Daten, 8, 2));
        }
        elseif (substr($Daten, 2, 4) == "4E03") {
          $Ergebnis["Relais"] = hexdec(substr($Daten, 8, 2));
        }
        elseif (substr($Daten, 2, 4) == "D5ED") {
          $Ergebnis["Batteriespannung"] = (hexdec(substr($Daten, 10, 2).substr($Daten, 8, 2)) / 100);
        }
        elseif (substr($Daten, 2, 4) == "7DED") { // Aux Voltage
          $Ergebnis["BatteriespannungAux"] = (Utils::hexdecs(substr($Daten, 10, 2).substr($Daten, 8, 2)) / 100);
        }
        elseif (substr($Daten, 2, 4) == "D7ED") {
          //  Batterieladestrom
          $Ergebnis["Batterieladestrom"] = (hexdec(substr($Daten, 10, 2).substr($Daten, 8, 2)) / 10);
        }
        elseif (substr($Daten, 2, 4) == "BBED") {
          $Ergebnis["Solarspannung"] = (hexdec(substr($Daten, 10, 2).substr($Daten, 8, 2)) / 100);
        }
        elseif (substr($Daten, 2, 4) == "ADED") {
          $Ergebnis["Batterieentladestrom"] = (hexdec(substr($Daten, 10, 2).substr($Daten, 8, 2)) / 10);
        }
        elseif (substr($Daten, 2, 4) == "4F10") {
          $Ergebnis["WattstundenGesamt"] = (hexdec(substr($Daten, 26, 2).substr($Daten, 24, 2).substr($Daten, 22, 2).substr($Daten, 20, 2)) * 10);
        }
        elseif (substr($Daten, 2, 4) == "DBED") {
          $Ergebnis["Temperatur"] = (hexdec(substr($Daten, 10, 2).substr($Daten, 8, 2)) / 100);
        }
        elseif (substr($Daten, 2, 4) == "4001") {
          $Ergebnis["Optionen"] = decbin(hexdec(substr($Daten, 14, 2).substr($Daten, 12, 2).substr($Daten, 10, 2).substr($Daten, 8, 2)));
          $Ergebnis["Optionen"] = str_pad($Ergebnis["Optionen"], 24, '0', STR_PAD_LEFT);
        }
        elseif (substr($Daten, 2, 4) == "0102") {
          $Ergebnis["Ladestatus"] = hexdec(substr($Daten, 8, 2));
        }
        elseif (substr($Daten, 2, 4) == "D2ED") {
          $Ergebnis["maxWattHeute"] = hexdec(substr($Daten, 10, 2).substr($Daten, 8, 2));
        }
        elseif (substr($Daten, 2, 4) == "BDED") {
          //  Solarstrom
          $Ergebnis["Solarstrom"] = (hexdec(substr($Daten, 10, 2).substr($Daten, 8, 2)) / 10);
        }
        elseif (substr($Daten, 2, 4) == "A8ED") {
          //  Load Status
          $Ergebnis["LoadStatus"] = hexdec(substr($Daten, 8, 2));
        }
        elseif (substr($Daten, 2, 4) == "BCED") {
          $Ergebnis["Solarleistung"] = (hexdec(substr($Daten, 14, 2).substr($Daten, 12, 2).substr($Daten, 10, 2).substr($Daten, 8, 2)) / 100);
        }
        elseif (substr($Daten, 2, 4) == "5010") {
          $Ergebnis["WattstundenGesamtHeute"] = (hexdec(substr($Daten, 16, 2).substr($Daten, 14, 2).substr($Daten, 12, 2).substr($Daten, 10, 2)) * 10);
          $Ergebnis["BulkMinutenHeute"] = hexdec(substr($Daten, 46, 2).substr($Daten, 44, 2));
          $Ergebnis["AbsorptionMinutenHeute"] = hexdec(substr($Daten, 50, 2).substr($Daten, 48, 2));
          $Ergebnis["FloatMinutenHeute"] = hexdec(substr($Daten, 54, 2).substr($Daten, 52, 2));
          $Ergebnis["BatterieladestromMaxHeute"] = (hexdec(substr($Daten, 66, 2).substr($Daten, 64, 2)) / 10);
          $Ergebnis["SolarspannungMaxHeute"] = (hexdec(substr($Daten, 70, 2).substr($Daten, 68, 2)) / 100);
        }
        elseif (substr($Daten, 2, 4) == "5110") {
          $Ergebnis["WattstundenGesamtGestern"] = (hexdec(substr($Daten, 16, 2).substr($Daten, 14, 2).substr($Daten, 12, 2).substr($Daten, 10, 2)) * 10);
        }
        elseif (substr($Daten, 2, 4) == "DAED") {
          $Ergebnis["ErrorCodes"] = hexdec(substr($Daten, 8, 2));
        }
        elseif (substr($Daten, 2, 4) == "DFED") {
          $Ergebnis["maxAmpHeute"] = (hexdec(substr($Daten, 10, 2).substr($Daten, 8, 2)) / 10);
        }
        elseif (substr($Daten, 2, 4) == "0002") {
          //  Mode
          $Ergebnis["Mode"] = hexdec(substr($Daten, 8, 2));
        }
        elseif (substr($Daten, 2, 4) == "1C03") {
          //  Warnungen
          $Ergebnis["Warnungen"] = hexdec(substr($Daten, 10, 2).substr($Daten, 8, 2));
        }
        elseif (substr($Daten, 2, 4) == "0122") {
          //  AC_Strom  => wird negativ angegeben... deshalb abs()
          $Ergebnis["AC_Ausgangsstrom"] = abs(Utils::hexdecs(substr($Daten, 10, 2).substr($Daten, 8, 2)) / 10);
        }
        elseif (substr($Daten, 2, 4) == "0022") {
          //  AC_Spannung
          $Ergebnis["AC_Ausgangsspannung"] = (Utils::hexdecs(substr($Daten, 10, 2).substr($Daten, 8, 2)) / 100);
        }
        elseif (substr($Daten, 2, 4) == "0320") {
          //  Batterie Sensor Temperatur
          $Ergebnis["BatterieSenseTemp"] = (Utils::hexdecs(substr($Daten, 10, 2).substr($Daten, 8, 2)) / 100);
          if ($Ergebnis["BatterieSenseTemp"] == 327.67) {
            $Ergebnis["BatterieSenseTemp"] = 0;
          }
          elseif ($Ergebnis["BatterieSenseTemp"] == 327.68) {
            $Ergebnis["BatterieSenseTemp"] = 0;
          }
        }
        else {
          $Ergebnis["Hexwerte"] = $Daten;
        }
        break;
      case 8:
        if (substr($Daten, 2, 4) == "9EED") {
          if (substr($Daten, 6, 2) == "00") {
            $Ergebnis["HEX Mode"] = "NEIN";
          }
          else {
            $Ergebnis["HEX Mode"] = "JA";
          }
        }
        break;
      case 'A':
        break;
      default:
        $Ergebnis["Error"] = $Daten;
        break;
    }
    return $Ergebnis;
  }

  public static function ve_fehlermeldung($ErrorCode) {
    switch ($ErrorCode) {
      case 2:
        $Text = "Batteriespannung zu hoch.";
        break;
      case 17:
        $Text = "Temperatur des Reglers ist zu hoch.";
        break;
      case 18:
        $Text = "Ladestrom zu hoch.";
        break;
      case 19:
        $Text = "Polarität vertauscht.";
        break;
      case 20:
        $Text = "Maximale Ladezeit überschritten.";
        break;
      case 21:
        $Text = "Charger current sensor issue.";
        break;
      case 26:
        $Text = "Regler Anschlüsse sind zu heiß.";
        break;
      case 33:
        $Text = "Eingangsspannung ist zu hoch.";
        break;
      case 38:
        $Text = "Input shutdown.";
        break;
      case 67:
        $Text = "BMS connection lost.";
        break;
      case 116:
        $Text = "Calibration data lost.";
        break;
      case 117:
        $Text = "Incompatible firmware.";
        break;
      case 119:
        $Text = "Settings data invalid.";
        break;
      default:
        $Text = "unbekannter Fehler";
        break;
    }
    return $Text;
  }
}
?>