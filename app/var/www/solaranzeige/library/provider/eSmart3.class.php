<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class eSmart3 {

  /************************************************************************/
  public static function eSmart3_auslesen($Device, $Befehl = '') {
    $Antwort = "";
    $OK = false;
    stream_set_blocking($Device, false); // Es wird auf keine Daten gewartet.
    fgets($Device, 4092);
    $Dauer = 3; // 4 Sekunden
    // echo $Befehl."\n";
    $rc = fwrite($Device, hex2bin($Befehl));
    usleep(500000);
    $Timestamp = time();
    $Ergebnis = "";
    $Antwort = "";
    do {
      $Antwort = fgets($Device, 192);
      // echo bin2hex($Antwort)."\n";
      if (trim($Antwort) == "") {
        // echo "Nichts empfangen .. \n";
        usleep(100000);
      }
      else {
        $Ergebnis .= $Antwort;
        Log::write("1: ".bin2hex($Ergebnis)." Länge: ".strlen($Ergebnis), "   ", 10);
        if (strtoupper(substr(bin2hex($Ergebnis), 0, 2)) == "AA") {
          $Datenlaenge = hexdec(substr(bin2hex($Ergebnis), 10, 2));
          if (strlen($Ergebnis) >= ($Datenlaenge + 7)) {
            // Paket ist komplett
            $Dauer = 0; // Ausgang vorbereiten..
            $OK = true;
          }
        }
        elseif (strtoupper(substr(bin2hex($Ergebnis), 0, 4)) == "A501") {
          // echo "Länge: ".strlen($Ergebnis)."\n";
          if (strlen($Ergebnis) == 13) {
            // Paket ist komplett
            $Dauer = 0; // Ausgang vorbereiten..
            $OK = true;
            $Ergebnis = substr($Ergebnis, 4, 8);
          }
          if (strlen($Ergebnis) == 26) {
            // Paket ist komplett
            $Dauer = 0; // Ausgang vorbereiten..
            $OK = true;
            $Ergebnis1 = substr($Ergebnis, 4, 8);
            $Ergebnis1 .= substr($Ergebnis, 17, 8);
            $Ergebnis = $Ergebnis1;
          }
          elseif (strlen($Ergebnis) == 39) {
            $Ergebnis1 = substr($Ergebnis, 4, 8);
            $Ergebnis1 .= substr($Ergebnis, 17, 8);
            $Ergebnis1 .= substr($Ergebnis, 30, 8);
            $Ergebnis1 .= substr($Ergebnis, 43, 8);
            $Dauer = 0; // Ausgang vorbereiten..
            $OK = true;
            $Ergebnis = $Ergebnis1;
          }
          elseif (strlen($Ergebnis) == 52) {
            $Ergebnis1 = substr($Ergebnis, 4, 8);
            $Ergebnis1 .= substr($Ergebnis, 17, 8);
            $Ergebnis1 .= substr($Ergebnis, 30, 8);
            $Ergebnis1 .= substr($Ergebnis, 43, 8);
            $Ergebnis1 .= substr($Ergebnis, 56, 8);
            $Dauer = 0; // Ausgang vorbereiten..
            $OK = true;
            $Ergebnis = $Ergebnis1;
          }
          elseif (strlen($Ergebnis) == 78) {
            $Ergebnis1 = substr($Ergebnis, 4, 8);
            $Ergebnis1 .= substr($Ergebnis, 17, 8);
            $Ergebnis1 .= substr($Ergebnis, 30, 8);
            $Ergebnis1 .= substr($Ergebnis, 43, 8);
            $Ergebnis1 .= substr($Ergebnis, 56, 8);
            $Ergebnis1 .= substr($Ergebnis, 69, 8);
            $Ergebnis1 .= substr($Ergebnis, 82, 8);
            $Dauer = 0; // Ausgang vorbereiten..
            $OK = true;
            $Ergebnis = $Ergebnis1;
          }
          elseif (strlen($Ergebnis) == 143) {
            $Ergebnis1 = substr($Ergebnis, 4, 8);
            $Ergebnis1 .= substr($Ergebnis, 17, 8);
            $Ergebnis1 .= substr($Ergebnis, 30, 8);
            $Ergebnis1 .= substr($Ergebnis, 43, 8);
            $Ergebnis1 .= substr($Ergebnis, 56, 8);
            $Ergebnis1 .= substr($Ergebnis, 69, 8);
            $Ergebnis1 .= substr($Ergebnis, 82, 8);
            $Ergebnis1 .= substr($Ergebnis, 95, 8);
            $Ergebnis1 .= substr($Ergebnis, 108, 8);
            $Ergebnis1 .= substr($Ergebnis, 121, 8);
            $Ergebnis1 .= substr($Ergebnis, 134, 8);
            $Dauer = 0; // Ausgang vorbereiten..
            $OK = true;
            $Ergebnis = $Ergebnis1;
          }
          elseif (strlen($Ergebnis) == 208) {
            $Ergebnis1 = substr($Ergebnis, 4, 8);
            $Ergebnis1 .= substr($Ergebnis, 17, 8);
            $Ergebnis1 .= substr($Ergebnis, 30, 8);
            $Ergebnis1 .= substr($Ergebnis, 43, 8);
            $Ergebnis1 .= substr($Ergebnis, 56, 8);
            $Ergebnis1 .= substr($Ergebnis, 69, 8);
            $Ergebnis1 .= substr($Ergebnis, 82, 8);
            $Ergebnis1 .= substr($Ergebnis, 95, 8);
            $Ergebnis1 .= substr($Ergebnis, 108, 8);
            $Ergebnis1 .= substr($Ergebnis, 121, 8);
            $Ergebnis1 .= substr($Ergebnis, 134, 8);
            $Ergebnis1 .= substr($Ergebnis, 147, 8);
            $Ergebnis1 .= substr($Ergebnis, 160, 8);
            $Ergebnis1 .= substr($Ergebnis, 173, 8);
            $Ergebnis1 .= substr($Ergebnis, 186, 8);
            $Ergebnis1 .= substr($Ergebnis, 199, 8);
            $Dauer = 0; // Ausgang vorbereiten..
            $OK = true;
            $Ergebnis = $Ergebnis1;
          }
        }
      }
    } while (($Timestamp + $Dauer) > time());
    stream_set_blocking($Device, true);
    if ($OK == true) {
      return bin2hex($Ergebnis);
    }
    return false;
  }

  /************************************************************************/
  public static function eSmart3_ergebnis_auswerten($Daten) {
    $Ergebnis = array();
    $string = "";
    if (strtoupper(substr($Daten, 0, 2)) == "AA") {
      if (substr($Daten, 8, 2) == "00") {
        $Ergebnis["Ladestatus"] = hexdec(substr($Daten, 18, 2).substr($Daten, 16, 2));
        $Ergebnis["Solarspannung"] = (hexdec(substr($Daten, 22, 2).substr($Daten, 20, 2)) / 10);
        $Ergebnis["Batteriespannung"] = (hexdec(substr($Daten, 26, 2).substr($Daten, 24, 2)) / 10);
        $Ergebnis["Batterieladestrom"] = (hexdec(substr($Daten, 30, 2).substr($Daten, 28, 2)) / 10);
        $Ergebnis["Verbraucherspannung"] = (hexdec(substr($Daten, 38, 2).substr($Daten, 36, 2)) / 10);
        $Ergebnis["Verbraucherstrom"] = (hexdec(substr($Daten, 42, 2).substr($Daten, 40, 2)) / 10);
        $Ergebnis["Solarleistung"] = hexdec(substr($Daten, 46, 2).substr($Daten, 44, 2));
        $Ergebnis["Verbraucherleistung"] = hexdec(substr($Daten, 50, 2).substr($Daten, 48, 2));
        $Ergebnis["Batterietemperatur"] = hexdec(substr($Daten, 54, 2).substr($Daten, 52, 2));
        $Ergebnis["Temperatur"] = hexdec(substr($Daten, 58, 2).substr($Daten, 56, 2));
        $Ergebnis["SOC"] = hexdec(substr($Daten, 62, 2).substr($Daten, 60, 2));
        $Ergebnis["CO2"] = (hexdec(substr($Daten, 70, 2).substr($Daten, 68, 2).substr($Daten, 66, 2).substr($Daten, 64, 2)) / 10);
        $Ergebnis["ErrorCode"] = substr($Daten, 74, 2).substr($Daten, 72, 2);
        // aa0101030020 0000 0300 6101 1601 0700 0000 0000 0000 1300 0000 1900 1a00 6400 00005e00 0000  [a6]
        //              12   16   20   24   28   32   36   40   44   48   52   56   60   64       72
      }
      if (substr($Daten, 8, 2) == "01") {
        $Ergebnis["Batterietyp"] = hexdec(substr($Daten, 22, 2).substr($Daten, 20, 2));
        $Ergebnis["Bulk_Spannung"] = (hexdec(substr($Daten, 30, 2).substr($Daten, 28, 2)) / 10);
        $Ergebnis["Float_Spannung"] = (hexdec(substr($Daten, 34, 2).substr($Daten, 32, 2)) / 10);
        $Ergebnis["MaxLadestrom"] = (hexdec(substr($Daten, 38, 2).substr($Daten, 36, 2)) / 10);
        $Ergebnis["MaxEntladestrom"] = (hexdec(substr($Daten, 42, 2).substr($Daten, 40, 2)) / 10);
        $Ergebnis["Auslastung"] = hexdec(substr($Daten, 54, 2).substr($Daten, 52, 2));
        // aa0101030116 0000 0ba4 0100 0000 9200 8a00 9001 9001 9200 1e00 0000 [9c]
        //              12   16   20   24   28   32   36   40   44   48   52
      }
      if (substr($Daten, 8, 2) == "02") {
        $Ergebnis["WattstundenGesamtHeute"] = hexdec(substr($Daten, 50, 2).substr($Daten, 48, 2).substr($Daten, 46, 2).substr($Daten, 44, 2));
        $Ergebnis["WattstundenGesamtMonat"] = hexdec(substr($Daten, 66, 2).substr($Daten, 64, 2).substr($Daten, 62, 2).substr($Daten, 60, 2));
        $Ergebnis["WattstundenGesamt"] = hexdec(substr($Daten, 82, 2).substr($Daten, 80, 2).substr($Daten, 78, 2).substr($Daten, 76, 2));
        // aa0101030234 0000 40a5 0000 0000 0100 0000 0000 0000 0000 0000 0000 0000 0000 0000
        //              12   16   20   24   28   32   36   40   44   48   52   56   60   64
        //              0000 0000 0000 0000 0000 0000 0000 0000 0000 0a00 0100 4ca5  [39]
        //              68   72   76   80   84   88   92   96   100  104  108  112
      }
      if (substr($Daten, 8, 2) == "08") {
        $hex = substr($Daten, 20, 32);
        for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
          $string .= chr(hexdec($hex[$i].$hex[$i + 1]));
        }
        $Ergebnis["Seriennummer"] = $string;
        $string = "";
        $hex = substr($Daten, 52, 8);
        for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
          $string .= chr(hexdec($hex[$i].$hex[$i + 1]));
        }
        $Ergebnis["Firmware"] = $string;
        $string = "";
        $hex = substr($Daten, 60, 32);
        for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
          $string .= chr(hexdec($hex[$i].$hex[$i + 1]));
        }
        $Ergebnis["Produkt"] = $string;
        // aa0101030820 0000 4044 3334303030303033323031373037303156332e3065536d6172743700ff
      }
      return $Ergebnis;
    }
    else {
      return false;
    }
  }
}
?>