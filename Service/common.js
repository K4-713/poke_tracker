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

function copyText(text, obj) {
    var dummy = document.createElement('textarea');
    document.body.appendChild(dummy);
    dummy.value = text;
    dummy.select();
    document.execCommand('copy');
    document.body.removeChild(dummy);

    $(".copy_highlighted").removeClass('copy_highlighted');
    $(obj).parent().addClass('copy_highlighted');
}

function do_ajax(action, rows, success_callback = false) {
    var sendMe = {};
    sendMe.rows = rows;
    sendMe.action = action;
    sendMe.key = data_auth_key;

    sendMe = JSON.stringify(sendMe);
    console.log(sendMe);

    $.ajax({
	type: "POST",
	url: "/data.php",
	data: sendMe,
	contentType: "application/json",
	responseType: 'application/json',
	dataType: "json",
	timeout: 10000,
	success: function (data, status, req) {
	    if (data.status === 'success') {
		if (success_callback === false) {
		    do_ajax_success_stuff(action, data);
		} else {
		    callback(success_callback);
		}
	    } else {
		do_ajax_fail_stuff(action, data.message);
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

function do_ajax_success_stuff(action, data, extras) {
    console.log(action + " successful. Reload?");
}

function do_ajax_fail_stuff(action, message, extras) {
    console.log("Action " + action + " failed: " + message);
}

function mark_complete(obj) {
    $(obj).parent().addClass('completed');
    $(obj).removeAttr('class');
    $(obj).addClass('check_img');
    $(obj).addClass('opacity_50');
    $(obj).addClass('grey_out_image');
    $(obj).removeAttr('onClick');
    $(obj).removeAttr('onMouseOver');
    $(obj).removeAttr('onMouseOut');
}

function update_shop_qty(neo_id, qty) {
    var div_id = "#" + neo_id + "_shop_qty";
    $(div_id).text(qty);
}

function get_shop_qty(neo_id) {
    var div_id = "#" + neo_id + "_shop_qty";
    return parseInt($(div_id).text());
}

function test_ajax(action, rows, success_callback = false){
  var what_i_got = "Action: " + action + "\nRows: " + JSON.stringify(rows);
  window.alert(what_i_got);
}

function poke_update(action, elem){
  var form = $(elem).closest("form");
  var backend_action = "toggle_collection_owned";
  var sendme = {};
  switch(action){
    case "owned":
      backend_action = "toggle_collection_owned";
      sendme = {
        'mon_id' : $(form).find("input#mon_id").attr("value"),
        'extra_form' : $(form).find("input#extra_form").attr("value"),
      };
      break;
    case "my_catch":
      backend_action = "toggle_collection_mine";
      sendme = {
        'collection_mons_id' : $(form).find("input#collection_mons_id").attr("value")
      };
      break;
  }
  test_ajax(backend_action, sendme);
}