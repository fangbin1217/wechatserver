<?php

namespace app\models;
use Yii;

class Rooms  extends \yii\db\ActiveRecord
{

    const STATUS_IS_READY = 0;
    const STATUS_BEGINING = 1;
    const STATUS_IS_END = 2;
    static public $room_status = [self::STATUS_IS_READY=>'已准备', self::STATUS_BEGINING=>'进行中', self::STATUS_IS_END=>'已结束'];

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
        $Rooms = Rooms::find()->select(['id'])->where(['is_del'=>0])->andWhere(['in', 'status', [self::STATUS_IS_READY, self::STATUS_BEGINING]])->andWhere(['<', 'expire_time', $time])->asArray()->all();
        if ($Rooms) {
                try {
                    $trans = Yii::$app->getDb()->beginTransaction();
                    foreach ($Rooms as $val) {
                        $upds = Rooms::find()->where(['id'=>$val['id']])->one();
                        $upds->status = self::STATUS_IS_END;
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
        return true;
    }

    static public function isRoomOwner($user_id) {
        $time = time();
        $date = date('Y-m-d H:i:s');
        $Rooms = Rooms::find()->where(['is_del'=>0, 'user_id'=>$user_id])->andWhere(['in', 'status', [self::STATUS_IS_READY, self::STATUS_BEGINING]])->orderBy(['id'=>SORT_DESC])->asArray()->one();
        if ($Rooms) {
            return true;
        }
        return false;
    }
}
