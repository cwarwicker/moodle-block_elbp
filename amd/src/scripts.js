/** min **/
define(['jquery', 'jqueryui', 'block_elbp/minicolors', 'block_elbp/raty', 'block_elbp/tinytbl'], function($, ui, miniColors, raty, tinytbl) {
       
    // Raty image path
    $.fn.raty.defaults.path = M.cfg.wwwroot + '/blocks/elbp/js/jquery/plugins/raty/images';

       
    var ELBP = {};
    var client = {};
    
    ELBP.load = function(group){alert('loading: ' + group)};
    
    window.ELBP = ELBP;

    client.init = function(){
        console.log('Starting ELBP');
    };
    
    client.loadGroup = function(group){
        ELBP.load(group);
    };


    
    return client;
    
    
});