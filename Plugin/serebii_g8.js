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
var normal_form = "Normal"; //not always.
var monster_name = ""; //gah

$(document).ready(function () {
    document.body.style.border = "2px solid yellow";
    console.log("THIS IS YOUR EXTENSION. Helloooo.");
    add_dealie_to_page("This is a dealie.", 2);
    add_button_to_dealie("Download Images", download_images, pane = 2);
    unset_dealie_css('height');

    kill_ads();
    
    //grab the monster's name. Try second "dextable" -> second tr, first immediate td 
    monster_name = $("table.dextable").eq(1).find("tr").eq(1).children("td").eq(0).text().trim();
    
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
    
    //what else can we get from this page?
    //catchability in different g8 games
    
    //LATER - needs new tables and/or a schema change.
    //evolutionary line and requirements - new dealie pane? oof.
    //  dynamaxer? mega-able?
    //...maybe we'll want to track held items way later.
    var lines =[];
    
    var variants = get_variants();
    console.log("Variants:");
    console.log(variants);
    
    var dex_numbers = get_dex_numbers();
    console.log("Dex Numbers:");
    console.log(dex_numbers);
    
    var gender_ratios = get_gender_ratios();
    console.log("Gender Ratios");
    console.log(gender_ratios);
    
    var types = get_types();
    console.log("Types:");
    console.log(types);
    
    var abilities = get_abilities();
    console.log("Abilities!:");
    console.log(abilities);
    
    var stats = get_stats();
    console.log("Stats:");
    console.log(stats);
    
    //expand all the things...
    if (types.length === 1 && variants.length > 1){
      var temp_types = [];
      for(var i=0; i<variants.length; ++i){
        temp_types[variants[i]] = types[0];
      };
      types = temp_types;
    }
    if (abilities.length === 1 && variants.length > 1){
      var temp_abilities = [];
      for(var i=0; i<variants.length; ++i){
        temp_abilities[variants[i]] = abilities[0];
      };
      abilities = temp_abilities;
    }
    
    //for some daffy reason, stats.length here is always zero... wat?
    if (Object.keys(stats).length === 1 && variants.length > 1){
      console.log("Tryin to expand stats here...");
      var temp_stats = [];
      for(var i=0; i<variants.length; ++i){
        temp_stats[variants[i]] = stats[normal_form];
      };
      stats = temp_stats;
    }    
    
    for (var i in variants) { //all the sames first
      //differentiating regions and forms is stupid.
      var variant = (variants[i] !== 'Normal' ? variants[i] : null);
      
      lines[variants[i]] = {
        name : monster_name,
        dex_national : dex_numbers['National'],
        region : (is_region(variant) ? variant : null),
        form : (!is_region(variant) ? variant : null),
        type1: (types[variants[i]].type1 || null),
        type2: (types[variants[i]].type2 || null),
        ability1: (abilities[variants[i]].ability1 || null),
        ability2: (abilities[variants[i]].ability2 || null),
        ability_hidden: (abilities[variants[i]].ability_hidden || null),
        b_hp : (stats[variants[i]].b_hp || null),
        b_att : (stats[variants[i]].b_att || null),
        b_def : (stats[variants[i]].b_def || null),
        b_sp_att : (stats[variants[i]].b_sp_att || null),
        b_sp_def : (stats[variants[i]].b_sp_def || null),
        b_speed : (stats[variants[i]].b_speed || null),
        female : gender_ratios['Female'],
        male : gender_ratios['Male'],
        egg_groups : get_egg_group_string(),
        dex_galar: (dex_numbers['Galar'] || null),
        dex_galar_isle: (dex_numbers['Isle of Armor'] || null),
        dex_galar_crown: (dex_numbers['Crown Tundra'] || null),
        dex_sinnoh_bdsp: (dex_numbers['Sinnoh'] || null),
        dex_hisui: (dex_numbers['Hisui'] || null),
      };
    }
    
    console.log("FINAL:");
    console.log(lines);
    
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
    final_name += '_' + img_char_to_variant(variant);
  } else {
    if (normal_form !== "Normal"){
      final_name += '_' + normal_form;
    }
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
  //shoot, I'm looking in the wrong place. REMIX
  var ret = [];
  //Variants are in the 7th or maybe 8th dextable, in a farther table, in bold.
  
  var variant_table = $("table.dextable").eq(6);
  if ($(variant_table).find("td").eq(0).text().trim() === "Gender Differences"){
    variant_table = $("table.dextable").eq(7);
  }

  var variants = $(variant_table).find("table").eq(0).find("b");
  //now, what does this look like if there's a variant?
  if(variants.length > 0){
    console.log("Found some variants!");
    variants.each(function (i) {
      ret.push(translate_form($(this).text().trim()));
    });
  } else {
    console.log("No variant types. Whew");
    ret.push('Normal');
  }

  //if there's no "Normal" in there, change the global synonym.
  if (!ret.includes("Normal")) {
    //again, this is basically a global.
    normal_form = ret[0];
  }

  return ret;
}

function translate_form(raw_form){
  //let's get rid of some garbage
  raw_form = raw_form.replace(" Forme", "");
  raw_form = raw_form.replace(" Form", "");
  switch (raw_form){
    case "Alola" :
      return "Alolan";
      break;
    case "Kantonian" :
    case "Johtonian" :
    case "Hoennian" :
      return "Normal";
      break;
    default :
      return raw_form;
  }
}

//could have plain types, or variant-based typing. gah.
function get_types(){
  var ret = [];
    //Same line as the name... and the only "cen" class td. Cool.
    var types = $("table.dextable").eq(1).find("tr").eq(1).children("td.cen").eq(0);
    //now, what does this look like if there's a variant?
    //first child is a table. Rad.
    var variants = types.children("table");
    if(variants.length > 0){
      console.log("Found some variants!");
      var rows = variants.find("tr");
      rows.each(function (i) {
        var variant = $(this).children('td').eq(0).text().trim();
        ret[variant] = get_types_from_links($(this).children('td').eq(1).children("a"));
      });
    } else {
      console.log("Single typing");
      var type_links = types.children("a");
      ret.push(get_types_from_links(type_links));
    }
    return ret;
}

function get_types_from_links(links){
  var ret = {};
  var counter = 1; //harumph
  links.each(function(i){
    ret["type" + counter] = get_type_from_link($(this).attr('href'));
    counter += 1;
  });
  return ret;
}

//This is gonna get ugly.
function get_abilities(){
  var ret = [];
    var maybe_abilities = $("table.dextable").eq(2).find("tr").eq(1).find("b");
    
    if(maybe_abilities.length > 0){
      var variant = normal_form;
      var ha_flag = false;
      var build_me = {};
      maybe_abilities.each(function (i) {
        var maybe_ability = $(this).text().trim();
        //here, we either have an ability, a Hidden Ability marker, or a form changer. Or garbage.

        if (ha_flag){
          build_me.ability_hidden = maybe_ability;
          ha_flag = false;
          return;
        }
        if (maybe_ability.includes("Hidden Ability")){
          ha_flag = true;
          return;
        }
        if (maybe_ability.includes("Form Abilit") || maybe_ability.includes("Forme Abilit")){
          var new_form = maybe_ability.split(" ")[0];
          ret[variant] = build_me;
          build_me = {};
          variant = translate_form(new_form);
          return;
        }
        //If we're still here, it's just an ability. Enjoy it.
        if ((build_me.ability1 || false)){
          build_me.ability2 = maybe_ability;
        } else {
          build_me.ability1 = maybe_ability;
        }
      });
      ret[variant] = build_me;
    } else {
      console.error("No abilities found!");
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
        var dex_name = $(this).children('td').eq(0).text().trim().replace(':', '');
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
    ret["Male"] = null;
    ret["Female"] = null;
  }
  return ret;
}

//Get stats
function get_stats(){
  var ret = [];
  var stat_anchor = $("a[name='stats']");
  //while the next sibling is a table.dextable, we have more stats.
  
  var checkme = stat_anchor.next();
  var go = true;
  while (go) {
    if (checkme.is("table") && checkme.hasClass("dextable")){
      //check for a form change, then grab dem numbers.
      var form_text = $(checkme).find("tr").eq(0).children("td").eq(0).text().trim();
      if (form_text === "Stats"){
        form_text = normal_form;
      } else {
        form_text = form_text.replace("Stats - ", "");
        form_text = form_text.replace("Forme", "Form");
        form_text = form_text.replace(" Form", "");
        form_text = form_text.replace(" " + monster_name, "");
      }
      
      var stat_row = $(checkme).find("tr").eq(2);
      
      var build_me = {
        b_hp : $(stat_row).children("td").eq(1).text().trim(),
        b_att : $(stat_row).children("td").eq(2).text().trim(),
        b_def : $(stat_row).children("td").eq(3).text().trim(),
        b_sp_att : $(stat_row).children("td").eq(4).text().trim(),
        b_sp_def : $(stat_row).children("td").eq(5).text().trim(),
        b_speed : $(stat_row).children("td").eq(6).text().trim(),
      }
      
      ret[form_text] = build_me;
      checkme = checkme.next();
    } else {
      go = false;
    }
  }
  return ret;
}

function get_egg_group_string(){
  var ret = null;
  var egg_group_links = $("table.dextable").eq(4).find("tr").eq(1).children("td").eq(1).find("td").eq(1).find("a");
  egg_group_links.each(function(i){
    if(ret === null){
      ret = $(this).text().trim();
    } else {
      ret += $(this).text().trim();
    }
  });
  return ret;
}

function get_catchable(variants){
  //Assumption: For each game, if it's catchable in that game's region, 
  //and there's specifically a variant named for the region, 
  //that's the catchable one. Any others probably aren't.
  //.
  //For each game, if there's no specific variant for that region, and there's a region
  //callout, that one isn't catchable there.
  //
  //If there are no regional variants, they're all catchable.
  
}