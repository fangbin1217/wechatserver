<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\Users;
use app\models\Rooms;
use app\models\RoomUsers;

class UserController extends Controller
{

    public function actionUpdinfo()
    {
        $this->jsonResponse['msg'] = 'upd userinfo fail';
        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        $nickname = $params['nickname'] ?? '';
        $nickname = trim($nickname);
        $avatar = $params['avatar'] ?? '';
        $avatar = trim($avatar);
        if (!$avatar) {
            $this->jsonResponse['msg'] = 'avatar empty';
            return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
        }
        if (!$nickname) {
            $nickname = uniqid();
        }

        //$formId = $params['formId'] ?? '';

        $time = time();
        $date = date('Y-m-d H:i:s');
        $expire_time = $time + Yii::$app->params['loginCacheTime'];
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);
                $users = Users::find()->where(['id'=>$cacheList['id']])->one();
                $users->nickname = $nickname;
                $users->avatar = $avatar;
                $users->expire_time = $expire_time;
                $users->update_time = $date;
                $users->avatar_updtime = $time;

                if ($users->save()) {

                    $result['code'] = 0;
                    $this->jsonResponse['code'] = 0;
                    $this->jsonResponse['msg'] = 'upd userinfo success';

                    $local_avatar = Yii::$app->params['image_default'];
                    if ($users->local_avatar) {
                        $local_avatar = Yii::$app->params['serverHost'].$users->local_avatar;
                    }

                    $getColorClass = Users::getColorClass($cacheList['id'], $cacheList['vip']);

                    $this->jsonResponse['data'] = [
                        'uid' => $cacheList['id'],
                        'nickName' => $nickname,
                        'avatarUrl' => $avatar,
                        'localAvatar' => $local_avatar,
                        'vip' => false,
                        'colorClass' => $getColorClass,
                        'isLogin' => true,
                        'box' => getRandData(true),
                        'isChecked' => Yii::$app->params['isChecked']
                    ];

                    if ($this->jsonResponse['data']['isLogin']) {
                        $vip = $cacheList['vip'] ?? '';
                        if ($vip) {
                            $this->jsonResponse['data']['vip'] = true;
                        }
                    }

                    $cacheList = Users::getUserInfo($cacheList['id']);
                    Yii::$app->redis->set('T#'.$access_token, json_encode($cacheList, JSON_UNESCAPED_UNICODE));
                    Yii::$app->redis->expire('T#'.$access_token, Yii::$app->params['loginCacheTime']);


                    Yii::$app->redis->set('AVATAR#'.$cacheList['id'], $avatar);
                    Yii::$app->redis->expire('AVATAR#'.$cacheList['id'], Yii::$app->params['wechat_avatar']);

                    Yii::$app->redis->set('NICKNAME#'.$cacheList['id'], $nickname);
                    Yii::$app->redis->expire('NICKNAME#'.$cacheList['id'], Yii::$app->params['wechat_nickname']);


                    $RoomUsers = RoomUsers::find()->where(['user_id'=>$cacheList['id'], 'is_del'=>0])->orderBy(['id'=>SORT_DESC])->asArray()->one();
                    if ($RoomUsers) {
                       $RoomUsers2 = RoomUsers::find()->where(['id'=>$RoomUsers['id'], 'is_del'=>0])->one();
                        $RoomUsers2->nickname = $nickname;
                        $RoomUsers2->update_time = $date;
                        $RoomUsers2->save();
                    }

                }

                //$openId = $cacheList['openid'] ?? '';
                //Users::collectFormId($formId, $openId);
            }

        }

        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);


    }

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

                $local_avatar = Yii::$app->params['image_default'];
                if ($cacheList['local_avatar']) {
                    $local_avatar = Yii::$app->params['serverHost'].$cacheList['local_avatar'];
                }
                $this->jsonResponse['data'] = [
                    'uid' => $cacheList['id'],
                    'nickName' => $cacheList['nickname'],
                    'avatarUrl' => $cacheList['avatar'],
                    'vip' => false,
                    'colorClass' => '',
                    'localAvatar' => $local_avatar,
                    'isLogin' => false,
                    'box' => getRandData(true),
                    'isChecked' => Yii::$app->params['isChecked']
                ];



                if ($cacheList['avatar'] !== Yii::$app->params['image_default']) {

                    $avatarUpdTime = $cacheList['avatar_updtime'] ?? '';
                    $avatarUpdTime = (int) $avatarUpdTime;
                    if ($avatarUpdTime) {
                        $be = time() - $avatarUpdTime;
                        if ($be < Yii::$app->params['loginCacheTime']) {
                            $this->jsonResponse['data']['isLogin'] = true;
                        }

                    }

                    if (!$this->jsonResponse['data']['isLogin']) {
                        $this->jsonResponse['data']['avatarUrl'] = Yii::$app->params['image_default'];
                        $this->jsonResponse['data']['nickName'] = '未设置';
                    }


                }

                if ($this->jsonResponse['data']['isLogin']) {
                    $vip = $cacheList['vip'] ?? '';
                    if ($vip) {
                        $this->jsonResponse['data']['vip'] = true;
                    }
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
        $version = $params['version'] ?? '';
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
                    $queryStartingScore = (new Users())->queryStartingScore($cacheList['id'], $version);
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
        $version = $params['version'] ?? '';
        $startUsers = $params['startUsers'] ?? [];
        //$formId = $params['formId'] ?? '';

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
                    $queryStartingScore = (new Users())->queryStartingScore($cacheList['id'], $version);
                    if ($queryStartingScore) {
                        $this->jsonResponse['xiaoji'] = $queryStartingScore['xiaoji'];
                        $this->jsonResponse['total'] = $queryStartingScore['total'];
                        $this->jsonResponse['userCount'] = count($queryStarting);
                    }

                    //$openId = $cacheList['openid'] ?? '';
                    //Users::collectFormId($formId, $openId);
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
        $version = $params['version'] ?? '';
        $startUsers = $params['startUsers'] ?? [];
        //$formId = $params['formId'] ?? '';

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
                    $queryStartingScore = (new Users())->queryStartingScore($cacheList['id'], $version);
                    if ($queryStartingScore) {
                        $this->jsonResponse['xiaoji'] = $queryStartingScore['xiaoji'];
                        $this->jsonResponse['total'] = $queryStartingScore['total'];
                        $this->jsonResponse['userCount'] = count($queryStarting);
                    }

                    //$openId = $cacheList['openid'] ?? '';
                    //Users::collectFormId($formId, $openId);
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
                $this->jsonResponse['data']['playerName'] = Users::$playerName;
                if ($isSave) {
                    $this->jsonResponse['code'] = 0;
                    $this->jsonResponse['msg'] = '添加玩家成功';
                } else {
                    if ($this->jsonResponse['data']['isFull'] == 1) {
                        $this->jsonResponse['msg'] = '最多支持4人';
                    }

                    //$this->jsonResponse['msg'] = Users::$error_msg;
                }


            }
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }


    public function actionCollect() {
        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        $formId = $params['formId'] ?? '';

        $this->jsonResponse['msg'] = '采集失败！';
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);
                $openId = $cacheList['openid'] ?? '';
                Users::collectFormId($formId, $openId);
                $this->jsonResponse['code'] = 0;
                $this->jsonResponse['msg'] = '采集成功！';
                $this->jsonResponse['data'] = ['formId'=>$formId];
            }
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }


}
