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


require_once('common.php');

//header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']); //lol, please don't
//Also... OMG this doesn't even work while receiving JSON. wtf. $_POST also useless as you
//might expect.

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

//TODO: Actual error handling and crap.
// Takes raw data from the request
$json = file_get_contents('php://input');

// Converts it into a PHP object
$data = json_decode($json, true);

if (!is_array($data)) {
    error_log("No data in the request? Investigate.");
    error_log($json);
    return_result('failure', "Invalid request");
    return false;
}

if (!array_key_exists('key', $data)) {
    error_log("No auth key supplied");
    return_result('failure', "Invalid request");
    return false;
}

if (!check_auth_key($data['key'])) {
    error_log("Wrong auth key supplied");
    return_result('failure', "Invalid request");
    return false;
}

if (!array_key_exists('action', $data)) {
    error_log("No action specified. Investigate.");
    error_log(print_r($data, true));
    return_result('failure', "Invalid request");
    return false;
}

$result = handle_request($data);

/**
 * Render the results of whatever we were trying to do here.
 * @param string $status 
 * @param type $message
 * @param type $data
 */
function return_result($status, $message, $data = null) {
    $return = array();
    $return['status'] = $status;
    $return['message'] = $message;

    if (!is_null($data)) {
	$return['data'] = $data;
    }

    header('Content-Type: application/json');

    echo json_encode($return);

    //and, just in case...
    die();
}

/**
 * TODO: Need a function that takes the posted data, and...
 * 	checks:what we're trying to do
 * 	Schema / type checking against the incoming array
 * 	Routes to the right saving function
 */

