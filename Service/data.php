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

//header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']); //lol, please don't
//Also... OMG this doesn't even work while receiving JSON. wtf. $_POST also useless as you
//might expect.

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

//TODO: Actual error handling and crap.
// Takes raw data from the request
$json = file_get_contents('php://input');

// Converts it into a PHP object
$data = json_decode($json, true);

if (!is_array($data)) {
    error_log("No data in the request? Investigate.");
    error_log($json);
    return_result('failure', "Invalid request");
    return false;
}

if (!array_key_exists('key', $data)) {
    error_log("No auth key supplied");
    return_result('failure', "Invalid request");
    return false;
}

if (!check_auth_key($data['key'])) {
    error_log("Wrong auth key supplied");
    return_result('failure', "Invalid request");
    return false;
}

if (!array_key_exists('action', $data)) {
    error_log("No action specified. Investigate.");
    error_log(print_r($data, true));
    return_result('failure', "Invalid request");
    return false;
}

$result = handle_request($data);

/**
 * Render the results of whatever we were trying to do here.
 * @param string $status 
 * @param type $message
 * @param type $data
 */
function return_result($status, $message, $data = null) {
    $return = array();
    $return['status'] = $status;
    $return['message'] = $message;

    if (!is_null($data)) {
	$return['data'] = $data;
    }

    header('Content-Type: application/json');

    echo json_encode($return);

    //and, just in case...
    die();
}

/**
 * TODO: Need a function that takes the posted data, and...
 * 	checks:what we're trying to do
 * 	Schema / type checking against the incoming array
 * 	Routes to the right saving function
 */

function handle_request($data) {

  $action = $data['action'];

  //always validate data
  if (!validate_data($action, $data['rows'])) {
    return_result('failure', "Invalid data");
    return false;
  }

  switch ($action) {
    case 'natdex_index':
      $rowcount = db_multi_query($action, 'insert_update', $data['rows']);
      if ($rowcount > 0) {
        return_result('success', "$action: $rowcount rows updated");
      } else {
        return_result('failure', "Problem writing to the database");
      }
      break;
    case 'g7_dex':
    case 'g8_dex':
    case 'g9_dex':
      $insert_count = 0;
      $update_count = 0;
      foreach ($data['rows'] as $key => $value){
        if (is_array($value) && array_key_exists('name', $value)){
          $update = check_mon_exists($value['name'], @$value['region'], @$value['form']); //bite me: this is fine
          $temp_data[0] = $value;
          if($update){
            $update_count += db_query($action, 'update', $temp_data );
          } else {
            $insert_count += db_query($action, 'insert', $temp_data );
          }
        } else {
          return_result('failure', "No mon name received: " . print_r($value));
        }
      }
      return_result('success', "$update_count rows updated, $insert_count rows inserted.");
      break;
    case 'tag_legends':
      $rowcount = 0;
      $legends = array();
      $myths = array();
      foreach ($data['rows'] as $key => $value){
        if (array_key_exists('mythical', $value) && !is_null($value['mythical'])){
          $myths[] = $value;
        } else {
          $legends[] = $value;
        }
      }
      $rowcount = db_query($action, 'tag_legends', $legends );
      $rowcount += db_query($action, 'tag_myths', $myths );
      if ($rowcount > 0) {
        return_result('success', "$action: $rowcount rows updated");
      } else {
        return_result('failure', "Problem writing to the database");
      }
      break;
    case 'toggle_collection_owned':
      if (sizeof($data['rows']) !== 1){
        return_result('failure', "Weird: Expected one row, got " . sizeof($data['rows']));
      }
      $exists = check_collection_mon_exists($data['rows'][0]['mon_id'], $data['rows'][0]['collection_id'], $data['rows'][0]['form_extras']);
      $did = "";
      $rowcount = "";
      if ($exists) {
        $rowcount = db_query($action, 'delete', $data['rows'] );
        $did = "Deleted";
      } else {
        $rowcount = db_query($action, 'insert', $data['rows'] );
        $did = "Inserted";
      }
      if ($rowcount > 0) {
        $cmon_id = null;
        if ($did === "Inserted"){
          $cmon_id = get_collection_mon_id($data['rows'][0]['mon_id'], $data['rows'][0]['collection_id'], $data['rows'][0]['form_extras']);
        }
        return_result('success', "$did: $rowcount row(s)", ["id" => $cmon_id]);
      } else {
        return_result('failure', "$did 0 rows");
      }
      break;
    case 'toggle_collection_mine':
      if (sizeof($data['rows']) !== 1){
        return_result('failure', "Weird: Expected one row, got " . sizeof($data['rows']));
      }
      $mine = check_collection_mon_mine($data['rows'][0]['id']);
      $did = "";
      if ($mine) {
        $did = "Unowned";
        $data['rows'][0]['my_catch'] = false;
      } else {
        $did = "Owned";
        $data['rows'][0]['my_catch'] = true;
      }
      $rowcount = db_query($action, 'update', $data['rows'] );
      if ($rowcount > 0) {
        return_result('success', "$did: $rowcount row(s)");
      } else {
        return_result('failure', "$did 0 rows");
      }
      break;
    case 'set_collection_ball':
      if (sizeof($data['rows']) !== 1){
        return_result('failure', "Weird: Expected one row, got " . sizeof($data['rows']));
      }
      $did = "Updated";
      if (array_key_exists('ball', $data['rows'][0]) && $data['rows'][0]['ball'] !== ""){
        $qdata = $data['rows'];
        $qdata[0]['ball_id'] = get_ball_id($qdata[0]['ball']);
        $rowcount = db_query($action, 'update', $qdata );
      } else {
        //unset the ball
        $did = "Unset Ball,";
        $rowcount = db_query($action, 'delete', $data['rows'] );
      }

      if ($rowcount > 0) {
        return_result('success', "$did: $rowcount row(s)");
      } else {
        return_result('failure', "$did 0 rows");
      }
      break;
    case 'set_collection_ability':
      if (sizeof($data['rows']) !== 1){
        return_result('failure', "Weird: Expected one row, got " . sizeof($data['rows']));
      }
      $did = "Updated";
      if (array_key_exists('ability', $data['rows'][0]) && $data['rows'][0]['ability'] !== ""){
        $rowcount = db_query($action, 'update', $data['rows'] );
      } else {
        //unset the ability
        $did = "Unset Ability,";
        $rowcount = db_query($action, 'delete', $data['rows'] );
      }

      if ($rowcount > 0) {
        return_result('success', "$did: $rowcount row(s)");
      } else {
        return_result('failure', "$did 0 rows");
      }
      break;
    default:
      return_result('failure', "Invalid action '$action'");
  }
}

