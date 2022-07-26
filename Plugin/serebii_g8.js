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

//global
var images = [];

$(document).ready(function () {
    document.body.style.border = "2px solid yellow";
    console.log("THIS IS YOUR EXTENSION. Helloooo.");
    add_dealie_to_page("This is a dealie.", 2);
    add_button_to_dealie("Download Images", download_images, pane = 2);
    unset_dealie_css('height');

    kill_ads();
    
    //grab the monster's name. Try second "dextable" -> second tr, first immediate td 
    var monster_name = $("table.dextable").eq(1).find("tr").eq(1).children("td").eq(0).text().trim();
    
    //check for images
    //The images we want to save apparently have alt text of "Normal Sprite" and "Shiny Sprite".Baller.
    //These are.png.
    images = add_image_info(images, monster_name);
    
    //add a hook to the sprite clickies
    var sprite_switchers = $('a.sprite-select');
    if (sprite_switchers.length > 0) { //sometimes they don't exist.
      sprite_switchers.each(function (i) {
        $(this).click(function () {
          images = add_image_info(images, monster_name);
        });
      });
    }
    
    //NEW PLAN: Show what we're about to save in the dealie. Add buttons to actually do the thing(s)
    
    
    //what else can we get frmm this page?
    //dynamaxer?
    //mega?
    //...maybe we'll want to track held items later. LATER I SAID. Per-game.
    //egg groups - none means sterile!
    //evolutionary line and requirements
    //per-game locations (later, I think. Maybe.)
    
    var variants = get_variants();
    console.log("Variants:");
    console.log(variants);
    
    var dex_numbers = get_dex_numbers();
    console.log("Dex Numbers:");
    console.log(dex_numbers);
    
    var gender_ratios = get_gender_ratios();
    console.log("Gender Ratios");
    console.log(gender_ratios);
    
    //what else do we have to do differently if we have multiple variants?
    
    //each variant should get its own built-out line to submit.
    //types
    //stats
    //abilities (fff)
    //evolution chain (double fff - okay, wait, this is okay: Images are named predictably!)
    //moves, but that's for later. Way, way later.


//    var sendMe = [];
//    var main_data = {
//	'item_name': item_name,
//	'rarity': rarity,
//	'release_date': release_date,
//	'price_history': price_history
//    }

    //Annoying. Oh well.
    //sendMe.push(main_data);

    //Er. You wanna do some error checking before we do this, or...?
    //do_ajax('jn_item', sendMe);

});

function add_image_info(images, monster_name){
  var maybe_image = $("img#sprite-regular");
  if (maybe_image.length > 0){
    images[get_img_final_name(maybe_image, monster_name)] = "https://www.serebii.net" + fix_img_src(maybe_image.attr('src'));
  }

  maybe_image = $("img#sprite-shiny");
  if (maybe_image.length > 0){
    images[get_img_final_name(maybe_image, monster_name)] = "https://www.serebii.net" + fix_img_src(maybe_image.attr('src'));
  }

  var message = "<ul style='padding-left:6px;text-align:left'>";
  for (var i in images) {
    message += "<li><a style='color:#998800' href='" + images[i] + "' target=_new>" + i + "</a></li>";
  }
  message += "</ul>";
  set_dealie_message("Images Found:<br>" + message, 2);

  return images;
}

//this will only work if images is indeed global
function download_images(){
  //Have the button send image download messages to the backgorund listener...
  for (var i in images) {
    chrome.runtime.sendMessage({ file: images[i], name: "scrapey/" + i }, function(response) {
      console.log(response.message);
    });
  }
}

function get_img_final_name(maybe_image, poke_name){
  var img_src = fix_img_src(maybe_image.attr('src'));
  //g8 image format:
  //Normal image: /swordshield/pokemon/263-g.png
  //Shiny: /Shiny/SWSH/263-g.png
  // :(
  
  var link_array = img_src.split('/');
  var shiny = false;
  if (link_array[1] === "Shiny"){
    shiny = true;
  }
  
  var orig_filename = link_array[(link_array.length-1)].split('.')[0]; //yeah, right
  var of_array = orig_filename.split('-');
  var variant = '';
  if (of_array.length > 1){
    variant = of_array[1];
  }
  
  var final_name = poke_name;
  if (variant !== ''){
    final_name += '_' + char_to_regional_variant(variant);
  }
  if (shiny){
    final_name += '_Shiny';
  }
  final_name += ".png";  //wfm
  return final_name;
}

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

//this should return an array with at least one variant.
//let's not make Kanto default, I don't even like that.
function get_variants(){
  var ret = [];
    //probably the best place to look for this will be the typing box.
    //so same line as the name... and the only "cen" class td. Cool.
    var types = $("table.dextable").eq(1).find("tr").eq(1).children("td.cen").eq(0);
    //now, what does this look like if there's a variant?
    //first child is a table. Rad.
    var variants = types.children("table");
    if(variants.length > 0){
      console.log("Found some variants!");
      var rows = variants.find("tr");
      rows.each(function (i) {
        ret.push($(this).children('td').eq(0).text().trim());
      });
    } else {
      console.log("No variants. Whew");
      ret.push('Normal');
    }
    return ret;
}

//Get all the different dex numbers, stuff them in an array
function get_dex_numbers(){
  var ret = [];
    //Second Dex table, Second row, column 3
    var main_cell = $("table.dextable").eq(1).find("tr").eq(1).children().eq(2);
    
    //first child is a table. Rad.
    var numbers = main_cell.children("table");
    if(numbers.length > 0){
      var rows = numbers.find("tr");
      rows.each(function (i) {
        var dex_name = $(this).children('td').eq(0).text().trim();
        var dex_number = $(this).children('td').eq(1).text().trim();
        dex_number = dex_number.replace('#', '');
        if (dex_number === "---"){
          return;
        }
        ret[dex_name] = parseInt(dex_number);
      });
    } else {
      console.error("Problem: No dex numbers!");
    }
    return ret;
}

//Get gender ratios
function get_gender_ratios(){
  var ret = [];
    //Second Dex table, Second row, column 4
    var main_cell = $("table.dextable").eq(1).find("tr").eq(1).children().eq(3);
    
    //first child is a table. Still rad.
    var genders = main_cell.children("table");
    if(genders.length > 0){
      var rows = genders.find("tr");
      rows.each(function (i) {
        var gender = $(this).children('td').eq(0).text().trim();
        gender = gender.split(" ")[0];
        var percent = $(this).children('td').eq(1).text().trim();
        percent = percent.replace('%', '');
        ret[gender] = parseFloat(percent);
      });
    } else {
      console.error("Problem: No genders!");
    }
    return ret;
}