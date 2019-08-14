<?php

namespace app\controllers;

use app\models\Users;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;

use app\models\Shops;
use app\models\Positions;


class TestController extends Controller
{

    public function actionTest() {
        $params = json_decode(file_get_contents('php://input'),true);
        $Shops = new Shops();
        $queryShop = $Shops->queryShop($params);
        if ($queryShop) {
            $this->jsonResponse['code'] = 0;
            $this->jsonResponse['msg'] = '';
            $this->jsonResponse['data'] = $queryShop;
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);

    }


}
