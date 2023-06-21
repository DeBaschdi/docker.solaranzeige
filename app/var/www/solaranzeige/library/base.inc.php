<?php

global $basedir, $funktionen;

function autoload_scan($basedir,$class) {
  $files = scandir($basedir);
  
  foreach($files as $file) {
    if ($file === '.' || $file === '..') continue;
    if (is_file($basedir."/".$file)) {
      if ($file === $class.".class.php") {
        require_once $basedir."/".$class.".class.php";
      }
    } elseif (is_dir($basedir."/".$file)) {
      autoload_scan($basedir."/".$file,$class);
    }
  }
}
function autoloader($class) {
  $basedir = dirname(__FILE__,2);
  autoload_scan($basedir,$class);
}

spl_autoload_register('autoloader');

$basedir = dirname(__FILE__,2);
require_once $basedir.'/library/funktionen.inc.php';
require_once $basedir."/config/user.config.php";

$funktionen = new funktionen();
