<?php

namespace app\models;
use Yii;

class UserClass  extends \yii\db\ActiveRecord
{

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_class';
    }

    static public function getColorclass($user_id) {
        $cache = Yii::$app->redis->get('AVATARCLASS#'.$user_id);
        if ($cache) {
            return $cache;
        }
        $UserClass = UserClass::find()->select(['color_class'])->where(['id'=>$user_id])->asArray()->one();
        if ($UserClass) {
            Yii::$app->redis->set('AVATARCLASS#'.$user_id, $UserClass['color_class']);
            return $UserClass['color_class'];
        }
        return '';
    }

}
