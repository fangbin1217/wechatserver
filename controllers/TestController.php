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
        /*
        $params = json_decode(file_get_contents('php://input'),true);
        $Shops = new Shops();
        $queryShop = $Shops->queryShop($params);
        if ($queryShop) {
            $this->jsonResponse['code'] = 0;
            $this->jsonResponse['msg'] = 0;
            $this->jsonResponse['data'] = $queryShop;
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);


        $params = json_decode(file_get_contents('php://input'),true);

        $access_token = $params['access_token'] ?? '';
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);
                $params['uid'] = $cacheList['id'];
            }
        }
        $params = [
            'shop_id' => 1, 'uid' => 3, 'star' => 5,
            'labels' => [
                ['label_id'=>1, 'is_choose'=>1]
            ]
        ];
        $saveComment = Shops::saveComment($params);
        if ($saveComment) {
            $this->jsonResponse['code'] = 0;
            $this->jsonResponse['msg'] = 'success';
        } else {
            $this->jsonResponse['msg'] = '评论失败:'.Shops::$error_msg;
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
        */

    }


}
