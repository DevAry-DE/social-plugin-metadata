function SocialPluginWidget(container) {
    var self = this;

    (function () {
        jQuery(container).attr('data-loaded', true);
       var limitContainer =  jQuery(container).find('.social-plugin-metadata-limit-comtainer');
       var contentSelector = jQuery(container).find('.social-plugin-metadata-widget-contenttype');

       contentSelector.click(function() {
           if (jQuery(this).val() == 'LastPost') {
               limitContainer.show();
           } else {
               limitContainer.hide();
           }
       })

       contentSelector.trigger('click');
    })();
}

jQuery(function() {
    jQuery('.widget-liquid-right .social-widget-metadata-widget:not([data-loaded])').each(function() {
        new SocialPluginWidget(this);
    });
});

jQuery(document).ajaxComplete(function() {
    jQuery('.widget-liquid-right .social-widget-metadata-widget:not([data-loaded])').each(function() {
        new SocialPluginWidget(this);
    });
});