<?php

/*
 * Copyright (C) 2019 K4-713 <k4@hownottospellwinnebago.com>
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
		error_log("Data failed to validate - Missing '$field' field in row '$row");
		error_log(print_r($data, true));
		return false;
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

function check_varchar($value, $length) {
    if (strlen($value) > $length) {
	return false;
    }
    return true;
}

function check_gourmet_pet_will_eat($item) {
    global $gourmet_pet_name;
//    According to me elsewhere: =IF(MOD(LEN(Trim(A1389)), 12) = 0, "won't", "")
    $item_length = strlen($item); //This isn't zero indexed or anything dum, is it?
    if ($item_length % strlen($gourmet_pet_name) === 0) {
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
    . "<title>Neologger - $title</title>"
    . "<link rel='stylesheet' href='site.css'>\n"
    . "<script src='libs/jquery-3.4.1.min.js'></script>\n"
    . "<script src='libs/d3/d3.min.js'></script>\n"
    . "<script src='common.js'></script>\n"
    . "</head>\n";
    add_navigation();
    echo "<h1>$title</h1>";
}

function add_navigation() {
    $links = array(
	'index.php' => 'Report By Day',
	'restock.php' => 'Restock Helper',
	'shop_stock.php' => 'All Shop Stock',
	'stockpiling.php' => 'Stockpiling and Event Prep',
	'gc_buy.php' => 'Gourmet Club - What to Buy',
	'gc_eaten_log.php' => 'Gourmet Club - Eaten Log',
	'shop_gen.php' => 'Shop Index Generator'
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

function get_upper_percentile_rank($item_name, $type) {
    static $results = null;
    if ($type !== 'Quest Item') {
	//error_log(__FUNCTION__ . ": No percentile rank function defined for type $type");
	return false;
    }

    if (is_null($results)) {
	$db = db_connect();

	$query = "SELECT name, rarity, my_subtype FROM items WHERE my_type = 'Quest Item'";
	$result = $db->query($query);

	if ($result === false) { //something went wrong?
	    error_log(__FUNCTION__ . ": Error retrieving item information.");
	    return false;
	}

	$results = array();
	$grouping_counts = array();
	//for each type, save a count for each rarity, and the total
	while ($row = $result->fetch_assoc()) {
	    if (!array_key_exists($row['name'], $results)) {
		$results[$row['name']] = array(
		    'rarity' => $row['rarity'],
		    'grouping' => $row['my_subtype']
		);
	    }
	    if (!array_key_exists($row['my_subtype'], $grouping_counts)) {
		$grouping_counts[$row['my_subtype']] = array(
		    $row['rarity'] => 1,
		    'total' => 1
		);
	    } else {
		if (!array_key_exists($row['rarity'], $grouping_counts[$row['my_subtype']])) {
		    $grouping_counts[$row['my_subtype']][$row['rarity']] = 0;
		}
		$grouping_counts[$row['my_subtype']][$row['rarity']] += 1;
		$grouping_counts[$row['my_subtype']]['total'] += 1;
	    }
	}

	//order grouping counts / types by index
	foreach ($grouping_counts as $type => $info) {
	    ksort($grouping_counts[$type]);
	    $info = $grouping_counts[$type]; //doesn't actually do anything with the main array.
	    $position = 0;
	    foreach ($info as $key => $value) {
		if ($key !== 'total') {
		    $position += $value;
		    //do math
		    $grouping_counts[$type][$key] = array(
			'position' => $position,
			'percent_rank' => round($position / $info['total'], 2)
		    );
		}
	    }
	}

	foreach ($results as $name => $info) {
	    $results[$name]['percent_rank'] = $grouping_counts[$info['grouping']][$info['rarity']]['percent_rank'];
	}

    }
    return $results[$item_name]['percent_rank'];
}

/**
 * Returns the average spent on a particular item by neo_id
 * @param array $neo_ids An array of neo_ids => quantities to average
 * @return array spent averages indexed by neo_id
 */
function get_batch_spent_averages($neo_ids) {
    $purchase_history_data = get_batch_purchase_history_data($neo_ids);

    $needs_legacy = array();
    foreach ($purchase_history_data as $neo_id => $values) {
	if ($values === false || $values['qty'] < $neo_ids[$neo_id]) {
	    $needs_legacy[] = $neo_id;
	}
    }

    //get the legacy data
    $legacy_spend = array();
    if (count($needs_legacy) > 0) {
	$db = db_connect();
	$sql = "select neo_id, average from legacy_spend where neo_id IN (" . implode(', ', $needs_legacy) . ")";

	$legacy_qry = $db->query($sql);

	while ($legacy_row = $legacy_qry->fetch_assoc()) {
	    $legacy_spend[$legacy_row['neo_id']] = $legacy_row['average'];
	}
    }

    //now roll them all up and send it back as one number.
    $return = array();
    foreach ($purchase_history_data as $neo_id => $values) {
	$qty = 0;
	$sum = 0;
	if ($values !== false) {
	    $qty = $values['qty'];
	    $sum = $values['sum'];
	}

	if ($qty < $neo_ids[$neo_id] && array_key_exists($neo_id, $legacy_spend)) {
	    $add_qty = $neo_ids[$neo_id] - $qty;
	    $sum += $add_qty * $legacy_spend[$neo_id];
	    $qty += $add_qty;
	}
	if ($qty > 0) {
	    $return[$neo_id] = ceil($sum / $qty);
	} else {
	    $return[$neo_id] = NULL;
	}
    }

    return $return;
}

