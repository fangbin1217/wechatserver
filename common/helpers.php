<?php
/**
 * Created by PhpStorm.
 * User: binfang
 * Date: 2019-07-24
 * Time: 15:42
 */

if (! function_exists('foo')) {

    function foo() {
        echo 'foo';
    }
}

if (! function_exists('vesionInt')) {

    function vesionInt($version = '') {
        if ($version) {
            $version = str_replace('.', '', $version);
            return (int) $version;
        }
        return 0;
    }
}

if (! function_exists('getAnimal')) {

    function getAnimal($year = '') {
        if (!$year) {
            $year = date('Y');
        }
        $animals = [
            'shu.png','niu.png','hu.png','tu.png','long.png','she.png'
            ];

        return $animals[0];
    }
}

if (! function_exists('getRandData')) {

    function getRandData($check = true) {

        $boxClass = ['out-front img-out', 'out-back img-out', 'out-left img-out', 'out-right img-out', 'out-top img-out', 'out-bottom img-out'];
        $data = [
            'shu.png', 'niu.png', 'hu.png', 'tu.png', 'long.png', 'she.png'
        ];
        $datas = [];
        $i = 0;
        foreach ($data as $val) {
            $datas[] = [
                'boxClass' => $boxClass[$i],
                'boxImg' =>  '../../images/'.$val
            ];
            $i++;
        }
        return $datas;


    }
}