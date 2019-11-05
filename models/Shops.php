<?php

namespace app\models;
use Yii;

class Shops  extends \yii\db\ActiveRecord
{

    public static $error_msg = '';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shops';
    }

    static public function shopList($page = 1, $city_name = '',$province_name = '',  $shop_name = '') {
        /*
        if (!$page) {
            $page = 1;
        }
        $Rooms = Shops::find()->where(['is_del'=>0]);

        if ($province_name) {
            //$Rooms->andWhere(['province_name' => $province_name]);
        }
        if ($city_name) {
            //$Rooms->andWhere(['city_name' => $city_name]);
        }
        if ($shop_name) {
            $Rooms->andWhere(['like', 'name', $shop_name]);
        }

        $result = ['page'=> (int) $page, 'list'=>[], 'count'=>0];
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
                    'id' => $val['id'], 'name' => $val['name'], 'logo' => Yii::$app->params['imgHost'].Yii::$app->params['shop_default_img'],
                    'create_time' => $val['create_time'], 'province_name' => $val['province_name'],'city_name' => $val['city_name'],  'address' => $val['address'],
                    'telphone' => $val['telphone'], 'star' => $val['star'],'business_time'=>$val['business_time'], 'label_list' => [], 'left' => '0px','width' => '0px'
                ];
                if ($val['logo']) {
                    $tmp['logo'] = Yii::$app->params['imgHost'].$val['logo'];
                }
                if ($ShopLabel) {
                    foreach ($ShopLabel as $v) {
                        if ($v->attributes['shop_id'] == $val['id']) {
                            $tmp['label_list'][] = ['label_id'=>$v->attributes['label_id'],'label_name'=>$v->label_name,'counts'=>$v->attributes['counts']];
                        }
                    }
                }

                $left = (int) ((5 - $val['star'])*25);
                if ($left > 0) {
                    $tmp['width'] = $left.'px';
                    $tmp['left'] = '-'.$left.'px';
                }
                $list[] =$tmp;

            }

            //print_r($list);exit;
            $result['list'] = $list;
            $result['count'] = (int) count($list);
            return $result;
        }
        return $result;
        */
    }

    public static function saveComment($params) {

        $params['shop_id'] = $params['shop_id'] ?? 0;
        $params['shop_id'] = (int) $params['shop_id'];
        $params['uid'] = $params['uid'] ?? 0;
        $params['star'] = $params['star'] ?? 0;
        $params['labels'] = $params['labels'] ?? [];

        if (!$params['shop_id'] || !$params['uid']) {
            Shops::$error_msg = 'shop_id or uid empty';
            return false;
        }

        if (!in_array($params['star'], [1,2,3,4,5])) {
            Shops::$error_msg = '评分不能为空';
            return false;
        }

        $Shops = Shops::find()->where(['id'=>$params['shop_id'], 'is_del'=>0])->asArray()->one();
        if (!$Shops) {
            Shops::$error_msg = 'shop data lost';
            return false;
        }

        $date = date('Y-m-d');
        $ShopStar = ShopStar::find()->where(['shop_id'=>$params['shop_id'], 'user_id'=>$params['uid'], 'create_time'=>$date])->asArray()->one();
        if ($ShopStar) {
            Shops::$error_msg = '今日已评论！';
            return false;
        }

        $labels = Labels::find()->where(['is_good'=>1])->asArray()->all();
        if (!$labels) {
            Shops::$error_msg = '无好评标签！';
            return false;
        }



        try {
            $trans = Yii::$app->getDb()->beginTransaction();


            $total_stars = 0;
            $star_times = 0;
            $ShopStars = ShopStar::find()->where(['shop_id'=>$params['shop_id']])->asArray()->all();
            if ($ShopStars) {
                foreach ($ShopStars as $val) {
                    $total_stars += $val['star'];
                }
                $star_times = count($ShopStars);
            }

            $st = new ShopStar();
            $st->shop_id = $params['shop_id'];
            $st->user_id = $params['uid'];
            $st->star = $params['star'];
            $st->create_time = $date;
            if (!$st->save()) {
                Shops::$error_msg = '保存星级失败';
                return false;
            }
            $total_stars += $params['star'];

            $star_times += 1;
            $ava_star = round($total_stars/$star_times, 1);



            $total_labels = 0;

            $labels_ids = array_column($labels, 'id');
            $sl2 = ShopLabel::find()->where(['shop_id' => $params['shop_id'], 'is_del' => 0])->andWhere(['in', 'label_id', $labels_ids])->asArray()->all();
            if ($sl2) {
                foreach ($sl2 as $vv) {
                    $total_labels += $vv['counts'];
                }
            }

            if ($params['labels']) {
                foreach ($params['labels'] as $val) {
                    if ($val['is_choose']) {
                        $ShopLabel = ShopLabel::find()->where(['shop_id'=>$params['shop_id'], 'label_id'=>$val['label_id'], 'is_del'=>0])->asArray()->one();
                        if ($ShopLabel) {
                            $SL = ShopLabel::find()->where(['id'=>$ShopLabel['id']])->one();
                            $SL->counts = $ShopLabel['counts'] + 1;
                            $SL->user_id = $params['uid'];
                            $SL->create_time = $date;
                            if (!$SL->save()) {
                                Shops::$error_msg = '更新标签失败';
                                return false;
                            }
                        } else {
                            $SL = new ShopLabel();
                            $SL->shop_id = $params['shop_id'];
                            $SL->label_id = $val['label_id'];
                            $SL->counts = 1;
                            $SL->user_id = $params['uid'];
                            $SL->create_time = $date;
                            if (!$SL->save()) {
                                Shops::$error_msg = '保存标签失败';
                                return false;
                            }
                        }
                        if (in_array($val['label_id'], $labels_ids)) {
                            $total_labels += 1;
                        }
                    }
                }
            }



            $SORTS = (int) (($ava_star*10) + $total_labels);


            $Shops2 = Shops::find()->where(['id'=>$params['shop_id'], 'is_del'=>0])->one();
            $Shops2->star = $ava_star;
            $Shops2->sorts = $SORTS;
            $Shops2->update_time = date('Y-m-d H:i:s');
            if (!$Shops2->save()) {
                Shops::$error_msg = '更新商家失败';
                return false;
            }

            $trans->commit();
            return true;
        } catch (Exception $E) {
            $trans->rollBack();
            Shops::$error_msg = '保存失败';
            return false;
        }
        Shops::$error_msg = '保存失败';
        return false;
    }

    public function queryShop($params) {
        /*
        $params['shop_id'] = $params['shop_id'] ?? 0;

        if (!$params['shop_id']) {
            return [];
        }
        $Shops = Shops::find()->where(['id'=>$params['shop_id'], 'is_del'=>0])->asArray()->one();
        if (!$Shops) {
            return [];
        }

        if ($Shops['logo']) {
            $Shops['logo'] = Yii::$app->params['imgHost'].$Shops['logo'];
        } else {
            $Shops['logo'] = Yii::$app->params['imgHost'].Yii::$app->params['shop_default_img'];
        }


        $labelsList = [];
        $labels = Labels::find()->where(['is_good'=>1])->asArray()->all();
        if ($labels) {

            foreach ($labels as &$val) {
                $labelsList[] = [
                    'label_id' => $val['id'], 'name' => $val['name'],
                    'is_choose' => 0, 'color' => '#cdcdcd', 'is_good' => $val['is_good']
                ];
            }
        }
        $res = [
            'shops' => $Shops, 'labels' => $labelsList
        ];
        return $res;
        */

    }


}
