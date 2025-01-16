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

function do_ajax(action, rows, elem = null) {
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
                do_ajax_success_stuff(action, data, elem);
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

function do_ajax_success_stuff(action, data, elem) {
  switch(action){
    case "toggle_collection_owned":
      toggle_own_complete(action, data, elem);
      break;
    case "toggle_collection_mine":
      toggle_mine_complete(action, data, elem);
      break;
    case 'set_collection_ability':
    case 'set_collection_ball':
      set_dd_value_complete(action, data, elem);
      break;
    default:
      console.log(action + " successful. Reload?");
  }
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
  var sendme = [];
  switch(action){
    case "owned":
      backend_action = "toggle_collection_owned";
      sendme.push({
        'mon_id' : $(form).find("input#mon_id").attr("value"),
        'form_extras' : $(form).find("input#form_extras").attr("value"),
        'collection_id' : $('input#collection_id').attr("value"),
      });
      $(elem).prop("disabled", true);
      break;
    case "my_catch":
      backend_action = "toggle_collection_mine";
      sendme.push({
        'id' : $(form).find("input#collection_mons_id").attr("value")
      });
      $(elem).prop("disabled", true);
      break;
    case "ability":
      backend_action = "set_collection_ability";
      sendme.push({
        'id' : $(form).find("input#collection_mons_id").attr("value"),
        'ability' : $(form).find("select#ability").val(),
      });
      $(elem).prop("disabled", true);
      break;
    case "ball":
      backend_action = "set_collection_ball";
      sendme.push({
        'id' : $(form).find("input#collection_mons_id").attr("value"),
        'ball' : $(form).find("select#ball").val(),
      });
      $(elem).prop("disabled", true);
      break;
  }
  do_ajax(backend_action, sendme, elem);
}

function toggle_own_complete(action, data, elem){
  $(elem).prop("disabled", false);
  if (data.message.includes("Inserted")){
    $(elem).prop( "checked", true );
    enable_mon(elem, data);
  } else {
    $(elem).prop( "checked", false );
    disable_mon(elem);
  }
}

function toggle_mine_complete(action, data, elem){
  $(elem).prop("disabled", false);
  if (data.message.includes("Unowned")){
    $(elem).prop( "checked", false );
  } else {
    $(elem).prop( "checked", true );
  }
}

function set_dd_value_complete(action, data, elem){
  $(elem).prop("disabled", false);
  //lol, maybe
  if (data[0]) {
    $(elem).val(data[0].$(elem).prop("id"));
  }
  style_mon(elem);
}

//to be run on document ready in the box view
function box_view_doc_ready(){
  //get all the owned checkboxes
  var unowned = $('input#owned:not(:checked)');
  //window.alert("Found " + unowned.length + " unowned");
  //for each owned checkbox that isn't checked, disable the mon
  unowned.each(function (i) {
    disable_mon(this);
  });
  
  //and style the rest?
  var owned = $('input#owned:checked');
  owned.each(function (i) {
    style_mon(this);
  });
  
}

function disable_mon(elem){
  var form = $(elem).closest("form");
  $(form).find("input#collection_mons_id").remove();
  $(form).find("input#my_catch").prop("disabled", true);
  $(form).find("select#ability").prop("disabled", true);
  $(form).find("select#ball").prop("disabled", true);
  $(form).find("table").addClass("disabled");
}

function enable_mon(elem, data = false){
  var form = $(elem).closest("form");
  $(form).find("input#my_catch").prop("disabled", false);
  $(form).find("select#ability").prop("disabled", false);
  $(form).find("select#ball").prop("disabled", false);
  $(form).find("table").removeClass("disabled");
  if (data){
    console.log("Data! woot.");
    console.log(data);
    $('<input type="hidden">').attr({
      id: 'collection_mons_id',
      name: 'collection_mons_id',
      value: data.data.id
    }).insertAfter($(form).find("input#form_extras"));
  } else {
    console.log("No data");
  }
}
  
function style_mon(elem){
  var form = $(elem).closest("form");
  
  $(form).find("table").removeClass("needs_ability");
  $(form).find("table").removeClass("needs_ball");
  $(form).find("table").removeClass("needs_both");
  
  hidden_ability = $(form).find("input#hidden_ability").val();
  
  needs_hidden_ability = false;
  if (hidden_ability !== ""){
    selected_ability = $(form).find("select#ability").val();
    if ($(form).find("select#ability").val() !== hidden_ability){
      needs_hidden_ability = true;
    }
  }
  
  //get ball image and tier for selected ball
  selected_ball_opt = $(form).find("select#ball").children().filter(':selected');
  
  ball_tier = $(selected_ball_opt).attr("tier");
  ball_image = $(selected_ball_opt).attr("image");
  
  if(ball_image !== undefined){
    var ball_td = $(selected_ball_opt).closest("td.ball");
    ball_td.css('background-image', 'url(./Images/Balls/' + ball_image + ')');
    ball_td.css('background-repeat', 'no-repeat');
    ball_td.css('background-position', '16px center');
    ball_td.css('background-size', '19px 19px')
  }
  
  needs_better_ball = true;
  if (ball_tier !== "" && ball_tier <= 3){
    needs_better_ball = false;
  }
  
  if (needs_hidden_ability && needs_better_ball){
    $(form).find("table").addClass("needs_both");
  }
  
  if (needs_hidden_ability && !needs_better_ball){
    $(form).find("table").addClass("needs_ability");
  }
  
  
  if (!needs_hidden_ability && needs_better_ball){
    $(form).find("table").addClass("needs_ball");
  }
  
}

window.onmouseup = function() { highlighter() };
window.onscroll = function() { highlighter() };

function highlighter() {
  //reset everything
  $(".searched").removeClass('searched');
  
  var thing = document.getSelection().toString();
    if (thing.length > 0){
      //highlight something
      console.log(thing);
      var elems = $("td.name:contains('" + thing + "')").each(function() {
        console.log($(this).text());
        var form = $(this).closest("form");
        $(form).find("table.mon").addClass("searched");
      });
    }
}

  