//temp until I can get all of them moved over.
function validate_data($action, $data) {  
  $expected_data = get_expected_data($action);
  if ($expected_data) {
    return validate($data, $expected_data);
  }
  return false;
}

function get_expected_data($action) {
    $dmi = get_data_model_info($action);
    if (is_array($dmi) && array_key_exists('data', $dmi)) {
      return $dmi['data'];
    }
    return false;
}

function get_table_composite_keys( $table ) {
  $composite_keys = array(
      'mons' => array(
        'name' => 'varchar_32',
        'region' => 'varchar_16|null',
        'form' => 'varchar_32|null',
      )
  );
  if (array_key_exists($table, $composite_keys)){
    return $composite_keys[$table];
  } else {
    return false;
  }
}

//Tryna refactor a few things at a time with this
function get_data_model_info($action) {
  $model_info = array();

  /** natdex_index **/
  $model_info['natdex_index']['data'] = array(
      'name' => 'varchar_32',
      'type1' => 'varchar_16',
      'type2' => 'varchar_16|null',
      'ability1' => 'varchar_32',
      'ability2' => 'varchar_32|null',
      'ability_hidden' => 'varchar_32|null',
      'b_att' => 'int',
      'b_def' => 'int',
      'b_hp' => 'int',
      'b_sp_att' => 'int',
      'b_sp_def' => 'int',
      'b_speed' => 'int',
      'dex_national' => 'int'
  );
  $model_info['natdex_index']['insert_update'] = query_build($model_info['natdex_index']['data'], 'insert_update', 'mons');

  
  /** g9_dex **/
  $model_info['g9_dex']['data'] = array(
      'name' => 'varchar_32',
      'dex_national' => 'int',
      'region' => 'varchar_16|null',
      'form' => 'varchar_32|null',
      'type1' => 'varchar_16',
      'type2' => 'varchar_16|null',
      'ability1' => 'varchar_32',
      'ability2' => 'varchar_32|null',
      'ability_hidden' => 'varchar_32|null',
      'b_att' => 'int',
      'b_def' => 'int',
      'b_hp' => 'int',
      'b_sp_att' => 'int',
      'b_sp_def' => 'int',
      'b_speed' => 'int',
      'female' => 'number|null',
      'male' => 'number|null',
      'egg_groups' => 'varchar_32|null',
      'dex_paldea' => 'int|null',
      'catchable_sv' => 'bool|null',
  );
  $model_info['g9_dex']['table'] = "mons";
  $model_info['g9_dex']['insert'] = query_build($model_info['g9_dex']['data'], 'insert', $model_info['g9_dex']['table']);
  $model_info['g9_dex']['update'] = query_build($model_info['g9_dex']['data'], 'update', $model_info['g9_dex']['table']);
  
  /** g8_dex **/
  $model_info['g8_dex']['data'] = array(
      'name' => 'varchar_32',
      'dex_national' => 'int',
      'region' => 'varchar_16|null',
      'form' => 'varchar_32|null',
      'type1' => 'varchar_16',
      'type2' => 'varchar_16|null',
      'ability1' => 'varchar_32',
      'ability2' => 'varchar_32|null',
      'ability_hidden' => 'varchar_32|null',
      'b_att' => 'int',
      'b_def' => 'int',
      'b_hp' => 'int',
      'b_sp_att' => 'int',
      'b_sp_def' => 'int',
      'b_speed' => 'int',
      'female' => 'number|null',
      'male' => 'number|null',
      'egg_groups' => 'varchar_32|null',
      'dex_galar' => 'int|null',
      'dex_galar_isle' => 'int|null',
      'dex_galar_crown' => 'int|null',
      'dex_sinnoh_bdsp' => 'int|null',
      'dex_hisui' => 'int|null',
      'catchable_swsh' => 'bool|null',
      'catchable_bdsp' => 'bool|null',
      'catchable_pla' => 'bool|null',
  );
  $model_info['g8_dex']['table'] = "mons";
  $model_info['g8_dex']['insert'] = query_build($model_info['g8_dex']['data'], 'insert', $model_info['g8_dex']['table']);
  $model_info['g8_dex']['update'] = query_build($model_info['g8_dex']['data'], 'update', $model_info['g8_dex']['table']);
  
  /** g7_dex **/
  $model_info['g7_dex']['data'] = array(
      'name' => 'varchar_32',
      'dex_national' => 'int',
      'region' => 'varchar_16|null',
      'form' => 'varchar_32|null',
      'type1' => 'varchar_16',
      'type2' => 'varchar_16|null',
      'ability1' => 'varchar_32',
      'ability2' => 'varchar_32|null',
      'ability_hidden' => 'varchar_32|null',
      'b_att' => 'int',
      'b_def' => 'int',
      'b_hp' => 'int',
      'b_sp_att' => 'int',
      'b_sp_def' => 'int',
      'b_speed' => 'int',
      'female' => 'number|null',
      'male' => 'number|null',
      'egg_groups' => 'varchar_32|null',
      'dex_alola_ultra' => 'int|null',
      'catchable_usum' => 'bool|null',
  );
  $model_info['g7_dex']['table'] = "mons";
  $model_info['g7_dex']['insert'] = query_build($model_info['g7_dex']['data'], 'insert', $model_info['g7_dex']['table']);
  $model_info['g7_dex']['update'] = query_build($model_info['g7_dex']['data'], 'update', $model_info['g7_dex']['table']);
  
  /** tag_legends **/
  $model_info['tag_legends']['data'] = array(
      'legendary' => 'bool|null',
      'mythical' => 'bool|null',
      'name' => 'varchar_32'
  );
  $model_info['tag_legends']['tag_legends'] = array(
      'query' => "UPDATE mons SET legendary = ? WHERE name = ?",
      'binding' => "is",
	    'data' => array(
        'legendary' => 'bool',
        'name' => 'varchar_32'
      )
  );
  $model_info['tag_legends']['tag_myths'] = array(
      'query' => "UPDATE mons SET mythical = ? WHERE name = ?",
      'binding' => "is",
	    'data' => array(
        'mythical' => 'bool',
        'name' => 'varchar_32'
      )
  );
  
  /** Collection Manipulation **/
  $model_info['toggle_collection_owned']['data'] = array(
      'mon_id' => 'int',
      'collection_id' => 'int',
      'form_extras' => 'varchar_16|null'
  );
  $model_info['toggle_collection_owned']['table'] = "collection_mons";
  $model_info['toggle_collection_owned']['insert'] = query_build($model_info['toggle_collection_owned']['data'], 'insert', $model_info['toggle_collection_owned']['table']);
  $model_info['toggle_collection_owned']['delete'] = query_build($model_info['toggle_collection_owned']['data'], 'delete', $model_info['toggle_collection_owned']['table']);
  
  $model_info['toggle_collection_mine']['data'] = array(
      'id' => 'int'
  );
  $model_info['toggle_collection_mine']['update'] = array(
      'query' => "UPDATE collection_mons SET my_catch = ? WHERE id = ?",
      'binding' => "ii",
	    'data' => array(
        'my_catch' => 'bool',
        'id' => 'int'
      )
  );
  
  $model_info['set_collection_ability']['data'] = array(
      'id' => 'int',
      'ability' => 'varchar_32'
  );
  $model_info['set_collection_ability']['update'] = array(
      'query' => "UPDATE collection_mons SET ability = ? WHERE id = ?",
      'binding' => "si",
	    'data' => array(
        'ability' => 'varchar_32',
        'id' => 'int'
      )
  );
  $model_info['set_collection_ability']['delete'] = array(
      'query' => "UPDATE collection_mons SET ability = NULL WHERE id = ?",
      'binding' => "i",
	    'data' => array(
        'id' => 'int'
      )
  );
  
  $model_info['set_collection_ball']['data'] = array(
      'id' => 'int',
      'ball' => 'varchar_32'
  );
  $model_info['set_collection_ball']['update'] = array(
      'query' => "UPDATE collection_mons SET ball_id = ? WHERE id = ?",
      'binding' => "ii",
	    'data' => array(
        'ball_id' => 'int',
        'id' => 'int'
      )
  );
  $model_info['set_collection_ball']['delete'] = array(
      'query' => "UPDATE collection_mons SET ball_id = NULL WHERE id = ?",
      'binding' => "i",
	    'data' => array(
        'id' => 'int'
      )
  );
  
  
  //and finally return
  if (array_key_exists($action, $model_info)) {
    return $model_info[$action];
  }
  error_log(__FUNCTION__ . ": No $action key defined");
  return false;
}

