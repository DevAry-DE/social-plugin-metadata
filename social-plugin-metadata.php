<?php
namespace Cloud86\WP\Social;

use Cloud86\WP\Social\Model\FacebookRestApi;

/**
 * Plugin Name: Social Plugin - Metadata
 * Description: Used to display Facebook related page meta information as widget or shortcode (E.g. Business hours, About Us, Last Post)
 * Version: 1.1.0
 * Author:      ole1986
 * License: MIT <https://raw.githubusercontent.com/Cloud-86/social-plugin-metadata/master/LICENSE>
 * Text Domain: social-plugin-metadata
 * 
 * @author  Ole Köckemann <ole.koeckemann@gmail.com>
 * @license MIT <https://raw.githubusercontent.com/Cloud-86/social-plugin-metadata/master/LICENSE>
 */

defined('ABSPATH') or die('No script kiddies please!');

require_once 'model/facebook-rest-api.php';
require_once 'widget.php';

class SocialPlugin extends FacebookRestApi
{
    /**
     * Cache expiration in seconds (5 minutes)
     */
    static $CACHE_EXPIRATION = 60 * 5;

    /**
     * The wordpress option where the facebook pages (long lived page token) are bing stored
     */
    static $WP_OPTION_PAGES = 'social_plugin_fb_pages';

    static $WP_OPTION_APPID = 'social_plugin_fb_app_id';
    static $WP_OPTION_APPSECRET = 'social_plugin_fb_app_secret';

    /**
     * The unique instance of the plugin.
     *
     * @var SocialPlugin
     */
    private static $instance;