/**
 * Returns an array of total spent and quantity, indexed by neo_id.
 * @staticvar array Cache of the data we're retrieving from the db
 * @param array $neo_ids An array of neo_ids => quantities to average
 * @return array arrays of sum and qty, indexed by neo_id
 */
function get_batch_purchase_history_data($neo_ids) {
    static $history_data = array();

    $retrieve_ids = array();
    $qty_max = 0;
    foreach ($neo_ids as $neo_id => $qty) {
	if (!array_key_exists($neo_id, $history_data)) {
	    $retrieve_ids[] = $neo_id;
	    if ($qty > $qty_max) {
		$qty_max = $qty;
	    }
	}
    }

    //go to the db if we don't have all the data already
    if (count($retrieve_ids) > 0) {
	$db = db_connect();

	$sql = "select neo_id, spent_np from purchases where neo_id IN (" . implode(', ', $retrieve_ids) . ") ORDER BY date DESC, time DESC";
	//echo "$sql<br>";
	$item_result = $db->query($sql);

	//store data as neo_id => total spent, qty
	$new_data = array();
	while ($sub_row = $item_result->fetch_assoc()) {
	    $new_data[$sub_row['neo_id']][] = $sub_row['spent_np'];
	//    echo "Adding to id " . $sub_row['neo_id'] . " spent value " . $sub_row['spent_np'] . "<br>";
	}

	foreach ($new_data as $neo_id => $spent_np) {
	    $qty_limit = $neo_ids[$neo_id]; //that's a quantity
	    $sum = 0;
	    $qty = 0;
	    //sum up the first $qty_limit rows
	    if (count($spent_np) > $qty_limit) {
		$sum_me = array_slice($spent_np, 0, $qty_limit);
		$sum = array_sum($sum_me);
		$qty = $qty_limit;
	    } else {
		//just do the whole thing
		$sum = array_sum($spent_np);
		$qty = count($spent_np);
	    }

	    $history_data[$neo_id] = array(
		'sum' => $sum,
		'qty' => $qty
	    );

	    //echo print_r($history_data[$neo_id], true) . "<br>";

	    if (($key = array_search($neo_id, $retrieve_ids)) !== false) {
		unset($retrieve_ids[$key]);
	    }
	}

	//We unset all the ones we retrieved. Anything left wasn't there.
	foreach ($retrieve_ids as $neo_id) {
	    $history_data[$neo_id] = false;
	}
    }

    //NOW we return relevant data, which by now is all in the static $history_data array.
    $return = array();
    foreach ($neo_ids as $neo_id => $values) {
	if (!array_key_exists($neo_id, $history_data)) {
	    $return[$neo_id] = false;
	} else {
	    $return[$neo_id] = $history_data[$neo_id];
	}
    }

    return $return;
}

function get_average_spent_on_stockpile_item($neo_id, $qty) {
    $db = db_connect();

    $sql = "select spent_np, date, time from purchases where neo_id = $neo_id ORDER BY date DESC, time DESC LIMIT $qty";
    $item_result = $db->query($sql);

    $spent = array();
    while ($sub_row = $item_result->fetch_assoc()) {
	$spent[] = $sub_row['spent_np'];
    }

    if (sizeof($spent) < $qty) {
	//get legacy information and pad out $spent
	$sql = "select average from legacy_spend where neo_id = $neo_id";
	$legacy_result = $db->query($sql);
	$average = 0;
	while ($avg_row = $legacy_result->fetch_assoc()) {
	    $average = $avg_row['average'];
	}
	$add_count = $qty - sizeof($spent);
	for ($i = 0; $i < $add_count; ++$i) {
	    $spent[] = $average;
	}
    }

    if (sizeof($spent) > 0) {
	return ceil(array_sum($spent) / count($spent));
    } else {
	return 0;
    }
}

/**
 * Returns an array with some or all of the following keys:
 * - spent: Average spent on either shop qty, or stock + sdb quantity
 * - price: Sale price for my shop
 * - base: Only useful for quest shop "window" pricing.
 * - percent_rank: "window" pricing
 * @staticvar array $price_settings Local cache of price settings by my_type
 * @param array $item_row All the standard stuff we know about a stock item
 * @return array Should at least return a price key.
 */
