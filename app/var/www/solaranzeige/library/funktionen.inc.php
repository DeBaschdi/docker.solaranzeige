<?php

//  Funktionen für das Solaranzeigen Programm geschrieben von Ulrich Kunz 2016-2021
//
//
class funktionen {

  function funktionenIn() {
    return true;
  }

  function openUSB($Device) {
    return USB::openUSB($Device);
  }

  function closeUSB($Device) {
    return USB::closeUSB($Device);
  }

  function _exec($cmd, & $out = null) {
    return Utils::_exec($cmd, $out);
  }

  /******************************************************************
  //
  //
  ******************************************************************/
  function usb_lesen($Device, $Befehl = '') {
    return USB::usb_lesen($Device, $Befehl);
  }

  function tageslicht($Ort = 'hamburg') {
    return Utils::tageslicht($Ort);
  }

  /**************************************************************************
  //  Test, ob die remote Influx Datenbank zu erreichen ist
  //  Es wird die Influx Datenbank kontaktiert!
  //  Auf dem remote Server wird kein Apache benötigt.
  **************************************************************************/
  function influx_remote_test() {
    return InfluxDB::influx_remote_test();
  }

  /**************************************************************************
  //  Daten in die lokale Influx Datenbank schreiben
  //
  //
  **************************************************************************/
  function influx_local($daten) {
    return InfluxDB::influx_local($daten);
  }

  /**************************************************************************
  //  Daten in die remote Influx Datenbank schreiben
  //
  //
  **************************************************************************/
  function influx_remote($daten) {
    return InfluxDB::influx_remote($daten);
  }

  /**************************************************************************
  //  Daten aus einer Datenbank lesen
  //
  //
  **************************************************************************/
  function influx_datenbank($db, $init, $query) {
    return InfluxDB::influx_datenbank($db, $init, $query);
  }

  /**************************************************************************
  //  Hier werden Demo Daten vorbereitet.
  //
  //
  **************************************************************************/
  function demo_daten_erzeugen($Regler) {
    return InfluxDB::demo_daten_erzeugen($Regler);
  }

  /**************************************************************************
  //  Hier werden Demo Daten erzeugt.
  //
  //
  **************************************************************************/
  function query_erzeugen($daten) {
    return InfluxDB::query_erzeugen($daten);
  }

  /**************************************************************************
  //  MQTT Fuktionen zum senden der mqtt Messages
  //  Call back functions for MQTT library
  ***************************************************************/
  function mqtt_connect($r) {
    MQTT::mqtt_connect($r);
  }

  function mqtt_publish() {
    MQTT::mqtt_publish();
  }

  function mqtt_disconnect() {
    MQTT::mqtt_disconnect();
  }

  function mqtt_subscribe() {
    MQTT::mqtt_subscribe();
  }

  function mqtt_message($message) {
    MQTT::mqtt_message($message);
  }

  /******************************************************************************
  //  Umwandlung der Binärdaten in lesbare Form
  ******************************************************************************/
  function solarxxl_daten($daten, $Times = false, $Negativ = false) {
    return SolarXXL::solarxxl_daten($daten, $Times, $Negativ);
  }

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
  function tracer_auslesen($USB, $Input) {
    return Tracer::tracer_auslesen($USB, $Input);
  }

  function us2000_daten_entschluesseln($Daten) {
    return US2000::us2000_daten_entschluesseln($Daten);
  }

  /****************************************************************************
  //  Hier wird das Polytech US2000 Protokoll ausgelesen.
  //  Bei schnelleren Raspberry Pi Rechnern müssen die zeitlichen Funktionen
  //  eventuell angepasst werden.
  //
  ****************************************************************************/
  function us2000_auslesen($USB, $Input) {
    return US2000::us2000_auslesen($USB, $Input);
  }

  /******************************************************************
  //
  //  Auslesen des MPPSolar Wechselrichter      MPI
  //
  ******************************************************************/
  function mpi_usb_lesen($USB, $Input) {
    return MPI::mpi_usb_lesen($USB, $Input);
  }

  /**********************************************************
  //   MPPSolar Daten entschlüsseln
  //
  **********************************************************/
  function mpi_entschluesseln($Befehl, $Daten) {
    return MPI::mpi_entschluesseln($Befehl, $Daten);
  }

