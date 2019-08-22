<?php

namespace app\controllers;

use app\models\Shops;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\Positions;



class ShopController extends Controller
{

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        $shop_name = $params['shop_name'] ?? '';
        $shop_name = trim($shop_name);
        $page = $params['page'] ?? '';
        $page = (int) $page;

        $city_name = '';
        $province_name = '';
        $cache = Yii::$app->redis->get('T#' . $access_token);
        if ($cache) {
            $cacheList = json_decode($cache, true);
            $p = Positions::getPosition($cacheList['id']);
            if ($p) {
                $province_name = $p['province_name'];
                $city_name = $p['city_name'];
            }
        }
        if (!$city_name) {
            $this->jsonResponse['code'] = -1;
            $this->jsonResponse['msg'] = '定位获取失败';
            return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);

        }

        $list = Shops::shopList($page,$city_name,$province_name, $shop_name);
        $this->jsonResponse['code'] = 0;
        $this->jsonResponse['msg'] = 'success';
        $this->jsonResponse['data'] = $list;
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    public function actionSavep() {
        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        $cache = Yii::$app->redis->get('T#' . $access_token);
        if ($cache) {
            $cacheList = json_decode($cache, true);
            $params['uid'] = $cacheList['id'];
            $params['city_name'] = $params['city_name'] ?? '';
            $params['city_name'] = trim($params['city_name']);
            if (!$params['city_name']) {
                $this->jsonResponse['msg'] = '获取城市失败';
                return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
            }
            $params['province_name'] = $params['province_name'] ?? '';
            $params['province_name'] = trim($params['province_name']);
            $isSave = (new Positions())->saveP($params);

            $this->jsonResponse['msg'] = '定位保存失败';
            if ($isSave) {
                $this->jsonResponse['code'] = 0;
                $this->jsonResponse['msg'] = 'success';
            }
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    public function actionSavecomment() {
        $params = json_decode(file_get_contents('php://input'),true);

        $access_token = $params['access_token'] ?? '';
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);
                $params['uid'] = $cacheList['id'];
            }
        }
        /*
        $params = [
            'shop_id' => 1, 'uid' => 3, 'star' => 5,
            'labels' => [
                ['label_id'=>1, 'is_choose'=>1]
            ]
        ];
        */
        $saveComment = Shops::saveComment($params);
        if ($saveComment) {
            $this->jsonResponse['code'] = 0;
            $this->jsonResponse['msg'] = 'success';
        } else {
            $this->jsonResponse['msg'] = Shops::$error_msg;
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    public function actionComment() {
        $params = json_decode(file_get_contents('php://input'),true);
        $Shops = new Shops();
        $queryShop = $Shops->queryShop($params);
        if ($queryShop) {
            $this->jsonResponse['code'] = 0;
            $this->jsonResponse['msg'] = '';
            $this->jsonResponse['data'] = $queryShop;
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }


}
