<?php

/*
 * Copyright (C) 2022 K4-713 <k4@hownottospellwinnebago.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

session_start();

require_once('config.php');

function db_connect() {
    global $db_host, $db_username, $db_password, $db_dbname;
    static $db = null;
    if (is_null($db)) {
	$db = new MySQLi($db_host, $db_username, $db_password, $db_dbname);

	//Check connection
	if ($db->connect_error) {
	    error_log("Connection failed: " . $db->connect_error);
	}
    }
    return $db;
}

function validate($data, $expected_structure) {  
  foreach ($data as $row => $columns) {
    //check to see that everything we expect is here, in every row
    foreach ($expected_structure as $field => $type) {
      if (!array_key_exists($field, $data[$row])) {
        if (strpos($type, '|null') > 0) {
          $data[$row][$field] = NULL;
        } else {
          error_log("Data failed to validate - Missing '$field' field in row '$row");
          error_log(print_r($data, true));
          return false;
        }
      }
    }

    //make sure the data types are legit
    foreach ($columns as $column => $value) {
	    //skip data we aren't going to process anyway
	    if (!array_key_exists($column, $expected_structure)) {
        if ($column !== 'ts') { //yeah, we know about these.
            error_log(__FUNCTION__ . ": Extra key '$column'");
        }
        continue;
	    }

	    //now I have the concept of '|null' types...
	    $base_type = $expected_structure[$column];
	    $null_ok = false;
	    $validated = false;
	    if (is_array($base_type)) {
        //can't solve this with recursion, but this looks like a refactor coming.
        //first thing's first, though: Test to see if we have an array where we expect one.
        $structure = $base_type;
        if (!is_array($value)) {
            error_log(__FUNCTION__ . ": $value expected to be an array.");
            return false;
        }

        //for each item in the incoming array, make sure it's in the structure
        foreach ($value as $sub_index => $sub_row) {
          foreach ($sub_row as $subkey => $subvalue) {
            if (!array_key_exists($subkey, $structure)) {
              error_log(__FUNCTION__ . ": $subkey not present in $column array");
              return false;
            }
          }
        }

        //for each item in the structure, make sure it's there and it validates.
        //NOTE: This will *pass* for empty arrays.
        foreach ($structure as $subcolumn => $subtype) {
          $base_type = $subtype;
          if (strpos($subtype, '|null') > 0) {
            $null_ok = true;
            $base_type = explode('|', $subtype);
            $base_type = $base_type[0];
          }

          $validate_function = 'check_' . $base_type;
          $validated = true; //ugh, this is just stinky...
          foreach ($value as $sub_index => $sub_row) {
            //And Equals, I guess.
            $validated &= $validate_function($value[$sub_index][$subcolumn]);
          }
        }
      } else {
        if (strpos($base_type, '|null') > 0) {
            $null_ok = true;
            $base_type = explode('|', $base_type);
            $base_type = $base_type[0];
        }

        $validate_function = 'check_' . $base_type;
        $validated = $validate_function($value);
	    }

	    if (!$validated && $null_ok && is_null($value)) {
        $validated = true;
	    }

	    if (!$validated) {
        if (is_array($value)) {
          $value = "Array";
        }
        error_log("Validation failure at '$validate_function' for '$value' ");
        return false;
	    }
    }
  }
  return true;
}

function check_date($value) {
    $date_array = explode('-', $value);
    if (sizeof($date_array) != 3) {
	return false;
    }
    if (strlen($date_array[0]) < 4 || strlen($date_array[1]) < 2 || strlen($date_array[2]) < 2) {
	return false;
    }
    foreach ($date_array as $part => $number) {

	if (!check_int($number)) {
	    return false;
	}
    }

    // I know this doesn't precisely check to see if this is a valid date, but I don't know how 
    //relevant that problem is going to be. Good enough for now.
    return true;
}

function check_time($value) {
    $date_array = explode(':', $value);
    if (sizeof($date_array) != 3) {
	return false;
    }
    if (strlen($date_array[0]) < 2 || strlen($date_array[1]) < 2 || strlen($date_array[2]) < 2) {
	return false;
    }
    foreach ($date_array as $part => $number) {

	if (!check_int($number)) {
	    return false;
	}
    }

    return true;
}

//this is going to be dumber than I thought.
function check_int($value) {
    if (is_numeric($value) && strpos($value, '.') == 0) {
	return true;
    }
    //error_log("Not an integer? '$value");
    return false;
}

//Use for float. Close enough
function check_number($value) {
  if (is_numeric($value)) {
    return true;
  }
  //error_log("Not an integer? '$value");
  return false;
}


function check_bool($value) {
  if ($value === true || $value === false) {
    return true;
  }
  return false;
}

//I guess if I'm going to go cheap, this is kinda okay
function check_varchar_64($value) {
    return check_varchar($value, 64);
}

function check_varchar_32($value) {
    return check_varchar($value, 32);
}

function check_varchar_8($value) {
    return check_varchar($value, 8);
}

function check_varchar_16($value) {
    return check_varchar($value, 16);
}

function check_varchar($value, $length) {
    if (strlen($value) > $length) {
	return false;
    }
    return true;
  }

function check_auth_key($key) {
    global $data_auth_key;
    if ($key === $data_auth_key) {
	return true;
    }
    return false;
}

function login_check() {
    if (array_key_exists('logged_in', $_SESSION) && $_SESSION['logged_in'] === true) {
	return true;
    }
    do_login();
}

function do_login() {
    $location = $_SERVER['REQUEST_URI'];
    echo "<thml><head></head><body>"
    . "<form name='login' action='/login.php' method='post'><table><tr><td>Password please:</td>"
    . "<td><input type='password' id='pass' name='pass'></td></tr>"
    . "<tr><td colspan=2 align=center><input type='submit' value='yep go'></td></tr></table>"
    . "<input type='hidden' name='page' value='$location'></form></body></html>";
    die();
}

function check_password() {
    global $site_access_key;
    if ($_POST['pass'] === $site_access_key) {
	$_SESSION['logged_in'] = true;
	header("Location: " . $_POST['page']);
	die();
    }
    echo "Lol nope\n<br>";
}

function start_page($title) {
    login_check();
    echo "<head>\n"
    . "<title>Poke Tracker - $title</title>"
    . "<link rel='stylesheet' href='site.css'>\n"
    . "<script src='libs/jquery-3.4.1.min.js'></script>\n"
    . "<script src='libs/d3/d3.min.js'></script>\n"
    . "<script src='common.js'></script>\n"
    . "</head>\n";
    add_navigation();
    echo "<h1>$title</h1>";
}

function add_document_ready($callback) {
    login_check();
    echo "<script>\n"
    . "$( document ).ready( $callback );"
    . "</script>\n";
}

function add_navigation() {
    $links = array(
	'index.php' => 'National Dex',
  'box_view.php' => 'Collection - Box View',
  'ha_needed.php' => 'HA Needed',
//	'shop_stock.php' => 'All Shop Stock',
//	'stockpiling.php' => 'Stockpiling and Event Prep',
//	'gc_buy.php' => 'Gourmet Club - What to Buy',
//	'gc_eaten_log.php' => 'Gourmet Club - Eaten Log',
//	'shop_gen.php' => 'Shop Index Generator'
  );
    $out = '';
    foreach ($links as $file => $text) {
	//let's just go for a top nav
	if ($out !== '') {
	    $out .= "&nbsp&nbsp|&nbsp&nbsp";
	}
	$out .= "<a href='$file'>$text</a>";
    }
    echo "<div id='topnav' class='topnav'>$out</div>\n";
}

function add_js($file) {
    echo "<script src='$file'></script>\n";
}

function poke_auth_key() {
    global $data_auth_key;
    echo "<script>var data_auth_key='$data_auth_key'</script>\n";
}

function poke_json($array, $var_name = 'dataset') {
    $json = json_encode($array);
    echo "<script>var $var_name = $json;</script>\n";
}

function add_copy_icon($text_to_copy) {
    return '<img src="copy.png" class="copy_img" onClick="copyText(\'' . $text_to_copy . '\', this)">';
}

function jsonify_date_keyed_array($date_array) {
    $out = array();
    foreach ($date_array as $date => $values) {
	$push = array(
	    'date' => $date,
	    'ts' => strtotime($date)
	);
	$push = array_merge($push, $values);
	$out[] = $push;
    }
    return json_encode($out);
}

function get_setting_value($setting, $group = null) {
    $group_string = '';
    if (!is_null($group)) {
	$group_string = " AND setting_group = '$group'";
    }
    $db = db_connect();
    $query = "select setting_value from settings where setting_name = '$setting' $group_string"
	    . " ORDER BY setting_date DESC LIMIT 1";
    $result = $db->query($query);

    if ($result === false OR $result->num_rows === 0) { //setting is undefined
	return false;
    }

    $ret = '';
    while ($row = $result->fetch_assoc()) {
	if ($ret !== '') {
	    error_log(__FUNCTION__ . ": Warning: Looks like multiple settings for '$setting'");
	}
	$ret = $row['setting_value'];
    }

    //bah, strings.
    if ($ret == 'false') {
	$ret = false;
    }

    return $ret;
}

function get_setting_group($group) {
    static $setting_groups = array();

    if (!array_key_exists($group, $setting_groups)) {
	//go get it
	$db = db_connect();
	//TODO: it would be cleaner to just select the freshest one for each setting
	$query = "select setting_name, setting_value, setting_date "
		. "from settings where setting_group = '$group' ORDER BY setting_date DESC";
	$result = $db->query($query);

	if ($result === false OR $result->num_rows === 0) { //setting is undefined
	    $setting_groups[$group] = false;
	    return false;
	}

	$setting_groups[$group] = array();

	while ($row = $result->fetch_assoc()) {
	    if (!array_key_exists($row['setting_name'], $setting_groups[$group])) {
		$setting_groups[$group][$row['setting_name']] = $row['setting_value'];
	    }
	}

	//cast to bool if the value is a string "false"
	foreach ($setting_groups[$group] as $setting => $value) {
	    if ($value == 'false') {
		$setting_groups[$group][$setting] = false;
	    }
	}
    }

    return $setting_groups[$group];
}

function set_setting_value($setting, $value) {
    $db = db_connect();

    $query = "INSERT INTO settings (setting_name, setting_value, setting_date) "
	    . "VALUES('$setting', '$value', CURDATE()) "
	    . "ON DUPLICATE KEY UPDATE setting_name = '$setting', setting_value = '$value', setting_date = CURDATE()";
    $result = $db->query($query);

    if ($result === false) { //something went wrong?
	error_log(__FUNCTION__ . ": Error saving setting $setting to $value");
	return false;
    }

    return true;
  }

function get_url_page() {
    static $uri = NULL;
    if (is_null($uri)) {
      $uri = explode('?', $_SERVER['REQUEST_URI']);
      $uri = $uri[0];
    }
    return $uri;
}

/**
 * Returns URL params we want to use (basically $_GET).
 * Optionally unsets keys that shouldn't persist to new URLs
 * we don't want to continue to use.
 * @staticvar array $gets The get params to keep / use
 * @param array|null $unset Optional param - Array of keys to unset, or null
 * @return array get params
 */
