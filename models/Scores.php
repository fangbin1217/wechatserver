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

                $sorts[$val['user_id']] = ['user_id'=>$val['user_id'], 'sorts'=>$val['sorts']];
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
                                $to['first_or_second'] = '../../images/NO.png';
                            } elseif ($uid_times[$to['user_id']] == 2) {
                                $to['first_or_second'] = '../../images/NO2.png';
                            }
                        }

                        $to['avatar'] = '';
                        $to['nickname'] = '';
                        if ($to['user_id']) {
                            $to['avatar'] = Users::getAvatar($to['user_id']);
                            $to['nickname'] = Users::getNickname($to['user_id']);
                        } else {
                            $to['nickname'] = '台板';
                            $to['avatar'] = Yii::$app->params['serverHost'].'images/fa.png';
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
        $res = [];
        $result = [];
        $time = date('Y-m-d H:i:s', strtotime('-1 years'));
        $RoomUsers = RoomUsers::find()->select(['id', 'user_id', 'score', 'room_id', 'create_time'])->where(['user_id'=>$user_id, 'is_del'=>0])->andWhere(['>', 'create_time', $time])->orderBy(['id'=>SORT_DESC])->asArray()->all();
        if ($RoomUsers) {
            $room_ids = array_column($RoomUsers, 'room_id');
            $others = RoomUsers::find()->select(['id', 'user_id', 'score', 'room_id', 'create_time'])->where(['is_del'=>0])->where(['in', 'room_id', $room_ids])->andWhere(['<>', 'user_id', $user_id])->andWhere(['<>', 'user_id', 0])->asArray()->all();
            $tais = RoomUsers::find()->select(['id', 'user_id', 'score', 'room_id', 'create_time'])->where(['is_del'=>0])->where(['in', 'room_id', $room_ids])->andWhere(['<>', 'user_id', $user_id])->andWhere(['user_id'=> 0])->asArray()->all();

            if ($others && $tais) {
                foreach ($RoomUsers as $val) {
                    $val['avatar'] = Users::getAvatar($val['user_id']);
                    $val['create_time'] = date('m月d日', strtotime($val['create_time']));
                    $val['color'] = '#E64340';
                    if ($val['score'] < 0) {
                        $val['color'] = '#09BB07';
                    }
                    $result[$val['room_id']][] = $val;
                    foreach ($others as $other) {
                        if ($other['room_id'] == $val['room_id']) {
                            if ($other['user_id']) {
                                $other['avatar'] = Users::getAvatar($other['user_id']);
                            } else {
                                $other['avatar'] = Yii::$app->params['serverHost'].'images/fa.png';
                            }
                            $other['create_time'] = date('m月d日', strtotime($other['create_time']));
                            $other['color'] = '#E64340';
                            if ($other['score'] < 0) {
                                $other['color'] = '#09BB07';
                            }
                            $result[$val['room_id']][] = $other;
                        }
                    }

                    foreach ($tais as $tai) {
                        if ($tai['room_id'] == $val['room_id']) {
                            if ($tai['user_id']) {
                                $tai['avatar'] = Users::getAvatar($tai['user_id']);
                            } else {
                                $tai['avatar'] = Yii::$app->params['serverHost'].'images/fa.png';
                            }
                            $tai['create_time'] = date('m月d日', strtotime($tai['create_time']));
                            $tai['color'] = '#E64340';
                            if ($tai['score'] < 0) {
                                $tai['color'] = '#09BB07';
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
        return $res;
    }

    public function myResults($user_id) {
        $result = [];
        $RoomUsers = RoomUsers::find()->where(['user_id'=>$user_id, 'is_del'=>0])->asArray()->all();
        if ($RoomUsers) {

            $result = [
                'totalScore' => 0, 'totalScoreColor'=> '#E64340',
                'cjScore' => 0, 'cjScoreColor'=> '#E64340',
                'maxScore'=>0, 'maxScoreColor' => '#E64340',
                'minScore'=>0, 'minScoreColor' => '#E64340',
                'jsScore'=>0, 'jsScoreColor' => '#353535',
                'timesScore'=>0, 'timesScoreColor' => '#353535'
            ];

            $totalTime = 0;

            $result['timesScore'] = count($RoomUsers);
            foreach ($RoomUsers as $val) {
                $result['totalScore'] += $val['score'];

                $totalTime += strtotime($val['update_time']) - strtotime($val['create_time']);
            }
            if ($result['totalScore'] < 0) {
                $result['totalScoreColor'] = '#09BB07';
            }

            $result['cjScore'] = round($result['totalScore']/$result['timesScore']);
            if ($result['cjScore'] < 0) {
                $result['cjScoreColor'] = '#09BB07';
            }


            $result['jsScore'] =  round(($totalTime/$result['timesScore'])/3600).'H';


            $RoomUsers2 = Users::arrSort($RoomUsers, 'score', 'desc');
            $result['maxScore'] = $RoomUsers2[0]['score'] ?? 0;
            if ($result['maxScore'] < 0) {
                $result['maxScoreColor'] = '#09BB07';
            }
            $result['minScore'] = $RoomUsers2[$result['timesScore'] - 1]['score'] ?? 0;
            if ($result['minScore'] < 0) {
                $result['minScoreColor'] = '#09BB07';
            }

        }

        return $result;
    }
}
