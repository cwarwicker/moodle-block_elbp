define(['jquery', 'jqueryui', 'block_elbp/minicolors', 'block_elbp/raty', 'block_elbp/tinytbl', 'block_elbp/fileupload'], function($, ui, miniColors, raty, tinytbl, fileupload) {

    // Raty image path
    if ($.fn.raty !== undefined) {
        $.fn.raty.defaults.path = M.cfg.wwwroot + '/blocks/elbp/js/jquery/plugins/raty/images';
    }

    var client = {};
    var ELBP = {};

    ELBP.www = M.cfg.wwwroot;
    ELBP.savedStates = new Array();
    ELBP.savedInputs = {};
    ELBP.savedCommands = new Array();
    ELBP.commandPointer = 0;
    ELBP.dockPosition = null;
    ELBP.pluginIcons = {};
    ELBP.moodleVersion = null;
    ELBP.reloadOnUnPop = 0;
    ELBP.tempData = null;
    ELBP.studentID = null;
    ELBP.courseID = null;
    ELBP.pluginGroup = null;
    ELBP.pluginIcons = {};
    ELBP.strings = {};
    ELBP.pluginScripts = {};

    // Push a plugin's AMD scripts onto the ELBP
    ELBP.push_script = function(name, scripts){
      ELBP.pluginScripts[name] = scripts;
    };

    // Call the bindings of all plugin scripts
    ELBP.bind_scripts = function(){
      $.each(ELBP.pluginScripts, function(name, scripts){
        scripts.bindings();
      });
    }

    // Call the bindings of a particular script
    ELBP.bind_script = function(plugin){
      if (ELBP.pluginScripts[plugin] !== undefined){
        ELBP.pluginScripts[plugin].bindings();
      }
    }

    // Hide an element
    ELBP.hide = function(el){
        $(el).hide();
    };

    // Show an element
    ELBP.show = function(el){
        $(el).show();
    };

    ELBP.ajax = function(plugin, action, params, callback, callBefore){

        var url = ELBP.www + "/blocks/elbp/js/ajaxHandler.php";

        if (callBefore){
            callBefore();
        }

        $.ajax({
            type: "POST",
            url: url,
            data: {plugin: plugin, action: action, params: params},
            error: function(d){
                ELBP.ajax_error(d.responseText);
                console.log('Error: ' + d);
            },
            success: function(d){

                // If no data returned, or attempted redirect - display alert
                if (d.length === 0 || d.indexOf("<!DOCTYPE") >= 0){
                  alert( 'No data was returned by this request. Your Moodle session may have timed out, please refresh the page and see if you need to login again.' );
                  return false;
                }

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

    };

    // Default callback after each ajax call
    ELBP.ajax_callback = function(data){
        ELBP.bind();
    };

    // AJAX error function
    ELBP.ajax_error = function(msg){
        $('#elbp_error_output').html('['+new Date() + '] ' + msg);
        $('#elbp_error_output').slideDown('slow');
    };

    // Look for ELBP:JS tags to remove data and eval() instead of displaying
    ELBP.process_data_eval = function(data){

        var pat = /\[ELBP:JS\]([\s\S]+?)\[\/ELBP:JS\]/g;
        var matches = data.match(pat);
        if(matches)
        {

            data = data.replace(pat, "");
            var toEval = "";
            for(var i = 0; i < matches.length; i++)
            {
                matches[i] = matches[i].replace(/(\[ELBP:JS\]|\[\/ELBP:JS\])/g, "");
                toEval += matches[i];
            }

            ELBP.tempData = data;
            return toEval;

        }

        return false;

    };

    // Test an MIS connection
    ELBP.test_mis_connection = function(type, host, user, pass, db, id){

        var params = {
            type: type,
            host: host,
            user: user,
            pass: pass,
            db: db
        };

        var el = '#elbp_config_test_conn';
        if (id != undefined){
            el += '_'+id;
        }

        ELBP.ajax(0, "test_mis_connection", params, function(d){
            $(el).html(d);
        }, function(){
            $(el).html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
        });

    };

    // Popup
    ELBP.pop = function(){

        ELBP.show('#elbp_blanket');
        $('#elbp_popup').show('scale', {}, 1000);

        // Prevent background scrolling
        $('body').css('overflow', 'hidden');

    };

    // Remove popup
    ELBP.unpop = function(pluginName, pluginTitle){

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

        if (ELBP.reloadOnUnPop){
            ELBP.load("group", ELBP.pluginGroup);
        }

        // Allow background scrolling again
        $('body').css('overflow', 'auto');

    };

    // Reset various global variables that may have been set at some point
    ELBP.reset_global_vars = function(){
        ELBP.Targets.loaded_from = false;
    };


    // Save the state of a plugin when we minimize it
    ELBP.save_state = function(pluginName){

        ELBP.savedStates[pluginName] = $('#elbp_popup').clone(true, true);
        ELBP.savedInputs[pluginName] = {};

        // Find inputs
        var inputs = $('#elbp_popup :input');
        $(inputs).each( function(){
            var tagName = $(this).prop('tagName');
            if (tagName != 'BUTTON' && $(this).attr('type') != 'submit'){

                var id = $(this).attr('id');
                var name = $(this).attr('name');
                var val = $(this).val();
                var chkd = ( $(this).is(':checked') ) ? true : false ;
                var content = '';

                // First check if it's a texteditor, in which case get iframe contents
                if ( $(this).hasClass('elbp_texteditor') ){

                    // Check for old iframe first
                    if ( $('#'+id+'_ifr').length > 0 ){
                        content = $('#'+id+'_ifr').contents().find('html')[0];
                    }
                    else
                    {
                        content = $('#'+id+'editable').html();
                    }

                    ELBP.savedInputs[pluginName][name] = {value: content, checked: chkd};

                }
                // Only one value with that name so far: {value: value, checked: checked}
                else if (ELBP.savedInputs[pluginName][name] != undefined && ELBP.savedInputs[pluginName][name] instanceof Object && ELBP.savedInputs[pluginName][name][0] == undefined ){
                    var tmpVar = ELBP.savedInputs[pluginName][name];
                    ELBP.savedInputs[pluginName][name] = [ tmpVar, {value: val, checked: chkd} ];
                } else if (ELBP.savedInputs[pluginName][name] != undefined && ELBP.savedInputs[pluginName][name] instanceof Object && ELBP.savedInputs[pluginName][name][0] instanceof Object){
                    var cnt = Object.keys(ELBP.savedInputs[pluginName][name]).length;
                    ELBP.savedInputs[pluginName][name][cnt] = {value: val, checked: chkd};
                } else {
                    ELBP.savedInputs[pluginName][name] = {value: val, checked: chkd};
                }

            }
        } );

    };


    ELBP.restore_state = function(pluginName){

        if (ELBP.savedStates[pluginName] != undefined && ELBP.savedStates[pluginName] != '' && $(ELBP.savedStates[pluginName]).html() != ''){

            $('#elbp_popup').remove();

            // Load inputs values back into saved html
            $.each( ELBP.savedInputs[pluginName], function(key, o){

                var v = o.value;
                var fullHtml = undefined;

                // First check for any texteditors as we need to do iframey stuff
                var type = $.type(v);
                if (type == 'object' && $(v).find('body').length > 0){
                    v = $(v).find('body').html();
                }

                key = key.replace(/"/g, '&quot;');
                var tmpVar = $('input[name="'+key+'"], textarea[name="'+key+'"], select[name="'+key+'"]', ELBP.savedStates[pluginName]);
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
                            $('select[name="'+key+'"] option', ELBP.savedStates[pluginName]).filter( function(){
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


            // Remove the whole editor section and it should re apply on load
            $(ELBP.savedStates[pluginName]).find('.elbp_texteditor').siblings('div').remove();


            var html = $(ELBP.savedStates[pluginName]).html();


            // Put the elbp_popup div around it again
            var wrapperDiv = $(ELBP.savedStates[pluginName]).first();
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

            setTimeout(function(){
                ELBP.bind();
            }, 1500);

            // Prevent background scrolling
            $('body').css('overflow', 'hidden');

        }
    };

    // Load all plugins
    ELBP.load = function(type, id){

        var params = {
            type: type,
            id: id,
            student: ELBP.studentID,
            course: ELBP.courseID
        }

        ELBP.ajax(0, 'load_template', params, function(d){
            $('#elbp_summary_boxes').html(d);
            ELBP.bind_scripts();
        }, function(d){
            $('#elbp_summary_boxes').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
        });

    };

    // Load the expanded view of a plugin
    ELBP.load_expanded = function(plugin, callAfter){

        var params = {
            pluginname: plugin,
            student: ELBP.studentID,
            course: ELBP.courseID
        }

        ELBP.ajax(0, 'load_expanded', params, function(d){

            $('#elbp_popup').html(d);
            ELBP.bind_script(plugin);

            if (callAfter){
                callAfter();
            }

        }, function(d){
            ELBP.pop();
            $('#elbp_popup').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
        });

    };


    ELBP.set_group_class = function(el){
        $('.elbp_tabrow li').each(function(){
            $(this).attr('class', '');
        });
        $(el).parent().addClass('selected');
    };


    ELBP.apply_summary_hover = function(){

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

    };

    // Load image
    ELBP.load_image = function(url, el){
        setTimeout("$('#"+el+"').attr('src', '"+url+"');", 500); // This is in a timeout because some browsers don't show the loading gif
    };

    ELBP.apply_tooltip = function(){
        $(document).ready(function(){
            $(".elbp_tooltip").tooltip({
                content: function(){
                    return $(this).prop('title');
                }
            });
        });
    };


    ELBP.expand = function(el){

        var wrapperObj = $(el).siblings('.elbp_summary_box_wrapper')[0];

        var scrollHeight = wrapperObj.scrollHeight + 10;
        $(wrapperObj).animate({height: scrollHeight}, 300);

        // Change expand link to contract
        $(el).attr('onclick', 'ELBP.contract(this);return false;');
        $(el).attr('title', ELBP.strings['contract']);
        var img = $(el).children('img');
        $(img).attr('src', $(img).attr('src').replace('switch_plus', 'switch_minus'));

    };

    ELBP.contract = function(el){

        var max_height = 225;

        var wrapperObj = $(el).siblings('.elbp_summary_box_wrapper')[0];

        $(wrapperObj).animate({height: max_height}, 300);

        $(el).slideUp('slow');

        // Change expand link to expand
        $(el).attr('onclick', 'ELBP.expand(this);return false;');
        $(el).attr('title', ELBP.strings['expand']);
        var img = $(el).children('img');
        $(img).attr('src', $(img).attr('src').replace('switch_minus', 'switch_plus'));

    };


    ELBP.set_view_link = function(el, sub){

        if (sub !== undefined){
            $('.elbp_view_link_'+sub).removeClass('selected');
        } else {
            $('.elbp_view_link').removeClass('selected');
        }

        if (el == undefined) return;
        $(el).parent().addClass('selected');

    };

    ELBP.confirm_submit = function(){
        return window.confirm( ELBP.strings['areyousuredelete'] );
    };

    // Resize popups
    ELBP.resize_popup = function(){

        // Height & Width of popup so we can use explode effect
        var screenWidth = ($(window).width() / 100) * 80;
        var screenHeight = ($(window).height() / 100) * 80;
        $('#elbp_popup').css('height', screenHeight);
        $('#elbp_popup').css('width', screenWidth);

    };

    // Dialogue popup
    ELBP. dialogue = function(title, content, opt){

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

    };

    // Check if the browser supports a given input type
    ELBP.browser_supports = function(inputType){
        var testInput = document.createElement('input');
        try {
            testInput.type = inputType;
        } catch (e){}
        return (testInput.type === inputType);
    };

    // Change an image src
    ELBP.toggle_image = function(img, el){
        $(el).attr('src', img);
    };

    // Append a link to the dock to open a plugin
    ELBP.dock = function(plugin, pluginTitle){

        if (!$('#docked_'+plugin)[0]){

            if (ELBP.dockPosition == 'bottom'){
                $('#elbp_dock_list').append('<li id="docked_'+plugin+'"><a href="#" onclick="ELBP.load_from_dock(\''+plugin+'\');return false;" class="dock_plugin_name" title="'+ELBP.strings['loadsavedstate']+': '+pluginTitle+'">'+pluginTitle+'</a> <a href="#" onclick="ELBP.undock(\''+plugin+'\');return false;" class="dock_plugin_close"><img src="'+ELBP.www+'/blocks/elbp/pix/close_tiny.png" alt="img" title="'+ELBP.strings['undock']+': '+pluginTitle+'" /></a></li>');
            }

            else if (ELBP.dockPosition == 'left'){
                var img = ELBP.pluginIcons[plugin];
                $('#elbp_dock_list').append('<li id="docked_'+plugin+'"><a href="#" onclick="ELBP.load_from_dock(\''+plugin+'\');return false;" class="dock_plugin_name" title="\'+ELBP.strings[\'loadsavedstate\']+\': '+pluginTitle+'"><img src="'+img+'" alt="'+pluginTitle+'" /></a><br><a href="#" onclick="ELBP.undock(\''+plugin+'\');return false;" class="dock_plugin_close"><img src="\'+ELBP.www+\'/blocks/elbp/pix/close_tiny.png" alt="img" title="'+ELBP.strings['undock']+': '+pluginTitle+'" /></a></li>');
            }

        }

    };

    // Remove a plugin from the dock list
    ELBP.undock = function(plugin)
    {
        $('#docked_'+plugin).remove();
        ELBP.savedStates[plugin] = undefined;

        // If there is an expanded view loaded and it's this plugin we are undocking, unpop it first
        if ($('#elbp_popup_header_plugin_'+plugin)[0]){
            ELBP.unpop();
        }
    };


    // Load a plugin from the dock
    ELBP.load_from_dock = function(plugin)
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
        if (ELBP.savedStates[plugin] != undefined && ELBP.savedStates[plugin] != '' && $(ELBP.savedStates[plugin]).html() != ''){
            ELBP.restore_state(plugin);
        } else {
            ELBP.load_expanded(plugin);
        }

    };

    // Load users into the select menu for the switch user bars
    ELBP.switch_users = function(param){

        $('#switch_user_users').find('option').remove();
        $('#switch_user_users').css('display', 'none');
        $('#find_other_user').css('display', 'none');

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
            $('#switch_users_loading').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" style="height:10px;" />');
        });

    };

    // Switch the user
    ELBP.switch_user = function(id, type){

        if (id == '' || id == ELBP.studentID) return false;

        var url = window.location.href;

        url = url.replace(/id=[\d]+/g, "id="+id);

        if (url.indexOf('cID') > -1){
            url = url.replace(/cID=(.+)/g, "cID="+type);
        } else {
            url = url + "&cID="+type;
        }

        window.location = url;

    };

    // Switch which type of users to look for
    ELBP.switch_search_user = function(search){

        if (search == '') return false;

        var params = { action: "load_users", search: search };
        ELBP.ajax(0, "search_load_student", params, function(d){

            $('#switch_users_loading').html('');
            eval(d);

        }, function(){
            $('#switch_users_loading').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" style="height:10px;" />');
        });

    };

    // Load your settings popup
    ELBP.my_settings = function(userID){

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
        };

        var params = { student: ELBP.studentID, userID: userID }

        $('#mysettingsimg').attr('src', ELBP.www + '/blocks/elbp/pix/loader.gif');

        ELBP.ajax(0, "load_my_settings", params, function(d){
            ELBP.dialogue(ELBP.strings['mysettings'], d, opt);
            $('#mysettingsimg').attr('src', ELBP.www + '/blocks/elbp/pix/icons/cog.png');
        });

    };

    // Save your settings
    ELBP.save_my_settings = function(userID){

        var params = {};

        if (userID == undefined || userID == "undefined" || userID == ""){
            params = { data: $('#my_settings').serialiseObject() }
        } else {
            params = { data: $('#my_settings').serialiseObject(), userID: userID }
        }

        ELBP.ajax(0, "save_my_settings", params, function(d){
            location.reload(true);
        });

    };

    // Apply colour picker when the browser does not support color inputs
    ELBP.apply_colour_picker = function(){

        if (!ELBP.browser_supports('color'))
        {

            $('input[type="color"]').each( function() {
                $(this).minicolors();
            });

        }

    };

    // Course Picker functions
    ELBP.course_picker = {

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

    };



    // User Picker functions
    ELBP.user_picker = {

        // Search users
        search_user: function(search, el){

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

    };


    // Validate a form based on validation attributes
    ELBP.validate_form = function(form){

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

                // Moodle 2.7 and above
                var ifr27 = $('#' + $(this).attr('id') + 'editable');
                if ( ifr27.length > 0 ) {

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
                                    $($(others)[0]).before('<span class="elbp_error"><br>'+ELBP.strings['validation:required:tickbox']+'<br></span> ');
                                }

                            }
                            else
                            {
                                var pat = /.+/;
                                if (value.match(pat) == null){
                                    if (firstEl == '') firstEl = input;
                                    errs++;
                                    $(input).addClass('elbp_red');
                                    $(input).after('<span class="elbp_error"><br>'+ELBP.strings['validation:required']+'</span>');
                                }
                            }


                            break;

                        case 'TEXT_ONLY':
                            var pat = /[^a-z ]/i;
                            if (value.match(pat) != null || value == ''){
                                if (firstEl == '') firstEl = input;
                                errs++;
                                $(input).addClass('elbp_red');
                                $(input).after('<span class="elbp_error"><br>'+ELBP.strings['validation:textonly']+'</span>');
                            }
                        break;

                        case 'NUMBERS_ONLY':
                            var pat = /^[0-9]+\.?[0-9]*$/i;
                            if (value.match(pat) == null || value == ''){
                                if (firstEl == '') firstEl = input;
                                errs++;
                                $(input).addClass('elbp_red');
                                $(input).after('<span class="elbp_error"><br>'+ELBP.strings['validation:numbersonly']+'</span>');
                            }
                            break;

                        case 'ALPHANUMERIC_ONLY':
                            var pat = /[^0-9 a-z]/i;
                            if (value.match(pat) != null || value == ''){
                                if (firstEl == '') firstEl = input;
                                errs++;
                                $(input).addClass('elbp_red');
                                $(input).after('<span class="elbp_error"><br>'+ELBP.strings['validation:alphanumericonly']+'</span>');
                            }
                            break;

                        case 'EMAIL':
                            var pat = /^[a-z0-9_\.]+@[a-z0-9\.]+\.[a-z\.]{2,4}[a-z]{1}$/i;
                            if (value.match(pat) == null || value == ''){
                                if (firstEl == '') firstEl = input;
                                errs++;
                                $(input).addClass('elbp_red');
                                $(input).after('<span class="elbp_error"><br>'+ELBP.strings['validation:email']+'</span>');
                            }

                            break;

                        case 'PHONE':
                            var pat = /^(\+\d{1,}\s?)?0\d{4}\s?\d{6}$/;
                            if (value.match(pat) == null || value == ''){
                                if (firstEl == '') firstEl = input;
                                errs++;
                                $(input).addClass('elbp_red');
                                $(input).after('<span class="elbp_error"><br>'+ELBP.strings['validation:phone']+'</span>');
                            }
                            break;

                        case 'DATE':
                            var pat = /^\d{2}\-\d{2}\-\d{4}$/
                            if (value.match(pat) == null || value == ''){
                                if (firstEl == '') firstEl = input;
                                errs++;
                                $(input).addClass('elbp_red');
                                $(input).after('<span class="elbp_error"><br>'+ELBP.strings['validation:date']+'</span>');
                            }

                            break;

                        case 'URL':
                            var pat = /(((http|ftp|https):\/{2})+(([0-9a-z_-]+\.)+(aero|asia|biz|cat|com|coop|edu|gov|info|int|jobs|mil|mobi|museum|name|net|org|pro|tel|travel|ac|ad|ae|af|ag|ai|al|am|an|ao|aq|ar|as|at|au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|bi|bj|bm|bn|bo|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|cr|cu|cv|cx|cy|cz|cz|de|dj|dk|dm|do|dz|ec|ee|eg|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|in|io|iq|ir|is|it|je|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|mg|mh|mk|ml|mn|mn|mo|mp|mr|ms|mt|mu|mv|mw|mx|my|mz|na|nc|ne|nf|ng|ni|nl|no|np|nr|nu|nz|nom|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|ps|pt|pw|py|qa|re|ra|rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sj|sk|sl|sm|sn|so|sr|st|su|sv|sy|sz|tc|td|tf|tg|th|tj|tk|tl|tm|tn|to|tp|tr|tt|tv|tw|tz|ua|ug|uk|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|yu|za|zm|zw|arpa)(:[0-9]+)?((\/([~0-9a-zA-Z\#\+\%@\.\/_-]+))?(\?[0-9a-zA-Z\+\%@\/&\[\];=_-]+)?)?))\b/im;
                            if (value.match(pat) == null || value == ''){
                                if (firstEl == '') firstEl = input;
                                errs++;
                                $(input).addClass('elbp_red');
                                $(input).after('<span class="elbp_error"><br>'+ELBP.strings['validation:url']+'</span>');
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


    };


    ELBP.execute = function(action){

        ELBP.savedCommands.push(action);
        ELBP.commandPointer = ELBP.savedCommands.length;

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

    };

    // Load helper doc
    ELBP.load_helper = function(name){
        window.open(ELBP.www + '/blocks/elbp/help.php?f='+name,'targetWindow','toolbar=no,location=no,status=no,menubar=no,scrollbars=yes,resizable=yes,width=800,height=600');
    };


    /******************* Plugins ********************/
    ELBP.Targets = {

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
                $('#elbp_targets_content').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
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
                $('#new_target_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        edit_target: function(id, loadedFrom, putInto){

            var params = { type: "edit", targetID: id, studentID: ELBP.studentID, courseID: ELBP.courseID, loadedFrom: loadedFrom, putInto: putInto }
            ELBP.ajax("Targets", "load_display_type", params, function(d){
                $('#elbp_targets_content').html(d);
            }, function(d){
                $('#elbp_targets_content').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        delete_target: function(id){

            var confirm = window.confirm(ELBP.strings['areyousuredelete']);
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


    };


    // STUDENT PROFILE PLUGIN

    ELBP.StudentProfile = {

        // Show the edit link on the student details & student info boxes - on hover
        edit_link : function(parent, display){
            $(parent).find('.elbp_studentprofile_edit_link').css('display', display);
        },

        edit : function(section){

            var hide = 'elbp_studentprofile_'+section+'_simple';
            var show = 'elbp_studentprofile_'+section+'_edit';
            var link = '#elbp_studentprofile_'+section+'_edit_link';

            ELBP.hide('.'+hide+', #'+hide);
            ELBP.show('.'+show+', #'+show);


            $(link).text('['+ELBP.strings['save']+']');
            $(link).attr('onclick', 'ELBP.StudentProfile.save("'+section+'");return false;');

            // Cancel link
            $(link).after('<a href="#" id="cancel_link_'+section+'" onclick="ELBP.StudentProfile.edit_return(\''+section+'\');$(this).remove();return false;">['+ELBP.strings['cancel']+']</a>');

        },

        edit_return : function(section){

            var hide = 'elbp_studentprofile_'+section+'_edit';
            var show = 'elbp_studentprofile_'+section+'_simple';
            var link = '#elbp_studentprofile_'+section+'_edit_link';

            ELBP.hide('.'+hide+', #'+hide);
            ELBP.show('.'+show+', #'+show);

            $(link).text(ELBP.strings['edit']);
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
                var info = '';
                if ($('#student_info_textarea_ifr').length > 0){
                    info = $('#student_info_textarea_ifr').contents().find('body').html();
                } else if ( $('#student_info_textareaeditable').length > 0 ) {
                    info = $('#student_info_textareaeditable').html();
                } else {
                    info = $('#student_info_textarea').val();
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
                $('#student_profile_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });
        },

        update_info : function(params){
            ELBP.ajax("StudentProfile", "update_info", params, function(){
                ELBP.StudentProfile.edit_return("info");
                ELBP.StudentProfile.return_info(params.info);
                $('#student_profile_output').html('');
            }, function(){
                $('#student_profile_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });
        }


    };

    // Attendance Plugin
    ELBP.Attendance = {

        // Load a display type, e.g. tabular, bar chart, etc...
        load_display: function(type, el){
            var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID };
            ELBP.ajax("Attendance", "load_display_type", params, function(d){
                $('#elbp_attendance_content').html(d);
                ELBP.set_view_link(el);
            }, function(d){
                $('#elbp_attendance_content').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });
        }

    };


    // Timetable Plugin
    ELBP.Timetable = {

        // Load a display type
        load_display: function(type, el){
            var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID };
            ELBP.ajax("elbp_timetable", "load_display_type", params, function(d){

                $('#elbp_timetable_content').html(d);
                ELBP.set_view_link(el);
                ELBP.bind_script('elbp_timetable');

            }, function(d){
                $('#elbp_timetable_content').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
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
            };

            var params = { student: ELBP.studentID };

            ELBP.ajax("elbp_timetable", "load_colours_form", params, function(d){
                ELBP.dialogue(ELBP.strings['changecolours'], d, opt);
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
            };

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
                $('#elbp_tt_content').html('<div class="elbp_centre"><img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" /></div>');
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
            ELBP.dialogue(ELBP.strings['lessoninfo'], info);

        }

    };


    // Tutorials Plugin
    ELBP.Tutorials = {

        // Load a display type
        load_display: function(type, el){
            var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID }
            ELBP.ajax("Tutorials", "load_display_type", params, function(d){
                $('#elbp_tutorials_content').html(d);
                ELBP.set_view_link(el);
            }, function(d){
                $('#elbp_tutorials_content').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
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

            var confirm = window.confirm(ELBP.strings['areyousureremovetarget']);
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
                $('#new_tutorial_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        edit_tutorial: function(id){

            var params = { type: "edit", tutorialID: id, studentID: ELBP.studentID, courseID: ELBP.courseID }
            ELBP.ajax("Tutorials", "load_display_type", params, function(d){
                $('#elbp_tutorials_content').html(d);
            }, function(d){
                $('#elbp_tutorials_content').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        delete_tutorial: function(id){

            var confirm = window.confirm(ELBP.strings['areyousuredelete']);
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
                $('#new_tutorial_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        }

    };


    // Attachments Plugin
    ELBP.Attachments = {

        // Load a display type
        load_display: function(type, el){
            var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID }
            ELBP.ajax("Attachments", "load_display_type", params, function(d){
                $('#elbp_attachments_content').html(d);
                ELBP.set_view_link(el);
            }, function(d){
                $('#elbp_attachments_content').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });
        },

        delete_attachment: function(id){

            var confirm = window.confirm(ELBP.strings['areyousuredelete']);
            if (confirm){

                var data = {id: id, studentID: ELBP.studentID, courseID: ELBP.courseID};

                ELBP.ajax("Attachments", "delete", data, function(d){
                    eval(d);
                }, function(d){
                    $('#attachments_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
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


    };


    // Course Reports plugin
    ELBP.CourseReports = {

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
                $('#elbp_course_reports_content').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });
        },

        save: function(data){

            $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});

            ELBP.ajax("CourseReports", "save_report", data, function(d){
                eval(d);
            }, function(d){
                $('#elbp_popup').scrollTop(0);
                $('#new_course_report_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
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

            var confirm = window.confirm(ELBP.strings['areyousuredelete']);
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
                $('#elbp_periodical_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        save_periodical: function(data){

            $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});

            ELBP.ajax("CourseReports", "save_periodical", data, function(d){
                eval(d);
            }, function(d){
                $('#elbp_periodical_saving_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        edit_periodical: function(id){

            var params = {studentID: ELBP.studentID, courseID: ELBP.courseID, reportID: id};

            ELBP.ajax("CourseReports", "edit_periodical", params, function(d){
                $('#elbp_periodical_output').html(d);
            }, function(d){
                $('#elbp_periodical_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        delete_periodical: function(id){

            var params = {studentID: ELBP.studentID, courseID: ELBP.courseID, reportID: id};

            ELBP.ajax("CourseReports", "delete_periodical", params, function(d){
                eval(d);
            }, function(d){
                $('#elbp_periodical_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        load_periodical: function(id){

            ELBP.load_expanded('CourseReports', function(){
                $(document).ready( function(){
                    setTimeout("ELBP.CourseReports.load_display('periodical_report', false, false, "+id+");", 2500);
                } );
            });

        }

    };


    // Comments
    ELBP.Comments = {

        // Load a display type
        load_display: function(type, el){
            var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID }
            ELBP.ajax("Comments", "load_display_type", params, function(d){
                $('#elbp_comments_content').html(d);
                ELBP.set_view_link(el);
            }, function(d){
                $('#elbp_comments_content').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });
        },

        save: function(form){

            var data = form.serialiseObject();
            $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});

            ELBP.ajax("Comments", "save_comment", data, function(d){
                eval(d);
            }, function(d){
                $('#elbp_popup').scrollTop(0);
                $('#new_comment_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        edit_comment: function(id){

            var params = { type: "edit", commentID: id, studentID: ELBP.studentID, courseID: ELBP.courseID }
            ELBP.ajax("Comments", "load_display_type", params, function(d){
                $('#elbp_comments_content').html(d);
            }, function(d){
                $('#elbp_comments_content').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        delete_comment: function(id){

            var confirm = window.confirm(ELBP.strings['areyousuredelete']);
            if (confirm){
                var params = { studentID: ELBP.studentID, courseID: ELBP.courseID, commentID: id };
                ELBP.ajax("Comments", "delete_comment", params, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_comments_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
                });
            }


        },

        // Load a particular tutorial from thye summary view
        load_comment: function(id){

            ELBP.load_expanded('Comments', function(){

                var show = function(){

                  var scrollTop = $('#elbp_comment_'+id).offset().top - $('#elbp_comment_'+id).height();
                  $('#comment_content_'+id).slideDown();
                  $('div#elbp_popup').scrollTop(0);
                  $('div#elbp_popup').animate({
                    scrollTop: scrollTop
                  }, 2000);
                };

                setTimeout(show, 1000);

            });

        },

        // Mark comment as resolved
        mark_resolved: function(id, val){

            var params = {studentID: ELBP.studentID, courseID: ELBP.courseID, commentID: id, val: val};

            ELBP.ajax("Comments", "mark_resolved", params, function(d){
                eval(d);
            }, function(d){
                $('#elbp_popup').scrollTop(0);
                $('#elbp_comments_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        }


    };


    // Additional Support Plugin
    ELBP.AdditionalSupport = {

        // Load a display type
        load_display: function(type, el){
            var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID }
            ELBP.ajax("AdditionalSupport", "load_display_type", params, function(d){
                $('#elbp_additional_support_content').html(d);
                ELBP.set_view_link(el);
            }, function(d){
                $('#elbp_additional_support_content').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
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

            var confirm = window.confirm(ELBP.strings['areyousureremovetarget']);
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
                $('#new_additional_support_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
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
                $('#elbp_additional_support_content').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        delete_session: function(id){

            var confirm = window.confirm(ELBP.strings['areyousuredelete']);
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
                $('#additional_support_target_output_session_'+sessionID).html('<img src="'+ELBP.wwww+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },


        update_target_status: function(statusID, targetID, sessionID){

            var params = {studentID: ELBP.studentID, courseID: ELBP.courseID, sessionID: sessionID, targetID: targetID, statusID: statusID};

            ELBP.ajax("AdditionalSupport", "update_target_status", params, function(d){
                eval(d);
            }, function(d){
                $('#additional_support_target_output_session_'+sessionID).html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
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
                    $('#elbp_comment_add_output_comment_'+parentID).html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
                } else {
                    $('#elbp_comment_add_output_'+id).html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
                }

                $('#elbp_additional_support_content input[type="button"]').attr('disabled', 'disabled');

            });

        },

        delete_comment: function(sessionID, commentID){

            var confirm = window.confirm(ELBP.strings['areyousuredelete']);
            if (confirm){
                var params = { sessionID: sessionID, commentID: commentID };
                ELBP.ajax("AdditionalSupport", "delete_comment", params, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_comment_add_output_'+sessionID).html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
                });
            }

        },

        save_attribute: function(attribute, value){

            var params = {studentID: ELBP.studentID, courseID: ELBP.courseID, attribute: attribute, value: value};

            ELBP.ajax("AdditionalSupport", "save_attribute", params, function(d){
                eval(d);
            }, function(d){
                $('#elbp_additional_support_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
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
                $('#new_additional_support_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        }


    };


    // Challenges Plugin
    ELBP.Challenges = {

        save: function(form){

            var data = form.serialiseObject();
            $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});

            ELBP.ajax("Challenges", "save", data, function(d){
                eval(d);
            }, function(d){
                $('#challenges_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });


        }

    };


    // Learning Styles Plugin
    ELBP.LearningStyles = {

        // Load a display type
        load_display: function(type){
            var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID }
            ELBP.ajax("LearningStyles", "load_display_type", params, function(d){
                $('#elbp_learning_styles_content').html(d);
            }, function(d){
                $('#elbp_learning_styles_content').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });
        },

        save: function(form){

            var data = form.serialiseObject();
            $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});

            ELBP.ajax("LearningStyles", "save", data, function(d){
                eval(d);
            }, function(d){
                $('#elbp_popup').scrollTop(0);
                $('#elbp_learning_styles_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        }


    };


    // Custom Plugins
    ELBP.Custom = {

        // Load a display type
        load_display: function(plugin, type, el){
            var params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID }
            ELBP.ajax(plugin, "load_display_type", params, function(d){
                $('#elbp_custom_content').html(d);
                ELBP.set_view_link(el);
            }, function(d){
                $('#elbp_custom_content').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });
        },


        save_single: function(plugin, form){

            var data = form.serialiseObject();
            $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});

            ELBP.ajax(plugin, "save_single", data, function(d){
                eval(d);
            }, function(d){
                $('#elbp_popup').scrollTop(0);
                $('#custom_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
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
                $('#custom_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        delete_incremental_item: function(plugin, id){

            var confirm = window.confirm(ELBP.strings['areyousuredelete']);
            if (confirm){

                var params = { studentID: ELBP.studentID, courseID: ELBP.courseID, itemID: id };
                ELBP.ajax(plugin, "delete_incremental_item", params, function(d){
                    eval(d);
                    ELBP.Custom.refresh_incremental(plugin);
                }, function(d){
                    $('#elbp_popup').scrollTop(0);
                    $('#custom_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
                });

            }


        },

        refresh_incremental: function(plugin){

            var params = { studentID: ELBP.studentID, courseID: ELBP.courseID };
            ELBP.ajax(plugin, "refresh_incremental", params, function(d){
                $('#elbp_custom_plugin_items').html( d );
            }, function(d){
                $('#elbp_popup').scrollTop(0);
                $('#elbp_custom_plugin_items').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        save_multi: function(plugin, form){

            var data = form.serialiseObject();
            $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID});

            ELBP.ajax(plugin, "save_multi", data, function(d){
                eval(d);
            }, function(d){
                $('#elbp_popup').scrollTop(0);
                $('#custom_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        delete_item: function(plugin, id){

            var confirm = window.confirm(ELBP.strings['areyousuredelete']);
            if (confirm){

                var params = { studentID: ELBP.studentID, courseID: ELBP.courseID, itemID: id };
                ELBP.ajax(plugin, "delete_item", params, function(d){
                    eval(d);
                }, function(d){
                    $('#elbp_popup').scrollTop(0);
                    $('#custom_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
                });

            }


        },

        edit_item: function(plugin, id){

            var params = { type: "edit", itemID: id, studentID: ELBP.studentID, courseID: ELBP.courseID }
            ELBP.ajax(plugin, "load_display_type", params, function(d){
                $('#elbp_custom_content').html(d);
            }, function(d){
                $('#elbp_popup').scrollTop(0);
                $('#elbp_custom_content').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },


    };



    // Badges
    ELBP.toggleBadges = function(){

        if ( $('#badges_content').length > 0 )
        {

            $('#badges_content').slideToggle();

            if ( $('#toggle-badges').length > 0 )
            {

                var src = $('#toggle-badges').attr('src');
                if (src.indexOf('switch_plus') > 0){
                    src = src.replace('switch_plus', 'switch_minus');
                    ELBP.addCookie('hide_elbp_badges', 0);
                } else {
                    src = src.replace('switch_minus', 'switch_plus');
                    ELBP.addCookie('hide_elbp_badges', 1);
                }

                $('#toggle-badges').attr('src', src);

            }

        }

    };

    ELBP.getCookie = function(cookie){

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

    };

    ELBP.addCookie = function(cookie, value){

        var str = "";
        str += (cookie + "=" + value);
        document.cookie = str;

    };




    // Bind events
    ELBP.bind = function(){

      console.log('binding ELBP');

        // Resize the popup
        ELBP.resize_popup();
        $(window).resize( function(){
            ELBP.resize_popup();
        } );

        $('a.elbp_toggle').unbind('click');
        $('a.elbp_toggle').bind('click', function(e){

          var toggle = $(this).attr('toggle');
          $(toggle).toggle();

          e.preventDefault();

        });

        $('select.elbp_toggle').unbind('change');
        $('select.elbp_toggle').bind('change', function(e){

          var toggle = $(this).attr('toggle');
          $(toggle).toggle();

          e.preventDefault();

        });


        // Destroy and reapply datepickers
        $('.elbp_datepicker').removeClass('hasDatepicker');
        $('.elbp_datepicker').datepicker('destroy');

        $('.elbp_datepicker').datepicker( {dateFormat: 'dd-mm-yy', changeMonth: true, changeYear: true} );
        $('.elbp_datepicker_no_past').datepicker( {dateFormat: 'dd-mm-yy', changeMonth: true, changeYear: true, minDate: 0} );
        $('.elbp_datepicker_no_future').datepicker( {dateFormat: 'dd-mm-yy', changeMonth: true, changeYear: true, maxDate: 0} );
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


        // Make popups draggable
        $('#elbp_popup').draggable( { handle: "div.elbp_popup_header" } );

        // Files
        $('.elbp_fileupload').each( function(){

          var id = $(this).attr('id');

          $(this).fileupload({
              url: M.cfg.wwwroot + '/blocks/elbp/upload.php',
              dataType: 'json',
              formData: {sid: '<?= $obj->getStudentID(); ?>'},
              send: function(e, data){

                // Remove output messages and values
                $('#output_messages-'+id).html('');
                $('#filevalue-'+id).html('');

                // Show progress
                $('#progress-'+id).show();

              },
              done: function (e, data) {

                if (data.result.success === true){
                    $('#output_messages-'+id).append('<div class="elbp_success_box"><span>'+ELBP.strings['uploaded']+': '+data.result.title+'</span></div>');
                } else {
                    $('#output_messages-'+id).append('<div class="elbp_err_box"><span>'+data.result.error+'</span></div>');
                }

                $('#progress-'+id).hide();
                $('#progress-amount-'+id).css('width', 0);
                $('#hidden-file-'+id).val(data.result.tmp);

              },
              fail: function(e, data){
                  $('#output_messages-'+id).html('<div class="elbp_err_box"><span>'+ELBP.strings['uploads:unknownerror']+'</span></div>');
                  $('#progress-'+id).hide();
              },
              progressall: function (e, data) {
                  var progress = parseInt(data.loaded / data.total * 100, 10);
                  $('#progress-amount-'+id).css(
                      'width',
                      progress + '%'
                  );
              }
          }).prop('disabled', !$.support.fileInput)
              .parent().addClass($.support.fileInput ? undefined : 'disabled');

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

        // Append notice to required fields
        $('.elbp_required_field').remove();
        $('[validation*="REQUIRED"]').after('<span class="elbp_required_field" title="'+ELBP.strings['requiredfield']+'">*</span>');

        // Add tooltips
        $('.target_name_tooltip').tooltip();

        // Dock hover images
        $('.dock_plugin_close img').mouseover( function(){
            var src = $(this).attr('src');
            $(this).attr('src', src.replace('close_tiny.png', 'close_tiny_hover.png'));
        } );

        $('.dock_plugin_close img').mouseout( function(){
            var src = $(this).attr('src');
            $(this).attr('src', src.replace('close_tiny_hover.png', 'close_tiny.png'));
        } );

        // Check for cookie to hide badges
        if ( ELBP.getCookie('hide_elbp_badges') == 1 ){
            if ( $('#toggle-badges').length > 0 ){
                var src = $('#toggle-badges').attr('src');
                src = src.replace('switch_minus', 'switch_plus');
                $('#toggle-badges').attr('src', src);
            }
        }

        // Traffic light click to view
        $('.elbp_progress_traffic_light').off('click');
        $('.elbp_progress_traffic_light').on('click', function(){

            var rankNum = $(this).attr('rankNum');

            // If already shown, hide it
            if ( $('#elbp_progress_traffic_light_desc_' + rankNum).css('display') == 'none' ){

                $('.elbp_progress_traffic_light_desc').hide();
                $('#elbp_progress_traffic_light_desc_' + rankNum).show();

            } else {

                $('.elbp_progress_traffic_light_desc').hide();

            }


        } );

        // Manual progress click to change
        $('.elbp_set_student_manual_progress').off('click');
        $('.elbp_set_student_manual_progress').on('click', function(){

            var rank = $(this).attr('rankNum');
            var params = { studentID: ELBP.studentID, rank: rank };
            ELBP.ajax(0, "set_student_manual_progress", params, function(d){
                eval(d);
                $('#elbp_progress_traffic_loading').html('');
            }, function(d){
                $('#elbp_progress_traffic_loading').html('<img src="'+M.cfg.wwwroot+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        });


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


        // Drag and drop file uploads
        $('.file-drop-zone').off('dragenter');
        $('.file-drop-zone').on('dragenter', function(e){
            $(this).addClass('in');
        });

        $('.file-drop-zone').off('dragleave dragend');
        $('.file-drop-zone').on('dragleave dragend', function(e){
            $(this).removeClass('in');
        });

        // Tooltips
        ELBP.apply_tooltip();


        // Settings
        var loadAlertsTable = function(type, value){

          if ( (type == 'course' || type == 'student') && value == '' ){
              $('#alerts_div').html('');
              return;
          }

          ELBP.ajax(null, 'get_alerts_table', {type: type, value: value}, function(data){
              $('#alerts_div').html(data);
          }, function(){
              $('#alerts_div').html('<img src="'+M.cfg.wwwroot+'/blocks/elbp/pix/loader.gif" alt="loading" />');
          });

        };

        // Change links
        $('.elbp_settings_change_alert_type').unbind('click');
        $('.elbp_settings_change_alert_type').bind('click', function(e){

          var type = $(this).attr('type');

          $('#alerts_div').html('');

          $('.switch_alerts').hide();
          $('.switch_alerts').each( function(){
              if ( $(this).attr('type') == type ){
                  $(this).show();
              }
          } );

          // Mentees or AddSup, just load the table
          if (type == 'mentees' || type == 'addsup'){
              loadAlertsTable(type);
          }

          e.preventDefault();

        });

        // Change dropdowns
        $('.switch_alerts').unbind('change');
        $('.switch_alerts').bind('change', function(){

            var type = $(this).attr('type');
            var value = $(this).val();

            loadAlertsTable(type, value);

        });

        $('.elbp_remove').off('click');
        $('.elbp_remove').on('click', function(e){

          var target = $(this).attr('remove');

          if (target === 'parent-row'){
            target = $($(this).parents('tr')[0]);
          }

          if (target !== undefined){
            $(target).remove();
          }

          e.preventDefault();

        });


        $('.elbp_add_progress_colour').off('click');
        $('.elbp_add_progress_colour').on('click', function(e){

          var output = "";
          output += "<tr>";
              output += "<td><input type='number' name='progress_ranks[]' value='' placeholder='1' /></td>";
              output += "<td><input type='text' name='progress_titles[]' value='' placeholder='At Risk' /></td>";
              output += "<td><input type='color' name='progress_colours[]' value='' placeholder='#000000' /></td>";
              output += "<td><input type='text' name='progress_desc[]' value='' placeholder='You are at risk of failing your course' /></td>";
              output += "<td><a href='#' class='elbp_remove' remove='parent-row'><img src='"+M.util.image_url('t/delete')+"' /></a></td>";
          output += "</tr>";
          $('#elbp_manual_progress_colours').append(output);

          ELBP.bind();

          e.preventDefault();

        });



        var rebuild_plugin_path = function(name){

          var type = $('#elbp_install_plugin_change_type').val();
          var external = $('#elbp_install_plugin_change_external_type').val();

          dir = '/';

          if (type == 'core'){
              dir = dir + 'blocks/elbp/plugins/';
          }
          else if (type == 'external' && external != 'other'){
              dir = dir + external + '/';
          }

          var prefix = (type === 'external' && name !== undefined && name.indexOf('elbp_') < 0) ? 'elbp_' : '';
          dir = dir + name + '/' + prefix + name + '.class.php';

          // Other
          if (type == 'external' && external == 'other'){
              dir = '/path/to/plugin/file.php';
          }

          $('#elbp_install_plugin_path').val(dir);

          return dir;

        };


        // Change plugin type for install (core or external)
        $('#elbp_install_plugin_change_type').off('change');
        $('#elbp_install_plugin_change_type').on('change', function(){

          var type = $(this).val();

          $('#elbp_install_plugin_change_external_type').parent().hide();
          $('#elbp_install_plugin_name').parent().hide();
          $('#elbp_install_plugin_path').parent().hide();
          $('#elbp_install_plugin_path').val('');
          $('#elbp_install_plugin_submit').hide();

          if (type === 'core'){

            $('#elbp_install_plugin_name').parent().show();
            $('#elbp_install_plugin_name').html('<option value=""></option>');

            var plugins = $('hidden > core > name');
            $.each(plugins, function(i, v){
              $('#elbp_install_plugin_name').append('<option value="'+$(v).text()+'">'+$(v).text()+'</option>');
            });

          }

          else if(type === 'external'){

            $('#elbp_install_plugin_change_external_type').parent().show();

          }

        });

        // Select plugin name
        $('#elbp_install_plugin_name').off('change');
        $('#elbp_install_plugin_name').on('change', function(){

          var name = $(this).val();

          if (name === ''){

            $('#elbp_install_plugin_path').parent().hide();
            $('#elbp_install_plugin_path').val('');
            $('#elbp_install_plugin_submit').hide();

          } else {

            rebuild_plugin_path(name);

            $('#elbp_install_plugin_path').parent().show();
            $('#elbp_install_plugin_submit').show();

          }

        });

        // Change external type
        $('#elbp_install_plugin_change_external_type').off('change');
        $('#elbp_install_plugin_change_external_type').on('change', function(){

          var type = $(this).val();

          $('#elbp_install_plugin_name').parent().hide();
          $('#elbp_install_plugin_submit').hide();
          $('#elbp_install_plugin_path').parent().hide();
          $('#elbp_install_plugin_path').val('');

          if (type === 'other'){

            rebuild_plugin_path();
            $('#elbp_install_plugin_path').parent().show();
            $('#elbp_install_plugin_submit').show();

          } else {

            $('#elbp_install_plugin_name').parent().show();
            $('#elbp_install_plugin_name').html('<option value=""></option>');

            var plugins = $('hidden > external > ' + type + ' > name');
            $.each(plugins, function(i, v){
              $('#elbp_install_plugin_name').append('<option value="'+$(v).text()+'">'+$(v).text()+'</option>');
            });


          }

        });

        // Add new plugin group to a layout
        $('.elbp_add_plugin_group').off('click');
        $('.elbp_add_plugin_group').on('click', function(e){

          var layoutNum = $(this).attr('layout');
          var numOfGroups = $('.layout_group_'+layoutNum).length;
          var groupNum = numOfGroups + 1;

          var output = "";

          output += "<tr class='layout_group_"+layoutNum+"_"+groupNum+"_row'>";
              output += "<td>";
                  output += "<a href='#' class='elbp_remove' remove='.layout_group_"+layoutNum+"_"+groupNum+"_row'><img src='"+M.cfg.wwwroot+"/blocks/elbp/pix/remove.png' alt='"+ELBP.strings.delete+"' /></a>";
              output += "</td>";
          output += "</tr>";

          output += "<tr class='layout_group_"+layoutNum+" layout_group_"+layoutNum+"_"+groupNum+"_row'>";
              output += "<td>"+ELBP.strings.name+"</td>";
              output += "<td>";
                  output += "<input type='hidden' name='plugin_layouts_groups_id["+layoutNum+"]["+groupNum+"]' value='' />";
                  output += "<input type='text' name='plugin_layouts_groups_name["+layoutNum+"]["+groupNum+"]' value='' />";
              output += "</td>";
          output += "</tr>";

          output += "<tr class='layout_group_"+layoutNum+"_"+groupNum+"_row'>";
              output += "<td>"+ELBP.strings.enabled+"</td>";
              output += "<td>";
                  output += "<input type='checkbox' name='plugin_layouts_groups_enabled["+layoutNum+"]["+groupNum+"]' value='1' />";
              output += "</td>";
          output += "</tr>";

          output += "<tr class='layout_group_"+layoutNum+"_"+groupNum+"_row'>";
              output += "<td>"+ELBP.strings.plugins+"</td>";
              output += "<td>";
                  output += "<small><a href='#' class='elbp_toggle_add_plugins_to_group elbp_add_layout_plugins_link_layout_"+layoutNum+"' layout='"+layoutNum+"' groupID='D"+groupNum+"' groupNum='"+groupNum+"' isAdding='0' id='elbp_add_layout_plugins_link_"+layoutNum+"_"+groupNum+"'>"+ELBP.strings.startaddingplugins+"</a></small>";
                  output += "<div class='elbp_layout_plugins_dump elbp_layout_plugins_dump_"+layoutNum+"' id='elbp_layout_plugins_dump_"+layoutNum+"_"+groupNum+"' groupNum='"+groupNum+"' layoutNum='"+layoutNum+"'><ul></ul></div>";
              output += "</td>";
          output += "</tr>";

           output += "<tr class='layout_group_"+layoutNum+"_"+groupNum+"_row'>";
              output += "<td colspan='2'><br></td>";
          output += "</tr>";

          $('#elbp_plugin_layout_'+layoutNum).append(output);

          ELBP.bind();

          e.preventDefault();

        });

        $('.elbp_remove_plugin_from_group').off('click');
        $('.elbp_remove_plugin_from_group').on('click', function(e){

          var layoutNum = $(this).attr('layout');
          var pluginID = $(this).attr('pluginID');

          // Remove list element
          $(this).parent().remove();

          // Show plugin in layout again so we can add it to a different group
          $('#plugin_'+pluginID+'_'+layoutNum).show();

          e.preventDefault();

        });

        $('.elbp_toggle_add_plugins_to_group').off('click');
        $('.elbp_toggle_add_plugins_to_group').on('click', function(e){

          var layoutNum = $(this).attr('layout');
          var groupNum = $(this).attr('groupNum');

          // Get current value
          var isAdding = $('#elbp_add_layout_plugins_link_'+layoutNum+'_'+groupNum).attr('isAdding');

          // Reset all
          $('.elbp_add_layout_plugins_link_layout_'+layoutNum).attr('isAdding', 0);
          $('.elbp_add_layout_plugins_link_layout_'+layoutNum).text( ELBP.strings.startaddingplugins );
          $('.elbp_layout_plugins_dump_'+layoutNum+' ul').removeClass('active');

          if (isAdding == 0){
              $('#elbp_add_layout_plugins_link_'+layoutNum+'_'+groupNum).attr('isAdding', 1);
              $('#elbp_add_layout_plugins_link_'+layoutNum+'_'+groupNum).text( ELBP.strings.stopaddingplugins );
              $('#elbp_layout_plugins_dump_'+layoutNum+'_'+groupNum+' ul').addClass('active');
          }

          e.preventDefault();

        });

        $('.elbp_add_new_plugin_layout').off('click');
        $('.elbp_add_new_plugin_layout').on('click', function(e){

          var numOfLayouts = $('.elbp_plugin_layouts').length;
          var layoutNum = numOfLayouts + 1;
          var plugins = $('hidden > plugins > plugin');

          var output = "";
          output += "<table id='elbp_plugin_layout_"+layoutNum+"' class='elbp_plugin_layouts'>";
              output += "<tr>";
                  output += "<td>";
                      output += "<a href='#' class='elbp_remove' remove='#elbp_plugin_layout_"+layoutNum+"'><img src='"+M.cfg.wwwroot+"/blocks/elbp/pix/remove.png' alt='"+ELBP.strings.delete+"' /></a>";
                  output += "</td>";
              output += "</tr>";
              output += "<tr>";
                  output += "<td>"+ELBP.strings.layoutname+"</td>";
                  output += "<td><input type='hidden' name='plugin_layouts_id["+layoutNum+"]' value='' /><input type='text' name='plugin_layouts_name["+layoutNum+"]' value='' /></td>";
              output += "</tr>";
              output += "<tr>";
                  output += "<td>"+ELBP.strings.default+"</td>";
                  output += "<td><input type='checkbox' name='plugin_layouts_default["+layoutNum+"]' value='1' /></td>";
              output += "</tr>";
              output += "<tr>";
                  output += "<td>"+ELBP.strings.enabled+"</td>";
                  output += "<td><input type='checkbox' name='plugin_layouts_enabled["+layoutNum+"]' value='1' /></td>";
              output += "</tr>";
              output += "<tr>";
                  output += "<td colspan='2'>";

                    $.each(plugins, function(i, el){

                      var id = $(el).find('id').text();
                      var name = $(el).find('name').text();
                      var bg = $(el).find('bg').text();
                      var font = $(el).find('font').text();
                      var custom = $(el).find('custom').text();

                      var isCustom = (custom == '1') ? 'c' : '';

                      output += "<div class='elbp_add_plugin_to_group elbp_layout_plugin_name' id='plugin_"+isCustom+id+"_"+layoutNum+"' layoutNum='"+layoutNum+"' pluginID='"+id+"' style='background-color:"+bg+";color:"+font+";' >"+name+"</div>";

                    });

                  output += "</td>";
              output += "</tr>";

              output += "<tr>";
                  output += "<th colspan='2'>"+ELBP.strings.groups+" <a href='#' class='elbp_add_plugin_group' layout='"+layoutNum+"'><img src='"+M.cfg.wwwroot+"/blocks/elbp/pix/add_small.png' alt='"+ELBP.strings.addnewgroup+"' /></a></th>";
              output += "</tr>";

          output += "</table>";

          $('#elbp_plugin_layouts_form').append(output);

          ELBP.bind();

          e.preventDefault();

        });

        $('.elbp_add_plugin_to_group').off('click');
        $('.elbp_add_plugin_to_group').on('click', function(e){

          var layoutNum = $(this).attr('layoutNum');
          var pluginID = $(this).attr('pluginID');

          var groupNum = false;
          var groups = $('.elbp_add_layout_plugins_link_layout_'+layoutNum);

          $.each(groups, function(i, el){

              var isAdding = $(el).attr('isAdding');
              if (isAdding == 1){
                  groupNum = $(el).attr('groupNum');
                  return;
              }

          });

          if (!groupNum){
              alert( ELBP.strings.plschooseplugingroup );
              return false;
          }

          // Hide plugin name div
          var divElement = $('#plugin_'+pluginID+'_'+layoutNum);
          $('#plugin_'+pluginID+'_'+layoutNum).hide();

          // Add list item to group
          $('#elbp_layout_plugins_dump_'+layoutNum+'_'+groupNum+' ul').append('<li pluginID="'+pluginID+'">'+$(divElement).text()+' <a href="#" class="elbp_remove_plugin_from_group" layout="'+layoutNum+'" pluginID="'+pluginID+'"><img src="'+M.cfg.wwwroot+'/blocks/elbp/pix/close_tiny.png" alt="'+ELBP.strings.remove+'" /></a><input type="hidden" name="layout_group_plugins['+layoutNum+']['+groupNum+'][]" value="'+pluginID+'" /></li>');

          ELBP.bind();

        });


        // Bind them as sortable
        $('.elbp_layout_plugins_dump ul').sortable( {
            placeholder: "elbp_layout_plugin_placeholder"
        } );

        $('.elbp_layout_plugins_dump ul').disableSelection();

        // Terrible quick-fix until I actually move all of the attribute form js into an AMD module
        $('#elbp_attributes_table tbody').sortable({
            containerSelector: 'tbody',
            itemSelector: 'tr',
            cancel: '.noSort',
            update: function(event, ui){
                resortNumbers('elbp_attributes_table tbody');
            }
        });



        // Remove loading overlay
        $('#elbp_loading_overlay').fadeOut();


    };









    // Set ELBP into global space
    window.ELBP = ELBP;

    // Set onto the client for access in other scripts
    client.scripts = ELBP;

    client.log = function(log){
        console.log('[ELBP] ' + new Date().toTimeString().split(' ')[0] + ': ' + log );
    };


    // Initalise scripts
    client.init = function(){

        client.log('Loading initial state');
        ELBP.ajax(0, 'get_initial_state');

    };


    // Initalise View ELBP page
    client.view = function(studentID, courseID, group){

        client.log('Loading student and course into ELBP');
        ELBP.studentID = studentID;
        ELBP.courseID = courseID;

        if (group){
            client.log('Loading default plugin group');
            ELBP.load('group', group.id);
        }

    };







    // Keyboard shortcuts
    var keyMap = {16: false, 17: false, 220: false}; // Ctrl + Shift + |
    var keyMapAdvanced = { 16: false, 17: false, 192: false }; // Ctrl + Shift + @
    var keyMapClear = {16: false, 17: false, 13: false}; // Ctrl + Shift + Enter


    $(document).keydown(function(e){

        // Centre popup
        if (e.keyCode in keyMap){
            keyMap[e.keyCode] = true;
            if (keyMap[16] && keyMap[17] && keyMap[220] && $('#elbp_popup').css('display') != 'none'){
                $('#elbp_popup').css('top', '10%').css('left', '10%');
                ELBP.resize_popup();
            }
        }

        // Bring up command line
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

        // Clear all popups and blankets
        if (e.keyCode in keyMapClear){
            keyMapClear[e.keyCode] = true;
            if (keyMapClear[16] && keyMapClear[17] && keyMapClear[13]){
                $('#elbp_popup').hide();
                ELBP.hide('#elbp_blanket');
                $('body').css('overflow', 'auto');
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


    // Command line inputs
    $('#cmd_input #input').keydown( function(e){

        if (e.keyCode == 38){

            if ( (ELBP.commandPointer - 1) >= 0){
                ELBP.commandPointer--;
                var command = ELBP.savedCommands[ELBP.commandPointer];
                $('#cmd_input #input').val('');
                $('#cmd_input #input').val(command);
            }

        } else if (e.keyCode == 40){

            if ( (ELBP.commandPointer + 1) < ELBP.savedCommands.length){
                ELBP.commandPointer++;
                var command = ELBP.savedCommands[ELBP.commandPointer];
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









    return client;


});