  /******************************************************************
  //
  //  Auslesen des Fronius Symo Wechselrichter
  //  $Benutzer =  UserID:Kennwort
  ******************************************************************/
  function read($host, $port, $DataString, $Header = "", $Benutzer = "") {
    return Utils::read($host, $port, $DataString, $Header, $Benutzer);
  }

  function fronius_getFehlerString($error) {
    return Fronius::fronius_getFehlerString($error);
  }

  /****************************************************************************
  //  Victron Geräte    Victron Geräte    Victron Geräte    Victron Geräte
  //  Hier wird das VE.Direct Protokoll ausgelesen.
  //  Bitte die usleep() Funktionen nicht verändern, zumindest erst nach
  //  längeren Testreihen.
  //  Bei schnelleren Raspberry Pi Rechnern müssen die zeitlichen Funktionen
  //  eventuell angepasst werden.
  //
  ****************************************************************************/
  function ve_regler_auslesen($USB, $Input) {
    return VE::ve_regler_auslesen($USB, $Input);
  }

  /**************************************************************************
  //  Hier wird das VE.Direct Protokoll entschlüsselt.
  //
  **************************************************************************/
  function ve_ergebnis_auswerten($Daten) {
    return VE::ve_ergebnis_auswerten($Daten);
  }

  function ve_fehlermeldung($ErrorCode) {
    return VE::ve_fehlermeldung($ErrorCode);
  }

  /**************************************************************************
  //  Joulie 16    Joulie 16    Joulie 16    Joulie 16    Joulie 16
  //  BMS Joulie 16 Routinen
  //
  **************************************************************************/
  function joulie_auslesen($USB, $Input) {
    return Joulie::joulie_auslesen($USB, $Input);
  }

  function joulie_zahl($Wert) {
    return Joulie::joulie_zahl($Wert);
  }

  function joulie_outb($Wert) {
    return Joulie::joulie_outb($Wert);
  }

  /**************************************************************************
  //  Pushover, WhatsApp und Signal Meldungen senden
  //
  //
  **************************************************************************/
  function po_influxdb_lesen($abfrage) {
    return PushoverTools::po_influxdb_lesen($abfrage);
  }

  /**************************************************************************
  //
  **************************************************************************/
  function po_send_message($APP_ID, $UserToken, $Message, $Bild = 0, $DevName = "", $Messenger = "pushover") {
    return PushoverTools::po_send_message($APP_ID, $UserToken, $Message, $Bild, $DevName, $Messenger);
  }

  /**************************************************************************
  //
  **************************************************************************/
  function po_messageControl($Meldung, $Nummer = 0, $GeraeteNummer = 1, $Messenger = "pushover") {
    return PushoverTools::po_messageControl($Meldung, $Nummer, $GeraeteNummer, $Messenger);
  }

  /*************************************************************************
  //
  //  SolarEdge    SolarEdge       SolarEdge       SolarEdge       SolarEdge
  //
  *************************************************************************/
  function solaredge_lesen($COM, $Input = "") {
    return SolarEdge::solaredge_lesen($COM, $Input);
  }

  /*************************************************************************
  //  SolarEdge    SolarEdge       SolarEdge       SolarEdge       SolarEdge
  *************************************************************************/
  function solaredge_faktor($wert, $hex) {
    return SolarEdge::solaredge_faktor($wert, $hex);
  }

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
  function renogy_auslesen($USB, $Input) {
    return Renogy::renogy_auslesen($USB, $Input);
  }

  /******************************************************************************
  //  Umwandlung der Binärdaten in lesbare Form
  ******************************************************************************/
  function renogy_daten($daten, $Dezimal = true, $Times = false) {
    return Renogy::renogy_daten($daten, $Dezimal, $Times);
  }

  /******************************************************************
  //  AEconversion    AEconversion    AEconversion    AEconversion
  //
  //  AEconversion Inverter auslesen
  //
  ******************************************************************/
  function aec_inverter_lesen($Device, $Befehl = '') {
    return AEC::aec_inverter_lesen($Device, $Befehl);
  }

