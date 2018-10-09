define([], function () {
    window.requirejs.config({
        paths: {
            "minicolors": M.cfg.wwwroot + '/blocks/elbp/js/jquery/plugins/minicolors/jquery.minicolors'
        },
        shim: {
            'moment': {exports: 'minicolors'}
        }
    });
});