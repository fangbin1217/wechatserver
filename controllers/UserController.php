<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\Users;

class UserController extends Controller
{

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionInfo()
    {
        $this->jsonResponse['msg'] = 'get userinfo fail';
        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);
                $this->jsonResponse['code'] = 0;
                $this->jsonResponse['msg'] = 'get userinfo success';
                $this->jsonResponse['data'] = [
                    'id' => $cacheList['id'], 'nickname' => $cacheList['nickname'], 'avatar' => $cacheList['avatar'],
                ];
            }
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    public function actionStartusers()
    {
        $this->jsonResponse['msg'] = 'get startusers fail';
        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);
                $queryStarting = (new Users())->queryStarting($cacheList['id']);
                if ($queryStarting) {
                    $this->jsonResponse['code'] = 0;
                    $this->jsonResponse['msg'] = 'get startusers success';
                    $this->jsonResponse['data'] = $queryStarting;
                }
            }
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    public function actionGetqrcode()
    {
        $this->jsonResponse['msg'] = 'get qrcode fail';
        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);
                $user_id = $cacheList['id'];
                Users::getQrcode($user_id);
            }
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    //扫码绑定房间接口
    public function actionBindroom() {
        $this->jsonResponse['msg'] = 'get qrcode fail';
        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        $binded_uid = $params['binded_uid'] ?? '';

        $isSave = false;
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);
                $user_id = $cacheList['id'];
                $nickname = $cacheList['nickname'];
                $isSave = Users::bindedRoom($user_id, $binded_uid, $nickname);
            }
        }

        if ($isSave) {
            $this->jsonResponse['code'] = 0;
            $this->jsonResponse['msg'] = 'success';
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    //保存得分(小计)
    public function actionSavescore() {

        $params = json_decode(file_get_contents('php://input'),true);
        /*
        $params = [
            ['user_id'=>1, 'score'=>50],['user_id'=>2, 'score'=>40],
            ['user_id'=>3, 'score'=>-10],['user_id'=>-30, 'score'=>-60],['user_id'=>0, 'score'=>10],

        ];
        */
        $isSave = Users::saveScore($params);

        if ($isSave) {
            $this->jsonResponse['code'] = 0;
            $this->jsonResponse['msg'] = 'success';
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    //保存得分(总计)
    public function actionSavetotalscore() {

        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        $isSave = false;
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);
                $user_id = $cacheList['id'];
                $isSave = Users::saveTotalScore($user_id);
            }
        }

        if ($isSave) {
            $this->jsonResponse['code'] = 0;
            $this->jsonResponse['msg'] = 'success';
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);

    }


}
