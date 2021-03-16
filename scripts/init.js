function SocialPlugin() {
    var self = this;
    
    var AlertMessage = function(type, message) {
        var alert = jQuery('#fb-pageinfo-alert');
        alert.removeClass('error').removeClass('updated');

        alert.addClass(type);
        alert.find('p').html(message);
    }

    this.fbRawPages = function() {
        jQuery.post(social_plugin.ajaxurl, { action: 'fb_get_page_options', pretty: '1' })
        .done(function(response) {
            jQuery('#rawdata').html(response);
        });
    };

    this.fbCheckAppDomain = function() {
        jQuery.post(social_plugin.gatewayurl, { action: 'fb_check_domain', domain: document.location.hostname})
            .done(function(response){
                if (response) {
                    jQuery('#fb-gateway-register-container').hide();
                } else {
                    jQuery('#fb-gateway-register-container').show();
                }
            }).catch(function(e) {
                AlertMessage('error', 'Unable to register the domain ' +  document.location.hostname);
            });
    }

    this.fbRegisterAppDomain = function() {
        jQuery.post(social_plugin.gatewayurl, { action: 'fb_register_domain', domain: document.location.hostname})
            .done(function(response){
                AlertMessage('updated', 'Domain ' + document.location.hostname + ' successfully registered');
                jQuery('#fb-gateway-register-container').hide();
            }).catch(function(e) {
                AlertMessage('error', 'Unable to register the domain ' +  document.location.hostname);
            });
    }

    this.fbSaveAppdata = function() {
        AlertMessage('', 'Updating info...');

        var appId = jQuery('#fbAppId').val();
        var appSecret = jQuery('#fbAppSecret').val();
        var isPublic = jQuery('#fbIsPublic').is(':checked') ? 1 : 0;

        jQuery.post(social_plugin.ajaxurl, { action: 'fb_save_appdata', appId, appSecret, isPublic})
        .done(function(response) {
            document.location.reload();
        }).catch(function(e) {
            AlertMessage('error', 'We encountered an error. Please try again later...');
        });
    }

    this.fbSavePages = function(data) {
        AlertMessage('', 'Saving data received from ' + (social_plugin.use_gateway ? 'remote' : 'local') + ' gateway ...');
    
        jQuery.post(social_plugin.ajaxurl, { action: 'fb_save_pages', data })
            .done(function(response){
                if (!response) {
                    AlertMessage('error', 'Something went wrong. Please choose at least one facebook page after login');
                    return;
                }

                AlertMessage('updated', 'Successfully synchronized ' + data.length +' pages. You can now configure <a href="widgets.php">the widget</a>');
            }).catch(function(e) {
                AlertMessage('error', 'We encountered an error. Please try again later...');
            });
    };

    this.fbLogin = function() {
        FB.login(function(response){
            if (response.status == 'connected') {
                response.authResponse.accessToken;
    
                jQuery.post(social_plugin.gatewayurl, { action: 'fb_get_pages', userID: response.authResponse.userID, token: response.authResponse.accessToken})
                .done(function(response){
                    self.fbSavePages(response.data);
                }).catch(function(e) {
                    var url = new URL(social_plugin.gatewayurl);
                    AlertMessage('error', 'Something went wrong contacting ' + url.hostname + ': ' + e.responseJSON.error.message);
                });
            }
        },  {scope: 'public_profile, pages_show_list, pages_read_engagement'});
    };

    this.showCustomAppOptions = function() {
        jQuery('#fb-gateway-custom').show();
        jQuery('#fb-gateway-our').hide();
    }

    var loadFB = function() {
        window.fbAsyncInit = function() {
            FB.init({
                appId            : social_plugin.app_id,
                autoLogAppEvents : true,
                xfbml            : true,
                version          : 'v9.0'
            });
        };
      
        // Load the SDK Asynchronously
        (function(d){
           var js, id = 'facebook-jssdk'; if (d.getElementById(id)) {return;}
           js = d.createElement('script'); js.id = id; js.async = true;
           js.src = "//connect.facebook.net/en_US/all.js";
           d.getElementsByTagName('head')[0].appendChild(js);
         }(document));   
    };

    (function () {
        loadFB();
        jQuery('#fb-gateway-login').click(self.fbLogin);
        jQuery('#fb-gateway-register').click(self.fbRegisterAppDomain);

        jQuery('#fb-appdata-save').click(self.fbSaveAppdata);

        jQuery('#fb-gateway-change').click(self.showCustomAppOptions);

        var fbAppId = jQuery('#fbAppId').val();

        if (!fbAppId) {
            jQuery('#fb-gateway-custom').hide();
        } else {
            jQuery('#fb-gateway-custom').show();
            jQuery('#fb-gateway-our').hide();
        }

        self.fbCheckAppDomain();
    })();
}

jQuery(function() {
    window.SocialPlugin = new SocialPlugin();
});
