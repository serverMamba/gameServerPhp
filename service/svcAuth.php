<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 18-10-8
 * Time: 下午5:17
 *
 * 玩家账号
 */

class svcAuth {

    /**
     * 注册
     * @param $param
     * @return array|int
     */
    public function register($param) {
        if (empty($param['account']) || empty($param['password'])) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', invalid param, param = ' . json_encode($param));
            return conErrorCode::ERR_INVALID_PARAM;
        }

        $account = $param['account'];
        $password = $param['password'];

        // account长度检测
        if (strlen($account) > 10) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', account too long, account = ' . json_encode($account));
            return conErrorCode::ERR_ACCOUNT_LONG;
        }

        // password长度检测
        if (strlen($password) > 10) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', account too long, account = ' . json_encode($account));
            return conErrorCode::ERR_ACCOUNT_LONG;
        }

        // todo  account合法性检测, 密码合法性检测, 密码加密

        return clsAuth::register($account, $password);
    }

    /**
     * 登陆
     * @param $param
     * @return int
     */
    public function login($param) {
        if (empty($param['account']) || empty($param['password'])) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', invalid param, param = ' . json_encode($param));
            return conErrorCode::ERR_INVALID_PARAM;
        }

        $account = $param['account'];
        $password = $param['password'];

        return clsAuth::login($account, $password);
    }
}