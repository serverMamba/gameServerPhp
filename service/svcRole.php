<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 18-10-10
 * Time: 上午2:48
 */

class svcRole {
    /**
     * 创建角色
     * @param $param
     * @return int
     */
    public function create($param) {
        if (!isset($param['accountId']) || !isset($param['roleName'])) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', invalid param, param = ' . json_encode($param));
            return conErrorCode::ERR_ROLE_CREATE_FAIL;
        }

        $accountId = intval($param['accountId']);
        $roleName = $param['roleName'];

        return clsRole::create($accountId, $roleName);
    }

    /**
     * 获取角色信息
     * @param $param
     * @return array|int|null
     */
    public function get($param) {
        if (!isset($param['roleId'])) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', invalid param, param = ' . json_encode($param));
            return conErrorCode::ERR_ROLE_GET_FAIL;
        }

        $roleId = intval($param['roleId']);

        return clsRole::get($roleId);
    }

    // 增加角色经验
    public function addExp($param) {
        if (!isset($param['roleId']) || !isset($param['expAdd'])) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', invalid param, param = ' . json_encode($param));
            return conErrorCode::ERR_ROLE_ADD_EXP_FAIL;
        }

        $roleId = intval($param['roleId']);
        $expAdd = intval($param['expAdd']);

        return clsRole::addExp($roleId, $expAdd);
    }
}