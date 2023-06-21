<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class Tracer {
  /****************************************************************************
  //  Hier wird der RS845 Bus ausgelesen. Diese Routine ist sehr zeitkritisch
  //  Bitte die usleep() Funktionen nicht verändern, zumindest erst nach
  //  längeren Testreihen.
  //  Bei schnelleren Raspberry Pi Rechnern müssen die zeitlichen Funktionen
  //  eventuell angepasst werden.
  //  $Input["DeviceID"]
  //  $Input["BefehlFunctionCode"]
  //  $Input["RegisterAddress"]
  //  $Input["RegisterCount"]
  ****************************************************************************/
  public static function tracer_auslesen($USB, $Input) {
    stream_set_blocking($USB, false);
    $BefehlBin = hex2bin($Input["DeviceID"].$Input["BefehlFunctionCode"].$Input["RegisterAddress"].$Input["RegisterCount"]);
    $BefehlBin = $BefehlBin.Utils::crc16($BefehlBin);
    // Befehl in HEX!
    // echo $Input["DeviceID"].$Input["BefehlFunctionCode"].$Input["RegisterAddress"].$Input["RegisterCount"];
    $Laenge = strlen($BefehlBin);
    for ($k = 1; $k < 3; $k++) {
      $buffer = fgets($USB, 500);
      $buffer = "";
      fputs($USB, $BefehlBin);
      usleep(50000); // 0,05 Sekunden warten
      for ($i = 1; $i < 50; $i++) {
        $buffer .= fgets($USB, 100);
        usleep(10000);
        $buffer .= fgets($USB, 100);
        if (bin2hex($buffer) <> "")
          Log::write("==> X: [ ".bin2hex($BefehlBin)." ]  [ ".bin2hex($buffer)." ] ".strlen($buffer), " ", 9);
        //echo $i." ".bin2hex($buffer)."\n";
        if (substr($buffer, 0, $Laenge) == $BefehlBin) {
          // echo "Echo erhalten ".$i."\n";
          $buffer = substr($buffer, $Laenge);
          $buffer = "";
        }
        if (strlen($buffer) == (hexdec(substr(bin2hex($buffer), 4, 2)) + 5) and strlen($buffer) < 30) {
          // Länger als 30 Byte ist keine gültige Antwort.
          // echo "Länge: ".(hexdec(substr(bin2hex($buffer),4,2)) + 5)."\n";
          // echo "Ausgang: ".$i."\n";
          Log::write("==> A: [ ".bin2hex($buffer)." ]", " ", 9);
          stream_set_blocking($USB, true);
          return bin2hex($buffer);
        }
        if (substr($buffer, 0, 2) == substr($BefehlBin, 0, 2)) {
          if (strlen($buffer) == 7) {
            if (bin2hex($buffer) == "0140020000b930") {
              break;
            }
            //echo "Ausgang: ".$i."\n";
            stream_set_blocking($USB, true);
            return bin2hex($buffer);
          }
          else {
            // echo "break1: ".bin2hex($buffer)."\n";
          }
        }
        elseif (strlen($buffer) > 0) {
          // echo "break2\n";
          break;
        }
      }
    }
    stream_set_blocking($USB, true);
    return false;
  }
}
?>