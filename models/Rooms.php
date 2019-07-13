<?php

namespace app\models;
use Yii;

class Rooms  extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'rooms';
    }
}
