<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quick Access</title>
  <link rel="stylesheet" href="css/coffeegrinder.css">
  <link rel="stylesheet" href="css/wireframe-theme.css">
  <!-- <script>document.createElement( "picture" );</script>
  <script src="js/picturefill.min.js" class="picturefill" async="async"></script>  -->
  <link rel="stylesheet" href="css/main.css">
</head>

<body>
  <div class="row cover-row">
    <div class="coffee-span-12"></div>
  </div>
  <div class="row article-header">
    <div class="coffee-span-12 coffee-768-span-12 coffee-768-offset-0 header-column coffee-offset-0" >
      <h1 class="welcome-intro-title">Solaranzeige.de</h1><br>
      <h1 class="welcome-title">Schnell Zugriff</h1>
    </div>
  </div>  
  <div class="row article-header">
    <div class="coffee-span-12 coffee-offset-0 coffee-768-span-12">  
      <fieldset style="padding:5px 20px; border: 2px solid #C5D8E1; border-radius: 6px; background: white;vertical-align:middle;">
        <legend style="padding:20px;">Quick Access</legend>
        <div style="float:left; width:30%; height:50px; vertical-align:middle;" ><p style="padding-top: 10px; text-align:center;"><td><a href='<?php echo "http://"; ?><?php echo $_SERVER['SERVER_NAME']; ?><?php echo ":3000"; ?>'>Grafana Dashboard&nbsp;</a></td></p></div>
        <p style="clear: both; text-align: center;"></p>		
        <div style="float:left; width:30%; height:50px; vertical-align:middle;" ><p style="padding-top: 10px; text-align:center;"><td><a href='automation.web.php'>Steuerung&nbsp;</a></td></p></div>
        <p style="clear: both; text-align: center;"></p>		
        <div style="float:left; width:30%; height:50px; vertical-align:middle;" ><p style="padding-top: 10px; text-align:center;"><td><a href='pheditor.php'>File Editor&nbsp;</a></td></p></div>  
      </fieldset>
    </div>
  </div> 
  <div class="row article-header">
    <div class="coffee-span-12 coffee-offset-0 coffee-768-span-12">  
      <fieldset style="padding:5px 20px; border: 2px solid #C5D8E1; border-radius: 6px; background: white;vertical-align:middle;">
        <legend style="padding:20px;">Live Log</legend>
        <div style="float:left; width:100%; height:500px; vertical-align:middle;" ><p style="padding-top: 10px; text-align:left;"><?php header("refresh: 5;"); exec('tail -n 20 /var/www/log/solaranzeige.log', $solarlogs); foreach($solarlogs as $solarlog) {echo "<br />".$solarlog;} ?></p></div>
        <p style="clear: both; text-align: center;"></p> 
      </fieldset>
    </div>
  </div>

    
