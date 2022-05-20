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
$title = "Report By Day";
start_page($title);

$db = db_connect();

$days = 60;

$sql = "SELECT s.*, i.my_type, i.my_subtype FROM sales s LEFT JOIN items i on s.item_name = i.name  WHERE date >= DATE_ADD(NOW(), INTERVAL -$days DAY) ORDER BY date ASC";
$result = $db->query($sql);

echo ($result->num_rows . " Sales rows retrieved <br>");

$sql = "SELECT p.*, i.my_type, i.my_subtype FROM purchases p LEFT JOIN items i on p.neo_id = i.neo_id  WHERE date >= DATE_ADD(NOW(), INTERVAL -$days DAY) ORDER BY date ASC";
$purchase_result = $db->query($sql);

echo ($purchase_result->num_rows . " Purchase rows retrieved <br>");

$report = array();

$table_structure = array(
    'year' => 'Year',
    'month' => 'Month',
    'week' => 'Week',
    'air_qty' => "Air",
    'dark_qty' => "Dark",
    'earth_qty' => "Earth",
    'fire_qty' => "Fire",
    'grey_qty' => "Grey",
    'light_qty' => "Light",
    'soup_qty' => "Soup",
    'water_qty' => "Water",
    'quest_qty' => "Total",
    'blank_4' => NULL,
    'blank_5' => NULL,
    'hoard_qty' => "Hoard Items",
    'hoard_sales' => "Hoard Sales",
    'hoard_purchases' => "Hoard Purchases",
    'blank_6' => NULL,
    'quest_sales' => "Quest Sales",
    'nerkmid_sales' => "Nerkmid Sales",
    'codestone_sales' => "Codestone Sales",
    'total_sales' => "Total Sales",
    'other_sales' => "Other Sales",
    'blank_7' => NULL,
    'quest_purchases' => 'Quest Purchases',
    'quest_net' => 'Quest Net',
    'hoard_net' => 'Hoard Net',
    'bd_prize_sales' => "BD Prize Sales"
);

while ($row = $result->fetch_assoc()) {
    if (!isset($report[$row['date']])) {
	foreach ($table_structure as $key => $value) {
	    if (!is_null($value)) {
		if (array_key_exists($key, $row)) {
		    $report[$row['date']][$key] = $row[$key];
		} else {
		    $report[$row['date']][$key] = NULL;
		}
	    }
	}
    }

    $report[$row['date']]['total_sales'] += $row['sold_np'];

    switch ($row['my_type']) {
	case 'Quest Item' :
	    $report[$row['date']]['quest_sales'] += $row['sold_np'];
	    $report[$row['date']]['quest_qty'] += 1;
	    $faerie_key = strtolower($row['my_subtype']) . "_qty";
	    $report[$row['date']][$faerie_key] += 1;
	    break;
	case 'Hoarding' :
	    $report[$row['date']]['hoard_sales'] += $row['sold_np'];
	    $report[$row['date']]['hoard_qty'] += 1;
	    break;
	case 'Nerkmid' :
	    $report[$row['date']]['nerkmid_sales'] += $row['sold_np'];
	    break;
	case 'Codestone' :
	    $report[$row['date']]['codestone_sales'] += $row['sold_np'];
	    break;
	case 'BD Prize' :
	    $report[$row['date']]['bd_prize_sales'] += $row['sold_np'];
	    break;
	default:
	    $report[$row['date']]['other_sales'] += $row['sold_np'];
    }
}

while ($row = $purchase_result->fetch_assoc()) {
    switch ($row['my_type']) {
	case 'Quest Item' :
	    $report[$row['date']]['quest_purchases'] += $row['spent_np'];
	    break;
	case 'Hoarding' :
	    $report[$row['date']]['hoard_purchases'] += $row['spent_np'];
	    break;
    }
}

foreach ($report as $date => $info) {
    $report[$date]['quest_net'] = $info['quest_sales'] - $info['quest_purchases'];
    $report[$date]['hoard_net'] = $info['hoard_sales'] - $info['hoard_purchases'];
    $date_array = explode('-', $date);
    $report[$date]['year'] = $date_array[0];
    $report[$date]['month'] = $date_array[1];
    $dt = new DateTime($date);
    $report[$date]['week'] = $dt->format("W");
}

echo "<table class='small'>";
echo "<tr>";
echo "<td>Date</td>";
foreach ($table_structure as $key => $label) {
    echo "<td>$label</td>";
}
echo "</tr>\n";


foreach ($report as $date => $data) {
    echo "<tr>";
    echo "<td>$date</td>";
    foreach ($table_structure as $key => $label) {
	echo "<td>" . @$data[$key] . "</td>";
    }
    echo "</tr>\n";
}
echo "</table>\n";

poke_json($report, 'all_data');
add_js('line_graph.js');

echo "<script>\n";
echo "var graph = new Line_Graph('graph', 'body', 800, 450);\n";
echo "graph.bind_data(all_data, ['total_sales', 'nerkmid_sales']);\n";
echo "</script>\n";
