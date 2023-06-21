<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class Renogy {
  /**************************************************************************
  //  Rover / Toyo / SRNE  Laderegler        Rover / Toyo / SRNE  Laderegler
  //  Rover / Toyo / SRNE  Laderegler        Rover / Toyo / SRNE  Laderegler
  //  Hier wird der RS845 Bus ausgelesen. Diese Routine ist sehr zeitkritisch
  //  Bitte die usleep() Funktionen nicht verändern, zumindest erst nach
  //  längeren Testreihen.
  //  Bei schnelleren Raspberry Pi Rechnern müssen die zeitlichen Funktionen
  //  eventuell angepasst werden.
  //  $Input["DeviceID"]
  //  $Input["BefehlFunctionCode"]
  //  $Input["RegisterAddress"]
  //  $Input["RegisterCount"]
  **************************************************************************/
  public static function renogy_auslesen($USB, $Input) {
    stream_set_blocking($USB, false);
    $BefehlBin = hex2bin($Input["DeviceID"].$Input["BefehlFunctionCode"].$Input["RegisterAddress"].$Input["RegisterCount"]);
    $BefehlBin = $BefehlBin.Utils::crc16($BefehlBin);
    // Befehl in HEX!
    // echo $Input["DeviceID"].$Input["BefehlFunctionCode"].$Input["RegisterAddress"].$Input["RegisterCount"];
    $Laenge = strlen($BefehlBin);
    for ($k = 1; $k < 3; $k++) {
      $buffer = fgets($USB, 500);
      $buffer = "";
      Log::write("==> B".$k.": [ ".bin2hex($BefehlBin)." ]", " ", 9);
      fputs($USB, $BefehlBin);
      usleep(50000); // 0,05 Sekunden warten
      for ($i = 1; $i < 500; $i++) {
        $buffer .= fgets($USB, 100);
        usleep(1000); // Geändert 24.6.2021
        $buffer .= fgets($USB, 100); // Geändert 24.6.2021
        if (bin2hex($buffer) <> "")
          Log::write("==> X: [ ".bin2hex($BefehlBin)." ]  [ ".bin2hex($buffer)." ] ".strlen($buffer), " ", 9);
        // echo $i." ".bin2hex($buffer)."\n";
        if (substr($buffer, 0, $Laenge) == $BefehlBin and substr(bin2hex($buffer), 0, 4) <> "0106") {
          // echo "Echo erhalten ".$i."\n";
          // Beim Schreiben ist Echo OK
          $buffer = substr($buffer, $Laenge);
          $buffer = "";
        }
        if (substr($buffer, 0, 2) == substr($BefehlBin, 0, 2)) {
          Log::write("==> C: [ ".bin2hex(substr($buffer, 0, 2))." ]", " ", 9);
          if (substr(bin2hex($buffer), 0, 4) == "0183" or substr(bin2hex($buffer), 0, 4) == "0186") {
            break;
          }
          if (strlen($buffer) == (hexdec(substr(bin2hex($buffer), 4, 2)) + 5) and strlen($buffer) < 30) {
            // Länger als 30 Byte ist keine gültige Antwort.
            // echo "Länge: ".(hexdec(substr(bin2hex($buffer),4,2)) + 5)."\n";
            // echo "Ausgang: ".$i."\n";
            Log::write("==> A: [ ".bin2hex($buffer)." ]", " ", 9);
            stream_set_blocking($USB, true);
            return bin2hex($buffer);
          }
          if (strcmp(bin2hex($buffer), bin2hex($BefehlBin)) == 0) {
            //  Load Ausgang wird geschaltet
            Log::write("==> A: [ ".bin2hex($buffer)." ]", " ", 9);
            stream_set_blocking($USB, true);
            return bin2hex($buffer);
          }
        }
        elseif (strlen($buffer) > 0) {
          // echo "break\n";
          break;
        }
      }
    }
    stream_set_blocking($USB, true);
    return false;
  }

  /******************************************************************************
  //  Umwandlung der Binärdaten in lesbare Form
  ******************************************************************************/
  public static function renogy_daten($daten, $Dezimal = true, $Times = false) {
    $DeviceID = substr($daten, 0, 2);
    $BefehlFunctionCode = substr($daten, 2, 2);
    $RegisterCount = substr($daten, 4, 2);
    if ($Dezimal) {
      $Ergebnis = hexdec(substr($daten, 6, ($RegisterCount * 2)));
    }
    else {
      $Ergebnis = substr($daten, 6, ($RegisterCount * 2));
    }
    if ($Times == true) {
      $Ergebnis = $Ergebnis / 100;
    }
    return $Ergebnis;
  }
}
?>