//Okay, this is now for everything.
function db_query($action, $query_type, $data) {
  $model_info = get_data_model_info($action);
  if (!$model_info) {
    return false;
  }

  $db = db_connect();
  $query = $model_info[$query_type]['query'];
  $stmt = $db->prepare($query);
  if (!$stmt) {
    error_log(__FUNCTION__ . ": $action, $query_type query unpreparable.");
    error_log($query);
//    return_result("failure", "$action, $query_type query unpreparable. :" . $query);
    return false;
  }

  //moving some stuff around, here...
  //still haven't really decided how I want to store queries and whatever.
  $data_structure = false;
  if (array_key_exists('data', $model_info[$query_type])) {
    $data_structure = $model_info[$query_type]['data'];
  } else {
    $data_structure = $model_info['data'];
  }
  
  //if the query type is an update, we need to reorder the structure with the composite keys at the end.
  //I think.
  if ($query_type === "update") {
    $table = $model_info['table'];
    $composite_keys = get_table_composite_keys( $table );
    if ($composite_keys){
      //remove the composite keys from the set data
      $set_data = $data_structure;
      foreach ($composite_keys as $key => $value) {
        if (array_key_exists($key, $set_data)){
          unset($set_data[$key]);
        }
      }
      $data_structure = array_merge($set_data, $composite_keys);
    }
  }
  
  //Try this, kids at home!
  extract($data_structure);
  $params = array(
      $model_info[$query_type]['binding']
  );

  //oh god
  foreach ($data_structure as $key => $whatever) {
    //pass the variable variable by reference. Obviously.
    $params[] = &$$key;
  }

  //This is probably confusing, and could totally be clenaer
  //For insert/update queries, we have to send the variable references twice
  if ($query_type === 'insert_update') {
    foreach ($data_structure as $key => $whatever) {
      $params[] = &$$key;
    }
  }

  //allegedly, you can call methods of instantiated objects with call_user_func_array like this...
  $call_me = array(
      $stmt,
      'bind_param'
  );

  error_log("Binding? '" . $params[0] . "'");

  //for the record, I definitely hate myself by now.
  call_user_func_array($call_me, $params);

  $db->query("START TRANSACTION");
  $rowcount = 0;
  foreach ($data as $row => $line) {
    //oh look, more.
    foreach ($data_structure as $key => $whatever) {
      $$key = $line[$key];
    }
    if ($stmt->execute()) {
      ++$rowcount;
    }
  }
  $stmt->close();
  $res = $db->query("COMMIT");

  return $rowcount;
}

