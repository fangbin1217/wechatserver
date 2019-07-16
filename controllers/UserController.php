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
        $bind_uid = $params['bind_uid'] ?? '';
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);
                $this->jsonResponse['code'] = 0;
                $this->jsonResponse['msg'] = 'get userinfo success';
                $this->jsonResponse['data'] = [
                    'id' => $cacheList['id'], 'nickname' => $cacheList['nickname'], 'avatar' => $cacheList['avatar']
                ];
                if ($bind_uid) {
                    $isSave = Users::bindedRoom($cacheList['id'], $bind_uid, $cacheList['nickname']);
                }
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
                $queryStarting = (new Users())->queryStartingUsers($cacheList['id']);
                if ($queryStarting) {
                    $this->jsonResponse['code'] = 0;
                    $this->jsonResponse['msg'] = 'get startusers success';
                    $this->jsonResponse['data'] = $queryStarting;
                    $this->jsonResponse['xiaoji'] = [];
                    $this->jsonResponse['total'] = [];
                    $queryStartingScore = (new Users())->queryStartingScore($cacheList['id']);
                    if ($queryStartingScore) {
                        $this->jsonResponse['xiaoji'] = $queryStartingScore['xiaoji'];
                        $this->jsonResponse['total'] = $queryStartingScore['total'];
                    }

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
                $qrcode = $cacheList['qrcode'];
                if ($qrcode) {
                    $this->jsonResponse['code'] = 0;
                    $this->jsonResponse['msg'] = 'get qrcode success';
                    $this->jsonResponse['data'] = Yii::$app->params['serverHost'].$qrcode;
                }
            }
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    //扫码绑定房间接口
    public function actionBindroom() {
        /*
        $this->jsonResponse['msg'] = 'get qrcode fail';
        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        $binded_uid = $params['bind_uid'] ?? '';

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
        */
    }

    //保存得分(小计)
    public function actionSavescore() {

        /*
        $this->jsonResponse['code'] = -1;
        $this->jsonResponse['msg'] = '不都为0';
        sleep(2);
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);



        $params = [
            ['user_id'=>1, 'score'=>50],['user_id'=>2, 'score'=>40],
            ['user_id'=>3, 'score'=>-10],['user_id'=>-30, 'score'=>-60],['user_id'=>0, 'score'=>10],

        ];
        */
        $params = json_decode(file_get_contents('php://input'),true);

        $startUsers = $params['startUsers'] ?? [];
        $isSave = Users::saveScore($startUsers);
        if ($isSave) {
            $this->jsonResponse['code'] = 0;
            $this->jsonResponse['msg'] = 'success';

            $this->jsonResponse['xiaoji'] = [];
            $this->jsonResponse['total'] = [];
            $access_token = $params['access_token'] ?? '';
            if ($access_token) {
                $cache = Yii::$app->redis->get('T#' . $access_token);
                if ($cache) {
                    $cacheList = json_decode($cache, true);
                    $queryStartingScore = (new Users())->queryStartingScore($cacheList['id']);
                    if ($queryStartingScore) {
                        $this->jsonResponse['xiaoji'] = $queryStartingScore['xiaoji'];
                        $this->jsonResponse['total'] = $queryStartingScore['total'];
                    }
                }
            }
        } else {
            $this->jsonResponse['msg'] = Users::$error_msg;
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    //保存得分(总计)
    public function actionSavetotalscore() {
        $this->jsonResponse['code'] = 0;
        $this->jsonResponse['msg'] = 'success';
        sleep(2);
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
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
