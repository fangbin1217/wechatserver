<?php

namespace app\models;
use Yii;

class Users  extends \yii\db\ActiveRecord
{

    static public $error_msg = '';

    static public $isFull = 0;

    static public $playerName = '';

    static public $playerAvatar = '';

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

    static public function getUserInfo2($uid) {
        return  self::find()->where(['id'=>$uid, 'is_del'=> 0])->one();
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
                Yii::$app->redis->expire('XCX', Yii::$app->params['XCX_ACCESS_TOKEN']);
            }
            return $xcx;
        }
        return '';
    }

    static public function getMyQrcode($uid = 0) {
        if (!$uid) {
            return '';
        }
        $cache = Yii::$app->redis->get('QR#'.$uid);
        if ($cache) {
            return $cache;
        }

        $users = Users::find()->where(['id'=>$uid])->asArray()->one();
        if ($users && $users['qrcode']) {
            Yii::$app->redis->set('QR#'.$uid, $users['qrcode']);
            Yii::$app->redis->expire('QR#'.$uid, Yii::$app->params['qrcodeImageTime']);
            return $users['qrcode'];
        }


        $saveQrcode = Users::saveQrcode($uid);
        if ($saveQrcode) {
            Yii::$app->redis->set('QR#'.$uid, $saveQrcode);
            Yii::$app->redis->expire('QR#'.$uid, Yii::$app->params['qrcodeImageTime']);

            $users = Users::find()->where(['id'=>$uid])->one();
            if ($users) {
                $users->qrcode = $saveQrcode;
                $users->update_time = date('Y-m-d H:i:s');
                $users->save();
            }
            return $saveQrcode;
        }
        return '';
    }

    static public function saveQrcode($uid = 0) {
        $xcx = Users::createXCX();
        if (!$xcx) {
            return '';
        }
        $time = time();
        $data_list = ['scene'=> $time.'-'.$uid, 'page'=>'pages/index/index', 'width'=> '280'];
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

    static public function saveImage($buffer, $firstPath = '') {
        //生成图片
        if (!$firstPath) {
            $imgDir = $_SERVER['DOCUMENT_ROOT'];
        } else {
            $imgDir = $firstPath;
        }
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


    static public function bindedRoom($cur_user_id, $binded_user_id = 0, $nickname = '', $isSd = false) {
        if ($isSd) {
            //查找最新房间数据
            $roomSd = Rooms::find()->where(['user_id'=>$binded_user_id, 'is_del'=>0])->andWhere(['in', 'status', [Rooms::STATUS_IS_READY, Rooms::STATUS_BEGINING]])->asArray()->one();
            if (!$roomSd) {
                $cur_user_id = 12;
            } else {
                $roomSdCount = RoomUsers::find()->where(['room_id'=>$roomSd['id'], 'is_del'=>0])->count();
                if ($roomSdCount == 3) {
                    $cur_user_id = 13;
                } elseif ($roomSdCount == 4) {
                    $cur_user_id = 14;
                    Users::$isFull = 1;
                } else {
                    if ($roomSdCount == 5) {
                        Users::$error_msg = '人数已满';
                        Users::$isFull = 1;
                    }
                    return false;
                }
            }

            if (!$nickname) {
                Users::$error_msg = '名称不能为空';
                return false;
            }

            //check is  or not ok
            $accessToken = Users::createXCX();

            $data_list = ['content'=> $nickname];
            $data_string = json_encode($data_list, JSON_UNESCAPED_UNICODE);
            $url = "https://api.weixin.qq.com/wxa/msg_sec_check?access_token=$accessToken";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                )
            );

            $output = curl_exec($ch);
            $codes = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($output) {
                $outputList = @json_decode($output, true);
                if (isset($outputList['errcode']) && $outputList['errcode'] == 87014) {
                    Users::$error_msg = '名称含违禁词';
                    Users::$isFull = 0;
                    return false;
                }
            }
        }

        Users::$playerName = $nickname;
        if (in_array($cur_user_id, [12,13,14])) {
            Users::$playerAvatar = Users::getAvatar($cur_user_id);
        }



        if (!$binded_user_id) {
            Users::$error_msg = '用户不存在';
            return false;
        }

        $userInfo = Users::getUserInfo($binded_user_id);
        if (!$userInfo) {
            Users::$error_msg = '用户不存在';
            return false;
        }

        if (!in_array($cur_user_id, [12,13,14])) {
            //判断当前用户是否处于已准备或进行中
            $myAll = RoomUsers::find()->where(['user_id' => $cur_user_id, 'is_del' => 0])->asArray()->all();
            if ($myAll) {
                $myIds = array_column($myAll, 'room_id');
                if ($myIds) {
                    $all_rooms = Rooms::find()->where(['is_del' => 0])->andWhere(['in', 'id', $myIds])->andWhere(['in', 'status', [Rooms::STATUS_IS_READY, Rooms::STATUS_BEGINING]])->asArray()->all();
                    if ($all_rooms) {
                        Users::$error_msg = '当前用户已绑定';
                        return false;
                    }
                }
            }
        }
        //查找最新房间数据
        $room = Rooms::find()->where(['user_id'=>$binded_user_id, 'is_del'=>0])->andWhere(['in', 'status', [Rooms::STATUS_IS_READY, Rooms::STATUS_BEGINING]])->asArray()->one();
        $date = date('Y-m-d H:i:s');
        $time = time() + Yii::$app->params['roomCacheTime'];
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
                    Users::$error_msg = '保存房间失败';
                    return false;
                }
                $room_id = $Rooms->id;

                $values = [
                    [$userInfo['id'], $room_id, $userInfo['nickname'], $date, $date, 1],
                    [$cur_user_id, $room_id, $nickname, $date, $date, 2],
                    [0, $room_id, Yii::$app->params['name_fa'], $date, $date, 5],
                ];

                $aa = Yii::$app->db->createCommand()
                    ->batchInsert(RoomUsers::tableName(), ['user_id', 'room_id', 'nickname', 'create_time', 'update_time', 'sorts'],
                        $values)
                    ->execute();

                $trans->commit();
                return true;
            } catch (Exception $E) {
                $trans->rollBack();
                Users::$error_msg = '批量保存失败';
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
                        Users::$error_msg = '用户已绑定';
                        return false;
                    }

                    $RoomUsersCount = RoomUsers::find()->where(['room_id'=> $room_id, 'is_del'=>0])->count();
                    if ($RoomUsersCount >= Yii::$app->params['MAX_PERSON_NUM']) {
                        Users::$error_msg = '人数已满！';
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
                        $Rooms->status = Rooms::STATUS_BEGINING;
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



            } elseif ($room['status'] == Rooms::STATUS_BEGINING) {
                Users::$error_msg = '房间开房中！';
                return false;
            }
        }
        Users::$error_msg = '绑定出错';
        return false;

    }

    //保存得分 默认是小计 参数二 true为总计
    static public function saveScore($params, $isTotalScore = false) {

        if (is_array($params)) {
            $saveList = [];
            $i = 0;
            $room_id = 0;
            $date = date('Y-m-d H:i:s');

            $times = 1;

            $totalScore = 0;

            $isEveryZero = 0;


            $onlyUser = 0;
            foreach ($params as $val) {
                if(!isset($val['user_id'])) {
                    Users::$error_msg = '入参格式不合法';
                    return false;
                }
                if (!in_array($val['user_id'], [0, 12, 13, 14])) {
                    $onlyUser = $val['user_id'];
                    break;
                }
            }

            if (!$onlyUser) {
                Users::$error_msg = '真实用户不存在';
                return false;
            }

            $room_id = 0;
            $my = RoomUsers::find()->where(['user_id'=>$onlyUser, 'is_del'=>0])->orderBy(['id'=>SORT_DESC])->asArray()->one();
            if ($my) {
                $room_id = $my['room_id'];
                $Rooms = Rooms::find()->where(['id'=>$room_id, 'is_del'=>0])->andWhere(['in', 'status', [Rooms::STATUS_IS_READY, Rooms::STATUS_BEGINING]])->asArray()->one();
                if (!$Rooms) {
                    Users::$error_msg = '房间状态已结束';
                    return false;
                }
            }

            if (!$room_id) {
                Users::$error_msg = '房间不存在';
                return false;
            }

            foreach ($params as $val) {
                $val['score'] = $val['score'] ?? 0;
                if(!isset($val['user_id']) || !isset($val['zf_index'])) {
                    Users::$error_msg = '入参格式不合法';
                    return false;
                }

                $val['user_id'] = (int) $val['user_id'];
                $val['score'] = (int) $val['score'];


                $lastCache = Yii::$app->redis->get('LAST#' . $val['user_id']);
                if ($lastCache) {
                    Yii::$app->redis->del('LAST#' . $val['user_id']);
                }

                $lastCache2 = Yii::$app->redis->get('LASTYEAR#' . $val['user_id']);
                if ($lastCache2) {
                    Yii::$app->redis->del('LASTYEAR#' . $val['user_id']);
                }

                $lastCache2 = Yii::$app->redis->get('LASTYEAR2#' . $val['user_id']);
                if ($lastCache2) {
                    Yii::$app->redis->del('LASTYEAR2#' . $val['user_id']);
                }

                $lastCache3 = Yii::$app->redis->get('RESULTS#' . $val['user_id']);
                if ($lastCache3) {
                    Yii::$app->redis->del('RESULTS#' . $val['user_id']);
                }


                if (!$val['score']) {
                    $isEveryZero += 1;
                }
                if ($val['zf_index'] == 1) {
                    $val['score'] = -$val['score'];
                }
                $totalScore += $val['score'];


                $maxScore = Scores::find()->where(['room_id'=>$room_id, 'is_del'=>0])->orderBy(['id'=>SORT_DESC])->asArray()->one();
                if ($maxScore) {
                    $times = $maxScore['times']+1;
                }
                if (!$room_id) {
                    Users::$error_msg = '您未在任何房间';
                    return false;
                }


                $my2 = RoomUsers::find()->where(['user_id'=>$val['user_id'], 'is_del'=>0, 'room_id'=>$room_id])->asArray()->one();
                if (!$my2) {
                    Users::$error_msg = '您未加入该房间';
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
                Users::$error_msg = '计分总和必须零';
                return false;
            }

            //小计需要验证不能都为0
            if (!$isTotalScore) {
                if ($isEveryZero == count($params)) {
                    Users::$error_msg = '计分不能都为零';
                    return false;
                }
            } else { //如果是总计
                //如果未提交数据
                if ($isEveryZero == count($params)) {
                    //查看历史数据
                    $mytmp = RoomUsers::find()->where(['is_del'=>0, 'room_id'=>$room_id])->asArray()->all();
                    $tmpscore = true;

                    if ($mytmp) {
                        foreach ($mytmp as $aa) {
                            if ($aa['score']) {
                                $tmpscore = false;
                            }
                        }
                    }
                    if ($tmpscore) {
                        Users::$error_msg = '未提交任何数据';
                        return false;
                    }

                }
            }


            if (!$saveList) {
                Users::$error_msg = '无提交保存数据';
                return false;
            }

            try {
                $trans = Yii::$app->getDb()->beginTransaction();

                if ($saveList) {
                    $Rooms2 = Rooms::find()->where(['id'=>$room_id, 'is_del'=>0])->one();
                    if ($isTotalScore) {  //如果是总计  改为已结束
                        $Rooms2->status = Rooms::STATUS_IS_END;
                    } else {
                        $Rooms2->status = Rooms::STATUS_BEGINING;
                    }
                    $Rooms2->update_time = $date;
                    $Rooms2->expire_time = time() + Yii::$app->params['roomCacheTime'];
                    if (!$Rooms2->save()) {
                        Users::$error_msg = '更新房间状态失败';
                        return false;
                    }

                    if (!($isEveryZero == count($params))) {
                        $aa = Yii::$app->db->createCommand()
                            ->batchInsert(Scores::tableName(), ['user_id', 'score', 'room_id', 'create_time', 'update_time', 'times'],
                                $saveList)
                            ->execute();
                        if (!$aa) {
                            Users::$error_msg = '保存得分数据失败';
                            return false;
                        }

                        foreach ($saveList as $vv) {
                            $tmp_score = 0;
                            $tmp2 = Scores::find()->where(['user_id'=>$vv['user_id'], 'room_id'=>$room_id, 'is_del'=>0])->asArray()->all();
                            if ($tmp2) {
                                foreach ($tmp2 as $v) {
                                    $tmp_score += $v['score'];
                                }
                                $RoomUsers = RoomUsers::find()->where(['user_id'=>$vv['user_id'], 'room_id'=>$room_id, 'is_del'=>0])->one();
                                $RoomUsers->score = $tmp_score;
                                $RoomUsers->update_time = $date;
                                if (!$RoomUsers->save()) {
                                    Users::$error_msg = '更新总分失败';
                                    return false;
                                }
                            }
                        }
                    }
                    $trans->commit();
                    return true;
                }

            } catch (Exception $E) {
                $trans->rollBack();
                Users::$error_msg = '保存小计失败';
                return false;
            }
            Users::$error_msg = '保存小计失败';
            return false;
        }

        Users::$error_msg = '请求参数非法';
        return false;
    }

    static public function saveTotalScore($user_id) {
        $my = RoomUsers::find()->where(['user_id'=>$user_id, 'is_del'=>0])->orderBy(['id'=>SORT_DESC])->asArray()->one();
        $room_id = 0;

        if (!$my) {
            Users::$error_msg = '未加入任何房间';
            return false;
        }

        $room_id = $my['room_id'];
        $Rooms = Rooms::find()->where(['id'=>$room_id, 'is_del'=>0])->andWhere(['in', 'status', [Rooms::STATUS_IS_READY, Rooms::STATUS_BEGINING]])->asArray()->one();
        if (!$Rooms) {
            Users::$error_msg = '房间状态已结束';
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
                Users::$error_msg = '更新房间状态失败';
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
                        Users::$error_msg = '保存总分失败';
                        return false;
                    }

                }
            }




            $trans->commit();
            return true;
        } catch (Exception $E) {
            $trans->rollBack();
            Users::$error_msg = '总计保存失败';
            return false;
        }
        Users::$error_msg = '总计保存失败';
        return false;
    }

    //查询进行中用户头像的数据
    public function queryStartingUsers($user_id) {

        $my = RoomUsers::find()->where(['user_id'=>$user_id, 'is_del'=>0])->orderBy(['id'=>SORT_DESC])->asArray()->one();
        $room_id = 0;
        if (!$my) {
            Users::$error_msg = '该用户ID不存在';
            return false;
        }

        $room_id = $my['room_id'];
        $Rooms = Rooms::find()->where(['id'=>$room_id, 'is_del'=>0])->andWhere(['in', 'status', [Rooms::STATUS_IS_READY, Rooms::STATUS_BEGINING]])->asArray()->one();
        if (!$Rooms) {
            Users::$error_msg = '该用户所在房间不是准备中或进行中';
            return false;
        }

        $our = RoomUsers::find()->select(
            [RoomUsers::tableName(). '.user_id', RoomUsers::tableName().'.nickname',  Users::tableName().'.avatar', Users::tableName().'.vip', Users::tableName().'.local_avatar'])
            ->joinWith('user')
            ->where([RoomUsers::tableName(). '.room_id'=>$room_id, RoomUsers::tableName().'.is_del'=>0])
            ->orderBy([RoomUsers::tableName().'.sorts'=>SORT_ASC])
            ->asArray()->all();
        if (!$our) {
            Users::$error_msg = '暂无数据';
            return false;
        }

        $res = [];
        if ($our) {
            foreach ($our as $val) {
                if (!$val['user_id']) {
                    $val['nickname'] = Yii::$app->params['name_fa'];
                    $val['avatar'] = Yii::$app->params['serverHost'].Yii::$app->params['image_fa'];
                    $val['vip'] = 0;
                }

                if (mb_strlen($val['nickname'], 'utf-8') > Yii::$app->params['MAX_NICKNAME']) {
                    $val['nickname'] = mb_substr($val['nickname'], 0, Yii::$app->params['MAX_NICKNAME']);
                }
                $res[] = [
                    'user_id' => $val['user_id'],'nickname' => $val['nickname'],'avatar' => $val['avatar'], 'vip'=> (int) $val['vip'], 'colorClass' => Users::getColorClass($val['user_id'], $val['vip']),
                    'zf_index'=>0, 'color'=> Yii::$app->params['red'], 'score'=>'', 'is_ready'=>0, 'is_last'=>0, 'jiajian_image'=> Yii::$app->params['image_jiajian'], 'local_avatar'=> Yii::$app->params['serverHost'].$val['local_avatar']
                ];
            }
        }

        return $res;


    }

    //查询正在比赛的得分
    public function queryStartingScore($user_id, $version = '') {
        $my = RoomUsers::find()->where(['user_id'=>$user_id, 'is_del'=>0])->orderBy(['id'=>SORT_DESC])->asArray()->one();
        $room_id = 0;
        if (!$my) {
            Users::$error_msg = '该用户ID不存在';
            return false;
        }

        $room_id = $my['room_id'];
        $Rooms = Rooms::find()->where(['id'=>$room_id, 'is_del'=>0])->andWhere(['in', 'status', [Rooms::STATUS_IS_READY, Rooms::STATUS_BEGINING]])->asArray()->one();
        if (!$Rooms) {
            Users::$error_msg = '该用户所在房间不是准备中或进行中';
            return false;
        }

        $tmp = [];
        $total = [];
        $our = RoomUsers::find()->where(['room_id'=>$room_id, 'is_del'=>0])->orderBy(['sorts'=>SORT_ASC])->asArray()->all();
        if ($our) {

            $sorts = [];
            foreach ($our as &$val) {
                $val['avatar'] = '';
                if ($val['user_id']) {
                    $tmp_user = Users::find()->where(['id' => $val['user_id']])->asArray()->one();
                    if ($tmp_user) {
                        $val['avatar'] = $tmp_user['avatar'];
                        $val['nickname'] = $tmp_user['nickname'];
                    }
                } else {
                    $val['avatar'] = Yii::$app->params['serverHost'].Yii::$app->params['image_fa'];
                    $val['nickname'] = Yii::$app->params['name_fa'];
                }
                $sorts[$val['user_id']] = ['user_id'=>$val['user_id'], 'nickname'=>$val['nickname'], 'avatar'=>$val['avatar'], 'sorts'=>$val['sorts']];
                $mycolor = '#E64340';
                if ($val['score'] < 0) {
                    $mycolor = '#09BB07';
                }
                $total[] = [
                    'user_id'=>$val['user_id'],'score'=>$val['score'], 'color'=>$mycolor
                ];
            }
            $scores = Scores::find()->where(['room_id'=>$room_id, 'is_del'=>0])->orderBy(['times'=>SORT_DESC])->asArray()->all();
            if ($scores) {


                foreach ($scores as $score) {

                    if (isset($sorts[$score['user_id']])) {
                        $tt = $sorts[$score['user_id']];
                        $tt['score'] = $score['score'];
                        $tt['times'] = $score['times'];
                        if($tt['score'] >= 0) {
                            $tt['color'] = '#E64340';
                        } else {
                            $tt['color'] = '#09BB07';
                        }
                        $tmp[$score['times']][] = $tt;
                    }
                }
            }
        }
        if ($tmp) {
            $tmp2 = [];
            foreach ($tmp as $key=>&$vv) {
                $vv = Users::arrSort($vv, 'sorts', 'asc');
                $tmp2[] = [
                    'times'=>$key,
                    'list' => $vv
                ];
            }

            /*
            if (vesionInt($version) >= 190) {
                return ['xiaoji'=>$tmp2, 'total'=>$total];
            } else {
                return ['xiaoji'=>$tmp, 'total'=>$total];
            }
            */
            return ['xiaoji'=>$tmp2, 'total'=>$total];

        }
        return false;

    }


    //二位数组排序
    static public function arrSort($array, $key, $order="desc"){
        $arr_nums=$arr=array();
        foreach($array as $k=>$v){
            $arr_nums[$k]=$v[$key];
        }
        $order = strtolower($order);
        if($order=='desc'){
            arsort($arr_nums);
        }else{
            asort($arr_nums);
        }
        foreach($arr_nums as $k=>$v){
            $arr[]=$array[$k];
        }
        return $arr;
    }

    static public function getAvatar($user_id) {
        $cache = Yii::$app->redis->get('AVATAR#'.$user_id);
        if ($cache) {
            return $cache;
        }
        $Users = Users::find()->select(['avatar'])->where(['id'=>$user_id])->asArray()->one();
        if ($Users) {
            Yii::$app->redis->set('AVATAR#'.$user_id, $Users['avatar']);
            Yii::$app->redis->expire('AVATAR#'.$user_id, Yii::$app->params['wechat_avatar']);
            return $Users['avatar'];
        }
        return '';
    }

    static public function getNickname($user_id) {
        $cache = Yii::$app->redis->get('NICKNAME#'.$user_id);
        if ($cache) {
            return $cache;
        }
        if (!$user_id) {
            return '';
        }
        $Users = Users::find()->select(['nickname'])->where(['id'=>$user_id])->asArray()->one();
        if ($Users) {
            Yii::$app->redis->set('NICKNAME#'.$user_id, $Users['nickname']);
            Yii::$app->redis->expire('NICKNAME#'.$user_id, Yii::$app->params['wechat_nickname']);
            return $Users['nickname'];
        }
        return '';
    }

    static public function getLocalAvatar($user_id) {
        $cache = Yii::$app->redis->get('LOCALAVATAR#'.$user_id);
        if ($cache) {
            return $cache;
        }
        $Users = Users::find()->select(['local_avatar'])->where(['id'=>$user_id])->asArray()->one();
        if ($Users && $Users['local_avatar']) {
            Yii::$app->redis->set('LOCALAVATAR#'.$user_id, $Users['local_avatar']);
            Yii::$app->redis->expire('LOCALAVATAR#'.$user_id, Yii::$app->params['history_avatar']);
            return $Users['local_avatar'];
        }
        return '';
    }

    static public function getUserClass($user_id) {
        $cache = Yii::$app->redis->get('USERCLASS#'.$user_id);
        if ($cache) {
            return $cache;
        }
    }


    static public function saveVip($cacheList, $access_token) {
        try {
            $trans = Yii::$app->getDb()->beginTransaction();
            $date = date('Y-m-d H:i:s');
            $users = Users::find()->where(['id'=>$cacheList['id']])->one();
            $users->vip = 1;
            $users->update_time = $date;
            if (!$users->save()) {
                return false;
            }

            $UserClass = new UserClass();
            $UserClass->uid = $cacheList['id'];
            $UserClass->color_class = 'sunshine';
            $UserClass->update_time = $date;
            if (!$UserClass->save()) {
                return false;
            }
            $trans->commit();

            $cacheList['vip'] = 1;
            if ($users->expire_time >= time()) {
                $expire_time = $users->expire_time - time();
                Yii::$app->redis->set('T#'.$access_token, json_encode($cacheList, JSON_UNESCAPED_UNICODE));
                Yii::$app->redis->expire('T#'.$access_token, $expire_time);

            }

            return true;
        } catch (Exception $E) {
            $trans->rollBack();
            Users::$error_msg = '保存失败';
            return false;
        }

        return false;

    }

    static public function updClass($uid, $color_class, $vip = 0) {

        $color_class = trim($color_class);
        if (!$color_class) {
            return false;
        }

        $UserClass = UserClass::find()->where(['uid'=>$uid])->one();
        if ($UserClass) {
            $UserClass->color_class = $color_class;
            $UserClass->update_time = date('Y-m-d H:i:s');
            if ($UserClass->save()) {
                Yii::$app->redis->set('AVATARCLASS#'.$uid, $color_class);
                return true;
            }
        } else {
            if ($vip) {
                $UserClass = new UserClass();
                $UserClass->uid = $uid;
                $UserClass->color_class = $color_class;
                $UserClass->update_time = date('Y-m-d H:i:s');
                if ($UserClass->save()) {
                    Yii::$app->redis->set('AVATARCLASS#'.$uid, $color_class);
                    return true;
                }
            }
        }
        return false;
    }

    static public function getColorClass($uid, $vip = 0) {
        if (!$uid) {
            return '';
        }
        $getColorclass = UserClass::getColorclass($uid);
        if (!$getColorclass) {
            if ($vip) {
                $getColorclass = 'sunshine';
            }
        }
        return $getColorclass;
    }

    static function getCityData($city_id) {

        $cache = Yii::$app->redis->get('CITY_DATA#'.$city_id);
        if ($cache) {
            return json_decode($cache, true);
        }

        $res = [];

        $citys = [];
        $allcitys = City::find()->asArray()->all();
        foreach ($allcitys as $key=>$val) {
            $allcitys[$key]['color'] = '#888888';
            if ($city_id == $val['id']) {
                $citys[] = $val;
                $allcitys[$key]['color'] = Yii::$app->params['green'];
            }
        }
        //$citys = City::find()->where(['in', 'id', [$city_id]])->asArray()->all();


        $title = [];
        $datas = [];

        $max = 0;
        $min = 0;
        $maxYear = '';
        if ($citys) {
            foreach ($citys as $val) {
                $CityData = CityData::find()->where(['city_id'=>$val['id']])->orderBy(['year'=>SORT_ASC])->asArray()->all();
                if ($CityData) {
                    if (!$title) {
                        foreach ($CityData as $v) {
                            $title[] = $v['year'];
                        }
                        $count = count($title);
                        $maxYear = $title[$count-1];
                    }
                    $tmp = [];
                    $tmp = [
                        'name' => $val['city_name'],
                        'data' => []
                    ];

                    $tmp2 = [];
                    foreach ($CityData as $v) {
                        $tmp2[$v['year']] = (float) $v['add_amount'];
                        $add_amount = (int) $v['add_amount'];
                        if ($add_amount > $max) {
                            $max = $add_amount;
                        }

                        if ($add_amount < $min) {
                            $min = $add_amount;
                        }

                    }

                    ksort($tmp2);
                    foreach ($tmp2 as $vv) {
                        $tmp['data'][] = $vv;
                    }
                    $datas[] = $tmp;
                }
            }
        }

        if ($title && $datas) {
            if ($max > 0) {
                $max = $max + 2;
            }
            if ($min < 0) {
                $min = $min - 2;
            }
            $res = ['categories' => $title, 'series' => $datas, 'max'=> $max, 'min'=>$min, 'maxYear'=>$maxYear, 'citys'=>$allcitys, 'city_id'=>$city_id];
        }
        Yii::$app->redis->set('CITY_DATA#'.$city_id, json_encode($res, JSON_UNESCAPED_UNICODE));
        Yii::$app->redis->expire('CITY_DATA#'.$city_id, 86400*10);
        return $res;
    }

    static function getLastYearZlPhb($maxYear) {

        $cache = Yii::$app->redis->get('CITY_YEAR#'.$maxYear);
        if ($cache) {
            return json_decode($cache, true);
        }

        $res = [];
        $title = [];
        $datas = [];

        $max = 0;
        $min = 0;
        $max2 = 0;
        $min2 = 0;
        $CityData = CityData::find()->where(['year'=>$maxYear])->orderBy(['add_amount'=>SORT_DESC])->limit(10)->asArray()->all();

        if ($CityData) {

            $datas = [];
            $datas2 = [];

            foreach ($CityData as $v) {
                $title[] = $v['city_name'];

                $datas[] = (float) $v['add_amount'];
                $add_amount = (int) $v['add_amount'];
                if ($add_amount > $max) {
                    $max = $add_amount;
                }

                if ($add_amount < $min) {
                    $min = $add_amount;
                }

                $datas2[] = [
                    'name' => $v['city_name'], 'data' => (float) $v['total_amount']
                ];
            }



            if ($title && $datas) {
                if ($max > 0) {
                    $max = $max + 2;
                }
                if ($min < 0) {
                    $min = $min - 2;
                }
                $res = [
                    'categories' => $title, 'series' => $datas, 'max'=> $max, 'min'=>$min,
                    'datas2' => $datas2
                ];
            }

        }

        Yii::$app->redis->set('CITY_YEAR#'.$maxYear, json_encode($res, JSON_UNESCAPED_UNICODE));
        Yii::$app->redis->expire('CITY_YEAR#'.$maxYear, 86400*10);
        return $res;
    }

    static function getLastYearMaxCity($maxYear) {

        $cache = Yii::$app->redis->get('CITY_MAX#'.$maxYear);
        if ($cache) {
            return json_decode($cache, true);
        }

        $res = [];
        $CityData = CityData::find()->where(['year'=>$maxYear])->orderBy(['total_amount'=>SORT_DESC])->limit(10)->asArray()->all();

        if ($CityData) {

            foreach ($CityData as $v) {

                $res[] = [
                    'name' => $v['city_name'].' '.$v['total_amount'].'（万）', 'data' => (float) $v['total_amount']
                ];
            }


        }

        Yii::$app->redis->set('CITY_MAX#'.$maxYear, json_encode($res, JSON_UNESCAPED_UNICODE));
        Yii::$app->redis->expire('CITY_MAX#'.$maxYear, 86400*10);
        return $res;
    }

    public static function collectFormId($formId, $openId) {

        if ($formId && $openId) {
            $expireTime = time() + 86400 * 7;
            $datas = [
                'formId' => $formId,
                'openId' => $openId,
                'expireTime' => $expireTime
            ];

            $json = json_encode($datas, JSON_UNESCAPED_UNICODE);
            Yii::$app->redis->lpush('FORM_ID', $json);
        }
    }

}
