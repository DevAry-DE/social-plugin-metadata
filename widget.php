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
        'About' => 'About',
        'LastPost' => 'Last Post'
    ];

    private $title = '';
    private $option = '';
    private $fb_page;
    private $fb_show_page;
    private $empty_message;

    public function __construct()
    {
        parent::__construct('fb-get-pageinfo', __('Social plugin - Metadata Widget', 'fb-get-pageinfo'), ['description' => __('Used to output several information gathered from a facebook page', 'fb-get-pageinfo')]);
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

        $currentPage = array_pop(
            array_filter(
                $pages,
                function ($v) {
                    return $v['id'] == $this->fb_page;
                }
            )
        );

        $result = Ole1986_FacebokPageInfo::get_instance()->processContentFromOption($currentPage, $this->option);
        
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
                        Ole1986_FacebokPageInfo::get_instance()->{'show' . $this->option}($result, $this->empty_message);
                    } else {
                        echo "<div><small>No option given for ". __('Facebook page info Widget', 'fb-get-pageinfo') ."<small></div>";
                    }
                    ?>
            <?php else: ?>
                <div><?php _e('Facebook page info Widget', 'fb-get-pageinfo') ?></div>
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
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:');?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $this->title ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('fb_page'); ?>"><?php _e('Facebook Page:', 'fb-get-pageinfo');?></label>
            <select name="<?php echo $this->get_field_name('fb_page'); ?>">
                <option value="">[select page]</option>
                <?php
                foreach ($pages as $value) {
                    echo '<option value='.$value['id'].' '. (($this->fb_page == $value['id']) ? 'selected':'') .'>'.$value['name'].'</option>';
                }
                ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('option'); ?>"><?php _e('Facebook Content:', 'fb-get-pageinfo');?></label>
            <select name="<?php echo $this->get_field_name('option'); ?>">
                <option value="">[select content]</option>
                <?php
                foreach ($this->supportedOptions as $k => $v) {
                    echo '<option value='.$k.' '. (($this->option == $k) ? 'selected':'') .'>'. __($v, 'fb-get-pageinfo').'</option>';
                }
                ?>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('fb_show_page'); ?>"><?php _e('Show page name:', 'fb-get-pageinfo');?></label>
            <input type="checkbox" id="<?php echo $this->get_field_id('fb_show_page'); ?>" name="<?php echo $this->get_field_name('fb_show_page'); ?>" type="text" <?php echo ($this->fb_show_page ? 'checked' : '') ?> value="1" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('empty_message'); ?>"><?php _e('Custom message when empty (optional):', 'fb-get-pageinfo');?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('empty_message'); ?>" name="<?php echo $this->get_field_name('empty_message'); ?>" type="text" value="<?php echo $this->empty_message ?>" />
        </p>
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
        $this->empty_message = !empty($instance['empty_message']) ? esc_attr($instance['empty_message']) : '';
    }
}