function handle_request($data) {

    $action = $data['action'];

    //always validate data
    if (!validate_data($action, $data['rows'])) {
	return_result('failure', "Invalid data");
	return false;
    }

    switch ($action) {
	case 'trading_post':
	case 'stock_transactions':
	    $rowcount = db_query($action, 'insert', $data['rows']);
	    if ($rowcount > 0) {
		return_result('success', "$action: $rowcount rows updated");
	    } else {
		return_result('failure', "Problem writing to the database");
	    }
	    break;
	case 'sales_history':
	    $data['rows'] = add_restock_bit($data['rows']);
	    $rowcount = db_query($action, 'insert', $data['rows']);
	    if ($rowcount > 0) {
		return_result('success', "$action: $rowcount rows updated");
	    } else {
		return_result('failure', "Problem writing to the database");
	    }
	    break;
	case 'purchase':
	    $stockpiling = get_setting_value('stockpiling');
	    if ($stockpiling) {
		$stockpiling = 1;
	    } else {
		$stockpiling = 0;
	    }
	    foreach ($data['rows'] as $index => $row) {
		$data['rows'][$index]['stockpiling'] = $stockpiling;
	    }
	    $rowcount = db_query($action, 'insert', $data['rows']);
	    if ($rowcount > 0) {
		return_result('success', "$action: $rowcount rows updated");
	    } else {
		return_result('failure', "Problem writing to the database");
	    }
	    break;
	case 'ssw_last':
	    //add the missing item if it's missing.

	    foreach ($data['rows'] as $ind => $row) {
		$item_exists = ensure_item_exists($row['item_name'], $row['neo_id']);
		if (!$item_exists) {
		    return_result('failure', "$action: Couldn't update base item");
		    break;
		}
	    }

	    $rowcount = db_query('ssw_insert', 'insert', $data['rows']);
	    if ($rowcount > 0) {
		return_result('success', "$action: $rowcount rows updated");
	    } else {
		return_result('failure', "Problem writing to the database");
	    }
	    break;
	case 'ssw_no_results':
	    //we just have one single item, with only the name
	    //get the neo_id if it exists. If not, create the item and use internal.
	    $item = get_or_insert_item_by_name($data['rows'][0]['item_name']);
	    if (!$item) {
		return_result('failure', "Couldn't retrieve the relevant item");
	    }

	    if (!is_null($item['neo_id'])) {
		$data['rows'][0]['neo_id'] = $item['neo_id'];
		$rowcount = db_query('ssw_no_results', 'insert_with_neo_id', $data['rows']);
	    } else {
		$data['rows'][0]['my_id'] = $item['id'];
		//error_log(print_r($data['rows'][0], true));
		$rowcount = db_query('ssw_no_results', 'insert_without_neo_id', $data['rows']);
	    }

	    if ($rowcount > 0) {
		return_result('success', "$rowcount zero searches added");
	    }

	    return_result('failure', "Couldn't add zero search");
	    break;
	case 'sdb_inventory':
	    $item_rowcount = save_sdb_item_data($data['rows']);
	    if ($item_rowcount == 0) {
		return_result('failure', "Problem writing to the database");
	    } else {
		$qty_rowcount = save_sdb_qty($data['rows']);
		if ($qty_rowcount > 0) {
		    return_result('success', "Updated $item_rowcount items, and  $qty_rowcount quantities");
		} else {
		    return_result('failure', "Updated $item_rowcount items, but failed to update quantity data");
		}
	    }
	    break;
	case 'sdb_qty':
	    $qty_rowcount = save_sdb_qty($data['rows']);
	    if ($qty_rowcount > 0) {
		return_result('success', "Updated $qty_rowcount quantities");
	    } else {
		return_result('failure', "Failed to update quantity data");
	    }
	    break;
	case 'shop_qty':
	    $qty_rowcount = save_shop_qty($data['rows']);
	    if ($qty_rowcount > 0) {
		return_result('success', "Updated $qty_rowcount quantities");
	    } else {
		return_result('failure', "Failed to update quantity data");
	    }
	    break;
	case 'gc_list':
	    //this one is going to be way tricky.We can't actually do the on duplicate key update,
	    //because item name is most definitely not unique, and that's all we have. :/
	    //I think we'll just have to do something relatively insane...
	    //I wonder how many WHERE IN items we can cram into a single query without everything exploding.

	    $result = update_gourmet_list_from_jn($data['rows']);
	    if (!$result) {
		error_log('I guess update gourmet list went wrong');
	    } else {
		return_result('success', $result);
	    }

	    break;
	case 'jn_item':
	    //Two things, now. First, update the rarity and release date.
	    //If we have any rows in price_history, add any *new* ones to jn_prices

	    $item_name = $data['rows'][0]['item_name'];
	    $item = get_or_insert_item_by_name($item_name);
	    $id = $item['id'];
	    if ($id) {
		$data['rows'][0]['id'] = $id;
	    } else {
		return_result('failure', "Problem retrieving item $item_name");
	    }

	    $updated_rr_string = 'not updated';
	    $updated_jn_price_rows = 0;

	    if (!is_null($data['rows'][0]['rarity'])) {
		//update rarity and releae date. Just do it.
		$result = db_query('jn_item', 'update_rarity_and_release_date', $data['rows']);
		if ($result === false) {
		    return_result('failure', "Failed to update '$item_name' rarity and/or release date");
		} else {
		    $updated_rr_string = 'updated';
		}
	    }
	    if (!empty($data['rows'][0]['price_history'])) {
		$insert_me = $data['rows'][0]['price_history'];
		//trim out the lines that exist, and send the rest in.
		$exists = db_raw_query("select * from jn_prices where item_name = '$item_name'");
		if ($exists && !empty($exists)) {
		    foreach ($exists as $i => $exists_values) {
			foreach ($insert_me as $j => $insert_values) {
			    if ($insert_values['price_date'] == $exists_values['date']) {
				unset($insert_me[$j]);
				break;
			    }
			}
		    }
		}

		if (!empty($insert_me)) {
		    //add the item name into every line. :/
		    foreach ($insert_me as $i => $values) {
			$insert_me[$i]['item_name'] = $item_name;
		    }

		    //do the batch query
		    $result = db_query('jn_item', 'insert_jn_prices', $insert_me);
		    if ($result === false) {
			return_result('failure', "Failed updating $id price history");
		    } else {
			$updated_jn_price_rows = $result;
		    }
		}

	    }
	    return_result('failure', "Rarity/release $updated_rr_string, added $updated_jn_price_rows price rows");

	    break;
	case 'stock_market':
	    //check to see if we've already saved somethin today, before you do the standard thing.
	    $date = $data['rows'][0]['date'];
	    if (check_later_stock_date($date)) {
		$rowcount = db_query($action, 'insert', $data['rows']);
		if ($rowcount > 0) {
		    return_result('success', "$action: $rowcount rows updated");
		} else {
		    return_result('failure', "Problem writing to the database");
		}
	    } else {
		return_result('success', "No update needed");
	    }

	    break;
	case 'stock_get_1year_highs':
	    $stocks = array();
	    foreach ($data['rows'] as $ind => $values) {
		$stocks[] = $values['stock'];
	    }
	    $max_stock_prices = get_max_stock_prices($stocks);
	    if (is_array($max_stock_prices) && !empty($max_stock_prices)) {
		return_result('success', "Max prices retrieved", $max_stock_prices);
	    }
	    return_result('failure', "Something went awry. No data.");
	    break;
	case 'restocked':
	    $neo_id = $data['rows'][0]['neo_id'];
	    $qty = $data['rows'][0]['qty'];
	    if (remove_restock_bits_by_id($neo_id, $qty)) {
		return_result('success', "Restock bits removed for neo_id '$neo_id'");
	    } else {
		return_result('failure', "Problem writing to the database");
	    }
	    break;
	case 'restock_quick':
	    //get a list of IDs limited by qty, remove bits from items in that list.
	    if (remove_restock_bits_by_name_qty($data['rows'])) {
		return_result('success', "Restock bits removed for restock batch");
	    } else {
		return_result('failure', "Problem writing to the database");
	    }
	    break;
	default:
	    return_result('failure', "Invalid action '$action'");
    }
}

