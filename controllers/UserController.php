<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\Users;
use app\models\Rooms;


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
                    'uid' => $cacheList['id'], 'nickname' => $cacheList['nickname'], 'avatar' => $cacheList['avatar'],
                    'vip' => 0, 'DATE' => YII_ENV.date('Y-m-d H:i:s'), 'colorClass' => ''
                ];

                if (isset($cacheList['vip'])) {
                    $this->jsonResponse['data']['vip'] = $cacheList['vip'];
                }

                $getColorClass = Users::getColorClass($cacheList['id'], $this->jsonResponse['data']['vip']);
                $this->jsonResponse['data']['colorClass'] = $getColorClass;
                //如果是扫码进来 就绑定用户及房间
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
                    $this->jsonResponse['userCount'] = count($queryStarting);
                    $this->jsonResponse['isRoomOwner'] = Rooms::isRoomOwner($cacheList['id']);
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

                $qrcode = Users::getMyQrcode($cacheList['id']);
                //$qrcode = $cacheList['qrcode'];
                if ($qrcode) {
                    $this->jsonResponse['code'] = 0;
                    $this->jsonResponse['msg'] = 'get qrcode success';
                    $this->jsonResponse['data'] = Yii::$app->params['serverHost'].$qrcode;
                }
            }
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    //保存得分(小计)
    public function actionSavescore() {
        $params = json_decode(file_get_contents('php://input'),true);

        $startUsers = $params['startUsers'] ?? [];
        $isSave = Users::saveScore($startUsers);
        if ($isSave) {
            $this->jsonResponse['code'] = 0;
            $this->jsonResponse['msg'] = 'success';

            $this->jsonResponse['xiaoji'] = [];
            $this->jsonResponse['total'] = [];
            $this->jsonResponse['userCount'] = 0;
            $access_token = $params['access_token'] ?? '';
            if ($access_token) {
                $cache = Yii::$app->redis->get('T#' . $access_token);
                if ($cache) {
                    $cacheList = json_decode($cache, true);
                    $queryStarting = (new Users())->queryStartingUsers($cacheList['id']);
                    $this->jsonResponse['data'] = $queryStarting;
                    $queryStartingScore = (new Users())->queryStartingScore($cacheList['id']);
                    if ($queryStartingScore) {
                        $this->jsonResponse['xiaoji'] = $queryStartingScore['xiaoji'];
                        $this->jsonResponse['total'] = $queryStartingScore['total'];
                        $this->jsonResponse['userCount'] = count($queryStarting);
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

        $params = json_decode(file_get_contents('php://input'),true);

        $startUsers = $params['startUsers'] ?? [];
        $isSave = Users::saveScore($startUsers, true);
        if ($isSave) {
            $this->jsonResponse['code'] = 0;
            $this->jsonResponse['msg'] = 'success';

            $this->jsonResponse['xiaoji'] = [];
            $this->jsonResponse['total'] = [];
            $this->jsonResponse['userCount'] = 0;
            $access_token = $params['access_token'] ?? '';
            if ($access_token) {
                $cache = Yii::$app->redis->get('T#' . $access_token);
                if ($cache) {
                    $cacheList = json_decode($cache, true);
                    $queryStarting = (new Users())->queryStartingUsers($cacheList['id']);
                    $this->jsonResponse['data'] = $queryStarting;
                    $queryStartingScore = (new Users())->queryStartingScore($cacheList['id']);
                    if ($queryStartingScore) {
                        $this->jsonResponse['xiaoji'] = $queryStartingScore['xiaoji'];
                        $this->jsonResponse['total'] = $queryStartingScore['total'];
                        $this->jsonResponse['userCount'] = count($queryStarting);
                    }
                }
            }
        } else {
            $this->jsonResponse['msg'] = Users::$error_msg;
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);

    }

    public function actionVip() {
        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        $this->jsonResponse['msg'] = '升级失败！';
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);
                $isSave = Users::saveVip($cacheList, $access_token);
                if ($isSave) {
                    $this->jsonResponse['code'] = 0;
                    $this->jsonResponse['msg'] = 'success';
                }
            }
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    public function actionUpdclass() {
        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        $color_class = $params['color_class'] ?? '';
        $this->jsonResponse['msg'] = '更新失败！';
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);
                $vip = isset($cacheList['vip']) ?? 0;
                $isSave = Users::updClass($cacheList['id'], $color_class, $vip);
                if ($isSave) {
                    $this->jsonResponse['code'] = 0;
                    $this->jsonResponse['msg'] = 'success';
                }
            }
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    public function actionBind() {
        $this->jsonResponse['msg'] = '添加玩家失败';
        $this->jsonResponse['data']['isFull'] = 0;
        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        $nickname = $params['nickname'] ?? '';
        $nickname = trim($nickname);
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);
                $isSave = Users::bindedRoom(0, $cacheList['id'], $nickname, true);
                $this->jsonResponse['data']['isFull'] = Users::$isFull;
                if ($isSave) {
                    $this->jsonResponse['code'] = 0;
                    $this->jsonResponse['msg'] = '添加玩家成功';
                } else {
                    if ($this->jsonResponse['data']['isFull'] == 1) {
                        $this->jsonResponse['msg'] = '最多支持4人';
                    }

                    $this->jsonResponse['msg'] = Users::$error_msg;
                }


            }
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }




}