    /**
     * Gets an instance of our plugin.
     *
     * @return SocialPlugin
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }
    /**
     * constructor overload of the WP_Widget class to initialize the media widget
     */
    public function __construct()
    {
        load_plugin_textdomain('social-plugin-metadata', false, dirname(plugin_basename(__FILE__)) . '/lang/');

        parent::__construct();

        // load scripts and styles for frontend
        add_action('wp_enqueue_scripts', [$this, 'load_frontend_scripts']);
        // load scripts and styles for backend
        add_action('admin_enqueue_scripts', [$this, 'load_scripts']);

        add_action('widgets_init', function () {
            register_widget('Cloud86\WP\Social\SocialPluginWidget');
        });

        add_action('admin_menu', [$this, 'settings_page']);

        // used to save the pages via ajaxed (only from admin area)
        add_action('wp_ajax_fb_save_pages', [$this, 'fb_save_pages']);
        add_action('wp_ajax_fb_get_page_options', [$this, 'fb_get_page_options']);
        add_action('wp_ajax_fb_save_appdata', [$this, 'fb_save_appdata']);

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
                'gatewayurl' => admin_url('admin-ajax.php'),
                'app_id' => $this->getAppID()
            ]);    
        } else if (strpos($hook, 'widgets.php') !== false) {
            wp_enqueue_script('social_plugin', plugins_url('scripts/widget.js', __FILE__));
        }
    }

    public function getAppID()
    {
        return get_option(self::$WP_OPTION_APPID);
    }

    public function getAppSecret()
    {
        return get_option(self::$WP_OPTION_APPSECRET, '');
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
        global $post;

        if (empty($currentPage)) {
            return;
        }

        $result = false;

        // apply cache when the current visitor has no edit_pages caps
        if ($post && !current_user_can('edit_page', $post->ID)) {
            $result = get_transient('social-plugin-cache-' . $currentPage['id'] . '-' . $option);
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
            $result = $this->fbGraphRequest($currentPage['id'] . '/published_posts?fields=message,permalink_url,created_time,status_type&limit='. ($options['limit'] ?? '') .'&access_token=' . $currentPage['access_token']);
            break;
        }

        // only cache when outside test environment
        if ($post && !current_user_can('edit_page', $post->ID)) {
            // expire in X minutes given by CACHE_EXPIRATION
            set_transient('social-plugin-cache-' . $currentPage['id'] . '-' . $option, $result, self::$CACHE_EXPIRATION);
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
        if (empty($page)) {
            ?>
            <div class="social-plugin-metadata-empty" style="text-align: center; font-size: smaller"><strong>[social-plugin-metadata]</strong><br /><?php echo sprintf(__('Facebook page not found or no access', 'social-plugin-metadata')); ?></div>
            <?php
            return;
        }

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
        if (empty($page)) {
            ?>
            <div class="social-plugin-metadata-empty" style="text-align: center; font-size: smaller"><strong>[social-plugin-metadata]</strong><br /><?php echo sprintf(__('Facebook page not found or no access', 'social-plugin-metadata')); ?></div>
            <?php
            return;
        }

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
        if (empty($page)) {
            ?>
            <div class="social-plugin-metadata-empty" style="text-align: center; font-size: smaller"><strong>[social-plugin-metadata]</strong><br /><?php echo sprintf(__('Facebook page not found or no access', 'social-plugin-metadata')); ?></div>
            <?php
            return;
        }

        if (isset($options['max_age'])) {
            $now = new \DateTime();
            $page['data'] = array_filter($page['data'] ?? [], function ($p) use ($now, $options) {
                $d = new \DateTime($p['created_time']);
                $diff = ($now->getTimestamp() - $d->getTimestamp()) / 60;

                return $diff < intval($options['max_age']);
            });
        }

        if (isset($options['max_words'])) {
            $maxWords = intval($options['max_words']);
        }
        

        if (empty($page['data'])) {
            ?>
            <div class="social-plugin-metadata-empty" style="text-align: center"><?php echo (empty($options['empty_message']) ? __('Currently there are no updated posted available on Facebook', 'social-plugin-metadata') : esc_attr($options['empty_message'])); ?></div>
            <?php
            return;
        }

        echo '<div class="social-plugin-lastposts">';

        foreach ($page['data'] as $lastPost) {
            $created = new \DateTime($lastPost['created_time']);
            $now = new \DateTime();

            if (!isset($lastPost['message'])) {
                $lastPost['message'] = '<i>' . __('Some information has been updated on Facebook', 'social-plugin-metadata') .'</i>';
            }

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

            if (!empty($maxWords)) {
                $l = strlen($lastPost['message']);
                $lastPost['message'] = implode(' ', array_slice(explode(' ', $lastPost['message']), 0, $maxWords));

                if (strlen($lastPost['message']) < $l) {
                    $lastPost['message'] .= '...';
                }
            }

            ?>
            <div class="social-plugin-metadata-lastpost">
                <div><?php echo $lastPost['message'] ?></div>
                <div class="social-plugin-metadata-lastpost-footer">
                    <div class="social-plugin-metadata-lastpost-link">
                        <small>
                        <a href="<?php echo $lastPost['permalink_url']; ?>" target="_blank">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/c/c2/F_icon.svg" style="width: 18px; vertical-align: middle;" />
                            <?php _e('Show on Facebook', 'social-plugin-metadata') ?>
                        </a>
                        </small>
                    </div>
                    <div class="social-plugin-metadata-lastpost-created"><small><?php echo $friendlyDiff; ?></small></div>
                </div>
            </div>
            <?php
        }

        echo '</div>';
    }

    public function fb_get_page_options()
    {
        $result = get_option(self::$WP_OPTION_PAGES, []);

        if (empty($_POST['pretty'])) {
            header('Content-Type: application/json');   
        }

        array_walk($result, function (&$v) {
            $v['access_token'] = '***' . substr($v['access_token'], -10);
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
        $appId = sanitize_key($_POST['app_id']);
        $appSecret = sanitize_key($_POST['app_secret']);


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
                <form method="POST" id="fb-configure-app" style="margin-top: 1em">  
                    <h3><?php _e('Setup your Facebook App', 'social-plugin-metadata') ?></h3>
                    <div id="fb-gateway-custom">
                        <p><?php printf(__('Build your own %s and configure the APP ID as well as the APP KEY here', 'social-plugin-metadata'), '<a href="https://developers.facebook.com/apps/" target="_blank">Facebook App</a>') ?></p>
                        <div>
                            <label>Facebook App ID</label><br />
                            <input class="widefat" type="text" name="app_id" autocomplete="off" id="fbAppId" value="<?php echo get_option(self::$WP_OPTION_APPID, '') ?>" />
                        </div>
                        <div style="margin-top: 0.5em">
                            <label>Facebook App Secret</label><br />
                            <input class="widefat" type="password" name="app_secret" autocomplete="new-password" id="fbAppSecret" />
                        </div>
                        <div style="margin-top: 1em">
                            <button id="fb-appdata-save" type="submit" class="button hide-if-no-js">Save</button>
                        </div>
                    </div>
                </form>
                <div id="fb-login-app" style="margin-top: 1em" hidden>
                    <h3><?php _e('Connect with Facebook', 'social-plugin-metadata') ?></h3>
                    <div id="fb-gateway-container">
                        <p>
                            <?php _e('Please use the below Login & Sync button to synchronize the facebook pages', 'social-plugin-metadata') ?>
                        </p>
                        <button id="fb-gateway-login" class="button hide-if-no-js">Login and Sync</button>
                    </div>
                </div>
            </div>
            <div style="margin: 1em; flex-basis: 375px; flex-grow: 1">
                <h3><?php _e('Shortcodes', 'social-plugin-metadata') ?></h3>
                <p><?php _e('Optionally you can also use shortcodes to displav the related information from Facebook (E.g. Business hours, About Us or the last posts)', 'social-plugin-metadata') ?>.</p>
                <div style="font-family: monospace">
                    <div>
                        <ul>
                            <li>[fb-pageinfo-businesshours page_id="..." empty_message=""]</li>
                            <li>[fb-pageinfo-about page_id="..." empty_message=""]</li>
                            <li>[fb-pageinfo-lastpost page_id="..." limit="..." max_age="..." empty_message=""]</li>
                        </ul>
                    </div>
                </div>
                <h2>Legal Notices</h2>
                <p>
                    <strong>This plugin does not save any facebook data. <br />All necessary information will be stored on this server (<?php echo $_SERVER['HTTP_HOST'] ?>)</strong>
                </p>
                <div id="rawdata" style="font-family: monospace; white-space: pre; background-color: white; padding: 1em;">
<a href="javascript:void(0)" onclick="SocialPlugin.fbRawPages()">SHOW DATA</a>
                </div>
                <p>Futhermore the data protection policy from facebook applies for the given Facebook App</p>
            </div>
        </div>
        <?php
    }
}

SocialPlugin::get_instance();
?>
