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


/**
 * Data Formatting
 */

//'dd/mm/yyyy' to 'yyyy-mm-dd'
function convert_neo_date_to_sql(date) {
    var date_array = date.split("/");
    var ret = date_array[2] + "-" + date_array[1] + "-" + date_array[0];
    return ret;
}

//'January 30, 2002' to 'yyyy-mm-dd'
function convert_jn_date_to_sql(date) {
    return get_date(date);
}

//price (with commas, and " NP" on the end)
function convert_neo_price_to_number(amount) {
    if (amount.includes(" NP")) {
	amount = amount.slice(0, amount.length - 3);
    }
    return amount.replace(/,/g, '');
}

//SQL formatted date - 'yyyy-mm-dd'
function get_date(date_value) {
    var datestring = '';
    var d;
    if (date_value === undefined) {
	var d = new Date();
    } else {
	var d = new Date(date_value);
    }
    datestring = d.getFullYear() + '-';
    var month = d.getMonth() + 1; //ffs
    if (month < 10) {
	datestring += '0';
    }
    datestring += month + '-';
    var day = d.getDate();
    if (day < 10) {
	datestring += '0';
    }
    datestring += day;

    //final check
    if (datestring.includes('NaN')) {
	return false;
    }

    return datestring;
}

//SQL formatted time - 'HH:MM:SS'
function get_time() {
    var timestring = '';
    var d = new Date();
    var hour = d.getHours();
    if (hour < 10) {
	timestring = '0';
    }
    timestring += hour + ":";
    var minute = d.getMinutes();
    if (minute < 10) {
	timestring += '0';
    }
    timestring += minute + ":";
    var second = d.getSeconds();
    if (second < 10) {
	timestring += '0';
    }
    timestring += second;
    return timestring;
}

function get_ints(int_string) {
    //basically, remove anything that isn't a number.
    //gonna assume the number is all in one place, and uninterrupted by commas
    var ints = int_string.match(/(\d+)/);
    if (ints) {
	return parseInt(ints[0]);
    } else {
	return false;
    }
}

function get_type_from_link(link) {
  //example: "/pokemon/type/grass"
  var type_array = link.split('/');
  var type = capitalize(type_array[type_array.length - 1]);
  type = type.split('.')[0]; //just in case. g8 links to .shtml files. 
  return type;
}

function capitalize(word){
  return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
}

function img_char_to_variant(char){
  switch(char){
    case 'a' :
      return "Alolan";
      break;
    case 'g' :
      return "Galarian";
      break;
    case 'h' :
      return "Hisuian";
      break;
    case 'o' :
      return "Origin";
      break;
    case 'n' :
      return "Normal";  //do I like this?
      break;
    default:
      return char + "-Undefined";
      break;
  }
}

function is_region(variant){
  var regional_forms = [
    "Kantonian",
    "Johtonian",
    "Hoennian",
    "Sinnoh",
    "Unovan",
    "Kalosian",
    "Galarian",
    "Alolan", "Alola",
    "Hisuian",
    "Paldean"
  ];
  return regional_forms.includes(variant);
}

function fix_img_src(src){
  var no_protocol = src.split("//");
  var nps = "";
  if (no_protocol.length === 1){
    nps = src;
  } else {
    nps = no_protocol[1];
  }
  
  var src_array = nps.split('/');
  if (src_array.length === 1){
    return src_array;
  }
  for (var i=0; i<src_array.length; ++i){
    if (src_array[i] === 'www.serebii.net'){
      src_array[i] = '';
    }
  }
  return src_array.join('/');
}

/**
 * Ajax doer
 * @param string action Something the php backend will understand how to route
 * @param array rows Nice pre-formatted rows of data. The actual rows should be associative
 * @param array extras Optional, if you need something in the success function.
 * @returns null, but will call do_ajax_fail_stuff and/or do_ajax_success_stuff **defined elsewhere**
 */

