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


$(document).ready(function () {
    document.body.style.border = "2px solid yellow";
    console.log("THIS IS YOUR EXTENSION. Helloooo.");
    add_dealie_to_page("This is a dealie.");
    
    var sendMe = [];

    //In the national dex page, it's all in the dextable...
    var dex_table_rows = $('table.dextable tr');
    if (dex_table_rows.length > 0){
      dex_table_rows.each(function (i) {
        var dex_entry = parse_natdex_row(this);
        if (dex_entry !== false){
          sendMe.push(dex_entry);
        }
      });
    }
    
    console.log(sendMe);
    $('#dealie_message').html(sendMe.length + " monsters found");
    if (sendMe.length > 0){
      add_button_to_dealie("Upload monsters!", function (event) {
	    console.log("I PUSHED THE BUTTON");
            set_dealie_button_result("...");
	    disable_dealie_button();
	    do_ajax('natdex_index', sendMe)
      });
    }

});

function parse_natdex_row(tr){
  var tds = $(tr).children();
  //test to see if the row is real
  if (tds.eq(0).attr("class") !== "fooinfo"){
    return false;
  }
  //Lines go: number, image, name, type(s), abilities, stats (hp, att, def, s_att, s_def, speed)
  var row_data = {};
  row_data.dex_national = get_ints(tds.eq(0).text().trim());
  row_data.name = tds.eq(2).text().trim();
  
  //handle types
  var types = $(tds.eq(3)).children();
  row_data.type1 = get_type_from_link(types.eq(0).attr('href'));
  row_data.type2 = null;
  if (types.length > 1){
    row_data.type2 = get_type_from_link(types.eq(1).attr('href'));
  }
  
  //and abilities
  var abilities = $(tds.eq(4)).children('a');
  row_data.ability1 = abilities.eq(0).text().trim();
  row_data.ability2 = null;
  row_data.ability_hidden = null;
  if (abilities.length === 2){
    row_data.ability_hidden = abilities.eq(1).text().trim();
  }
  if (abilities.length === 3){
    row_data.ability2 = abilities.eq(1).text().trim();
    row_data.ability_hidden = abilities.eq(2).text().trim();
  }
  
  //finally, stats
  row_data.b_hp = get_ints(tds.eq(5).text().trim());
  row_data.b_att = get_ints(tds.eq(6).text().trim());
  row_data.b_def = get_ints(tds.eq(7).text().trim());
  row_data.b_sp_att = get_ints(tds.eq(8).text().trim());
  row_data.b_sp_def = get_ints(tds.eq(9).text().trim());
  row_data.b_speed = get_ints(tds.eq(10).text().trim());

  console.log(row_data);
 
  return row_data;
}

function do_ajax_fail_stuff(action, message) {
  set_dealie_button_result("Graceful Failure!<br>" + message);
  enable_dealie_button();
  console.log(message);
}

function do_ajax_success_stuff(action, data) {
  set_dealie_button_result("Success!<br>" + data.message);
  console.log(data.message);
}

