<?php
error_reporting(E_ALL);
set_time_limit(0);
date_default_timezone_set('Asia/shanghai');

class clsWebSocket {
    const LOG_PATH = '/tmp/';
    const LISTEN_SOCKET_NUM = 2;

    private $sockets = [];
    private $master;

    public function __construct($host, $port) {
        try {
            // 创建socket
            $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            // 设置ip和端口重用， 在重启服务器后能重新使用此端口
            socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1);
            // 绑定ip和端口
            socket_bind($this->master, $host, $port);
            // 监听连接
            socket_listen($this->master, self::LISTEN_SOCKET_NUM);
        } catch (Exception $e) {
            $err_code = socket_last_error();
            $err_msg = socket_strerror($err_code);

            $this->error([
                'err_init_server',
                $err_code,
                $err_msg
            ]);
        }
        $this->sockets[0] = ['resource' => $this->master];
        $pid = posix_getpid();
        $this->debug(["server: {$this->master} started, pid: {$pid}"]);

        while (true) {
            try {
                $this->doServer();
            } catch (Exception $e) {
                $this->error([
                    'err_do_server',
                    $e->getCode(),
                    $e->getMessage()
                ]);
            }
        }
    }

    private function doServer() {
        $write = $except = null;
        $sockets = array_column($this->sockets, 'resource');

        // select 函数使用传统的 select 模型，可读、写、异常的 socket 会被分别放入 $socket, $write, $except 数组中,
        // 然后返回 状态改变的 socket 的数目，如果发生了错误，函数将会返回 false.
        // 需要注意的是最后两个时间参数，它们只有单位不同，可以搭配使用，用来表示 socket_select 阻塞的时长，
        // 为0时此函数立即返回，可以用于轮询机制。 为 NULL 时，函数会一直阻塞下去,
        // 这里我们置 $tv_sec 参数为null，让它一直阻塞，直到有可操作的 socket 返回
        $read_num = socket_select($sockets, $write, $except, null);
        if (false === $read_num) {
            $this->error([
                'error_select',
                $err_code = socket_last_error(),
                socket_strerror($err_code)
            ]);
            return;
        }

        foreach ($sockets as $socket) {
            // 如果可读的是服务器socket， 则处理连接逻辑
            if ($socket == $this->master) {
                // 创建， 绑定， 监听后， accept函数将会接受socket要来的连接，
                // 一旦有一个连接成功， 将会返回一个新的socket资源用以交互，
                // 如果是一个多个连接的队列， 只会处理第一个，
                // 如果没有连接的话， 进程将会被阻塞， 直到连接上
                // 如果用set_socket_blocking或socket_set_noblock设置了阻塞, 会返回false
                // 返回资源后， 将会持续等待连接
                $client = socket_accept($this->master);
                if (false == $client) {
                    $this->error([
                        'err_accept',
                        $err_code = socket_last_error(),
                        socket_strerror($err_code)
                    ]);
                    continue;
                } else {
                    self::connect($client);
                    continue;
                }
            } else { // 如果可读的是其他已连接socket， 则读取其数据， 并处理应答逻辑
                $bytes = @socket_recv($socket, $buffer, 2048, 0);
                // 当客户端忽然中断时，服务器会接收到一个 8 字节长度的消息
                //（由于其数据帧机制，8字节的消息我们认为它是客户端异常中断消息），服务器处理下线逻辑，并将其封装为消息广播出去
                if ($bytes < 9) {
                    $recv_msg = $this->disconnect($socket);
                } else {
                    // 如果此客户端还未握手，执行握手逻辑
                    if (!$this->sockets[(int)$socket]['handshake']) {
                        self::handShake($socket, $buffer);
                        continue;
                    } else {
                        $recv_msg = self::parse($buffer);
                    }
                }
                // 在数组开头插入一个或多个单元
                array_unshift($recv_msg, 'receive_msg');
                $msg = self::dealMsg($socket, $recv_msg);

                $this->broadcast($msg);
            }
        }
    }

    /**
     * 将socket添加到已连接列表， 但握手状态留空
     * @param $socket
     */
    public function connect($socket) {
        // 获取socket的ip和端口， 存入ip和port
        socket_getpeername($socket, $ip, $port);
        $socket_info = [
            'resource' => $socket,
            'uname' => '',
            'handshake' => false,
            'ip' => $ip,
            'port' => $port,
        ];
        $this->sockets[(int)$socket] = $socket_info;
        $this->debug(array_merge(['socket_connect'], $socket_info));
    }

    /**
     * 客户端关闭连接
     * @param $socket
     *
     * @return array
     */
    private function disconnect($socket) {
        $recv_msg = [
            'type' => 'logout',
            'content' => $this->sockets[(int)$socket]['uname'],
        ];
        unset($this->sockets[(int)$socket]);

        return $recv_msg;
    }

    /**
     * 用公共握手算法握手
     * @param $socket
     * @param $buffer
     *
     * @return bool
     */
    public function handShake($socket, $buffer) {
        // 获取到客户端的升级密钥
        $line_with_key = substr($buffer, strpos($buffer, 'Sec-WebSocket-Key:') + 18);
        $key = trim(substr($line_with_key, 0, strpos($line_with_key, "\r\n")));

        // 生成升级密钥， 并拼接websocket升级头
        $upgradeKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)); // 升级key的算法
        $upgradeMsg = "HTTP/1.1 101 Switching Protocols\r\n";
        $upgradeMsg .= "Upgrade: websocket\r\n";
        $upgradeMsg .= "Sec-WebSocket-Version: 13\r\n";
        $upgradeMsg .= "Connection: Upgrade\r\n";
        $upgradeMsg .= "Sec-WebSocket-Accept:" . $upgradeKey . "\r\n\r\n";

        // 向socket写入升级信息
        socket_write($socket, $upgradeMsg, strlen($upgradeMsg));
        $this->sockets[(int)$socket]['handshake'] = true;

        socket_getpeername($socket, $ip, $port);
        $this->debug([
            'hand_shake',
            $socket,
            $ip,
            $port
        ]);

        // 向客户端发送握手成功消息， 以触发客户端发送用户名动作
        $msg = [
            'type' => 'handshake',
            'content' => 'done',
        ];
        $msg = $this->build(json_encode($msg));

        socket_write($socket, $msg, strlen($msg));

        return true;
    }

    /**
     * 广播消息
     * @param $data
     */
    public function broadcast($data) {
        foreach ($this->sockets as $socket) {
            if ($socket['resource'] == $this->master) {
                continue;
            }
            socket_write($socket['resource'], $data, strlen($data));
        }
    }

    // ==

    /**
     * 解析数据
     *
     * @param $buffer
     *
     * @return bool|string
     */
    private function parse($buffer) {
        $decoded = '';
        $len = ord($buffer[1]) & 127;
        if ($len === 126) {
            $masks = substr($buffer, 4, 4);
            $data = substr($buffer, 8);
        } else if ($len === 127) {
            $masks = substr($buffer, 10, 4);
            $data = substr($buffer, 14);
        } else {
            $masks = substr($buffer, 2, 4);
            $data = substr($buffer, 6);
        }
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }
        return json_decode($decoded, true);
    }
    /**
     * 将普通信息组装成websocket数据帧
     *
     * @param $msg
     *
     * @return string
     */
    private function build($msg) {
        $frame = [];
        $frame[0] = '81';
        $len = strlen($msg);
        if ($len < 126) {
            $frame[1] = $len < 16 ? '0' . dechex($len) : dechex($len);
        } else if ($len < 65025) {
            $s = dechex($len);
            $frame[1] = '7e' . str_repeat('0', 4 - strlen($s)) . $s;
        } else {
            $s = dechex($len);
            $frame[1] = '7f' . str_repeat('0', 16 - strlen($s)) . $s;
        }
        $data = '';
        $l = strlen($msg);
        for ($i = 0; $i < $l; $i++) {
            $data .= dechex(ord($msg{$i}));
        }
        $frame[2] = $data;
        $data = implode('', $frame);
        return pack("H*", $data);
    }
    /**
     * 拼装信息
     *
     * @param $socket
     * @param $recv_msg
     *          [
     *          'type'=>user/login
     *          'content'=>content
     *          ]
     *
     * @return string
     */
    private function dealMsg($socket, $recv_msg) {
        $msg_type = $recv_msg['type'];
        $msg_content = $recv_msg['content'];
        $response = [];
        switch ($msg_type) {
            case 'login':
                $this->sockets[(int)$socket]['uname'] = $msg_content;
                // 取得最新的名字记录
                $user_list = array_column($this->sockets, 'uname');
                $response['type'] = 'login';
                $response['content'] = $msg_content;
                $response['user_list'] = $user_list;
                break;
            case 'logout':
                $user_list = array_column($this->sockets, 'uname');
                $response['type'] = 'logout';
                $response['content'] = $msg_content;
                $response['user_list'] = $user_list;
                break;
            case 'user':
                $uname = $this->sockets[(int)$socket]['uname'];
                $response['type'] = 'user';
                $response['from'] = $uname;
                $response['content'] = $msg_content;
                break;
        }
        return $this->build(json_encode($response));
    }

    /**
     * 记录debug信息
     *
     * @param array $info
     */
    private function debug(array $info) {
        $time = date('Y-m-d H:i:s');
        array_unshift($info, $time);

        // 为数组的每个元素应用回调函数
        $info = array_map('json_encode', $info);
        // implode - 将一个一维数组的值转化为字符串
        file_put_contents(self::LOG_PATH . 'websocket_debug.log',
            implode(' | ', $info) . "\r\n", FILE_APPEND);
    }

    private function error(array $info) {
        $time = date('Y-m-d H:i:s');
        array_unshift($info, $time);

        $info = array_map('json_encode', $info);
        file_put_contents(self::LOG_PATH . 'websocket_error.log',
            implode(' | ', $info) . "\r\n", FILE_APPEND);
    }

    private function log($content) {
        $time = date('Y-m-d H:i:s');
        $file = self::LOG_PATH . 'websocket.log';
        file_put_contents($file, $content . "\n", FILE_APPEND);
    }
}

$ws = new clsWebSocket('127.0.0.1', 8080);