<?php

interface Ole1986_IFacebookGatewayHost
{
    public function getAppID();
    public function getAppSecret();

    public function fbGraphRequest($url);
}