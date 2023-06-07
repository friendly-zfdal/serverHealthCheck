<?php
include_once("../../../inc/includes.php");
use GlpiPlugin\ServerHealthCheck\ServerHealthCheck;

Html::header("ServerHealthCheck", $_SERVER['PHP_SELF'], "plugins", ServerHealthCheck::class, "");
// check user's privilege
if (!ServerHealthCheck::canView()) {
  echo "Sorry, you can't access this page. Check your privileges level with GLPI's administrator";
  return;
}
?>

<!-- template for main plugin's form -->
<h2>Server Health Check</h2>
<style>
  table {
    border-collapse: collapse;
    width: 100%;
    margin-top: 10px;
  }

  th,
  td {
    border: 1px solid black;
    padding: 8px;
    text-align: center;
  }

  input {
    margin-right: 10px;
    margin-left: 10px;
  }
</style>

<form action="posthandler.php" method="post">

  <!-- Buttons -->
  <input type='submit' name='save' value='Save changes'>
  <input type='submit' name='update' value='Update servers list'>
  <input type='submit' name='gather' value='Gather sensors values'>
  <input type='submit' name='report' value='Show report'>

  <!-- Editable table for servers -->
  <table>
    <tr>
      <th>ID</th>
      <th>IP</th>
      <th>Login</th>
      <th>Password</th>
    </tr>


    <?php
    // update servers state field
    ServerHealthCheck::plugin_serverhealthcheck_gatherServerStates();
    // get actual info from plugin's table
    $servers = ServerHealthCheck::plugin_serverhealthcheck_getServerHealthData();
    $member_id = 1;
    // print gathered info on the form
    foreach ($servers as $server) {
      echo "<tr>";
      echo "<td><a href='http://" . $_SERVER['SERVER_NAME'] . "/glpi/front/computer.form.php?id=" . $server['id'] . "'>" . $server['id'] . "</a></td>";
      echo "<input type='hidden' name='id_" . $member_id . "' value='" . htmlentities($server['id']) . "'>";
      echo "<td><input type='text' name='ip_" . $member_id . "' value='" . htmlentities($server['ip']) . "'></td>";
      echo "<td><input type='text' name='login_" . $member_id . "' value='" . htmlentities($server['login']) . "'></td>";
      echo "<td><input type='password' name='password_" . $member_id . "' value='" . htmlentities($server['password']) . "'></td>";
      echo "</tr>";
      $member_id++;
    }

    echo "</table>";
    echo "</form>";
    // if no servers in the system yet
    if (empty($servers)) {
      echo "<h1>There are no servers yet. Try to update servers list or add new ones to your main computers database.";
    }
    Html::footer();
    ?>