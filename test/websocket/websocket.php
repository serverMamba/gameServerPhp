<?php

error_reporting(E_ALL);
set_time_limit(0);
date_default_timezone_set('Asia/shanghai');

class WebSocket {
    const LOG_PATH = '/tmp/';
    const LISTEN_SOCKET_NUM = 9;

    const LOG_DEBUG = 1;
    const LOG_ERROR = 2;

    public function __construct($host, $port) {
        try {
            $this->log('ok11');
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
            socket_bind($socket, $host, $port);
            socket_listen($socket, self::LISTEN_SOCKET_NUM);
            $this->log('ok12');
        } catch (Exception $e) {
            $errCode = socket_last_error();
            $errMsg = socket_strerror($errCode);

            $logContent = __METHOD__ . ', ' . __LINE__ . ', errCode = ' . $errCode . ', errMsg = ' . $errMsg;
            $this->log($logContent);
        }

        while (true) {
            try {
                $this->log('ok13');
                // 接收客户端连接
                $acceptResource = socket_accept($socket);
                $this->log('ok14');
                if (false === $acceptResource) {
                    $this->log('socket accept error');
                } else {
                    // 当客户端忽然中断时，服务器会接收到一个 8 字节长度的消息
                    $bytes = socket_recv($acceptResource, $buffer, 2048, 0);
                    $this->log('ok15');
                    if ($bytes < 9) {
                        $this->log('ok16');
                        socket_close($acceptResource);
                    } else { // 握手
                        // 获取客户端升级密钥
                        $line_with_key = substr($buffer, strpos($buffer, 'Sec-WebSocket-Key:') + 18);
                        $key = trim(substr($line_with_key, 0, strpos($line_with_key, "\r\n")));

                        // 生成升级密钥, 并拼接WebSocket升级头
                        $upgradeKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
                        $upgradeMsg = "HTTP/1.1 101 Switching Protocols\r\n";
                        $upgradeMsg .= "Upgrade: websocket\r\n";
                        $upgradeMsg .= "Sec-WebSocket-Version: 13\r\n";
                        $upgradeMsg .= "Connection: Upgrade\r\n";
                        $upgradeMsg .= "Sec-WebSocket-Accept:" . $upgradeKey . "\r\n\r\n";

                        // 向socket写入升级信息
                        $this->log('ok17');
                        socket_write($acceptResource, $upgradeMsg, strlen($upgradeMsg));
                        $this->log('ok18');
                    }
                }
            } catch (Exception $e) {
                $this->log($e->getMessage());
            }
        }
    }

    public function log($content, $logType = 1) {
        $time = date('Y-m-d H:i:s');
        $content = $time . ' : ' . $content;

        $fileName = '';
        switch ($logType) {
            case self::LOG_DEBUG:
                $fileName = 'websocket_error';
                break;
            case self::LOG_ERROR:
                $fileName = 'websocket_debug';
                break;
        }
        file_put_contents(self::LOG_PATH . $fileName, $content . "\r\n", FILE_APPEND);
    }
}

$ws = new WebSocket('127.0.0.1', 8080);