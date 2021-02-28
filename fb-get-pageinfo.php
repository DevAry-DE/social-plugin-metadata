<?php
/**
 * Plugin Name: Facebook page info
 * Description: Used to display facebook related page information as widget or shortcode (Business hours, About Us, Last Post)
 * Version:     1.0.0
 * Author:      ole1986
 * License: MIT
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Text Domain: fb-get-pageinfo
 * 
 * @author  Ole Köckemann <ole.koeckemann@gmail.com>
 * @license MIT
 */

defined('ABSPATH') or die('No script kiddies please!');

require_once 'widget.php';

class Ole1986_FacebokPageInfo
{
    /**
     * Cache expiration in seconds (3 minutes)
     */
    static $CACHE_EXPIRATION = 60 * 3;
    /**
     * The frontend page where the [fb-gateway] shortcode is located (provided by the fb-gateway plugin)
     */
    static $FB_GATEWAY_URL = "https://www.cloud86.de/facebook-gateway/";

    /**
     * The wordpress option where the facebook pages (long lived page token) are bing stored
     */
    static $WP_OPTION_PAGES = 'fb_get_page_info';

     /**
      * The unique instance of the plugin.
      *
      * @var Ole1986_FacebokPageInfo
      */
    private static $instance;

    /**
     * Gets an instance of our plugin.
     *
     * @return Ole1986_FacebokPageInfo
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private $isTesting = false;

    /**
     * constructor overload of the WP_Widget class to initialize the media widget
     */
    public function __construct()
    {
        load_plugin_textdomain('fb-get-pageinfo', false, dirname(plugin_basename(__FILE__)) . '/lang/');

        // check if currently is setup for testing
        $this->checkTesting();

        add_action('widgets_init', function () {
            register_widget('Ole1986_FacebokPageInfoWidget');
        });

        add_action('admin_menu', [$this, 'settings_page']);

        // used to save the pages via ajaxed (only from admin area)
        add_action('wp_ajax_fb_save_pages', [$this, 'fb_save_pages']);
        add_action('wp_ajax_fb_get_page_options', [$this, 'fb_get_page_options']);

        $this->registerShortcodes();
    }

    private function checkTesting()
    {
        $this->isTesting = $_SERVER['HTTP_HOST'] == 'test.cloud86.de';

        if ($this->isTesting) {
            self::$FB_GATEWAY_URL = "https://test.cloud86.de/facebook-gateway";
        }
    }

    private function registerShortcodes()
    {
        // [fb-pageinfo-businesshours page_id="<page>"]
        add_shortcode('fb-pageinfo-businesshours', function ($atts, $content, $tag) {
            return $this->shortcodeCallback('BusinessHours', $atts, $content, $tag);
        });

        // [fb-pageinfo-about page_id="<page>"]
        add_shortcode('fb-pageinfo-about', function ($atts, $content, $tag) {
            return $this->shortcodeCallback('About', $atts, $content, $tag);
        });

        // [fb-pageinfo-lastpost page_id="<page>"]
        add_shortcode('fb-pageinfo-lastpost', function ($atts, $content, $tag) {
            return $this->shortcodeCallback('LastPost', $atts, $content, $tag);
        });
    }

    private function shortcodeCallback($option, $atts, $content, $tag)
    {
        $pages = get_option(self::$WP_OPTION_PAGES, []);

        $page_id = $atts['page_id'];

        $currentPage = array_pop(
            array_filter(
                $pages,
                function ($v) use ($page_id) {
                    return $v['id'] == $page_id;
                }
            )
        );

        $result = $this->processContentFromOption($currentPage, $option);

        ob_start();
        
        $this->{'show' . $option}($result, $atts['empty_message']);
        $output_string = ob_get_contents();

        ob_end_clean();

        return $output_string;
    }

