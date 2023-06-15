#!/usr/bin/php
<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2015-2016]  [Ulrich Kunz]
//
//  Dieses Programm ist freie Software. Sie können es unter den Bedingungen
//  der GNU General Public License, wie von der Free Software Foundation
//  veröffentlicht, weitergeben und/oder modifizieren, entweder gemäß
//  Version 3 der Lizenz oder (nach Ihrer Option) jeder späteren Version.
//
//  Die Veröffentlichung dieses Programms erfolgt in der Hoffnung, daß es
//  Ihnen von Nutzen sein wird, aber OHNE IRGENDEINE GARANTIE, sogar ohne
//  die implizite Garantie der MARKTREIFE oder der VERWENDBARKEIT FÜR EINEN
//  BESTIMMTEN ZWECK. Details finden Sie in der GNU General Public License.
//
//  Ein original Exemplar der GNU General Public License finden Sie hier:
//  http://www.gnu.org/licenses/
//
//  Dies ist ein Programmteil des Programms "Solaranzeige"
//
//  Es dient dem Auslesen des SMA Tripower und Andere Wechselrichter über die
//  LAN Schnittstelle.
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
//  Gerätetype:
//  9015 => "SB 700",
//  9016 => "SB 700U",
//  9017 => "SB 1100",
//  9018 => "SB 1100U",
//  9019 => "SB 1100LV",
//  9020 => "SB 1700",
//  9021 => "SB 1900TLJ",
//  9022 => "SB 2100TL",
//  9023 => "SB 2500",
//  9024 => "SB 2800",
//  9025 => "SB 2800i",
//  9026 => "SB 3000",
//  9027 => "SB 3000US",
//  9028 => "SB 3300",
//  9029 => "SB 3300U",
//  9030 => "SB 3300TL",
//  9031 => "SB 3300TL HC",
//  9032 => "SB 3800",
//  9033 => "SB 3800U",
//  9034 => "SB 4000US",
//  9035 => "SB 4200TL",
//  9036 => "SB 4200TL HC",
//  9037 => "SB 5000TL",
//  9038 => "SB 5000TLW",
//  9039 => "SB 5000TL HC",
//  9066 => "SB 1200",
//  9067 => "STP 10000TL-10",
//  9068 => "STP 12000TL-10",
//  9069 => "STP 15000TL-10",
//  9076 => "SB 5000TL-21",
//  9070 => "STP 17000TL-10",
//  9084 => "WB 3600TL-20",
//  9085 => "WB 5000TL-20",
//  9086 => "SB 3800US-10",
//  9098 => "STP 5000TL-20",
//  9099 => "STP 6000TL-20",
//  9100 => "STP 7000TL-20",
//  9101 => "STP 8000TL-10",
//  9102 => "STP 9000TL-20",
//  9103 => "STP 8000TL-20",
//  9104 => "SB 3000TL-JP-21",
//  9105 => "SB 3500TL-JP-21",
//  9106 => "SB 4000TL-JP-21",
//  9107 => "SB 4500TL-JP-21",
//  9108 => "SCSMC",
//  9109 => "SB 1600TL-10",
//  9131 => "STP 20000TL-10",
//  9139 => "STP 20000TLHE-10",
//  9140 => "STP 15000TLHE-10",
//  9157 => "Sunny Island 2012",
//  9158 => "Sunny Island 2224",
//  9159 => "Sunny Island 5048",
//  9160 => "SB 3600TL-20",
//  9168 => "SC630HE-11",
//  9169 => "SC500HE-11",
//  9170 => "SC400HE-11",
//  9171 => "WB 3000TL-21",
//  9172 => "WB 3600TL-21",
//  9173 => "WB 4000TL-21",
//  9174 => "WB 5000TL-21",
//  9175 => "SC 250",
//  9176 => "SMA Meteo Station",
//  9177 => "SB 240-10",
//  9171 => "WB 3000TL-21",
//  9172 => "WB 3600TL-21",
//  9173 => "WB 4000TL-21",
//  9174 => "WB 5000TL-21",
//  9179 => "Multigate-10",
//  9180 => "Multigate-US-10",
//  9181 => "STP 20000TLEE-10",
//  9182 => "STP 15000TLEE-10",
//  9183 => "SB 2000TLST-21",
//  9184 => "SB 2500TLST-21",
//  9185 => "SB 3000TLST-21",
//  9186 => "WB 2000TLST-21",
//  9187 => "WB 2500TLST-21",
//  9188 => "WB 3000TLST-21",
//  9189 => "WTP 5000TL-20",
//  9190 => "WTP 6000TL-20",
//  9191 => "WTP 7000TL-20",
//  9192 => "WTP 8000TL-20",
//  9193 => "WTP 9000TL-20",
//  9223 => "Sunny Island 6.0H",
//  9254 => "Sunny Island 3324",
//  9255 => "Sunny Island 4.0M",
//  9256 => "Sunny Island 4248",
//  9257 => "Sunny Island 4248U",
//  9258 => "Sunny Island 4500",
//  9259 => "Sunny Island 4548U",
//  9260 => "Sunny Island 5.4M",
//  9261 => "Sunny Island 5048U",
//  9262 => "Sunny Island 6048U",
//  9278 => "Sunny Island 3.0M",
//  9279 => "Sunny Island 4.4M",
//  9281 => "STP 10000TL-20",
//  9282 => "STP 11000TL-20",
//  9283 => "STP 12000TL-20",
//  9284 => "STP 20000TL-30",
//  9285 => "STP 25000TL-30",
//  9301 => "SB1.5-1VL-40",
//  9302 => "SB2.5-1VL-40",
//  9303 => "SB2.0-1VL-40",
//  9304 => "SB5.0-1SP-US-40",
//  9305 => "SB6.0-1SP-US-40",
//  9306 => "SB8.0-1SP-US-40",
//  9307 => "Energy Meter",
//  9313 => "SB50.0-3SP-40",
//  9319 => "SB3.0-1AV-40 (Sunny Boy 3.0 AV-40)",
//  9320 => "SB3.6-1AV-40 (Sunny Boy 3.6 AV-40)",
//  9321 => "SB4.0-1AV-40 (Sunny Boy 4.0 AV-40)",
//  9322 => "SB5.0-1AV-40 (Sunny Boy 5.0 AV-40)",
//  9324 => "SBS1.5-1VL-10 (Sunny Boy Storage 1.5)",
//  9325 => "SBS2.0-1VL-10 (Sunny Boy Storage 2.0)",
//  9326 => "SBS2.5-1VL-10 (Sunny Boy Storage 2.5)",
//  9327 => "SMA Energy Meter",
//  9331 => "SI 3.0M-12 (Sunny Island 3.0M)",
//  9332 => "SI 4.4M-12 (Sunny Island 4.4M)",
//  9333 => "SI 6.0H-12 (Sunny Island 6.0H)",
//  9334 => "SI 8.0H-12 (Sunny Island 8.0H)",
//  9335 => "SMA Com Gateway",
//  9336 => "STP 15000TL-30",
//  9337 => "STP 17000TL-30",
//  9338 => "SUNNY TRIPOWER CORE1",
//  9344 => "STP4.0-3AV-40 (Sunny Tripower 4.0)",
//  9345 => "STP5.0-3AV-40 (Sunny Tripower 5.0)",
//  9346 => "STP6.0-3AV-40 (Sunny Tripower 6.0)",
//  9347 => "STP8.0-3AV-40 (Sunny Tripower 8.0)",
//  9348 => "STP10.0-3AV-40 (Sunny Tripower 10.0)",
//  9356 => "SBS3.7-1VL-10 (Sunny Boy Storage 3.7)",
//  9358 => "SBS5.0-10 (Sunny Boy Storage 5.0)",
//  9366 => "STP3.0-3AV-40 (Sunny Tripower 3.0)",
//  9401 => "SB3.0-1AV-41 (Sunny Boy 3.0 AV-41)",
//  9402 => "SB3.6-1AV-41 (Sunny Boy 3.6 AV-41)",
//  9403 => "SB4.0-1AV-41 (Sunny Boy 4.0 AV-41)",
//  9404 => "SB5.0-1AV-41 (Sunny Boy 5.0 AV-41)",
//  9405 => "SB6.0-1AV-41 (Sunny Boy 6.0 AV-41)",
//
//
//
//  8000 => "Alle Geräte",
//  8001 => "Solar-Wechselrichter",
//  8002 => "Wind-Wechselrichter",
//  8007 => "Batterie-Wechselrichter",
//  8009 => "Wechselrichter"
//  8033 => "Verbraucher",
//  8064 => "Sensorik allgemein",
//  8065 => "Stromzähler",
//  8128 => "Kommunikationsprodukte",
//
*****************************************************************************/
$path_parts = pathinfo($argv[0]);
$Pfad = $path_parts['dirname'];
if (!is_file($Pfad."/1.user.config.php")) {
  // Handelt es sich um ein Multi Regler System?
  require($Pfad."/user.config.php");
}