//temp until I can get all of them moved over.
function validate_data($action, $data) {
    $expected_data = get_expected_data($action);
    if ($expected_data) {
	return validate($data, $expected_data);
    }
    return false;
}

function get_expected_data($action) {
    $dmi = get_data_model_info($action);
    if (is_array($dmi) && array_key_exists('data', $dmi)) {
	return $dmi['data'];
    }
    return false;
}

//Tryna refactor a few things at a time with this
function get_data_model_info($action) {
    $model_info = array();

    /** sales_history * */
    $model_info['sales_history'] = array(
	'data' => array(
	    'date' => 'date',
	    'item_name' => 'varchar_64',
	    'purchased_by' => 'varchar_32',
	    'sold_np' => 'int'
	),
	'insert' => array(
	    'query' => 'INSERT INTO sales '
	    . '(date, item_name, purchased_by, sold_np, restock) '
	    . 'VALUES (?,?,?,?,?)',
	    'binding' => 'sssii', //later, we can do this programmatically based on the types in 'data'
	    'data' => array(
		'date' => 'date',
		'item_name' => 'varchar_64',
		'purchased_by' => 'varchar_32',
		'sold_np' => 'int',
		'restock' => 'bool'
	    )
	)
    );

    /** purchase * */
    $model_info['purchase'] = array(
	'data' => array(
	    'date' => 'date',
	    'time' => 'time',
	    'neo_id' => 'int',
	    'sold_by' => 'varchar_32',
	    'spent_np' => 'int'
	),
	'insert' => array(
	    'query' => 'INSERT INTO purchases '
	    . '(date, time, neo_id, sold_by, spent_np, stockpiling) '
	    . 'VALUES (?,?,?,?,?,?)',
	    'binding' => 'ssisii',
	    'data' => array(
		'date' => 'date',
		'time' => 'time',
		'neo_id' => 'int',
		'sold_by' => 'varchar_32',
		'spent_np' => 'int',
		'stockpiling' => 'bool'
	    ),
	)
    );

    /** ssw_last * */
    $model_info['ssw_last'] = array(
	'data' => array(
	    'date' => 'date',
	    'time' => 'time',
	    'item_name' => 'varchar_64',
	    'neo_id' => 'int',
	    'sold_by' => 'varchar_32',
	    'sold_np' => 'int',
	    'ssw_total' => 'int'
	)
    );

    $model_info['ssw_insert'] = array(
	'data' => array(
	    'date' => 'date',
	    'time' => 'time',
	    'neo_id' => 'int',
	    'sold_by' => 'varchar_32',
	    'sold_np' => 'int',
	    'ssw_total' => 'int'
	),
	'insert' => array(
	    'query' => 'INSERT INTO ssw_next '
	    . '(date, time, neo_id, sold_by, sold_np, ssw_total) '
	    . 'VALUES (?,?,?,?,?,?)',
	    'binding' => 'ssisii'
	)
    );

    /** sdb_inventory * */
    $model_info['sdb_inventory'] = array(
	'data' => array(
	    'name' => 'varchar_64',
	    'neo_type' => 'varchar_32',
	    'sdb_qty' => 'int',
	    'neo_id' => 'int'
	),
    );

    /** sdb_qty * */
    $model_info['sdb_qty'] = array(
	'data' => array(
	    'sdb_qty' => 'int',
	    'neo_id' => 'int'
	),
    );

    /** shop_qty * */
    $model_info['shop_qty'] = array(
	'data' => array(
	    'shop_qty' => 'int',
	    'neo_id' => 'int'
	),
    );

    /** trading_post * */
    $model_info['trading_post'] = array(
	'data' => array(
	    'date' => 'date',
	    'time' => 'time',
	    'item_name' => 'varchar_64',
	    'tp_trades' => 'int',
	    'tp_items_found' => 'int',
	    'tp_price' => 'int|null'
	),
	'insert' => array(
	    'query' => 'INSERT INTO trading_post '
	    . '(date, time, item_name, tp_trades, tp_items_found, tp_price) '
	    . 'VALUES (?,?,?,?,?,?)',
	    'binding' => 'sssiii'
	)
    );

    /** Stock Market * */
    $model_info['stock_market'] = array(
	'data' => array(
	    'date' => 'date',
	    'stock' => 'varchar_8',
	    'price' => 'int'
	),
	'insert' => array(
	    'query' => 'INSERT INTO stock_prices '
	    . '(date, stock, price) '
	    . 'VALUES (?,?,?)',
	    'binding' => 'ssi'
	),
    );

    $model_info['stock_transactions'] = array(
	'data' => array(
	    'date' => 'date',
	    'stock' => 'varchar_8',
	    'price_point' => 'int',
	    'volume' => 'int',
	    'total_np' => 'int'
	),
	'insert' => array(
	    'query' => 'INSERT INTO stock_transactions '
	    . '(date, stock, price_point, volume, total_np) '
	    . 'VALUES (?,?,?,?,?)',
	    'binding' => 'ssiii'
	),
    );

    $model_info['stock_get_1year_highs'] = array(
	'data' => array(
	    'stock' => 'varchar_8'
	)
    );

    $model_info['gc_list'] = array(
	'data' => array(
	    'name' => 'varchar_64',
	    'rarity' => 'int'
	)
    );

    $model_info['jn_item'] = array(
	'data' => array(
	    'item_name' => 'varchar_64',
	    'rarity' => 'int|null',
	    'release_date' => 'date|null',
	    'price_history' => array(
		'price' => 'int',
		'price_date' => 'date'
	    )
	),
	'update_rarity_and_release_date' => array(
	    'query' => 'UPDATE items SET rarity = ?, release_date = ? where id = ?',
	    'binding' => 'isi',
	    'data' => array(
		'rarity' => 'int',
		'release_date' => 'date',
		'id' => 'int'
	    )
	),
	'insert_jn_prices' => array(
	    'query' => 'INSERT INTO jn_prices (item_name, date, jn_price) VALUES (?,?,?)',
	    'binding' => 'ssi',
	    'data' => array(
		'item_name' => 'varchar_64',
		'price_date' => 'date',
		'price' => 'int'
	    )
	)
    );

    $model_info['update_item_rarity_by_name'] = array(
	'data' => array(
	    'rarity' => 'int',
	    'name' => 'varchar_64'
	),
	'update' => array(
	    'query' => 'UPDATE items SET rarity = ? WHERE name = ?',
	    'binding' => 'is'
	)
    );

    $model_info['update_item_name_neo_id'] = array(
	'name_by_neo_id' => array(
	    'query' => 'UPDATE items SET name = ? WHERE neo_id = ?',
	    'data' => array(
		'name' => 'varchar_64',
		'neo_id' => 'int'),
	    'binding' => 'si'
	),
	'neo_id_by_name' => array(
	    'query' => 'UPDATE items SET neo_id = ? WHERE name = ?',
	    'data' => array(
		'neo_id' => 'int',
		'name' => 'varchar_64'
	    ),
	    'binding' => 'is'
	)
    );

    $model_info['insert_item_name_and_rarity'] = array(
	'data' => array(
	    'name' => 'varchar_64',
	    'rarity' => 'int'
	),
	'insert' => array(
	    'query' => 'INSERT INTO items '
	    . '(name, rarity)'
	    . 'VALUES (?,?)',
	    'binding' => 'si'
	)
    );

    $model_info['insert_item_name_and_neo_id'] = array(
	'data' => array(
	    'name' => 'varchar_64',
	    'neo_id' => 'int'
	),
	'insert' => array(
	    'query' => 'INSERT INTO items '
	    . '(name, neo_id)'
	    . 'VALUES (?,?)',
	    'binding' => 'si'
	)
    );

    $model_info['insert_gc_item'] = array(
	'data' => array(
	    'my_id' => 'int'
	),
	'insert_update' => array(
	    'query' => 'INSERT INTO gourmet_club '
	    . '(my_id)'
	    . 'VALUES (?) '
	    . 'ON DUPLICATE KEY UPDATE '
	    . 'my_id = ?',
	    'binding' => 'ii'
	)
    );

    $model_info['ssw_no_results'] = array(
	'data' => array(
	    'item_name' => 'varchar_64',
	    'date' => 'date',
	    'time' => 'time'
	),
	'insert_with_neo_id' => array(
	    'query' => 'INSERT INTO ssw_next '
	    . '(date, time, neo_id, ssw_total) '
	    . 'VALUES (?,?,?,0)',
	    'binding' => 'ssi',
	    'data' => array(
		'date' => 'date',
		'time' => 'time',
		'neo_id' => 'int'
	    )
	),
	'insert_without_neo_id' => array(
	    'query' => 'INSERT INTO ssw_next '
	    . '(date, time, my_id, ssw_total) '
	    . 'VALUES (?,?,?,0)',
	    'binding' => 'ssi',
	    'data' => array(
		'date' => 'date',
		'time' => 'time',
		'my_id' => 'int'
	    )
	)
    );

    $model_info['restocked'] = array(
	'data' => array(
	    'neo_id' => 'int',
	    'qty' => 'int'
	),
	'update' => array(//getting an error here - this version of mySQL doesn't support limit in subqueries. Blast.
	    'query' => 'UPDATE sales SET restock=0 WHERE id IN '
	    . '(select id from sales '
	    . 'left join items i on sales.item_name = i.name '
	    . 'where i.neo_id = ? and sales.restock=1 LIMIT ?)',
	    'binding' => 'ii'
	)
    );

    $model_info['restock_quick'] = array(
	'data' => array(
	    'item_name' => 'varchar_64',
	    'qty' => 'int'
	)
    );

    //and finally return
    if (array_key_exists($action, $model_info)) {
	return $model_info[$action];
    }
    error_log(__FUNCTION__ . ": No $action key defined");
    return false;
}

