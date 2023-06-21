<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class SolarMax {

  /******************************************************************
  //
  //  Auslesen des SolarMax S Serie Wechselrichter
  //
  ******************************************************************/
  public static function com_lesen($COM, $WR_Adresse, $Input = "") {
    $sendenachricht = SolarMax::nachricht_bauen($WR_Adresse, $Input);
    $rc = fwrite($COM, $sendenachricht);
    usleep(100000);
    $haeder = explode(";", fread($COM, 9)); // 9 Bytes lesen
    $laenge = (hexdec($haeder[2]) - 9);
    if ($laenge == 0) {
      Log::write("Länge = 0", "   ", 5);
      return 0;
    }
    usleep(100000);
    $antwort = fread($COM, $laenge);
    $teile = explode("|", $antwort);
    if (empty($teile[1])) {
      return 0;
    }
    $befehl = substr($teile[1], (strpos($teile[1], ":") + 1));
    $ergebnis = explode("=", $befehl);
    if ($ergebnis[0] == $Input and substr($haeder[0], 1) == sprintf("%02X", $WR_Adresse)) {
      // Der Befehl und die Adresse müssen übereinstimmen.
      return $ergebnis[1];
    }
    return 0;
  }
  
  
  /************************************************************************/
  private static function nachricht_bauen($adr, $befehl) {
    # Die Nachricht zusammenbauen
    $src = 'FB';
    $adr = sprintf('%02X', $adr);
    $len = '00';
    $cs = '0000';
    $msg = is_array($befehl) ? "64:".implode(';', $befehl):"64:".$befehl;
    $len = strlen("{".$src.";".$adr.";".$len."|".$msg."|".$cs."}");
    $len = sprintf("%02X", $len);
    $cs = Utils::checksum16($src.";".$adr.";".$len."|".$msg."|");
    $cs = sprintf("%04X", $cs);
    return "{".$src.";".$adr.";".$len."|".$msg."|".$cs."}";
  }
  
}
?>