require_once($Pfad."/phpinc/funktionen.inc.php");
if (!isset($funktionen)) {
  $funktionen = new funktionen();
}
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}

$Tracelevel = 7;  //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$Start = time();  // Timestamp festhalten
$funktionen->log_schreiben("-------------   Start  sma_wr.php    -------------------------- ","|--",6);

$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale(LC_TIME,"de_DE.utf8");
$funktionen->log_schreiben( "SMA: ".$WR_IP." Port: ".$WR_Port." GeräteID: ".$WR_Adresse, "   ", 7 );


//  Hardware Version ermitteln.
$Teile =  explode(" ",$Platine);
if ($Teile[1] == "Pi") {
  $Version = trim($Teile[2]);
  if ($Teile[3] == "Model") {
    $Version .= trim($Teile[4]);
    if ($Teile[5] == "Plus") {
      $Version .= trim($Teile[5]);
    }
  }
}
$funktionen->log_schreiben("Hardware Version: ".$Version,"o  ",8);

switch($Version) {
  case "2B":
  break;
  case "3B":
  break;
  case "3BPlus":
  break;
  case "4B":
  break;
  default:
  break;
}


/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//
*****************************************************************************/
$StatusFile = $Pfad."/database/".$GeraeteNummer.".WhProTag.txt";
if (file_exists($StatusFile)) {
  /***************************************************************************
  //  Daten einlesen ...
  ***************************************************************************/
  $aktuelleDaten["WattstundenGesamtHeute"] = file_get_contents($StatusFile);
  $aktuelleDaten["WattstundenGesamtHeute"] = round($aktuelleDaten["WattstundenGesamtHeute"],2);
  $funktionen->log_schreiben("WattstundenGesamtHeute: ".$aktuelleDaten["WattstundenGesamtHeute"],"   ",8);
  if (empty($aktuelleDaten["WattstundenGesamtHeute"])){
      $aktuelleDaten["WattstundenGesamtHeute"] = 0;
  }
  if (date("H:i") == "00:00" or date("H:i") == "00:01") {   // Jede Nacht 0 Uhr
    $aktuelleDaten["WattstundenGesamtHeute"] = 0;       //  Tageszähler löschen
    $rc = file_put_contents($StatusFile,"0");
    $funktionen->log_schreiben("WattstundenGesamtHeute gelöscht.","    ",5);
  }
}
else {
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;
  /***************************************************************************
  //  Inhalt der Status Datei anlegen.
  ***************************************************************************/
  $rc = file_put_contents($StatusFile,"0");
  if ($rc === false) {
    $funktionen->log_schreiben("Konnte die Datei kwhProTag_ax.txt nicht anlegen.","   ",5);
  }
}




