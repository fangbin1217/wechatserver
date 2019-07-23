<?php

$commonParams = [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    'appid' => 'wx547bae972d6fecf9',
    'appsercet' => '1f391c7cedf7862260078eba1924d31d',
    'randkey' => 'GR511M',
    'loginCacheTime' => 864000, //登陆状态记录时间，单位为秒
    'withoutlogin' => ['login'],
    'name_fa' => '台板',
    'image_fa' => 'images/fa.png',
    'image_jiajian' => '../../images/jiahao.png',
    'image_no1' => '../../images/NO.png',
    'image_no2' => '../../images/NO2.png',
    'wechat_avatar' => 86400, //微信头像
    'wechat_nickname' => 2592000, //微信昵称
    'history_avatar' => 2592000,  //历史头像
    'roomCacheTime' => 86400, //房间缓存,
    'qrcodeImageTime' => 2592000, //qrcode缓存
    'XCX_ACCESS_TOKEN' => 7200,
    'MAX_PERSON_NUM' => 5, //最大人数上限
    'MAX_NICKNAME' => 8,
    'red' => '#E64340',
    'green' => '#09BB07',
    'black'=> '#353535',
];

if (YII_ENV == 'dev') {
    $commonParams['serverHost'] = 'http://www.wechatserver.com/';
    $commonParams['imageFirstPath'] = '/usr/local/var/www/wechatserver/web';
} else {
    $commonParams['serverHost'] = 'https://jifen.myshared.top/';
    $commonParams['imageFirstPath'] = '/home/www/jifen/wechatserver/web';

}

return $commonParams;

