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
}
