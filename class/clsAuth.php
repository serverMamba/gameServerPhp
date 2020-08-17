<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 18-10-8
 * Time: 下午5:40
 */

class clsAuth {

    /**
     * 注册
     * @param $account
     * @param $password
     * @return array|int
     */
    public static function register($account, $password) {
        // 检测account是否存在
        $accountCheckRet = daoAuth::checkAccountExist($account, $password);
        if ($accountCheckRet !== false) {
            Log::warn(__METHOD__ . ', ' . __LINE__ . ', accountCheckRet = ' . json_encode($accountCheckRet));
            return conErrorCode::ERR_ACCOUNT_EXIST;
        }

        // 创建账号
        $aid = daoAuth::createAuth($account, $password);
        if ($aid <= 0) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', register fail');
            return conErrorCode::ERR_REGISTER_FAIL;
        }

        return [
            'errCode' => conErrorCode::ERR_OK,
            'data' => $aid
        ];
    }

    /**
     * 登陆
     * @param $account
     * @param $password
     * @return int
     */
    public static function login($account, $password) {
        // 账号是否存在
        $accountCheckRet = daoAuth::checkAccountExist($account, $password);
        if ($accountCheckRet !== true) {
            Log::warn(__METHOD__ . ', ' . __LINE__ . ', account not exist, account = ' . $account
                . ', accountCheckRet = ' . json_encode($accountCheckRet));
            return conErrorCode::ERR_ACCOUNT_NOT_EXIST;
        }

        // 密码是否正确
        $passwordCheckRet = daoAuth::checkPassword($account, $password);
        if ($passwordCheckRet !== true) {
            Log::warn(__METHOD__ . ', ' . __LINE__ . ', password wrong, account = ' . $account
                . ', password = ' . $password
                . ', accountCheckRet = ' . json_encode($accountCheckRet));
            return conErrorCode::ERR_ACCOUNT_PASSWORD_WRONG;
        }

        return conErrorCode::ERR_OK;
    }
}