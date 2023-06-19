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
add_document_ready("box_view_doc_ready");
poke_auth_key();

$db = db_connect();

$sql = "SELECT * from collections WHERE name = 'National Living Dex'";
$res_collection = $db->query($sql);
$collection_info = $res_collection->fetch_assoc();
echo $collection_info['name'] . ", " . $collection_info['location'] . "<br>";

$sql = "SELECT * from mons WHERE box_hide IS NOT true ORDER BY dex_national ASC, box_order ASC, region ASC, form ASC";
$result = $db->query($sql);
echo ($result->num_rows . " monsters retrieved <br>");

$box = $collection_info['start_box'];
$box_row = 0;
$box_column = 0;

echo "<input type=hidden id='collection_id' value='" . $collection_info['id'] . "'>";

while ($row = $result->fetch_assoc()) {
  $rows = 1;
  $extra_forms = [];
  if ($row['strong_dimorphism'] == true){
    $rows = 2;
    $extra_forms = ['Male', 'Female'];
  }
  
  while ($rows > 0) {
    if ($box_row === 0 && $box_column === 0){
      //start a new box
      echo "<table class='box'>\n";
      echo "<tr><th colspan='6'>Box $box</th></tr>\n";
    }
    if($box_column === 0){
      echo "<tr>";
    }
    
    echo "<td>" . add_monster_in_box($row, array_pop($extra_forms)) . "</td>";

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
    $rows -= 1;
  }
  
}

function add_monster_in_box($row, $extra_form = false){
  $add_mon = "<form id='" . $row['id'] . "' name='" . $row['id'] . "'>";
  $add_mon .= "<input type='hidden' name='mon_id' id='mon_id' value='" . $row['id'] . "'>";
  $add_mon .= "<input type='hidden' name='extra_form' id='extra_form' value='" . $extra_form . "'>";
  $collected = is_collected($row['id'], $extra_form);
  if (is_array($collected)){
    $add_mon .= "<input type='hidden' name='collection_mons_id' id='collection_mons_id' value='" . $collected['id'] . "'>";
  }
  $add_mon .= "<table class=mon>";
  
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
  if ($extra_form) {
    if ($rf_string != ""){
      $rf_string .= " ";
    }
    $rf_string .= ($extra_form);
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
  $checked = "";
  if (is_array($collected)){
    $checked = "checked";
  }
  $add_mon .= "<tr><td class='check'><input type='checkbox' $checked class='cbo' id='owned' onClick=\"poke_update('owned', this);\"></td><td class='name' colspan=2>$namestring</td>";
  $add_mon .= "<td class = 'dex'>#" . $row['dex_national'] . "</td></tr>\n";
  $add_mon .= "<tr><td class='region_form' colspan=2>" . $rf_string . "</td>";
  $add_mon .= "<td class='ball' colspan=2>" . get_ball_dd($collected['ball_id']) . "</td></tr>\n";
  
  $types_out = get_poketype_output($row['type1']);
  if (!is_null($row['type2'])){
    $types_out .= get_poketype_output($row['type2']);
  }
  $add_mon .= "<tr><td class='types' colspan=3>" . $types_out . "</td>";
  $checked = "";
  if (is_array($collected) && $collected['my_catch'] == true){
    $checked = "checked";
  }
  $add_mon .= "<td class='check'><input type='checkbox' $checked class='cbo' onClick=\"poke_update('my_catch', this);\" name='my_catch' id='my_catch'></td></tr>\n";
  
  $add_mon .= "<tr colspan=4><td class = 'ability'>" . get_ability_dd($row, $collected['ability']) . "</td></tr>\n";
  
  $add_mon .= "</td></tr>";
  
  $add_mon .= "</table></form>\n";
  return $add_mon;
}

function get_ball_dd($selected = null){
  static $balls = null;
  if (!is_array($balls)){
    $db = db_connect();
    $sql = "SELECT * from balls ORDER BY tier ASC";
    $result_balls = $db->query($sql);
    while ($row = $result_balls->fetch_assoc()) {
      $balls[] = $row;
    }
  }
  
  $ball_dd = "<select name='ball' id='ball' class='ball' onChange=\"poke_update('ball', this);\">\n";
  $ball_dd .= "<option value=''> - </option>\n";
  foreach ($balls as $ball){
    if ($selected && $selected === $ball['id']){
      $ball_dd .= "<option value=" . $ball['name'] ." selected>" . $ball['name'] . "</option>\n";
    } else {
      $ball_dd .= "<option value=" . $ball['name'] .">" . $ball['name'] . "</option>\n";
    }
  }
  $ball_dd .= "</select>\n";
  return $ball_dd;
}

function get_ability_dd($row, $selected_ability = null){
  $ability_dd = "<select name='ability' id='ability' class='ability' onChange=\"poke_update('ability', this);\">\n";
  $ability_dd .= "<option value=''> - </option>\n";
  $selected = "";
  if ($row['ability1'] === $selected_ability){
    $selected = " selected";
  }
  $ability_dd .= "<option value='" . $row['ability1'] . "$selected'>" . $row['ability1'] . "</option>\n";
  if (!is_null($row['ability2'])){
    $selected = "";
    if ($row['ability2'] === $selected_ability){
      $selected = " selected";
    }
    $ability_dd .= "<option value='" . $row['ability2'] . "$selected'>" . $row['ability2'] . "</option>\n";
  }
  if (!is_null($row['ability_hidden'])){
    $selected = "";
    if ($row['ability_hidden'] === $selected_ability){
      $selected = " selected";
    }
    $ability_dd .= "<option value='" . $row['ability_hidden'] . "$selected'>*" . $row['ability_hidden'] . "</option>\n";
  }
  $ability_dd .= "</select>\n"; 
  return $ability_dd;
}

function is_collected($id, $extra_form){
  static $collected = null;
  if (!is_array($collected)){
    $db = db_connect();
    $sql = "SELECT * from collection_mons WHERE collection_id = 1"; //TODO: a dynamic.
    $result_collected = $db->query($sql);
    while ($row = $result_collected->fetch_assoc()) {
      $collected[] = $row;
    }
  }
  foreach ($collected as $row){
    if (($row['mon_id'] === $id) && ($row['form_extras'] === $extra_form)){
      return $row;
    }
  }
  return false;
}