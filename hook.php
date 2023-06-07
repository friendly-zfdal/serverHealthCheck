<?php

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_serverhealthcheck_install()
{
  global $DB;
  // get default settings from DB
  $default_charset = DBConnection::getDefaultCharset();
  $default_collation = DBConnection::getDefaultCollation();
  $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

  // Create the table for server health data
  if (!$DB->tableExists("glpi_plugin_serverhealthcheck_serverhealthchecks")) {
    $query = "CREATE TABLE `glpi_plugin_serverhealthcheck_serverhealthchecks` (
                  `id` int {$default_key_sign} NOT NULL,
                  `ip` varchar(255) default NULL,
                  `login` varchar(255) default NULL,
                  `password` varchar(255) default NULL,
                  `state` varchar(255) default NULL,
                PRIMARY KEY (`id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

    $DB->query($query) or die("error creating glpi_plugin_serverhealthcheck_serverhealthchecks " . $DB->error());

    // Get all servers from GLPI and insert them into the plugin's table
    $query = "SELECT id FROM glpi_computers WHERE computertypes_id = (SELECT id FROM glpi_computertypes WHERE name = 'server')";
    $servers = $DB->query($query) or die("error during fetching servers list" . $DB->error());

    foreach ($servers as $server) {
      $DB->insert(
        'glpi_plugin_serverhealthcheck_serverhealthchecks',
        [
          'id' => $server['id'],
          'ip' => '',
          'login' => '',
          'password' => '',
          'state' => '',
        ]
      );
    }
  }

  //create new user group for plugin
  $query = "INSERT INTO glpi_groups (name, comment, completename)
              VALUES('ServerHealthCheck', 'Group for ServerHealthCheck plugin access manage', 'ServerHealthCheck')";
  $DB->query($query) or die("Error during creating new group for plugin" . $DB->error());

  // add super-admin user 'glpi' to the group
  $query = "SELECT id from glpi_users WHERE name = 'glpi'";
  $user_id = $DB->query($query) or die("Error during getting glpi user id." . $DB->error());

  $query = "SELECT id from glpi_groups WHERE name = 'ServerHealthCheck'";
  $group_id = $DB->query($query) or die("Error during getting group id." . $DB->error());

  $query = "INSERT INTO glpi_groups_users (users_id, groups_id)
              VALUES ($user_id, $group_id)";
  $DB->query($query) or die("Error during add user to ServerHealthCheck plugin group " . $DB->error());

  //create new folder for reports
  if (!file_exists("./reports")) {
    mkdir("./reports");
  }
  return true;
}


/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_serverhealthcheck_uninstall()
{
  global $DB;
  // Drop the table for server health data
  if ($DB->tableExists("glpi_plugin_serverhealthcheck_serverhealthchecks")) {
    $query = "DROP TABLE `glpi_plugin_serverhealthcheck_serverhealthchecks`";
    $DB->query($query) or die("error deleting glpi_plugin_serverhealthcheck_serverhealthchecks");
  }

  //get ServerHealthCheck group's id 
  $group_id = 0;
  $query = "SELECT * FROM glpi_groups WHERE name = 'ServerHealthCheck'";
  $response = $DB->query($query) or die("error during getting ServerHealthCheck user group's id");
  foreach ($response as $row) {
    $group_id = $row['id'];
  }
  // Delete user data about belonging to ServerHealthCheck group
  $query = "DELETE FROM glpi_groups_users WHERE groups_id = " . $group_id;
  $DB->query($query) or die("error deleting user's data about belonging to ServerHealthCheck group");


  // Drop ServerHealthCheck group
  $query = "DELETE FROM glpi_groups WHERE id = " . $group_id;
  $DB->query($query) or die("error deleting ServerHealthCheck group");


  //Delete dir for reports
  $dir = "./reports";
  if (file_exists($dir)) {
    foreach (glob($dir . '/*') as $file) {
      if (is_dir($file))
        deleteAll($file);
      else
        unlink($file);
    }
    rmdir($dir);
  }
  return true;
}
