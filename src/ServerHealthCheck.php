<?php


namespace GlpiPlugin\ServerHealthCheck;

include_once("/var/www/html/glpi/inc/includes.php");
use CommonDBTM;

//use CommonGLPI;
use Html;
use Session;


class ServerHealthCheck extends CommonDBTM
{

    // Should return the localized name of the type
    static function getTypeName($nb = 0)
    {
        return 'Test Type';
    }

    //return the name of tab on a sidebar
    static function getMenuName()
    {
        return __('ServerHealthCheck');
    }

    //checks user for required privilege
    static function canView()
    {
        global $DB;
        //get current user's ID
        $user_id = Session::getLoginUserID();
        //search for plugin group's id
        $query = "SELECT id from glpi_groups WHERE name = 'ServerHealthCheck'";
        $group_id_response = $DB->query($query) or die("Error during getting group id." . $DB->error());
        foreach ($group_id_response as $id) {
            $group_id = $id['id'];
        }

        //check if current user in plugin's group and have rights to access plugins page
        $query = "SELECT * from glpi_groups_users WHERE users_id = " . $user_id . " AND groups_id = " . $group_id;
        $responses = $DB->query($query) or die("Error during getting user belonging to the plugin's group." . $DB->error());
        // handle response from db
        foreach ($responses as $response) {
            if ($response['users_id'] == $user_id && $response['groups_id'] == $group_id) {
                return true;
            }
        }



        return false;
    }
    /**
     * @see CommonGLPI::getAdditionalMenuLinks()
     **/
    static function getAdditionalMenuLinks()
    {
        global $CFG_GLPI;
        $links = [];
        return $links;
    }

    // get all rows from plugin's table
    static function plugin_serverhealthcheck_getServerHealthData()
    {
        global $DB;

        // Fetch the server health data from the plugin's table
        $result = $DB->query("SELECT * FROM `glpi_plugin_serverhealthcheck_serverhealthchecks`");
        $servers = [];
        while ($row = $result->fetch_assoc()) {
            $servers[] = $row;
        }

        return $servers;
    }

