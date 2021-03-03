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

    this.fbSaveAppdata = function() {
        AlertMessage('', 'Updating info...');

        var appId = jQuery('#fbAppId').val();
        var appSecret = jQuery('#fbAppSecret').val();
        var appGateway = jQuery('#fbAppGateway').val();

        jQuery.post(social_plugin.ajaxurl, { action: 'fb_save_appdata', appId, appSecret, appGateway})
        .done(function(response) {
            document.location.reload();
        }).catch(function(e) {
            AlertMessage('error', 'We encountered an error. Please try again later...');
        });
    }

    this.fbSavePages = function(data) {
        AlertMessage('', 'Syncing...');
    
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
                    AlertMessage('error', 'Something went wrong contacting ' + url.hostname + '. Please try again later...');
                });
            }
            console.log(response);
        },  {scope: 'public_profile,pages_show_list'});
    };

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
        jQuery('#fb-appdata-save').click(self.fbSaveAppdata);

        if (!social_plugin.app_id) {
            AlertMessage('error', 'Please setup the Facebook App ID first');
        }

        if (!social_plugin.gatewayurl) {
            AlertMessage('error', 'Please consider to configure either a gateway url (remote) or facebook secret (standalone)');
        }
    })();
}

jQuery(function() {
    window.SocialPlugin = new SocialPlugin();
});
