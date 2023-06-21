<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class Fronius {

  public static function fronius_getFehlerString($error) {
    $text = "";
    switch ($error) {
      // ---- 100 ----
      case 102:
        $text = "AC-Spannung zu hoch";
        break;
      case 103:
        $text = "AC-Spannung zu gering";
        break;
      case 105:
        $text = "AC-Frequenz zu hoch";
        break;
      case 106:
        $text = "AC-Frequenz zu gering";
        break;
      case 107:
        $text = "AC-Netz nicht vorhanden";
        break;
      case 108:
        $text = "Inselbetrieb erkannt";
        break;
      case 112:
        $text = "Fehler RCMU";
        break;
      case 301:
        $text = "Überstrom AC";
        break;
      case 302:
        $text = "Überstrom DC";
        break;
      case 303:
        $text = "Übertemperatur DC Modul";
        break;
      case 304:
        $text = "Übertemperatur AC Modul";
        break;
      case 305:
        $text = "Keine Einspeisung trotz geschlossener Relais";
        break;
      case 306:
        $text = "Zu wenig PV Leistung für Einspeisung";
        break;
      case 307:
        $text = "DC Spannung zu gering für Einspeisung";
        break;
      case 308:
        $text = "Zwischenkreisspannung zu hoch";
        break;
      case 309:
        $text = "DC Eingangsspannung MPPT1 zu hoch";
        break;
      case 311:
        $text = "DC Stränge verpolt";
        break;
      case 313:
        $text = "DC Eingangsspannung MPPT2 zu hoch";
        break;
      case 314:
        $text = "Timeout Stromsensor-Kalibrierung";
        break;
      case 315:
        $text = "AC Stromsensor Fehler";
        break;
      case 316:
        $text = "InterruptCheck falin";
        break;
      case 325:
        $text = "Übertemperatur im Anschlussbreich";
        break;
      case 326:
        $text = "Lüfter 1 Fehler";
        break;
      case 327:
        $text = "Lüfter 2 Fehler";
        break;
      case 401:
        $text = "Kommunikation mit dem Leistungsteil nicht möglich";
        break;
      case 406:
        $text = "Temperatursensor AC Modul defekt (L1)";
        break;
      case 407:
        $text = "Temperatursensor AC Modul defekt (L2)";
        break;
      case 408:
        $text = "Zu hoher Gleichanteil im Versorgungsnetz gemessen";
        break;
      case 412:
        $text = "Fixspannung statt MPP-Betrieb: Einstellwert ist zu niedrig oder zu hoch";
        break;
      case 415:
        $text = "Sicherheitsschaltung durch Optionskarte oder RECERBO hat ausgelöst";
        break;
      case 416:
        $text = "Kommunikation zwischen Leistungsteil und Steuerung nicht möglich";
        break;
      case 417:
        $text = "ID-Problem der Hardware";
        break;
      case 419:
        $text = "Unique-ID Konflikt";
        break;
      case 420:
        $text = "Kommunikation mit dem Hybridmanager nicht möglich";
        break;
      case 421:
        $text = "Fehler HID-Range";
        break;
      case 425:
        $text = "Kommunikation mit dem Leistungsteil ist nicht möglich";
        break;
      case 426:
        $text = "Möglicher Hardware-Defekt";
        break;
      case 427:
        $text = "Möglicher Hardware-Defekt";
        break;
      case 428:
        $text = "Möglicher Hardware-Defekt";
        break;
      case 431:
        $text = "Software Problem";
        break;
      case 436:
        $text = "Funktions-Inkompatibilität";
        break;
      case 437:
        $text = "Leistungsteil-Problem";
        break;
      case 438:
        $text = "Funktions-Inkompatibilität";
        break;
      case 443:
        $text = "Zwischenkreis-Spannung zu gering oder unsymetrisch";
        break;
      case 445:
        $text = "Kompatibilitätsfehler";
        break;
      case 447:
        $text = "Isolationsfehler";
        break;
      case 448:
        $text = "Neutralleiter nicht angeschlossen";
        break;
      case 450:
        $text = "Guard kann nicht gefunden werden";
        break;
      case 451:
        $text = "Speicherfehler entdeckt";
        break;
      case 452:
        $text = "Kommunikationsfehler zwischen den Prozessoren";
        break;
      case 453:
        $text = "Netzspannung und Leistungsteil stimmen nicht überein";
        break;
      case 454:
        $text = "Netzspannung und Leistungsteil stimmen nicht überein";
        break;
      case 456:
        $text = "Anti-Islanding-Funktion wird nicht mehr korrekt ausgeführt";
        break;
      case 457:
        $text = "Netzrelais klebt oder die Neutralleiter-Erde-Spannung ist zu hoch";
        break;
      case 458:
        $text = "Fehler bei der Mess-Signalerfassung";
        break;
      case 459:
        $text = "Fehler bei der Erfassung des Mess-Signals für den Isolationstest";
        break;
      case 460:
        $text = "Referenz-Spannungsquelle DSP außerhalb der tolerierten Grenzen";
        break;
      case 461:
        $text = "Fehler im DSP-Datenspeicher";
        break;
      case 462:
        $text = "Fehler bei der DC-Einspeisungs-Überwachungsroutine";
        break;
      case 463:
        $text = "Polarität AC vertauscht, AC-Verbindungsstecker falsch eingesteckt";
        break;
      case 474:
        $text = "RCMU-Sensor defekt";
        break;
      case 475:
        $text = "Isolationsfehler (Verbindung zwischen Solarmodul und Erdung)";
        break;
      case 476:
        $text = "Versorgungsspannung der Treiberversorgung zu gering";
        break;
      case 479:
        $text = "Zwischenkreis-Spannungsrelais hat ausgeschaltet";
        break;
      case 480:
        $text = "Funktions-Inkompatibilität";
        break;
      case 481:
        $text = "Funktions-Inkompatibilität";
        break;
      case 482:
        $text = "Setup nach der erstmaligen Inbetriebnahme wurde abgebrochen";
        break;
      case 483:
        $text = "Spannung UDCfix beim MPP2-String liegt außerhalb des gültigen Bereichs";
        break;
      case 485:
        $text = "CAN Sende-Buffer ist voll";
        break;
      case 489:
        $text = "Permanente Überspannung am Zwischenkreis-Kondensator";
        break;
      case 502:
        $text = "Isolationsfehler an den Solarmodulen";
        break;
      case 509:
        $text = "Keine Einspeisung innerhalb der letzten 24 Stunden";
        break;
      case 515:
        $text = "Kommunikation mit Filter nicht möglich";
        break;
      case 516:
        $text = "Kommunikation mit der Speichereinheit nicht möglich";
        break;
      case 517:
        $text = "Leistungs-Derating auf Grund zu hoher Temperatur";
        break;
      case 518:
        $text = "Interne DSP-Fehlfunktion";
        break;
      case 519:
        $text = "Kommunikation mit der Speichereinheit nicht möglich";
        break;
      case 520:
        $text = "Keine Einspeisung innerhalb der letzten 24 Stunden von MPPT1";
        break;
      case 522:
        $text = "DC low String 1";
        break;
      case 523:
        $text = "DC low String 2";
        break;
      case 558:
        $text = "Funktions-Inkompatibilität";
        break;
      case 559:
        $text = "Funktions-Inkompatibilität";
        break;
      case 560:
        $text = "Leistungs-Derating wegen Überfrequenz";
        break;
      case 564:
        $text = "Funktions-Inkompatibilität";
        break;
      case 566:
        $text = "Arc Detector ausgeschaltet (z.B. bei externer LichtbogenÜberwachung)";
        break;
      case 568:
        $text = "fehlerhaftes Eingangssignal an der Multifunktions-Stromschnittstelle";
        break;
      case 572:
        $text = "Leistungslimitierung durch das Leistungsteil";
        break;
      case 573:
        $text = "Untertemperatur Warnung";
        break;
      case 581:
        $text = "Setup ?Special Purpose Utility-Interactive? (SPUI) ist aktiviert";
        break;
      case 601:
        $text = "CAN Bus ist voll";
        break;
      case 603:
        $text = "Temperatursensor AC Modul defekt (L3)";
        break;
      case 604:
        $text = "Temperatursensor DC Modul defekt";
        break;
      case 607:
        $text = "RCMU Fehler";
        break;
      case 608:
        $text = "Funktions-Inkompatibilität";
        break;
      case 701:
        $text = "Gibt Auskunft über den internen Prozessorstatus";
        break;
      case 702:
        $text = "Gibt Auskunft über den internen Prozessorstatus";
        break;
      case 703:
        $text = "Gibt Auskunft über den internen Prozessorstatus";
        break;
      case 704:
        $text = "Gibt Auskunft über den internen Prozessorstatus";
        break;
        // 706 - 716: 	$text = "Gibt Auskunft über den internen Prozessorstatus"
      case 721:
        $text = "EEPROM wurde neu initialisiert";
        break;
        // 722 - 730: 	$text = "Gibt Auskunft über den internen Prozessorstatus"
      case 731:
        $text = "Initialisierungsfehler - USB-Stick wird nicht unterstützt";
        break;
      case 732:
        $text = "Initialisierungsfehler - Überstrom am USB-Stick";
        break;
      case 733:
        $text = "Kein USB-Stick angesteckt";
        break;
      case 734:
        $text = "Update-Datei wird nicht erkannt oder ist nicht vorhanden";
        break;
      case 735:
        $text = "Nicht zum Gerät passende Update-Datei, zu alte Update-Datei";
        break;
      case 736:
        $text = "Schreib- oder Lesefehler aufgetreten";
        break;
      case 737:
        $text = "Datei konnte nicht geöffnet werden";
        break;
      case 738:
        $text = "Abspeichern einer Log-Datei nicht möglich";
        break;
      case 740:
        $text = "Initialisierungsfehler - Fehler im Dateisystem des USB-Sticks";
        break;
      case 741:
        $text = "Fehler beim Aufzeichnen von Logging-Daten";
        break;
      case 743:
        $text = "Fehler während des Updates aufgetreten";
        break;
      case 745:
        $text = "Update-Datei fehlerhaft";
        break;
      case 746:
        $text = "Fehler während des Updates aufgetreten";
        break;
      case 751:
        $text = "Uhrzeit verloren";
        break;
      case 752:
        $text = "Real Time Clock Modul Kommunikationsfehler";
        break;
      case 753:
        $text = "Interner Fehler: Real Time Clock Modul ist im Notmodus";
        break;
      case 754:
        $text = "Gibt Auskunft über den internen Prozessorstatus";
        break;
      case 755:
        $text = "Gibt Auskunft über den internen Prozessorstatus";
        break;
      case 757:
        $text = "Hardware-Fehler im Real Time Clock Modul";
        break;
      case 758:
        $text = "Interner Fehler: Real Time Clock Modul ist im Notmodus";
        break;
      case 760:
        $text = "Interner Hardware-Fehler";
        break;
        // 761 - 765: 	$text = "Gibt Auskunft über den internen Prozessorstatus"
      case 766:
        $text = "Notfall-Leistungsbegrenzung wurde aktiviert (max. 750 W)";
        break;
      case 767:
        $text = "Gibt Auskunft über den internen Prozessorstatus";
        break;
      case 768:
        $text = "Leistungsbegrenzung in den Hardware-Modulen unterschiedlich";
        break;
      case 772:
        $text = "Speichereinheit nicht verfügbar";
        break;
      case 773:
        $text = "Software-Update Gruppe 0 (ungültiges Länder-Setup)";
        break;
      case 775:
        $text = "PMC-Leistungsteil nicht verfügbar";
        break;
      case 776:
        $text = "Device-Typ ungültig";
        break;
        // 781 - 794: 	$text = "Gibt Auskunft über den internen Prozessorstatus"
      default:
        $text = "Gibt Auskunft über den internen Prozessorstatus";
        break;
    }
    return $text;
  }
}
?>