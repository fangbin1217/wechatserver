<?php

if (YII_ENV == 'dev') {
    return [
        'class' => 'yii\db\Connection',
        'dsn' => 'mysql:host=localhost;dbname=wechat',
        'username' => 'root',
        'password' => '123456',
        'charset' => 'utf8mb4',

        // Schema cache options (for production environment)
        //'enableSchemaCache' => true,
        //'schemaCacheDuration' => 60,
        //'schemaCache' => 'cache',
    ];
} else {
    return [
        'class' => 'yii\db\Connection',
        'dsn' => 'mysql:host=127.0.0.1;dbname=wechat',
        'username' => 'root',
        'password' => '11Cda4b4ddca',
        'charset' => 'utf8mb4',
    ];
}
