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
$title = "Stockpiling!";
start_page($title);

$params = poke_url_params();
if (array_key_exists('stockpiling', $params)) {
    $stockpiling = $params['stockpiling'];
    if ($stockpiling === 'true' || $stockpiling === 'false') {
	set_setting_value('stockpiling', $stockpiling);
    }
    //unset the stockpiling param
    $params = poke_url_params(array('stockpiling'));
}

echo "Change View: ";
echo "<a href='" . make_link(array('view' => 'all')) . "'>All</a>";
echo " | ";
echo "<a href='" . make_link(array('view' => 'charity_corner')) . "'>Charity Corner</a>";
echo " | ";
echo "<a href='" . make_link(array('view' => 'quest_event')) . "'>Quest Event Prep</a>";
echo "<br><br>";

$stockpiling = get_setting_value('stockpiling');
$stockpiling_string = 'We are not currently stockpiling.';
$link = "<a href='" . make_start_stockpiling_link() . "'>Start</a>";

if ($stockpiling) {
    $stockpiling_string = "We are currently stockpiling.";
    $link = "<a href='" . make_stop_stockpiling_link() . "'>Stop</a>";
}

echo "$stockpiling_string $link<br>";

//charity corner should limit to rarities between 80 and 100, and 102 and 179.
//quest prep should limit to my_type = "Quest Item"
//and hoarding is of course hoarding. Not sure about everything else.

$db = db_connect();
$query = "SELECT p.neo_id, sum(p.spent_np) as spent, count(p.neo_id) as qty, "
	. "i.name, i.rarity, i.my_type, i.my_subtype, next.sold_np as next_price, "
	. "inventory.sdb_qty "
	. "FROM purchases p "
	. "LEFT JOIN items i on p.neo_id = i.neo_id "
	. "LEFT JOIN ( SELECT n.neo_id, n.sold_np from ssw_next n "
	. "	INNER JOIN (SELECT max(id) as last, neo_id from ssw_next GROUP BY neo_id) as latest "
	. "	on n.id = latest.last) as next "
	. " on p.neo_id = next.neo_id "
	. "LEFT JOIN inventory on inventory.neo_id = p.neo_id "
	. "WHERE p.stockpiling = true AND" . get_where_clause()
	. " GROUP BY p.neo_id ";

//echo "$query<br>";

$result = $db->query($query);

$report = array();

$table_structure = array(
    'name' => 'Name',
    'rarity' => 'Rarity',
    'my_type' => 'Type',
    'spent' => 'Spent NP',
    'qty' => 'Quantity',
    'average_cost' => 'Average Cost',
    'next_price' => 'Next SSW',
    'sdb_qty' => 'SDB Quantity',
    'event_value' => 'Event Value Estimate',
    'price_cap' => 'Price Cap'
);

while ($row = $result->fetch_assoc()) {
    if (!isset($report[$row['neo_id']])) {
	$report[$row['neo_id']] = $table_structure;
	foreach ($report[$row['neo_id']] as $key => $value) {
	    if ($value !== '') {
		$report[$row['neo_id']][$key] = 0;
	    }
	    if (array_key_exists($key, $row)) {
		$report[$row['neo_id']][$key] = $row[$key];
	    }
	}
    }
    $report[$row['neo_id']]['average_cost'] = $row['spent'] / $row['qty'];
    $report[$row['neo_id']]['event_value'] = estimate_event_value($row) * $row['qty'];
    $report[$row['neo_id']]['price_cap'] = get_cost_cap($row);
}

echo "<table class='small'>";
echo "<tr class='header'>";
echo "<td>ID</td>";
foreach ($table_structure as $key => $label) {
    echo "<td>$label</td>";
}
echo "</tr>";


foreach ($report as $id => $data) {
    echo "<tr>";
    echo "<td>$id</td>";
    foreach ($table_structure as $key => $label) {
	if ($key === 'name') {
	    echo "<td class='long'>" . $data[$key] . add_copy_icon($data[$key]) . "</td>";
	} else {
	    echo "<td>" . $data[$key] . "</td>";
	}
    }
    echo "</tr>";
}
echo "</table>";


echo "<br><br>";

$query = get_big_list_query();

//echo "$query<br>";
$result = $db->query($query);
$report = array();

$table_structure = array(
    'name' => 'Name',
    'price_cap' => 'Price Cap',
    'rarity' => 'Rarity',
    'my_type' => 'Type',
    'my_subtype' => 'Subtype',
    'next_price' => 'Next SSW',
    'ssw_date' => 'Last Search',
    'sdb_qty' => 'SDB',
    'event_max' => 'Event Max',
    'event_qty_sold' => '# Sold',
    'qty_delta' => 'Qty Delta',
    'estimated_current_value' => 'Estimated Current Value',
    'estimated_event_value' => 'Estimated Event Value',
    'price_delta' => 'Current Price Delta (Cap-SSW)'
);

