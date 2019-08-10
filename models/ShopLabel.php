<?php

namespace app\models;
use Yii;

class ShopLabel  extends \yii\db\ActiveRecord
{

    public $label_name;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shop_label';
    }

    public function getLabels() {
        return $this->hasOne(Labels::className(), ['id' => 'label_id'])->from(Labels::tableName().' L');// from设置别名，尽量避免手写表名称，会要求手动添加表前缀
    }
}
