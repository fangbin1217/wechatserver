<?php

namespace app\controllers;

use app\models\Scores;
use app\models\Users;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\Rooms;
class LoginController extends Controller
{

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionOpenid()
    {

        $this->jsonResponse['msg'] = 'get openid error';
        $appid = Yii::$app->params['appid'];
        $appsercet = Yii::$app->params['appsercet'];

        $params = json_decode(file_get_contents('php://input'),true);
        $CODE = $params['CODE'] ?? '';
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
            $this->jsonResponse['code'] = 0;
            $this->jsonResponse['msg'] = 'get openid success';
            $this->jsonResponse['data'] = [
                'openid' => $openid,
                'session_key' => $session_key
            ];
        }

        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    public function actionUpduserinfo()
    {
        $params = json_decode(file_get_contents('php://input'),true);
        $openid = $params['openid'] ?? '';
        $session_key = $params['session_key'] ?? '';
        $nickname = $params['nickname'] ?? '';
        $avatar = $params['avatar'] ?? '';
        $bind_uid = $params['bind_uid'] ?? '';
        $this->jsonResponse['msg'] = 'do login error';

        if (!$openid) {
            $this->jsonResponse['msg'] = 'openid empty';
        }

        if (!$nickname || !$avatar) {
            $this->jsonResponse['msg'] = 'nickname or avatar empty';
        }

        $usersInfo = Users::getUserByOpenId($openid);
        $time = time();
        $date = date('Y-m-d H:i:s');
        $expire_time = $time + Yii::$app->params['loginCacheTime'];
        $username = uniqid();
        $access_token = strtoupper(md5($openid.$session_key.rand(1,1000)));
        if (!$usersInfo) {
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
                ];

                $cacheList = Users::getUserInfo($users->id);
                Yii::$app->redis->set('T#'.$access_token, json_encode($cacheList, JSON_UNESCAPED_UNICODE));
                Yii::$app->redis->expire('T#'.$access_token, Yii::$app->params['loginCacheTime']);

                /*
                $saveQrcode = Users::saveQrcode($users->id);
                if ($saveQrcode) {
                    $users2 = Users::find()->where(['id'=>$users->id])->one();
                    if ($users2) {
                        $users2->qrcode = $saveQrcode;
                        $users2->save();
                    }
                }
                */

                //如果是扫码进来 就绑定用户及房间
                if ($bind_uid) {
                    $isSave = Users::bindedRoom($users->id, $bind_uid, $nickname);
                }

                //生成本地图片放入队列
                Yii::$app->redis->lpush('Q#AVATAR', $users->id);
            }

        } else {
            $users = Users::find()->where(['id'=>$usersInfo['id']])->one();
            $users->nickname = $nickname;
            $users->avatar = $avatar;
            $users->session_key = $session_key;
            $users->access_token = $access_token;
            $users->expire_time = $expire_time;
            $users->update_time = $date;
            $users->login_ip = Yii::$app->request->getUserIP();
            $users->login_time = $date;

            /*
            if (!$usersInfo['qrcode']) {
                $saveQrcode = Users::saveQrcode($usersInfo['id']);
                if ($saveQrcode) {
                    $users->qrcode = $saveQrcode;
                }
            }
            */

            if ($users->save()) {
                $this->jsonResponse['code'] = 0;
                $this->jsonResponse['msg'] = 'login upd success';
                $this->jsonResponse['data'] = [
                    'access_token' => $access_token,
                ];

                $cacheList = Users::getUserInfo($usersInfo['id']);
                Yii::$app->redis->set('T#'.$access_token, json_encode($cacheList, JSON_UNESCAPED_UNICODE));
                Yii::$app->redis->expire('T#'.$access_token, Yii::$app->params['loginCacheTime']);

                //如果是扫码进来 就绑定用户及房间
                if ($bind_uid) {
                    $isSave = Users::bindedRoom($usersInfo['id'], $bind_uid, $nickname);
                }
            }
        }

        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    public function actionTest() {
        $len = Yii::$app->redis->llen('Q#AVATAR');

        //echo Users::getMyQrcode(2);exit;
        echo $len;
    }


}
