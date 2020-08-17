<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 18-10-8
 * Time: 上午10:33
 *
 * http
 */
class Http {
    // todo 保证返回类型是[]
    public static function curlPost($url, $param) {
        $ch = curl_init();

        // 设置 HTTP 头字段的数组。格式： array('Content-type: text/plain', 'Content-length: 100')
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept:text/plain;charset=utf-8',
            'Content-Type:application/x-www-form-urlencoded',
            'charset=utf-8']);

        // 设置 获取的信息以文件流的形式返回, 而不是直接输出
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // 允许 cURL 函数执行的最长秒数。
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // 设置 通信方式
        curl_setopt($ch, CURLOPT_POST, 1);

        // 禁止 cURL 验证对等证书（peer's certificate）
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // 需要获取的 URL 地址，也可以在curl_init() 初始化会话的时候
        curl_setopt($ch, CURLOPT_URL, $url);

        // 全部数据使用HTTP协议中的 "POST" 操作来发送。
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));

        // 执行 cURL 会话
        $ret = curl_exec($ch);

        // 关闭 cURL 会话并且释放所有资源。cURL 句柄 ch 也会被删除。
        curl_close($ch);

        return $ret;
    }
}