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
$title = "Restock Helper";
start_page($title);

//we're gonna do some ajax here, so...
poke_auth_key();

$db = db_connect();

$sql = "SELECT i.neo_id, i.name, i.my_type, i.my_subtype, 
next.sold_np, next.date as last_price_date, count(*) as qty, 
inventory.shop_qty, inventory.sdb_qty, inventory.unload FROM sales s
LEFT JOIN items i on s.item_name = i.name 
LEFT JOIN (
    select n.neo_id, n.sold_np, n.date from ssw_next n INNER JOIN (
	select max(id) as last, neo_id from ssw_next GROUP BY neo_id
    ) as latest on n.id = latest.last
 ) as next on i.neo_id = next.neo_id
LEFT JOIN inventory on inventory.neo_id = i.neo_id
WHERE s.restock = 1 
GROUP BY s.item_name 
ORDER BY i.my_type ASC, i.neo_id ASC";
//echo "$sql\n";
$result = $db->query($sql);

echo ($result->num_rows . " items flagged as restockers<br>");

$report = array();

$table_structure = array(
    'ajax' => '+',
    'name' => 'Name',
    'price' => 'New Price',
    'qty' => 'Number to Stock',
    'spent' => 'Spent (average)',
    'shop_qty' => 'Shop Qty',
    'sdb_qty' => 'SDB Qty',
    'purchase_qty' => 'Recent Purchase Qty',
    'purchase_date_time' => 'Last Purchase',
    'last_price_date' => 'Last Priced',
    'sold_np' => 'Going',
    'cached_price' => 'Cached Price',
    'percent_rank' => 'Percent Rank',
    'my_type' => NULL, //Auxiliary Data
    'my_subtype' => 'Subtype',
    'neo_id' => NULL,
    'unload' => NULL
);

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

$hours = 3;
$sql = "SELECT p.neo_id, count(*) as purchase_qty, i.name, i.my_type, i.my_subtype, 
next.sold_np, next.date as last_price_date, 
inventory.shop_qty, inventory.sdb_qty, inventory.unload, 
max(CONCAT(p.date, \" \", p.time)) as purchase_date_time FROM purchases p
LEFT JOIN items i on p.neo_id = i.neo_id
LEFT JOIN (
    select n.neo_id, n.sold_np, n.date from ssw_next n INNER JOIN (
	select max(id) as last, neo_id from ssw_next GROUP BY neo_id
    ) as latest on n.id = latest.last
 ) as next on p.neo_id = next.neo_id
LEFT JOIN inventory on inventory.neo_id = p.neo_id
WHERE inventory.shop_qty > 0 AND p.stockpiling = 0 
AND CONCAT(p.date, \" \", p.time) > DATE_ADD(NOW(), INTERVAL -$hours HOUR) 
GROUP BY p.neo_id 
ORDER BY p.neo_id ASC";

$result = $db->query($sql);

echo ($result->num_rows . " restock items purchased in the last $hours hours<br>");

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

    cache_sale_price($report[$neo_id]);
    $report[$neo_id]['cached_price'] = get_cached_sale_price($report[$neo_id]);

    if ($report[$neo_id]['sdb_qty'] == 0) {
	$report[$neo_id]['sdb_qty'] = null;
    }

    //Populate the ajax column with something that will mark this as complete
    if (!is_null($values['qty'])) {
	$report[$neo_id]['ajax'] = get_ajax_restock_button($neo_id, $values['qty']);
    }
}

foreach ($categories as $cat) {
    echo "<h2>$cat</h2>";
    echo "<table cellspacing=0 cellpadding=0 class='small'>";
    echo "<tr class='header'>";
    echo "<td>ID</td>";
    foreach ($table_structure as $key => $label) {
	if (!is_null($label)) {
	    echo "<td>$label</td>";
	}
    }
    echo "</tr>\n";


    foreach ($report as $id => $data) {
	if ($data['my_type'] === $cat) {
	    $sold_out_class = '';
	    if ($data['qty'] >= $data['shop_qty']) {
		$sold_out_class = 'sold_out';
	    }
	    echo "<tr class='$sold_out_class'>";
	    echo "<td>$id</td>";
	    foreach ($table_structure as $key => $label) {
		if (!is_null($label)) {
		    switch ($key) {
			case 'name' :
			    echo "<td class='long'><a href='item.php?item_name=$data[$key]'>$data[$key]</a>" . add_copy_icon($data[$key]) . "</td>";
			    break;
			case 'price' :
			    $cache_diff = ($data['price'] - $data['cached_price']) * $data['shop_qty'];
			    $reprice_class = '';
			    //hello, arbitrary mess.
			    if (abs($cache_diff) > 300) {
				$reprice_class = 'small_diff';
			    }
			    if (abs($cache_diff) > 1000) {
				$reprice_class = 'medium_diff';
			    }
			    if (abs($cache_diff) > 2000) {
				$reprice_class = 'large_diff';
			    }
			    echo "<td class='$reprice_class'>";
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
			case 'shop_qty' :
			    echo "<td><div id=" . $data['neo_id'] . "_shop_qty class='left  nudge_down'>" . $data[$key] . "</div>";
			    if ($data[$key] > 0) {
				echo add_ajax_shop_stock_buttons($data);
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

function get_ajax_restock_button($neo_id, $qty) {
    $ret = "<img src='check.png' class='check_img opacity_50' onClick=\"do_ajax('restocked', [{'neo_id': '$neo_id', 'qty': '$qty'}], mark_complete($(this)))\", onMouseOver=\"$(this).removeClass('opacity_50')\", onMouseOut=\"$(this).addClass('opacity_50')\")\">";
    return $ret;
}