function poke_url_params($unset = null) {
    static $gets = null;
    if (is_null($gets)) {
	$gets = $_GET;
    }
    if (!is_null($unset)) {
	foreach ($unset as $key) {
	    if (array_key_exists($key, $gets)) {
		unset($gets[$key]);
	    }
	}
    }
    return $gets;
}

function make_link($add_params) {
    if (empty($add_params)) {
	return get_url_page();
    }
    $gets = array_merge(poke_url_params(), $add_params);
    $link = get_url_page() . '?' . http_build_query($gets);
    return $link;
}

function sort_array_by_key($sort_me, $key, $pk, $direction = NULL) {

  switch ($direction) {
	case NULL:
	    $sort_asc = array('my_type', 'my_subtype', 'name', 'neo_id');
	    if (in_array($key, $sort_asc)) {
		$direction = SORT_ASC;
	    } else {
		$direction = SORT_DESC;
	    }
	    break;
	case 'ASC':
	    $direction = SORT_ASC;
	    break;
	case 'DESC':
	    $direction = SORT_DESC;
	    break;
    }

    //Multisort setup
    $sort1 = array_column($sort_me, $key);
    //this is obviously weird. Hrm.
    $sort2 = array_column($sort_me, $key);

    array_multisort($sort1, $direction, $sort2, SORT_DESC, $sort_me);

    return $sort_me;
  }

function get_poketype_output($type) {
  return "<div class='poke_type " . strtolower($type) ."'>" . strtoupper($type) . "</div>";
}
