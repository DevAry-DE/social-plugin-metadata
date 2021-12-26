<?php

namespace Cloud86\WP\Social\Model;

abstract class FacebookRestApi
{
    abstract public function getAppID();
    abstract public function getAppSecret();

    public function __construct()
    {
        add_action('wp_ajax_fb_get_pages', [$this, 'fb_get_pages']);
        add_action('wp_ajax_fb_check_domain', [$this, 'fb_check_domain']);
        add_action('wp_ajax_fb_register_domain', [$this, 'fb_register_domain']);
    }

    /**
     * AJAX: Used to register your domain as part of the (remote) facebook app
     */
    public function fb_check_domain()
    {
        header('Content-Type: application/json');

        $domain = sanitize_text_field($_POST['domain']);

        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
            echo json_encode(['error' => 'Invalid domain given']);
            wp_die('', '', ['response' => 400]);
            return;
        }

        $url = $this->getAppID() . '?fields=app_domains&access_token=' . $this->getAppID() . '|' . $this->getAppSecret();

        $result = $this->fbGraphRequest($url);

        if (!empty($result['error'])) {
            echo json_encode($result);
            wp_die('', '', ['response' => 400]);
            return;
        }

        echo json_encode(in_array($domain, $result['app_domains']));

        wp_die();
    }

    /**
     * AJAX: Used to register the app domain on the given Facebook App
     */
    public function fb_register_domain()
    {
        header('Content-Type: application/json');

        $domain = sanitize_text_field($_POST['domain']);

        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
            echo json_encode(['error' => 'Invalid domain given']);
            wp_die('', '', ['response' => 400]);
            return;
        }

        // get all available domains first
        $url = $this->getAppID() . '?fields=app_domains&access_token=' . $this->getAppID() . '|' . $this->getAppSecret();
        $result = $this->fbGraphRequest($url);

        if (!empty($result['error'])) {
            echo json_encode($result);
            wp_die('', '', ['response' => 400]);
            return;
        }

        array_push($result['app_domains'], $domain);

        $updatedDomains = urlencode(json_encode($result['app_domains']));

        $url = $this->getAppID() . '?app_domains='. $updatedDomains .'&access_token=' . $this->getAppID() . '|' . $this->getAppSecret();;
        $result = $this->fbGraphRequest($url, true);

        if (!empty($result['error'])) {
            echo json_encode($result);
            wp_die('', '', ['response' => 400]);
            return;
        }

        echo json_encode($result['success']);

        wp_die();
    }

    /**
     * AJAX: Generate a facebook long-lived user token and fetch the account pages afterwards
     *
     * @return JSON the user accounts (aka the given pages) as json through ajax
     */
    public function fb_get_pages()
    {
        header('Content-Type: application/json');

        $shortLivedToken = sanitize_text_field($_POST['token']);
        $userID = sanitize_key($_POST['userID']);
        
        $url = "oauth/access_token?client_id=".$this->getAppID()."&client_secret=".$this->getAppSecret()."&grant_type=fb_exchange_token&fb_exchange_token=".$shortLivedToken;

        $result = $this->fbGraphRequest($url);

        if (!empty($result['error'])) {
            echo json_encode($result);
            wp_die('', '', ['response' => 400]);
            return;
        }

        $longLivedToken = $result['access_token'];
        
        $result = $this->fbGraphRequest("$userID/accounts?access_token=$longLivedToken");

        // return users known accounts (aka pages)
        echo json_encode($result);

        wp_die();
    }

    public function fbGraphRequest($url, $doPost = false)
    {
        $path = 'https://graph.facebook.com/';

        if ($doPost) {
            $resp = wp_remote_post($path . $url);
        } else {
            $resp = wp_remote_get($path . $url);
        }

        if ($resp instanceof \WP_Error) {
            return [];
        }
        
        return json_decode($resp['body'], true);
    }
}