    public function processContentFromOption($currentPage, $option)
    {
        if (empty($currentPage)) {
            return;
        }

        // cache check
        $result = get_transient('fp-get-pageinfo-' . $option);

        if ($result !== false) {
            return $result;
        }

        switch($option) {
        case 'BusinessHours':
            $result = $this->fbGraphRequest($currentPage['id'] . '/?fields=hours&access_token=' . $currentPage['access_token']);
            break;
        case 'About':
            $result = $this->fbGraphRequest($currentPage['id'] . '/?fields=about&access_token=' . $currentPage['access_token']);
            break;
        case 'LastPost':
            $result = $this->fbGraphRequest($currentPage['id'] . '/posts?fields=message,permalink_url,created_time&limit=1&access_token=' . $currentPage['access_token']);
            break;
        }

        // only cache when outside test environment
        if (!$this->isTesting) {
            // expire in 1 minute
            set_transient('fp-get-pageinfo-' . $option, $result, self::$CACHE_EXPIRATION);
        }

        return $result;
    }

    /**
     * Parse the hours taken from facebook graph api and output in proper HTML format
     * 
     * @param array  $page          the page properties received from facebook api
     * @param string $empty_message optional message to use when result is empty
     */
    public function showBusinessHours($page, $empty_message)
    {
        if (empty($page['hours'])) {
            ?>
            <div class="fb-pageinfo-empty" style="text-align: center"><?php echo (empty($empty_message) ? __('Currently there are no entries given in Facebook') : $empty_message); ?></div>
            <?php
            return;
        }
        
        $result = [];

        array_walk(
            $page['hours'],
            function ($item, $k) use (&$result) {
                if (preg_match('/(\w{3})_(\d+)_(open|close)/', $k, $m)) {
                    if (empty($result[$m[1]])) {
                        $result[$m[1]] = [];
                    }

                    if (empty($result[$m[1]][$m[2]])) {
                        $result[$m[1]][$m[2]] = [
                        'open' => '',
                        'close' => ''
                        ];
                    }
                    $result[$m[1]][$m[2]][$m[3]] = $item;
                }
            }
        );

        $mapDayNames = [
            'mon' => __('Monday'),
            'tue' => __('Tuesday'),
            'wed' => __('Wednesday'),
            'thu' => __('Thursday'),
            'fri' => __('Friday'),
            'sat' => __('Saturday'),
            'sun' => __('Sunday'),
        ];

        echo '<div class="fb-pageinfo-hours">';
        foreach ($result as $k => $v) {
            echo '<div class="fb-pageinfo-days">';
            echo "<div>" . $mapDayNames[$k] . "</div>";
            echo "<div class='fb-pageinfo-hours-times'>";
            foreach ($v as $value) {
                echo "<div>".$value['open']." - ".$value['close']."</div>";
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    public function showAbout($page, $empty_message)
    {
        if (empty($page['about'])) {
            ?>
            <div class="fb-pageinfo-empty" style="text-align: center"><?php echo (empty($empty_message) ? __('Currently there are no entries given in Facebook') : $empty_message); ?></div>
            <?php
            return;
        }
        echo '<div class="fb-pageinfo-about">'.$page['about'].'</div>';
    }

    public function showLastPost($page, $empty_message)
    {
        if (empty($page['data'])) {
            ?>
            <div class="fb-pageinfo-empty" style="text-align: center"><?php echo (empty($empty_message) ? __('Currently there are no entries given in Facebook') : $empty_message); ?></div>
            <?php
            return;
        }

        $lastPost = array_pop($page['data']);

        $created = new DateTime($lastPost['created_time']);
        $now = new DateTime();

        $diffSeconds = $now->getTimestamp() - $created->getTimestamp();

        $diff = $now->diff($created);
        
        $friendlyDiff = $diff->format(__('%i minutes ago'));

        if ($diffSeconds > (60 * 60)) {
            $friendlyDiff = $diff->format(__('%h hours ago'));
        }
        if ($diffSeconds > (60 * 60 * 24)) {
            $friendlyDiff = $diff->format(__('%d days ago'));
        }
        if ($diffSeconds > (60 * 60 * 24 * 3)) {
            $friendlyDiff = gmstrftime('%x', $created->getTimestamp());
        }

        ?>
        <div class="fb-pageinfo-lastpost">
            <?php echo $lastPost['message'] ?>
            <div class="fb-pageinfo-lastpost-footer">
                <div class="fb-pageinfo-lastpost-link">
                    <small><a href="<?php echo $lastPost['permalink_url']; ?>" target="_blank"><?php _e('Open on Facebook') ?></a></small>
                </div>
                <div class="fb-pageinfo-lastpost-created"><small><?php echo $friendlyDiff; ?></small></div>
            </div>
        </div>
        <?php
    }

    private function fbGraphRequest($url)
    {
        $path = 'https://graph.facebook.com/';

        $curl_facebook1 = curl_init(); // start curl
        curl_setopt($curl_facebook1, CURLOPT_URL, $path . $url); // set the url variable to curl
        curl_setopt($curl_facebook1, CURLOPT_RETURNTRANSFER, true); // return output as string

        $output = curl_exec($curl_facebook1); // execute curl call
        curl_close($curl_facebook1); // close curl
        $decode_output = json_decode($output, true); // decode the response (without true this will crash)

        return $decode_output;
    }

    public function fb_get_page_options()
    {
        $result = get_option(self::$WP_OPTION_PAGES, []);

        if (empty($_POST['pretty'])) {
            header('Content-Type: application/json');   
        }

        array_walk($result, function (&$v) {
            $v['access_token'] = '(hidden)';
        });

        echo json_encode($result, JSON_PRETTY_PRINT);
        
        wp_die();
    }
    /**
     * The ajax call being used to save the pages received by the fb-gateway
     */
    public function fb_save_pages()
    {
        $ok = $this->savePages($_POST['data']);

        header('Content-Type: application/json');
        echo json_encode($ok);
        wp_die();
    }

    /**
     * Save the pages as wordpress option
     * 
     * @param array $new_value all known pages selected by the client
     */
    private function savePages($new_value)
    {
        if (empty($new_value)) {
            delete_option(self::$WP_OPTION_PAGES);
            return false;
        }

        if (get_option(self::$WP_OPTION_PAGES) !== false) {
            // The option already exists, so update it.
            update_option(self::$WP_OPTION_PAGES, $new_value);
        } else {
            add_option(self::$WP_OPTION_PAGES, $new_value, null, 'no');
        }

        return true;
    }

    /**
     * Populate the Settings menu entry
     */
    public function settings_page()
    {
        add_menu_page(__('Facebook page info', 'fb-get-pageinfo'), __('Facebook page info', 'fb-get-pageinfo'), 'edit_posts', 'fb-get-pageinfo-plugin', [$this, 'settings_page_content'], '', 4);
    }
    
    /**
     * Populate the settings content used to gather the facebook pages from fb-gateway
     */
    public function settings_page_content()
    {
        $pages = get_option(self::$WP_OPTION_PAGES, []);
        ?>
        <script>
            var fbRawPages = function() {
                jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', { action: 'fb_get_page_options', pretty: '1' })
                    .done(function(response) {
                        jQuery('#rawdata').html(response);
                    });
            }

            var fbSavePages = function(data) {
                var alert = jQuery('#fb-pageinfo-sync');
                var frame = jQuery('#fb-gateway-frame');
                frame.hide();

                alert.removeClass('error').removeClass('updated');
                alert.find('p').text('Syncing...');

                jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', { action: 'fb_save_pages', data })
                    .done(function(response){
                        if (!response) {
                            alert.addClass('error');
                            alert.find('p').html('<?php _e('Something went wrong. Please choose at least one facebook page after login') ?>');    
                            frame.show();
                            return;
                        }
                        alert.addClass('updated');
                        alert.find('p').html('Successfully synchronized ' + data.length +' pages. You can now configure <a href="widgets.php">the widget</a>');
                    }).catch(function(e) {
                        alert.addClass('error');
                        alert.find('p').text('We encountered an error. Please try again later...');
                        frame.show();
                    });
            }

            jQuery(function() {
                window.addEventListener("message", (event) => {
                console.log(event);
                // received postMessage from iframe
                if (event.origin.match(/cloud86\.de/)) {
                    fbSavePages(event.data);
                }
                
            }, false);
            });
            
        </script>
        <h2><?php _e('Facebook page info', 'fb-get-pageinfo') ?></h2>
        <div id="fb-pageinfo-sync" class="notice">
            <p><?php _e('Please follow the instruction below to syncronize your facebook pages') ?></p>
        </div>
        <div style="display: flex;">
            <div id="fb-gateway-frame" style="margin: 1em">
                <iframe src="<?php echo self::$FB_GATEWAY_URL ?>" width="400px" height="250px"></iframe>
                <?php if ($this->isTesting) : ?>
                <div>
                    <small>Debugging is enabled connecting to <?php echo self::$FB_GATEWAY_URL ?></small>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <h2><?php _e('Quick Guide', 'fb-get-pageinfo') ?></h2>
                <p><?php _e('To sychronize and outpout meta information (E.g. Business hours, About Us, last posts) from facebook pages', 'fb-get-pageinfo') ?>.</p>
                <div style="font-family: monospace">
                    <ol>
                        <li><?php _e('Use the button Login and Sync (left side) to connect your facebook account with the Cloud 86 / Link Page application', 'fb-get-pageinfo') ?></li>
                        <li><?php _e('Once successfully logged into your facebook account, choose the pages you wish to output metadata for', 'fb-get-pageinfo') ?></li>
                        <li><?php _e('Is your account properly connected and the syncronization completed, you can switch to the Appearance -> Widget page', 'fb-get-pageinfo') ?></li>
                        <li><?php printf(__('To display the content on your front page, move the widget %s into a desired widget area', 'fb-get-pageinfo'), __('Facebook page info Widget', 'fb-get-pageinfo')) ?></li>
                        <li><?php _e('Finally save the widget settings and check the output on the front page', 'fb-get-pageinfo') ?></li>
                    </ol>
                    <h4>Shortcodes</h4>
                    <div>
                        <?php printf(__('If you prefer to use %s, the below options are available', 'fb-get-pageinfo'), '<a href="https://wordpress.com/de/support/wordpress-editor/bloecke/shortcode-block/" target="_blank">Shortcodes</a>') ?>
                        <ul>
                            <li>[fb-pageinfo-businesshours page_id="..." empty_message=""]</li>
                            <li>[fb-pageinfo-about page_id="..." empty_message=""]</li>
                            <li>[fb-pageinfo-lastpost page_id="..." empty_message=""]</li>
                        </ul>
                    </div>
                </div>
                <h2>Rechtliche Hinweise</h2>
                <p>
                    <strong>Cloud 86 selbst speichert keine Facebook Daten. <br />Es werden ausschließlich technisch erforderliche Informationen AUF DIESEM SERVER (<?php echo $_SERVER['HTTP_HOST'] ?>) als Wordpress Option unter "<?php echo self::$WP_OPTION_PAGES; ?>" abgelegt</strong>
                </p>
                <div id="rawdata" style="font-family: monospace; white-space: pre; background-color: white; padding: 1em;">
                    <a href="#" onclick="fbRawPages()">DATEN ANZEIGEN</a>
                </div>
                <p>WEITER INFORMATIONEN ZUM DATENSCHUTZ FINDEN SIE <a href="https://www.cloud86.de/datenschutzerklaerung" target="_blank">HIER</a></p>
            </div>
        </div>
        <?php
    }
}

Ole1986_FacebokPageInfo::get_instance();

?>
