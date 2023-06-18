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
    add_dealie_to_page("This is a dealie.", 3);
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
    
    //LATER - needs new tables and/or a schema change.
    //evolutionary line and requirements - new dealie pane? oof.
    //  dynamaxer? mega-able?
    //...maybe we'll want to track held items way later.
    
    var dex_numbers = get_dex_numbers();
    console.log("Dex Numbers:");
    console.log(dex_numbers);
    var natdex = dex_numbers["National"];
    
    var variants = get_variants(natdex);
    console.log("Variants:");
    console.log(variants);
    
    var gender_ratios = get_gender_ratios();
    console.log("Gender Ratios");
    console.log(gender_ratios);
    
    var types = get_types(natdex);
    console.log("Types:");
    console.log(types);
    
    var abilities = get_abilities(natdex);
    console.log("Abilities!:");
    console.log(abilities);
    
    var stats = get_stats(natdex);
    console.log("Stats:");
    console.log(stats);
    
    var catchable = get_catchable(variants);
    console.log("Catchable");
    console.log(catchable);
    
    var next_link = get_next_number_link(dex_numbers['National']);
    set_dealie_message(next_link, 3);
    
    //expand all the things that *could* be per-variant, but may not be
    types = expand_array(types, variants);
    abilities = expand_array(abilities, variants);
    stats = expand_array(stats, variants);
    
    var li_fillet = ""; //wait for it...
    
    var sendMe =[];
    
    for (var i in variants) { //all the sames first
      //handle it here, and pick the right stats to look at.
      var variant = variants[i];
      var region = is_region(variant) ? variant : null;
      var form = !is_region(variant) ? variant : null;
      if (variant.indexOf("|") !== -1){
        console.log("AGH! COMPOUND FORM!");
        region = variant.split("|")[0];
        form = variant.split("|")[1];
      }
      
      sendMe.push({
        name : monster_name,
        dex_national : dex_numbers['National'],
        region : (region !== 'Normal' ? region : null),
        form : (form !== 'Normal' ? form : null),
        type1: (types[variants[i]].type1 || null),
        type2: (types[variants[i]].type2 || null),
        ability1: (abilities[get_best_variant_fallback(variants[i], abilities)].ability1 || null),
        ability2: (abilities[get_best_variant_fallback(variants[i], abilities)].ability2 || null),
        ability_hidden: (abilities[get_best_variant_fallback(variants[i], abilities)].ability_hidden || null),
        b_hp : (stats[variants[i]].b_hp || null),
        b_att : (stats[variants[i]].b_att || null),
        b_def : (stats[variants[i]].b_def || null),
        b_sp_att : (stats[variants[i]].b_sp_att || null),
        b_sp_def : (stats[variants[i]].b_sp_def || null),
        b_speed : (stats[variants[i]].b_speed || null),
        female : gender_ratios['Female'],
        male : gender_ratios['Male'],
        egg_groups : get_egg_group_string(),
        dex_paldea: (dex_numbers['Paldea'] || null),
        catchable_sv: (catchable[variants[i]].catchable_sv || null),
      });
      li_fillet += "<li>" + variants[i] + "</li>"; //...wait for it...
    }
    
    console.log("FINAL:");
    console.log(sendMe);
    
    var main_message = "Scraped " + sendMe.length + " variants:";
    main_message += "<ul>";
    main_message += li_fillet; //there it is
    main_message += "</ul>";
    set_dealie_message(main_message, 1);
    
    add_button_to_dealie("Send 'em home!", function(){send_it_on_home(sendMe);}, 1);
});

function send_it_on_home(mons){
  console.log("CLICK");
  do_ajax('g9_dex', mons);
}

