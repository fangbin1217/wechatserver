<?php

namespace app\models;
use Yii;

class Users  extends \yii\db\ActiveRecord
{

    static $error_msg = '';

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

    static public function bindedRoom($cur_user_id, $binded_user_id = 0, $nickname = '') {
        if (!$binded_user_id) {
            Users::$error_msg = '被绑定用户ID不存在';
            return false;
        }

        $userInfo = Users::getUserInfo($binded_user_id);
        if (!$userInfo) {
            Users::$error_msg = '被绑定用户数据不存在';
            return false;
        }

        //判断当前用户是否处于已准备或进行中
        $myAll = RoomUsers::find()->where(['user_id'=>$cur_user_id, 'is_del'=>0])->asArray()->all();
        if ($myAll) {
            $myIds = array_column($myAll, 'room_id');
            if ($myIds) {
                $all_rooms = Rooms::find()->where(['is_del' => 0])->andWhere(['in', 'id', $myIds])->andWhere(['in', 'status', [0, 1]])->asArray()->all();
                if ($all_rooms) {
                    Users::$error_msg = '当前用户已绑定';
                    return false;
                }
            }
        }
        //查找最新房间数据
        $room = Rooms::find()->where(['user_id'=>$binded_user_id, 'is_del'=>0])->andWhere(['in', 'status', [0,1]])->asArray()->one();
        $date = date('Y-m-d H:i:s');
        $time = time() + 86400;
        if (!$room) {
            try {
                $trans = Yii::$app->getDb()->beginTransaction();
                $Rooms = new Rooms();
                $Rooms->user_id = $binded_user_id;
                $Rooms->name = $userInfo['nickname'] . '的房间';
                $Rooms->create_time = $date;
                $Rooms->update_time = $date;
                $Rooms->expire_time = $time;
                if (!$Rooms->save()) {
                    Users::$error_msg = '保存房间数据失败';
                    return false;
                }
                $room_id = $Rooms->id;

                $values = [
                    [$userInfo['id'], $room_id, $userInfo['nickname'], $date, $date, 1],
                    [$cur_user_id, $room_id, $nickname, $date, $date, 2],
                    [0, $room_id, '台', $date, $date, 5],
                ];

                $aa = Yii::$app->db->createCommand()
                    ->batchInsert(RoomUsers::tableName(), ['user_id', 'room_id', 'nickname', 'create_time', 'update_time', 'sorts'],
                        $values)
                    ->execute();

                $trans->commit();
                return true;
            } catch (Exception $E) {
                $trans->rollBack();
                Users::$error_msg = '批量保存绑定失败';
                return false;
            }
            //添加 owner  self bai
        } else {
            if ($room['status'] == 0) {  //已准备
                try {
                    $trans = Yii::$app->getDb()->beginTransaction();
                    $room_id = $room['id'];

                    $myCount = RoomUsers::find()->where(['room_id'=> $room_id, 'is_del'=>0, 'user_id'=>$cur_user_id])->count();
                    if ($myCount) {
                        Users::$error_msg = '当前用户已绑定';
                        return false;
                    }

                    $RoomUsersCount = RoomUsers::find()->where(['room_id'=> $room_id, 'is_del'=>0])->count();
                    if ($RoomUsersCount >= 5) {
                        Users::$error_msg = '限定人数上限4人';
                        return false;
                    }
                    $mySort = $RoomUsersCount;
                    $RoomUsers = new RoomUsers();
                    $RoomUsers->room_id = $room_id;
                    $RoomUsers->user_id = $cur_user_id;
                    $RoomUsers->create_time = $date;
                    $RoomUsers->update_time = $date;
                    $RoomUsers->nickname = $nickname;
                    $RoomUsers->sorts = $mySort;
                    if (!$RoomUsers->save()) {
                        Users::$error_msg = '绑定失败';
                        return false;
                    }

                    //自动延长时间
                    $Rooms = Rooms::find()->where(['id' => $room_id, 'is_del' => 0])->one();
                    $Rooms->update_time = $date;
                    $Rooms->expire_time = $time;
                    if ($mySort == 4) {
                        //更改状态
                        $Rooms->status = 1;
                    }
                    if (!$Rooms->save()) {
                        Users::$error_msg = '更改房间状态失败';
                        return false;
                    }


                    $trans->commit();
                    return true;
                } catch (Exception $E) {
                    $trans->rollBack();
                    Users::$error_msg = '绑定失败';
                    return false;
                }



            } elseif ($room['status'] == 1) {
                Users::$error_msg = '房间状态进行中，不可绑定';
                return false;
            }
        }
        Users::$error_msg = '绑定失败';
        return false;

    }

