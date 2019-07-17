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

    public function setExpireRoom() {
        $time = time();
        $date = date('Y-m-d H:i:s');
        $Rooms = Rooms::find()->select(['id'])->where(['is_del'=>0])->andWhere(['in', 'status', [0,1]])->andWhere(['<', 'expire_time', $time])->asArray()->all();
        if ($Rooms) {

                try {
                    $trans = Yii::$app->getDb()->beginTransaction();
                    foreach ($Rooms as $val) {
                        $upds = Rooms::find()->where(['id'=>$val['id']])->one();
                        $upds->status = 2;
                        $upds->update_time = $date;
                        if (!$upds->save()) {
                            return false;
                        }
                    }
                    $trans->commit();
                    return true;
                } catch (Exception $E) {
                    $trans->rollBack();
                    return false;
                }


        }
        return false;
    }

    static public function isRoomOwner($user_id) {
        $time = time();
        $date = date('Y-m-d H:i:s');
        $Rooms = Rooms::find()->where(['is_del'=>0, 'user_id'=>$user_id])->andWhere(['in', 'status', [0,1]])->orderBy(['id'=>SORT_DESC])->asArray()->one();
        if ($Rooms) {
            return true;
        }
        return false;
    }
}
