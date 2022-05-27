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
$title = "National Dex";
start_page($title);

$db = db_connect();

$sql = "SELECT * from mons ORDER BY dex_national ASC, variant DESC";
$result = $db->query($sql);

echo ($result->num_rows . " monsters retrieved <br>");

$report = array();

$table_structure = array(
    'dex_national' => '#',
    'name' => 'Name',
    'variant' => 'Variant',
    'type1' => 'Type 1',
    'type2' => 'Type 2',
    'ability1' => "Ability 1",
    'ability2' => "Ability 2",
    'ability_hidden' => "Hidden Ability",
    'legendary' => "Legend",
    'mythical' => "Myth",
    'blank_1' => NULL,
    'b_hp' => "HP",
    'b_att' => "Attack",
    'b_def' => "Defense",
    'b_sp_att' => "Sp. Attack",
    'b_sp_def' => "Sp. Defense",
    'b_speed' => "Speed",
    'blank_2' => NULL,
    'female' => "% Female",
    'male' => "% Male",
    'egg_groups' => "Egg Groups",
    'blank_3' => NULL
);

while ($row = $result->fetch_assoc()) {
  $add_row = array();
  foreach ($table_structure as $key => $value) {
    if (!is_null($value)) {
      if (array_key_exists($key, $row)) {
        $add_row[$key] = $row[$key];
      } else {
        $add_row[$key] = NULL;
      }
    }
  }
  $report[] = $add_row;
}

echo "<table class='small'>";
echo "<tr>";
foreach ($table_structure as $key => $label) {
    echo "<td>$label</td>";
}
echo "</tr>\n";


foreach ($report as $date => $data) {
  echo "<tr>";
  foreach ($table_structure as $key => $label) {
    echo "<td>" . @$data[$key] . "</td>";
  }
  echo "</tr>\n";
}
echo "</table>\n";
