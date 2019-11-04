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
        //Yii::$app->redis->del('myLock2');exit;

        $a = Users::find()->where(['id'=>1738])->asArray()->one();
        $b = $a['nickname'];
        print_r($b);exit;
        return json_encode(['code'=>0, 'msg'=>'ok', 'data'=>$b]);exit;

        //print_r($b);exit;
        /*
        $redis = Yii::$app->redis;
        $llen = (int) $redis->llen('queue');
        $queueList = [];
        for ($i=0;$i<$llen;$i++) {
            $orderJson = $redis->rpop('queue');
            if ($orderJson) {
                $tmp = @json_decode($orderJson, true);
                if ($tmp) {
                    $queueList[] = $tmp;
                }
            }
        }

        $list = [];

        $llen2 = (int) $redis->llen('queue2');
        if ($llen2) {
            for ($j = 0; $j < $llen2; $j++) {
                $orderJson2 = $redis->rpop('queue2');
                if ($orderJson2) {
                    $tmp2 = @json_decode($orderJson2, true);
                    if ($tmp2) {
                        $list[$tmp2['userId']] = $tmp2;
                    }
                }
            }
        }

        foreach ($queueList as $val) {
            if (isset($list[$val['userId']])) {
                if ($val['t'] > 0 && $val['t'] < 100) {  //sub
                    $list[$val['userId']]['amount'] = bcsub($list[$val['userId']]['amount'], bcadd($val['orderPrice'], $val['fee'], 2), 2);
                    $list[$val['userId']]['outRemitNo'][] = $val['outRemitNo'];
                } else if ($val['t'] >= 100) {  //add
                    $list[$val['userId']]['amount'] = bcadd($list[$val['userId']]['amount'], $val['orderPrice'], 2);
                    $list[$val['userId']]['outRemitNo'][] = $val['outRemitNo'];
                }
            } else {
                if ($val['t'] > 0 && $val['t'] < 100) {  //sub
                    $list[$val['userId']] = [
                        'userId' => $val['userId'],
                        'amount' => bcsub(0, bcadd($val['orderPrice'], $val['fee'], 2), 2),
                        'outRemitNo' => [$val['outRemitNo']]
                    ];
                } else if ($val['t'] >= 100) {  //add
                    $list[$val['userId']] = [
                        'userId' => $val['userId'],
                        'amount' => bcadd(0, $val['orderPrice'], 2),
                        'outRemitNo' => [$val['outRemitNo']]
                    ];
                }
            }
        }

        $listJson = [];
        foreach ($list as $val) {
            $listJson[] = json_encode($val, JSON_UNESCAPED_UNICODE);
        }

        $redis->lpush('queue2', ...$listJson);
        */
    }

    public function actionTest2() {
        /*
        echo "'".json_encode(['t' => 1, 'stype' => 1,'userId'=>237, 'fee'=> 1.8, 'orderPrice' => 513, 'outRemitNo' => '123456', 'order_num' => 'dd'], JSON_UNESCAPED_UNICODE)."'";

        exit;
        $a = [
            json_encode(['t' => 1, 'stype' => 1,'userId'=>236, 'fee'=> 1.6, 'orderPrice' => 100, 'outRemitNo' => '123456', 'order_num' => 'EE'], JSON_UNESCAPED_UNICODE),
            json_encode(['t' => 100, 'stype' => 2,'userId'=>236, 'fee'=> 1.5, 'orderPrice' => 101, 'outRemitNo' => '123457', 'order_num' => 'ff'], JSON_UNESCAPED_UNICODE),
            json_encode(['t' => 1, 'stype' => 1,'userId'=>238, 'fee'=> 1.8, 'orderPrice' => 513, 'outRemitNo' => '123456', 'order_num' => 'dd'], JSON_UNESCAPED_UNICODE),
            json_encode(['t' => 1, 'stype' => 1,'userId'=>237, 'fee'=> 1.8, 'orderPrice' => 513, 'outRemitNo' => '123456', 'order_num' => 'dd'], JSON_UNESCAPED_UNICODE),
        ];

        Yii::$app->redis->lpush('queue', ...$a);
        */


    }

}