//Okay, this is now for everything.
function db_query($action, $query_type, $data) {
    $model_info = get_data_model_info($action);
    if (!$model_info) {
	return false;
    }

    $db = db_connect();
    $query = $model_info[$query_type]['query'];
    $stmt = $db->prepare($query);
    if (!$stmt) {
	error_log(__FUNCTION__ . ": $action, $query_type query unpreparable.");
	error_log($query);
	return false;
    }

    //moving some stuff around, here...
    //still haven't really decided how I want to store queries and whatever.
    $data_structure = false;
    if (array_key_exists('data', $model_info[$query_type])) {
	$data_structure = $model_info[$query_type]['data'];
    } else {
	$data_structure = $model_info['data'];
    }

    //Try this, kids at home!
    extract($data_structure);
    $params = array(
	$model_info[$query_type]['binding']
    );

    //oh god
    foreach ($data_structure as $key => $whatever) {
	//pass the variable variable by reference. Obviously.
	$params[] = &$$key;
    }

    //This is probably confusing, and could totally be clenaer
    //For insert/update queries, we have to send the variable references twice
    if ($query_type === 'insert_update') {
	foreach ($data_structure as $key => $whatever) {
	    $params[] = &$$key;
	}
    }

    //allegedly, you can call methods of instantiated objects with call_user_func_array like this...
    $call_me = array(
	$stmt,
	'bind_param'
    );

    //for the record, I definitely hate myself by now.
    call_user_func_array($call_me, $params);

    $db->query("START TRANSACTION");
    $rowcount = 0;
    foreach ($data as $row => $line) {
	//oh look, more.
	foreach ($data_structure as $key => $whatever) {
	    $$key = $line[$key];
	}
	if ($stmt->execute()) {
	    ++$rowcount;
	}
    }
    $stmt->close();
    $res = $db->query("COMMIT");

    return $rowcount;
}