function do_ajax(action, rows, extras) {
    var sendMe = {};
    sendMe.rows = rows;
    sendMe.action = action;
    sendMe.key = data_auth_key;

    sendMe = JSON.stringify(sendMe);
    console.log(sendMe);

    $.ajax({
	type: "POST",
	url: service_url + "/data.php",
	data: sendMe,
	contentType: "application/json",
	responseType: 'application/json',
	dataType: "json",
	timeout: 10000,
	success: function (data, status, req) {
	    if (data.status === 'success') {
		do_ajax_success_stuff(action, data, extras);
	    } else {
		do_ajax_fail_stuff(action, data.message, extras);
	    }
	},
	error: function (req, status, err) {
	    do_ajax_fail_stuff("Ajax failure. Check your logs.");
	    console.log("Req =");
	    console.log(req);
	    console.log("Status = ");
	    console.log(status);
	    console.log("Err = ");
	    console.log(err);
	}
    });
}

/**
 * Universal UI dealie monster
 */


function add_dealie_to_page(message, panes = 1) {
    var dealie = document.createElement('div');
    dealie.id = "interface_dealie";

    for (var i=1; i <= panes; ++i){
      var dealie_message = document.createElement('div');
      dealie_message.id = "dealie_message_" + i;
      dealie.appendChild(dealie_message);

      var input_div = document.createElement('div');
      input_div.id = "dealie_input_div_" + i;
      dealie.appendChild(input_div);

      var button_div = document.createElement('div');
      button_div.id = "dealie_button_div_" + i;
      dealie.appendChild(button_div);
    }

    $('body').append(dealie);
    apply_dealie_css(panes);
    set_dealie_message(message);

}

function set_dealie_message(message, pane = 1){
    $('#dealie_message_' + pane).html(message);
    //just in case...
    $('#interface_dealie ul').css('padding-left', '6px')
        .css('text-align', 'left');
}

function add_button_to_dealie(button_text, button_callback, pane = 1) {
    var dealie_button = document.createElement('BUTTON');
    dealie_button.innerHTML = button_text;
    dealie_button.id = 'dealie_button_' + pane;
    dealie_button.onclick = button_callback;

    $('#dealie_button_div_' + pane).append(dealie_button);

    var interface_button_result = document.createElement('div');
    interface_button_result.id = 'interface_button_result_' + pane;
    $('#dealie_button_div_' + pane).append(interface_button_result);
}

function rebind_dealie_button(callback, pane = 1){
  $('#dealie_button_' + pane).attr('onclick', '').unbind('click');
  $('#dealie_button_' + pane).attr('onclick', callback);
}

function add_input_to_dealie(input_label, pane = 1) {
    var dealie_input = document.createElement('input');
    dealie_input.id = "interface_user_input_" + pane;

    var table = document.createElement("table");
    table.id = 'dealie_input_table_' + pane;
    $('#dealie_input_div_' + pane).append(table);

    $('#dealie_input_table_' + pane).append(document.createElement('tr'));

    var dealie_input_label = document.createElement('td');
    dealie_input_label.id = 'dealie_input_label_' + pane;

    $('#dealie_input_table_' + pane + ' tr').append(dealie_input_label);
    $('#dealie_input_table_' + pane + ' tr').append(document.createElement('td'));
    $('#dealie_input_div_' + pane + ' tr td + td').append(dealie_input);

    $('#dealie_input_table_' + pane).css('padding-top', '10px')
	    .css('padding-bottom', '10px');
    $('#dealie_input_label_' + pane).css('text-align', 'right')
	    .css('width', '120px');
    $('#interface_user_input_' + pane).css('width', '60px');
    $('#dealie_button_div_' + pane).css('padding-top', '0px');
    $('#interface_dealie').css('height', '125px')

    $('#dealie_input_label_' + pane).html(input_label);
}

function get_dealie_input_value(pane = 1) {
    return $('#interface_user_input_' + pane).val();
}

function disable_dealie_input(pane = 1) {
    $('#interface_user_input_' + pane).attr("disabled", true);
}

function enable_dealie_input(pane = 1) {
    $('#interface_user_input_' + pane).attr("disabled", false);
}

function remove_dealie_input(pane = 1) {
    $('#dealie_input_table_' + pane).remove();
}

function disable_dealie_button(pane = 1) {
    $('#dealie_button_' + pane).attr("disabled", true);
}

function enable_dealie_button(pane = 1) {
    $('#dealie_button_' + pane).attr("disabled", false);
}