  /******************************************************************
  //  InfiniSolar V Serie     InfiniSolar V Serie      InfiniSolar V
  //  Auslesen des InfiniSolar V Serie Wechselrichter
  //
  ******************************************************************/
  function infini_lesen($USB, $Input, $Senden = false) {
    return Infini::infini_lesen($USB, $Input, $Senden);
  }

  /**************************************************************
  //   InfiniSolar Daten entschlüsseln
  //
  **************************************************************/
  function infini_entschluesseln($Befehl, $Daten) {
    return Infini::infini_entschluesseln($Befehl, $Daten);
  }

  /****************************************************************
  //   Die IVT Regler USB Schnittstelle auslesen
  //
  ****************************************************************/
  function ivt_lesen($Device, $Befehl = '') {
    return IVT::ivt_lesen($Device, $Befehl);
  }

  /******************************************************************
  //   Die IVT Daten entschlüsseln
  //
  ******************************************************************/
  function ivt_entschluesseln($rc) {
    return IVT::ivt_entschluesseln($rc);
  }

  /**************************************************************************
  //  KOSTAL       KOSTAL       KOSTAL       KOSTAL       KOSTAL       KOSTAL
  //  KOSTAL       KOSTAL       KOSTAL       KOSTAL       KOSTAL       KOSTAL
  //  Auslesen des Kostal Wechselrichter
  //
  **************************************************************************/
  function kostal_com_lesen($COM, $Input = "") {
    return Kostal::kostal_com_lesen($COM, $Input);
  }

  /*************************************************************************/
  function kostal_register_lesen($COM1, $Register, $Laenge, $Typ) {
    return Kostal::kostal_register_lesen($COM1, $Register, $Laenge, $Typ);
  }

  /************************************************************************/
  function kostal_auslesen($USB, $Input, $Laenge) {
    return Kostal::kostal_auslesen($USB, $Input, $Laenge);
  }

  /************************************************************************/
  function kostal_umwandlung($Wert) {
    return Kostal::kostal_umwandlung($Wert);
  }

  /************************************************************************/
  function cobs_decoder($Wert) {
    return Utils::cobs_decoder($Wert);
  }

  /****************************************************************************
  //  MODBUS      MODBUS      MODBUS      MODBUS      MODBUS      MODBUS
  //
  //  Funktionen für Modbus Geräte      MODBUS TCP
  //
  ****************************************************************************/
  function modbus_register_lesen($COM1, $Register, $Laenge, $Typ, $GeraeteAdresse, $Befehl = "03") {
    return ModBus::modbus_register_lesen($COM1, $Register, $Laenge, $Typ, $GeraeteAdresse, $Befehl);
  }

  /****************************************************************************
  //  STECA      STECA      STECA      STECA      STECA      STECA      STECA
  //
  //  Funktionen für die STECA Solarregler
  //
  ****************************************************************************/
  function steca_daten($HEXDaten) {
    return STECA::steca_daten($HEXDaten);
  }

  /*****************************************************************************
  //  Hier wird das Datenfeld entschlüsselt. Dazu wird die D_ServiceID,
  //  der D_ServiceCode und die eigentlichen D_Daten benötigt.
  //
  *****************************************************************************/
  function steca_FrameErstellen($FrameDaten) {
    return STECA::steca_FrameErstellen($FrameDaten);
  }

  /*****************************************************************************
  //  Hier wird das Datenfeld entschlüsselt. Dazu wird die D_ServiceID,
  //  der D_ServiceCode und die eigentlichen D_Daten benötigt.
  //
  *****************************************************************************/
  function steca_entschluesseln($ServiceID, $ServiceCode, $StecaDaten, $ReglerModell = "MPPT6000") {
    return STECA::steca_entschluesseln($ServiceID, $ServiceCode, $StecaDaten, $ReglerModell);
  }

  /****************************************************************************
  //  Hier wird der RS845 Bus ausgelesen. Diese Routine ist sehr zeitkritisch
  //  Bitte die usleep() Funktionen nicht verändern, zumindest erst nach
  //  längeren Testreihen.
  //  Bei schnelleren Raspberry Pi Rechnern müssen die zeitlichen Funktionen
  //  eventuell angepasst werden.
  ****************************************************************************/
  function steca_auslesen($USB, $Befehl) {
    return STECA::steca_auslesen($USB, $Befehl);
  }

