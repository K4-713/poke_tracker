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
$title = "Gourmet Club - What to buy";
start_page($title);

$db = db_connect();

$gc_query = "select items.name, items.rarity, items.release_date, gourmet_club.eaten_status "
	. "from gourmet_club left join items on items.id = gourmet_club.my_id "
	. "WHERE eaten_status = 'No' OR (eaten_status = 'Refu' AND YEAR(items.release_date) < 2015)";

$ssw_query = "select i.name, ssw.neo_id, ssw.date, ssw.time, ssw.sold_np "
	. "FROM ssw_next ssw "
	. "LEFT JOIN items i ON ssw.neo_id = i.neo_id "
	. "LEFT JOIN gourmet_club gc ON gc.my_id = i.id "
	. "INNER JOIN (select neo_id, max(CONCAT(date, \" \", time)) as last_search from ssw_next GROUP BY neo_id) ssw2 "
	. "ON ssw.neo_id = ssw2.neo_id AND CONCAT(ssw.date, \" \", ssw.time) = ssw2.last_search "
	. "WHERE (gc.eaten_status = 'No' OR (gc.eaten_status = 'Refu' AND YEAR(i.release_date) < 2015)) "
	. "AND i.neo_id IS NOT NULL ORDER BY sold_np ASC";

//zero searches. 
//These should only ever come up this way, if we have never found a price (and neo id) on the ssw, ever.
$ssw_query2 = "select i.name, ssw.neo_id, ssw.date, ssw.time, ssw.sold_np "
	. "FROM ssw_next ssw "
	. "LEFT JOIN items i ON ssw.my_id = i.id "
	. "LEFT JOIN gourmet_club gc ON gc.my_id = i.id "
	. "INNER JOIN (select my_id, max(CONCAT(date, \" \", time)) as last_search from ssw_next GROUP BY my_id) ssw2 "
	. "ON ssw.my_id = ssw2.my_id AND CONCAT(ssw.date, \" \", ssw.time) = ssw2.last_search "
	. "WHERE (gc.eaten_status = 'No' OR (gc.eaten_status = 'Refu' AND YEAR(i.release_date) < 2015)) "
	. "ORDER BY sold_np ASC";

$tp_query = "select i.name, tp.date, tp.time, tp.tp_price, tp.tp_items_found "
	. "FROM trading_post tp "
	. "LEFT JOIN items i ON tp.item_name = i.name "
	. "LEFT JOIN gourmet_club gc ON gc.my_id = i.id "
	. "INNER JOIN (select item_name, max(CONCAT(date, \" \", time)) as last_search from trading_post GROUP BY item_name) tp2 "
	. "ON tp.item_name = tp2.item_name AND CONCAT(tp.date, \" \", tp.time) = tp2.last_search "
	. "WHERE (gc.eaten_status = 'No' OR (gc.eaten_status = 'Refu' AND YEAR(i.release_date) < 2015)) "
	. "ORDER BY tp_price ASC";

$jn_query = "select jn.item_name, jn.date, jn.jn_price "
	. "FROM jn_prices jn "
	. "LEFT JOIN items i ON jn.item_name = i.name "
	. "LEFT JOIN gourmet_club gc ON gc.my_id = i.id "
	. "INNER JOIN (select item_name, max(date) as last_search from jn_prices GROUP BY item_name) jn2 "
	. "ON jn.item_name = jn2.item_name AND jn.date = jn2.last_search "
	. "WHERE (gc.eaten_status = 'No' OR (gc.eaten_status = 'Refu' AND YEAR(i.release_date) < 2015)) "
	. "ORDER BY jn.jn_price ASC";

$result = $db->query($gc_query);

$all_items_by_name = array();
echo ($result->num_rows . " GC Items Not Eaten<br>");
while ($row = $result->fetch_assoc()) {
    $items_by_name[$row['name']] = array(
    	'eaten_status' => $row['eaten_status'],
	'rarity' => $row['rarity'],
	'release_date' => $row['release_date'],
    	'will_eat' => check_gourmet_pet_will_eat($row['name']),
	'best_price' => NULL,
	'last_search_date' => NULL
    );
}

//fold in the ssw results
$result = $db->query($ssw_query);
while ($row = $result->fetch_assoc()) {
    $items_by_name[$row['name']]['best_price'] = $row['sold_np'];
    $items_by_name[$row['name']]['best_price_src'] = 'ssw';
    $items_by_name[$row['name']]['last_search_date'] = $row['date'];
    $items_by_name[$row['name']]['ssw_price'] = $row['sold_np'];
    $items_by_name[$row['name']]['last_ssw_search_date'] = $row['date'];
}

$result = $db->query($ssw_query2);
while ($row = $result->fetch_assoc()) {
    //zero searches
    if (!isset($items_by_name[$row['name']]['last_search_date'])) {
	$items_by_name[$row['name']]['last_search_date'] = $row['date'];
	$items_by_name[$row['name']]['last_ssw_search_date'] = $row['date'];
    }
}

