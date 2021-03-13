<?php

class Ole1986_FacebookGateway
{
    private $shortcodeName = 'fb-gateway';

    /**
     * @var Ole1986_IFacebookGatewayHost gateway
     */
    private $host;
    private $isPublic;

    public function __construct($gwHost, $isPublic = false)
    {
        $this->host = $gwHost;
        $this->isPublic = $isPublic;

        add_action('wp_ajax_fb_get_pages', [$this, 'fb_get_pages']);
        add_action('wp_ajax_fb_check_domain', [$this, 'fb_check_domain']);
        add_action('wp_ajax_fb_register_domain', [$this, 'fb_register_domain']);

        if ($this->isPublic) {
            add_action('wp_ajax_nopriv_fb_get_pages', [$this, 'fb_get_pages']);
            add_action('wp_ajax_nopriv_fb_check_domain', [$this, 'fb_check_domain']);
            add_action('wp_ajax_nopriv_fb_register_domain', [$this, 'fb_register_domain']);
        }
    }

    /**
     * AJAX: Used to register your domain as part of the (remote) facebook app
     */
    public function fb_check_domain()
    {
        if ($this->isPublic) {
            header("Access-Control-Allow-Origin: *");
        }

        header('Content-Type: application/json');

        $domain = sanitize_text_field($_POST['domain']);

        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
            echo json_encode(['error' => 'Invalid domain given']);
            wp_die('', '', ['response' => 400]);
            return;
        }

        $url = $this->host->getAppID() . '?fields=app_domains&access_token=' . $this->host->getAppID() . '|' . $this->host->getAppSecret();

        $result = $this->host->fbGraphRequest($url);

        if (!empty($result['error'])) {
            echo json_encode($result);
            wp_die('', '', ['response' => 400]);
            return;
        }

        echo json_encode(in_array($domain, $result['app_domains']));

        wp_die();
    }

    public function fb_register_domain()
    {
        if ($this->isPublic) {
            header("Access-Control-Allow-Origin: *");
        }

        header('Content-Type: application/json');

        $domain = sanitize_text_field($_POST['domain']);

        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
            echo json_encode(['error' => 'Invalid domain given']);
            wp_die('', '', ['response' => 400]);
            return;
        }

        // get all available domains first
        $url = $this->host->getAppID() . '?fields=app_domains&access_token=' . $this->host->getAppID() . '|' . $this->host->getAppSecret();
        $result = $this->host->fbGraphRequest($url);

        if (!empty($result['error'])) {
            echo json_encode($result);
            wp_die('', '', ['response' => 400]);
            return;
        }

        array_push($result['app_domains'], $domain);

        $updatedDomains = urlencode(json_encode($result['app_domains']));

        $url = $this->host->getAppID() . '?app_domains='. $updatedDomains .'&access_token=' . $this->host->getAppID() . '|' . $this->host->getAppSecret();;
        $result = $this->host->fbGraphRequest($url, true);

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
        if ($this->isPublic) {
            header("Access-Control-Allow-Origin: *");
        }

        header('Content-Type: application/json');

        $shortLivedToken = sanitize_text_field($_POST['token']);
        $userID = sanitize_key($_POST['userID']);
        
        $url = "oauth/access_token?client_id=".$this->host->getAppID()."&client_secret=".$this->host->getAppSecret()."&grant_type=fb_exchange_token&fb_exchange_token=".$shortLivedToken;

        $result = $this->host->fbGraphRequest($url);

        if (!empty($result['error'])) {
            echo json_encode($result);
            wp_die('', '', ['response' => 400]);
            return;
        }

        $longLivedToken = $result['access_token'];
        
        $result = $this->host->fbGraphRequest("$userID/accounts?access_token=$longLivedToken");

        // return users known accounts (aka pages)
        echo json_encode($result);

        wp_die();
    }
}