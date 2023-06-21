<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class Phocos {
  /**************************************************************************
  //  MODBUS RTU     MODBUS RTU     MODBUS RTU     MODBUS RTU     MODBUS RTU
  //
  //  Phocos PH1800, PV18 sowie viele andere Geräte mit MODBUS RTO Protokoll
  //
  //  $Input["DeviceID"]
  //  $Input["BefehlFunctionCode"]
  //  $Input["RegisterAddress"]
  //  $Input["RegisterCount"]
  **************************************************************************/
  public static function phocos_pv18_auslesen($USB, $Input, $Timer = "50000") {
    stream_set_blocking($USB, false);
    if ($Input["BefehlFunctionCode"] == "10") {
      // Befehl schreiben! FunctionCode 16
      $RegLen = str_pad(dechex(strlen($Input["Befehl"]) / 2), 2, "0", STR_PAD_LEFT);
      $BefehlBin = hex2bin($Input["DeviceID"].$Input["BefehlFunctionCode"].$Input["RegisterAddress"].$Input["RegisterCount"].$RegLen.$Input["Befehl"]);
    }
    else {
      $BefehlBin = hex2bin($Input["DeviceID"].$Input["BefehlFunctionCode"].$Input["RegisterAddress"].$Input["RegisterCount"]);
    }
    // Befehl in HEX!
    //echo $Input["DeviceID"].$Input["BefehlFunctionCode"].$Input["RegisterAddress"].$Input["RegisterCount"].bin2hex(Utils::crc16($BefehlBin))."\n";
    $BefehlBin = $BefehlBin.Utils::crc16($BefehlBin);
    $data = array();
    $data["ok"] = false;
    for ($k = 1; $k < 15; $k++) { // vorher 3
      for ($l = 1; $l < 10; $l++) {
        usleep(100);
        $buffer = fgets($USB, 1000);
        // echo $l." ".bin2hex($buffer)."\n";
        if (empty($buffer)) {
          break;
        }
      }
      $buffer = "";
      fputs($USB, $BefehlBin);
      Log::write("xx> [ ".bin2hex($BefehlBin)." ]", " ", 9);
      // Die Ausgabe der Daten ist zeitlich sehr unterschiedlich!
      // Viele Timing Probleme.
      //
      usleep($Timer); // 0,05 Sekunden warten (Default) kann geändert werden mit dem Timing Parameter
      $buffer .= fgets($USB, 1024);
      usleep($Timer);
      $buffer .= fgets($USB, 1024);
      usleep($Timer);
      $buffer .= fgets($USB, 1024);
      // echo $i." ".bin2hex($buffer)."\n";
      //echo "> ".bin2hex(Utils::crc16(substr($buffer,0,-2)))."\n";
      //echo "! ".bin2hex(substr($buffer,-2))."\n";
      if (substr(bin2hex($buffer), 0, 4) == "0103" or substr(bin2hex($buffer), 0, 4) == "0104" or substr(bin2hex($buffer), 0, 4) == "0403" or substr(bin2hex($buffer), 0, 4) == $Input["DeviceID"].$Input["BefehlFunctionCode"]) {
        //  Falls zu viel Daten kommen, abschneiden!
        $data["lenght"] = hexdec(substr(bin2hex($buffer), 4, 2));
        $buffer = substr($buffer, 0, ($data["lenght"] + 5));
      }
      elseif (substr(bin2hex($buffer), 0, 4) == strtolower($Input["DeviceID"].$Input["BefehlFunctionCode"])) {
        if ($Input["BefehlFunctionCode"] == "10") {
          // Es wurde ein befehl gesendet... Response ist OK
          if ($Input["DeviceID"].$Input["BefehlFunctionCode"].$Input["RegisterAddress"].$Input["RegisterCount"] == substr(bin2hex($buffer), 0, 12)) {
            $data["ok"] = true;
            // echo "Befehl OK ..\n";
            break;
          }
        }
        $data["lenght"] = hexdec(substr(bin2hex($buffer), 4, 2));
        $buffer = substr($buffer, 0, ($data["lenght"] + 5));
      }
      Log::write("==> [ ".bin2hex($buffer)." ]", " ", 9);
      if (substr(bin2hex($buffer), 0, 4) == $Input["DeviceID"]."83" or substr(bin2hex($buffer), 0, 4) == $Input["DeviceID"]."84") {
        $data["lenght"] = 1;
        $data["data"] = 0;
        $data["ok"] = true;
        // echo "Fehler! ..\n";
        break;
      }
      if (bin2hex(Utils::crc16(substr($buffer, 0, - 2))) == bin2hex(substr($buffer, - 2))) {
        $data["address"] = substr(bin2hex($buffer), 0, 2);
        $data["functioncode"] = substr(bin2hex($buffer), 2, 2);
        if ($data["functioncode"] == $Input["BefehlFunctionCode"]) { // eingefügt 18.04.2021
          if (isset($data["lenght"])) {
            if (($data["lenght"] + 5) == strlen($buffer)) {
              $data["lenght"] = substr(bin2hex($buffer), 4, 2);
              $data["data"] = substr(bin2hex($buffer), 6, hexdec($data["lenght"]) * 2);
              $data["ok"] = true;
              $data["raw"] = bin2hex($buffer);
              // echo $data["data"]." ..\n";
              Log::write("OK> [ ".$data["data"]." ]", " ", 9);
              break;
            }
            else {
              $data["ok"] = false;
            }
          }
        }
      }
      if ((substr(bin2hex($buffer), 0, 3) <> "010" and substr(bin2hex($buffer), 0, 3) <> "040") or strlen($buffer) > (hexdec(substr(bin2hex($buffer), 4, 2)) + 6)) {
        Log::write("F > [ ".bin2hex($buffer)." ]", " ", 9);
        fgets($USB, 1000);
        break;
      }
      usleep(10000);
    }
    stream_set_blocking($USB, true);
    if ($data["ok"] == true) {
      return $data;
    }
    Log::write("Lesefehler > [ ".bin2hex($buffer)." ]", " ", 5);
    return false;
  }
}
?>