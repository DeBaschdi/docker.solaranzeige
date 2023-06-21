<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class MPI {

  /******************************************************************
  //
  //  Auslesen des MPPSolar Wechselrichter      MPI
  //
  ******************************************************************/
  public static function mpi_usb_lesen($USB, $Input) {
    stream_set_blocking($USB, false);
    $Antwort = "";
    //  Die folgenden 2 Zeilen dienen der Syncronisation der Hidraw
    //  Schnittstelle und sollten bestehen bleiben.
    // fputs( $USB, "\r" );  // Ausgeschaltet 4.5.2022
    fgets($USB, 50); // 50
    //  Der Befehl wird gesendet...
    //  Die Geräte sind empfindlich beim Empfang
    if (strlen($Input) > 24) {
      fputs($USB, substr($Input, 0, 8));
      usleep(30000); // 10000
      fputs($USB, substr($Input, 8, 8));
      usleep(30000); // 10000
      fputs($USB, substr($Input, 16, 8));
      usleep(30000); // 10000
      fputs($USB, substr($Input, 24)."\r");
      usleep(30000); // 10000
    }
    elseif (strlen($Input) > 16) {
      fputs($USB, substr($Input, 0, 8));
      usleep(30000); // 10000
      fputs($USB, substr($Input, 8, 8));
      usleep(30000); // 10000
      fputs($USB, substr($Input, 16)."\r");
      usleep(30000); // 10000
    }
    elseif (strlen($Input) > 8) {
      fputs($USB, substr($Input, 0, 8));
      usleep(30000); // 10000
      fputs($USB, substr($Input, 8)."\r");
      usleep(30000); // 10000
    }
    else {
      fputs($USB, $Input."\r");
    }
    usleep(30000); // 30000
    Log::write("Befehl: ".$Input, "   ", 8);
    for ($k = 1; $k < 50; $k++) { // normal 200
      $rc = fgets($USB, 4096); // 4096
      usleep(30000); // 30000
      $Antwort .= $rc;
      // echo bin2hex($rc)."\n";
      Log::write("Antwort: ".bin2hex($rc), "   ", 8);
      if (substr($Antwort, 0, 2) == "^D" and strlen($Antwort) > 5) {
        if ((substr($Antwort, 2, 3) + 5) <= strlen($Antwort)) {
          if (strlen($Antwort) > substr($Antwort, 2, 3)) {
            $Laenge = (substr($Antwort, 2, 3) - 3);
            stream_set_blocking($USB, true);
            return substr($Antwort, 5, $Laenge);
          }
        }
      }
      if (substr($Antwort, 0, 2) == "^0") {
        // Befehl nicht erfolgreich
        break;
      }
      if (substr($Antwort, 0, 2) == "^1") {
        stream_set_blocking($USB, true);
        // Befehl erfolgreich
        return "OK";
      }
    }
    stream_set_blocking($USB, true);
    // echo bin2hex($Antwort)."\n";
    Log::write("Antwort: ".bin2hex($Antwort), "   ", 8);
    return false;
  }

  /**********************************************************
  //   MPPSolar Daten entschlüsseln
  //
  **********************************************************/
  public static function mpi_entschluesseln($Befehl, $Daten) {
    $Ergebnis = array();
    switch ($Befehl) {
      case "BATS":
        $Teile = explode(",", $Daten);
        $Ergebnis["BatterieMaxLadestromSet"] = ($Teile[0] / 10);
        $Ergebnis["BatterieMaxEntladestromSet"] = ($Teile[17]);
        break;
      case "GS":
        $Teile = explode(",", $Daten);
        $Ergebnis["Solarspannung1"] = ($Teile[0] / 10);
        $Ergebnis["Solarspannung2"] = ($Teile[1] / 10);
        $Ergebnis["Solarstrom1"] = ($Teile[2] / 10);
        $Ergebnis["Solarstrom2"] = ($Teile[3] / 10);
        $Ergebnis["Batteriespannung"] = ($Teile[4] / 10);
        $Ergebnis["Batteriekapazitaet"] = $Teile[5];
        $Ergebnis["Batteriestrom"] = ($Teile[6] / 10);
        $Ergebnis["Netzspannung_R"] = ($Teile[7] / 10);
        $Ergebnis["Netzspannung_S"] = ($Teile[8] / 10);
        $Ergebnis["Netzspannung_T"] = ($Teile[9] / 10);
        $Ergebnis["Netzfrequenz"] = ($Teile[10] / 100);
        $Ergebnis["Netzstrom_R"] = ($Teile[11] / 10);
        $Ergebnis["Netzstrom_S"] = ($Teile[12] / 10);
        $Ergebnis["Netzstrom_T"] = ($Teile[13] / 10);
        $Ergebnis["AC_Ausgangsspannung_R"] = ($Teile[14] / 10);
        $Ergebnis["AC_Ausgangsspannung_S"] = ($Teile[15] / 10);
        $Ergebnis["AC_Ausgangsspannung_T"] = ($Teile[16] / 10);
        $Ergebnis["AC_Ausgangsfrequenz"] = ($Teile[17] / 100);
        if ($Teile[18] != null) {
          $Ergebnis["AC_Ausgangsstrom_R"] = ($Teile[18] / 10);
          $Ergebnis["AC_Ausgangsstrom_S"] = ($Teile[19] / 10);
          $Ergebnis["AC_Ausgangsstrom_T"] = ($Teile[20] / 10);
        }
        else {
          $Ergebnis["AC_Ausgangsstrom_R"] = 0;
          $Ergebnis["AC_Ausgangsstrom_S"] = 0;
          $Ergebnis["AC_Ausgangsstrom_T"] = 0;
        }
        $Ergebnis["Temperatur"] = $Teile[21];
        $Ergebnis["Batterie_Temperatur"] = $Teile[23];
        break;
      case "PS":
        $Teile = explode(",", $Daten);
        $Ergebnis["Solarleistung1"] = round($Teile[0], 0);
        $Ergebnis["Solarleistung2"] = round($Teile[1], 0);
        $Ergebnis["Batterieleistung"] = $Teile[2];
        $Ergebnis["AC_Eingangsleistung_R"] = round($Teile[3], 0);
        $Ergebnis["AC_Eingangsleistung_S"] = round($Teile[4], 0);
        $Ergebnis["AC_Eingangsleistung_T"] = round($Teile[5], 0);
        $Ergebnis["AC_Eingangsleistung"] = round($Teile[6], 0);
        $Ergebnis["AC_Wirkleistung_R"] = round($Teile[7], 0);
        $Ergebnis["AC_Wirkleistung_S"] = round($Teile[8], 0);
        $Ergebnis["AC_Wirkleistung_T"] = round($Teile[9], 0);
        $Ergebnis["AC_Wirkleistung"] = round($Teile[10], 0);
        $Ergebnis["AC_Scheinleistung_R"] = round($Teile[11], 0);
        $Ergebnis["AC_Scheinleistung_S"] = round($Teile[12], 0);
        $Ergebnis["AC_Scheinleistung_T"] = round($Teile[13], 0);
        $Ergebnis["AC_Scheinleistung"] = round($Teile[14], 0);
        $Ergebnis["Ausgangslast"] = $Teile[15]; // in %
        $Ergebnis["AC_Ausgang_Status"] = $Teile[16]; // 0 = aus  1 = ein
        $Ergebnis["Solar_Status1"] = $Teile[17]; // 0 = aus  1 = ein
        $Ergebnis["Solar_Status2"] = $Teile[18]; // 0 = aus  1 = ein
        $Ergebnis["Batteriestromrichtung"] = $Teile[19]; // 0 = aus  1 = laden  2 = entladen
        $Ergebnis["WR_Stromrichtung"] = $Teile[20]; // 0 = aus  1 = AC/DC  2 = DC/AC
        $Ergebnis["Netzstromrichtung"] = $Teile[21]; // 0 = aus  1 = Eingang  2 = Ausgang
        break;
      case "CFS":
        $Teile = explode(",", $Daten);
        $Ergebnis["ErrorCodes"] = $Teile[0];
        break;
      case "PI":
        $Ergebnis["Firmware"] = $Daten;
        break;
      case "MOD":
        $Ergebnis["Modus"] = $Daten;
        break;
      case "ED".date("Ymd"):$Ergebnis["WattstundenGesamtHeute"] = $Daten;
      break;
    case "EM".date("Ym", strtotime("-1 month")):$Ergebnis["WattstundenGesamtMonat"] = $Daten;
      break;
    case "EY".date("Y", strtotime("-1 year")):$Ergebnis["WattstundenGesamtJahr"] = $Daten;
      break;
      case "ET":
        $Ergebnis["KiloWattstundenTotal"] = $Daten;
        break;
      case "DI":
        $Teile = explode(",", $Daten);
        $Ergebnis["BatterieMaxLadestrom"] = ($Teile[10] / 10);
        break;
      case "DM":
        $Ergebnis["Produkt"] = $Daten;
        break;
      case "T":
        // Hier kann Datum und Uhrzeit entschlüsselt werden
        $Ergebnis["Stunden"] = substr($Daten, 8, 2);
        $Ergebnis["Minuten"] = substr($Daten, 10, 2);
        $Ergebnis["Sekunden"] = substr($Daten, 12, 2);
        break;
      case "WS":
        $Warnungen = array();
        $k = 1;
        $Ergebnis["Warnungen"] = 0;
        $Teile = explode(",", $Daten);
        for ($i = 0; $i < 22; $i++) {
          if ($Teile[$i] == 1) {
            // Es gibt eine oder mehrere Warnungen
            $Warnungen[$k] = ($i + 1);
            $k++;
          }
        }
        $k = ($k - 1);
        if ($k == 1) {
          $Ergebnis["Warnungen"] = $Warnungen[$k];
        }
        elseif ($k > 1) {
          //  Es sind mehrere Warnungen vorhanden
          $Ergebnis["Warnungen"] = $Warnungen[rand(1, $k)];
        }
        break;
      case "MD":
        $Teile = explode(",", $Daten);
        $Ergebnis["Maschinennummer"] = $Teile[0];
        $Ergebnis["Werksangabe_VA_Leistung"] = $Teile[1];
        $Ergebnis["Werksangabe_Faktor"] = $Teile[2];
        $Ergebnis["Werksangabe_Anz_EingangsPhasen"] = $Teile[3];
        $Ergebnis["Werksangabe_Anz_AusgangsPhasen"] = $Teile[4];
        $Ergebnis["Werksangabe_Ausgangsspannung"] = ($Teile[5] / 10);
        $Ergebnis["Werksangabe_Eingangsspannung"] = ($Teile[6] / 10);
        $Ergebnis["Werksangabe_Anz_Batterien"] = (int) $Teile[7];
        $Ergebnis["Werksangabe_Batteriespannung"] = ($Teile[8] / 10);
        break;
    }
    return $Ergebnis;
  }
}
?>