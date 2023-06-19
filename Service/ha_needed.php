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
$title = "HA Needed";
start_page($title);

$db = db_connect();

$sql = "select dex_national, name, region, form, ability_hidden, male, female from mons "
        . "left join collection_mons on mons.id = collection_mons.mon_id "
        . "where mons.ability_hidden IS NOT NULL "
        . "and mons.box_hide is not true "
        . "and (not (mons.ability_hidden = collection_mons.ability) OR collection_mons.ability IS NULL)  "
        . "ORDER BY mons.dex_national ASC";
$result = $db->query($sql);

echo ($result->num_rows . " monsters retrieved <br>");

$report = array();

$table_structure = array(
    'dex_national' => '#',
    'name' => 'Name',
    'region' => 'Region',
    'form' => 'Form',
    'ability_hidden' => "Hidden Ability",
    'blank_1' => NULL,
    'female' => "% Female",
    'male' => "% Male",
    'blank_3' => NULL
);

while ($row = $result->fetch_assoc()) {
  $add_row = array();
  foreach ($table_structure as $key => $value) {
    switch ($key) {
      case "type":
        $add_row[$key] = get_poketype_output($row['type1']);
        if (!is_null($row['type2'])){
          $add_row[$key] .= get_poketype_output($row['type2']);
        }
        $add_row[$key] = "<div class='types'>" . $add_row[$key] . "</div>";
        break;
      case "abilities":
        $add_row[$key] = $row['ability1'];
        if (!is_null($row['ability2'])){
          $add_row[$key] .= "<br>" . $row['ability2'];
        }
        break;
      case "name":
        $add_row[$key] = $row[$key];
        if ($row['legendary'] === '1') {
          $add_row[$key] .= "<img class='inline' src='./Images/Icons/Legend.gif'>";
        }
        if ($row['mythical'] === '1') {
          $add_row[$key] .= "<img class='inline' src='./Images/Icons/Myth.gif'>";
        }
        break;
      default:
        if (!is_null($value)) {
          if (array_key_exists($key, $row)) {
            $add_row[$key] = $row[$key];
          } else {
            $add_row[$key] = NULL;
          }
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