function db_multi_query($action, $query_type, $data, $chunk_size = 20) {
    $chunks = array_chunk($data, $chunk_size);
    $rowcount = 0;
    foreach ($chunks as $ehh => $chunk) {
        $returns = db_query($action, $query_type, $chunk);
        if ($returns === false) {
            return false;
        }
        $rowcount += $returns;
    }
    return $rowcount;
}

function query_build($structure, $query_type, $table) {
  $query = array();
  switch ($query_type) {
    case 'insert_update' :
      $query['query'] = "INSERT INTO $table ";
      $query['query'] .= query_build_insert_fields($structure) . " ";
      $query['query'] .= "VALUES " . query_qmarks($structure) . " ";
      $query['query'] .= "ON DUPLICATE KEY UPDATE ";
      $query['query'] .= query_build_odku_values($structure);
      $query['binding'] = query_get_binding_string($structure) . query_get_binding_string($structure);
      break;
    case 'insert' :
      $query['query'] = "INSERT INTO $table ";
      $query['query'] .= query_build_insert_fields($structure) . " ";
      $query['query'] .= "VALUES " . query_qmarks($structure) . " ";
      $query['binding'] = query_get_binding_string($structure);
      break;
    case 'update' :
      $composite_keys = get_table_composite_keys( $table );
      $query['query'] = "UPDATE $table "; //er...?
      //remove the composite keys from the set data
      $set_data = $structure;
      foreach ($composite_keys as $key => $value) {
        if (array_key_exists($key, $set_data)){
          unset($set_data[$key]);
        }
      }
      $query['query'] .= "SET " . query_build_set_fields($set_data) . ' ';
      $query['query'] .= "WHERE " . query_build_where_fields($composite_keys);
      $query['binding'] = query_get_binding_string(array_merge($set_data, $composite_keys));
      break;
    case 'delete' :
      $query['query'] = "DELETE FROM $table ";
      $query['query'] .= "WHERE " . query_build_where_fields($structure);
      $query['binding'] = query_get_binding_string($structure);
      break;
    default :
      return_result('failure', "Invalid query type '$query_type'");
  }
  return $query;
}

