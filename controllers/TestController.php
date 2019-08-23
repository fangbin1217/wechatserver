<?php

namespace app\controllers;

use app\models\Users;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;

use app\models\Shops;
use app\models\Positions;


class TestController extends Controller
{

    public function actionTest() {

        $a = '1.9.0';
        $b = '1.9.0';
        echo vesionInt($a);
        var_dump($a === $b);

    }


}
