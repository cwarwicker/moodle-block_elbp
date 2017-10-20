<?php
/**
 * Javascript scripts
 *
 * This outputs all the javascript which is required to make the ELBP work
 * 
 * @copyright 2014 Bedford College
 * @package Bedford College Electronic Learning Blue Print (ELBP)
 * @version 1.0
 * @author Conn Warwicker <cwarwicker@bedford.ac.uk> <conn@cmrwarwicker.com>
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 */

header('Content-Type: text/javascript');
require '../../../config.php';
require_once '../lib.php';
require $CFG->dirroot . '/version.php';
$moodleVersion = $version;

$ELBP = new \ELBP\ELBP( array('suppress_errors' => true) );

// Some variables that might be required in the heredoc
$studentID = optional_param('studentid', -1, PARAM_INT);
$courseID = optional_param('courseid', -1, PARAM_INT);
$string = get_string_manager()->load_component_strings('block_elbp', $CFG->lang, true);

// If in development mode, reload plugins when you close an expanded view
$reloadPluginsOnUnPop = ($CFG->debug >= 32767) ? 'ELBP.load("group", ELBP.pluginGroup);' : '';

$dockPosition = $ELBP->getDockPosition();

$icons = "";
if ($ELBP->getPlugins())
{
    foreach($ELBP->getPlugins() as $plugin)
    {
        if ($plugin->isCustom())
        {
            $name = $plugin->getName();
            $name = str_replace(" ", "_", $name);
            $icons .= "pluginIcons['{$name}'] = '{$plugin->getDockIconPath()}';\n";
        } 
        else
        {
            $icons .= "pluginIcons['{$plugin->getName()}'] = '{$plugin->getDockIconPath()}';\n";
        }
    }
}

