<?php

namespace app\models;
use Yii;

class Labels  extends \yii\db\ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'labels';
    }

}
