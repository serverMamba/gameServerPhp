<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 18-10-7
 * Time: 下午3:36
 */

require 'autoload.php';

$ret = [ // 返回值标准格式, 支持只返回其中一个
    'errCode' => conErrorCode::ERR_OK,
    'data' => []
];

if (!isset($_POST['svc']) || !isset($_POST['func'])) {
    Log::error(basename(__FILE__) . ', ' . __LINE__ . ', invalid param, param = ' . json_encode($_POST));
    $ret['errCode'] = conErrorCode::ERR_CLIENT;
    echo json_encode($ret);
    ob_flush();
    exit();
}
$_POST['param'] = isset($_POST['param']) ? $_POST['param'] : [];

$ret = (new $_POST['svc'])->{$_POST['func']}($_POST['param']);

if (!is_array($ret)) {
    if (is_int($ret)) { // 只返回errCode
        $ret = [
            'errCode' => $ret,
            'data' => []
        ];
    } else {
        $ret = [
            'errCode' => conErrorCode::ERR_SERVER,
            'data' => []
        ];
    }

    echo json_encode($ret);
    ob_flush();
    exit();
}

if (!isset($ret['errCode'])) {
    if (isset($ret['data']) && is_array($ret['data'])) { // 只返回data
        $data = $ret['data'];
        $ret = [
            'errCode' => conErrorCode::ERR_OK,
            'data' => $data
        ];
    } else if (!isset($ret['data'])) { // errCode和data都没返回, 但ret就是data
        $data = $ret;
        $ret = [
            'errCode' => conErrorCode::ERR_OK,
            'data' => $data
        ];
    } else {
        Log::error(basename(__FILE__) . ', ' . __LINE__ . ', server return is wrong, ret = ' . json_encode($ret));
        $ret = [
            'errCode' => conErrorCode::ERR_SERVER,
            'data' => []
        ];
    }

    echo json_encode($ret);
    ob_flush();
    exit();
}

if (!is_int($ret['errCode']) || !is_array($ret['data'])) { // 返回了errCode和data但类型错误
    Log::error(__FILE__ . ', ' . __LINE__ . ', server return is wrong, ret = ' . var_dump($ret));

    $ret = [
        'errCode' => conErrorCode::ERR_SERVER,
        'data' => []
    ];
}

echo json_encode($ret);
ob_flush();
exit();
