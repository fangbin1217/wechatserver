<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\Rooms;
use app\models\Users;

/**
 * This command echoes the first argument that you have entered.
 *
 * This command is provided as an example for you to learn how to create console commands.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class RoomController extends Controller
{
    /**
     *
     * 将过期房间设置已结束
     * This command echoes what you have entered as the message.
     * @param string $message the message to be echoed.
     * @return int Exit code
     */
    public function actionIndex($message = 'hello world')
    {

        $Rooms = new Rooms();
        $run = $Rooms->setExpireRoom();
        if ($run) {
            echo "success\n";
        } else {
            echo "fail\n";
        }
        return ExitCode::OK;
    }

    public function actionPushmsg($message = 'test') {
        set_time_limit(0);
        $templateId = 'ud0Ws6ss1ZMXTGymwizcqwDswWYQ9wUg11LR0B11TH8';
        $page = 'pages/index/index';
        $len = (int) Yii::$app->redis->llen('FORM_ID');
        $success = 0;
        if ($len) {

            $time = time();
            $res = [];
            for ($i=0;$i<$len;$i++) {
                $json = Yii::$app->redis->rpop('FORM_ID');
                $tmp = json_decode($json, true);
                if ($time < $tmp['expireTime']) {
                    $res[$tmp['openId']] = $tmp['formId'];
                }
            }

            $date = date('Y年m月d日');
            if ($res) {
                $accessToken = Users::createXCX();
                $url = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token='.$accessToken;


                $randList = [
                    '有人超越您的最佳记录，快来看看吧',
                    '客官很久没来光顾了，快来看看吧',
                    'VIP限时免费领，看来看看吧'
                ];

                $maxRand = count($randList) - 1;
                $randKey = rand(0, $maxRand);
                $randVal = $randList[$randKey];

                foreach ($res as $key=>$val) {

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 13);
                    //设置post方式提交
                    curl_setopt($ch, CURLOPT_POST, 1);
                    //设置post数据
                    $post_data = array(
                        "access_token" => $accessToken,
                        "touser" => $key,
                        "template_id" => $templateId,
                        "page" => $page,
                        "form_id" => $val,
                        "data" => ["keyword1"=>["value"=>$randVal], "keyword2"=>["value"=>$date]],
                        //"emphasis_keyword" => "keyword1.DATA"
                    );


                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data, JSON_UNESCAPED_UNICODE));
                    $output = curl_exec($ch);
                    $codes = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($codes == 200) {
                        echo " $output \n";
                        $success++;
                    }
                    sleep(1);

                }
            }
        }

        echo " total:$len success:$success \n";
        return ExitCode::OK;

    }

    public function actionImage($message = 'test') {
        $len = Yii::$app->redis->llen('Q#AVATAR');
        $max = 20;
        if ($len < $max) {
            $max = $len;
        }

        if ($len == 0) {
            $datas = Users::find()->where(['is_del'=>0, 'local_avatar'=>''])->asArray()->all();
            $aaa = false;
            if ($datas) {
                foreach ($datas as $val) {
                    if ($val['avatar'] && ($val['avatar'] !== Yii::$app->params['image_default'])) {
                        Yii::$app->redis->lpush('Q#AVATAR', $val['id']);
                        $aaa = true;
                    }
                }
            }

            if (!$aaa) {
                echo " no data \n";
                return ExitCode::OK;
            }
        }

        $success = 0;
        for ($i=0;$i<$max;$i++) {
            $uid = Yii::$app->redis->rpop('Q#AVATAR');

            $avatar = Users::getAvatar($uid);
            if ($avatar) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $avatar);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 13);
                $output = curl_exec($ch);
                $codes = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($codes == 200) {
                    $saveImage = Users::saveImage($output, Yii::$app->params['imageFirstPath']);
                    if ($saveImage) {
                        $Users = Users::find()->where(['id'=>$uid])->one();
                        $Users->local_avatar = $saveImage;
                        $Users->update_time = date('Y-m-d H:i:s');
                        if ($Users->save()) {
                            $success++;
                        }
                    }
                }
            }
            sleep(2);
        }
        echo " total:$max success:$max \n";
        return ExitCode::OK;
    }
}
