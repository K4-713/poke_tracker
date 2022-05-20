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
    var ints = rarity_string.match(/(\d+)/);
    if (ints) {
	return ints[0];
    } else {
	return false;
    }
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


function add_dealie_to_page(message) {
    var dealie = document.createElement('div');
    dealie.id = "interface_dealie";

    var dealie_message = document.createElement('div');
    dealie_message.id = "dealie_message";
    dealie.appendChild(dealie_message);

    var input_div = document.createElement('div');
    input_div.id = "dealie_input_div";
    dealie.appendChild(input_div);

    var button_div = document.createElement('div');
    button_div.id = "dealie_button_div";
    dealie.appendChild(button_div);

    $('body').append(dealie);
    apply_dealie_css();

    $('#dealie_message').html(message);

}

function add_button_to_dealie(button_text, button_callback) {
    var dealie_button = document.createElement('BUTTON');
    dealie_button.innerHTML = button_text;
    dealie_button.id = 'dealie_button';
    dealie_button.onclick = button_callback;

    $('#dealie_button_div').append(dealie_button);

    var interface_button_result = document.createElement('div');
    interface_button_result.id = 'interface_button_result';
    $('#dealie_button_div').append(interface_button_result);
}

function add_input_to_dealie(input_label) {
    var dealie_input = document.createElement('input');
    dealie_input.id = "interface_user_input";

    var table = document.createElement("table");
    table.id = 'dealie_input_table';
    $('#dealie_input_div').append(table);

    $('#dealie_input_table').append(document.createElement('tr'));

    var dealie_input_label = document.createElement('td');
    dealie_input_label.id = 'dealie_input_label';

    $('#dealie_input_table tr').append(dealie_input_label);
    $('#dealie_input_table tr').append(document.createElement('td'));
    $('#dealie_input_div tr td + td').append(dealie_input);

    $('#dealie_input_table').css('padding-top', '10px')
	    .css('padding-bottom', '10px');
    $('#dealie_input_label').css('text-align', 'right')
	    .css('width', '120px');
    $('#interface_user_input').css('width', '60px');
    $('#dealie_button_div').css('padding-top', '0px');
    $('#interface_dealie').css('height', '125px')

    $('#dealie_input_label').html(input_label);
}

function get_dealie_input_value() {
    return $('#interface_user_input').val();
}

function disable_dealie_input() {
    $('#interface_user_input').attr("disabled", true);
}

function enable_dealie_input() {
    $('#interface_user_input').attr("disabled", false);
}

function remove_dealie_input() {

    $('#dealie_input_table').remove();
}

function disable_dealie_button() {
    $('#dealie_button').attr("disabled", true);
}

function enable_dealie_button() {
    $('#dealie_button').attr("disabled", false);
}

function remove_dealie_button() {
    $('#dealie_button').remove();
}

function apply_dealie_css() {
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

    $('#dealie_message').css('text-align', 'center')
	    .css('font-weight', 'bold')
	    .css('padding', '10px 15px 0px 15px');

    $('#dealie_button_div').css('text-align', 'center')
	    .css('font-weight', 'bold')
	    .css('padding', '20px 15px 0px 15px');
}

function set_dealie_button_result(message) {
    $('#interface_button_result').html(message);
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