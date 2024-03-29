<?php

namespace app\models;
use Yii;

class Scores  extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'scores';
    }

    public function getUser(){
        return $this->hasOne(Users::className(), ['id' => 'user_id']);
    }

    //最近的已结束记录
    public function getLastRecord($user_id, $isLogin = false) {
        if (!$isLogin) {
            return [];
        }
        $lastCache = Yii::$app->redis->get('LAST#' . $user_id);
        if ($lastCache) {
            return json_decode($lastCache, true);
        }
        $result = [];
        $room_id = 0;
        //最近2次记录
        $RoomUsers = RoomUsers::find()->where(['user_id'=>$user_id, 'is_del'=>0])->orderBy(['id'=>SORT_DESC])->asArray()->all();
        foreach ($RoomUsers as $val) {
            //查看最近的一条是否为已结束
            $Rooms = Rooms::find()->where(['id'=>$val['room_id'], 'is_del'=>0])->asArray()->one();
            if ($Rooms) {
                if ($Rooms['status'] == Rooms::STATUS_IS_END) {
                    $room_id = $val['room_id'];
                    break;
                }
            }
        }


        if ($room_id) {
            $tmp = [];
            $total = [];
            $total2 = [];
            $our = RoomUsers::find()->where(['room_id'=>$room_id, 'is_del'=>0])->orderBy(['sorts'=>SORT_ASC])->asArray()->all();
            $sorts = [];
            foreach ($our as &$val) {

                $sorts[$val['user_id']] = ['user_id'=>$val['user_id'], 'sorts'=>$val['sorts']];
                $mycolor = Yii::$app->params['red'];
                if ($val['score'] < 0) {
                    $mycolor = Yii::$app->params['green'];
                }
                $total[] = [
                    'user_id'=>$val['user_id'],'score'=>$val['score'], 'color'=>$mycolor, 'date' => date('Y年m月d日', strtotime($val['create_time']))
                ];
                if ($val['user_id'] > 0) {
                    $total2[] = [
                        'user_id'=>$val['user_id'],'score'=>$val['score'], 'color'=>$mycolor, 'date' => date('Y年m月d日', strtotime($val['create_time']))
                    ];
                }
            }
            $scores = Scores::find()->where(['room_id'=>$room_id, 'is_del'=>0])->orderBy(['times'=>SORT_DESC])->asArray()->all();
            if ($scores) {

                foreach ($scores as $score) {

                    if (isset($sorts[$score['user_id']])) {
                        $tt = $sorts[$score['user_id']];
                        $tt['score'] = $score['score'];
                        $tt['times'] = $score['times'];
                        if($tt['score'] >= 0) {
                            $tt['color'] = Yii::$app->params['red'];
                        } else {
                            $tt['color'] = Yii::$app->params['green'];
                        }
                        $tmp[$score['times']][] = $tt;
                    }
                }

                if ($total && $total2) {
                    $total2 = Users::arrSort($total2, 'score', 'desc');
                    $ii = 1;
                    $uid_times = [];
                    foreach ($total2 as $v) {
                        $uid_times[$v['user_id']] = $ii;
                        $ii++;
                    }
                    foreach ($total as &$to) {
                        $to['first_or_second'] = '';
                        $to['style'] = '';
                        if (isset($uid_times[$to['user_id']])) {
                            if ($uid_times[$to['user_id']] == 1) {
                                $to['first_or_second'] = Yii::$app->params['image_no1'];
                                $to['style'] = 'gold';
                            } elseif ($uid_times[$to['user_id']] == 2) {
                                $to['first_or_second'] = Yii::$app->params['image_no2'];
                                $to['style'] = 'silvery';
                            }
                        }

                        $to['avatar'] = '';
                        $to['nickname'] = '';
                        $to['local_avatar'] = Yii::$app->params['image_default'];
                        if ($to['user_id']) {
                            $to['avatar'] = Users::getAvatar($to['user_id']);
                            $avatar = Users::getLocalAvatar($to['user_id']);
                            if ($avatar) {
                                $to['local_avatar'] = Yii::$app->params['serverHost'].$avatar;
                            }
                            $to['nickname'] = Users::getNickname($to['user_id']);
                        } else {
                            $to['nickname'] = Yii::$app->params['name_fa'];
                            $to['avatar'] = Yii::$app->params['image_fa'];
                        }

                    }
                }
            }
            if ($tmp) {
                $tmp2 = [];
                foreach ($tmp as $key=>$vv) {
                    $tmp2[] = ['times'=>$key, 'list'=>$vv];
                }
                $result = ['score' => $tmp2, 'total' => $total];

            }
        }

        Yii::$app->redis->set('LAST#' . $user_id, json_encode($result, JSON_UNESCAPED_UNICODE));
        Yii::$app->redis->expire('LAST#' . $user_id, 3600);
        return $result;
    }

    //获取历史统计记录汇总(近1年的)
    public function getLastYearScore2($user_id, $vip, $isLogin = false) {
        if (!$isLogin) {
            return [];
        }
        $res = [];
        $result = [];

        $lastCache = Yii::$app->redis->get('LASTYEAR2#' . $user_id);
        if ($lastCache) {
            return json_decode($lastCache, true);
        }

        $room_ids = [];
        $Rooms = Rooms::find()->where(['in', 'status', [Rooms::STATUS_IS_READY, Rooms::STATUS_BEGINING]])->andWhere(['is_del'=>0])->asArray()->all();
        if ($Rooms) {
            $room_ids = array_column($Rooms, 'id');
        }

        if (!$room_ids) {
            if ($vip) {
                $RoomUsers = RoomUsers::find()->select(['id', 'user_id', 'score', 'room_id', 'create_time'])->where(['user_id' => $user_id, 'is_del' => 0])->orderBy(['id' => SORT_DESC])->offset(0)->limit(10)->asArray()->all();
            } else {
                $RoomUsers = RoomUsers::find()->select(['id', 'user_id', 'score', 'room_id', 'create_time'])->where(['user_id' => $user_id, 'is_del' => 0])->orderBy(['id' => SORT_DESC])->offset(0)->limit(5)->asArray()->all();
            }
        } else {
            if ($vip) {
                $RoomUsers = RoomUsers::find()->select(['id', 'user_id', 'score', 'room_id', 'create_time'])->where(['user_id' => $user_id, 'is_del' => 0])->andWhere(['not in', 'room_id', $room_ids])->orderBy(['id' => SORT_DESC])->offset(0)->limit(10)->asArray()->all();
            } else {
                $RoomUsers = RoomUsers::find()->select(['id', 'user_id', 'score', 'room_id', 'create_time'])->where(['user_id' => $user_id, 'is_del' => 0])->andWhere(['not in', 'room_id', SORT_ASC])->offset(0)->limit(5)->orderBy(['id' => SORT_DESC])->asArray()->all();
            }
        }
        if ($RoomUsers) {
            $room_ids = array_column($RoomUsers, 'room_id');

            $max = 0;
            $min = 0;
            $title = [];
            $value = [];

            $nickName = Users::getNickname($user_id);
            $curYear = date('Y');
            foreach ($RoomUsers as $val) {
                $year = date('Y', strtotime($val['create_time']));
                if ($year == $curYear) {
                    $date = date('m月d日', strtotime($val['create_time']));
                    $tmp = date('Ymd', strtotime($val['create_time']));
                    $strtotime = strtotime($tmp);
                } else {
                    $date = date('Ymd', strtotime($val['create_time']));
                    $strtotime = strtotime($date);
                }

                $score = (int) $val['score'];
                if (!isset($value[$strtotime])) {
                    $title[$strtotime] = $date;
                    $value[$strtotime] = $score;
                } else {
                    $value[$strtotime] += $score;
                }

                if ($value[$strtotime] > $max) {
                    $max = $value[$strtotime];
                }
                if ($value[$strtotime] < $min) {
                    $min = $value[$strtotime];
                }
            }

            if ($max > 0) {
                $max += 100;
            }
            if ($min < 0) {
                $min -= 100;
            }

            $title2 = [];
            ksort($title);
            foreach ($title as $v1) {
                $title2[] = $v1;
            }
            $value2 = [];
            ksort($value);
            foreach ($value as $vv) {
                $value2[] = $vv;
            }

            $res = [
                'title' => $title2, 'value' => $value2, 'max' => $max, 'min' => $min, 'nickName' => $nickName
            ];
        }



        Yii::$app->redis->set('LASTYEAR2#' . $user_id, json_encode($res, JSON_UNESCAPED_UNICODE));
        Yii::$app->redis->expire('LASTYEAR2#' . $user_id, 86400);


        return $res;
    }


    //获取历史统计记录汇总(近1年的)
    public function getLastYearScore($user_id, $vip) {
        $res = [];
        $result = [];

        $lastCache = Yii::$app->redis->get('LASTYEAR#' . $user_id);
        if ($lastCache) {
            return json_decode($lastCache, true);
        }

        $room_ids = [];
        $Rooms = Rooms::find()->where(['in', 'status', [Rooms::STATUS_IS_READY, Rooms::STATUS_BEGINING]])->andWhere(['is_del'=>0])->asArray()->all();
        if ($Rooms) {
            $room_ids = array_column($Rooms, 'id');
        }

        $time = date('Y-m-d H:i:s', strtotime('-1 years'));
        if (!$room_ids) {
            if ($vip) {
                $RoomUsers = RoomUsers::find()->select(['id', 'user_id', 'score', 'room_id', 'create_time'])->where(['user_id' => $user_id, 'is_del' => 0])->orderBy(['id' => SORT_DESC])->asArray()->all();
            } else {
                $RoomUsers = RoomUsers::find()->select(['id', 'user_id', 'score', 'room_id', 'create_time'])->where(['user_id' => $user_id, 'is_del' => 0])->andWhere(['>', 'create_time', $time])->orderBy(['id' => SORT_DESC])->asArray()->all();
            }
        } else {
            if ($vip) {
                $RoomUsers = RoomUsers::find()->select(['id', 'user_id', 'score', 'room_id', 'create_time'])->where(['user_id' => $user_id, 'is_del' => 0])->andWhere(['not in', 'room_id', $room_ids])->orderBy(['id' => SORT_DESC])->asArray()->all();
            } else {
                $RoomUsers = RoomUsers::find()->select(['id', 'user_id', 'score', 'room_id', 'create_time'])->where(['user_id' => $user_id, 'is_del' => 0])->andWhere(['>', 'create_time', $time])->andWhere(['not in', 'room_id', $room_ids])->orderBy(['id' => SORT_DESC])->asArray()->all();
            }
        }
        if ($RoomUsers) {
            $room_ids = array_column($RoomUsers, 'room_id');
            $others = RoomUsers::find()->select(['id', 'user_id', 'score', 'room_id', 'create_time'])->where(['is_del'=>0])->where(['in', 'room_id', $room_ids])->andWhere(['<>', 'user_id', $user_id])->andWhere(['<>', 'user_id', 0])->asArray()->all();
            $tais = RoomUsers::find()->select(['id', 'user_id', 'score', 'room_id', 'create_time'])->where(['is_del'=>0])->where(['in', 'room_id', $room_ids])->andWhere(['user_id'=> 0])->asArray()->all();

            if ($others && $tais) {
                foreach ($RoomUsers as $val) {
                    $val['avatar'] = Users::getAvatar($val['user_id']);
                    $val['local_avatar'] = Yii::$app->params['image_default'];
                    $avatar = Users::getLocalAvatar($val['user_id']);
                    if ($avatar) {
                        $val['local_avatar'] = Yii::$app->params['serverHost'] . $avatar;
                    }

                    $val['create_time'] = date('m.d', strtotime($val['create_time']));
                    $val['color'] = Yii::$app->params['red'];
                    if ($val['score'] < 0) {
                        $val['color'] = Yii::$app->params['green'];
                    }
                    $result[$val['room_id']][] = $val;
                    foreach ($others as $other) {
                        if ($other['room_id'] == $val['room_id']) {
                            $other['avatar'] = Users::getAvatar($other['user_id']);

                            $other['local_avatar'] = Yii::$app->params['image_default'];
                            $avatar2 = Users::getLocalAvatar($other['user_id']);
                            if ($avatar2) {
                                $other['local_avatar'] = Yii::$app->params['serverHost'].$avatar2;
                            }
                            $other['create_time'] = date('m.d', strtotime($other['create_time']));
                            $other['color'] = Yii::$app->params['red'];
                            if ($other['score'] < 0) {
                                $other['color'] = Yii::$app->params['green'];
                            }
                            $result[$val['room_id']][] = $other;
                        }
                    }

                    foreach ($tais as $tai) {
                        if ($tai['room_id'] == $val['room_id']) {
                            $tai['local_avatar'] = Yii::$app->params['image_default'];
                            $tai['avatar'] = Yii::$app->params['image_fa'];
                            $tai['create_time'] = date('m.d', strtotime($tai['create_time']));
                            $tai['color'] = Yii::$app->params['red'];
                            if ($tai['score'] < 0) {
                                $tai['color'] = Yii::$app->params['green'];
                            }
                            $result[$val['room_id']][] = $tai;
                        }
                    }
                }

                if ($result) {
                    foreach ($result as $V) {
                        $res[] = $V;
                    }
                }
            }
        }

        Yii::$app->redis->set('LASTYEAR#' . $user_id, json_encode($res, JSON_UNESCAPED_UNICODE));
        Yii::$app->redis->expire('LASTYEAR#' . $user_id, 86400);


        return $res;
    }

    public function myResults($user_id, $isLogin = false) {
        if (!$isLogin) {
            return [];
        }
        $lastCache = Yii::$app->redis->get('RESULTS#' . $user_id);
        if ($lastCache) {
            return json_decode($lastCache, true);
        }

        $result = [
            'totalScore' => 0, 'totalScoreColor'=> Yii::$app->params['red'],
            'cjScore' => 0, 'cjScoreColor'=> Yii::$app->params['red'],
            'maxScore'=>0, 'maxScoreColor' => Yii::$app->params['red'],
            'minScore'=>0, 'minScoreColor' => Yii::$app->params['red'],
            'jsScore'=>0, 'jsScoreColor' => Yii::$app->params['black'],
            'timesScore'=>0, 'timesScoreColor' => Yii::$app->params['black']
        ];

        $RoomUsers = RoomUsers::find()->where(['user_id'=>$user_id, 'is_del'=>0])->asArray()->all();
        if ($RoomUsers) {

            $totalTime = 0;

            $result['timesScore'] = count($RoomUsers);
            foreach ($RoomUsers as $val) {
                $result['totalScore'] += $val['score'];

                $totalTime += strtotime($val['update_time']) - strtotime($val['create_time']);
            }
            if ($result['totalScore'] < 0) {
                $result['totalScoreColor'] = Yii::$app->params['green'];
            }

            $result['cjScore'] = round($result['totalScore']/$result['timesScore']);
            if ($result['cjScore'] < 0) {
                $result['cjScoreColor'] = Yii::$app->params['green'];
            }


            $result['jsScore'] =  round(($totalTime/$result['timesScore'])/3600).'H';


            $RoomUsers2 = Users::arrSort($RoomUsers, 'score', 'desc');
            $result['maxScore'] = $RoomUsers2[0]['score'] ?? 0;
            if ($result['maxScore'] < 0) {
                $result['maxScoreColor'] = Yii::$app->params['green'];
            }
            $result['minScore'] = $RoomUsers2[$result['timesScore'] - 1]['score'] ?? 0;
            if ($result['minScore'] < 0) {
                $result['minScoreColor'] = Yii::$app->params['green'];
            }

        }

        Yii::$app->redis->set('RESULTS#' . $user_id, json_encode($result, JSON_UNESCAPED_UNICODE));
        Yii::$app->redis->expire('RESULTS#' . $user_id, 86400);

        return $result;
    }
}