function get_pricing_info($item_row) {
    static $price_settings = array();
    if (!array_key_exists($item_row['my_type'], $price_settings)) {
	$price_settings[$item_row['my_type']] = get_setting_group($item_row['my_type']);
    }

    //if it's an unload, do that and return early.
    if ($item_row['unload']) {
	$multiplier = $price_settings[$item_row['my_type']]['Unload Mult'];
	$return['price'] = floor($item_row['sold_np'] * $multiplier);
	return $return;
    }

    //now check $price_settings for how to calc the real price
    if ($price_settings[$item_row['my_type']] !== false && array_key_exists('Shop Pricing', $price_settings[$item_row['my_type']])) {
	//let's set some major types here.
	switch ($price_settings[$item_row['my_type']]['Shop Pricing']) {
	    case 'Windows':
		//base is weird and totally arbitrary, but this is what I had in my sheets....
		$return['base'] = ceil(($item_row['spent'] + ($item_row['sold_np'] * 3)) / 4);
		$return['percent_rank'] = get_upper_percentile_rank($item_row['name'], $item_row['my_type']);

		$low = $price_settings[$item_row['my_type']][$item_row['my_subtype'] . ' Low'];
		$high = $price_settings[$item_row['my_type']][$item_row['my_subtype'] . ' High'];
		$window_add = ($high - $low) * $return['percent_rank'] + $low;
		//if we have anything in the SDB and the going price is ahead of where we want to be, 
		//float more or less with the going price
		if ($item_row['sdb_qty'] > 0 && $item_row['sold_np'] > ($item_row['spent'] + $window_add)) {
		    $return['base'] = $item_row['sold_np'];
		    $window_add = 100;
		}
		$return['price'] = ceil($return['base'] + $window_add);
		if ($return['price'] < 1001) {
		    $return['price'] = 1001;
		}
		break;
	    case 'Linear':
		$increace_percent = $price_settings[$item_row['my_type']]['Target Gain'];
		$sale_mult = $price_settings[$item_row['my_type']]['Sale Mult'];
		if ($item_row['sold_np'] > $item_row['spent'] * $increace_percent) {
		    $return['price'] = floor($item_row['sold_np'] * $sale_mult);
		} else {
		    $return['price'] = ceil($item_row['spent'] * $increace_percent);
		}
		break;
	}
    }

    return $return;
}

function cache_sale_price($item_row) {
    if (!array_key_exists('prices', $_SESSION)) {
	$_SESSION['prices'] = array();
    }

    if (!array_key_exists($item_row['neo_id'], $_SESSION['prices'])) {
	$_SESSION['prices'][$item_row['neo_id']] = array(
	    'price' => $item_row['price'],
	    'ts' => time() //now, in seconds
	);
    } else {
	//The key exists.
	//If it's within whatever interval we care about, leave it alone. Otherwise, overwrite.
	$hours = 3;
	$seconds = $hours * 60 * 60;
	$ts_floor = time() - $seconds;
	if ($_SESSION['prices'][$item_row['neo_id']]['ts'] < $ts_floor) {
	    $_SESSION['prices'][$item_row['neo_id']] = array(
		'price' => $item_row['price'],
		'ts' => time() //now, in seconds
	    );
	}
    }
}

function get_cached_sale_price($item_row) {
    if (!array_key_exists('prices', $_SESSION) || !array_key_exists($item_row['neo_id'], $_SESSION['prices'])) {
	return null;
    }

    return $_SESSION['prices'][$item_row['neo_id']]['price'];
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

function add_ajax_sdb_empty_button($neo_id) {
    $ret = "<img src='close.png' class='zero_img opacity_50' onClick=\"do_ajax('sdb_qty', [{'neo_id': '$neo_id', 'sdb_qty': '0'}], mark_complete($(this)))\", onMouseOver=\"$(this).removeClass('opacity_50')\", onMouseOut=\"$(this).addClass('opacity_50')\")\">";
    return $ret;
}

function add_ajax_shop_stock_buttons($data) {
    $neo_id = $data['neo_id'];
    $ret = "<div class='right'><table class='structure'><tr><td>"
	    . "<img src='up.png' class='arrow_img opacity_50' "
	    . "onClick=\"do_ajax('shop_qty', [{'neo_id': '$neo_id', 'shop_qty': get_shop_qty($neo_id) + 1}], "
	    . "update_shop_qty($neo_id, get_shop_qty($neo_id) + 1))\", "
	    . "onMouseOver=\"$(this).removeClass('opacity_50')\", "
	    . "onMouseOut=\"$(this).addClass('opacity_50')\")\">"
	    . "</td></tr><tr><td>"
	    . "<img src='down.png' class='arrow_img opacity_50' "
	    . "onClick=\"do_ajax('shop_qty', [{'neo_id': '$neo_id', 'shop_qty': get_shop_qty($neo_id) - 1}], "
	    . "update_shop_qty($neo_id, get_shop_qty($neo_id) - 1))\", "
	    . "onMouseOver=\"$(this).removeClass('opacity_50')\", "
	    . "onMouseOut=\"$(this).addClass('opacity_50')\")\">"
	    . "</td></tr></table></div>";
    return $ret;
}
