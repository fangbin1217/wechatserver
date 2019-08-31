<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'defaultRoute' => 'index',
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '8XiTbYMP3xKK7Gd460mCfQLlZK-mCRXe',
            'enableCsrfValidation' => false,
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'login/error',
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                /*
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
                */
                'error_file' => [  //error_file是自定义的，方便见名知道意思
                    'logFile' => '@runtime/logs/error.'.date('Ym').'.log',//日志存放目录
                    'class' => 'yii\log\FileTarget', //指定使用4个方式其中之以的文件存储方式
                    'levels' => ['error'],//存放级别，错误级别的
                    'maxLogFiles' => 200,//最多存放的日志文件数
                    'logVars' => ['_POST', '_FILES', '_COOKIE', '_SESSION','_SERVER'],
                ],
                'warning_file' => [
                    'logFile' => '@runtime/logs/warning.'.date('Ym').'.log',//日志存放目录
                    'class' => 'yii\log\FileTarget', //指定使用4个方式其中之以的文件存储方式
                    'levels' => ['warning'],//存放级别，错误级别的
                    'maxLogFiles' => 200,//最多存放的日志文件数
                    'logVars' => ['_POST', '_FILES', '_COOKIE', '_SESSION','_SERVER'],
                ]
            ],
        ],
        'db' => $db,
        'redis' => [
            'class' => 'yii\redis\Connection',
            'hostname'      => '127.0.0.1',
            'port'          => 6379,
            'database'      => 0,
            //'password'      => ''
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
