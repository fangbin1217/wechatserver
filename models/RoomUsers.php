<?php

namespace app\models;
use Yii;

class RoomUsers  extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'room_users';
    }

    public function getUser(){
        return $this->hasOne(Users::className(), ['id' => 'user_id']);

    }
}
