<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class Log {
  ############################################################################
  /**************************************************************************
  //  Log Eintrag in die Logdatei schreiben
  //  $LogMeldung = Die Meldung ISO Format
  //  $Loglevel=5   Loglevel 1-10   10 = Trace
  **************************************************************************/
  public static function write($LogMeldung, $Titel = "   ", $Loglevel = 5, $UTF8 = 0) {
    global $Tracelevel;
    $LogDateiName = "/var/log/solaranzeige.log";
    if ($Loglevel <= $Tracelevel) {
      if ($UTF8) $LogMeldung = utf8_encode($LogMeldung);
      if ($handle = fopen($LogDateiName, 'a')) {
        //  Schreibe in die geöffnete Datei.
        //  Bei einem Fehler bis zu 3 mal versuchen.
        for ($i = 1; $i < 4; $i++) {
          $rc = fwrite($handle, date("d.m. H:i:s")." ".substr($Titel, 0, 3)."-".$LogMeldung."\n");
          if ($rc) break;
          sleep(1);
        }
        fclose($handle);
      }
    }
    return true;
  }

}
?>