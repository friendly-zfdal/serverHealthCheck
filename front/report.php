<?php

include_once("../../../inc/includes.php");
use GlpiPlugin\ServerHealthCheck\ServerHealthCheck;
use Html;

Html::header("ServerHealthCheck Report Public", $_SERVER['PHP_SELF'], "plugins");
// getting private report
$report = ServerHealthCheck::plugin_serverhealthcheck_healthReport("public");
// print it out
echo $report;
Html::footer();