<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 18-10-9
 * Time: 下午4:42
 */

class daoAuth {

    /**
     * 账号是否存在
     * @param $account
     * @param $password
     * @return bool|int - true存在, false不存在, int错误码
     */
    public static function checkAccountExist($account, $password) {
        // 从redis获取
        $redis = clsRedis::getInstance();
        if (null === $redis) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', redis connect fail');
            return conErrorCode::ERR_REDIS_CONNECT_FAIL;
        }
        if ($redis->exists(conRedisKey::auth_register_account . $account)) {
            Log::info(__METHOD__ . ', ' . __LINE__ . ', account already exist');
            return true;
        }

        // 从mysql获取
        $pdo = clsMysql::getInstance();
        if (null === $pdo) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', mysql connect fail');
            return conErrorCode::ERR_MYSQL_CONNECT_FAIL;
        }

        try {
            $sql = 'select aid, account, password from auth where account = :account limit 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':account' => $account
            ]);
            $row = $stmt->fetch();
        } catch (PDOException $e) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', mysql exception: ' . $e->getMessage());
            return conErrorCode::ERR_MYSQL_EXCEPTION;
        }

        if (empty($row)) { // todo now： 如果查不到返回什么, 查不到是否触发exception
            return false;
        }

        // 更新redis
        $aid = intval($row['aid']);
        $password = $row['password'];

        $redis->hMSet(conRedisKey::auth_register_aid . $aid, [
            'account' => $account,
            'password' => $password
        ]);
        $redis->expire(conRedisKey::auth_register_aid, conConstant::default_redis_expire_time);

        $redis->hMSet(conRedisKey::auth_register_account . $account, [
            'aid' => $aid,
            'password' => $password
        ]);
        $redis->expire(conRedisKey::auth_register_account, conConstant::default_redis_expire_time);

        Log::info(__METHOD__ . ', ' . __LINE__ . ', account already exist');
        return true;
    }

    /**
     * 创建账号
     * @param $account
     * @param $password
     * @return int|null
     */
    public static function createAuth($account, $password) {
        $pdo = clsMysql::getInstance();
        if (null === $pdo) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', mysql connect fail');
            return null;
        }

        $redis = clsRedis::getInstance();
        if (null == $redis) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', redis connect fail');
            return null;
        }

        // 更新mysql
        try {
            $sql = 'insert into auth(account, password) values (:account, :password)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':account' => $account,
                ':password' => $password
            ]);
            $aid = intval($pdo->lastInsertId());
        } catch (PDOException $e) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', mysql exception: ' . $e->getMessage());
            return null;
        }

        // 更新redis
        $redis->hMSet(conRedisKey::auth_register_aid . $aid, [
            'account' => $account,
            'password' => $password
        ]);
        $redis->expire(conRedisKey::auth_register_aid, conConstant::default_redis_expire_time);

        $redis->hMSet(conRedisKey::auth_register_account . $account, [
            'aid' => $aid,
            'password' => $password
        ]);
        $redis->expire(conRedisKey::auth_register_account, conConstant::default_redis_expire_time);


        return $aid;
    }

    /**
     * 登陆时检测密码是否正确
     * @param $account
     * @param $password
     * @return bool|int - true正确, false错误, int错误码
     */
    public static function checkPassword($account, $password) {
        // 从redis获取
        $redis = clsRedis::getInstance();
        if (null === $redis) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', redis connect fail');
            return conErrorCode::ERR_REDIS_CONNECT_FAIL;
        }
        $savedPass = $redis->hGet(conRedisKey::auth_register_account . $account, 'password');
        if ($savedPass) {
            // 判断密码是否正确
            if ($savedPass === $password) {
                return true;
            } else {
                Log::error(__METHOD__ . ', ' . __LINE__ . ', password wrong, password = '
                    . $password . ', savedPass = ' . $savedPass);
                return false;
            }
        }

        // 从mysql获取
        $pdo = clsMysql::getInstance();
        if (null === $pdo) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', mysql connect fail');
            return conErrorCode::ERR_MYSQL_CONNECT_FAIL;
        }

        try {
            $sql = 'select aid, account, password from auth where account = :account limit 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':account' => $account
            ]);
            $row = $stmt->fetch();
        } catch (PDOException $e) {
            Log::error(__METHOD__ . ', ' . __LINE__ . ', mysql exception: ' . $e->getMessage());
            return conErrorCode::ERR_MYSQL_EXCEPTION;
        }

        if (empty($row)) { // todo： 返回值到底是什么
            return false;
        }

        // 更新redis
        $aid = intval($row['aid']);
        $savedPass = $row['password'];
        $redis->hMSet(conRedisKey::auth_register_aid . $aid, [
            'account' => $account,
            'password' => $password
        ]);
        $redis->expire(conRedisKey::auth_register_aid, conConstant::default_redis_expire_time);

        $redis->hMSet(conRedisKey::auth_register_account . $account, [
            'aid' => $aid,
            'password' => $password
        ]);
        $redis->expire(conRedisKey::auth_register_account, conConstant::default_redis_expire_time);

        // 判断密码是否正确
        if ($savedPass === $password) {
            return true;
        }

        Log::error(__METHOD__ . ', ' . __LINE__ . ', password wrong, password = '
            . $password . ', savedPass = ' . $savedPass);
        return false;
    }
}