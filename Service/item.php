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

$item_name = $_GET['item_name'];
$title = "Item Information - $item_name";
start_page($title);

$db = db_connect();

$item_query = "select name, rarity, release_date from items "
    . "WHERE name = '$item_name'";

$ssw_query = "select ssw.date, ssw.time, ssw.sold_np "
    . "FROM ssw_next ssw "
    . "LEFT JOIN items i ON ssw.neo_id = i.neo_id "
    . "WHERE i.name = '$item_name' ORDER BY ssw.id ASC";

$tp_query = "select i.name, tp.date, tp.time, tp.tp_price, tp.tp_items_found "
    . "FROM trading_post tp "
    . "LEFT JOIN items i ON tp.item_name = i.name "
    . "WHERE i.name = '$item_name' ORDER BY tp.id ASC";

$jn_query = "select jn.item_name, jn.date, jn.jn_price "
    . "FROM jn_prices jn "
    . "LEFT JOIN items i ON jn.item_name = i.name "
    . "WHERE i.name = '$item_name' ORDER BY jn.id ASC";

$sold_query = "select max(sold_np) as sold_max, date from sales "
	. "where item_name = '$item_name' "
	. "GROUP BY date ORDER BY date ASC";

$purchased_query = '';

$result = $db->query($item_query);

//should only be one here, but what the hell.
$row = $result->fetch_assoc();
echo "Rarity: " . $row['rarity'] . "<br>";
echo "Release Date: " . $row['release_date'] . "<br>";

$table_data = array();

$result = $db->query($ssw_query);
while ($row = $result->fetch_assoc()) {
    $table_data[$row['date']]['ssw'] = $row['sold_np'];
}

$result = $db->query($tp_query);
while ($row = $result->fetch_assoc()) {
    $table_data[$row['date']]['tp'] = $row['tp_price'];
}

$result = $db->query($jn_query);
while ($row = $result->fetch_assoc()) {
    $table_data[$row['date']]['jn'] = $row['jn_price'];
}

$result = $db->query($sold_query);
while ($row = $result->fetch_assoc()) {
    $table_data[$row['date']]['sold'] = $row['sold_max'];
}

ksort($table_data);

$table_format = array(
    'date' => array(
	'heading' => "Date"
    ),
    'ssw' => array(
	'heading' => "SSW Price"
    ),
    'tp' => array(
	'heading' => "TP Price"
    ),
    'jn' => array(
	'heading' => "JN Price"
    ),
    'sold' => array(
	'heading' => "Max Sold Price"
    )
);


echo "<div style='float:right'><table class=small><tr>";
foreach ($table_format as $column => $attribs) {
    echo "<td>" . $attribs['heading'] . "</td>";
}
echo "</tr>";

foreach ($table_data as $date => $info) {
    echo "<tr>";
    foreach ($table_format as $column => $attribs) {
	$value = '';
	if ($column === 'date') { //special case
	    $value = $date;
	} else {
	    if (array_key_exists($column, $info)) {
		$value = $info[$column];
	    }
	}
	echo "<td>$value</td>";
    }
    echo "</tr>";
}
echo "</table></div>\n";
echo "<div id='graph_container' style='float:left; width:600px'></div>\n";

poke_json($table_data, 'all_data');
add_js('line_graph.js');

echo "<script>\n";
echo "var graph = new Line_Graph('graph', 'div#graph_container', 550, 300);\n";
echo "graph.bind_data(all_data, ['ssw', 'tp', 'jn', 'sold']);\n";
echo "</script>\n";
