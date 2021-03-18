<?php
/**
 * Plugin Name: Social Plugin - Metadata
 * Description: Used to display Facebook related page meta information as widget or shortcode (E.g. Business hours, About Us, Last Post)
 * Version: 1.0.2
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Author:      ole1986
 * License: MIT
 * Text Domain: social-plugin-metadata
 * 
 * @author  Ole Köckemann <ole.koeckemann@gmail.com>
 * @license MIT
 */

defined('ABSPATH') or die('No script kiddies please!');

require_once 'gateway/interfaces/IFacebookGatewayHost.php';
require_once 'gateway/gateway.php';
require_once 'widget.php';

class Ole1986_FacebokPageInfo implements Ole1986_IFacebookGatewayHost
{
    /**
     * Cache expiration in seconds (5 minutes)
     */
    static $CACHE_EXPIRATION = 60 * 5;

    static $DEFAULT_APP_ID = "475478070525107";
    static $SP_GATEWAY_URL = "https://www.cloud86.de/wp-admin/admin-ajax.php";

    /**
     * The wordpress option where the facebook pages (long lived page token) are bing stored
     */
    static $WP_OPTION_PAGES = 'social_plugin_fb_pages';

    static $WP_OPTION_APPID = 'social_plugin_fb_app_id';
    static $WP_OPTION_APPSECRET = 'social_plugin_fb_app_secret';
    static $WP_OPTION_ISPUBLIC = 'social_plugin_api_public';

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
        $this->isTesting = $_SERVER['HTTP_HOST'] == 'test.cloud86.de';

        load_plugin_textdomain('social-plugin-metadata', false, dirname(plugin_basename(__FILE__)) . '/lang/');

        // load scripts and styles for frontend
        add_action('wp_enqueue_scripts', [$this, 'load_frontend_scripts']);
        // load scripts and styles for backend
        add_action('admin_enqueue_scripts', [$this, 'load_scripts']);

        add_action('widgets_init', function () {
            register_widget('Ole1986_FacebokPageInfoWidget');
        });

        add_action('admin_menu', [$this, 'settings_page']);

        // used to save the pages via ajaxed (only from admin area)
        add_action('wp_ajax_fb_save_pages', [$this, 'fb_save_pages']);
        add_action('wp_ajax_fb_get_page_options', [$this, 'fb_get_page_options']);
        add_action('wp_ajax_fb_save_appdata', [$this, 'fb_save_appdata']);

        // initialize the facebook for private use
        if (!empty($this->getAppSecret())) {
            new Ole1986_FacebookGateway($this, $this->isPublic());
        }