function add_image_info(images, monster_name){
  var maybe_image = $("img#sprite-regular");
  if (maybe_image.length > 0){
    images[get_img_final_name(maybe_image, monster_name)] = "https://www.serebii.net" + fix_img_src(maybe_image.attr('src'));
  }

  maybe_image = $("img#sprite-shiny");
  if (maybe_image.length > 0){
    images[get_img_final_name(maybe_image, monster_name)] = "https://www.serebii.net" + fix_img_src(maybe_image.attr('src'));
  }

  var message = "<ul>";
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
function get_variants(natdex){
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
      var add_me_maybe = translate_form($(this).text().trim(), natdex);
      if (ret.indexOf(add_me_maybe) === -1){ //unique it. #869
        ret.push(add_me_maybe);
      }
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

function translate_form(raw_form, natdex){
  //let's get rid of some garbage
  raw_form = raw_form.replace(" Forme", "");
  raw_form = raw_form.replace(" Form", "");
  raw_form = raw_form.replace(" Standard Mode", "");
  switch (raw_form){
    case "Alola" :
    case "Alolan" :
      if (natdex >= 722 && natdex <= 807 ){
        return "Normal"
      } else {
        return "Alolan";
      }
      break;
    case "Galarian" :
      if (natdex >= 810 && natdex <= 890 ){
        return "Normal"
      } else {
        return "Galarian";
      }
      break;
    case "Hisui" :
    case "Hisuian" :
      if (natdex >= 899 && natdex <= 904 ){
        return "Normal"
      } else {
        return "Hisuian";
      }
      break;
    case "Paldean" :
      if (natdex >= 906 && natdex <= 1010 ){
        return "Normal"
      } else {
        if (natdex === 128){
          return "Paldean|Combat";
        }
        return "Paldean";
      }
      break;
    case "Kantonian" :
    case "Johtonian" :
    case "Hoennian" :
    case "Unovan" :
    case "Kalosian" :
    case monster_name, "":
      return "Normal";
      break;
    default :
      //ugh, this is going to get nasty.
      switch(natdex){
        case 555:
          //Compound form modes go region mode mode mode whatever.
          //translate stripped region
          var split = raw_form.split(" ");
          if (!is_region(split[0])) {
            return raw_form;
          }
          var ret_me = translate_form(split[0], natdex);
          if (split.length > 1){
            split.splice(0, 1);
            if (ret_me === "Normal"){
              ret_me = split.join(" ");
            } else {
              ret_me += "|" + split.join(" ");
            }
          }
          return ret_me;
          break;
        case 25:
          //fuck pikachu "cap" forms
          console.log("Translating Pikachu " + raw_form);
          if (raw_form.includes(" Cap")){
            return "Normal";
          }
          return raw_form;
          break;
        case 128:
          raw_form = raw_form.replace(" Breed", "");
          raw_form = raw_form.replace("Paldean", "Paldean|");
          if (!raw_form.includes("Paldean") && raw_form !== "Normal" ) {
            raw_form = "Paldean|" + raw_form;
          }
          return raw_form;
          break;
        default:
          return raw_form;
      }
  }
}

//could have plain types, or variant-based typing. gah.
function get_types(natdex){
  var ret = [];
    //Same line as the name... and the only "cen" class td. Cool.
    var types = $("table.dextable").eq(1).find("tr").eq(1).children("td.cen").eq(0);
    //now, what does this look like if there's a variant?
    //first child is a table. Rad.
    var variants = types.children("table");
    if(variants.length > 0){
      console.log("Found some variants!");
      var rows = variants.find("tr");
      var last_variant = "";
      rows.each(function (i) {
        var variant = translate_form($(this).children('td').eq(0).text().trim(), natdex);
        if (ret[variant]){ //already in there
          switch (natdex) { //have to special catch those with compound region and form
            case 555: //types go region, form, region, exact same form
              variant = last_variant + "|" + variant;
              break;
          }
        }
        ret[variant] = get_types_from_links($(this).children('td').eq(1).children("a"));
        last_variant = variant;
      });
    } else {
      console.log("Single typing");
      var type_links = types.children("a");
      ret[normal_form] = get_types_from_links(type_links);
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
function get_abilities(natdex){
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
          var new_form = maybe_ability.split(" Form")[0];
          ret[variant] = build_me;
          build_me = {};
          variant = translate_form(new_form, natdex);
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
      if (natdex === 128){
        ret["Paldean|Aqua"] = ret["Paldean|Combat"];
        ret["Paldean|Blaze"] = ret["Paldean|Combat"];
      }
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
function get_stats(natdex){
  var ret = [];
  var stat_anchor = $("a[name='stats']");
  //while the next sibling is a table.dextable, we have more stats.
  if (stat_anchor.length === 0){
    console.log("Arceus town");
    stat_anchor = $("a[name='legendsstats']");
  }
  var checkme = stat_anchor.next();
  if (checkme.length === 0){
    //geeze. Pick a page format.
    checkme = stat_anchor.parent().next();
  }
  var go = true;
  while (go) {
    if (checkme.is("table") && checkme.hasClass("dextable")){
      //check for a form change, then grab dem numbers.
      var form_text = $(checkme).find("tr").eq(0).children("td").eq(0).text().trim();
      form_text = form_text.replace("Click here for full details", "");
      if (form_text === "Stats"){
        form_text = normal_form;
      } else {
        form_text = form_text.replace("Stats - ", "");
        form_text = form_text.replace(" " + monster_name, "");
        form_text = translate_form(form_text, natdex)
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
      if (checkme.is("div") && checkme.hasClass("radar-graph")){
        checkme = checkme.next();
      }
    } else {
      go = false;
    }
    if (natdex === 128){ // :[
      ret["Paldean|Blaze"] = ret["Paldean|Combat"];
      ret["Paldean|Aqua"] = ret["Paldean|Combat"];
    }
  }
  return ret;
}

function get_egg_group_string(){
  var ret = null;
  var egg_group_links = $("table.dextable").eq(4).find("tr").eq(1).children("td").eq(1).find("a");
  egg_group_links.each(function(i){
    if(ret === null){
      ret = $(this).text().trim();
    } else {
      ret += ", " + $(this).text().trim();
    }
  });
  return ret;
}

/**  Assumption: For each game, if it's catchable in that game's region, 
  and there's specifically a variant named for the region, 
  that's the catchable one. Any others probably aren't.
  .
  For each game, if there's no specific variant for that region, and there's a region
  callout, that one isn't catchable there.

  If there are no regional variants, they're all catchable. **/

//Get catchable games
function get_catchable(variants){
  var ret = [];
  for(var i=0; i<variants.length; ++i){
    ret[variants[i]] = {
      catchable_sv : false
    };
  };
  
  var stat_anchor = $("a[name='location']").eq(0);
  //while the next sibling is a table.dextable, we have more stats.
  var checkme = stat_anchor.next();
  if (checkme.is("table") && checkme.hasClass("dextable")){
    console.log("Found the location table");
    //step through the game lines
    var rows = $(checkme).find("tr");
    rows.each(function(i) {
      var tds = $(this).children("td");
      var game = false;
      var locations = false;
      if (tds.length === 2 || tds.length === 3){
        game = get_game_from_location_text($(this).children("td").eq(0).text().trim());
        locations = $(this).children("td").eq(1);
      }
      if (tds.length === 4){
        game = get_game_from_location_text($(this).children("td").eq(1).text().trim());
        locations = $(this).children("td").eq(2);
      }
      console.log("Found game: " + game);

      if (game){
        switch(game){
          case "swsh":
            if (variants.includes("Galarian")){
              ret["Galarian"].catchable_swsh = true;
            } else {
              if ($(locations).find("a").length > 0){ //links mean yes! I think.
                //set the normal one to true
                ret[normal_form].catchable_swsh = true;
              }
            }
            break;
          case "bdsp":
            if ($(locations).find("a").length > 0){ //links mean yes! I think.
              //set the normal one to true
              ret[normal_form].catchable_bdsp = true;
            }
            break;
          case "pla":
            if (variants.includes("Hisuian")){
              ret["Hisuian"].catchable_pla = true;
            } else {
              if ($(locations).find("a").length > 0){ //links mean yes! I think.
                //set the normal one to true
                ret[normal_form].catchable_pla = true;
              }
            }
            break;
          case "sv":
            if (variants.includes("Paldean")){
              ret["Paldean"].catchable_sv = true;
            } else {
              if ($(locations).find("a").length > 0){ //links mean yes! I think.
                //set the normal one to true
                ret[normal_form].catchable_sv = true;
              }
            }
            break;
        }
      }
    });
    
  } else {
    console.error("No location data found");
    return false;
  }
  
  return ret;
}

function get_game_from_location_text(text){
  switch (text){
    case "Sword":
    case "Shield":
      return "swsh";
      break;
    case "Brilliant Diamond":
    case "Shining Pearl":
      return "bdsp";
      break;
    case "Legends: Arceus":
      return "pla";
      break;
    case "Scarlet":
    case "Violet":
      return "sv";
      break;
    default:
      return false;
  }
}
