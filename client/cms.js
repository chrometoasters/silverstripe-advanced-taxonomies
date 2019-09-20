/**
 * TODO: to be deleted once the issue is solved in core. the issue is described in github issue tracker
 * https://github.com/silverstripe/silverstripe-admin/issues/911
 */
;(function() {
    'use strict';
    jQuery(document).ready(function () {
        var hash = window.location.hash;
        if(hash != '' && jQuery('#Form_ItemEditForm').length) {
            jQuery('#Form_ItemEditForm').find('ul.ui-tabs-nav > li.ui-state-default').each(function(index){
                if (jQuery(this).attr('aria-controls') !== '') {
                    if (('#' + jQuery(this).attr('aria-controls')) == hash) {
                        jQuery(this).find('a').trigger('click');
                        return;
                    }
                }

            });
        }
    });
}( jQuery ));
