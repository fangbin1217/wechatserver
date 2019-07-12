<?php

namespace app\models;
use Yii;

class Users  extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'users';
    }

    static public function getUserByAccessToken($access_token) {
        return self::find()->where(['access_token'=>$access_token, 'is_del'=> 0])->asArray()->one();
    }

    static public function getUserByOpenId($openid) {
        return self::find()->where(['openid'=>$openid, 'is_del'=> 0])->asArray()->one();
    }

    static public function isLogin($access_token = '') {
        if (!$access_token) {
            return false;
        }
        $cache = Yii::$app->redis->get('T#'.$access_token);
        if ($cache) {
            return true;
        }
        $userInfo = Users::getUserByAccessToken($access_token);
        if ($userInfo) {
            if ($userInfo['expire_time'] >= time()) {
                $expire_time = $userInfo['expire_time'] - time();
                Yii::$app->redis->set('T#'.$access_token, json_encode($userInfo, JSON_UNESCAPED_UNICODE));
                Yii::$app->redis->expire('T#'.$access_token, $expire_time);
                return true;
            }
        }
        return false;
    }

    static public function getUserInfo($uid) {
        return  self::find()->where(['id'=>$uid, 'is_del'=> 0])->asArray()->one();
    }


    static public function createXCX() {
        $cache = Yii::$app->redis->get('XCX');
        if ($cache) {
            return $cache;
        }
        $appid = Yii::$app->params['appid'];
        $appsercet = Yii::$app->params['appsercet'];
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appid&secret=$appsercet";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 13);
        $output = curl_exec($ch);
        $codes = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($codes == 200) {
            $output = json_decode($output, true);
            $xcx = $output['access_token'] ?? '';
            if ($xcx) {
                Yii::$app->redis->set('XCX', $xcx);
                Yii::$app->redis->expire('XCX', 7200);
            }
            return $xcx;
        }
        return '';
    }

    static public function saveQrcode($scene = 2) {
        $xcx = Users::createXCX();
        if (!$xcx) {
            return '';
        }
        $data_list = ['scene'=>$scene, 'page'=>'pages/index/index', 'width'=> '280'];
        $data_string = json_encode($data_list);
        $url = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=$xcx";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 13);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                //'Content-Length: ' . strlen($data_string)
            )
        );

        $output = curl_exec($ch);
        $codes = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($output) {
            if (strpos($output, 'errcode') === false) {
                return Users::saveImage($output);
            }
        }
        return '';
    }

    static public function saveImage($buffer) {
        //生成图片
        $imgDir = $_SERVER['DOCUMENT_ROOT'];
        $imgDir2 = $imgDir.'/images/'.date('Ym');
        if(!is_dir($imgDir2)) {
            mkdir($imgDir2, 0777);
            chmod($imgDir2, 0777);
        }
        $name = date('dHis').'-'.rand(1000,9999).'.png';
        $filename = $imgDir2.'/'.$name;///要生成的图片名字

        $file = fopen($filename,"w");//打开文件准备写入
        fwrite($file,$buffer);//写入
        fclose($file);//关闭

        if (file_exists($filename)) {
            return 'images/'.date('Ym').'/'.$name;
        }
        return '';

    }
}
