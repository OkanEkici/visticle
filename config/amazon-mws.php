<?php

return [
    'store' => [
        'store1' => [
            'merchantId'       => env('WSM_AMZ_SELLER_ID', '_MWS_UNKNOWN_'),
            'marketplaceId'    => 'A1PA6795UKMFR9', //Germany Marketplace
            'keyId'            => env('WSM_AMZ_AWS_ACCESS_KEY_ID', '_MWS_UNKNOWN_'),
            'secretKey'        => env('WSM_AMZ_SECRET_KEY', '_MWS_UNKNOWN_'),
            'mwsAuthToken'     => env('WSM_AMZ_MWS_AUTH_TOKEN', '_MWS_UNKNOWN_'),
            'amazonServiceUrl' => 'https://mws-eu.amazonservices.com/',
            'muteLog'          => false,
        ],
    ],
];