$total_estimated_value = 0;
$total_event_value = 0;
while ($row = $result->fetch_assoc()) {
    if (!isset($report[$row['name']])) {
	$report[$row['name']] = $table_structure;
	foreach ($report[$row['name']] as $key => $value) {
	    if ($value !== '') {
		$report[$row['name']][$key] = 0;
	    }
	    if (array_key_exists($key, $row)) {
		$report[$row['name']][$key] = $row[$key];
	    }
	}
    }
    $report[$row['name']]['estimated_current_value'] = $row['next_price'] * $row['sdb_qty'];
    $report[$row['name']]['event_max'] = estimate_event_value($row);
    //cost cap is the AVERAGE price they sold for during the last event.
    $report[$row['name']]['price_cap'] = get_cost_cap($row);
    $report[$row['name']]['estimated_event_value'] = $report[$row['name']]['price_cap'] * $row['sdb_qty'];
    $report[$row['name']]['event_qty_sold'] = get_number_sold($row);
    $report[$row['name']]['qty_delta'] = ceil($report[$row['name']]['event_qty_sold'] * 1.2) - $report[$row['name']]['sdb_qty'];
    $total_estimated_value += $report[$row['name']]['estimated_current_value'];
    $total_event_value += $report[$row['name']]['estimated_event_value'];
    $report[$row['name']]['price_delta'] = $report[$row['name']]['price_cap'] - $row['next_price'];
}

$sort_by = 'price_delta'; //default sort
if (array_key_exists('bigsort', $params)) {
    $sort_by = $params['bigsort'];
}

$report = sort_array_by_key($report, $sort_by, 'name');

$total_estimated_value = number_format($total_estimated_value, null, null, ',');
$total_event_value = number_format($total_event_value, null, null, ',');

echo "<b>Total SDB Estimated Immediate Value = $total_estimated_value</b><br>";
echo "<b>Total SDB Event Value = $total_event_value</b><br>";

echo "<table class='small'>";
echo "<tr class='header'>";
//this is the header row...
foreach ($table_structure as $key => $label) {
    echo "<td><a href='" . make_link(array('bigsort' => $key)) . "'>$label</a></td>";
}
echo "</tr>";


foreach ($report as $id => $data) {
    echo "<tr>";
    foreach ($table_structure as $key => $label) {
	if ($key === 'name') {
	    echo "<td class='long'><a href='item.php?item_name=$data[$key]'>$data[$key]</a>" . add_copy_icon($data[$key]) . "</td>";
	} else {
	    echo "<td>" . $data[$key] . "</td>";
	}
    }
    echo "</tr>";
}
echo "</table>";

//add_copy_function();

function estimate_event_value($row) {
    if ($row['my_type'] === 'Quest Item') {
	$event_data = get_quest_item_event_data($row['name']);
	return $event_data['event_max'];
    }

    $rarity = $row['rarity'];
    if ($rarity >= 80 && $rarity <= 89) {
	return 460;
    }
    if ($rarity >= 90 && $rarity <= 97) {
	return 1400;
    }
    if ($rarity >= 98 && $rarity <= 100) {
	return 3000;
    }
    if ($rarity >= 102 && $rarity <= 179) {
	return 930;
    }
    return null;
}

function get_cost_cap($row) {
    if ($row['my_type'] === 'Quest Item') {
	$event_data = get_quest_item_event_data($row['name']);
	return floor($event_data['event_average']);
    }

    $rarity = $row['rarity'];
    if ($rarity >= 80 && $rarity <= 89) {
	return 10;
    }
    if ($rarity >= 90 && $rarity <= 97) {
	return 300;
    }
    if ($rarity >= 98 && $rarity <= 100) {
	return 800;
    }
    if ($rarity >= 102 && $rarity <= 179) {
	return 50;
    }
    return null;
}

function get_number_sold($row) {
    if ($row['my_type'] === 'Quest Item') {
	$event_data = get_quest_item_event_data($row['name']);
	return $event_data['qty_sold'];
    }
    return 0;
}

function make_start_stockpiling_link() {
    return make_link(array('stockpiling' => 'true'));
}

function make_stop_stockpiling_link() {
    return make_link(array('stockpiling' => 'false'));
}

