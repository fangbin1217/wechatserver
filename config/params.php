<?php

if (YII_ENV == 'dev') {
    return [
        'adminEmail' => 'admin@example.com',
        'senderEmail' => 'noreply@example.com',
        'senderName' => 'Example.com mailer',
        'appid' => 'wx547bae972d6fecf9',
        'appsercet' => '1f391c7cedf7862260078eba1924d31d',
        'randkey' => 'GR511M',
        'loginCacheTime' => 86400, //登陆状态记录时间，单位为秒
        'withoutlogin' => ['login'],
        'serverHost' => 'http://www.wechatserver.com/'
    ];
} else {
    return [
        'adminEmail' => 'admin@example.com',
        'senderEmail' => 'noreply@example.com',
        'senderName' => 'Example.com mailer',
        'appid' => 'wx547bae972d6fecf9',
        'appsercet' => '1f391c7cedf7862260078eba1924d31d',
        'randkey' => 'GR511M',
        'loginCacheTime' => 86400, //登陆状态记录时间，单位为秒
        'withoutlogin' => ['login'],
        'serverHost' => 'https://jifen.myshared.top/'
    ];
}

