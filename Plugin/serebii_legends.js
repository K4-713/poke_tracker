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

  //first two tables are legends, and the last one is mythical
  var mythical = false;
  for (i = 0; i < 3; ++i) {
    if (i===2) {
      mythical = true;
    }
    var dex_table_rows = $('table.trainer').eq(i).children('tbody').children('tr');
    console.log("Found " + dex_table_rows.length + " table rows");
    if (dex_table_rows.length > 0) {
      dex_table_rows.each(function (j) {
        var send_these_too = parse_legends_row(this, mythical);
        if (send_these_too !== false) {
          sendMe = sendMe.concat(send_these_too);
        }
      });
    } else {
      console.log("got no rows. Dang.");
    }
  }

  console.log(sendMe);
  $('#dealie_message').html(sendMe.length + " monsters found");
  if (sendMe.length > 0) {
    add_button_to_dealie("Tag Legends++!", function (event) {
      console.log("I PUSHED THE BUTTON");
      set_dealie_button_result("...");
      disable_dealie_button();
      do_ajax('tag_legends', sendMe);
    });
  }

});

function parse_legends_row(tr, mythical = false) {
  var column = 'legendary';
  if (mythical) {
    column = 'mythical';
  }
  var cells = $(tr).children('td').children('table');
  var ret = [];
  if (cells.length > 0) {
    cells.each(function (i) {
      var name = $(this).find('tr').eq(1).children('td').children('a');
      if (name.length > 0){
        var add = {};
        add['name'] = $(name).text().trim();
        add[column] = true;
        ret.push(add);
      }
    });
  } else {
    return false;
  }

  console.log(ret);

  return ret;
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

