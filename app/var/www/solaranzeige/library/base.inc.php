<?php

require_once 'funktionen.inc.php';
require_once 'Utils.class.php';
require_once 'Log.class.php';
require_once 'InfluxDB.class.php';
require_once 'ModBus.class.php';
require_once 'MQTT.class.php';


if (!isset($funktionen)) {
  $funktionen = new funktionen();
}

function getEnvAsString(string $key, string $defaultValue) :string {
  $value = getenv($key);
  if ($value === FALSE) return $defaultValue;
  return $value;
}

function getEnvAsBoolean(string $key, bool $defaultValue) :bool {
  $value = getenv($key);
  if ($value === FALSE) return $defaultValue;
  return ($value === "true" || $value === "1" || $value === "on" || $value === "yes");
}

function getEnvAsInteger(string $key, int $defaultValue) :int {
  $value = getenv($key);
  if ($value === FALSE) return $defaultValue;
  return intval($value);
}

function getEnvAsFloat(string $key, float $defaultValue) :float {
  $value = getenv($key);
  if ($value === FALSE) return $defaultValue;
  return floatval($value);
}

function getEnvPlattform() :string {
  If (is_file("/sys/firmware/devicetree/base/model")) {
    //  Auf welcher Platine läuft die Software?
    $Platine = file_get_contents("/sys/firmware/devicetree/base/model");
  } else {
    $Platine = "Docker Image ".getEnvAsString("SA_VERSION","0.0.0");
  }
  
  return $Platine;
}