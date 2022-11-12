<?php

/* 
 * Copyright (C) 2022 K4-713 <k4@hownottospellwinnebago.com>
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
$title = "Box View";
start_page($title);

$db = db_connect();

$sql = "SELECT * from mons ORDER BY dex_national ASC, region DESC";
$result = $db->query($sql);

echo ($result->num_rows . " monsters retrieved <br>");

$report = array();

$box = 1 + @$_GET['offset'];
$box_row = 0;
$box_column = 0;

while ($row = $result->fetch_assoc()) {
  if ($box_row === 0 && $box_column === 0){
    //start a new box
    echo "<table class='box'>\n";
    echo "<tr><th colspan='6'>Box $box</th></tr>\n";
  }
  if($box_column === 0){
    echo "<tr>";
  }
  
  echo "<td>" . add_monster_in_box($row) . "</td>";
  
  if($box_column === 5){
    echo "</tr>\n";
  }
  if ($box_row === 4 && $box_column === 5){
    //end the box
    echo "</table>\n";
    $box += 1;
    $box_row = 0;
    $box_column = 0;
  } else if($box_column === 5){
    $box_row += 1;
    $box_column = 0;
  } else {
    $box_column += 1;
  }
  
}

function add_monster_in_box($row){
  $add_mon = "<table class=mon>";
  
  $rf_string = "";
  if ($row['region']) {
    $rf_string .= $row['region'];
  }
  if ($row['form']) {
    if ($rf_string != ""){
      $rf_string .= ", ";
    }
    $rf_string .= $row['form'];
  }
  if ($rf_string === ""){
    $rf_string = "&nbsp";
  }
  $namestring = $row['name'];
  if ($row['legendary'] === '1') {
    $namestring .= "<img class='inline' src='./Images/Icons/Legend.gif'>";
  }
  if ($row['mythical'] === '1') {
    $namestring .= "<img class='inline' src='./Images/Icons/Myth.gif'>";
  }
  
  $add_mon .= "<tr><td class='name'>$namestring</td>";
  $add_mon .= "<td class = 'dex'>#" . $row['dex_national'] . "</td></tr>\n";
  $add_mon .= "<tr><td class='region_form'>" . $rf_string . "</td>";
  $add_mon .= "<td class='check'><input type='checkbox' class='cbo'></td></tr>\n";
  
  $types_out = get_poketype_output($row['type1']);
  if (!is_null($row['type2'])){
    $types_out .= get_poketype_output($row['type2']);
  }
  $add_mon .= "<tr><td class='types' colspan=2>" . $types_out . "</td></tr>\n";
  
  $ability_dd = "<select name='ability' id='ability' class='ability'>\n";
  $ability_dd .= "<option value=''> - </option>\n";
  $ability_dd .= "<option value='" . $row['ability1'] . "'>" . $row['ability1'] . "</option>\n";
  if (!is_null($row['ability2'])){
    $ability_dd .= "<option value='" . $row['ability2'] . "'>" . $row['ability2'] . "</option>\n";
  }
  if (!is_null($row['ability_hidden'])){
    $ability_dd .= "<option value='" . $row['ability_hidden'] . "'>*" . $row['ability_hidden'] . "</option>\n";
  }
  $ability_dd .= "</select>\n"; 
  
  $add_mon .= "<tr colspan=2><td class = 'ability'>" . $ability_dd . "</td></tr>\n";
  
  $add_mon .= "</td></tr>";
  
  $add_mon .= "</table>\n";
  return $add_mon;
}