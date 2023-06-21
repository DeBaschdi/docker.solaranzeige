<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class Delta {
  /******************************************************************
  //   DMODBUS vom Delta Wechselrichter lesen.
  //
  //      "Start" => "02"
  //      "Byte1" => "05",
  //      "ID" => "07",
  //      "Byte2" => "02",
  //      "Command" => "0000",
  //      "CRC" => "0000",
  //      "Stop" => "03");
  ******************************************************************/
  public static function delta_lesen($USB, $Input) {
    stream_set_blocking($USB, false);
    $BefehlBin = hex2bin($Input["Start"].$Input["Byte1"].$Input["ID"].$Input["Byte2"].$Input["Command"].$Input["CRC"].$Input["Stop"]);
    // echo $Input["Start"].$Input["Byte1"].$Input["ID"].$Input["Byte2"].$Input["Command"].$Input["CRC"].$Input["Stop"]."\n";
    $Laenge = strlen($BefehlBin);
    for ($k = 1; $k < 2; $k++) {
      $buffer = "";
      fputs($USB, $BefehlBin);
      for ($i = 1; $i < 20; $i++) {
        $buffer .= fgets($USB, 4096);
        //  echo $i."[ ".bin2hex($buffer)." ]\n";
        if (!empty($buffer)) {
          // echo $i."[ ".bin2hex($buffer)." ]\n";
          if ($buffer == $BefehlBin) {
            echo bin2hex($buffer)." Echo erhalten\n";
            $buffer = "";
          }
          if (bin2hex(substr($buffer, 0, 2)) == "0206" and bin2hex(substr($buffer, - 1)) == "03") {
            // echo $i."[ ".bin2hex($buffer)." ]\n";
            $Ergebnis = bin2hex($buffer);
            if ($Input["ID"] == substr($Ergebnis, 4, 2)) {
              $Laenge = hexdec(substr($Ergebnis, 6, 2));
              // echo "Länge: ".$Laenge."\n";
            }
            stream_set_blocking($USB, true);
            return substr($Ergebnis, 8, ($Laenge * 2));
          }
        }
        usleep(50000);
      }
    }
    stream_set_blocking($USB, true);
    return false;
  }

  public static function alpha_auslesen($Device, $Befehl = '') {
    $Antwort = "";
    $OK = false;
    stream_set_blocking($Device, false); // Es wird auf keine Daten gewartet.
    fgets($Device, 4092);
    $Dauer = 2; // 4 Sekunden
    //echo $Befehl."\n";
    $rc = fwrite($Device, hex2bin($Befehl));
    usleep(500000);
    $Timestamp = time();
    $Ergebnis = "";
    $Antwort = "";
    do {
      if (($Antwort = fgets($Device, 4096)) == false) {
        // echo "Nichts empfangen .. \n";
        usleep(100000);
      }
      else {
        $Ergebnis .= $Antwort;
        // echo "Ergebnis: ".bin2hex($Ergebnis)."\n";
        // echo "Länge_soll: ".(hexdec(substr(bin2hex($Ergebnis),4,2))+5)."\n";
        // echo "Länge_ist: ".strlen($Ergebnis)."\n";
        if (substr(bin2hex($Ergebnis), 0, 4) == "5503") {
          $Datenlaenge = hexdec(substr(bin2hex($Ergebnis), 4, 2));
          if (strlen($Ergebnis) == ($Datenlaenge + 5)) {
            // Paket ist komplett
            $Dauer = 0; // Ausgang vorbereiten..
            $OK = true;
          }
        }
        if (substr(bin2hex($Ergebnis), 0, 4) == "5583") {
          $Dauer = 0; // Ausgang vorbereiten..
          $OK = false;
        }
      }
    } while (($Timestamp + $Dauer) > time());
    stream_set_blocking($Device, true);
    if ($OK == true) {
      return bin2hex($Ergebnis);
    }
    return false;
  }
}
?>