/** min **/
define(['jquery', 'jqueryui', 'block_elbp/minicolors'], function($, ui, miniColors) {
       
    return {
        init: function(){
            console.log('start');
            $('input').each( function() {
                    $(this).minicolors();
                });
        }
    }
    
    
});