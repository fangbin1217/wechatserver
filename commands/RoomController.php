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
                    if ($val['avatar'] !== Yii::$app->params['image_default']) {
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