    static public function saveScore($params) {
        //, Yii::$app->params['serverHost'].'images/fa.png'
        if (is_array($params)) {

            $saveList = [];
            $i = 0;
            $room_id = 0;
            $date = date('Y-m-d H:i:s');

            $times = 1;

            $totalScore = 0;
            foreach ($params as $val) {

                if(!isset($val['user_id']) || !isset($val['score'])) {
                    Users::$error_msg = 'user_id 或score缺失';
                    return false;
                }

                $val['user_id'] = (int) $val['user_id'];
                $totalScore += $val['score'];
                if ($i == 0) {
                    $my = RoomUsers::find()->where(['user_id'=>$val['user_id'], 'is_del'=>0])->orderBy('id', 'DESC')->asArray()->one();
                    if ($my) {
                        $room_id = $my['room_id'];

                        $Rooms = Rooms::find()->where(['id'=>$room_id, 'is_del'=>0])->andWhere(['in', 'status', [0,1]])->asArray()->one();
                        if (!$Rooms) {
                            Users::$error_msg = '该用户所在房间不是准备中或进行中';
                            return false;
                        }
                        $times += Scores::find()->where(['room_id'=>$room_id, 'user_id'=>$val['user_id'], 'is_del'=>0])->count();
                    }
                }

                if (!$room_id) {
                    Users::$error_msg = '房间不存在';
                    return false;
                }

                $my2 = RoomUsers::find()->where(['user_id'=>$val['user_id'], 'is_del'=>0, 'room_id'=>$room_id])->asArray()->one();
                if (!$my2) {
                    Users::$error_msg = '该用户ID不在该房间';
                    return false;
                }

                $saveList[] = [
                    'user_id' => $val['user_id'], 'score' => $val['score'],
                    'room_id' => $room_id, 'create_time' => $date, 'update_time'=>$date,
                    'times' => $times
                ];
                $i++;
            }

            if ($totalScore) {
                Users::$error_msg = '小计总分不为0';
                return false;
            }

            try {
                $trans = Yii::$app->getDb()->beginTransaction();


                if ($times == 1) {
                    $Rooms2 = Rooms::find()->where(['id'=>$room_id, 'is_del'=>0])->one();
                    $Rooms2->status = 1;
                    $Rooms2->update_time = $date;
                    if ($Rooms2->save()) {
                        Users::$error_msg = '更改房间状态失败';
                        return false;
                    }
                }
                if ($saveList) {
                    $aa = Yii::$app->db->createCommand()
                        ->batchInsert(Scores::tableName(), ['user_id', 'score', 'room_id', 'create_time', 'update_time', 'times'],
                            $saveList)
                        ->execute();
                }
                $trans->commit();
                if ($aa) {
                    return true;
                }
            } catch (Exception $E) {
                $trans->rollBack();
                Users::$error_msg = '批量保存小计失败';
                return false;
            }
            return false;
        }
    }