        $this->registerShortcodes();
    }

    public function load_frontend_scripts()
    {
        wp_enqueue_style('social_plugin_style', plugins_url('styles/style.css', __FILE__));
    }

    public function load_scripts($hook)
    {
        if (strpos($hook, 'social-plugin-metadata-plugin') !== false) {
            wp_enqueue_script('social_plugin', plugins_url('scripts/init.js', __FILE__));
            wp_localize_script('social_plugin', 'social_plugin', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'gatewayurl' => empty($this->getAppSecret()) ? self::$SP_GATEWAY_URL : admin_url('admin-ajax.php'),
                "use_gateway" => $this->useGateway(),
                'app_id' => $this->getAppID()
            ]);    
        } else if (strpos($hook, 'widgets.php') !== false) {
            wp_enqueue_script('social_plugin', plugins_url('scripts/widget.js', __FILE__));
        }
    }

    public function getAppID()
    {
        return get_option(self::$WP_OPTION_APPID, self::$DEFAULT_APP_ID);
    }

    public function getAppSecret()
    {
        return get_option(self::$WP_OPTION_APPSECRET, '');
    }

    public function useGateway()
    {
        return empty($this->getAppSecret) ? 1 : 0;
    }

    public function isPublic()
    {
        return get_option(self::$WP_OPTION_ISPUBLIC, 0);
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

        unset($atts['page_id']);

        $filteredPages = array_filter(
            $pages,
            function ($v) use ($page_id) {
                return $v['id'] == $page_id;
            }
        );

        $currentPage = array_pop($filteredPages);

        $result = $this->processContentFromOption($currentPage, $option, $atts);

        ob_start();
        
        $this->{'show' . $option}($result, $atts);
        $output_string = ob_get_contents();

        ob_end_clean();

        return $output_string;
    }

    public function processContentFromOption($currentPage, $option, $options = [])
    {
        if (empty($currentPage)) {
            return;
        }

        $result = false;

        // cache check
        if (!$this->isTesting) {
            $result = get_transient('fp-get-pageinfo-' . $option);
        }

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
            $result = $this->fbGraphRequest($currentPage['id'] . '/published_posts?fields=message,permalink_url,created_time&limit='. ($options['limit'] ?? '') .'&access_token=' . $currentPage['access_token']);
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
     * @param array $page    the page properties received from facebook api
     * @param array $options optional message to use when result is empty
     */
    public function showBusinessHours($page, $options = [])
    {
        if (empty($page['hours'])) {
            ?>
            <div class="social-plugin-metadata-empty" style="text-align: center"><?php echo (empty($options['empty_message']) ? __('Currently there are no entries available on Facebook', 'social-plugin-metadata') : $options['empty_message']); ?></div>
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

        echo '<div class="social-plugin-metadata-hours">';
        foreach ($result as $k => $v) {
            echo '<div class="social-plugin-metadata-days">';
            echo "<div>" . $mapDayNames[$k] . "</div>";
            echo '<div class="social-plugin-metadata-hours-times">';
            foreach ($v as $value) {
                echo "<div>".$value['open']." - ".$value['close']."</div>";
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    public function showAbout($page, $options = [])
    {
        if (empty($page['about'])) {
            ?>
            <div class="social-plugin-metadata-empty" style="text-align: center"><?php echo (empty($options['empty_message']) ? __('Currently there are no entries available on Facebook', 'social-plugin-metadata') : $options['empty_message']); ?></div>
            <?php
            return;
        }
        echo '<div class="social-plugin-metadata-about">'.$page['about'].'</div>';
    }

    public function showLastPost($page, $options = [])
    {
        if (empty($page['data'])) {
            ?>
            <div class="social-plugin-metadata-empty" style="text-align: center"><?php echo (empty($options['empty_message']) ? __('Currently there are no entries available on Facebook', 'social-plugin-metadata') : esc_attr($options['empty_message'])); ?></div>
            <?php
            return;
        }

        foreach ($page['data'] as $lastPost) {
            $created = new DateTime($lastPost['created_time']);
            $now = new DateTime();

            $diffSeconds = $now->getTimestamp() - $created->getTimestamp();

            $diff = $now->diff($created);
            
            $friendlyDiff = $diff->format(__('%i minutes ago', 'social-plugin-metadata'));

            if ($diffSeconds > (60 * 60)) {
                $friendlyDiff = $diff->format(__('%h hours ago', 'social-plugin-metadata'));
            }
            if ($diffSeconds > (60 * 60 * 24)) {
                $friendlyDiff = $diff->format(__('%d days ago', 'social-plugin-metadata'));
            }
            if ($diffSeconds > (60 * 60 * 24 * 3)) {
                $friendlyDiff = gmstrftime('%x', $created->getTimestamp());
            }
            ?>
            <div class="social-plugin-metadata-lastpost">
                <div><?php echo $lastPost['message'] ?></div>
                <div class="social-plugin-metadata-lastpost-footer">
                    <div class="social-plugin-metadata-lastpost-link">
                        <small>
                        <a href="<?php echo $lastPost['permalink_url']; ?>" target="_blank">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/c/c2/F_icon.svg" style="width: 18px; vertical-align: middle;" />
                            <?php _e('Open on Facebook', 'social-plugin-metadata') ?>
                        </a>
                        </small>
                    </div>
                    <div class="social-plugin-metadata-lastpost-created"><small><?php echo $friendlyDiff; ?></small></div>
                </div>
            </div>
            <?php
        }
    }

    public function fbGraphRequest($url, $doPost = false)
    {
        $path = 'https://graph.facebook.com/';

        if ($doPost) {
            $resp = wp_remote_post($path . $url);
        } else {
            $resp = wp_remote_get($path . $url);
        }
        

        return json_decode($resp['body'], true);
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
        // changed as requested by Wordpress Review Teams
        $pages = array_map(function ($page) {
            return  [
                'access_token' => sanitize_text_field($page['access_token']),
                'category' => sanitize_text_field($page['category']),
                'name' => sanitize_text_field($page['name']),
                'id' => sanitize_key($page['id']),
            ];
        }, $_POST['data']);

        $ok = $this->savePages($pages);

        header('Content-Type: application/json');
        echo json_encode($ok);
        wp_die();
    }

    public function fb_save_appdata()
    {
        $appId = sanitize_key($_POST['appId']);
        $appSecret = sanitize_key($_POST['appSecret']);
        $isPublic = sanitize_key($_POST['isPublic']);


        if (empty($appId)) {
            delete_option(self::$WP_OPTION_APPID);
        } else {
            update_option(self::$WP_OPTION_APPID, $appId);
        }
        
        if (empty($appSecret)) {
            delete_option(self::$WP_OPTION_APPSECRET);    
        } else {
            update_option(self::$WP_OPTION_APPSECRET, $appSecret);
        }

        if (empty($isPublic)) {
            delete_option(self::$WP_OPTION_ISPUBLIC);
        } else {
            update_option(self::$WP_OPTION_ISPUBLIC, 1);
        }

        header('Content-Type: application/json');
        echo json_encode(true);

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
        add_management_page(__('Social Plugin - Metadata', 'social-plugin-metadata'), __('Social Plugin - Metadata', 'social-plugin-metadata'), 'edit_posts', 'social-plugin-metadata-plugin', [$this, 'settings_page_content'], 4);
    }
    
    /**
     * Populate the settings content used to gather the facebook pages from fb-gateway
     */
    public function settings_page_content()
    {
        $pages = get_option(self::$WP_OPTION_PAGES, []);
        ?>
        <h2><?php _e('Social Plugin - Metadata', 'social-plugin-metadata') ?></h2>
        <div id="fb-pageinfo-alert" class="notice">
            <p><?php _e('Please follow the instruction below to syncronize your facebook pages') ?></p>
        </div>
        <div style="display: flex;  flex-wrap: wrap">
            <div id="fb-gateway-frame" style="margin: 1em; flex-basis: 375px;">
                <h3><?php _e('Connect with Facebook', 'social-plugin-metadata') ?></h3>
                <div hidden id="fb-gateway-register-container">
                    <p>
                        <?php _e('Please register your domain first before using the Facebook App', 'social-plugin-metadata') ?>
                    </p>
                    <button id="fb-gateway-register" class="button button-primary hide-if-no-js">Register domain</button>
                </div>
                <div id="fb-gateway-container">
                    <p>
                        <?php _e('Please use the below Login & Sync button to synchronize the facebook pages', 'social-plugin-metadata') ?>
                    </p>
                    <button id="fb-gateway-login" class="button hide-if-no-js">Login and Sync</button>
                </div>
                <div style="margin-top: 1em">  
                    <h3><?php _e('Setup your Facebook App (optional)', 'social-plugin-metadata') ?></h3>
                    <div id="fb-gateway-custom" hidden>
                        <p><?php printf(__('Build your own %s or use our service gateway', 'social-plugin-metadata'), '<a href="https://developers.facebook.com/apps/" target="_blank">Facebook App</a>') ?></p>
                        <div>
                            <label>Facebook App ID (standalone / optional)</label><br />
                            <input class="widefat" type="text"  autocomplete="off" id="fbAppId" value="<?php echo get_option(self::$WP_OPTION_APPID, '') ?>" />
                        </div>
                        <div style="margin-top: 0.5em">
                            <label>Facebook App Secret (standalone / optional)</label><br />
                            <input class="widefat" type="password" autocomplete="new-password" id="fbAppSecret" />
                        </div>
                        <div style="margin-top: 0.5em">
                            <label><input type="checkbox" id="fbIsPublic"  <?php echo $this->isPublic() ? 'checked' : '' ?> /> Make API publicly available</label>
                        </div>
                        <div style="margin-top: 1em">
                            <button id="fb-appdata-save" class="button hide-if-no-js">Save</button>
                        </div>
                    </div>
                    <div id="fb-gateway-our">
                        <div><?php _e('Currently you are using our Facebook App to gather necessary page information', 'social-plugin-metadata') ?></div>
                        <div style="margin-top: 1em">
                            <button id="fb-gateway-change" class="button hide-if-no-js"><?php _e('Use my Facebook App', 'social-plugin-metadata') ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <div style="margin: 1em; flex-basis: 375px; flex-grow: 1">
                <h3><?php _e('Quick Guide', 'social-plugin-metadata') ?></h3>
                <p><?php _e('To sychronize and outpout meta information (E.g. Business hours, About Us, last posts) from facebook pages', 'social-plugin-metadata') ?>.</p>
                <div style="font-family: monospace">
                    <ol>
                        <li>
                            <?php _e('Use the button Login and Sync (left side) to connect your facebook account with the Facebook App', 'social-plugin-metadata') ?><br />
                            <strong><?php printf(__("Dependent on details given in the '%s' you get asked to connect your Facebook Account with the following app", 'social-plugin-metadata'), __('Setup your Facebook App (optional)', 'social-plugin-metadata')) ?></strong>
                            <ul style="list-style: inside">
                                <li>our Facebook App "Cloud 86 / Link Page"</li>
                                <li>your own Facebook app</li>
                            </ul>
                        </li>
                        <li><?php _e('Once successfully logged into your facebook account, choose the pages you wish to output metadata for', 'social-plugin-metadata') ?></li>
                        <li><?php _e('Is your account properly connected and the syncronization completed, you can switch to the Appearance -> Widget page', 'social-plugin-metadata') ?></li>
                        <li><?php printf(__('To display the content on your front page, move the widget %s into a desired widget area', 'social-plugin-metadata'), __('Social plugin - Metadata Widget', 'social-plugin-metadata')) ?></li>
                        <li><?php _e('Finally save the widget settings and check the output on the front page', 'social-plugin-metadata') ?></li>
                    </ol>
                    <h4>Shortcodes</h4>
                    <div>
                        <?php printf(__('If you prefer to use %s, the below options are available', 'social-plugin-metadata'), '<a href="https://wordpress.com/de/support/wordpress-editor/bloecke/shortcode-block/" target="_blank">Shortcodes</a>') ?>
                        <ul>
                            <li>[fb-pageinfo-businesshours page_id="..." empty_message=""]</li>
                            <li>[fb-pageinfo-about page_id="..." empty_message=""]</li>
                            <li>[fb-pageinfo-lastpost page_id="..." empty_message=""]</li>
                        </ul>
                    </div>
                </div>
                <h2>Rechtliche Hinweise</h2>
                <p>
                    <strong>Cloud 86 selbst speichert keine Facebook Daten. <br />Es werden ausschließlich technisch erforderliche Informationen zur Darstellung der Metadaten AUF DIESEM SERVER (<?php echo $_SERVER['HTTP_HOST'] ?>) abgelegt</strong>
                </p>
                <div id="rawdata" style="font-family: monospace; white-space: pre; background-color: white; padding: 1em;">
                    <a href="javascript:void(0)" onclick="SocialPlugin.fbRawPages()">DATEN ANZEIGEN</a>
                </div>
                <p>WEITER INFORMATIONEN ZUM DATENSCHUTZ FINDEN SIE <a href="https://www.cloud86.de/datenschutzerklaerung" target="_blank">HIER</a></p>
            </div>
        </div>
        <?php
    }
}

Ole1986_FacebokPageInfo::get_instance();

?>
