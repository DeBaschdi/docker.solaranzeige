<?php
/******************************************************************************
//  Regler auslesen. Da es verschiedene Regler gibt wird hier der richtige
//  Script ermittelt, der ausgelesen werden soll. Die Information steht in
//  der Datei "user.config.php
******************************************************************************/
$zentralerTimestamp = time();

$basedir = dirname(__FILE__,2);
require_once($basedir."/library/base.inc.php");

$Platine = Utils::getEnvPlattform();

/****************************************************************************
//  Erst einmal prüfen ob der Script schon läuft
//  Der PHP Script darf nur einmal laufen, da sonst der COM Port besetzt ist.
****************************************************************************/

$runningScript = $_SERVER['SCRIPT_NAME'];
// echo "Scriptname: ".$runningScript;
if( !empty($runningScript) ) {
  // Pruefe wie oft das Hauptscript schon laeuft
  // Es werden auch die Parameter berücksichtigt.
  $output = shell_exec("ps ax | grep {$runningScript} | grep -v grep | grep -v bash | grep -v bin/sh| wc -l");
  // Ergebnis groesser 1: Script laeuft bereits
  // 1 wird immer geliefert, da das Script sich selbst auch sieht
  // echo "Info: Anzahl PHP Scripte laufen: ".trim($output);
  if( (int)$output > 1 ) {
    // echo "|---> Stop  PHP Script: ".basename($argv[0])." **************";
    exit;
  }
}

// $shell_befehl = "vcgencmd measure_temp | egrep -o '[0-9]*\.[0-9]*'";
// $temp = shell_exec($shell_befehl);
// $RaspiTemp = (int) $temp;

$Pfad = $basedir.'/wechselrichter';

