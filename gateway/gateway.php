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

        if ($this->isPublic) {
            add_action('wp_ajax_nopriv_fb_get_pages', [$this, 'fb_get_pages']);
        }
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