//and the trading post...
$result = $db->query($tp_query);
while ($row = $result->fetch_assoc()) {
    $items_by_name[$row['name']]['tp_price'] = $row['tp_price'];
    $items_by_name[$row['name']]['last_tp_search_date'] = $row['date'];

    if (is_null($items_by_name[$row['name']]['best_price'])) {
	$items_by_name[$row['name']]['best_price'] = $row['tp_price'];
	$items_by_name[$row['name']]['best_price_src'] = 'tp';
	if (isset($items_by_name[$row['name']]['last_search_date']) && $row['date'] > $items_by_name[$row['name']]['last_search_date']) {
	    $items_by_name[$row['name']]['last_search_date'] = $row['date'];
	}
    } else {
	if ($items_by_name[$row['name']]['last_search_date'] === $row['date']) {
	    //same date, go with the lesser
	    if ($row['tp_price'] < $items_by_name[$row['name']]['best_price']) {
		$items_by_name[$row['name']]['best_price_src'] = 'tp';
		$items_by_name[$row['name']]['best_price'] = $row['tp_price'];
	    }
	} else {
	    //overwrite if the new data is... newer. hrr.
	    if ($row['date'] > $items_by_name[$row['name']]['last_search_date']) {
		$items_by_name[$row['name']]['best_price_src'] = 'tp';
		$items_by_name[$row['name']]['best_price'] = $row['tp_price'];
	    }
	}
    }
}

//and finally, the last known jn price...
//$jn_query = "select jn.item_name, jn.date, jn.jn_price "
$result = $db->query($jn_query);
while ($row = $result->fetch_assoc()) {
    $items_by_name[$row['item_name']]['jn_price'] = $row['jn_price'];
    $items_by_name[$row['item_name']]['last_jn_price_date'] = $row['date'];
    if (is_null($items_by_name[$row['item_name']]['best_price'])) {
	$items_by_name[$row['item_name']]['best_price'] = $row['jn_price'];
	$items_by_name[$row['item_name']]['best_price_src'] = 'jn';
	if (is_null($items_by_name[$row['item_name']]['last_search_date'])) {
	    $items_by_name[$row['item_name']]['last_search_date'] = $row['date'];
	}
    }
}

$price_sort = array();
$no_price = array();
foreach ($items_by_name as $name => $values) {
    if (!is_null($values['best_price'])) {
	$price_sort[$name] = $values['best_price'];
    } else {
	$no_price[$name] = $values['last_search_date'];
    }
}

asort($price_sort);

//TODO: Actually label table columns so you can see what's going on? har
// also use colors and styles and things

$table_formatting = array(
    'name' => array(
	'heading' => 'Item Name',
	'class' => 'long'
    ),
    'rarity' => array(
	'heading' => 'Rarity'
    ),
    'best_price' => array(
	'heading' => 'Best Price',
	'class' => 'price_source'
    ),
    'release_date' => array(
	'heading' => 'Release Date',
	'class' => 'release_age'
    ),
    'last_search_date' => array(
	'heading' => 'Last Search Date',
	'class' => 'last_search'
    ),
    'ssw_price' => array(
	'heading' => 'SSW Price'
    ),
    'last_ssw_search_date' => array(
	'heading' => 'SSW Date'
    ),
    'tp_price' => array(
	'heading' => 'TP Price'
    ),
    'last_tp_search_date' => array(
	'heading' => 'TP Date'
    ),
    'jn_price' => array(
	'heading' => 'JN Price'
    ),
    'last_jn_price_date' => array(
	'heading' => 'JN Date'
    ),
);

echo count($price_sort) . " priced items found";
echo "<table class=small><tr>";
foreach ($table_formatting as $column => $attribs) {
    echo "<td>" . $attribs['heading'] . "</td>";
}
echo "</tr>";
foreach ($price_sort as $name => $price) {
    echo "<tr>";
    foreach ($table_formatting as $column => $attribs) {
	$value = '';
	$class = '';
	if ($column === 'name') { //special case
	    $value = "<a href='item.php?item_name=$name'>$name</a>" . add_copy_icon($name);
	} else {
	    if (array_key_exists($column, $items_by_name[$name])) {
		$value = $items_by_name[$name][$column];
	    }
	}
	if (array_key_exists('class', $attribs)) {
	    switch ($attribs['class']) {
		case 'price_source':
		    $class = ' class="best_' . $items_by_name[$name]['best_price_src'] . '"';
		    break;
		case 'release_age':
		    $cutoff = strtotime("-9 months");
		    $release_date = strtotime($value);
		    if ($release_date > $cutoff) {
			$class = ' class="too_new"';
		    }
		    break;
		case 'last_search':
		    //searched_today, not_searched_today
		    $today = strtotime("-24 hours");
		    $last_search = strtotime($value);
		    if ($last_search > $today) {
			$class = ' class="searched_today"';
		    } else {
			$class = ' class="not_searched_today"';
		    }
		    break;
		default:
		    $class = ' class="' . $attribs['class'] . '"';
		    break;
	    }
	}
	echo "<td$class>$value</td>";
    }
    echo "</tr>\n";
}
echo "</table><br>\n";


asort($no_price);

echo count($no_price) . " unpriced items found";
echo "<table class=small>";
foreach ($no_price as $name => $date) {
    echo "<tr><td style='width:250px'>" . $name . add_copy_icon($name) . "</td>";
    foreach ($items_by_name[$name] as $key => $value) {
	echo "<td>$value</td>";
    }
    echo "</tr>\n";
}
echo "</table>";

add_copy_function();