function remove_dealie_button(pane = 1) {
    $('#dealie_button_' + pane).remove();
}

function apply_dealie_css(panes) {
    $('#interface_dealie').css('position', 'fixed')
	    .css('top', '10px')
	    .css('right', '8px')
	    .css('z-index', '10')
	    .css('width', '200px')
	    .css('height', '95px')
	    .css('border', '3px dashed')
	    .css('border-color', '#ffcc00')
	    .css('background-color', '#ffffff')
	    .css('opacity', "0.85")
	    .css('color', '#000000')
	    .css('font-family', 'Verdana, Arial, Helvetica')
	    .css('font-size', '12px');
    for (var i=1; i <= panes; ++i){
      $('#dealie_message_' + i).css('text-align', 'center')
              .css('font-weight', 'bold')
              .css('padding', '10px 15px 10px 15px');

      $('#dealie_button_div_' + i).css('text-align', 'center')
              .css('font-weight', 'bold')
              .css('padding', '20px 15px 10px 15px');
    }
}

function unset_dealie_css(attr) {
  $('#interface_dealie').css(attr, '');
}

function set_dealie_button_result(message, pane) {
    $('#interface_button_result_' + pane).html(message);
}

function highlight_all_onclick(input_obj) {
    input_obj.click(function () {
	$(this).select();
    });
}

function disappear(element) {
  element.css('visibility', 'none')
          .css('height', '0px');
}

/**
 * I had to do this several times, so... 
 * If expand_me is only one row, copy it once for each key supplied and return
 * @param array|Object expand_me
 * @param array keys
 * @returns array|Object expanded expand_me if there was only one row there, or the original
 */
function expand_array(expand_me, keys) {
  //actual array
  if (expand_me.length === 1 && keys.length > 1){
    var temp_expand_me = [];
    for(var i=0; i<keys.length; ++i){
      temp_expand_me[keys[i]] = expand_me[0];
    };
    return temp_expand_me;
  }
  
  //for some daffy reason, stats.length is always zero if the keys are named instead of numeric.
  //because I guess that rips a hole in the universe. k.
  if (get_named_key_array_length(expand_me) === 1 && keys.length > 1){
    var temp_expand_me = [];
    for(var i=0; i<keys.length; ++i){
      temp_expand_me[keys[i]] = expand_me[Object.keys(expand_me)[0]];
    };
    return temp_expand_me;
  }
  
  return expand_me;
  
}

/**
 * Trying to make compound forms suck less.
 * Return the best fallback key to use for the key supplied.
 * @param {string} variant - any variety of a variant key - could be region, form, or region|form
 * @param {array} data_array
 * @returns {undefined}
 */
function get_best_variant_fallback(variant, data_array){
  //easy stuff first
  if (data_array[variant]){
    return variant;
  } else {
    console.log("Doing a variant fallback for " + variant + ", check the results!");
  }
  
  //now what?
  //The problem I'm actually having right now, is: In the case of compound variants,
  //there are no abilities supplied for different forms - just the regions.
  //Also the region can be "Normal".
  if (variant.indexOf("|") !== -1){ //compound variant
    var region = variant.split("|")[0];
    if (data_array[region]){
      return region;
    }
    var form = variant.split("|")[1];
    if (data_array[form]){
      return form;
    }
    //fuck it
    if (data_array["Normal"]){
      return "Normal";
    }
  } else {
    //not compound, just... not there.
    if (!is_region(variant)) { //probably a form
      if (data_array["Normal"]){
        return "Normal";
      }
    }
  }
  console.log("wtf? " + variant);
  return "wtf";
}

function get_named_key_array_length(arrgh){
  return (Object.keys(arrgh).length);
}

function get_next_number_link(number){
  links = $("div#content").find("td[align=right]").find("a");
  console.log("Links?");
  console.log(links);
  for(var i=0; i<links.length; ++i){
    if (links[i].innerHTML.includes("#")){
      console.log("returning a link");
      links[i].innerHTML = links[i].innerHTML.replace("<br>", " - ");
      $(links[i]).css("color", "#000000");
      return links[i];
    }
  }
  console.log("Returning no link");
  return false;
}