function save_sdb_item_data($data) {
    $db = db_connect();
    $query = "INSERT INTO items (name, neo_type, neo_id) VALUES (?,?,?)";
    $query .= " ON DUPLICATE KEY UPDATE name = ?, neo_type = ?, neo_id = ?";
    $stmt = $db->prepare($query);

    if (!$stmt) {
	error_log($db->error);
    }

    $name = '';
    $neo_type = '';
    $neo_id = '';

    //what the
    $stmt->bind_param("ssissi", $name, $neo_type, $neo_id, $name, $neo_type, $neo_id);

    $db->query("START TRANSACTION");
    $rowcount = 0;
    foreach ($data as $row => $line) {
	$name = $line['name'];
	$neo_type = $line['neo_type'];
	$neo_id = $line['neo_id'];
	if ($stmt->execute()) {
	    ++$rowcount;
	}
    }
    $stmt->close();
    $res = $db->query("COMMIT");

    return $rowcount;
}

function save_sdb_qty($data) {
    $db = db_connect();
    $query = "INSERT INTO inventory (neo_id, sdb_qty) VALUES (?,?)";
    $query .= " ON DUPLICATE KEY UPDATE neo_id = ?, sdb_qty = ?";
    $stmt = $db->prepare($query);

    if (!$stmt) {
	error_log($db->error);
    }

    $neo_id = '';
    $sdb_qty = '';

    //what the
    $stmt->bind_param("iiii", $neo_id, $sdb_qty, $neo_id, $sdb_qty);

    $db->query("START TRANSACTION");
    $rowcount = 0;
    foreach ($data as $row => $line) {
	$neo_id = $line['neo_id'];
	$sdb_qty = $line['sdb_qty'];
	if ($stmt->execute()) {
	    ++$rowcount;
	}
    }
    $stmt->close();
    $res = $db->query("COMMIT");

    return $rowcount;
}

