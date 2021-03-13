<?php
/**
 * The Facebook page info Widget used to display in sidebars or footer bars (dependent on the theme)
 */

class Ole1986_FacebokPageInfoWidget extends WP_Widget
{
    /**
     * Supported option to output various page content
     */
    public $supportedOptions = [
        'BusinessHours' => 'Business hours',
        'About' => 'About us',
        'LastPost' => 'Last Posts'
    ];

    private $title = '';
    private $option = '';
    private $fb_page;
    private $fb_show_page;
    private $options = [];

    public function __construct()
    {
        parent::__construct('social-plugin-metadata-widget', __('Social plugin - Metadata Widget', 'social-plugin-metadata'), ['description' => __('Used to output several information gathered from a facebook page', 'social-plugin-metadata')]);
    }

    /**
     * Display the widget onto the frontend
     * 
     * @param array $args     the arguments given to the wordpress widget
     * @param array $instance contains the current settings
     */
    public function widget($args, $instance)
    {
        $this->parseSettings($instance);

        $pages = get_option(Ole1986_FacebokPageInfo::$WP_OPTION_PAGES, []);

        $filteredPages = array_filter(
            $pages,
            function ($v) {
                return $v['id'] == $this->fb_page;
            }
        );

        $currentPage = array_pop($filteredPages);

        $result = Ole1986_FacebokPageInfo::get_instance()->processContentFromOption($currentPage, $this->option, $this->options);
        
        // before and after widget arguments are defined by themes
        echo $args['before_widget'];
        echo $args['before_title'] . $this->title . $args['after_title'];
        ?>
        <div id="fb-pageinfo-widget">
            <?php if (empty($result['error'])) : ?>
                <?php if ($this->fb_show_page) : ?>
                    <h4 class="social-plugin-metadata-title"><?php echo $currentPage['name']; ?></h4>
                <?php endif; ?>
                    <?php 
                    if (!empty($this->option)) {
                        Ole1986_FacebokPageInfo::get_instance()->{'show' . $this->option}($result, $this->options);
                    } else {
                        echo "<div><small>No option given for ". __('Facebook page info Widget', 'social-plugin-metadata') ."<small></div>";
                    }
                    ?>
            <?php else: ?>
                <div><?php _e('Facebook page info Widget', 'social-plugin-metadata') ?></div>
                <?php if (!empty($result['error'])) : ?>
                    <div><small><?php echo $result['error']['message'] ?></small></div>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>

        <?php
        echo $args['after_widget'];
    }

    /**
     * Show the widget form in admin area to manage the widget settings
     * 
     * @param array $instance the settings saved as array
     */
    public function form($instance)
    {
        $this->parseSettings($instance);
        $pages = get_option(Ole1986_FacebokPageInfo::$WP_OPTION_PAGES, []);
        ?>
        <div class="social-widget-metadata-widget">
            <p>
                <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:');?></label>
                <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $this->title ?>" />
            </p>
            <p>
                <label for="<?php echo $this->get_field_id('fb_show_page'); ?>"><?php _e('Show page name:', 'social-plugin-metadata');?></label>
                <input type="checkbox" id="<?php echo $this->get_field_id('fb_show_page'); ?>" name="<?php echo $this->get_field_name('fb_show_page'); ?>" type="text" <?php echo ($this->fb_show_page ? 'checked' : '') ?> value="1" />
            </p>
            <p>
                <label for="<?php echo $this->get_field_id('fb_page'); ?>"><?php _e('Facebook Page:', 'social-plugin-metadata');?></label>
                <select name="<?php echo $this->get_field_name('fb_page'); ?>">
                    <?php
                    foreach ($pages as $value) {
                        echo '<option value='.$value['id'].' '. (($this->fb_page == $value['id']) ? 'selected':'') .'>'.$value['name'].'</option>';
                    }
                    ?>
                </select>
            </p>
            <p>
                <label for="<?php echo $this->get_field_id('option'); ?>"><?php _e('Facebook Content:', 'social-plugin-metadata');?></label>
                <select class="social-plugin-metadata-widget-contenttype" id="<?php echo $this->get_field_id('option'); ?>" name="<?php echo $this->get_field_name('option'); ?>">
                    <?php
                    foreach ($this->supportedOptions as $k => $v) {
                        echo '<option value='.$k.' '. (($this->option == $k) ? 'selected':'') .'>'. __($v, 'social-plugin-metadata').'</option>';
                    }
                    ?>
                </select>
            </p>
            <p class="social-plugin-metadata-limit-comtainer">
                <label for="<?php echo $this->get_field_id('options[limit]'); ?>"><?php _e('Number of posts:', 'social-plugin-metadata');?></label>
                <select id="<?php echo $this->get_field_id('options[limit]'); ?>" name="<?php echo $this->get_field_name('options[limit]'); ?>">
                    <option value="">[All]</option>
                    <?php
                    $selected = $this->options['limit'] ?? '';
                    foreach (range(1, 10) as $v) {
                        echo '<option value='.$v.' '. (($selected == $v) ? 'selected':'') .'>'. $v.'</option>';
                    }
                    ?>
                </select>
            </p>
            <p>
                <label for="<?php echo $this->get_field_id('options[empty_message]'); ?>"><?php _e('Custom message when empty (optional):', 'social-plugin-metadata');?></label>
                <input class="widefat" id="<?php echo $this->get_field_id('options[empty_message]'); ?>" name="<?php echo $this->get_field_name('options[empty_message]'); ?>" type="text" value="<?php echo $this->options['empty_message'] ?? '' ?>" />
            </p>
        </div>
        <?php
    }

    /**
     * Parse the widget settings into its current class object
     * 
     * @param array $instance the widget settings
     */
    private function parseSettings($instance)
    {
        $this->title = isset($instance['title']) ? esc_attr($instance['title']) : "";
        $this->option = isset($instance['option']) ? esc_attr($instance['option']) : "";
        $this->fb_page = isset($instance['fb_page']) ? esc_attr($instance['fb_page']) : "";
        $this->fb_show_page = !empty($instance['fb_show_page']) ?true : false;
        $this->options = $instance['options'] ?? [];
    }
}