$output = <<<JS

   var savedStates = new Array();
   var savedInputs = {};
   var ELBP = "";
   var myVar = null;
   var savedCommands = new Array();     
   var commandPointer = 0;
   var dockPosition = '{$dockPosition}';
   var pluginIcons = {};
   var moodleVersion = '{$moodleVersion}';
   {$icons}

        
   var www = '{$CFG->wwwroot}/';
   
   ELBP = {
   
        tempData: null,
        studentID: {$studentID},
        courseID: {$courseID},
        pluginGroup: null,
            
        hide : function(el){
            $(el).css('display', 'none');
        },
        
        show : function(el){
            $(el).css('display', 'block');
        },

        ajax: function(plugin, action, params, callback, callBefore){
        
            if (callBefore){
                callBefore();
            }
        
            var url = "{$CFG->wwwroot}/blocks/elbp/js/ajaxHandler.php";
                        
            $.ajax({
                type: "POST",
                url: url,
                data: {plugin: plugin, action: action, params: params},
                error: function(d){
                    ELBP.ajax_error(d.responseText);
                    console.log('Error: ' + d);
                },
                success: function(d){
                    // Process data for hidden JS
                    var e = ELBP.process_data_eval(d);
                    if (ELBP.tempData != null){
                        d = ELBP.tempData;
                        ELBP.tempData = null;
                    }

                    // Run specified callback
                    if (callback){
                        callback(d);
                    }
                    
                    // Run default callback
                    ELBP.ajax_callback(d);
                    
                    if (e){
                        eval(e);
                    }

                }
            });

        },
        
        ajax_callback: function(data){
            ELBP.apply_summary_hover();
            ELBP.apply_draggable();
            elbp_load_stuff();
        },
        
        ajax_error: function(msg){
            $('#elbp_error_output').html('['+new Date() + '] ' + msg);
            $('#elbp_error_output').slideDown('slow');
        },

        pop: function(){
            ELBP.show('#elbp_blanket');
            $('#elbp_popup').show('scale', {}, 1000);
            
            // Prevent background scrolling
            $('body').css('overflow', 'hidden');
        },

        unpop: function(pluginName, pluginTitle){
                
            ELBP.save_state(pluginName);
            ELBP.hide('#elbp_blanket');
            if (ELBP.fast_hide == true){
                $('#elbp_popup').hide();
            } else {
                $('#elbp_popup').hide('scale', {}, 1000);
            }
            $('#elbp_popup').html('');
            if (pluginName != undefined && pluginTitle != undefined){
                ELBP.dock(pluginName, pluginTitle);
            }
            
            ELBP.reset_global_vars();
            
            {$reloadPluginsOnUnPop}
            
            // Allow background scrolling again
            $('body').css('overflow', 'auto');

        },
        
        save_state: function(pluginName){
        
            savedStates[pluginName] = $('#elbp_popup').clone(true, true);
            savedInputs[pluginName] = {};
            
            // Find inputs
            var inputs = $('#elbp_popup :input');
            $(inputs).each( function(){
                var tagName = $(this).prop('tagName');
                if (tagName != 'BUTTON' && $(this).attr('type') != 'submit'){
            
                    var id = $(this).attr('id');
                    var name = $(this).attr('name');
                    var val = $(this).val();
                    var chkd = ( $(this).is(':checked') ) ? true : false ;
                    
                    // First check if it's a texteditor, in which case get iframe contents
                    if ( $(this).hasClass('elbp_texteditor') ){
                        // Check for old iframe first
                        if ( $('#'+id+'_ifr').length > 0 ){
                            var content = $('#'+id+'_ifr').contents().find('html')[0];
                        }
                        else
                        {
                            var content = $('#'+id+'editable').html();
                        }
                        savedInputs[pluginName][name] = {value: content, checked: chkd};
                    }
                    // Only one value with that name so far: {value: value, checked: checked}
                    else if (savedInputs[pluginName][name] != undefined && savedInputs[pluginName][name] instanceof Object && savedInputs[pluginName][name][0] == undefined ){
                        var tmpVar = savedInputs[pluginName][name];
                        savedInputs[pluginName][name] = [ tmpVar, {value: val, checked: chkd} ];
                    } else if (savedInputs[pluginName][name] != undefined && savedInputs[pluginName][name] instanceof Object && savedInputs[pluginName][name][0] instanceof Object){
                        var cnt = Object.keys(savedInputs[pluginName][name]).length;
                        savedInputs[pluginName][name][cnt] = {value: val, checked: chkd};
                    } else {
                        savedInputs[pluginName][name] = {value: val, checked: chkd};
                    }
                    
                }
            } );

        },
        
        restore_state: function(pluginName){
            
            if (savedStates[pluginName] != undefined && savedStates[pluginName] != '' && $(savedStates[pluginName]).html() != ''){
                
                $('#elbp_popup').remove();
                            
                // Load inputs values back into saved html
                $.each( savedInputs[pluginName], function(key, o){

                        var v = o.value;
                        var fullHtml = undefined;
            
                        // First check for any texteditors as we need to do iframey stuff
                        var type = jQuery.type(v);
                        if (type == 'object' && $(v).find('body').length > 0){
                            v = $(v).find('body').html();
                        }
            
                        key = key.replace(/"/g, '&quot;');
                        var tmpVar = $('input[name="'+key+'"], textarea[name="'+key+'"], select[name="'+key+'"]', savedStates[pluginName]);
                        var tagName = $(tmpVar).prop('tagName');
                                                            
                        // If we found more than one with the same name, loop through
                        if (tmpVar.length > 1)
                        {   
                
                            for (var i = 0; i < tmpVar.length; i++)
                            {

                                v = o[i].value;

                                switch(tagName)
                                {
                                    case 'TEXTAREA':
                                        $(tmpVar[i]).text(v);
                                    break;
                                    case 'INPUT':
                                        $(tmpVar[i]).attr('value', v);
                                        // Checkbox/Radio
                                        if (o[i].checked == true){
                                            $(tmpVar[i]).attr('checked', 'checked');
                                        }
                                    break;

                                    default:
                                        $(tmpVar[i]).attr('value', v);
                                    break;
                                }

                            }

                        }
                        else
                        {
                        
                            switch(tagName)
                            {
                                case 'TEXTAREA':
            
                                    // Add the value into the textarea
                                    $(tmpVar).text(v);
            
                                break;
                                case 'SELECT':
                                    $('select[name="'+key+'"] option', savedStates[pluginName]).filter( function(){ 
                                            return $(this).val() == v 
                                    }).attr('selected', 'selected');
                                break;
                                case 'INPUT':
                                    $(tmpVar).attr('value', v);
                                    // Checkbox/Radio
                                    if (o.checked == true){
                                        $(tmpVar).attr('checked', 'checked');
                                    }
                                break;

                                default:
                                    $(tmpVar).attr('value', v);
                                break;
                            }
                            
                        }
                        

                } );
                
            
                // Moodle 2.7+ doesn't use iframes for the text editor
                if (moodleVersion >= 2014051200)
                {
            
                    // Remove the whole editor section and it should re apply on load
                    $(savedStates[pluginName]).find('.elbp_texteditor').siblings('div').remove();   
            
                }
            
            
                var html = $(savedStates[pluginName]).html();
            
            
                // Put the elbp_popup div around it again
                var wrapperDiv = $(savedStates[pluginName]).first();
                $(wrapperDiv).css('display', 'none');
                html = "<div id='"+$(wrapperDiv).attr('id')+"' style='"+$(wrapperDiv).attr('style')+"' class='"+$(wrapperDiv).attr('class')+"'>" + html + "</div>";
                        
                $('body').append( html );
            
                // Remove iframes from html content tbat we just loaded so we can apply text editor again
                $('body').find('iframe').each( function(){

                    // Get id of iframe
                    var id = $(this).attr('id');
            
                    // If it's an elbp field continue
                    if (id.substring(0, 7) == "elbpfe_"){
            
                        // Explode id into array by _
                        var explode = id.split("_");

                        // Set last item in arrya to "parent" instead of "ifr"
                        explode[ explode.length - 1 ] = "parent";
            
                        // Join the array back into a string for the span's id
                        var spanID = explode.join("_");
            
                        // Also get the id of the actual textarea
                        var editorID = spanID.replace("_parent", "");

                        // Remove the span parent
                        $(this).parents('#'+spanID).remove();
                        $('#'+editorID).css('display', 'block');
            
                    }

                });
            
                
                            
                
                ELBP.show('#elbp_blanket');
                $('#elbp_popup').show('explode', {}, 1000);
                
                setTimeout("elbp_load_stuff();", 1500);
                
                // Prevent background scrolling
                $('body').css('overflow', 'hidden');

            }
        },
        
        load: function(type, id){
            var params = {
                type: type,
                id: id,
                student: ELBP.studentID,
                course: ELBP.courseID
            }
            ELBP.ajax(0, 'load_template', params, function(d){
                $('#elbp_summary_boxes').html(d);
            }, function(d){
                $('#elbp_summary_boxes').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
            });
        },
        
        load_expanded: function(plugin, callAfter){
        
            var params = {
                pluginname: plugin,
                student: ELBP.studentID,
                course: ELBP.courseID
            }

            ELBP.ajax(0, 'load_expanded', params, function(d){
                $('#elbp_popup').html(d);
                if (callAfter){
                    callAfter();
                }
            }, function(d){
                ELBP.pop(); 
                $('#elbp_popup').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        set_group_class: function(el){
            $('.elbp_tabrow li').each(function(){
                $(this).attr('class', '');
            });
            $(el).parent().addClass('selected');
        },
        
        apply_summary_hover: function(){
        
            // When we hover over a summary box, if there is any overflow hidden, show the expand link
            $('.elbp_summary_box_wrapper').hover( 
                function(){
                    var height = $(this).height();
                    var scrollHeight = $(this)[0].scrollHeight;
                    if (scrollHeight > height){
                        $(this).next('.elbp_summary_box_footer').slideDown('slow');
                    }
                }
            );
            

        },
        
        load_image: function(url, el){
            setTimeout("$('#"+el+"').attr('src', '"+url+"');", 500); // This is in a timeout because some browsers don't show the loading gif
        },
        
        process_data_eval: function(data){
        
            var pat = /\[ELBP:JS\](.+?)\[\/ELBP:JS\]/g;
            var matches = data.match(pat);

            if(matches)
            {

                data = data.replace(pat, "");
                var toEval = "";
                for(var i=0; i < matches.length; i++)
                {
                    matches[i] = matches[i].replace(/(\[ELBP:JS\]|\[\/ELBP:JS\])/g, "");
                    toEval += matches[i];
                }

                ELBP.tempData = data;
                return toEval;

            }

            return false;

        },
        
        apply_draggable: function(){
            
        },
        
        apply_tooltip: function(){
            $(document).ready(function(){
                $(".elbp_tooltip").tooltip({
                    content: function(){
                        return $(this).prop('title');
                    }
                });
            });
        },

        expand: function(el){
        
            var wrapperObj = $(el).siblings('.elbp_summary_box_wrapper')[0];
                        
            var scrollHeight = wrapperObj.scrollHeight + 10;
            $(wrapperObj).animate({height: scrollHeight}, 300);
            
            // Change expand link to contract
            $(el).attr('onclick', 'ELBP.contract(this);return false;');
            $(el).attr('title', '{$string['contract']}');
            var img = $(el).children('img');
            $(img).attr('src', $(img).attr('src').replace('switch_plus', 'switch_minus'));
        },
        
        contract: function(el){
        
            var max_height = 225;
            
            var wrapperObj = $(el).siblings('.elbp_summary_box_wrapper')[0];
            
            $(wrapperObj).animate({height: max_height}, 300);

            $(el).slideUp('slow');
            
            // Change expand link to expand
            $(el).attr('onclick', 'ELBP.expand(this);return false;');
            $(el).attr('title', '{$string['expand']}');
            var img = $(el).children('img');
            $(img).attr('src', $(img).attr('src').replace('switch_minus', 'switch_plus'));

        },

        resize_popup: function(){
        
            // Height & Width of popup so we can use explode effect
            var screenWidth = ($(window).width() / 100) * 90;
            var screenHeight = ($(window).height() / 100) * 90;
            $('#elbp_popup').css('height', screenHeight);
            $('#elbp_popup').css('width', screenWidth);

        },
        
        set_view_link: function(el, sub){
        
            if (sub !== undefined){
                $('.elbp_view_link_'+sub).removeClass('selected');
            } else {
                $('.elbp_view_link').removeClass('selected');
            }
            
            if (el == undefined) return;
            $(el).parent().addClass('selected');

        },

        test_mis_connection: function(type, host, user, pass, db, id){
        
            var params = {
                type: type,
                host: host,
                user: user,
                pass: pass,
                db: db
            }
            
            var el = '#elbp_config_test_conn';
            if (id != undefined){
                el += '_'+id;
            }
            
            ELBP.ajax(0, "test_mis_connection", params, function(d){
                $(el).html(d);
            }, function(){
                $(el).html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },
        
        confirm_submit: function(){
            return window.confirm("{$string['areyousuredelete']}");
        },
        
        dialogue: function(title, content, opt){
            
            $('#elbp_dialogue div').html(content);

            var options = {
                    title: title,
                    autoOpen: true,
                    autoResize: true,
                    show: "blind",
                    hide: "blind",
                    width: 600,
                    height: 250,
                    stack: true,
                    open: function() {
                        $('.ui-button').removeClass('ui-state-focus');
                    }
            }
            
            if (opt != undefined){
                $.each(opt, function(ind, val){
                    options[ind] = val;
                });        
            }

            $('#elbp_dialogue').dialog(options);

        },
        
        browser_supports: function(inputType){
            var testInput = document.createElement('input');
            try {
                testInput.type = inputType;
            } catch (e){}
            return (testInput.type === inputType); 
        },
        
        toggle_image: function(img, el){
            $(el).attr('src', img);
        },

        dock: function(plugin, pluginTitle){
            
            if (!$('#docked_'+plugin)[0]){
            
                if (dockPosition == 'bottom'){
                    $('#elbp_dock_list').append('<li id="docked_'+plugin+'"><a href="#" onclick="ELBP.load_from_dock(\''+plugin+'\');return false;" class="dock_plugin_name" title="{$string['loadsavedstate']}: '+pluginTitle+'">'+pluginTitle+'</a> <a href="#" onclick="ELBP.undock(\''+plugin+'\');return false;" class="dock_plugin_close"><img src="{$CFG->wwwroot}/blocks/elbp/pix/close_tiny.png" alt="img" title="{$string['undock']}: '+pluginTitle+'" /></a></li>');
                }
                    
                else if (dockPosition == 'left'){
                    var img = pluginIcons[plugin];
                    $('#elbp_dock_list').append('<li id="docked_'+plugin+'"><a href="#" onclick="ELBP.load_from_dock(\''+plugin+'\');return false;" class="dock_plugin_name" title="{$string['loadsavedstate']}: '+pluginTitle+'"><img src="'+img+'" alt="'+pluginTitle+'" /></a><br><a href="#" onclick="ELBP.undock(\''+plugin+'\');return false;" class="dock_plugin_close"><img src="{$CFG->wwwroot}/blocks/elbp/pix/close_tiny.png" alt="img" title="{$string['undock']}: '+pluginTitle+'" /></a></li>');
                }
                    
            }
                
        },
        
        undock: function(plugin)
        {
            $('#docked_'+plugin).remove();
            savedStates[plugin] = undefined;
            
            // If there is an expanded view loaded and it's this plugin we are undocking, unpop it first
            if ($('#elbp_popup_header_plugin_'+plugin)[0]){
                ELBP.unpop();
            }
        },
        
        load_from_dock: function(plugin)
        {
            
            // If there is an expanded view already and it is the plugin we are trying to restore, do not save its state
            if ($('#elbp_popup_header_plugin_'+plugin)[0] == undefined)
            {
                // If there is an expanded view laoded, dock that
                if ($('.elbp_popup_header')[0]){
                    ELBP.fast_hide = true;
                    $('#close_expanded_view').click();
                    ELBP.fast_hide = false;
                }
            }

            // if there is a saved state use that
            if (savedStates[plugin] != undefined && savedStates[plugin] != '' && $(savedStates[plugin]).html() != ''){
                ELBP.restore_state(plugin);
            } else {
                ELBP.load_expanded(plugin);
            }

        },

        // Reset various global variables that may have been set at some point
        reset_global_vars: function(){
        
            ELBP.Targets.loaded_from = false;

        },

        // Load users into the select menu for the switch user bars
        switch_users: function(param){
        
            $('#switch_user_users').find('option').remove();
            $('#switch_user_users').css('display', 'none');
        
            if (param == ''){
                return false;
            }

            var params = { action: "load_users", param: param };
            ELBP.ajax(0, "switch_user", params, function(d){
            
                $('#switch_user_users').find('option').remove();
                $('#switch_user_users').append('<option value=""></option>');
                eval(d);
                $('#switch_users_loading').html('');
                if ( $('#switch_user_users option').length > 1 ){
                    $('#switch_user_users').css('display', 'inline-block');    
                }

            }, function(){
                $('#find_other_user').remove();
                $('#switch_users_loading').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" style="height:10px;" />');
            });

        },
        
        // Switch the user
        switch_user: function(id, type){
        
            if (id == '' || id == ELBP.studentID) return false;
                        
            var url = window.location.href;
            
            url = url.replace(/id=[\d]+/g, "id="+id);
                
            if (url.indexOf('cID') > -1){
                url = url.replace(/cID=(.+)/g, "cID="+type);
            } else {
                url = url + "&cID="+type;
            }
                            
            window.location = url;
            
        },
                
        switch_search_user: function(search){
          
            if (search == '') return false;
                                
            var params = { action: "load_users", search: search };
            ELBP.ajax(0, "search_load_student", params, function(d){
            
                $('#switch_users_loading').html('');
                eval(d);

            }, function(){
                $('#switch_users_loading').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" style="height:10px;" />');
            });    
            
                
        },
        
        change_background: function(bg){
        
            var params = { image: bg };

            ELBP.ajax(0, "change_background", params, function(d){
                eval(d);
            });

        },
                
        my_settings: function(userID){
                
            var opt = {
                buttons: {
                    "Save": function() { 
                            $(this).dialog("close");
                            ELBP.save_my_settings(""+userID+"");
                    },
                    "Close": function() { 
                            $(this).dialog("close"); 
                    }
                },
                height: 400
            }

            var params = { student: ELBP.studentID, userID: userID }
                
            $('#mysettingsimg').attr('src', '{$CFG->wwwroot}/blocks/elbp/pix/loader.gif');

            ELBP.ajax(0, "load_my_settings", params, function(d){
                ELBP.dialogue('{$string['mysettings']}', d, opt);
                $('#mysettingsimg').attr('src', '{$CFG->wwwroot}/blocks/elbp/pix/icons/cog.png');
            });
                
        },
                
        save_my_settings: function(userID){
          
            if (userID == undefined || userID == "undefined" || userID == ""){
                var params = { data: $('#my_settings').serialiseObject() }
            } else {
                var params = { data: $('#my_settings').serialiseObject(), userID: userID }
            }
                                
            ELBP.ajax(0, "save_my_settings", params, function(d){
                location.reload(true);
            });
                
        },
                

        // Apply colour picker when the browser does not support color inputs
        apply_colour_picker: function(){
        
            if (!ELBP.browser_supports('color'))
            {
                
                $('input[type="color"]').each( function() {
                    $(this).minicolors();
                });

            }
            
        },
        
        course_picker: {
        
            // Return all courses in given cat as <option>s for the course_picker form element
            choose_category: function(catID, el, use){
            
                var params = { action: "choose_category", catID: catID, use: use };

                ELBP.ajax(0, "course_picker", params, function(data){
                    $($(el).siblings('#category_picker_pick_courses').children('input')[0]).val('');
                    $($(el).siblings('#category_picker_pick_courses').children('select')[0]).html(data);
                    $(el).siblings('#category_picker_pick_courses').css('display', 'block');
                });

            },
            
            search_course: function(search, el, use){
                        
                var catID = $($($(el).parents()[1]).children()[0]).val();
                var params = { action: "search_courses", catID: catID, search: search, use: use };
                ELBP.ajax(0, "course_picker", params, function(data){
                    $($(el).siblings('select')[0]).html(data);
                });

            },
            
            add: function(el){
            
                var parents = $(el).parents()[0];
                var searchdiv = $(parents).siblings('.elbp_course_picker_search_div')[0];
                var coursediv = $(searchdiv).children('#category_picker_pick_courses')[0];
                var select = $(coursediv).children('select.course_list')[0];
                var courses = $(select).val();
                
                if (courses != null){
                    
                    var options = $(select).children('option');
                    var coursenames = new Array();
                    
                    $.each(options, function(k,v){
                        coursenames[v.value] = v.innerHTML;
                    });

                    var chosendiv = $(parents).siblings('.elbp_course_picker_chosen_div')[0];
                    var addselect = $(chosendiv).children('select.courseholder')[0];
                    var hiddeninputs = $(addselect).siblings('.coursepickerhiddeninputs')[0];
                    var fieldname = $(hiddeninputs).attr('fieldname');

                    var addedoptions = $(addselect).children('option');
                    var addedoptionvalues = new Array();
                    $.each(addedoptions, function(k,v){
                        addedoptionvalues.push(v.value);
                    });
                    
                                        
                    $.each(courses, function(k, v){

                        // Check not already in chosen select list
                        if ( $.inArray(v, addedoptionvalues) === -1 ){
                        
                            // Append to it
                            $(addselect).append('<option value="'+v+'">'+coursenames[v]+'</option>');

                            // Also add in a hidden input with the value, because
                            $(hiddeninputs).append('<input type="hidden" name="'+fieldname+'[]" value="'+v+'" />');

                        }
                        
                    });
                
                }

            },
            
            remove: function(el){
            
                var parents = $(el).parents()[0];
                var chosendiv = $(parents).siblings('.elbp_course_picker_chosen_div')[0];
                var addselect = $(chosendiv).children('select.courseholder')[0];
                var hiddeninputs = $(addselect).siblings('.coursepickerhiddeninputs')[0];
                var fieldname = $(hiddeninputs).attr('fieldname');
                    
                var courses = $(addselect).val();
                
                if (courses != null){
                
                    $.each(courses, function(k,v){
                        
                        // Remove any option in the select with that value
                        $(addselect).children('option[value="'+v+'"]').remove();
                        
                        // Remove hidden input
                        $(hiddeninputs).children('input[value="'+v+'"]').remove();

                    });

                }

            }

        },
        

        user_picker: {
        
            // Search users
            search_user: function(search, el){
                        
                var catID = $($($(el).parents()[1]).children()[0]).val();
                var params = { action: "search_users", search: search };
                ELBP.ajax(0, "user_picker", params, function(data){
                    $($(el).siblings('select')[0]).html(data);
                });

            },
            
            add: function(el){
            
                var parents = $(el).parents()[0];
                var searchdiv = $(parents).siblings('.elbp_user_picker_search_div')[0];
                var select = $(searchdiv).children('.user_list')[0];
                var users = $(select).val();
                
                if (users != null){
                    
                    var options = $(select).children('option');
                    var usernames = new Array();
                    
                    $.each(options, function(k,v){
                        usernames[v.value] = v.innerHTML;
                    });

                    var chosendiv = $(parents).siblings('.elbp_user_picker_chosen_div')[0];
                    var addselect = $(chosendiv).children('.userholder')[0];
                    var hiddeninputs = $(addselect).siblings('.userpickerhiddeninputs')[0];
                    var fieldname = $(hiddeninputs).attr('fieldname');

                    var addedoptions = $(addselect).children('option');
                    var addedoptionvalues = new Array();
                    $.each(addedoptions, function(k,v){
                        addedoptionvalues.push(v.value);
                    });
                    
                                        
                    $.each(users, function(k, v){

                        // Check not already in chosen select list
                        if ( $.inArray(v, addedoptionvalues) === -1 ){
                        
                            // Append to it
                            $(addselect).append('<option value="'+v+'">'+usernames[v]+'</option>');

                            // Also add in a hidden input with the value, because
                            $(hiddeninputs).append('<input type="hidden" name="'+fieldname+'[]" value="'+v+'" />');

                        }
                        
                    });
                
                }

            },
            
            remove: function(el){
            
                var parents = $(el).parents()[0];
                var chosendiv = $(parents).siblings('.elbp_user_picker_chosen_div')[0];
                var addselect = $(chosendiv).children('.userholder')[0];
                var hiddeninputs = $(addselect).siblings('.userpickerhiddeninputs')[0];
                var fieldname = $(hiddeninputs).attr('fieldname');
                    
                var users = $(addselect).val();
                
                if (users != null){
                
                    $.each(users, function(k,v){
                        
                        // Remove any option in the select with that value
                        $(addselect).children('option[value="'+v+'"]').remove();
                        
                        // Remove hidden input
                        $(hiddeninputs).children('input[value="'+v+'"]').remove();

                    });

                }

            }

        },



        validate_form: function(form){

            var data = $(form).serialiseObject();
            var errs = 0;
            var formID = $(form).attr('id');
            
            $( '#' + formID + ' input, #'+formID+' select, #'+formID+' textarea').removeClass('elbp_red');
            $('span.elbp_error').remove();
            
            var firstEl = '';

            // Loop through the data elements provided and check if they have a "validation" attribute
            $( '#' + formID + ' input, #'+formID+' select, #'+formID+' textarea').each( function(){
            
                var input = $(this);
                var value = $(this).val();
                value = $.trim(value);
                
                // If it's a Moodle Text Editor, the value is in the iframe, so we need to get that instead
                if ( $(this).hasClass('elbp_texteditor') ){
                
                    // Moodle 2.6 and lower
                    var ifr = $('#' + $(this).attr('id') + '_ifr');
                
                    // Moodle 2.7 and above
                    var ifr27 = $('#' + $(this).attr('id') + 'editable');
                
                    if (ifr.length > 0){
                        // Using the .text() not .html() as otherwise it will bring back empty tags like:
                        // "<p><br data-mce-bogus="1"></p>"
                        value = ifr.contents().find('body').text(); 
                    } else if ( ifr27.length > 0 ) {
                
                        value = ifr27.html();
                
                        // If the value is just a break in a paragraph (by default), clear that
                        if (value == '<p><br></p>'){
                            value = '';
                        }
                
                    } else {
                        // Plain text area
                        value = $(this).val();
                    }
                
                    value = $.trim(value);
                
                }
                
                if ( $(this).attr('validation') != undefined ){
                
                    // Implode into array and check all of the types
                    var validation = $(this).attr('validation').split(',');
                    var type = $(this).attr('type');
                    var name = $(this).attr('name');
    
                    $.each(validation, function(i, v){       

                        switch(v)
                        {
                            
                            case 'NOT_EMPTY':
                            case 'REQUIRED':
                
                                if (type == 'radio' || type == 'checkbox')
                                {

                                    var others = $('#'+formID+' input[name="'+name+'"]');
                                    var cnt = others.length;
                                    var ticked = 0;
                                    $.each(others, function(){
                                        if ( $(this).prop('checked') == true ){
                                            ticked++;
                                            return (false);
                                        }
                                    });
                                
                                    if (ticked == 0){
                                        if (firstEl == '') firstEl = input;
                                        errs++;
                                        // Only add it to the first checkbox/radio of this name, not all
                                        $($(others)[0]).siblings('span.elbp_error').remove();
                                        $($(others)[0]).addClass('elbp_red');
                                        $($(others)[0]).before('<span class="elbp_error"><br>{$string['validation:required:tickbox']}<br></span> ');
                                    }
                
                                }
                                else
                                {
                                    var pat = /.+/;
                                    if (value.match(pat) == null){
                                        if (firstEl == '') firstEl = input;
                                        errs++;
                                        $(input).addClass('elbp_red');
                                        $(input).after('<span class="elbp_error"><br>{$string['validation:required']}</span>');
                                    }
                                }
                
                                
                            break;
                            
                            case 'TEXT_ONLY':
                                var pat = /[^a-z ]/i;
                                    if (value.match(pat) != null || value == ''){
                                        if (firstEl == '') firstEl = input;
                                        errs++;
                                        $(input).addClass('elbp_red');
                                        $(input).after('<span class="elbp_error"><br>{$string['validation:textonly']}</span>');
                                    }
                            break;
                            
                            case 'NUMBERS_ONLY':
                                var pat = /^[0-9]+\.?[0-9]*$/i;
                                    if (value.match(pat) == null || value == ''){
                                        if (firstEl == '') firstEl = input;
                                        errs++;
                                        $(input).addClass('elbp_red');
                                        $(input).after('<span class="elbp_error"><br>{$string['validation:numbersonly']}</span>');
                                    }
                            break;
                                        
                            case 'ALPHANUMERIC_ONLY':
                                var pat = /[^0-9 a-z]/i;
                                    if (value.match(pat) != null || value == ''){
                                        if (firstEl == '') firstEl = input;
                                        errs++;
                                        $(input).addClass('elbp_red');
                                        $(input).after('<span class="elbp_error"><br>{$string['validation:alphanumericonly']}</span>');
                                    }
                            break;
                            
                            case 'EMAIL':
                                var pat = /^[a-z0-9_\.]+@[a-z0-9\.]+\.[a-z\.]{2,4}[a-z]{1}$/i;
                                   if (value.match(pat) == null || value == ''){
                                        if (firstEl == '') firstEl = input;
                                        errs++;
                                        $(input).addClass('elbp_red');
                                        $(input).after('<span class="elbp_error"><br>{$string['validation:email']}</span>');
                                   }                                 

                            break;
                            
                            case 'PHONE':
                                var pat = /^(\+\d{1,}\s?)?0\d{4}\s?\d{6}$/;
                                    if (value.match(pat) == null || value == ''){
                                        if (firstEl == '') firstEl = input;
                                        errs++;
                                        $(input).addClass('elbp_red');
                                        $(input).after('<span class="elbp_error"><br>{$string['validation:phone']}</span>');
                                    }
                            break;
                                        
                            case 'DATE':
                                 var pat = /^\d{2}\-\d{2}\-\d{4}$/        
                                 if (value.match(pat) == null || value == ''){
                                        if (firstEl == '') firstEl = input;
                                        errs++;
                                        $(input).addClass('elbp_red');
                                        $(input).after('<span class="elbp_error"><br>{$string['validation:date']}</span>');
                                    }       
                                        
                            break;
                                        
                            case 'URL':
                                var pat = /(((http|ftp|https):\/{2})+(([0-9a-z_-]+\.)+(aero|asia|biz|cat|com|coop|edu|gov|info|int|jobs|mil|mobi|museum|name|net|org|pro|tel|travel|ac|ad|ae|af|ag|ai|al|am|an|ao|aq|ar|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|bi|bj|bm|bn|bo|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|cr|cu|cv|cx|cy|cz|cz|de|dj|dk|dm|do|dz|ec|ee|eg|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|in|io|iq|ir|is|it|je|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mg|mh|mk|ml|mn|mn|mo|mp|mr|ms|mt|mu|mv|mw|mx|my|mz|na|nc|ne|nf|ng|ni|nl|no|np|nr|nu|nz|nom|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|ps|pt|pw|py|qa|re|ra|rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sj|sk|sl|sm|sn|so|sr|st|su|sv|sy|sz|tc|td|tf|tg|th|tj|tk|tl|tm|tn|to|tp|tr|tt|tv|tw|tz|ua|ug|uk|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|yu|za|zm|zw|arpa)(:[0-9]+)?((\/([~0-9a-zA-Z\#\+\%@\.\/_-]+))?(\?[0-9a-zA-Z\+\%@\/&\[\];=_-]+)?)?))\b/im;
                                if (value.match(pat) == null || value == ''){
                                    if (firstEl == '') firstEl = input;
                                    errs++;
                                    $(input).addClass('elbp_red');
                                    $(input).after('<span class="elbp_error"><br>{$string['validation:url']}</span>');
                                }
                            break;

                        }

                    });

                }

            } );

            if (errs > 0){
                // Focus on first el
                if (firstEl != ''){
                    $(firstEl).focus();
                }
                return false;
            }

            return true;


        },
                                        
                                        
        execute: function(action){
            
            savedCommands.push(action);
            commandPointer = savedCommands.length;
                                        
            if (action == 'clear'){
                $('#cmd_output').html('');
                $('#cmd_input input').val('');                           
                return true;                        
            }                            
                                      
            if (action === 'quit' || action === 'exit'){
                $('#cmd_input input').val('');         
                $('#elbp_admin_blanket').hide();
                $('body').css('overflow', 'auto');
                return true;
            }
                                        
            var params = {action: action};                            
                                  
            $('#cmd_input input').val('');                            
                                        
            ELBP.ajax(0, "execute", params, function(d){
                $('#cmd_output').append(d + '<br>');
                $(function() {
                    var height = $('#cmd_output')[0].scrollHeight;
                    $('#cmd_output').scrollTop(height);
                });                        
            });
                                        
        },


        load_helper: function(name){
          
            window.open('{$CFG->wwwroot}/blocks/elbp/help.php?f='+name,'targetWindow','toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=800,height=600');
                                        
        },



  
        // STUDENT PROFILE PLUGIN

        StudentProfile: {

            // Show the edit link on the student details & student info boxes - on hover
            edit_link : function(parent, display){
                $(parent).find('.elbp_studentprofile_edit_link').css('display', display);
            },
            
            edit : function(section){
                var hide = '.elbp_studentprofile_'+section+'_simple';
                var show = '.elbp_studentprofile_'+section+'_edit';
                var link = '#elbp_studentprofile_'+section+'_edit_link';
                
                ELBP.hide(hide);
                ELBP.show(show);
                $(link).text('[{$string['save']}]');
                $(link).attr('onclick', 'ELBP.StudentProfile.save("'+section+'");return false;');
                
                // Cancel link
                $(link).after('<a href="#" id="cancel_link_'+section+'" onclick="ELBP.StudentProfile.edit_return(\''+section+'\');$(this).remove();return false;">[{$string['cancel']}]</a>');
                
            },
            
            edit_return : function(section){
            
                var hide = '.elbp_studentprofile_'+section+'_edit';
                var show = '.elbp_studentprofile_'+section+'_simple';
                var link = '#elbp_studentprofile_'+section+'_edit_link';
                                
                ELBP.hide(hide);
                ELBP.show(show);
                $(link).text('[{$string['edit']}]');
                $(link).attr('onclick', 'ELBP.StudentProfile.edit("'+section+'");return false;');

            },
            
            return_details : function(params){
            
                // Set the simple values to the same that were just submitted in the edit form
                if (params){
                    $.each(params, function(key, val){
                        var element = '#elbp_studentprofile_details_simple_'+key;
                        $(element).text(val);
                    });
                }

            },
            
            return_info : function(info){
                $('#elbp_studentprofile_info_simple').html(info);
            },

            save : function(section){
            
                if (section == "details")
                {
                    
                    var values = $('.elbp_studentprofile_details_edit_values');
                    var params = {};
                    $.each(values, function(){
                        var key = this.name;
                        params[key] = this.value;
                    });
                    
                    params.studentID = ELBP.studentID;
                    
                    ELBP.StudentProfile.update_details(params);

                }
                else if(section == "info")
                {
                    
                    // Are we using iframes or not? Moodle 2.7+ doesn't, but previous versions do
                    if ($('#student_info_textarea_ifr').length > 0){
                        var info = $('#student_info_textarea_ifr').contents().find('body').html();
                    } else if ( $('#student_info_textareaeditable').length > 0 ) {
                        var info = $('#student_info_textareaeditable').html();
                    } else {
                        var info = $('#student_info_textarea').val();
                    }
                
                    var params = {
                        studentID: ELBP.studentID,
                        info: info,
                        element: 'student_info_textarea'
                    };
                    
                    ELBP.StudentProfile.update_info(params);

                }
                
                $('#cancel_link_'+section).remove();

            },
            
            update_details : function(params){
                ELBP.ajax("StudentProfile", "update_details", params, function(d){
                    ELBP.StudentProfile.edit_return("details");
                    ELBP.StudentProfile.return_details(params);
                    eval(d);
                    $('#student_profile_output').html('');
                }, function(){
                    $('#student_profile_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
            },
            
            update_info : function(params){
                ELBP.ajax("StudentProfile", "update_info", params, function(){
                    ELBP.StudentProfile.edit_return("info");
                    ELBP.StudentProfile.return_info(params.info);
                    $('#student_profile_output').html('');
                }, function(){
                    $('#student_profile_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
            }


        },


        Attendance: {
        
            // Load a display type, e.g. tabular, bar chart, etc...
            load_display: function(type, el){
                var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax("Attendance", "load_display_type", params, function(d){
                    $('#elbp_attendance_content').html(d);
                    ELBP.set_view_link(el);
                }, function(d){
                    $('#elbp_attendance_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
            }

        },
        
        BKSB: {
        
            // Load a display type
            load_display: function(type, el){
                var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax("elbp_bksb", "load_display_type", params, function(d){
                    $('#elbp_bksb_content').html(d);
                    ELBP.set_view_link(el);
                }, function(d){
                    $('#elbp_bksb_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
            }

        },
                    
        BKSBLive: {
        
            // Load a display type
            load_display: function(type, el){
                var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax("elbp_bksblive", "load_display_type", params, function(d){
                    $('#elbp_bksb_content').html(d);
                    ELBP.set_view_link(el);
                }, function(d){
                    $('#elbp_bksb_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
            },
                    
            load_plan: function(subject, level, el){
             
                var params = { subject: subject, level: level, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax("elbp_bksblive", "load_learning_plan", params, function(d){
                    $('#bksblive_topics_content').html(d);
                    ELBP.set_view_link(el, 'sub');
                }, function(d){
                    $('#bksblive_topics_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
                    
            }

        },
        
        Timetable: {
        
            // Load a display type
            load_display: function(type, el){
                var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax("elbp_timetable", "load_display_type", params, function(d){
                    $('#elbp_timetable_content').html(d);
                    ELBP.set_view_link(el);
                }, function(d){
                    $('#elbp_timetable_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
            },

            // Load colour setting dialogue box
            load_colour_settings: function(){

                var opt = {
                    buttons: {
                                "Save": function() { 
                                        ELBP.Timetable.save_colour_settings();
                                        $(this).dialog("close");
                                },
                                "Close": function() { 
                                        $(this).dialog("close"); 
                                }
                            },
                    height: 400
                }
                                
                var params = { student: ELBP.studentID }

                ELBP.ajax("elbp_timetable", "load_colours_form", params, function(d){
                    ELBP.dialogue('{$string['changecolours']}', d, opt);
                });
                

            },
            
            save_colour_settings: function(){
                        
                var colours = {
                    MON: $('#monday_colour').val(),
                    TUE: $('#tuesday_colour').val(),
                    WED: $('#wednesday_colour').val(),
                    THU: $('#thursday_colour').val(),
                    FRI: $('#friday_colour').val(),
                    SAT: $('#saturday_colour').val(),
                    SUN: $('#sunday_colour').val(),
                    student: ELBP.studentID
                }
                                                  
                ELBP.ajax("elbp_timetable", "save_colours_form", colours, function(){
                
                    // Update all the colours on the screen
                    $('.elbp_timetable_monday').css('background-color', colours['MON']);
                    $('.elbp_timetable_monday a').css('background-color', colours['MON']);
                    ELBP.Timetable.update_font_colour('.elbp_timetable_monday', colours['MON']);
                    
                    $('.elbp_timetable_tuesday').css('background-color', colours['TUE']);
                    $('.elbp_timetable_tuesday a').css('background-color', colours['TUE']);
                    ELBP.Timetable.update_font_colour('.elbp_timetable_tuesday', colours['TUE']);
                    
                    $('.elbp_timetable_wednesday').css('background-color', colours['WED']);
                    $('.elbp_timetable_wednesday a').css('background-color', colours['WED']);
                    ELBP.Timetable.update_font_colour('.elbp_timetable_wednesday', colours['WED']);
                    
                    $('.elbp_timetable_thursday').css('background-color', colours['THU']);
                    $('.elbp_timetable_thursday a').css('background-color', colours['THU']);
                    ELBP.Timetable.update_font_colour('.elbp_timetable_thursday', colours['THU']);
                    
                    $('.elbp_timetable_friday').css('background-color', colours['FRI']);
                    $('.elbp_timetable_friday a').css('background-color', colours['FRI']);
                    ELBP.Timetable.update_font_colour('.elbp_timetable_friday', colours['FRI']);
                    
                    $('.elbp_timetable_saturday').css('background-color', colours['SAT']);
                    $('.elbp_timetable_saturday a').css('background-color', colours['SAT']);
                    ELBP.Timetable.update_font_colour('.elbp_timetable_saturday', colours['SAT']);
                    
                    $('.elbp_timetable_sunday').css('background-color', colours['SUN']);
                    $('.elbp_timetable_sunday a').css('background-color', colours['SUN']);
                    ELBP.Timetable.update_font_colour('.elbp_timetable_sunday', colours['SUN']);
                    
                });

            },
            
            update_font_colour: function(cl, bg){

                ELBP.ajax("elbp_timetable", "get_font_colour", {background: bg}, function(d){
                    $(cl).css('color', d);
                    $(cl + ' a').css('color', d);
                });
                                
            },
            
            load_calendar: function(cal, link, params){
            
                if(typeof link != 'undefined' && link != null){
                    $('#elbp_tt_type li a').removeClass('sel');
                    $(link).addClass('sel');
                }
                
                var add = 0;
                var today = false;
                
                if(typeof params != 'undefined' && typeof params['add'] != 'undefined'){
                    if (params['add'] == 'today'){
                        today = true;
                    }
                    else
                    {
                        add = params['add'];
                    }
                }

                ELBP.ajax("elbp_timetable", "load_calendar", {student: ELBP.studentID, type: cal, format: true, add: add}, function(d){
                    $('#elbp_tt_content').html(d);
                    
                    // If we have defined the add and it's 0, then we've pressed "Today" to colour today when we're done
                    if (today){
                        $('.elbp_today').css('background-color', '#FBFFB7');
                        $('.elbp_today').css('color', '#000000');
                    }
                    
                    // Apply tooltips if applicable
                    ELBP.apply_tooltip();
                    
                }, function(d){
                    $('#elbp_tt_content').html('<div class="elbp_centre"><img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" /></div>');
                });

            },
            
            week: function(add){
                ELBP.Timetable.load_calendar('week', null, {add: add});
            },
            
            day: function(add){
                ELBP.Timetable.load_calendar('day', null, {add: add});
            },
            
            month: function(add){
                ELBP.Timetable.load_calendar('month', null, {add: add});
            },
            
            year: function(add){
                ELBP.Timetable.load_calendar('year', null, {add: add});
            },
            
            popup_class_info: function(id, day){
            
                var info = $('#class_info_'+id).html();
                info = "<div class='day_number'>"+day+"</div>" +  info;
                ELBP.dialogue('{$string['lessoninfo']}', info);

            }

        },
        
        Targets: {
        
            loaded_type: false,

            // Load a display type
            load_display: function(type, el, callBack, loadedFrom, putInto){
                var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID, loadedFrom: loadedFrom, putInto: putInto }
                ELBP.ajax("Targets", "load_display_type", params, function(d){
                    $('#elbp_targets_content').html(d);
                    ELBP.set_view_link(el);
                    if (callBack != undefined){
                        callBack();
                    }
                }, function(d){
                    $('#elbp_targets_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
            },
            
            load_targets: function(statusID, targetID){
            
                ELBP.Targets.loaded_type = true;
                
                var runSetFalse = function(){
                    ELBP.Targets.loaded_type = false;
                };
                
            
                ELBP.load_expanded('Targets', function(){
                    ELBP.Targets.load_display(statusID, $('#statustab_'+statusID), function(){
                        if (targetID != undefined){
                            $('#target_content_'+targetID).show();
                            $('div#elbp_popup').scrollTop(0); // First we scroll right to the top
                            $('div#elbp_popup').animate({ scrollTop: ($('#elbp_target_id_'+targetID).offset().top - $('#elbp_target_id_'+targetID).height()) }, 2000);
                        }
                    });
                    runSetFalse();
                });

            },

            
            save_target: function(form, loadedFrom, putInto){
                
                if (putInto == ''){
                    putInto = undefined;
                }

                var data = form.serialiseObject();
                $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID, loadedFrom: loadedFrom, putInto: putInto});

                ELBP.ajax("Targets", "save_target", data, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_popup').scrollTop(0);
                    $('#new_target_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            },
            
            edit_target: function(id, loadedFrom, putInto){
            
                var params = { type: "edit", targetID: id, studentID: ELBP.studentID, courseID: ELBP.courseID, loadedFrom: loadedFrom, putInto: putInto }
                ELBP.ajax("Targets", "load_display_type", params, function(d){
                    $('#elbp_targets_content').html(d);
                }, function(d){
                    $('#elbp_targets_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            },
            
            delete_target: function(id){
            
                var confirm = window.confirm("{$string['areyousuredelete']}");
                if (confirm){    
                    var params = { type: "delete", targetID: id, studentID: ELBP.studentID, courseID: ELBP.courseID }
                    ELBP.ajax("Targets", "delete_target", params, function(d){
                        eval(d);
                    });
                }

            },
            
            add_comment: function(id, comment, parentID){
            
                var params = { targetID: id, comment: comment, parentID: parentID };
                ELBP.ajax("Targets", "add_comment", params, function(d){
                    eval(d);
                });

            },
            
            delete_comment: function(targetID, commentID){
            
                var params = { targetID: targetID, commentID: commentID };
                ELBP.ajax("Targets", "delete_comment", params, function(d){ 
                    eval(d);
                });

            },
            
            update_status: function(targetID, statusID){
            
                var params = { targetID: targetID, statusID: statusID };
                ELBP.ajax("Targets", "update_status", params, function(d){ 
                    eval(d);
                });

            },
                
            forward_email: function(targetID, usernames){
                
                $('#email-success-'+targetID).hide();
                $('#email-error-'+targetID).hide();
                $('#email-to-addr-'+targetID).hide();
                $('#email-loading-img-'+targetID).show();
                var params = { targetID: targetID, usernames: usernames };
                ELBP.ajax("Targets", "forward_email", params, function(d){ 
                    eval(d);
                    $('#email-loading-img-'+targetID).hide();
                    $('#email-to-addr-'+targetID).show();
                });
                
            },
                
            filter: function(filterBy){
                
                // Firstly show them all
                $('.elbp_target').show();
                                
                // Now, if the filter was defined, hide others
                if (filterBy !== undefined && filterBy != ""){
                                    
                    $('.elbp_target').each( function(){
                        
                        var f = $(this).attr('filter-attribute');
                        if (f != filterBy){
                            $(this).hide();
                        }   
   
                    } );
                
                }
                
            }
            

        },
        
        
        Tutorials: {
        
            // Load a display type
            load_display: function(type, el){
                var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax("Tutorials", "load_display_type", params, function(d){
                    $('#elbp_tutorials_content').html(d);
                    ELBP.set_view_link(el);
                }, function(d){
                    $('#elbp_tutorials_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
            },
            
            // Load a particular tutorial from thye summary view
            load_tutorial: function(tutorialID){
            
                ELBP.load_expanded('Tutorials', function(){
                    
                    setTimeout("$('#tutorial_content_"+tutorialID+"').slideDown();$('div#elbp_popup').scrollTop(0);$('div#elbp_popup').animate({ scrollTop: ($('#elbp_tutorial_"+tutorialID+"').offset().top - $('#elbp_tutorial_"+tutorialID+"').height()) }, 2000);", 1000);
                    
                });

            },
            
            // Load up the Add Target form, then when we submit it, bring us back to the TUtorial form and add that target into it as well as restoring the state we left in
            add_target: function(pluginTitle){
            
                ELBP.save_state('Tutorials');
                ELBP.dock("Tutorials", pluginTitle);
                ELBP.Targets.loaded_from = "Tutorials";
                ELBP.load_expanded('Targets', function(){
                    ELBP.Targets.load_display('new', undefined, undefined, 'Tutorials', 'elbp_tutorial_new_targets');
                });

            },
            
            // Edit a target in a tutoria;
            edit_target: function(id, pluginTitle){
            
                ELBP.save_state('Tutorials');
                ELBP.dock("Tutorials", pluginTitle);
                ELBP.Targets.loaded_from = "Tutorials";
                
                ELBP.load_expanded('Targets', function(){
                    ELBP.Targets.edit_target(id, 'Tutorials', 'elbp_tutorial_new_targets');
                });

            },
                    
            add_existing_target: function(id){
                    
                $('#loading_add_existing_target').show();
                var params = { targetID: id, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax("Tutorials", "get_target_row", params, function(d){
                    $('#elbp_tutorial_new_targets').append(d);
                    $('#loading_add_existing_target').hide();                    
                });
                    
            },
            
            // Remove a target from the tutorial
            remove_target: function(targetID, tutorialID){
            
                var confirm = window.confirm("{$string['areyousureremovetarget']}");
                if (confirm){
                
                    // If no tutorial ID specified, must be new tutorial that hasn't been saved yet, so just remove from screen and it won't get added to tutorial
                    if (tutorialID == undefined){
                        $('#new_added_target_id_'+targetID).remove();
                    }
                    
                    var params = { studentID: ELBP.studentID, courseID: ELBP.courseID, tutorialID: tutorialID, targetID: targetID };
                    
                    ELBP.ajax("Tutorials", "remove_target", params, function(d){
                        eval(d);
                    });

                }
            
                

            },
            
            save_tutorial: function(form){
            
                var data = form.serialiseObject();
                $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});
                
                ELBP.ajax("Tutorials", "save_tutorial", data, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_popup').scrollTop(0);
                    $('#new_tutorial_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            },
            
            edit_tutorial: function(id){
            
                var params = { type: "edit", tutorialID: id, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax("Tutorials", "load_display_type", params, function(d){
                    $('#elbp_tutorials_content').html(d);
                }, function(d){
                    $('#elbp_tutorials_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            },
            
            delete_tutorial: function(id){
            
                var confirm = window.confirm("{$string['areyousuredelete']}");
                if (confirm){
                    var params = { studentID: ELBP.studentID, courseID: ELBP.courseID, tutorialID: id };
                    ELBP.ajax("Tutorials", "delete_tutorial", params, function(d){
                        eval(d);
                    });
                }

            },
                
            auto_save:  function(){
                
                var data = $('#new_tutorial_form').serialiseObject();
                $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID, auto: 1});
                
                ELBP.ajax("Tutorials", "save_tutorial", data, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_popup').scrollTop(0);
                    $('#new_tutorial_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
                
            }

        },
        

        Attachments: {
        
            // Load a display type
            load_display: function(type, el){
                var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax("Attachments", "load_display_type", params, function(d){
                    $('#elbp_attachments_content').html(d);
                    ELBP.set_view_link(el);
                }, function(d){
                    $('#elbp_attachments_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
            },           
            
            delete_attachment: function(id){
            
                var confirm = window.confirm('{$string['areyousuredelete']}');

                if (confirm){
                    var data = {id: id, studentID: ELBP.studentID, courseID: ELBP.courseID};

                    ELBP.ajax("Attachments", "delete", data, function(d){
                        eval(d);
                    }, function(d){
                        $('#attachments_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                    });
                }

            },
            
            add_comment: function(id, comment, parentID){
            
                var params = { id: id, comment: comment, parentID: parentID };
                ELBP.ajax("Attachments", "add_comment", params, function(d){
                    eval(d);
                });

            },
            
            delete_comment: function(id){
            
                var params = { id: id };
                ELBP.ajax("Attachments", "delete_comment", params, function(d){
                    eval(d);
                });

            }


        },
        

        CourseReports: {
        
            // Load a display type
            load_display: function(type, el, courseIDForReport, reportID, callBack){
                var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID, courseIDForReport: courseIDForReport, reportID: reportID }
                ELBP.ajax("CourseReports", "load_display_type", params, function(d){
                    $('#elbp_course_reports_content').html(d);
                    ELBP.set_view_link(el);
                    if (callBack){
                        callBack();
                    }
                }, function(d){
                    $('#elbp_course_reports_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
            },     
            
            save: function(data){
            
                $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});

                ELBP.ajax("CourseReports", "save_report", data, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_popup').scrollTop(0);
                    $('#new_course_report_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            },
            
            load_report: function(cID, id)
            {
                
                var tmpID = ELBP.courseID;
                ELBP.courseID = cID;
                ELBP.load_expanded('CourseReports');
                                
                ELBP.courseID = tmpID;
                
            },
            
            load_report_quick: function(cid, id){
                
                ELBP.CourseReports.load_display('course', undefined, cid, undefined, function(){
                    $('div#elbp_popup').scrollTop(0); // First we scroll right to the top
                    var top = $('#course_report_content_'+id).offset().top - $('#course_report_content_'+id).height();
                    $('#course_report_content_'+id).slideDown();
                    $('div#elbp_popup').animate({ scrollTop: top }, 2000);
                });

            },

            delete_report: function(id){
                
                var confirm = window.confirm('{$string['areyousuredelete']}');
                
                if (confirm){

                    var params = {studentID: ELBP.studentID, courseID: ELBP.courseID, reportID: id};
                    ELBP.ajax("CourseReports", "delete_report", params, function(d){
                        eval(d);
                    });

                }

            },
            
            search: function(from, to){
            
                if (from == '' || to == '') return false;
                
                var params = {studentID: ELBP.studentID, courseID: ELBP.courseID, dateFrom: from, dateTo: to};

                ELBP.ajax("CourseReports", "search", params, function(d){
                    $('#elbp_periodical_output').html(d);
                }, function(d){
                    $('#elbp_periodical_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            },
            
            save_periodical: function(data){
            
                $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});
                
                ELBP.ajax("CourseReports", "save_periodical", data, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_periodical_saving_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            },
                    
            edit_periodical: function(id){
                            
                var params = {studentID: ELBP.studentID, courseID: ELBP.courseID, reportID: id};

                ELBP.ajax("CourseReports", "edit_periodical", params, function(d){
                    $('#elbp_periodical_output').html(d);
                }, function(d){
                    $('#elbp_periodical_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            },   
                
            delete_periodical: function(id){
                            
                var params = {studentID: ELBP.studentID, courseID: ELBP.courseID, reportID: id};

                ELBP.ajax("CourseReports", "delete_periodical", params, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_periodical_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            },      
                
            load_periodical: function(id){
             
                ELBP.load_expanded('CourseReports', function(){
                    $(document).ready( function(){
                        setTimeout("ELBP.CourseReports.load_display('periodical_report', false, false, "+id+");", 2500);
                    } );
                });
                    
            }
            

        },
        
        Comments: {
        
            // Load a display type
            load_display: function(type, el){
                var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax("Comments", "load_display_type", params, function(d){
                    $('#elbp_comments_content').html(d);
                    ELBP.set_view_link(el);
                }, function(d){
                    $('#elbp_comments_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
            },    
            
            save: function(form){
                
                var data = form.serialiseObject();
                $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});
                
                ELBP.ajax("Comments", "save_comment", data, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_popup').scrollTop(0);
                    $('#new_comment_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            },
            
            edit_comment: function(id){
            
                var params = { type: "edit", commentID: id, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax("Comments", "load_display_type", params, function(d){
                    $('#elbp_comments_content').html(d);
                }, function(d){
                    $('#elbp_comments_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            },
            
            delete_comment: function(id){
            
                var confirm = window.confirm("{$string['areyousuredelete']}");
                if (confirm){
                    var params = { studentID: ELBP.studentID, courseID: ELBP.courseID, commentID: id };
                    ELBP.ajax("Comments", "delete_comment", params, function(d){
                        eval(d);
                    }, function(d){
                        $('#elbp_comments_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                    });
                }
                

            },
            
            // Load a particular tutorial from thye summary view
            load_comment: function(id){
            
                ELBP.load_expanded('Comments', function(){
                    
                    setTimeout("$('#comment_content_"+id+"').slideDown();$('div#elbp_popup').scrollTop(0);$('div#elbp_popup').animate({ scrollTop: ($('#elbp_comment_"+id+"').offset().top - $('#elbp_comment_"+id+"').height()) }, 2000);", 1000);
                    
                });

            },
            
            // Mark comment as resolved
            mark_resolved: function(id, val){
            
                var params = {studentID: ELBP.studentID, courseID: ELBP.courseID, commentID: id, val: val};
                
                ELBP.ajax("Comments", "mark_resolved", params, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_popup').scrollTop(0);
                    $('#elbp_comments_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            }



        },
        
        AdditionalSupport: {
        
             // Load a display type
            load_display: function(type, el){
                var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax("AdditionalSupport", "load_display_type", params, function(d){
                    $('#elbp_additional_support_content').html(d);
                    ELBP.set_view_link(el);
                }, function(d){
                    $('#elbp_additional_support_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
            },
            

            // Load up the Add Target form, then when we submit it, bring us back to the Addiiotnal Support form and add that target into it as well as restoring the state we left in
            add_target: function(pluginTitle){
            
                ELBP.save_state('AdditionalSupport');
                ELBP.dock("AdditionalSupport", pluginTitle);
                ELBP.Targets.loaded_from = "AdditionalSupport";
                
                ELBP.load_expanded('Targets', function(){
                    ELBP.Targets.load_display('new', undefined, undefined, 'AdditionalSupport', 'elbp_additional_support_new_targets');
                });

            },
            
            // Edit a target in a tutoria;
            edit_target: function(id, pluginTitle){
            
                ELBP.save_state('AdditionalSupport');
                ELBP.dock("AdditionalSupport", pluginTitle);
                ELBP.Targets.loaded_from = "AdditionalSupport";
                
                ELBP.load_expanded('Targets', function(){
                    ELBP.Targets.edit_target(id, 'AdditionalSupport', 'elbp_additional_support_new_targets');
                });

            },
            
            // Remove a target from the tutorial
            remove_target: function(targetID, sessionID){
            
                var confirm = window.confirm("{$string['areyousureremovetarget']}");
                if (confirm){
                
                    // If no sessionID specified, must be new sessoin that hasn't been saved yet, so just remove from screen and it won't get added to session
                    if (sessionID == undefined){
                        $('#new_added_target_id_'+targetID).remove();
                    }

                    var params = { studentID: ELBP.studentID, courseID: ELBP.courseID, sessionID: sessionID, targetID: targetID };
                    
                    ELBP.ajax("AdditionalSupport", "remove_target", params, function(d){
                        eval(d);
                    });

                }                

            },
            
            save: function(form){
            
                var data = form.serialiseObject();
                $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});
                
                ELBP.ajax("AdditionalSupport", "save", data, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_popup').scrollTop(0);
                    $('#new_additional_support_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            },
            
            // Load a particular tutorial from thye summary view
            load_session: function(id){
            
                ELBP.load_expanded('AdditionalSupport', function(){
                    
                    setTimeout("$('#additional_support_content_"+id+"').show();$('div#elbp_popup').scrollTop(0);$('div#elbp_popup').animate({ scrollTop: ($('#elbp_additional_support_"+id+"').offset().top - $('#elbp_additional_support_"+id+"').height()) }, 2000);", 1000);
                    
                });

            },
            
            edit_session: function(id){
            
                var params = { type: "edit", sessionID: id, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax("AdditionalSupport", "load_display_type", params, function(d){
                    $('#elbp_additional_support_content').html(d);
                }, function(d){
                    $('#elbp_additional_support_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            },
            
            delete_session: function(id){
            
                var confirm = window.confirm("{$string['areyousuredelete']}");
                if (confirm){
                    var params = { studentID: ELBP.studentID, courseID: ELBP.courseID, sessionID: id };
                    ELBP.ajax("AdditionalSupport", "delete", params, function(d){
                        $('#elbp_popup').scrollTop(0);
                        eval(d);
                    });
                }
                

            },
            
            update_target_confidence: function(type, value, sessionID, targetID){
            
                var params = {studentID: ELBP.studentID, courseID: ELBP.courseID, sessionID: sessionID, targetID: targetID, type: type, value: value};
                
                ELBP.ajax("AdditionalSupport", "update_target_confidence", params, function(d){
                    eval(d);
                }, function(d){
                    $('#additional_support_target_output_session_'+sessionID).html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            },
            

            update_target_status: function(statusID, targetID, sessionID){
            
                var params = {studentID: ELBP.studentID, courseID: ELBP.courseID, sessionID: sessionID, targetID: targetID, statusID: statusID};
                
                ELBP.ajax("AdditionalSupport", "update_target_status", params, function(d){
                    eval(d);
                }, function(d){
                    $('#additional_support_target_output_session_'+sessionID).html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            },
            
            add_comment: function(id, comment, parentID){
            
                if (comment == '') return;
                
                var params = { sessionID: id, comment: comment, parentID: parentID };
                ELBP.ajax("AdditionalSupport", "add_comment", params, function(d){
                    eval(d);
                    $('#elbp_additional_support_content input[type="button"]').removeAttr('disabled');
                }, function(d){
                    if (parentID != undefined){
                        $('#elbp_comment_add_output_comment_'+parentID).html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                    } else {
                        $('#elbp_comment_add_output_'+id).html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                    }
                    
                    $('#elbp_additional_support_content input[type="button"]').attr('disabled', 'disabled');

                });

            },
            
            delete_comment: function(sessionID, commentID){
            
                var confirm = window.confirm("{$string['areyousuredelete']}");
                if (confirm){
                    var params = { sessionID: sessionID, commentID: commentID };
                    ELBP.ajax("AdditionalSupport", "delete_comment", params, function(d){ 
                        eval(d);
                    }, function(d){
                        $('#elbp_comment_add_output_'+sessionID).html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                    });
                }

            },
                        
            save_attribute: function(attribute, value){
             
                 var params = {studentID: ELBP.studentID, courseID: ELBP.courseID, attribute: attribute, value: value};
                
                ELBP.ajax("AdditionalSupport", "save_attribute", params, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_additional_support_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
                        
            },
                    
            add_existing_target: function(id){
                    
                if ( $('#new_added_target_id_'+id).length == 0 ){
                    $('#loading_add_existing_target').show();
                    var params = { targetID: id, studentID: ELBP.studentID, courseID: ELBP.courseID }
                    ELBP.ajax("AdditionalSupport", "get_target_row", params, function(d){
                        $('#elbp_additional_support_new_targets').append(d);
                        $('#loading_add_existing_target').hide();                    
                    });
                }
                    
            },
                    
            auto_save:  function(){
                    
                var data = $('#new_additional_support_form').serialiseObject();
                $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID, auto: 1});
                
                ELBP.ajax("AdditionalSupport", "save", data, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_popup').scrollTop(0);
                    $('#new_additional_support_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
                
            }
            

        },
                        
        Challenges: {
        
            save: function(form){
            
                var data = form.serialiseObject();
                $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});
                
                ELBP.ajax("Challenges", "save", data, function(d){
                    eval(d);
                }, function(d){
                    $('#challenges_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

   
            }
                        
        },
                    
        LearningStyles: {
        
            // Load a display type
            load_display: function(type){
                var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax("LearningStyles", "load_display_type", params, function(d){
                    $('#elbp_learning_styles_content').html(d);
                }, function(d){
                    $('#elbp_learning_styles_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
            },      
                    
            save: function(form){
             
                var data = form.serialiseObject();
                $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});
                
                ELBP.ajax("LearningStyles", "save", data, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_popup').scrollTop(0);
                    $('#elbp_learning_styles_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });  
                    
            }
                    
   
        },
                    
        Custom: {
                
            // Load a display type
            load_display: function(plugin, type, el){
                var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax(plugin, "load_display_type", params, function(d){
                    $('#elbp_custom_content').html(d);
                    ELBP.set_view_link(el);
                }, function(d){
                    $('#elbp_custom_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
            },        
                    
                    
            save_single: function(plugin, form){
             
                var data = form.serialiseObject();
                $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});
                
                ELBP.ajax(plugin, "save_single", data, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_popup').scrollTop(0);
                    $('#custom_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
                    
            },
                    
            save_incremental: function(plugin, form){
              
                var data = form.serialiseObject();
                $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});
                
                ELBP.ajax(plugin, "save_incremental", data, function(d){
                    eval(d);
                    ELBP.Custom.refresh_incremental(plugin);
                    $(form)[0].reset();
                }, function(d){
                    $('#elbp_popup').scrollTop(0);
                    $('#custom_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
                    
            },
                    
            delete_incremental_item: function(plugin, id){
            
                var confirm = window.confirm("{$string['areyousuredelete']}");
                if (confirm){
                
                    var params = { studentID: ELBP.studentID, courseID: ELBP.courseID, itemID: id };
                    ELBP.ajax(plugin, "delete_incremental_item", params, function(d){
                        eval(d);
                        ELBP.Custom.refresh_incremental(plugin);
                    }, function(d){
                        $('#elbp_popup').scrollTop(0);
                        $('#custom_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                    });
                
                }
                

            },
                        
            refresh_incremental: function(plugin){
            
                var params = { studentID: ELBP.studentID, courseID: ELBP.courseID };
                ELBP.ajax(plugin, "refresh_incremental", params, function(d){
                    $('#elbp_custom_plugin_items').html( d );
                }, function(d){
                    $('#elbp_popup').scrollTop(0);
                    $('#elbp_custom_plugin_items').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
                        
            },
                    
            save_multi: function(plugin, form){
             
                var data = form.serialiseObject();
                $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});
                
                ELBP.ajax(plugin, "save_multi", data, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_popup').scrollTop(0);
                    $('#custom_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });
                    
            },
                    
            delete_item: function(plugin, id){
            
                var confirm = window.confirm("{$string['areyousuredelete']}");
                if (confirm){
                
                    var params = { studentID: ELBP.studentID, courseID: ELBP.courseID, itemID: id };
                    ELBP.ajax(plugin, "delete_item", params, function(d){
                        eval(d);
                    }, function(d){
                        $('#elbp_popup').scrollTop(0);
                        $('#custom_output').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                    });
                
                }
                

            },
                        
            edit_item: function(plugin, id){
            
                var params = { type: "edit", itemID: id, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax(plugin, "load_display_type", params, function(d){
                    $('#elbp_custom_content').html(d);
                }, function(d){
                    $('#elbp_popup').scrollTop(0);
                    $('#elbp_custom_content').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
                });

            },
                    
                    
        }


        // NEXT PLUGIN




   };
  

// On Ready
function elbp_load_stuff(){

//    $('#elbp_studentprofile_details, #elbp_studentprofile_info').hover( function(){
//        ELBP.StudentProfile.edit_link(this, 'block');
//    }, function(){
//        ELBP.StudentProfile.edit_link(this, 'none');
//    });
    
    // Resize the popup
    ELBP.resize_popup();  
    $(window).resize( function(){
        ELBP.resize_popup();
    } );
       
    
    // Destroy datepickers and recreate them
    $('.elbp_datepicker').removeClass('hasDatepicker');

    $('.elbp_datepicker').datepicker( {dateFormat: 'dd-mm-yy', changeMonth: true, changeYear: true} );
    $('.elbp_datepicker_no_past').datepicker( {dateFormat: 'dd-mm-yy', changeMonth: true, changeYear: true, minDate: 0} );
    $('.elbp_datepicker_no_future').datepicker( {dateFormat: 'dd-mm-yy', changeMonth: true, changeYear: true, maxDate: 0} );
    
    //$('.elbp_datepicker, .elbp_datepicker_no_past, .elbp_datepicker_no_future').attr('readonly', 'readonly');
    $('.elbp_datepicker, .elbp_datepicker_no_past, .elbp_datepicker_no_future').attr('placeholder', 'dd-mm-yyyy');

    // Rating
    if ( $().raty !== undefined ){
        $('.elbp_rate').raty('destroy');
        $('.elbp_rate').raty( {
            score: function() {
                return $(this).attr('data-score');
            },
            scoreName: function(){
                return $(this).attr('score-name');
            },
            readOnly: function(){
                return ($(this).attr('data-readonly') == 1);
            },
            number: function(){
                return $(this).attr('data-number');
            }
        } );
    }
                    
                    
                    
    $('#elbp_popup').draggable( { handle: "div.elbp_popup_header" } );
                    
    // Files
    $('.elbp_file').each( function(){
            
        var elID = $(this).attr('id');

        var uploader = new qq.FineUploader({
        element: document.getElementById(elID),
        request: {
          endpoint: M.cfg.wwwroot + '/blocks/elbp/upload.php'
        },
        multiple: false,
        autoUpload: true,
        text: {
          uploadButton: '<i class="icon-plus icon-white"></i> {$string['selectfile']}',
          formatProgress: '({percent}% of {total_size})'
        },
        failedUploadTextDisplay: {
            mode: 'custom',
            maxChars: 150,
            responseProperty: 'error',
            enableTooltip: true
        },
        callbacks: {
          onComplete: function(id, filename, response){
              if (response.success){
                  $('#'+elID).val('');
                  $('#hidden-file-'+elID).val(response.uploadName);
              }
          }
        }
      });

    } );                
    
    
    $.fn.serialiseObject = function(){

        var obj = {};
        var f = this;
        var arr = this.serializeArray();

        $.each(arr, function(){
	
            var name = this.name;
                    
            // If it's an array with a key, we need to deal with that differently
            var match = name.match(/\[(\d+|\w+|)\]/);
            if (match != null){

                var key = match[1];
                var arrayName = name.split("[")[0];

                if (!obj[arrayName]){
                        obj[arrayName] = [];
                }

                if (key != ''){
                        obj[arrayName][key] = this.value || '';
                } else {
                        obj[arrayName].push(this.value || '');
                }

            } else {

                if (obj[this.name]) {
                        if (!obj[this.name].push) {
                                obj[this.name] = [obj[this.name]];
                        }
                        obj[this.name].push(this.value || '');
                } else {
                        obj[this.name] = this.value || '';
                }

            }

        });
              
        // Find moodle text editor elements
        var els = $(this).find('.elbp_texteditor');
        $(els).each( function(){
        
            var id = $(this).attr('id');
            var name = $(this).attr('name');
            var content = '';
          
            // Moodle 2.6 and lower
            var ifr = $(f).find('#'+id+'_ifr');
          
            // Moodle 2.7 and above
            var ifr27 = $(f).find('#'+id+'editable');
          
            if (ifr.length > 0){
                content = $(ifr).contents().find('body').html();
            } else if ( ifr27.length > 0 ){
                content = $(ifr27).html();
            } else {
                content = $(f).find('#'+id).val();
            }
          
            obj[name] = content;
   
        } );
                                                
        return obj;

    }; 

    $('.elbp_required_field').remove();
    $('[validation*="REQUIRED"]').after('<span class="elbp_required_field" title="{$string['requiredfield']}">*</span>');
   
    $('.target_name_tooltip').tooltip();
    
    $('.dock_plugin_close img').mouseover( function(){
        var src = $(this).attr('src');
        $(this).attr('src', src.replace('close_tiny.png', 'close_tiny_hover.png'));
    } );
    
    $('.dock_plugin_close img').mouseout( function(){
        var src = $(this).attr('src');
        $(this).attr('src', src.replace('close_tiny_hover.png', 'close_tiny.png'));
    } );
    
    // Check for cookie to hide badges
    if ( getCookie('hide_elbp_badges') == 1 ){
        if ( $('#toggle-badges').length > 0 ){
            var src = $('#toggle-badges').attr('src');
            src = src.replace('switch_minus', 'switch_plus');
            $('#toggle-badges').attr('src', src);
        }
    }
    
    
    $('.elbp_progress_traffic_light').unbind('click');
    $('.elbp_progress_traffic_light').bind('click', function(){
        
        var rankNum = $(this).attr('rankNum');
    
        // If already shown, hide it
        if ( $('#elbp_progress_traffic_light_desc_' + rankNum).css('display') == 'none' ){
    
            $('.elbp_progress_traffic_light_desc').hide();
            $('#elbp_progress_traffic_light_desc_' + rankNum).show();
    
        } else {
    
            $('.elbp_progress_traffic_light_desc').hide();
    
        }
        
    
    } );
    
    
    $('.elbp_set_student_manual_progress').unbind('click');
    $('.elbp_set_student_manual_progress').bind('click', function(){
        
        var rank = $(this).attr('rankNum');
        var params = { studentID: ELBP.studentID, rank: rank };
        ELBP.ajax(0, "set_student_manual_progress", params, function(d){
            eval(d);
            $('#elbp_progress_traffic_loading').html('');
        }, function(d){
            $('#elbp_progress_traffic_loading').html('<img src="{$CFG->wwwroot}/blocks/elbp/pix/loader.gif" alt="" />');
        });
    
    });
    
}
    
    
function toggleBadges(){
 
    if ( $('#badges_content').length > 0 )
    {
    
        $('#badges_content').slideToggle();

        if ( $('#toggle-badges').length > 0 )
        {

            var src = $('#toggle-badges').attr('src');
            if (src.indexOf('switch_plus') > 0){
                src = src.replace('switch_plus', 'switch_minus');
                addCookie('hide_elbp_badges', 0);
            } else {
                src = src.replace('switch_minus', 'switch_plus');
                addCookie('hide_elbp_badges', 1);
            }

            $('#toggle-badges').attr('src', src);
        
        }
    
    }
    
}

function getCookie(cookie){
  
    var c = document.cookie.split(' ');
    var cookies = {};
    for (i in c){
        var split = c[i].split("=");
        var k = split[0];
        var v = split[1];
        v = v.replace(';', '');
        cookies[k] = v;
    }
    
    return cookies[cookie];   
    
}
    
function addCookie(cookie, value){
 
    var str = "";
    str += (cookie + "=" + value);
    document.cookie = str;
    
}
    
    
$(document).ready( function(){

    elbp_load_stuff();
    
    // Shortcut to bring popup back into centre of screen incase we accidently moved it outside of window and can't close
    var keyMap = {16: false, 17: false, 220: false}; // Ctrl + Shift + |
    var keyMapAdvanced = { 16: false, 17: false, 192: false }; // Ctrl + Shift + @
    var keyMapClear = {16: false, 17: false, 13: false}; // Ctrl + Shift + Enter
    
    $(document).keydown(function(e){
        
        if (e.keyCode in keyMap){
            keyMap[e.keyCode] = true;
            if (keyMap[16] && keyMap[17] && keyMap[220] && $('#elbp_popup').css('display') != 'none'){
                $('#elbp_popup').css('top', '5%').css('left', '5%');
            }
        }
    
        if (e.keyCode in keyMapAdvanced){
    
            keyMapAdvanced[e.keyCode] = true;
            if (keyMapAdvanced[16] && keyMapAdvanced[17] && keyMapAdvanced[192]){
                $('#elbp_admin_blanket').toggle();
                if ( $('#elbp_admin_blanket').css('display') != 'none' ){
                    $('#cmd_input input').focus();
                    $('body').css('overflow', 'hidden');
                } else {
                    $('body').css('overflow', 'auto');
                }
            }
    
        }
    
    if (e.keyCode in keyMapClear){
            keyMapClear[e.keyCode] = true;
            if (keyMapClear[16] && keyMapClear[17] && keyMapClear[13]){
                $('#elbp_popup').hide();
                ELBP.hide('#elbp_blanket');
            }
        }
    

    }).keyup(function(e) {
    
        $.each(keyMap, function(i, item){ 
            keyMap[i] = false; 
        });
    
        $.each(keyMapAdvanced, function(i, item){ 
            keyMapAdvanced[i] = false; 
        });
    
        $.each(keyMapClear, function(i, item){ 
            keyMapClear[i] = false; 
        });
    
    });
    
    
    
    
    $('#cmd_input #input').keydown( function(e){
        
        if (e.keyCode == 38){
    
            if ( (commandPointer - 1) >= 0){
                commandPointer--;
                var command = savedCommands[commandPointer];
                $('#cmd_input #input').val('');
                $('#cmd_input #input').val(command);
            }
    
        } else if (e.keyCode == 40){
    
            if ( (commandPointer + 1) < savedCommands.length){
                commandPointer++;
                var command = savedCommands[commandPointer];
                $('#cmd_input #input').val('');
                $('#cmd_input #input').val(command);
            }
    
        }
    
    } );
    

    
    if (!String.prototype.trim){
        String.prototype.trim = function(){
            return this.replace(/^\s+|\s+$/g, '');
        };
    }
    
    // http://whattheheadsaid.com/2010/10/a-safer-object-keys-compatibility-implementation //
    Object.keys = Object.keys || (function () {
        var hasOwnProperty = Object.prototype.hasOwnProperty,
            hasDontEnumBug = !{toString:null}.propertyIsEnumerable("toString"),
            DontEnums = [
                'toString',
                'toLocaleString',
                'valueOf',
                'hasOwnProperty',
                'isPrototypeOf',
                'propertyIsEnumerable',
                'constructor'
            ],
            DontEnumsLength = DontEnums.length;

        return function (o) {
            if (typeof o != "object" && typeof o != "function" || o === null)
                throw new TypeError("Object.keys called on a non-object");

            var result = [];
            for (var name in o) {
                if (hasOwnProperty.call(o, name))
                    result.push(name);
            }

            if (hasDontEnumBug) {
                for (var i = 0; i < DontEnumsLength; i++) {
                    if (hasOwnProperty.call(o, DontEnums[i]))
                        result.push(DontEnums[i]);
                }   
            }

            return result;
        };
    })();



} );


   

   
JS;

echo $output;