function query_build_insert_fields($structure) {
  $fields = implode(', ', array_keys($structure));
  return "($fields)";
}

function query_build_set_fields($structure) {
  //for use in update statements.
  $comparator = " = ?";
  foreach($structure as $key => $value){
    $structure[$key] = $key . $comparator;
  }
  $fields = implode(', ', $structure);
  return $fields;
}

function query_build_where_fields($structure) {
  //for use in update statements.
  $comparator = " = ?";
  //SPACESHIP - Is for comparing nulls correctly.
  $spaceship = " <=> ?";
  foreach($structure as $key => $value){
    //if $value is nullable, use the spaceship! whee 
    if (strpos($value, '|null') > 0) {
      $structure[$key] = $key . $spaceship;
    } else {
      $structure[$key] = $key . $comparator;
    }
  }
  $fields = implode(' AND ', $structure);
  return $fields;
}

function query_qmarks($structure) {
  //this is so dum
  $qmarks = '';
  for ($i = 0; $i < sizeof($structure); ++$i) {
    if ($qmarks === '') {
      $qmarks = '?';
    } else {
      $qmarks .= ',?';
        }
  }
  return "($qmarks)";
}

function query_build_odku_values($structure) {
  //more dum
  $odku = '';
    foreach ($structure as $field => $type) {
        if ($odku === '') {
            $odku = "$field=?";
        } else {
            $odku .= ", $field=?";
        }
    }
    return $odku;
}

