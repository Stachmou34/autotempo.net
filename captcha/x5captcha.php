<?php
include("../res/x5engine.php");
$nameList = array("vdl","tgv","cwd","5ww","5rf","ndk","cjw","vc6","why","gfd");
$charList = array("8","3","Z","6","F","Z","4","7","3","7");
$cpt = new X5Captcha($nameList, $charList);
//Check Captcha
if ($_GET["action"] == "check")
	echo $cpt->check($_GET["code"], $_GET["ans"]);
//Show Captcha chars
else if ($_GET["action"] == "show")
	echo $cpt->show($_GET['code']);
// End of file x5captcha.php