function save_shop_qty($data) {
    $db = db_connect();
    $query = "INSERT INTO inventory (neo_id, shop_qty) VALUES (?,?)";
    $query .= " ON DUPLICATE KEY UPDATE neo_id = ?, shop_qty = ?";
    $stmt = $db->prepare($query);

    if (!$stmt) {
	error_log($db->error);
    }

    $neo_id = '';
    $shop_qty = '';

    //what the
    $stmt->bind_param("iiii", $neo_id, $shop_qty, $neo_id, $sdb_qty);

    $db->query("START TRANSACTION");
    $rowcount = 0;
    foreach ($data as $row => $line) {
	$neo_id = $line['neo_id'];
	$sdb_qty = $line['shop_qty'];
	if ($stmt->execute()) {
	    ++$rowcount;
	}
    }
    $stmt->close();
    $res = $db->query("COMMIT");

    return $rowcount;
}

function db_raw_query($query) {
    $db = db_connect();
    $result = $db->query($query);
    if ($result === false) { //this should actually do what I want...
	error_log(__FUNCTION__ . " exploded.");
	error_log($db->error);
	return false;
    }

    //for those times you're just inserting things
    if ($result === true) {
	return true;
    }

    $ret = [];
    //there are probably nicer ways to do this, but.
    while ($row = $result->fetch_assoc()) {
	$ret[] = $row;
    }
    return $ret;
}

function update_gourmet_list_from_jn($items) {
    //$items is just a huge array of name and rarity rows from jellyneo.

    //see if the item exists yet
    $where_in = '(';
    foreach ($items as $row => $data) {
	//build the WHERE IN parens string
	if ($where_in !== '(') {
	    $where_in .= ', ';
	}
	$where_in .= "'" . $data['name'] . "'";
    }
    $where_in .= ")";

    //this is, btw, about 1400 rows at the time of this coding
    $query = "select id, name, rarity from items where name IN $where_in";
    $existing_gc_items = db_raw_query($query);

    //change this into something we can use more readily
    $existing_items_by_name = [];
    if (is_array($existing_gc_items)) {
	foreach ($existing_gc_items as $whocares => $rowdata) {
	    $existing_items_by_name[$rowdata['name']] = array(
		'id' => $rowdata['id'],
		'rarity' => $rowdata['rarity'],
	    );
	}
    }

    //now, figure out what needs updating, and update accordingly..
    $new_items = [];
    $needs_rarity = [];

    foreach ($items as $row => $rowdata) {
	if (array_key_exists($rowdata['name'], $existing_items_by_name)) {
	    if ($existing_items_by_name[$rowdata['name']]['rarity'] < 1) {
		$needs_rarity[] = $rowdata;
	    }
	} else {
	    $new_items[] = $rowdata;
	}
    }

    if (count($needs_rarity) > 0) {
	$rowcount_rarity = db_query('update_item_rarity_by_name', 'update', $needs_rarity);
	if (!$rowcount_rarity) {
	    error_log("No rarity rows updated.");
	    return false;
	}
    }

    $new_gc_items = array();
    if (count($new_items) > 0) {
	$rowcount_new = db_query('insert_item_name_and_rarity', 'insert', $new_items);
	if (!$rowcount_new) {
	    error_log("No new GC rows inserted.");
	    return false;
	}

	//is this stupid? Kinda.
	$where_in = '(';
	foreach ($new_items as $row => $data) {
	    //build the WHERE IN parens string
	    if ($where_in !== '(') {
		$where_in .= ', ';
	    }
	    $where_in .= "'" . $data['name'] . "'";
	}
	$where_in .= ")";

	$query = "select id, name, rarity from items where name IN $where_in";
	$new_gc_items = db_raw_query($query);

	if (count($new_gc_items) == 0) {
	    error_log(__FUNCTION__ . ": Couldn't retrieve new rows.");
	    return false;
	}
    }

    //now, paste the $existing_gc_items and $new_gc_items together, and add them to the gc table
    $add_to_gc_list = array_merge($existing_gc_items, $new_gc_items);
    //format the way the query function expects it...
    foreach ($add_to_gc_list as $index => $values) {
	$add_to_gc_list[$index] = array(
	    'my_id' => $values['id']
	);
    }
    //and go
    //TODO: This works, but takes forever. Maybe stop doing insert/uodates at all. Too slow.
    $rows_poked = db_query('insert_gc_item', 'insert_update', $add_to_gc_list);

    if (!$rows_poked) {
	error_log(__FUNCTION__ . ": Most everything happened, but no gc table rows were even poked.");
	return false;
    }

//  return something useful...
    $incoming_count = count($items);
    $found_count = count($existing_items_by_name);

    $ret = "Processed $incoming_count incoming items\n"
	    . "Found $found_count items already in the db. $rowcount_rarity updated rarity\n"
	    . "Added $rowcount_new items";

    return $ret;
}

