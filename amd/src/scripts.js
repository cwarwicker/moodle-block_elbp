/** min **/
define(['jquery', 'jqueryui', 'block_elbp/minicolors', 'block_elbp/raty', 'block_elbp/tinytbl'], function($, ui, miniColors, raty, tinytbl) {
       
    // Raty image path
    $.fn.raty.defaults.path = M.cfg.wwwroot + '/blocks/elbp/js/jquery/plugins/raty/images';

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
    ELBP.tempData = null;
    ELBP.studentID = null;
    ELBP.courseID = null;
    ELBP.pluginGroup = null;
    ELBP.pluginIcons = {};
    ELBP.strings = {};

    // Hide an element
    ELBP.hide = function(el){
        $(el).hide();
    };

    // Show an element
    ELBP.show = function(el){
        $(el).show();
    };

    ELBP.ajax = function(plugin, action, params, callback, callBefore){

        let url = ELBP.www + "/blocks/elbp/js/ajaxHandler.php";

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

                // Process data for hidden JS
                let e = ELBP.process_data_eval(d);

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

        let pat = /\[ELBP:JS\](.+?)\[\/ELBP:JS\]/gs;
        let matches = data.match(pat);

        if(matches)
        {

            data = data.replace(pat, "");
            let toEval = "";
            for(let i = 0; i < matches.length; i++)
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

        let params = {
            type: type,
            host: host,
            user: user,
            pass: pass,
            db: db
        };

        let el = '#elbp_config_test_conn';
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
        let inputs = $('#elbp_popup :input');
        $(inputs).each( function(){
            let tagName = $(this).prop('tagName');
            if (tagName != 'BUTTON' && $(this).attr('type') != 'submit'){

                let id = $(this).attr('id');
                let name = $(this).attr('name');
                let val = $(this).val();
                let chkd = ( $(this).is(':checked') ) ? true : false ;
                let content = '';

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
                    let tmpVar = ELBP.savedInputs[pluginName][name];
                    ELBP.savedInputs[pluginName][name] = [ tmpVar, {value: val, checked: chkd} ];
                } else if (ELBP.savedInputs[pluginName][name] != undefined && ELBP.savedInputs[pluginName][name] instanceof Object && ELBP.savedInputs[pluginName][name][0] instanceof Object){
                    let cnt = Object.keys(ELBP.savedInputs[pluginName][name]).length;
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

                let v = o.value;
                let fullHtml = undefined;

                // First check for any texteditors as we need to do iframey stuff
                let type = $.type(v);
                if (type == 'object' && $(v).find('body').length > 0){
                    v = $(v).find('body').html();
                }

                key = key.replace(/"/g, '&quot;');
                let tmpVar = $('input[name="'+key+'"], textarea[name="'+key+'"], select[name="'+key+'"]', ELBP.savedStates[pluginName]);
                let tagName = $(tmpVar).prop('tagName');

                // If we found more than one with the same name, loop through
                if (tmpVar.length > 1)
                {

                    for (let i = 0; i < tmpVar.length; i++)
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


            let html = $(ELBP.savedStates[pluginName]).html();


            // Put the elbp_popup div around it again
            let wrapperDiv = $(ELBP.savedStates[pluginName]).first();
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
                    let explode = id.split("_");

                    // Set last item in arrya to "parent" instead of "ifr"
                    explode[ explode.length - 1 ] = "parent";

                    // Join the array back into a string for the span's id
                    let spanID = explode.join("_");

                    // Also get the id of the actual textarea
                    let editorID = spanID.replace("_parent", "");

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

    // Load
    ELBP.load = function(type, id){
        let params = {
            type: type,
            id: id,
            student: ELBP.studentID,
            course: ELBP.courseID
        }
        ELBP.ajax(0, 'load_template', params, function(d){
            $('#elbp_summary_boxes').html(d);
        }, function(d){
            $('#elbp_summary_boxes').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
        });
    };

    // Load the expanded view of a plugin
    ELBP.load_expanded = function(plugin, callAfter){

        let params = {
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
                let height = $(this).height();
                let scrollHeight = $(this)[0].scrollHeight;
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

        let wrapperObj = $(el).siblings('.elbp_summary_box_wrapper')[0];

        let scrollHeight = wrapperObj.scrollHeight + 10;
        $(wrapperObj).animate({height: scrollHeight}, 300);

        // Change expand link to contract
        $(el).attr('onclick', 'ELBP.contract(this);return false;');
        $(el).attr('title', ELBP.strings['contract']);
        let img = $(el).children('img');
        $(img).attr('src', $(img).attr('src').replace('switch_plus', 'switch_minus'));

    };

    ELBP.contract = function(el){

        let max_height = 225;

        let wrapperObj = $(el).siblings('.elbp_summary_box_wrapper')[0];

        $(wrapperObj).animate({height: max_height}, 300);

        $(el).slideUp('slow');

        // Change expand link to expand
        $(el).attr('onclick', 'ELBP.expand(this);return false;');
        $(el).attr('title', ELBP.strings['expand']);
        let img = $(el).children('img');
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
        let div = $('#elbp_popup').parents('#page-content');
        let screenWidth = ($(div).width() / 100) * 90;
        let screenHeight = ($(div).height() / 100) * 90;
        $('#elbp_popup').css('height', screenHeight);
        $('#elbp_popup').css('width', screenWidth);

    };

    // Dialogue popup
    ELBP. dialogue = function(title, content, opt){

        $('#elbp_dialogue div').html(content);

        let options = {
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
                let img = ELBP.pluginIcons[plugin];
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

        if (param == ''){
            return false;
        }

        let params = { action: "load_users", param: param };
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

        let params = { action: "load_users", search: search };
        ELBP.ajax(0, "search_load_student", params, function(d){

            $('#switch_users_loading').html('');
            eval(d);

        }, function(){
            $('#switch_users_loading').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" style="height:10px;" />');
        });

    };

    // Load your settings popup
    ELBP.my_settings = function(userID){

        let opt = {
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

        let params = { student: ELBP.studentID, userID: userID }

        $('#mysettingsimg').attr('src', ELBP.www + '/blocks/elbp/pix/loader.gif');

        ELBP.ajax(0, "load_my_settings", params, function(d){
            ELBP.dialogue(ELBP.strings['mysettings'], d, opt);
            $('#mysettingsimg').attr('src', ELBP.www + '/blocks/elbp/pix/icons/cog.png');
        });

    };

    // Save your settings
    ELBP.save_my_settings = function(userID){

        let params = {};

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

            let params = { action: "choose_category", catID: catID, use: use };

            ELBP.ajax(0, "course_picker", params, function(data){
                $($(el).siblings('#category_picker_pick_courses').children('input')[0]).val('');
                $($(el).siblings('#category_picker_pick_courses').children('select')[0]).html(data);
                $(el).siblings('#category_picker_pick_courses').css('display', 'block');
            });

        },

        search_course: function(search, el, use){

            let catID = $($($(el).parents()[1]).children()[0]).val();
            let params = { action: "search_courses", catID: catID, search: search, use: use };
            ELBP.ajax(0, "course_picker", params, function(data){
                $($(el).siblings('select')[0]).html(data);
            });

        },

        add: function(el){

            let parents = $(el).parents()[0];
            let searchdiv = $(parents).siblings('.elbp_course_picker_search_div')[0];
            let coursediv = $(searchdiv).children('#category_picker_pick_courses')[0];
            let select = $(coursediv).children('select.course_list')[0];
            let courses = $(select).val();

            if (courses != null){

                let options = $(select).children('option');
                let coursenames = new Array();

                $.each(options, function(k,v){
                    coursenames[v.value] = v.innerHTML;
                });

                let chosendiv = $(parents).siblings('.elbp_course_picker_chosen_div')[0];
                let addselect = $(chosendiv).children('select.courseholder')[0];
                let hiddeninputs = $(addselect).siblings('.coursepickerhiddeninputs')[0];
                let fieldname = $(hiddeninputs).attr('fieldname');

                let addedoptions = $(addselect).children('option');
                let addedoptionvalues = new Array();
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

            let parents = $(el).parents()[0];
            let chosendiv = $(parents).siblings('.elbp_course_picker_chosen_div')[0];
            let addselect = $(chosendiv).children('select.courseholder')[0];
            let hiddeninputs = $(addselect).siblings('.coursepickerhiddeninputs')[0];

            let courses = $(addselect).val();

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

            let params = { action: "search_users", search: search };
            ELBP.ajax(0, "user_picker", params, function(data){
                $($(el).siblings('select')[0]).html(data);
            });

        },

        add: function(el){

            let parents = $(el).parents()[0];
            let searchdiv = $(parents).siblings('.elbp_user_picker_search_div')[0];
            let select = $(searchdiv).children('.user_list')[0];
            let users = $(select).val();

            if (users != null){

                let options = $(select).children('option');
                let usernames = new Array();

                $.each(options, function(k,v){
                    usernames[v.value] = v.innerHTML;
                });

                let chosendiv = $(parents).siblings('.elbp_user_picker_chosen_div')[0];
                let addselect = $(chosendiv).children('.userholder')[0];
                let hiddeninputs = $(addselect).siblings('.userpickerhiddeninputs')[0];
                let fieldname = $(hiddeninputs).attr('fieldname');

                let addedoptions = $(addselect).children('option');
                let addedoptionvalues = new Array();
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

            let parents = $(el).parents()[0];
            let chosendiv = $(parents).siblings('.elbp_user_picker_chosen_div')[0];
            let addselect = $(chosendiv).children('.userholder')[0];
            let hiddeninputs = $(addselect).siblings('.userpickerhiddeninputs')[0];

            let users = $(addselect).val();

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

        let errs = 0;
        let formID = $(form).attr('id');

        $( '#' + formID + ' input, #'+formID+' select, #'+formID+' textarea').removeClass('elbp_red');
        $('span.elbp_error').remove();

        let firstEl = '';

        // Loop through the data elements provided and check if they have a "validation" attribute
        $( '#' + formID + ' input, #'+formID+' select, #'+formID+' textarea').each( function(){

            let input = $(this);
            let value = $(this).val();
            value = $.trim(value);

            // If it's a Moodle Text Editor, the value is in the iframe, so we need to get that instead
            if ( $(this).hasClass('elbp_texteditor') ){

                // Moodle 2.7 and above
                let ifr27 = $('#' + $(this).attr('id') + 'editable');
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
                let validation = $(this).attr('validation').split(',');
                let type = $(this).attr('type');
                let name = $(this).attr('name');

                $.each(validation, function(i, v){

                    switch(v)
                    {

                        case 'NOT_EMPTY':
                        case 'REQUIRED':

                            if (type == 'radio' || type == 'checkbox')
                            {

                                let others = $('#'+formID+' input[name="'+name+'"]');
                                let ticked = 0;
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

        let params = {action: action};

        $('#cmd_input input').val('');

        ELBP.ajax(0, "execute", params, function(d){
            $('#cmd_output').append(d + '<br>');
            $(function() {
                let height = $('#cmd_output')[0].scrollHeight;
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
            let params = { type: type, studentID: ELBP.studentID, courseID: ELBP.courseID, loadedFrom: loadedFrom, putInto: putInto }
            ELBP.ajax("Targets", "load_display_type", params, function(d){
                $('#elbp_targets_content').html(d);
                ELBP.set_view_link(el);
                if (callBack != undefined){
                    callBack();
                }
            }, function(d){
                $('#elbp_targets_content').html('<img src='+ELBP.www+'"/blocks/elbp/pix/loader.gif" alt="" />');
            });
        },

        load_targets: function(statusID, targetID){

            ELBP.Targets.loaded_type = true;

            let runSetFalse = function(){
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

            let data = form.serialiseObject();
            $.extend(data, {studentID: ELBP.studentID, courseID: ELBP.courseID, loadedFrom: loadedFrom, putInto: putInto});

            ELBP.ajax("Targets", "save_target", data, function(d){
                eval(d);
            }, function(d){
                $('#elbp_popup').scrollTop(0);
                $('#new_target_output').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        edit_target: function(id, loadedFrom, putInto){

            let params = { type: "edit", targetID: id, studentID: ELBP.studentID, courseID: ELBP.courseID, loadedFrom: loadedFrom, putInto: putInto }
            ELBP.ajax("Targets", "load_display_type", params, function(d){
                $('#elbp_targets_content').html(d);
            }, function(d){
                $('#elbp_targets_content').html('<img src="'+ELBP.www+'/blocks/elbp/pix/loader.gif" alt="" />');
            });

        },

        delete_target: function(id){

            let confirm = window.confirm(ELBP.strings['areyousuredelete']);
            if (confirm){
                let params = { type: "delete", targetID: id, studentID: ELBP.studentID, courseID: ELBP.courseID }
                ELBP.ajax("Targets", "delete_target", params, function(d){
                    eval(d);
                });
            }

        },

        add_comment: function(id, comment, parentID){

            let params = { targetID: id, comment: comment, parentID: parentID };
            ELBP.ajax("Targets", "add_comment", params, function(d){
                eval(d);
            });

        },

        delete_comment: function(targetID, commentID){

            let params = { targetID: targetID, commentID: commentID };
            ELBP.ajax("Targets", "delete_comment", params, function(d){
                eval(d);
            });

        },

        update_status: function(targetID, statusID){

            let params = { targetID: targetID, statusID: statusID };
            ELBP.ajax("Targets", "update_status", params, function(d){
                eval(d);
            });

        },

        forward_email: function(targetID, usernames){

            $('#email-success-'+targetID).hide();
            $('#email-error-'+targetID).hide();
            $('#email-to-addr-'+targetID).hide();
            $('#email-loading-img-'+targetID).show();

            let params = { targetID: targetID, usernames: usernames };

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
















    // Bind events
    ELBP.bind = function(){

        // Resize the popup
        ELBP.resize_popup();
        $(window).resize( function(){
            ELBP.resize_popup();
        } );


        // Destroy and reapply datepickers
        $('.elbp_datepicker').datepicker('destroy');
        $('.elbp_datepicker').removeClass('hasDatepicker');

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
        // [TODO]


        $.fn.serialiseObject = function(){

            let obj = {};
            let f = this;
            let arr = this.serializeArray();

            $.each(arr, function(){

                let name = this.name;

                // If it's an array with a key, we need to deal with that differently
                let match = name.match(/\[(\d+|\w+|)\]/);
                if (match != null){

                    let key = match[1];
                    let arrayName = name.split("[")[0];

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
            let els = $(this).find('.elbp_texteditor');
            $(els).each( function(){

                let id = $(this).attr('id');
                let name = $(this).attr('name');
                let content = '';

                // Moodle 2.6 and lower
                let ifr = $(f).find('#'+id+'_ifr');

                // Moodle 2.7 and above
                let ifr27 = $(f).find('#'+id+'editable');

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
            let src = $(this).attr('src');
            $(this).attr('src', src.replace('close_tiny.png', 'close_tiny_hover.png'));
        } );

        $('.dock_plugin_close img').mouseout( function(){
            let src = $(this).attr('src');
            $(this).attr('src', src.replace('close_tiny_hover.png', 'close_tiny.png'));
        } );

        // Traffic light click to view
        $('.elbp_progress_traffic_light').off('click');
        $('.elbp_progress_traffic_light').on('click', function(){

            let rankNum = $(this).attr('rankNum');

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

            let rank = $(this).attr('rankNum');
            let params = { studentID: ELBP.studentID, rank: rank };
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
                let height = $(this).height();
                let scrollHeight = $(this)[0].scrollHeight;
                if (scrollHeight > height){
                    $(this).next('.elbp_summary_box_footer').slideDown('slow');
                }
            }
        );







    };





    // Set ELBP into global space
    window.ELBP = ELBP;


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
    let keyMap = {16: false, 17: false, 220: false}; // Ctrl + Shift + |
    let keyMapAdvanced = { 16: false, 17: false, 192: false }; // Ctrl + Shift + @
    let keyMapClear = {16: false, 17: false, 13: false}; // Ctrl + Shift + Enter


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

            if ( (ELBP.commandPointer - 1) >= 0){
                ELBP.commandPointer--;
                let command = ELBP.savedCommands[ELBP.commandPointer];
                $('#cmd_input #input').val('');
                $('#cmd_input #input').val(command);
            }

        } else if (e.keyCode == 40){

            if ( (ELBP.commandPointer + 1) < ELBP.savedCommands.length){
                ELBP.commandPointer++;
                let command = ELBP.savedCommands[ELBP.commandPointer];
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