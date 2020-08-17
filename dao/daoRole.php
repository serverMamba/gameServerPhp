<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 18-10-11
 * Time: 下午2:42
 */

class daoRole {

    /**
     * 检测该账号在该服是否已存在角色
     * @param $accountId
     * @return bool|int - true存在, false不存在, int错误码
     */
    public static function checkRoleExist($accountId) {
        // 从redis获取
        $redis = clsRedis::getInstance();
        if (null === $redis) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', redis connect fail');
            return conErrorCode::ERR_REDIS_CONNECT_FAIL;
        }

        if ($redis->exists(conRedisKey::auth_role . $accountId)) {
            return true;
        }

        // 从mysql获取
        $pdo = clsMysql::getInstance();
        if (null === $pdo) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', mysql connect fail');
            return conErrorCode::ERR_MYSQL_CONNECT_FAIL;
        }

        try {
            $sql = 'select role_id from role where account_id = :account_id limit 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':account_id' => $accountId
            ]);
            $roleId = $stmt->fetchColumn();
        } catch (PDOException $e) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', mysql exception: ' . $e->getMessage());
            return conErrorCode::ERR_MYSQL_EXCEPTION;
        }

        if (empty($roleId)) {
            return false;
        }

        // 更新redis
        $roleId = intval($roleId);
        $redis->set(conRedisKey::auth_role . $accountId, $roleId, conConstant::default_redis_expire_time);

        return true;
    }

    /**
     * 角色名是否在该服已存在
     * @param $roleName
     * @return bool|int
     */
    public static function checkRoleNameExist($roleName) {
        // 从redis获取
        $redis = clsRedis::getInstance();
        if (null === $redis) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', redis connect fail');
            return conErrorCode::ERR_REDIS_CONNECT_FAIL;
        }

        if ($redis->exists(conRedisKey::role_name . $roleName)) {
            return true;
        }

        // 从mysql获取
        $pdo = clsMysql::getInstance();
        if (null === $pdo) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', mysql connect fail');
            return conErrorCode::ERR_MYSQL_CONNECT_FAIL;
        }
        try {
            $sql = 'select role_id from role where name = :roleName';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':roleName' => $roleName
            ]);
            $roleId = $stmt->fetchColumn();
        } catch (PDOException $e) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', mysql exception: ' . $e->getMessage());
            return conErrorCode::ERR_MYSQL_EXCEPTION;
        }

        if (empty($roleId)) {
            return false;
        }

        // 更新redis
        $roleId = intval($roleId);
        $redis->set(conRedisKey::role_name . $roleName, $roleId, conConstant::default_redis_expire_time);

        return true;
    }

    /**
     * 获取角色信息
     * @param $roleId
     * @return array|null
     */
    public static function get($roleId) {
        $pdo = clsMysql::getInstance();
        if (null === $pdo) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', mysql connect fail');
            return null;
        }

        try {
            $sql = 'select account_id, name, lv, exp, gold, silver from role where id = :roleId';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':roleId' => $roleId
            ]);
            $row = $stmt->fetch();
        } catch (PDOException $e) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', mysql exception: ' . $e->getMessage());
            return null;
        }

        $ret = [];
        foreach ($row as $v) {
            $ret['accountId'] = intval($v['account_id']);
            $ret['name'] = $row['name'];
            $ret['lv'] = intval($row['lv']);
            $ret['exp'] = intval($row['exp']);
            $ret['gold'] = intval($row['gold']);
            $ret['silver'] = intval($row['silver']);
        }

        return $ret;
    }

    // todo now
    public static function createRole($accountId, $roleName) {

        return 1;
    }
}