function ensure_item_exists($item_name, $neo_id) {
    //see if it's already all in there.
    $query = "select * from items where name = '$item_name' and neo_id = $neo_id";
    $check = db_raw_query($query);

    if ($check === false) {
	error_log(__FUNCTION__ . ": Error running query \n$query");
	return false;
    }

    if (count($check) === 1) {
	//nothing to do here.
	return true;
    }

    //...anything?
    $query = "select * from items where name = '$item_name' OR neo_id = $neo_id";
    $check = db_raw_query($query);

    if ($check === false) {
	error_log(__FUNCTION__ . ": Error running query \n$query");
	return false;
    }

    //we're gonna need this one way or another
    $data[] = array(
	'neo_id' => $neo_id,
	'name' => $item_name
    );
    if (count($check) === 0) {
	//easy - just add the item.
	$lines = db_query('insert_item_name_and_neo_id', 'insert', $data);
	if ($lines) {
	    return true;
	} else {
	    error_log(__FUNCTION__ . ": Error inserting new item '$item_name', '$neo_id'");
	    return false;
	}
    }

    //If we're still here, check to make sure we don't have to do a stupid merge
    if (count($check) > 1) {
	error_log(__FUNCTION__ . ": Help! I need an adult...");
	error_log(print_r($data));
	error_log(print_r($check));
	return false;
    }

    //which one do we have? I'm leaving this long in case multiple return hits becomes less scary sometime
    $found_neo_id = false;
    $found_name = false;
    foreach ($check as $id => $line) {
	if (!is_null($line['neo_id'])) {
	    $found_neo_id = true;
	}
	if (!is_null($line['name'])) {
	    $found_name = true;
	}
    }

    if ($found_name && !$found_neo_id) {
	//'update_item_name_neo_id' 'name_by_neo_id' and 'neo_id_by_name'
	$lines = db_query('update_item_name_neo_id', 'neo_id_by_name', $data);
	if ($lines) {
	    return true;
	} else {
	    error_log(__FUNCTION__ . ": Error inserting updating item's id: '$item_name', '$neo_id'");
	    return false;
	}
    }

    if ($found_neo_id && !$found_name) {
	$lines = db_query('update_item_name_neo_id', 'name_by_neo_id', $data);
	if ($lines) {
	    return true;
	} else {
	    error_log(__FUNCTION__ . ": Error inserting updating item's name: '$item_name', '$neo_id'");
	    return false;
	}
    }

    if ($found_neo_id && $found_name) {
	//the id is probably different...

	$lines = db_query('update_item_name_neo_id', 'name_by_neo_id', $data);
	if ($lines) {
	    return true;
	} else {
	    error_log(__FUNCTION__ . ": Error inserting updating item's name: '$item_name', '$neo_id'");
	    return false;
	}
    }
}

