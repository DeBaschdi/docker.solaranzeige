<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class USB {

  public static function openUSB($Device) {
    $res = fopen($Device, "r+");
    //stream_set_timeout ( $res, 1, 0 );
    return $res;
  }

  public static function closeUSB($Device) {
    $res = fclose($Device);
    return $res;
  }

  /******************************************************************
  //
  //
  ******************************************************************/
  public static function usb_lesen($Device, $Befehl = '') {
    $CR = "\n"; // CR LF -> eventuell löschen
    $Antwort = "";
    stream_set_blocking($Device, false); // Es wird auf keine Daten gewartet.
    if (empty($Befehl)) {
      $Antwort = fgets($Device, 8192);
      $Dauer = 0; // nur 1 Durchlauf
    }
    else {
      $Dauer = 4; // 4 Sekunden
      $rc = fwrite($Device, $Befehl.$CR);
      usleep(500000); // 1/2 Sekunde
    }
    $Timestamp = time();
    $Ergebnis = "";
    $Antwort = "";
    do {
      $Antwort = fgets($Device, 4096);
      if (trim($Antwort) == "") {
        // echo "Nichts empfangen .. \n";
        usleep(50000);
        $Antwort = "";
      }
      else {
        $Ergebnis .= $Antwort;
        Log::write(time()." ".bin2hex($Ergebnis)."\n", 10);
        if (substr($Ergebnis, 0, 18) == "solar.pc.senddata." and strlen(bin2hex($Ergebnis)) == 74) {
          $Dauer = 0; // Ausgang vorbereiten..
        }
      }
    } while (($Timestamp + $Dauer) > time());
    stream_set_blocking($Device, true);
    if (strlen(bin2hex($Ergebnis)) == 74) {
      return $Ergebnis;
    }
    return false;
  }

###################################################################
  //function sendUSBJK($USB, $FrameHex) {
  public static function sendUSB($USB, $FrameHex) {
    //  Log::write( "Bin  drin!", "!! ", 3 );
    $MaxTries = 20;
    $buffer = "";
    $rcv = array();
    $rcv["ok"] = false;
    $rcv["response"] = "";
    stream_set_blocking($USB, false);
    //echo "opend USB:".$USB."\n";
    //Log::write( "opend USB:".$USB, "   ", 7 );
    if ($buffer = fread($USB, 1024)) {
      Log::write("unexpected frame:".bin2hex($buffer), "   ", 1);
    }
    $FrameBin = hex2bin($FrameHex);
    $FrameBin = $FrameBin.utils::checksumJK32($FrameBin);
    Log::write("out> : ".bin2hex($FrameBin), "   ", 8);
    fputs($USB, $FrameBin);
    for ($i = 1; $i <= $MaxTries; $i++) {
      usleep(10000);
      $buffer = fread($USB, 1024);
      if ($buffer) {
        $rcv["response"] .= bin2hex($buffer);
        $rcv["ok"] = true;
      }
      elseif ($rcv["ok"] == true) {
        break;
      }
    }
    if ($rcv["ok"] != true) {
      Log::write("no answer from USB in ".($i * 10)."ms)!", "   ", 1);
    }
    //  Log::write("in< (".($i*10)."ms): ".$rcv["response"], "   ", 8 );
    stream_set_blocking($USB, true);
    return $rcv;
  }
}
?>