function get_quest_item_event_data($item_name) {
    static $event_data = null;
    if ($event_data === null) {
	//get the data.
	$db = db_connect();
	$query = "SELECT item_name, MAX(sold_np) as event_max, "
		. "AVG(sold_np) as event_average, COUNT(*) as qty_sold "
		. "from sales "
		. "LEFT JOIN items on items.name = sales.item_name "
		. "where items.my_type = 'Quest Item' "
		. "AND (year(date)) =2019 AND (month(date) >= 3 AND month(date) <=5 ) "
		. "GROUP BY item_name";
	$result = $db->query($query);
	while ($row = $result->fetch_assoc()) {
	    $name = $row['item_name'];
	    unset($row['item_name']);
	    $event_data[$name] = $row;
	}
    }

    if (array_key_exists($item_name, $event_data)) {
	return $event_data[$item_name];
    }
    return false;
}

function get_where_clause() {
    $where_clause = " (1=1)"; //oh well.;
    if (array_key_exists('view', $_GET)) {
	switch ($_GET['view']) {
	    case 'all':
		break;
	    case 'charity_corner':
		$where_clause = " ((i.rarity >= 80 && i.rarity <= 100) OR "
			. "(i.rarity >= 102 && i.rarity <= 179))";
		break;
	    case 'quest_event':
		$where_clause = " (i.my_type = 'Quest Item')";
		break;
	}
    }
    return $where_clause;
}

function get_big_list_query() {
    $query = "SELECT inventory.neo_id, inventory.sdb_qty, "
	    . "i.name, i.rarity, i.my_type, i.my_subtype, next.sold_np as next_price, next.date as ssw_date "
	    . "FROM inventory "
	    . "LEFT JOIN items i on inventory.neo_id = i.neo_id "
	    . "LEFT JOIN ( SELECT n.neo_id, n.sold_np, n.date from ssw_next n "
	    . "	INNER JOIN (SELECT max(id) as last, neo_id from ssw_next GROUP BY neo_id) as latest "
	    . "	on n.id = latest.last) as next "
	    . " on inventory.neo_id = next.neo_id "
	    . "WHERE inventory.sdb_qty > 0 AND" . get_where_clause()
	    . " ORDER BY inventory.sdb_qty DESC";
    if (array_key_exists('view', $_GET)) {
	switch ($_GET['view']) {
	    case 'all':
		break;
	    case 'charity_corner':
		$query = "SELECT i.neo_id, i.name, i.rarity, i.my_type, i.my_subtype, "
			. "inventory.sdb_qty, next.sold_np as next_price, next.date as ssw_date, next.ssw_total "
			. "FROM items i "
			. "LEFT JOIN inventory on inventory.neo_id = i.neo_id "
			. "LEFT JOIN (SELECT n.neo_id, n.sold_np, n.ssw_total, n.my_id, n.date from ssw_next n "
			. "	INNER JOIN (SELECT max(id) as last_id, my_id from ssw_next GROUP BY neo_id) as latest "
			. "	on n.id = latest.last_id) as next "
			. "on i.neo_id = next.neo_id "
			. "LEFT JOIN (SELECT n.my_id, n.sold_np, n.ssw_total from ssw_next n "
			. "	INNER JOIN (SELECT max(id) as last_id, my_id from ssw_next GROUP BY my_id) as latest "
			. "	on n.id = latest.last_id) as zero "
			. "on i.id = zero.my_id "
			. "WHERE ((zero.ssw_total <> 0 OR zero.ssw_total IS NULL) AND "
			. "(next.ssw_total <> 0 OR next.ssw_total IS NULL)) AND "
			. "(next.sold_np < 5000 OR next.sold_np IS NULL) AND " . get_where_clause()
			. " ORDER BY inventory.sdb_qty DESC";
		break;
	    case 'quest_event':
		$query = "SELECT i.neo_id, i.name, i.rarity, i.my_type, i.my_subtype, "
			. "inventory.sdb_qty, next.sold_np as next_price, next.date as ssw_date "
			. "FROM items i "
			. "LEFT JOIN inventory on inventory.neo_id = i.neo_id "
			. "LEFT JOIN (SELECT n.neo_id, n.sold_np, n.ssw_total, n.my_id, n.date from ssw_next n "
			. "	INNER JOIN (SELECT max(id) as last_id, my_id from ssw_next GROUP BY neo_id) as latest "
			. "	on n.id = latest.last_id) as next "
			. "on i.neo_id = next.neo_id "
			. "WHERE " . get_where_clause()
			. " ORDER BY inventory.sdb_qty DESC";
		break;
	}
    }

    return $query;
}

function get_big_list_structure() {
    
}