  /******************************************************************
  //
  //  Auslesen des SolarMax S Serie Wechselrichter
  //
  ******************************************************************/
  function com_lesen($COM, $WR_Adresse, $Input = "") {
    return SolarMax::com_lesen($COM, $WR_Adresse, $Input);
  }

  /************************************************************************/
  function nachricht_bauen($adr, $befehl) {
    return Utils::nachricht_bauen($adr, $befehl);
  }

  /******************************************************************
  //
  //  Auslesen des Labornetzteil   JT-DPM8600
  //
  ******************************************************************/
  function ln_lesen($USB, $WR_Adresse, $Input = "") {
    return Labornetzteil::ln_lesen($USB, $WR_Adresse, $Input);
  }

  /**************************************************************************
  //  SDM630
  //  $Input["DeviceID"]
  //  $Input["BefehlFunctionCode"]
  //  $Input["RegisterAddress"]
  //  $Input["RegisterCount"]
  **************************************************************************/
  function sdm_auslesen($USB, $Input, $Studer = false) {
    return SDM::sdm_auslesen($USB, $Input, $Studer);
  }

  /************************************************************************/
  function eSmart3_auslesen($Device, $Befehl = '') {
    return eSmart3::eSmart3_auslesen($Device, $Befehl);
  }

  /************************************************************************/
  function eSmart3_ergebnis_auswerten($Daten) {
    return eSmart3::eSmart3_ergebnis_auswerten($Daten);
  }

  /******************************************************************
  //   DMODBUS vom Delta Wechselrichter lesen.
  //
  //      "Start" => "02"
  //      "Byte1" => "05",
  //      "ID" => "07",
  //      "Byte2" => "02",
  //      "Command" => "0000",
  //      "CRC" => "0000",
  //      "Stop" => "03");
  ******************************************************************/
  function delta_lesen($USB, $Input) {
    return Delta::delta_lesen($USB, $Input);
  }

  function alpha_auslesen($Device, $Befehl = '') {
    return Delta::alpha_auslesen($Device, $Befehl);
  }

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
  function phocos_pv18_auslesen($USB, $Input, $Timer = "50000") {
    return Phocos::phocos_pv18_auslesen($USB, $Input, $Timer);
  }

  /**************************************************************************
  //  Solaredge WND Smart Meter      (2021 geschrieben von Egmont Schreiter )
  //  $Input["DeviceID"]
  //  $Input["BefehlFunctionCode"] type int, 3 oder 4, ist egal
  //  $Input["RegisterAddress"] type int
  //  $Input["RegisterCount"] type int
  //  Type: 1 - 16Bit int
  //        2 - 32Bit-float
  //        3 - 32Bit int
  **************************************************************************/
  function sem_auslesen($USB, $Input, $returntype = 1) {
    return SolarEdge::sem_auslesen($USB, $Input, $returntype);
  }

  /**************************************************************************
  //  HTTP POST / GET Request
  //  [Request"] = POST
  //  ["Data"]   = Daten
  //  ["Header"] = Header
  //  ["Port"]   = Port
  //  ["Benutzer"] = UserID:Kennwort
  //
  **************************************************************************/
  function http_read($abfrage) {
    return Utils::http_read($abfrage);
  }

  /*************************/
  function senec($Daten) {
    return Utils::senec($Daten);
  }

  /****************************************************************************
  //  MODBUS      MODBUS      MODBUS      MODBUS      MODBUS      MODBUS
  //
  //  Allgemeine Funktionen für Modbus Geräte
  //
  //
  ****************************************************************************/
  function modbus_tcp_lesen($COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase = 600000) {
    return ModBus::modbus_tcp_lesen($COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase);
  }

  /****************************************************************************
  //  MODBUS      MODBUS      MODBUS      MODBUS      MODBUS      MODBUS
  //
  //  Allgemeine Funktionen für Modbus Geräte
  //
  // modbus_tcp_schreiben( $COM1, "01", "10", "1012", "0006", "40c0000040c0000040c00000");
  ****************************************************************************/
  function modbus_tcp_schreiben($COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $Befehlsdaten, $Timebase = 600000) {
    return ModBus::modbus_tcp_schreiben($COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $Befehlsdaten, $Timebase);
  }

