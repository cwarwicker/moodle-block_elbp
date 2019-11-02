define([], function () {

    window.requirejs.config({
        paths: {
            "minicolors":   M.cfg.wwwroot + '/blocks/elbp/js/jquery/plugins/minicolors/jquery.minicolors',
            "raty":         M.cfg.wwwroot + '/blocks/elbp/js/jquery/plugins/raty/jquery.raty',
            "fileupload":    M.cfg.wwwroot + '/blocks/elbp/js/jquery/plugins/fileupload/jquery.fileupload'
        },
        shim: {
            'minicolors': {exports: 'minicolors'},
            'raty': {exports: 'raty'},
            'fileupload': {exports: 'fileupload'}
        }
    });
});