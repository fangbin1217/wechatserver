<?php

namespace app\controllers;

use app\models\RoomUsers;
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

        $RoomUsers = RoomUsers::find()->where(['id'=>258])->one();

        $name = Users::getNickname(1738);

        $RoomUsers->nickname = $name;
        $RoomUsers->update_time = date('Y-m-d H:i:s');
        $RoomUsers->save();
        $a = '2.0.0';
        $b = '1.9.0';
        echo vesionInt($a);
        var_dump(vesionInt($a) >= vesionInt($b));

    }


}
