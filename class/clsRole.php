<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 18-10-10
 * Time: 上午3:01
 */

class clsRole {
    /**
     * 创建角色
     * @param $accountId
     * @param $roleName
     * @return array|int
     */
    public static function create($accountId, $roleName) {
        // 角色名合法性检测 todo

        // 角色名长度检测
        if (strlen($roleName) > 10) {
            Log::warn(__METHOD__ . ', ' . __LINE__ . ', roleName too long, accountId = ' . $accountId
                . 'roleName = ' . $roleName);
            return conErrorCode::ERR_ROLE_NAME_TOO_LONG;
        }

        // 该账号在该服是否已存在角色
        $checkRoleExist = daoRole::checkRoleExist($accountId);
        if ($checkRoleExist !== false) {
            Log::warn(__METHOD__ . ', ' . __LINE__ . ', role already exist, accountId = ' . $accountId);
            return conErrorCode::ERR_ROLE_EXIST;
        }

        // 角色名是否在该服已存在
        $checkRoleNameExist = daoRole::checkRoleNameExist($roleName);
        if ($checkRoleNameExist !== false) {
            Log::warn(__METHOD__ . ', ' . __LINE__ . ', roleName already exist, accountId = ' . $accountId
                . ', roleName = ' . $roleName);
            return conErrorCode::ERR_ROLE_NAME_EXIST;
        }

        // 创建角色
        $roleId = daoRole::createRole($accountId, $roleName);

        return [
            'roleId' => $roleId,
            'name' => $roleName,
            'lv' => 1,
            'exp' => 0,
            'gold' => 0,
            'silver' => 0
        ];
    }

    /**
     * 获取角色信息
     * @param $roleId
     * @return array|null
     */
    public static function get($roleId) {
        return daoRole::get($roleId);
    }

    // 增加角色经验
    public static function addExp($roleId, $ExpAdd) {

    }
}