<?php

namespace app\controllers;

use app\models\RoomUsers;
use app\models\Scores;
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
        /*
        $cache = Yii::$app->redis->get('T#CDCFF5C5C00430367BEFE5CDABCB5498');
        if ($cache) {
            $cacheList = json_decode($cache, true);
            $RoomUsers = RoomUsers::find()->where(['id'=>258])->one();
            $RoomUsers->nickname = $cacheList['nickname'];
            $RoomUsers->update_time = date('Y-m-d H:i:s');
            $RoomUsers->save();

        }



        $a = '2.0.0';
        $b = '1.9.0';
        echo vesionInt($a);
        var_dump(vesionInt($a) >= vesionInt($b));

        */


        //$Scores = (new Scores())->getLastYearScore2(2, true);

        //$this->jsonResponse['code'] = 0;
        //$this->jsonResponse['data'] = $Scores;
        //return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
        //print_r($Scores);

    }


}