$COM1 = fsockopen($WR_IP, $WR_Port, $errno, $errstr, 5);   // 5 = Timeout in Sekunden
if (!is_resource($COM1)) {
  $funktionen->log_schreiben("Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port,"XX ",3);
  $funktionen->log_schreiben("Exit.... ","XX ",9);
  goto Ausgang;
}

$i = 1;
do {
  $funktionen->log_schreiben("Die Daten werden ausgelesen...","+  ",9);

  /****************************************************************************
  //  Ab hier wird der Wechselrichter ausgelesen.
  //
  // function modbus_register_lesen($COM1,$Register,$Laenge,$Typ,$UnitID,$Befehl="03")
  //
  // Auf UnitID und Befehl achten!   UnitID muss 03 sein. Ist hier fest vergeben.
  //  Befehl 03 = single Byte read
  //  UnitID 03 = default
  ****************************************************************************/
  $aktuelleDaten["KeineSonne"] = false;  // Dummy
  $aktuelleDaten["Betriebszustand"] = 16777213;

  $rc = $funktionen->modbus_register_lesen($COM1,"30005","0002","U32","03");
  $aktuelleDaten["Seriennummer"] = $rc["Wert"];
  if (trim($aktuelleDaten["Seriennummer"]) == false) {
    $funktionen->log_schreiben(print_r($rc,1),"!  ",6);
  }

  $rc = $funktionen->modbus_register_lesen($COM1,"30007","0002","U32","03");
  $aktuelleDaten["LiveBit"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen($COM1,"30051","0002","U32","03");
  $aktuelleDaten["Geraeteklasse"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen($COM1,"30053","0002","U32","03");
  $aktuelleDaten["Geraetetype"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen($COM1,"30059","0002","U32","03");
  $aktuelleDaten["Softwarepaket"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen($COM1,"30201","0002","U32","03");
  $aktuelleDaten["Geraetestatus"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen($COM1,"30529","0002","S32","03");
  $aktuelleDaten["Wh_Gesamt"] = ($rc["Wert"]);
  $rc = $funktionen->modbus_register_lesen($COM1,"30535","0002","U32","03");
  $aktuelleDaten["Wh_GesamtHeute"] = ($rc["Wert"]);
  $rc = $funktionen->modbus_register_lesen($COM1,"30583","0002","U32","03");
  $aktuelleDaten["Einspeisung_Wh"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen($COM1,"30581","0002","U32","03");
  $aktuelleDaten["Bezug_Wh"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen($COM1,"30865","0002","S32","03");
  $aktuelleDaten["AC_Leistung_Bezug"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen($COM1,"30867","0002","S32","03");
  $aktuelleDaten["AC_Leistung_Einspeisung"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen($COM1,"30803","0002","U32","03");
  $aktuelleDaten["AC_Frequenz"] = ($rc["Wert"]/100);
  $rc = $funktionen->modbus_register_lesen($COM1,"30775","0002","S32","03");
  $aktuelleDaten["AC_Leistung"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen($COM1,"30777","0002","S32","03");
  $aktuelleDaten["AC_Wirkleistung_R"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen($COM1,"30779","0002","S32","03");
  $aktuelleDaten["AC_Wirkleistung_S"] = $rc["Wert"];
  $rc = $funktionen->modbus_register_lesen($COM1,"30781","0002","S32","03");
  $aktuelleDaten["AC_Wirkleistung_T"] = $rc["Wert"];

  if ($aktuelleDaten["Geraeteklasse"] == 8001) {
    // 8001 = Wechselrichter
    // Gerätetyp:
    // 9366: SUNNY TRIPOWER 3.0 (STP3.0-3AV-40)
    // 9344: SUNNY TRIPOWER 4.0 (STP4.0-3AV-40)
    // 9345: SUNNY TRIPOWER 5.0 (STP5.0-3AV-40)
    // 9346: SUNNY TRIPOWER 6.0 (STP6.0-3AV-40)
    $aktuelleDaten["Modell"] = "Tripower";
  }
  elseif ($aktuelleDaten["Geraeteklasse"] == 8007) {
    //  8007 = Batterie Wechselrichter
    //  Gerätetyp
    //  9332, 9333, 9334, 9474, 9475, 9476
    $aktuelleDaten["Modell"] = "Island";
  }
  elseif ($aktuelleDaten["Geraeteklasse"] == 8009) {
    //  8009 = Hybrid-Wechselrichter (DevClss9)
    //  Gerätetyp
    //  19048: SUNNY TRIPOWER 5.0 SE (STP5.0-3SE-40)
    //  19049: SUNNY TRIPOWER 6.0 SE (STP6.0-3SE-40)
    //  19050: SUNNY TRIPOWER 8.0 SE (STP8.0-3SE-40)
    //  19051: SUNNY TRIPOWER 10.0 SE (STP10.0-3SE-40)
    $aktuelleDaten["Modell"] = "Tripower SE";
  }
  else {
    $funktionen->log_schreiben("Die Geräteklasse ist unbekannt: ".$aktuelleDaten["Geraeteklasse"],"   ",2);
    $funktionen->log_schreiben("Ausgang! ","   ",2);
    return;
  }


  if ($aktuelleDaten["Modell"]  == "Tripower" or $aktuelleDaten["Modell"]  == "Tripower SE") {
    // Wechselrichter oder Hybrid-Wechselrichter
    $rc = $funktionen->modbus_register_lesen($COM1,"30217","0002","U32","03");
    $aktuelleDaten["Netz-Schuetz"] = $rc["Wert"];
    $rc = $funktionen->modbus_register_lesen($COM1,"40497","0010","String","03");
    $aktuelleDaten["MAC"] = trim($rc["Wert"]);
    $rc = $funktionen->modbus_register_lesen($COM1,"30977","0002","U32","03");
    $aktuelleDaten["AC_Strom_R"] = ($rc["Wert"]/100);
    $rc = $funktionen->modbus_register_lesen($COM1,"30979","0002","U32","03");
    $aktuelleDaten["AC_Strom_S"] = ($rc["Wert"]/100);
    $rc = $funktionen->modbus_register_lesen($COM1,"30981","0002","U32","03");
    $aktuelleDaten["AC_Strom_T"] = ($rc["Wert"]/100);
    $rc = $funktionen->modbus_register_lesen($COM1,"30783","0002","U32","03");
    $aktuelleDaten["AC_Spannung_R"] = ($rc["Wert"]/100);
    $rc = $funktionen->modbus_register_lesen($COM1,"30785","0002","U32","03");
    $aktuelleDaten["AC_Spannung_S"] = ($rc["Wert"]/100);
    $rc = $funktionen->modbus_register_lesen($COM1,"30787","0002","U32","03");
    $aktuelleDaten["AC_Spannung_T"] = ($rc["Wert"]/100);
    $rc = $funktionen->modbus_register_lesen($COM1,"30769","0002","S32","03");
    $aktuelleDaten["DC_Strom1"] = ($rc["Wert"]/1000);
    $rc = $funktionen->modbus_register_lesen($COM1,"30957","0002","S32","03");
    $aktuelleDaten["DC_Strom2"] = ($rc["Wert"]/1000);
    $rc = $funktionen->modbus_register_lesen($COM1,"30771","0002","S32","03");
    $aktuelleDaten["DC_Spannung1"] = ($rc["Wert"]/100);
    $rc = $funktionen->modbus_register_lesen($COM1,"30959","0002","S32","03");
    $aktuelleDaten["DC_Spannung2"] = ($rc["Wert"]/100);
    $rc = $funktionen->modbus_register_lesen($COM1,"30773","0002","S32","03");
    $aktuelleDaten["DC_Leistung1"] = $rc["Wert"];
    $aktuelleDaten["KeineSonne"] = $rc["KeineSonne"];
    $rc = $funktionen->modbus_register_lesen($COM1,"30961","0002","S32","03");
    $aktuelleDaten["DC_Leistung2"] = $rc["Wert"];
    $aktuelleDaten["KeineSonne"] = $rc["KeineSonne"];

    $aktuelleDaten["DC_Strom3"] = 0;                            // hinzugefügt 28.8.2020
    $aktuelleDaten["DC_Strom4"] = 0;                            // hinzugefügt 28.8.2020
    $aktuelleDaten["DC_Strom5"] = 0;                            // hinzugefügt 04.9.2020
    $aktuelleDaten["DC_Strom6"] = 0;                            // hinzugefügt 04.9.2020
    $aktuelleDaten["DC_Spannung3"] = 0;                         // hinzugefügt 28.8.2020
    $aktuelleDaten["DC_Spannung4"] = 0;                         // hinzugefügt 28.8.2020
    $aktuelleDaten["DC_Spannung5"] = 0;                         // hinzugefügt 04.9.2020
    $aktuelleDaten["DC_Spannung6"] = 0;                         // hinzugefügt 04.9.2020
    $aktuelleDaten["DC_Leistung3"] = 0;                         // hinzugefügt 28.8.2020
    $aktuelleDaten["DC_Leistung4"] = 0;                         // hinzugefügt 28.8.2020
    $aktuelleDaten["DC_Leistung5"] = 0;                         // hinzugefügt 04.9.2020
    $aktuelleDaten["DC_Leistung6"] = 0;                         // hinzugefügt 04.9.2020

    if ($aktuelleDaten["Geraetetype"] == "9347"  or $aktuelleDaten["Geraetetype"] == "9348" or $aktuelleDaten["Geraetetype"] == "9338" or $aktuelleDaten["Geraetetype"] == "9491") {  // Tripower 8.0,10.0 und CORE1 25.5.2023
      $rc = $funktionen->modbus_register_lesen($COM1,"30963","0002","S32","03");   // hinzugefügt 28.8.2020
      $aktuelleDaten["DC_Strom3"] = ($rc["Wert"]/1000);                            // hinzugefügt 28.8.2020
      $rc = $funktionen->modbus_register_lesen($COM1,"30969","0002","S32","03");   // hinzugefügt 28.8.2020
      $aktuelleDaten["DC_Strom4"] = ($rc["Wert"]/1000);                            // hinzugefügt 28.8.2020
      $rc = $funktionen->modbus_register_lesen($COM1,"30965","0002","S32","03");   // hinzugefügt 28.8.2020
      $aktuelleDaten["DC_Spannung3"] = ($rc["Wert"]/100);                          // hinzugefügt 28.8.2020
      $rc = $funktionen->modbus_register_lesen($COM1,"30971","0002","S32","03");   // hinzugefügt 28.8.2020
      $aktuelleDaten["DC_Spannung4"] = ($rc["Wert"]/100);                          // hinzugefügt 28.8.2020
      $rc = $funktionen->modbus_register_lesen($COM1,"30967","0002","S32","03");   // hinzugefügt 28.8.2020
      $aktuelleDaten["DC_Leistung3"] = $rc["Wert"];                                // hinzugefügt 28.8.2020
      $rc = $funktionen->modbus_register_lesen($COM1,"30973","0002","S32","03");   // hinzugefügt 28.8.2020
      $aktuelleDaten["DC_Leistung4"] = $rc["Wert"];                                // hinzugefügt 28.8.2020
    }
    if ($aktuelleDaten["Geraetetype"] == "9338") {  // Tripower CORE1 letzte Änderung 11.3.2021
      $rc = $funktionen->modbus_register_lesen($COM1,"31209","0002","S32","03");   // hinzugefügt 04.9.2020
      $aktuelleDaten["DC_Strom5"] = ($rc["Wert"]/1000);                            // hinzugefügt 04.9.2020
      $rc = $funktionen->modbus_register_lesen($COM1,"31215","0002","S32","03");   // hinzugefügt 04.9.2020
      $aktuelleDaten["DC_Strom6"] = ($rc["Wert"]/1000);                            // hinzugefügt 04.9.2020
      $rc = $funktionen->modbus_register_lesen($COM1,"31211","0002","S32","03");   // hinzugefügt 04.9.2020
      $aktuelleDaten["DC_Spannung5"] = ($rc["Wert"]/100);                          // hinzugefügt 04.9.2020
      $rc = $funktionen->modbus_register_lesen($COM1,"31217","0002","S32","03");   // hinzugefügt 04.9.2020
      $aktuelleDaten["DC_Spannung6"] = ($rc["Wert"]/100);                          // hinzugefügt 04.9.2020
      $rc = $funktionen->modbus_register_lesen($COM1,"31213","0002","S32","03");   // hinzugefügt 04.9.2020
      $aktuelleDaten["DC_Leistung5"] = $rc["Wert"];                                // hinzugefügt 04.9.2020
      $rc = $funktionen->modbus_register_lesen($COM1,"31219","0002","S32","03");   // hinzugefügt 04.9.2020
      $aktuelleDaten["DC_Leistung6"] = $rc["Wert"];                                // hinzugefügt 04.9.2020
      $rc = $funktionen->modbus_register_lesen($COM1,"30983","0002","U32","03");   // hinzugefügt 28.8.2020
      $aktuelleDaten["DC_Leistung_"] = ($rc["Wert"]);                              // hinzugefügt 28.8.2020
    }
    $rc = $funktionen->modbus_register_lesen($COM1,"30231","0002","S32","03");
    $aktuelleDaten["Max_Wirkleistung"] = $rc["Wert"];
    $rc = $funktionen->modbus_register_lesen($COM1,"30211","0002","S32","03");
    $aktuelleDaten["nextAktion"] = $rc["Wert"];
    $rc = $funktionen->modbus_register_lesen($COM1,"33001","0002","S32","03");
    $aktuelleDaten["Standbystatus"] = $rc["Wert"];
    $rc = $funktionen->modbus_register_lesen($COM1,"33003","0002","S32","03");
    $aktuelleDaten["Betriebsstatus"] = $rc["Wert"];
    $rc = $funktionen->modbus_register_lesen($COM1,"30953","0002","S32","03");
    $aktuelleDaten["Temperatur"] = ($rc["Wert"]/10);
    $rc = $funktionen->modbus_register_lesen($COM1,"40029","0002","U32","03");
    $aktuelleDaten["Betriebszustand"] = ($rc["Wert"]);


    $aktuelleDaten["DC_Leistung"] = $aktuelleDaten["DC_Leistung1"] + $aktuelleDaten["DC_Leistung2"] + $aktuelleDaten["DC_Leistung3"] + $aktuelleDaten["DC_Leistung4"]  + $aktuelleDaten["DC_Leistung5"] + $aktuelleDaten["DC_Leistung6"];

    if ($aktuelleDaten["KeineSonne"]) {
      $aktuelleDaten["Betriebszustand"] = 0;
      $aktuelleDaten["AC_Leistung_Einspeisung"] = 0;
      $aktuelleDaten["AC_Leistung_Bezug"] = 0;
    }


  }

  if ($aktuelleDaten["Modell"] == "Island" or $aktuelleDaten["Modell"] == "Tripower SE") {
    // Batterie Wechselrichter oder Hybrid-Wechselrichter
    $rc = $funktionen->modbus_register_lesen($COM1,"30845","0002","U32","03");
    $aktuelleDaten["SOC"] = ($rc["Wert"]);
    $rc = $funktionen->modbus_register_lesen($COM1,"30851","0002","U32","03");
    $aktuelleDaten["Batteriespannung"] = ($rc["Wert"]/100);
    $rc = $funktionen->modbus_register_lesen($COM1,"30859","0002","U32","03");
    $aktuelleDaten["Batterieladung"] = ($rc["Wert"]);
    $rc = $funktionen->modbus_register_lesen($COM1,"30869","0002","S32","03");
    $aktuelleDaten["DC_Leistung"] = ($rc["Wert"]);
    $rc = $funktionen->modbus_register_lesen($COM1,"30843","0002","S32","03");
    $aktuelleDaten["Batteriestrom"] = ($rc["Wert"]/1000);
    $rc = $funktionen->modbus_register_lesen($COM1,"30849","0002","S32","03");
    $aktuelleDaten["Temperatur"] = ($rc["Wert"]/10);
    $rc = $funktionen->modbus_register_lesen($COM1,"30955","0002","U32","03");
    $aktuelleDaten["Batteriestatus"] = ($rc["Wert"]);
    $rc = $funktionen->modbus_register_lesen($COM1,"30879","0002","U32","03");
    $aktuelleDaten["Betriebszustand"] = ($rc["Wert"]);
    $rc = $funktionen->modbus_register_lesen($COM1,"30847","0002","U32","03");
    $aktuelleDaten["SOE"] = ($rc["Wert"]);
  }

  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/



  /***************************************************************************
  //  Wenn kein PV Strom mehr kommt...
  ***************************************************************************/
  // für SB2.5 -> nur DC_1 für "Keine Sonne" relevant
  if ($aktuelleDaten["Geraetetype"] != "9302") {
    if ($aktuelleDaten["KeineSonne"] == true) {
      if (date("i") % 20 != 0) {
        // Alle 20 Minuten trotzdem abspeichern.
        break;
      }
    }
  }
  else {
    $funktionen->log_schreiben("Keine Sonne für DC2 deaktiviert, Gerätetyp: ".$aktuelleDaten["Geraetetype"]," ",6);
  }
  //
  $funktionen->log_schreiben("Geräteklasse: ".$aktuelleDaten["Geraeteklasse"],"*- ",7);
  $funktionen->log_schreiben("Gerätetyp: ".$aktuelleDaten["Geraetetype"],"*- ",7);
  $funktionen->log_schreiben(print_r($aktuelleDaten,1),"*- ",8);


  /***************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  ***************************************************************************/
  $FehlermeldungText = "";


  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);

  if ($i == 1)
    $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists ("/var/www/html/sma_wr_math.php")) {
    include 'sma_wr_math.php';  // Falls etwas neu berechnet werden muss.
  }



  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT) {
    $funktionen->log_schreiben("MQTT Daten zum [ $MQTTBroker ] senden.","   ",1);
    require($Pfad."/mqtt_senden.php");
  }

  /****************************************************************************
  //  Zeit und Datum
  ****************************************************************************/
  //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
  $aktuelleDaten["Timestamp"] = time();
  $aktuelleDaten["Monat"]     = date("n");
  $aktuelleDaten["Woche"]     = date("W");
  $aktuelleDaten["Wochentag"] = strftime("%A",time());
  $aktuelleDaten["Datum"]     = date("d.m.Y");
  $aktuelleDaten["Uhrzeit"]   = date("H:i:s");



  /****************************************************************************
  //  InfluxDB  Zugangsdaten ...stehen in der user.config.php
  //  falls nicht, sind das hier die default Werte.
  ****************************************************************************/
  $aktuelleDaten["InfluxAdresse"] = $InfluxAdresse;
  $aktuelleDaten["InfluxPort"] = $InfluxPort;
  $aktuelleDaten["InfluxUser"] =  $InfluxUser;
  $aktuelleDaten["InfluxPassword"] = $InfluxPassword;
  $aktuelleDaten["InfluxDBName"] = $InfluxDBName;
  $aktuelleDaten["InfluxDaylight"] = $InfluxDaylight;
  $aktuelleDaten["InfluxDBLokal"] = $InfluxDBLokal;
  $aktuelleDaten["InfluxSSL"] = $InfluxSSL;
  $aktuelleDaten["Demodaten"] = false;


  $funktionen->log_schreiben(print_r($aktuelleDaten,1),"*- ",10);


  /*********************************************************************
  //  Daten werden in die Influx Datenbank gespeichert.
  //  Lokal und Remote bei Bedarf.
  *********************************************************************/
  if ($InfluxDB_remote) {
    // Test ob die Remote Verbindung zur Verfügung steht.
    if ($RemoteDaten) {
      $rc = $funktionen->influx_remote_test();
      if ($rc) {
        $rc = $funktionen->influx_remote($aktuelleDaten);
        if ($rc) {
          $RemoteDaten = false;
        }
      }
      else {
        $RemoteDaten = false;
      }
    }
    if ($InfluxDB_local) {
      $rc = $funktionen->influx_local($aktuelleDaten);
    }
  }
  else {
    $rc = $funktionen->influx_local($aktuelleDaten);
  }




  if (is_file($Pfad."/1.user.config.php")) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (7 - (time() - $Start));
    $funktionen->log_schreiben("Multi-Regler-Ausgang. ".$Zeitspanne,"   ",2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    $funktionen->log_schreiben("Schleife: ".($i)." Zeitspanne: ".(floor((56 - (time() - $Start))/($Wiederholungen-$i+1))),"   ",9);
    sleep(floor((56 - (time() - $Start))/($Wiederholungen-$i+1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben("Schleife ".$i." Ausgang...","   ",5);
    break;
  }

  $i++;
} while (($Start + 54) > time());


if ($aktuelleDaten["KeineSonne"] == false) {


  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $aktuelleDaten["Solarspannung"] = $aktuelleDaten["Solarspannung1"];
    $funktionen->log_schreiben("Daten werden zur HomeMatic übertragen...","   ",8);
    require($Pfad."/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    $funktionen->log_schreiben("Nachrichten versenden...","   ",8);
    require($Pfad."/meldungen_senden.php");
  }

  $funktionen->log_schreiben("OK. Datenübertragung erfolgreich.","   ",7);
}
else {
  $funktionen->log_schreiben("Keine gültigen Daten empfangen.","!! ",6);
}



/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Achtung! Dieser Wert wird jeden Tag um Mitternacht auf 0 gesetzt.
//  Leistung in Watt / 60 Minuten, da 60 mal in der Stunde addiert wird.
*****************************************************************************/
if (file_exists($StatusFile) and isset($aktuelleDaten["DC_Leistung"])) {
  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Tag = Wh
  ***************************************************************************/
  $whProTag = file_get_contents($StatusFile);
  // aktuellen Wert in die Datei schreiben:
  $whProTag = ($whProTag + ($aktuelleDaten["DC_Leistung"]/60));
  $rc = file_put_contents($StatusFile,$whProTag);
  $funktionen->log_schreiben("Solarleistung: ".$aktuelleDaten["DC_Leistung"]." Watt -  WattstundenGesamtHeute: ".round($whProTag,2),"   ",5);
}


Ausgang:

fclose($COM1);

$funktionen->log_schreiben("-------------   Stop   sma_wr.php    -------------------------- ","|--",6);

return;






?>
