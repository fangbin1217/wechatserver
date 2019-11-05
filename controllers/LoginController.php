<?php

namespace app\controllers;

use app\models\Users;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;


class LoginController extends Controller
{

    public function actionIndex()
    {
        $this->jsonResponse['msg'] = 'login error';
        $appid = Yii::$app->params['appid'];
        $appsercet = Yii::$app->params['appsercet'];

        $params = json_decode(file_get_contents('php://input'),true);
        $CODE = $params['CODE'] ?? '';
        $bind_uid = $params['bind_uid'] ?? '';
        if (!$CODE) {
            $this->jsonResponse['msg'] = 'CODE empty';
            return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
        }
        $URL2 = "https://api.weixin.qq.com/sns/jscode2session?appid=$appid&secret=$appsercet&js_code=$CODE&grant_type=authorization_code";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $URL2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 13);
        $output = curl_exec($ch);
        $codes = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($codes == 200) {
            $output = json_decode($output, true);
            $session_key = $output['session_key'] ?? '';
            $openid = $output['openid'] ?? '';
            if (!$openid) {
                $this->jsonResponse['msg'] = 'get openid empty';
                return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
            }


            $usersInfo = Users::getUserByOpenId($openid);
            $time = time();
            $date = date('Y-m-d H:i:s');
            $expire_time = $time + Yii::$app->params['loginCacheTime'];
            $username = uniqid();
            $access_token = strtoupper(md5($openid.$session_key.rand(1,1000)));
            if (!$usersInfo) {
                $nickname = '未设置';
                $avatar = Yii::$app->params['image_default'];

                $users = new Users();
                $users->nickname = $nickname;
                $users->avatar = $avatar;
                $users->openid = $openid;
                $users->session_key = $session_key;
                $users->access_token = $access_token;
                $users->username = $username;
                $users->expire_time = $expire_time;
                $users->create_time = $date;
                $users->update_time = $date;
                $users->login_ip = Yii::$app->request->getUserIP();
                $users->login_time = $date;

                if ($users->save()) {
                    $result['code'] = 0;
                    $this->jsonResponse['code'] = 0;
                    $this->jsonResponse['msg'] = 'login save success';
                    $this->jsonResponse['data'] = [
                        'access_token' => $access_token,
                        'openid' => $openid,
                        'uid' => $users->id,
                        'vip' => false,
                        'colorClass' => '',
                        'nickName' => $nickname,
                        'avatarUrl' => $avatar,
                        'localAvatar' => $avatar,
                        'isLogin' => false,
                        'box' => getRandData(true),
                        'isChecked' => Yii::$app->params['isChecked'],
                        'notice' => Yii::$app->params['notice'],
                        'shareImage' => Yii::$app->params['shareImage']
                    ];

                    $cacheList = Users::getUserInfo($users->id);
                    Yii::$app->redis->set('T#'.$access_token, json_encode($cacheList, JSON_UNESCAPED_UNICODE));
                    Yii::$app->redis->expire('T#'.$access_token, Yii::$app->params['loginCacheTime']);


                    //如果是扫码进来 就绑定用户及房间
                    if ($bind_uid) {
                        $isSave = Users::bindedRoom($users->id, $bind_uid, $nickname);
                    }
                }

            } else {
                $users = Users::find()->where(['id'=>$usersInfo['id']])->one();
                $users->session_key = $session_key;
                $users->access_token = $access_token;
                $users->expire_time = $expire_time;
                $users->update_time = $date;
                $users->login_ip = Yii::$app->request->getUserIP();
                $users->login_time = $date;


                if ($users->save()) {
                    $this->jsonResponse['code'] = 0;
                    $this->jsonResponse['msg'] = 'login upd success';


                    $local_avatar = Yii::$app->params['image_default'];
                    if ($users->local_avatar) {
                        $local_avatar = $users->local_avatar;
                    }
                    $this->jsonResponse['data'] = [
                        'access_token' => $access_token,
                        'openid' => $openid,
                        'uid' => $users->id,
                        'vip' => false,
                        'colorClass' => '',
                        'nickName' => $users->nickname,
                        'avatarUrl' => $users->avatar,
                        'localAvatar' => $local_avatar,
                        'isLogin' => false,
                        'box' => getRandData(true),
                        'isChecked' => Yii::$app->params['isChecked'],
                        'notice' => Yii::$app->params['notice'],
                        'shareImage' => Yii::$app->params['shareImage']
                    ];



                    if ($users->avatar !== Yii::$app->params['image_default']) {

                        $avatarUpdTime = $users->avatar_updtime;
                        $avatarUpdTime = (int) $avatarUpdTime;
                        if ($avatarUpdTime) {
                            $be = time() - $avatarUpdTime;
                            if ($be < Yii::$app->params['loginCacheTime']) {
                                $this->jsonResponse['data']['isLogin'] = true;
                            }

                        }
                    }

                    if ($this->jsonResponse['data']['isLogin']) {
                        if ($users->vip) {
                            $this->jsonResponse['data']['vip'] = true;
                        }
                    }

                    if (!$this->jsonResponse['data']['isLogin']) {
                        $this->jsonResponse['data']['avatarUrl'] = Yii::$app->params['image_default'];
                        $this->jsonResponse['data']['nickName'] = '未设置';
                    }



                    $getColorClass = Users::getColorClass($users->id, $users->vip);
                    $this->jsonResponse['data']['colorClass'] = $getColorClass;
                    $cacheList = Users::getUserInfo($usersInfo['id']);
                    Yii::$app->redis->set('T#'.$access_token, json_encode($cacheList, JSON_UNESCAPED_UNICODE));
                    Yii::$app->redis->expire('T#'.$access_token, Yii::$app->params['loginCacheTime']);

                    //如果是扫码进来 就绑定用户及房间
                    if ($bind_uid) {
                        $isSave = Users::bindedRoom($usersInfo['id'], $bind_uid, $usersInfo['nickname']);
                    }
                }
            }


        }

        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }


    public function actionError()
    {
        return '404';
        exit;
    }

    public function actionWxcharts()
    {
        $params = json_decode(file_get_contents('php://input'),true);
        $city_id = $params['city_id'] ?? 5;
        $getCityData = Users::getCityData($city_id);
        if ($getCityData) {
            $this->jsonResponse['code'] = 0;
            $this->jsonResponse['categories'] = $getCityData['categories'];
            $this->jsonResponse['series'] = $getCityData['series'];
            $this->jsonResponse['max'] = $getCityData['max'];
            $this->jsonResponse['min'] = $getCityData['min'];
            $this->jsonResponse['year'] = $getCityData['maxYear'];
            $this->jsonResponse['citys'] = $getCityData['citys'];
            $this->jsonResponse['city_id'] = $getCityData['city_id'];
            $this->jsonResponse['maxYear'] = Users::getLastYearZlPhb($getCityData['maxYear']);
            $this->jsonResponse['maxCity'] = Users::getLastYearMaxCity($getCityData['maxYear']);
            $this->jsonResponse['isTg'] = true;

            $this->jsonResponse['tg'] = [
                ['appId' => 'wx547bae972d6fecf9', 'path'=>'pages/index/index?from=1', 'img'=>Yii::$app->params['serverHost'].'images/tg1.png'],
            ];
        }
        $this->jsonResponse['msg'] = 'no record';
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }
}