switch ($Regler) {
  case 1:
    // Victron energy Regler  Serie BlueSolar
    require($Pfad."/ivt_solarregler.php");
  break;

  case 2:
    // Tracer Serie
    require($Pfad."/steca_solarregler.php");
  break;

  case 3:
    // Tracer Serie
    require($Pfad."/tracer_regler.php");
  break;

  case 4:
    // Victron energy Regler  Serie BlueSolar
    require($Pfad."/victron_solarregler.php");
  break;

  case 5:
    // Micro-Wechselrichter  INV250-45
    require($Pfad."/aec_wechselrichter.php");
  break;

  case 6:
    // Victron energy Batteriemonitore BMV 7xx
    require($Pfad."/bmv_serie.php");
  break;

  case 7:
    // Steca Solarix PLI 5000 Wechselrichter & Regler
    require($Pfad."/ax_wechselrichter.php");
  break;

  case 8:
    // InfiniSolar V Serie Wechselrichter
    require($Pfad."/infini_v_serie.php");
  break;

  case 9:
    // MPPSolar MPI 10kW Hybrid 3 Phasen
    require($Pfad."/mpi_3phasen_serie.php");
  break;

  case 10:
    // SolarMax S-Serien
    require($Pfad."/solarmax_s_serie.php");
  break;

  case 11:
    // Phoenix Wechselrichter von Victron
    require($Pfad."/phoenix_victron.php");
  break;

  case 12:
    // Fronius Symo Serie
    require($Pfad."/fronius_symo_serie.php");
  break;

  case 13:
    // Joulie-16 BMS von AutarcTech
    require($Pfad."/joulie_16_bms.php");
  break;

  case 14:
    // Rover von Renogy
    require($Pfad."/rover_renogy.php");
  break;

  case 15:
    // US2000B von PylonTech
    require($Pfad."/us2000_bms.php");
  break;

  case 16:
    // SolarEdge Wechselrichter mit MODBUS Zähler
    require($Pfad."/solaredge_serie.php");
  break;

  case 17:
    // KOSTAL Plenticore Wechselrichter
    require($Pfad."/kostal_plenticore.php");
  break;

  case 18:
    // S10E von E3/DC Wechselrichter
    require($Pfad."/e3dc_wechselrichter.php");
  break;

  case 19:
    // eSmart3 Laderegler
    require($Pfad."/eSmart3.php");
  break;

  case 20:
    // SolarEdge Wechselrichter ohne MODBUS Zähler
    require($Pfad."/solaredge_ohne.php");
  break;

  case 21:
    // KOSTAL Pico mit USB Anschluss
    require($Pfad."/kostal_pico.php");
  break;

  case 22:
    // KOSTAL Smart Energy Meter mit MODBUS TCP Anschluss
    require($Pfad."/kostal_meter.php");
  break;

  case 23:
    // Sonoff POW R2 mit Tasmota Firmware und MQTT Anbindung
    require($Pfad."/sonoff_mqtt.php");
  break;

  case 24:
    // Infini xKW Hybrid Wechselrichter    1 Phase
    require($Pfad."/infini_p16.php");
  break;

  case 25:
    // Sonnen Batterie
    require($Pfad."/sonnen_batterie.php");
  break;

  case 26:
    // MPPSolar 5048 MK und GK
    require($Pfad."/qpi_p30.php");
  break;

  case 27:
    // SMA Sunny Tripower
    require($Pfad."/sma_wr.php");
  break;

  case 28:
    // HRDi marlec Laderegler
    require($Pfad."/hrdi_laderegler.php");
  break;

  case 29:
    // go-e Charger Wallbox
    require($Pfad."/go-e_wallbox.php");
  break;

  case 30:
    // Keba Wallbox
    require($Pfad."/keba_wallbox.php");
  break;

  case 31:
    // Shelly 3EM
    require($Pfad."/shelly.php");
  break;

  case 32:
    // KACO Wechselrichter
    require($Pfad."/kaco_wr.php");
  break;

  case 33:
    // Labornetzteil JT-8600
    require($Pfad."/labornetzteil.php");
  break;

  case 34:
    // SDM630  Smart Meter
    require($Pfad."/SDM630_meter.php");
  break;

  case 35:
    // Wallbe Wallbox
    require($Pfad."/wallbe_wallbox.php");
  break;

  case 36:
    // Delta Wechselrichter
    require($Pfad."/delta_wechselrichter.php");
  break;

  case 37:
    // Simple EVSE Wallbox
    require($Pfad."/simple_evse.php");
  break;

  case 38:
    // Alpha ESS Wechselrichter
    require($Pfad."/alpha_ess.php");
  break;

  case 39:
    // openWB Wallbox
    require($Pfad."/openWB.php");
  break;

  case 40:
    // Phocos Wechselrichter
    require($Pfad."/phocos.php");
  break;

  case 41:
    // Pylontech US 3000 BMS
    require($Pfad."/us3000_bms.php");
  break;

  case 42:
    // Pv18 VHM Wechselrichter
    require($Pfad."/pv18_vhm_serie.php");
  break;

  case 43:
    // Senec Stromspeicher
    require($Pfad."/senec.php");
  break;

  case 44:
    // Webasto Wallbox
    require($Pfad."/webasto_wb.php");
  break;

  case 45:
    // Phocos Any-Grid
    require($Pfad."/phocos_any_grid.php");
  break;

  case 46:
    // Huawei Wechselrichter
    require($Pfad."/huawei.php");
  break;

  case 47:
    // Phoenix Contact Wallbox
    require($Pfad."/phoenix_wb.php");
  break;

  case 48:
    // Growatt Wechselrichter
    require($Pfad."/growatt.php");
  break;

  case 49:
    // Huawei SmartLogger
    require($Pfad."/huawei_SL.php");
  break;

  case 50:
    // SDM230 Zähler 1 Phase
    require($Pfad."/SDM230_meter.php");
  break;

  case 51:
    // Siemens PAC2200 3 Phasen Zähler
    require($Pfad."/pac2200_meter.php");
  break;

  case 52:
    // Goodwe Wechselrichter
    require($Pfad."/goodwe_wr.php");
  break;

  case 53:
    // Solarlog Pro 380 -Mod
    require($Pfad."/solarlog380pro.php");
  break;

  case 54:
    // SMA Energy Meter 2.0
    require($Pfad."/sma_energy.php");
  break;

  case 55:
    // Studer xtender Wechselrichter
    require($Pfad."/studer_wr.php");
  break;

  case 56:
    // Huawei M1 Modelle
    require($Pfad."/huawei_M1.php");
  break;

  case 57:
    // Daly BMS China
    require($Pfad."/daly_bms.php");
  break;

  case 58:
    // SolarEdge Ertragszaehler
    require($Pfad."/solaredge_smartmeter.php");
  break;

  case 59:
    // EASUN POWER Wechselrichter
    require($Pfad."/easun_p30.php");
  break;

  case 60:
    // Hardy Barth Wallbox
    require($Pfad."/hardy_barth.php");
  break;

  case 61:
    // SMARTPI Zähler
    require($Pfad."/smartpi.php");
  break;

  case 62:
    // Huawei Wechselrichter mit SDongle
    require($Pfad."/huawei_LAN.php");
  break;

  case 63:
    // cFos Wallbox
    require($Pfad."/cFos_wb.php");
  break;

  case 64:
    // Goodwe Wechselrichter ET Serie
    require($Pfad."/goodwe_ET.php");
  break;

  case 65:
    // RCT Wechselrichter
    require($Pfad."/rct_wr.php");
  break;

  case 66:
    // Kostal Piko CI Wechselrichter
    require($Pfad."/kostal_piko_ci.php");
  break;

  case 67:
    // Goodwe Wechselrichter  XS Serie
    require($Pfad."/goodwe_wr.php");
  break;

  case 68:
    // VARTA Pulse Neo
    require($Pfad."/varta_pulse.php");
  break;

  case 69:
    // Vestel Wallbox
    require($Pfad."/vestel_wallbox.php");
  break;

  case 70:
    // Sungrow Wechselrichter
    require($Pfad."/sungrow.php");
  break;

  case 71:
    // EASUN SMG II Wechselrichter
    require($Pfad."/easun_smg.php");
  break;

  case 72:
    // HomeMatic Gaszähler
    require($Pfad."/hm_geraet.php");
  break;

  case 73:
    // SofarSolar Wechselrichter
    require($Pfad."/sofarsolar_wr.php");
  break;

  case 74:
    // Carlo Gavazzi EM24 Meter
    require($Pfad."/em24_meter.php");
  break;

  case 75:
    // Hager Meter
    require($Pfad."/hager_meter.php");
  break;

  case 76:
    // Hoymiles Microwechselrichter
    require($Pfad."/hoymiles.php");
  break;

  case 77:
    // AX Licom Box von Effekta
    require($Pfad."/licom-box.php");
  break;

  case 78:
    // Innogy Wallbox
    require($Pfad."/innogy_wallbox.php");
  break;

  case 79:
    // IAMMETER WEM3080T
    require($Pfad."/wem3080t.php");
  break;

  case 80:
    // Solax X3 Wechselrichter
    require($Pfad."/solax_wr.php");
  break;

  case 81:
    // my-PV THOR
    require($Pfad."/my-pv-thor.php");
  break;

  case 82:
    // Solis Wechselrichter
    require($Pfad."/solis_wr.php");
  break;

  case 83:
    // JK-BMS
    require($Pfad."/JK_bms.php");
  break;

  case 84:
    // SofarSolar Wechselrichter
    require($Pfad."/sofarsolar_wr2.php");
  break;

  case 85:
    // Ökofen Pelletronic
    require($Pfad."/oekofen.php");
  break;

  case 86:
    // Cerbo GX, CCGX, Venus OS GX
    require($Pfad."/venus_gx.php");
  break;

  case 87:
    // SofarSolar Wechselrichter
    require($Pfad."/sofarsolar.php");
  break;

  case 88:
    // Ahoy DTU
    require($Pfad."/ahoy.php");
  break;

  case 89:
    // OpenDTU
    require($Pfad."/opendtu.php");
  break;

  case 90:
    // NILAN Wärmepumpe
    require($Pfad."/nilan_wp.php");
  break;

  case 91:
    // SEPLOS BMS
    require($Pfad."/seplos_bms.php");
  break;

  case 92:
    // FSP MES BMS
    require($Pfad."/fsp_mes_bms.php");
  break;

  case 93:
    // Deye Wechselrichter
    require($Pfad."/deye_wr.php");
  break;

  default:
    /************************************************************************
    //  User PHP Script, falls gewünscht oder nötig
    ************************************************************************/
    if ( file_exists ($basedir."/custom/user_device.php")) {
      Log::write("Datei 'user_device.php' gefunden.","   ",7);
      require($basedir."/custom/user_device.php"); // Vom Benutzer selber geschriebene Datei.
    } else {
      Log::write("Angegebener Regler ungültig. ".$Regler,"   ",2);
      require($basedir."/services/fehler.php");
    }
  break;
}

exit;
?>