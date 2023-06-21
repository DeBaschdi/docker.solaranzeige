<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class Kostal {

  /**************************************************************************
  //  KOSTAL       KOSTAL       KOSTAL       KOSTAL       KOSTAL       KOSTAL
  //  KOSTAL       KOSTAL       KOSTAL       KOSTAL       KOSTAL       KOSTAL
  //  Auslesen des Kostal Wechselrichter
  //
  **************************************************************************/
  public static function kostal_com_lesen($COM, $Input = "") {
    $Antwort = "";
    $TransactionIdentifier = "0001";
    $ProtocilIdentifier = "0000";
    $MessageLenght = str_pad(dechex(strlen($Input) / 2), 4, "0", STR_PAD_LEFT);
    $sendenachricht = hex2bin($TransactionIdentifier.$ProtocilIdentifier.$MessageLenght.$Input);
    $rc = fwrite($COM, $sendenachricht);
    stream_set_timeout($COM, 50); // Timeout 50 Sekunden
    $i = 0;
    do {
      // Manche Geräte senden die Daten in Häppchen.
      $Antwort .= bin2hex(fread($COM, 1000)); // 1000 Bytes lesen, Timeout 50 Sekunden
      usleep(1000);
      $Laenge = ((hexdec(substr($Antwort, 8, 4)) * 2) + 12); // 12 = Header Länge
      $i++;
      Log::write("Antwort = ".$Antwort, "o  ", 8);
      // **************  Eingebaut 14.08.2021 ******************************/
      if ($Antwort == "") {
        $i = 200;
        Log::write("Modbus: Timeout", "o  ", 6);
      }
      // *******************************************************************/
    } while (strlen($Antwort) < $Laenge and $i < 200);
    // **************  Eingebaut 14.08.2021 ********************************/
    if ($i >= 200) {
      Log::write("Modbus: Keine Häppchen empfangen", "o  ", 6);
      $Antwort = "";
    }
    // *********************************************************************/
    return $Antwort;
  }

  /*************************************************************************/
  public static function kostal_register_lesen($COM1, $Register, $Laenge, $Typ) {
    $GeraeteAdresse = "47"; // Dec 71
    $Befehl = "03";
    $rc = Kostal::kostal_com_lesen($COM1, $GeraeteAdresse.$Befehl.$Register.$Laenge);
    $Daten["Register"] = $Register;
    $Daten["RawDaten"] = $rc;
    $Daten["Transaction"] = substr($rc, 0, 4);
    $Daten["Protocol"] = substr($rc, 4, 4);
    $Daten["Laenge"] = substr($rc, 8, 4);
    $Daten["Adresse"] = substr($rc, 12, 2);
    $Daten["Befehl"] = substr($rc, 14, 2);
    $Daten["DatenLaenge"] = hexdec(substr($rc, 16, 2));
    $Daten["Wert"] = 0;
    if ($Daten["Transaction"] == "0001" and $Daten["Adresse"] == "47" and $Daten["Befehl"] == "03") {
      //  Prüfen ob der Wert richtig sein kann.
      switch ($Typ) {
        case "String":
          $Daten["Wert"] = Utils::Hex2String(substr($rc, 18, $Daten["DatenLaenge"]));
          break;
        case "U16-1":
          $Daten["Wert"] = hexdec(substr($rc, 18, $Daten["DatenLaenge"] * 2));
          break;
        case "U16":
          $Daten["Wert"] = hexdec(substr($rc, 18, $Daten["DatenLaenge"]));
          // $Daten["Wert"] = hexdec(substr($rc,22,$Daten["DatenLaenge"]).substr($rc,18,$Daten["DatenLaenge"]));
          break;
        case "S16":
          $Daten["Wert"] = hexdec(substr($rc, 18, $Daten["DatenLaenge"]));
          if ($Daten["Wert"] > 32767) {
            $Daten["Wert"] = $Daten["Wert"] - 65536;
          }
          break;
        case "Float":
          $Daten["Wert"] = round($this->hex2float(substr($rc, 22, $Daten["DatenLaenge"]).substr($rc, 18, $Daten["DatenLaenge"])), 2);
          break;
        case "U32":
          $Daten["Wert"] = hexdec(substr($rc, 18, $Daten["DatenLaenge"] * 2));
          break;
        case "U64":
          $Daten["Wert"] = hexdec(substr($rc, 18, $Daten["DatenLaenge"] * 2));
          break;
        case "String2":
          $Daten["Wert"] = Utils::Hex2String(trim(substr($rc, 18, $Daten["DatenLaenge"] * 2)));
          break;
        case "Hex":
          $Daten["Wert"] = hexdec(substr($rc, 18, $Daten["DatenLaenge"] * 2));
          break;
          //Korrekte Startposition für den Piko
        case "Float_Piko":
          $Daten["Wert"] = round(Utils::hex2float(substr($rc, 18, $Daten["DatenLaenge"]).substr($rc, 18, $Daten["DatenLaenge"])), 2);
          break;
        default:
          $Daten["Wert"] = substr($rc, 18, $Daten["DatenLaenge"] * 2);
          break;
      }
    }
    return $Daten;
  }

  /************************************************************************/
  public static function kostal_auslesen($USB, $Input, $Laenge) {
    stream_set_blocking($USB, false);
    $BefehlBin = hex2bin($Input);
    fputs($USB, $BefehlBin);
    usleep(50000); // 0,05 Sekunden warten
    $buffer = ""; // Buffer leeren
    for ($i = 1; $i < 10; $i++) {
      $buffer .= fgets($USB, 4092);
      usleep(1000);
      $Antwort = bin2hex($buffer);
      if (strlen($Antwort) >= ($Laenge * 2)) {
        $Antwort = substr($Antwort, strpos($Antwort, "05"));
        if (strpos($Antwort, "00", ($Laenge * 2 - 2))) {
          $Antwort = substr($Antwort, 0, strpos($Antwort, "00", ($Laenge * 2 - 2)) + 2);
        }
      }
      // echo "\n";
      // echo $i." ".$Input."\n";
      // echo $i." ".$Antwort."\n";
      // echo "\n";
      if (substr($Antwort, 0, 2) == "05" and strlen($Antwort) == $Laenge * 2) {
        stream_set_blocking($USB, true);
        return $Antwort;
      }
      else {
        usleep(20000);
      }
    }
    $BefehlBin = hex2bin($Input);
    fputs($USB, $BefehlBin);
    usleep(50000); // 0,05 Sekunden warten
    $buffer = ""; // Buffer leeren
    for ($i = 1; $i < 10; $i++) {
      $buffer .= fgets($USB, 4092);
      usleep(1000);
      $Antwort = bin2hex($buffer);
      if (strlen($Antwort) >= ($Laenge * 2)) {
        $Antwort = substr($Antwort, strpos($Antwort, "05"));
        if (strpos($Antwort, "00", ($Laenge * 2 - 2))) {
          $Antwort = substr($Antwort, 0, strpos($Antwort, "00", ($Laenge * 2 - 2)) + 2);
        }
      }
      if (substr($Antwort, 0, 2) == "05" and strlen($Antwort) == $Laenge * 2) {
        stream_set_blocking($USB, true);
        return $Antwort;
      }
      else {
        usleep(20000);
      }
    }
    stream_set_blocking($USB, true);
    return false;
  }

  /************************************************************************/
  public static function kostal_umwandlung($Wert) {
    $Ergebnis = "";
    switch (strlen($Wert)) {
      case 2:
        $Ergebnis = hexdec(substr($Wert, 0, 2));
        break;
      case 4:
        $Ergebnis = hexdec(substr($Wert, 2, 2).substr($Wert, 0, 2));
        break;
      case 8:
        $Ergebnis = hexdec(substr($Wert, 6, 2).substr($Wert, 4, 2).substr($Wert, 2, 2).substr($Wert, 0, 2));
        break;
    }
    return $Ergebnis;
  }
}
?>