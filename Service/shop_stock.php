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
$title = "Shop Stock - All";
start_page($title);

//we're gonna do some ajax here, so...
poke_auth_key();

$db = db_connect();

$report = array();

$table_structure = array(
    'neo_id' => 'Neo ID',
    'name' => 'Name',
    'shop_qty' => 'Shop Qty',
    'my_type' => 'Type',
    'my_subtype' => 'Subtype',
    'sdb_qty' => 'SDB Qty',
    'spent' => 'Spent (for average)', //need to get this
    'sold_np' => 'Going',
    'price' => 'New Price',
    'last_price_date' => 'Last Priced',
    'last_sold_date' => 'Last Sold',
    'percent_rank' => 'Percent Rank',
    'unload' => NULL
);

$sql = "SELECT inv.*, i.name, i.my_type, i.my_subtype, 
next.sold_np, next.date as last_price_date, 
last_sold.date as last_sold_date 
FROM inventory inv 
LEFT JOIN items i on inv.neo_id = i.neo_id 
LEFT JOIN ( 
    select n.neo_id, n.sold_np, n.date from ssw_next n INNER JOIN ( 
	select max(id) as last, neo_id from ssw_next GROUP BY neo_id 
    ) as latest on n.id = latest.last 
 ) as next on inv.neo_id = next.neo_id 

LEFT JOIN ( 
    select s.item_name, s.sold_np, s.date from sales s INNER JOIN ( 
	select max(id) as last, item_name from sales GROUP BY item_name 
    ) as l_sold on s.id = l_sold.last 
 ) as last_sold on i.name = last_sold.item_name 

WHERE inv.shop_qty > 0 
ORDER BY inv.neo_id ASC";

$result = $db->query($sql);

while ($row = $result->fetch_assoc()) {
    if (!isset($report[$row['neo_id']])) {
	$report[$row['neo_id']] = $table_structure;
	foreach ($report[$row['neo_id']] as $key => $value) {
	    if (array_key_exists($key, $row)) {
		$report[$row['neo_id']][$key] = $row[$key];
	    } else {
		$report[$row['neo_id']][$key] = NULL;
	    }
	}
    }
};

echo ($result->num_rows . " items stocked<br>");

while ($row = $result->fetch_assoc()) {
    if (!array_key_exists($row['neo_id'], $report)) {
	$report[$row['neo_id']] = $table_structure;
	foreach ($report[$row['neo_id']] as $key => $value) {
	    if (array_key_exists($key, $row)) {
		$report[$row['neo_id']][$key] = $row[$key];
	    } else {
		$report[$row['neo_id']][$key] = NULL;
	    }
	}
    } else {
	foreach ($report[$row['neo_id']] as $key => $value) {
	    if (array_key_exists($key, $row) && is_null($report[$row['neo_id']][$key])) {
		$report[$row['neo_id']][$key] = $row[$key];
	    }
	}
    }
}

$categories = array();
$neo_ids = array();

foreach ($report as $neo_id => $values) {
    $categories[] = $values['my_type'];
    $categories = array_unique($categories);
    //$report[$neo_id] = array_merge($report[$neo_id], get_pricing_info($values));
    $qty = $values['shop_qty'] + $values['sdb_qty'];

    $neo_ids[$neo_id] = $qty;
}

$averages = get_batch_spent_averages($neo_ids);

//echo print_r($price_array, true) . "<br>";
foreach ($averages as $neo_id => $average) {
    $report[$neo_id]['spent'] = $average;
}

foreach ($report as $neo_id => $values) {
    $report[$neo_id] = array_merge($report[$neo_id], get_pricing_info($values));
}

$sort_by = 'neo_id'; //default sort
$params = poke_url_params();
if (array_key_exists('bigsort', $params)) {
    $sort_by = $params['bigsort'];
}

$report = sort_array_by_key($report, $sort_by, 'name');

foreach ($categories as $cat) {
    echo "<h2>$cat</h2>";
    echo "<table cellspacing=0 cellpadding=0 class='small'>";
    echo "<tr class='header'>";
    foreach ($table_structure as $key => $label) {
	if (!is_null($label)) {
	    echo "<td><a href='" . make_link(array('bigsort' => $key)) . "'>$label</a></td>";
	}
    }
    echo "</tr>\n";


    foreach ($report as $id => $data) {
	if ($data['my_type'] === $cat) {
	    echo "<tr>";
	    foreach ($table_structure as $key => $label) {
		if (!is_null($label)) {
		    switch ($key) {
			case 'name' :
			    echo "<td class='long'><a href='item.php?item_name=$data[$key]'>$data[$key]</a>" . add_copy_icon($data[$key]) . "</td>";
			    break;
			case 'price' :
			    echo "<td>";
			    if ($data[$key]) {
				echo $data[$key] . add_copy_icon($data[$key]);
			    }
			    echo "</td>";
			    break;
			case 'sdb_qty' :
			    echo "<td>" . $data[$key];
			    if ($data[$key] > 0) {
				echo add_ajax_sdb_empty_button($data['neo_id']);
			    }
			    echo "</td>";
			    break;
			default:
			    echo "<td>" . $data[$key] . "</td>";
			    break;
		    }
		}
	    }
	    echo "</tr>\n";
	}
    }
    echo "</table>\n";
}