  /****************************************************************************
  //  RCT Wechselrichter         RCT Wechselrichter         RCT Wechselrichter
  //
  //
  ****************************************************************************/
  function rct_auslesen($COM, $Command, $Laenge, $ID, $Form = "float") {
    return RCT::rct_auslesen($COM, $Command, $Laenge, $ID, $Form);
  }

  /**************************************************************************
  //  MODBUS RTU auslesen
  //  $Input["DeviceID"]
  //  $Input["BefehlFunctionCode"]
  //  $Input["RegisterAddress"]
  //  $Input["RegisterCount"]
  //  $Input["DatenTyp"]
  //  $Input[""]
  **************************************************************************/
  function modbus_rtu_auslesen($USB, $Input) {
    return ModBus::modbus_rtu_auslesen($USB, $Input);
  }

  // #############################################################################################
  //function sendUSBJK($USB, $FrameHex) {
  function sendUSB($USB, $FrameHex) {
    return USB::sendUSB($USB, $FrameHex);
  }

  function checksumJK32($data) {
    return Utils::checksumJK32($data);
  }

  // #############################################################################################
  /**************************************************************************
  //  Log Eintrag in die Logdatei schreiben
  //  $LogMeldung = Die Meldung ISO Format
  //  $Loglevel=5   Loglevel 1-10   10 = Trace
  **************************************************************************/
  function log_schreiben($LogMeldung, $Titel = "   ", $Loglevel = 5, $UTF8 = 0) {
    return Log::write($LogMeldung, $Titel, $Loglevel, $UTF8);
  }

  function _hex2float($num) {
    return Utils::_hex2float($num);
  }

  function hex2float32($number) {
    return Utils::hex2float32($number);
  }

  function hex2float64($strHex) {
    return Utils::hex2float64($strHex);
  }

  /**************************************************************************
  //  HEX / String  Umwandlungsroutinen
  //
  **************************************************************************/
  function hex2str($hex) {
    return Utils::hex2str($hex);
  }

  /************************************************************/
  function hexdecs($hex) {
    return Utils::hexdecs($hex);
  }

  /*  Dezimal Umwandlung in Bitwise                          */
  //  Der Eingangswert muss Dezimal sein!
  function d2b($dec, $n = 16) {
    return Utils::d2b($dec, $n);
  }

  /**************************************************************************
  //  unterschiedliche CRC Berechnungen
  //
  //
  **************************************************************************/
  function crc8($ptr) {
    return Utils::crc8($ptr);
  }

  /*****************************************************************************
  // D_Daten + D_ServiceCode = CRCData
  //
  *****************************************************************************/
  function crc8Data($ptr) {
    return Utils::crc8Data($ptr);
  }

  function crc16_steca($ptr) {
    return Utils::crc16_steca($ptr);
  }

  function checksum16($msg) {
    return Utils::checksum16($msg);
  }

  function crc16($data) {
    return Utils::crc16($data);
  }

  function crc16_us2000($Input) {
    return Utils::crc16_us2000($Input);
  }

  function CRC16Normal($buffer) {
    return Utils::CRC16Normal($buffer);
  }

  function Hex2String($hex) {
    return Utils::Hex2String($hex);
  }

  function string2hex($string) {
    return Utils::string2hex($string);
  }

  function VE_CRC($Daten) {
    return Utils::VE_CRC($Daten);
  }

  function eSmart3_CRC($Daten) {
    return Utils::eSmart3_CRC($Daten);
  }

  function hex2float($number) {
    return Utils::hex2float($number);
  }

  function onebytechecksum($string) {
    return Utils::onebytechecksum($string);
  }

  function kelvinToCelsius($temperature) {
    return Utils::kelvinToCelsius($temperature);
  }

  function crc16_arc($data) {
    return Utils::crc16_arc($data);
  }

  function reversebyte($byte) {
    return Utils::reversebyte($byte);
  }

  function reversebits($cc) {
    return Utils::reversebits($cc);
  }

  function calcCRC(string $command) {
    return Utils::calcCRC($command);
  }

}
?>