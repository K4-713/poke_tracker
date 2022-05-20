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

    kill_ads();

    //But first, we need the item name...
    var item_name = $('div.content-wrapper h1').text();
    //console.log(item_name);

    var rarity = null;
    var release_date = null;

    var extra_info = $('div.content-wrapper h1 + div div ul.small-block-grid-2 li');
    if (extra_info.length > 0) {
	extra_info.each(function (i) {
	    var kids = $(this).children();
	    var heading = $(kids).eq(0).text().trim();
	    switch (heading) {
		case 'Rarity':
		    rarity = get_rarity($(kids).eq(1).text().trim());
		    break;
		case 'Release Date':
		    //coming in like 'January 30, 2002'
		    release_date = convert_jn_date_to_sql($(kids).eq(1).text().trim());
		    break;
	    }
	});
    }
    //console.log('Rarity = ' + rarity);
    //console.log('Release Date = ' + release_date);

    var price_history = [];
    var price_history_rows = $('div.price-row');
    if (price_history_rows.length > 0) {
	price_history_rows.each(function (i) {
	    var price_date = get_date_from_mush($(this));
	    var price = get_price_from_mush($(this).text().trim());
	    if (price && price_date) {
		var temp = {
		    'price': price,
		    'price_date': price_date
		};
		price_history.push(temp);
	    }
	});
    }

    var sendMe = [];
    var main_data = {
	'item_name': item_name,
	'rarity': rarity,
	'release_date': release_date,
	'price_history': price_history
    }

    //Annoying. Oh well.
    //sendMe.push(main_data);

    //Er. You wanna do some error checking before we do this, or...?
    //do_ajax('jn_item', sendMe);

});

function kill_ads() {
  var banner = $('div#content div');
  var underbanner = $('div#nn_sticky');
  disappear(banner);
  disappear(underbanner);
  
}

function do_ajax_fail_stuff(action, message) {
    console.log(message);
}

function do_ajax_success_stuff(action, data) {
    console.log(data.message);
}

function get_price_from_mush(mush) {
    var mush_array = mush.split("(");
    var text = mush_array[0];
    if (text.includes('Price unable to be determined')) {
	return false;
    }
    if (text.includes(' on ')) {
	var text_array = text.split(' on ');
	text = text_array[0];
    }

    var price_string = convert_neo_price_to_number(text.trim());
    return price_string;
}

function get_date_from_mush(mush) {
    var probably_date = mush.children().eq(2).text().trim();
    if (probably_date.includes("by ")) {
	var text = mush.text().trim();
	text_array = text.split(' on ');
	text = text_array[1];
	text_array = text.split(' by ');
	return get_date(text_array[0]);
    }
    return get_date(probably_date);
}