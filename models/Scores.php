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
    public function getLastRecord($user_id) {
        $result = [];
        $room_id = 0;
        //最近2次记录
        $RoomUsers = RoomUsers::find()->where(['user_id'=>$user_id, 'is_del'=>0])->orderBy(['id'=>SORT_DESC])->asArray()->all();
        foreach ($RoomUsers as $val) {
            //查看最近的一条是否为已结束
            $Rooms = Rooms::find()->where(['id'=>$val['room_id'], 'is_del'=>0])->asArray()->one();
            if ($Rooms) {
                if ($Rooms['status'] == 2) {
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
                $val['avatar'] = '';
                if ($val['user_id']) {
                    $tmp_user = Users::find()->where(['id' => $val['user_id']])->asArray()->one();
                    if ($tmp_user) {
                        $val['avatar'] = $tmp_user['avatar'];
                        $val['nickname'] = $tmp_user['nickname'];
                    }
                } else {
                    $val['avatar'] = Yii::$app->params['serverHost'].'images/fa.png';
                    $val['nickname'] = '台板';
                }
                $sorts[$val['user_id']] = ['user_id'=>$val['user_id'], 'nickname'=>$val['nickname'], 'avatar'=>$val['avatar'], 'sorts'=>$val['sorts']];
                $mycolor = '#E64340';
                if ($val['score'] < 0) {
                    $mycolor = '#09BB07';
                }
                $total[] = [
                    'user_id'=>$val['user_id'],'score'=>$val['score'], 'color'=>$mycolor
                ];
                if ($val['user_id'] > 0) {
                    $total2[] = [
                        'user_id'=>$val['user_id'],'score'=>$val['score'], 'color'=>$mycolor
                    ];
                }
            }
            $scores = Scores::find()->where(['room_id'=>$room_id, 'is_del'=>0])->orderBy(['times'=>SORT_ASC])->asArray()->all();
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
                        if (isset($uid_times[$to['user_id']])) {
                            if ($uid_times[$to['user_id']] == 1) {
                                $to['first_or_second'] = '../../images/guanjun.png';
                            } elseif ($uid_times[$to['user_id']] == 2) {
                                $to['first_or_second'] = '../../images/yajun.png';
                            }
                        }
                    }
                }
            }
            if ($tmp) {
                $result = ['score' => $tmp, 'total' => $total];
            }
        }
        return $result;
    }

    //获取历史统计记录汇总(近1年的)
    public function getLastYearScore($user_id) {
        $result = [];
        $room_id = 0;
        $time = date('Y-m-d H:i:s', strtotime('-1 years'));
        $RoomUsers = RoomUsers::find()->select(['id', 'user_id', 'score', 'room_id', 'create_time'])->where(['user_id'=>$user_id, 'is_del'=>0])->andWhere(['>', 'create_time', $time])->orderBy(['id'=>SORT_DESC])->asArray()->all();
        if ($RoomUsers) {
            $room_ids = array_column($RoomUsers, 'room_id');
            $others = RoomUsers::find()->select(['id', 'user_id', 'score', 'room_id', 'create_time'])->where(['is_del'=>0])->where(['in', 'room_id', $room_ids])->andWhere(['<>', 'user_id', $user_id])->asArray()->all();

            if ($others) {
                $tmp = [];
                foreach ($RoomUsers as $val) {
                    $val['avatar'] = Users::getAvatar($val['user_id']);
                    $val['create_time'] = date('m月d日', strtotime($val['create_time']));
                    $val['color'] = '#E64340';
                    if ($val['score'] < 0) {
                        $val['color'] = '#09BB07';
                    }
                    $tmp[$room_id][] = $val;
                    foreach ($others as $other) {
                        if ($other['room_id'] == $val['room_id']) {
                            $other['avatar'] = Users::getAvatar($other['user_id']);
                            $other['create_time'] = date('m月d日', strtotime($other['create_time']));
                            $other['color'] = '#E64340';
                            if ($other['score'] < 0) {
                                $other['color'] = '#09BB07';
                            }
                            $tmp[$room_id][] = $other;
                        }
                    }
                }
            }
        }
        return $result;
    }

    public function myResults($user_id) {
        $result = [
            'totalScore' => 0, 'maxScore'=>0, 'minScore'=>0, 'times'=>0
        ];
        $RoomUsers = RoomUsers::find()->select(['id', 'user_id', 'score', 'room_id', 'create_time'])->where(['user_id'=>$user_id, 'is_del'=>0])->asArray()->all();
        if ($RoomUsers) {
            $result['times'] = count($RoomUsers);
            foreach ($RoomUsers as $val) {
                $result['totalScore'] += $val['score'];
            }

            $RoomUsers2 = Users::arrSort($RoomUsers, 'score', 'desc');
            $result['maxScore'] = $RoomUsers2[0]['score'] ?? 0;
            $result['minScore'] = $RoomUsers2[$result['times'] - 1]['score'] ?? 0;

        }

        return $result;
    }
}
