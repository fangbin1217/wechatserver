<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\Scores;


class ScoreController extends Controller
{

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        return 'success';
    }


    public function actionLast()
    {
        $this->jsonResponse['msg'] = 'get lastrecord fail';
        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        $version = $params['version'] ?? '';
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);
                $isLogin = false;
                if ($cacheList['avatar'] !== Yii::$app->params['image_default']) {
                    $avatarUpdTime = $cacheList['avatar_updtime'] ?? '';
                    $avatarUpdTime = (int) $avatarUpdTime;
                    if ($avatarUpdTime) {
                        $be = time() - $avatarUpdTime;
                        if ($be < Yii::$app->params['loginCacheTime']) {
                            $isLogin = true;
                        }
                    }
                }
                $getLastRecord = (new Scores())->getLastRecord($cacheList['id'], $isLogin);
                if ($getLastRecord) {
                    $this->jsonResponse['code'] = 0;
                    $this->jsonResponse['msg'] = 'get lastrecord success';
                    $this->jsonResponse['xiaojiScore'] = $getLastRecord['score'];
                    $this->jsonResponse['totalScore'] = $getLastRecord['total'];
                }

            }
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }


    public function actionResults()
    {
        $this->jsonResponse['msg'] = 'get results fail';
        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);
                $isLogin = false;
                if ($cacheList['avatar'] !== Yii::$app->params['image_default']) {
                    $avatarUpdTime = $cacheList['avatar_updtime'] ?? '';
                    $avatarUpdTime = (int) $avatarUpdTime;
                    if ($avatarUpdTime) {
                        $be = time() - $avatarUpdTime;
                        if ($be < Yii::$app->params['loginCacheTime']) {
                            $isLogin = true;
                        }
                    }
                }
                $myResults = (new Scores())->myResults($cacheList['id'], $isLogin);
                if ($myResults) {
                    $this->jsonResponse['code'] = 0;
                    $this->jsonResponse['msg'] = 'get results success';
                    $this->jsonResponse['data'] = $myResults;
                }

            }
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    public function actionLastyear()
    {
        $this->jsonResponse['msg'] = 'get results fail';
        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);

                $vip = 0;
                if (isset($cacheList['vip'])) {
                    $vip = $cacheList['vip'];
                }
                $myResults = (new Scores())->getLastYearScore($cacheList['id'], $vip);
                if ($myResults) {
                    $this->jsonResponse['code'] = 0;
                    $this->jsonResponse['msg'] = 'get results success';
                    $this->jsonResponse['data'] = $myResults;
                }

            }
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }

    public function actionLastyear2()
    {
        $this->jsonResponse['msg'] = 'get results fail';
        $params = json_decode(file_get_contents('php://input'),true);
        $access_token = $params['access_token'] ?? '';
        if ($access_token) {
            $cache = Yii::$app->redis->get('T#' . $access_token);
            if ($cache) {
                $cacheList = json_decode($cache, true);

                $vip = $cacheList['vip'] ?? false;

                $isLogin = false;
                if ($cacheList['avatar'] !== Yii::$app->params['image_default']) {
                    $avatarUpdTime = $cacheList['avatar_updtime'] ?? '';
                    $avatarUpdTime = (int) $avatarUpdTime;
                    if ($avatarUpdTime) {
                        $be = time() - $avatarUpdTime;
                        if ($be < Yii::$app->params['loginCacheTime']) {
                            $isLogin = true;
                        }
                    }
                }
                $myResults = (new Scores())->getLastYearScore2($cacheList['id'], $vip, $isLogin);
                if ($myResults) {
                    $this->jsonResponse['code'] = 0;
                    $this->jsonResponse['msg'] = 'get results success';
                    $this->jsonResponse['data'] = $myResults;
                }

            }
        }
        return json_encode($this->jsonResponse, JSON_UNESCAPED_UNICODE);
    }


}