function get_or_insert_item_by_name($item_name) {
    $query = "select * from items where name = '$item_name'";
    $check = db_raw_query($query);
    if ($check === false) {
	error_log(__FUNCTION__ . ": First query exploded.");
	return false;
    }

    if (count($check) === 1) {
	return $check[0];
    }

    if (count($check) === 0) {
	//insert the item and return it.
	$query = "INSERT INTO items (name) VALUES ('$item_name')";
	$check_insert = db_raw_query($query);
	if ($check_insert === false) {
	    error_log(__FUNCTION__ . ": Failed to isnert new item '$item_name'");
	    return false;
	}
	//HAHA, you completely predictable monster.
	return get_or_insert_item_by_name($item_name);
    }

    if (count($check) > 1) {
	error_log(__FUNCTION__ . ": Edge case: Too many results with the same dang name.");
	return false;
    }
}

function check_later_stock_date($date) {
    $query = "select MAX(date) as max from stock_prices";
    $max_date = db_raw_query($query);
    if ($max_date !== false) {
	$max_date = $max_date[0]['max'];
    }
    if ($max_date < $date) {
	return true;
    }
    return false;
}

function get_max_stock_prices($stocks) {
    $query = "select stock, MAX(price) as price from stock_prices "
	    . "where date > DATE(NOW()-INTERVAL 1 YEAR) "
	    . "GROUP BY stock";
    $results = db_raw_query($query);
    $return = array();
    foreach ($results as $row => $data) {
	if (in_array($data['stock'], $stocks)) {
	    $return[] = $results[$row];
	}
    }
    return $return;
}

function add_restock_bit($rows) {
    //make a list of item IDs out of $rows
    $item_names = array();
    foreach ($rows as $index => $data) {
	$item_names[] = $data['item_name'];
    }

    $item_names = array_unique($item_names);

    $item_name_string = "'" . implode("', '", $item_names) . "'";


    $query = "SELECT i.name, inv.shop_qty from inventory inv "
	    . "LEFT JOIN items i on i.neo_id = inv.neo_id "
	    . "WHERE i.name IN ($item_name_string) "
	    . "AND inv.shop_qty IS NOT NULL";

    $restocks = array();

    $results = db_raw_query($query);
    foreach ($results as $row => $data) {
	$restocks[$data['name']] = $data['shop_qty'];
    }

    foreach ($rows as $index => $data) {
	if (array_key_exists($data['item_name'], $restocks)) {
	    //add a bit to the row, and pull one qty off the restocks array
	    $rows[$index]['restock'] = true;
	    if ($restocks[$data['item_name']] === '1') {
		unset($restocks[$data['item_name']]);
	    } else {
		$restocks[$data['item_name']] -= 1;
	    }
	} else {
	    $rows[$index]['restock'] = false;
	}
    }

    return $rows;
}

function remove_restock_bits_by_id($neo_id, $qty) {
    //due to my version of MySQL not allowing limits in subqueries (blast), this has to be done in layers.
    $query = "select sales.id from sales left join items i "
	    . "on sales.item_name = i.name "
	    . "where i.neo_id = '$neo_id' and restock=1 LIMIT $qty";
    $results = db_raw_query($query);
    if ($results === false) {
	error_log(__FUNCTION__ . ": Error finding rows for neo_id '$neo_id' that need restocking");
	return false;
    }
    if (sizeof($results) === 0) {
	error_log(__FUNCTION__ . ": No sales IDs found for neo_id '$neo_id' that need restocking");
	return false;
    }

    $sales_ids = array();
    foreach ($results as $row => $data) {
	$sales_ids[] = $data['id'];
    }

    $sales_ids = implode(", ", $sales_ids);
    return remove_restock_bits_by_sales_ids($sales_ids);
}

function remove_restock_bits_by_name_qty($data) {
    error_log(print_r($data, true));
    $sales_ids = array();
    foreach ($data as $index => $row) {
	$query = "select id from sales "
		. "where item_name = '" . $row['item_name'] . "' and restock=1 "
		. "ORDER BY id ASC LIMIT " . $row['qty'];
	$results = db_raw_query($query);
	if ($results === false) {
	    error_log(__FUNCTION__ . ": Error finding rows for item_name '" . $row['item_name'] . "' that need restocking");
	    return false;
	}

	foreach ($results as $row => $data) {
	    $sales_ids[] = $data['id'];
	}
    }

    $sales_ids = implode(", ", $sales_ids);
    return remove_restock_bits_by_sales_ids($sales_ids);
}

function remove_restock_bits_by_sales_ids($sales_ids) {
    $query = "update sales set restock=0 where id IN ($sales_ids)";
    $result = db_raw_query($query);
    if ($result === false) {
	error_log(__FUNCTION__ . ": Query failed: $query");
	return false;
    }
    return true;
}
