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
$title = "Gourmet Club - Eaten Log";
start_page($title);

$db = db_connect();

$sql = "select items.name, gourmet_club.eaten_status, gourmet_club.eaten_log_order, gourmet_club.paid "
	. "from gourmet_club left join items on items.id = gourmet_club.my_id "
	. "WHERE eaten_status = 'Yes' ORDER BY eaten_log_order ASC;";
$result = $db->query($sql);

echo ($result->num_rows . " GC items eaten<br>");

$cells = array();
while ($row = $result->fetch_assoc()) {
    $item_number = $row['eaten_log_order'];
    $item_name = $row['name'];
    $cells[$row['eaten_log_order']] = "<td class='small'>Item #$item_number<br>$item_name</td>";
}

$cellcount = max(array_keys($cells));

echo "<table>";
for ($i = 1; $i <= $cellcount; ++$i) {
    //build rows of 10
    if ($i % 10 === 1) {
	echo "<tr>";
    }

    if (array_key_exists($i, $cells)) {
	echo $cells[$i];
    } else {
	echo "<td>$i IS MISSING</td>";
    }

    if ($i % 10 === 0) {
	echo "</tr>";
    }
};

echo "</table>";