    // update rows in the plugin's table with data in $data
    static public function plugin_serverhealthcheck_updateServerHealthData($data)
    {
        global $DB;

        // Update the server health data in the plugin's table
        foreach ($data as $row) {
            //get fields values
            $id = $row['id'];
            $ip = $row['ip'];
            $login = $row['login'];
            $password = $row['password'];
            //update required row in table
            $DB->query("
            UPDATE `glpi_plugin_serverhealthcheck_serverhealthchecks`
            SET `ip` = '$ip', `login` = '$login', `password` = '$password'
            WHERE `id` = $id
            ");
        }
    }

    //updating servers list in plugin table
    static function plugin_serverhealthcheck_updateServerList()
    {
        global $DB;
        // Get lists of servers from 'glpi_computers' table and from plugin's table
        $servers_list_new = $DB->request("SELECT id FROM glpi_computers WHERE (computertypes_id = (SELECT id FROM glpi_computertypes WHERE name = 'server') AND is_deleted <> 1)");
        $servers_list_old = $DB->request("SELECT id FROM glpi_plugin_serverhealthcheck_serverhealthchecks");

        $remove_flag = 1;
        //removing not actual records from plugin's table
        foreach ($servers_list_old as $server_old) {
            foreach ($servers_list_new as $server_new) {
                if ($server_old == $server_new)
                    $remove_flag = 0;
            }
            if ($remove_flag == 1) {
                $DB->delete(
                    'glpi_plugin_serverhealthcheck_serverhealthchecks',
                    [
                        'id' => $server_old,
                    ]
                );
            }
            $remove_flag = 1;
        }


        //adding new servers if they don't exist in plugin's table yet
        foreach ($servers_list_new as $server_new) {
            $req = $DB->request('SELECT id from glpi_plugin_serverhealthcheck_serverhealthchecks WHERE id=$server_new');
            if (!count($req)) {
                $DB->insert(
                    'glpi_plugin_serverhealthcheck_serverhealthchecks',
                    [
                        'id' => $server_new['id'],
                        'ip' => '',
                        'login' => '',
                        'password' => '',
                        'state' => '',
                    ]
                );
            }
        }
    }

    // return servers list
    static function plugin_serverhealthcheck_getServers()
    {
        global $DB;
        $servers = []; // Placeholder for server records

        // Retrieve server records from the plugin's table
        $query = "SELECT * FROM `glpi_plugin_serverhealthcheck_serverhealthchecks`";
        $servers = $DB->query($query);

        return $servers;
    }

    // update state field of server with $id with $state value
    static function plugin_serverhealthcheck_updateServerState($id, $state)
    {
        global $DB;

        // Update the state value for the specified server ID in the plugin's table
        $query = "UPDATE `glpi_plugin_serverhealthcheck_serverhealthchecks` SET `state` = '$state' WHERE `id` = $id";
        $DB->query($query);
    }

    //gather states of sensors from all servers from the plugin's table and set state for them
    static public function plugin_serverhealthcheck_gatherServerStates()
    {
        global $DB;

        $servers = plugin_serverhealthcheck_getServers();

        foreach ($servers as $server) {
            $ip = $server['ip'];
            $login = $server['login'];
            $password = $server['password'];

            // Execute ipmitool sdr command
            $output = [];
            exec("ipmitool -I lanplus -H $ip -U $login -P $password sdr", $output);

            $state = 'OK'; // Default state

            $criticalFound = false;
            $nonCriticalFound = false;
            //case when ipmitool isn't installed
            if (str_contains($output, 'No such file or directory')) {
                die("ipmitool isn't installed on GLPI host system. ServerHealthCheck can't get data from servers without it.
                 Try to install it or contact your Administrator");
            }
            // can't establish connection with provided credentials
            if (str_contains($output, 'Error: Unable to establish IPMI v2 / RMCP+ session')) {
                $state = 'Unable to establish connection';
                //data gathered successfully, parsing
            } else {
                // Check sensor states
                foreach ($output as $line) {

                    // Check if the sensor state is Upper Critical or Lower Critical
                    if (str_contains($line, 'Upper Critical') || str_contains($line, 'Lower Critical')) {
                        $state = 'Critical';
                        $criticalFound = true;
                        break;
                    }

                    // Check if the sensor state is Upper Non-critical or Lower Non-critical
                    if (str_contains($line, 'Upper Non-critical') || str_contains($line, 'Lower Non-critical')) {
                        $nonCriticalFound = true;
                    }
                }


                // Set state based on the sensor values
                if ($criticalFound) {
                    $state = 'Critical';
                } elseif (!$criticalFound && $nonCriticalFound) {
                    $state = 'Non-Critical';
                } elseif (!$criticalFound && !$nonCriticalFound) {
                    $state = 'OK';
                }
            }
            // Update state value in the plugin's table
            ServerHealthCheck::plugin_serverhealthcheck_updateServerState($server['id'], $state);
        }
    }


    // generating HTML page with private or public report based on value in $access ("public"/"private")
    static function plugin_serverhealthcheck_healthReport($access)
    {
        //ServerHealthCheck::plugin_serverhealthcheck_gatherServerStates();
        $servers = ServerHealthCheck::plugin_serverhealthcheck_getServers();
        $html = "    <style>
        table {
          border-collapse: collapse;
          width: 100%;
          margin-top: 10px;
        }
    
        th, td {
          border: 1px solid black;
          padding: 8px;
          text-align: center;
        }
    
        input {
          margin-right: 10px;
          margin-left: 10px;
        }
        </style>";
        if ($access == "private") {

            $html .= '<html><head><title>Server Health Report</title></head><body>';
            $html .= '<h1>Server Health Report</h1>';
            $html .= "<h2>Date of report:  " . date("Y-m-d") . " " . date("h:s") . "</h2>";
            $html .= '<table>';
            $html .= '<tr><th>ID</th><th>IP</th><th>Login</th><th>Password</th><th>State</th></tr>';

            foreach ($servers as $server) {
                $state = $server['state'];

                $html .= '<tr>';
                $html .= '<td>' . $server['id'] . '</td>';
                $html .= '<td>' . $server['ip'] . '</td>';
                $html .= '<td>' . $server['login'] . '</td>';
                $html .= '<td>' . $server['password'] . '</td>';
                if ($state == 'Critical') {
                    $html .= '<td bgcolor = "red">' . $state . '</td>';
                } elseif ($state == 'Non-Critical') {
                    $html .= '<td bgcolor = "yellow">' . $state . '</td>';
                } elseif ($state == 'OK') {
                    $html .= '<td bgcolor = "green">' . $state . '</td>';
                } elseif ($state == 'Unable to establish connection') {
                    $html .= '<td bgcolor = "LightGray">' . $state . '</td>';
                }
                $html .= '</tr>';

            }

            $html .= '</table>';
            $html .= '</body></html>';

            // Generate a unique filename for the report
            $filename = 'server_health_report_private' . uniqid() . '.html';
        } else {
            $html .= '<html><head><title>Server Health Report</title></head><body>';
            $html .= '<h1>Server Health Report</h1>';
            $html .= "<h2>Date of report:  " . date("Y-m-d") . " " . date("H:i") . "</h2>";
            $html .= '<table>';
            $html .= '<tr><th>ID</th><th>IP</th><th>State</th></tr>';

            foreach ($servers as $server) {
                $state = $server['state'];


                $html .= '<tr>';
                $html .= '<td>' . $server['id'] . '</td>';
                $html .= '<td>' . $server['ip'] . '</td>';
                if ($state == 'Critical') {
                    $html .= '<td bgcolor = "red">' . $state . '</td>';
                } elseif ($state == 'Non-Critical') {
                    $html .= '<td bgcolor = "yellow">' . $state . '</td>';
                } elseif ($state == 'OK') {
                    $html .= '<td bgcolor = "green">' . $state . '</td>';
                } elseif ($state == 'Unable to establish connection') {
                    $html .= '<td bgcolor = "LightGray">' . $state . '</td>';
                }
                $html .= '</tr>';

            }

            $html .= '</table>';
            $html .= '</body></html>';

            // Generate a unique filename for the report
            $filename = '../reports/server_health_report_public_' . date("Y-m-d") . "_" . date("H:i") . '.html';
        }

        if ($access == 'public') {
            // Save the HTML content to a file
            file_put_contents($filename, $html);
        }
        return $html;


    }


    // define new widget type for the dashboard
    static function dashboardTypes()
    {
        return [
            'serverhealthcheck' => [
                'label' => __("Plugin ServerHealthCheck", 'serverhealthcheck'),
                'function' => ServerHealthCheck::class . "::cardWidget",
                'image' => "https://via.placeholder.com/100x86?text=example",
            ],
        ];
    }


    // define content generator for widget
    static function dashboardCards($cards = [])
    {
        if (is_null($cards)) {
            $cards = [];
        }
        $new_cards = [
            'plugin_serverhealthcheck_card' => [
                'widgettype' => ["serverhealthcheck"],
                'label' => __("ServerHealthCheck last report:"),
                'provider' => ServerHealthCheck::class . "::cardDataProvider",
            ],
        ];

        return array_merge($cards, $new_cards);
    }


    // template for widget
    static function cardWidget(array $params = [])
    {
        $default = [
            'data' => [],
            'title' => '',
            // this property is "pretty" mandatory,
            // as it contains the colors selected when adding widget on the grid send
            // without it, your card will be transparent
            'color' => 'red',
        ];
        $p = array_merge($default, $params);

        // you need to encapsulate your html in div.card to benefit core style
        $html = "<div class='card' style='background-color: {$p["color"]};'>";
        $html .= "<h2>{$p['title']}</h2>";
        $html .= "<ul>";
        foreach ($p['data'] as $line) {
            $html .= "<li>$line</li>";
        }
        $html .= "</ul>";
        $html .= "</div>";

        return $html;
    }

    // generating content for widget
    static function cardDataProvider(array $params = [])
    {
        global $DB;
        $critical_count = 0;
        $noncritical_count = 0;
        $ok_count = 0;
        $unavailable = 0;
        $general_state = "OK";
        $general_status_string = "";
        $query = "SELECT * from glpi_plugin_serverhealthcheck_serverhealthchecks";
        $servers = $DB->query($query) or die("Error during getting info for widget." . $DB->error());

        //count each type of state
        foreach ($servers as $server) {
            if ($server['state'] == 'Critical') {
                $critical_count++;
                $general_state = "Critical";
            }
            if ($server['state'] == 'Non-Critical') {
                $noncritical_count++;
                if ($general_state != "Critical") {
                    $general_state = "Non-Critial";
                }
            }
            if ($server['state'] == 'OK') {
                $ok_count++;
            }
            if ($server['state'] == 'Unable to establish connection') {
                $unavailable++;
            }
        }

        if ($general_state == "Critical") {
            $general_status_string = "<h2>General status: <font color ='red'> Critical</font>.</h2>";
        } elseif ($general_state == "Non-Critical") {
            $general_status_string = "<h2>General status: <font bgcolor ='yellow'> Non-Critical</font>.</h2>";
        } elseif ($general_state == "OK") {
            $general_status_string = "<h2>General status: <font bgcolor ='green'> OK</font>.</h2>";
        } else {
            $general_status_string = "<h2> General status: Unknown.</h2>";
        }


        $default_params = [
            'label' => null,
            'icon' => "fa-sharp fa-solid fa-server",
        ];
        $params = array_merge($default_params, $params);
        //<td bgcolor = "red">' . $state . '</td>
        return [
            'title' => $params['label'],
            'icon' => $params['icon'],
            'data' => [
                '<h3>' . $critical_count . ' servers with critical state;</h3>',
                '<h3>' . $noncritical_count . ' servers with non-critical state;</h3>',
                '<h3>' . $ok_count . ' servers with normal state;</h3>',
                '<h3>Plugin cant establish connection with ' . $unavailable . ' servers;</h3>',
                $general_status_string . '',
            ],
            'color' => 'red'
        ];
    }
}