//
function query_get_binding_string($structure) {
  $binding = '';
  foreach ($structure as $field => $type) {
    $mod_type = explode('_', $type);
    $mod_type = explode('|', $mod_type[0]);
    $mod_type = $mod_type[0];
    switch ($mod_type) {
      case 'int':
      case 'bool':
        $binding .= 'i';
        break;
      case 'varchar':
        $binding .= 's';
        break;
      case 'number':
        $binding .= 'd';
        break;
      default:
        return_result('failure', "Unhandled var type '$type' for field '$field' ($mod_type)");
        break;
    }
  }
  return $binding;
}

function db_raw_query($query) {
    $db = db_connect();
    $result = $db->query($query);
    if ($result === false) { //this should actually do what I want...
	error_log(__FUNCTION__ . " exploded.");
	error_log($db->error);
	return false;
    }

    //for those times you're just inserting things
    if ($result === true) {
	return true;
    }

    $ret = [];
    //there are probably nicer ways to do this, but.
    while ($row = $result->fetch_assoc()) {
	$ret[] = $row;
    }
    return $ret;
}

function check_mon_exists($name, $region = null, $form = null){
  $query = "SELECT count(*) as count from mons where";
  $query .= " name " . format_raw_query_equivalence($name); 
  $query .= " AND region " . format_raw_query_equivalence($region);
  $query .= " AND form " . format_raw_query_equivalence($form);
  $count = db_raw_query($query);
  
  if (array_key_exists('count', $count[0]) && $count[0]['count'] > 0 ){
    return true;
  } else {
    return false;
  }
}

function get_collection_mon_id($mon_id, $collection_id, $form_extras){
  $query = "SELECT id from collection_mons where";
  $query .= " mon_id " . format_raw_query_equivalence($mon_id); 
  $query .= " AND collection_id " . format_raw_query_equivalence($collection_id);
  $query .= " AND form_extras " . format_raw_query_equivalence($form_extras);
  $res = db_raw_query($query);
  
  if (array_key_exists('id', $res[0])){
    return $res[0]['id'];
  } else {
    return false;
  }
}

function check_collection_mon_exists($mon_id, $collection_id, $form_extras){
  $query = "SELECT count(*) as count from collection_mons where";
  $query .= " mon_id " . format_raw_query_equivalence($mon_id); 
  $query .= " AND collection_id " . format_raw_query_equivalence($collection_id);
  $query .= " AND form_extras " . format_raw_query_equivalence($form_extras);
  $count = db_raw_query($query);
  
  if (array_key_exists('count', $count[0]) && $count[0]['count'] > 0 ){
    return true;
  } else {
    return false;
  }
}

function check_collection_mon_mine($id){
  $query = "SELECT count(*) as count from collection_mons where";
  $query .= " id " . format_raw_query_equivalence($id); 
  $query .= " AND my_catch = true";
  $count = db_raw_query($query);
  
  if (array_key_exists('count', $count[0]) && $count[0]['count'] > 0 ){
    return true;
  } else {
    return false;
  }
}

function get_ball_id($ball_name){
  $query = "SELECT id from balls where";
  $query .= " name " . format_raw_query_equivalence($ball_name); 
  $id = db_raw_query($query);
  
  if (array_key_exists('id', $id[0])){
    return $id[0]['id'];
  } else {
    return false;
  }
}

function format_raw_query_equivalence($value){
  if (is_null($value)){
    return 'IS NULL';
  }
  if (is_numeric($value)){
    return '= ' . $value;
  }
  //real escaping is kinda dum.
  $db = db_connect();
  $value = $db->real_escape_string($value);
  return "= '$value'";
}

