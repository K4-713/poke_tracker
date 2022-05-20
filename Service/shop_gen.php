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
$title = "Shop Generator";
start_page($title);


$db = db_connect();

$sql = "SELECT i.neo_id, i.name, i.my_type, i.my_subtype, 
inventory.shop_qty FROM items i 
LEFT JOIN inventory on inventory.neo_id = i.neo_id 
WHERE inventory.shop_qty > 0 
ORDER BY i.my_type ASC, i.name ASC";
$result = $db->query($sql);


$report = array();

while ($row = $result->fetch_assoc()) {
    $item = array(
	'name' => $row['name'],
	'neo_id' => $row['neo_id']
    );

    switch ($row['my_type']) {
	case 'Quest Item':
	    $report[$row['my_subtype']][] = $item;
	    break;
	case 'Hoarding':
	    if ($row['my_subtype'] === 'Doomed Book') {
		$report[$row['my_subtype']][] = $item;
	    }
	    break;
    }
};

$section_order = array(
    'Earth', 'Fire', 'Air', 'Light', 'Dark', 'Water', 'Grey', 'Soup', 'Doomed Book'
);

foreach ($section_order as $section) {
    $item_count = count($report[$section]);
    $column_size = ceil($item_count / 3);
    $item_counter = 0;
    $column_counter = 0;
    foreach ($report[$section] as $item) {
	if ($item_counter === 0) {
	    $column_counter += 1;
	    echo "\n\n" . $section . " column $column_counter <br>\n\n";
	    echo "<ul>\n";
	}
	echo "<li>" . make_shop_link($item) . "</li>";
	$item_counter += 1;
	if ($item_counter >= $column_size) {
	    echo "\n</ul>";
	    $item_counter = 0;
	}
    }
    if ($item_counter !== 0) {
	echo "\n</ul>";
    }
}

function make_shop_link($item) {
    global $account_name;
    $link_base = 'http://www.neopets.com/browseshop.phtml?';
    $link_qstring = array(
	'owner' => $account_name,
	'buy_obj_info_id' => $item['neo_id'],
	'buy_cost_neopoints' => 999999
    );

    $link = '<a href="' . $link_base . http_build_query($link_qstring) . '">' . get_dumb_name($item['name']) . '</a>';
    return $link;
}

function get_dumb_name($name) {
    switch ($name) {
	case 'Uncle Tharg':
	    return 'Unc Tharg';
	case 'Magic Foam Balls':
	    return 'Magic Foam Bals';
	case 'Shenkuu Firecrackers':
	    return 'Shenkuu Firecrakers';
	case 'Learn Social Skills':
	    return 'Learn Social Skils';
	case 'Large Cucumber Breezes Smoothie':
	    return 'Large Cucmber Breezes Smoothie';
    }
    return $name;
}