    static public function saveTotalScore($user_id) {
        $my = RoomUsers::find()->where(['user_id'=>$user_id, 'is_del'=>0])->orderBy('id', 'DESC')->asArray()->one();
        $room_id = 0;

        if (!$my) {
            Users::$error_msg = '该用户ID不存在';
            return false;
        }

        $room_id = $my['room_id'];
        $Rooms = Rooms::find()->where(['id'=>$room_id, 'is_del'=>0])->andWhere(['in', 'status', [0,1]])->asArray()->one();
        if (!$Rooms) {
            Users::$error_msg = '该用户所在房间不是准备中或进行中';
            return false;
        }
        $date = date('Y-m-d H:i:s');
        $time = time();
        try {
            $trans = Yii::$app->getDb()->beginTransaction();
            $Rooms = Rooms::find()->where(['id' => $room_id, 'is_del' => 0])->one();
            $Rooms->update_time = $date;
            $Rooms->status = 2;
            if (!$Rooms->save()) {
                Users::$error_msg = '更改房间状态失败';
                return false;
            }

            $RoomUsers = RoomUsers::find()->where(['is_del'=>0, 'room_id'=>$room_id])->asArray()->all();
            if ($RoomUsers) {
                foreach ($RoomUsers as $val) {
                    $total = 0;
                    $Scores = Scores::find()->where(['user_id'=>$val['user_id'], 'room_id'=>$room_id, 'is_del'=>0])->asArray()->all();
                    if ($Scores) {
                        foreach ($Scores as $vv) {
                            $total += $vv['score'];
                        }
                    }

                    $my2 = RoomUsers::find()->where(['user_id' => $val['user_id'], 'is_del' => 0, 'room_id' => $room_id])->one();
                    $my2->score = $total;
                    $my2->update_time = $date;
                    if (!$my2->save()) {
                        Users::$error_msg = '统计总计保存失败';
                        return false;
                    }

                }
            }



            $trans->commit();
            return true;
        } catch (Exception $E) {
            $trans->rollBack();
            return false;
        }

        return false;
    }

    //查询进行中的数据
    public function queryStarting($user_id) {
        $my = RoomUsers::find()->where(['user_id'=>$user_id, 'is_del'=>0])->orderBy('id', 'DESC')->asArray()->one();
        $room_id = 0;
        if (!$my) {
            Users::$error_msg = '该用户ID不存在';
            return false;
        }

        $room_id = $my['room_id'];
        $Rooms = Rooms::find()->where(['id'=>$room_id, 'is_del'=>0])->andWhere(['in', 'status', [0,1]])->asArray()->one();
        if (!$Rooms) {
            Users::$error_msg = '该用户所在房间不是准备中或进行中';
            return false;
        }
        $our = RoomUsers::find()->select(
            [RoomUsers::tableName(). '.user_id', Users::tableName().'.nickname', Users::tableName().'.avatar'])
            ->joinWith('user')
            ->where([RoomUsers::tableName(). '.room_id'=>$room_id, RoomUsers::tableName().'.is_del'=>0])
            ->orderBy(RoomUsers::tableName().'.sorts', 'ASC')
            ->asArray()->all();
        if (!$our) {
            Users::$error_msg = '暂无数据';
            return false;
        }

        $res = [];
        if ($our) {
            foreach ($our as $val) {
                if (!$val['user_id']) {
                    $val['nickname'] = '發';
                    $val['avatar'] = Yii::$app->params['serverHost'].'images/fa.png';
                }

                if (mb_strlen($val['nickname'], 'utf-8') > 8) {
                    $val['nickname'] = mb_substr($val['nickname'], 0, 8);
                }
                $res[] = [
                    'user_id' => $val['user_id'],'nickname' => $val['nickname'],'avatar' => $val['avatar'],
                    'zf_index'=>0, 'color'=> 'red', 'cur_score'
                ];
            }
        }

        return $res;


    }

    //查询已完成最近一个
    static public function queryLatelyDetail($user_id) {

    }

    //查询已完成具体一个
    static public function queryHistoryDetail($user_id) {

    }

    //查询所有历史数据总计
    static public function queryHistoryAll($user_id) {

    }





}
