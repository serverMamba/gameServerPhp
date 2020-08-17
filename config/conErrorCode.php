<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 18-10-7
 * Time: 下午4:36
 */
class conErrorCode {
    const ERR_OK = 0; // 接口请求成功
    const ERR_SERVER = 1; // 服务端错误
    const ERR_CLIENT = 2; // 客户端错误

    const ERR_INVALID_PARAM = 3; // 参数错误

    // mysql
    const ERR_MYSQL_CONNECT_FAIL = 100; // mysql连接失败
    const ERR_MYSQL_EXCEPTION = 101; // mysql异常

    // redis
    const ERR_REDIS_CONNECT_FAIL = 200; // redis连接失败

    // 账号
    const ERR_ACCOUNT_LONG = 300; // 账号不能超过10个字符
    const ERR_PASSWORD_LONG = 301; // 密码不能超过10个字符
    const ERR_REGISTER_FAIL = 302; // 服务器错误, 账号注册失败
    const ERR_ACCOUNT_EXIST = 303; // 账号已存在
    const ERR_ACCOUNT_NOT_EXIST = 304; // 账号不存在
    const ERR_ACCOUNT_PASSWORD_WRONG = 305; // 密码错误

    // 角色
    const ERR_ROLE_CREATE_FAIL = 400; // 创建角色失败
    const ERR_ROLE_EXIST = 401; // 该账号在该服务已有角色
    const ERR_ROLE_NAME_EXIST = 402; // 角色名已存在
    const ERR_ROLE_GET_FAIL = 403; // 获取角色信息失败
    const ERR_ROLE_ADD_EXP_FAIL = 404; // 增加角色经验失败
    const ERR_ROLE_NAME_TOO_LONG = 405; // 角色名太长


}