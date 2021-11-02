<?php

return [
    'app_id'=>'a0846e6b-b98d-4536-aaae-ba1eeecd09c5',
    'app_secret_key'=>'fb991770-af78-46cc-8767-a7d4d99c5539',
    'app_url_path'=>'/api/wix/install-start',
    'redirect_url_path'=>'/api/wix/install-complete',
    'wix_install_url'=>'https://www.wix.com/installer/install',
    'wix_authorization_url'=>'https://www.wix.com/oauth/access',
    'wix_finish_url'=>'https://www.wix.com/_api/site-apps/v1/site-apps/token-received??access_token=',

    //jetzt kommen API-URLs

    //für Access-Token
    'access_token'=>[
        'access_token_url'=>'https://www.wix.com/oauth/access',
        'refresh_token_url'=>'https://www.wix.com/oauth/access',
        //in Minutes
        'valid_time'=>10,
    ],

];
