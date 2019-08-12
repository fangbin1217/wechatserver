<?php

namespace app\models;
use Yii;

class Shops  extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shops';
    }

    static public function shopList($page = 1, $access_token = '',$city_name = '',$province_name = '',  $shop_name = '') {
        if (!$page) {
            $page = 1;
        }
        $Rooms = Shops::find()->where(['is_del'=>0]);

        if ($province_name) {
            $Rooms->andWhere(['province_name' => $province_name]);
        }
        if ($city_name) {
            $Rooms->andWhere(['city_name' => $city_name]);
        }
        if ($shop_name) {
            $Rooms->andWhere(['like', 'name', $shop_name]);
        }

        $list = [];
        $res = $Rooms->orderBy(['sorts'=>SORT_DESC])->offset(($page - 1) * Yii::$app->params['page_size'])->limit(Yii::$app->params['page_size'])->asArray()->all();
        if ($res) {
            $shop_ids = array_column($res, 'id');

            $SL = ShopLabel::tableName();
            $ShopLabel = ShopLabel::find()->select([$SL.'.shop_id',$SL.'.label_id',$SL.'.counts', 'L.name as label_name'])->joinWith('labels')->where(['in', $SL.'.shop_id', $shop_ids])->andWhere([$SL.'.is_del'=>0])->all();

            foreach ($res as &$val) {
                if (!isset($val['label_list'])) {
                    $val['label_list'] = [];
                }
                $tmp = [];
                $tmp = [
                    'id' => $val['id'], 'name' => $val['name'], 'logo' => Yii::$app->params['imgHost'].Yii::$app->params['shop_image'],
                    'create_time' => $val['create_time'], 'province_name' => $val['province_name'],'city_name' => $val['city_name'],  'address' => $val['address'],
                    'telphone' => $val['telphone'], 'star' => $val['star'],'business_time'=>$val['business_time'], 'label_list' => []
                ];
                if ($val['logo']) {
                    $tmp['logo'] = Yii::$app->params['serverHost'].$val['logo'];
                }
                if ($ShopLabel) {
                    foreach ($ShopLabel as $v) {
                        if ($v->attributes['shop_id'] == $val['id']) {
                            $tmp['label_list'][] = ['label_id'=>$v->attributes['label_id'],'label_name'=>$v->label_name,'counts'=>$v->attributes['counts']];
                        }
                    }
                }
                $list[] =$tmp;

            }

            //print_r($list);exit;
            return ['page'=>$page, 'list'=>$list];
        }
        return $list;
    }
}
