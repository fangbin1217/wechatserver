<?php

namespace app\models;
use Yii;

class Positions  extends \yii\db\ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'positions';
    }

    public static function getPosition($uid) {
        $Positions = Positions::find()->where(['user_id'=>$uid])->asArray()->one();
        if ($Positions) {
            $datas = ['province_name'=>$Positions['province_name'], 'city_name'=>$Positions['city_name']];
            return $datas;
        }
        return [];
    }

    public function saveP($params) {
        $date = date('Y-m-d H:i:s');
        $Positions = Positions::find()->where(['user_id'=>$params['uid']])->asArray()->one();
        if ($Positions) {
            $p = Positions::find()->where(['user_id'=>$params['uid']])->one();
            $p->province_name = $params['province_name'];
            $p->city_name = $params['city_name'];
            $p->update_time = $date;
            if ($p->save()) {
                return true;
            }
        } else {
            $p = new Positions();
            $p->province_name = $params['province_name'];
            $p->city_name = $params['city_name'];
            $p->user_id = $params['uid'];
            $p->create_time = $date;
            $p->update_time = $date;
            if ($p->save()) {
                return true;
            }
        }
        return false;

    }
}
