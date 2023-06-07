<?php
include("../../../inc/includes.php");
use GlpiPlugin\ServerHealthCheck\ServerHealthCheck;
use Html;

// saving content form main form to the plugin's table
if (isset($_POST['save'])) {
    //check if the user has enough rights to do this operation
    if (!ServerHealthCheck::canView()) {
        echo "Sorry, you can't access this page. Check your privileges level with GLPI's administrator";
        return;
    }
    $member_id = 1;
    $data = [];
    $errors = [];

    while (!empty($_POST['id_' . $member_id])) {
        $error_flag = 0;
        //check if ip is valid
        if (!filter_var($_POST['ip_' . $member_id], FILTER_VALIDATE_IP) && $_POST['ip_' . $member_id] != "") {
            $errors[] = "Invalid IP address format for server with id = " . $_POST['id_' . $member_id] . ",";
            $error_flag = 1;
        }
        //check password for minimum length
        if (strlen($_POST['password_' . $member_id]) < 8 && $_POST['password_' . $member_id] != "") {
            $errors[] = "Password length is less than 8 characters for server with id = " . $_POST['id_' . $member_id] . ",";
            $error_flag = 1;
        }
        // report if the program can't update some of the servers due to ip/password fields values invalid format
        if ($error_flag) {
            $errors[] = "Properties of server with id = " . $_POST['id_' . $member_id] . "won't be updated, change noted fields values,";
            $member_id++;
            continue;
        }
        // if all is okay, combine fields values in one array
        $data[] = [
            'id' => $_POST['id_' . $member_id],
            'ip' => $_POST['ip_' . $member_id],
            'login' => $_POST['login_' . $member_id],
            'password' => $_POST['password_' . $member_id],
        ];
        $member_id++;
    }
    // update data in plugins table
    ServerHealthCheck::plugin_serverhealthcheck_updateServerHealthData($data);
    //print errors for the uset
    foreach ($errors as $error) {
        echo $error . "<br>";
    }
    echo "<h1>saved</h1>";
}

// action for update button
if (isset($_POST['update'])) {
    //check if the user has enough rights to do this operation
    if (!ServerHealthCheck::canView()) {
        echo "Sorry, you can't access this page. Check your privileges level with GLPI's administrator";
        return;
    }
    // update serves list in the plugin's table
    ServerHealthCheck::plugin_serverhealthcheck_updateServerList();
    echo "Server list has been successully updated";
}
// action for the gather sensors values button
if (isset($_POST['gather'])) {
    //check if the user has enough rights to do this operation
    if (!ServerHealthCheck::canView()) {
        echo "Sorry, you can't access this page. Check your privileges level with GLPI's administrator";
        return;
    }
    // gather actual info about servers state
    ServerHealthCheck::plugin_serverhealthcheck_gatherServerStates();
    echo "Health data has been successfully gathered";

}

// show private version of report
if (isset($_POST['report'])) {
    // generate private report
    $report = ServerHealthCheck::plugin_serverhealthcheck_healthReport("private");
    Html::header("ServerHealthCheck private report", $_SERVER['PHP_SELF'], "plugins");
    // check if the user has enough rights to do this operation
    if (!ServerHealthCheck::canView()) {
        echo "Sorry, you can't access this page. Check your privileges level with GLPI's administrator";
        Html::footer();
        return;
    } else {
        // print private report
        echo "$report";
    }
    Html::footer();

}

?>