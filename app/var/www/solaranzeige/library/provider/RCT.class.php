<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class RCT {
  /****************************************************************************
  //  RCT Wechselrichter         RCT Wechselrichter         RCT Wechselrichter
  //
  //
  ****************************************************************************/
  public static function rct_auslesen($COM, $Command, $Laenge, $ID, $Form = "float") {
    stream_set_timeout($COM, 1); // 1 Sekunde
    $Antwort = "";
    $Start = "2b";
    $Ergebnis = array("OK" => 0);
    $ID = strtolower($ID);
    $CRC = Utils::calcCRC($Command.$Laenge.$ID);
    if (strpos($ID, "2d2d") !== false)
      $CRC = Utils::calcCRC($Command.$Laenge.str_replace("2d2d", "2d", $ID));
    if (strpos($ID, "2d2b") !== false)
      $CRC = Utils::calcCRC($Command.$Laenge.str_replace("2d2b", "2b", $ID));
    // echo $Start.$Command.$Laenge.$ID.$CRC."\n";
    $sendenachricht = hex2bin(strtolower($Start.$Command.$Laenge.$ID.$CRC));
    // $rc = fread( $COM, 1000 ); // 1000 Bytes lesen
    $rc = fwrite($COM, $sendenachricht);
    for ($i = 1; $i < 5; $i++) {
      $Antwort .= bin2hex(fread($COM, 1000)); // 1000 Bytes lesen
      // echo $i."  ".$Antwort."\n\n";
      if (strrpos($Antwort, "002b05") === false) {
        $Antwort = "";
        continue;
      }
      elseif (strrpos($Antwort, "002b05") <> 0) {
        // Enthält das Paket mehrere Antworten?
        Log::write("Korrigierte Antwort = ".substr($Antwort, strrpos($Antwort, $ID) - 8), "   ", 7);
        Log::write("!! RPOS = ".(strrpos($Antwort, $ID) - 8), "   ", 8);
        Log::write("!! Text = ".$ID, "   ", 8);
        $Antwort = substr($Antwort, strrpos($Antwort, $ID) - 8);
      }
      if (strpos($Antwort, "002b05") == 0) {
        $Ergebnis["Command"] = substr($Antwort, 4, 2);
        $Ergebnis["Laenge"] = substr($Antwort, 6, 2);
        $Ergebnis["Raw"] = $Antwort;
        $Ergebnis["ID"] = substr(str_replace("2d2d", "2d", $Antwort), 8, 8);
        if (str_replace("2d2d", "2d", $ID) <> $Ergebnis["ID"]) {
          // Falls eine falsche Antwort zurück kommt.
          Log::write(" Lesefehler aufgetreten. Abbruch! ]", " [ ", 2);
          Log::write("[Raw] ".$Ergebnis["Raw"], "   ", 5);
          $Ergebnis["OK"] = 0;
          break;
        }
        if ($Form == "float") {
          $Ergebnis["Wert"] = round(Utils::hex2float(substr(str_replace("2d2d", "2d", $Antwort), 16, (hexdec($Ergebnis["Laenge"]) * 2) - 8)), 2);
        }
        elseif ($Form == "U32") {
          $Ergebnis["Wert"] = hexdec(substr(str_replace("2d2d", "2d", $Antwort), 16, (hexdec($Ergebnis["Laenge"]) * 2) - 8));
        }
        elseif ($Form == "Hex") {
          $Ergebnis["Wert"] = substr(str_replace("2d2d", "2d", $Antwort), 16, (hexdec($Ergebnis["Laenge"]) * 2) - 8);
        }
        elseif ($Form == "String") {
          $Ergebnis["Wert"] = Utils::Hex2String(substr(str_replace("2d2d", "2d", $Antwort), 16, (hexdec($Ergebnis["Laenge"]) * 2) - 8));
        }
        else {
          $Ergebnis["Wert"] = 0;
        }
        $Ergebnis["OK"] = 1;
        break;
      }
      usleep(1000);
    }
    if ($Ergebnis["OK"] == 1) {
      return $Ergebnis;
    }
    // echo "Fehler! \n";
    return false;
  }
}
?>