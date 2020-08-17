<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 18-10-19
 * Time: 下午8:24
 */

error_reporting(E_ALL ^ E_NOTICE);

// ob_implicit_flush()将打开或关闭绝对（隐式）刷送。
// 绝对（隐式）刷送将导致在每次输出调用后有一次刷送操作，以便不再需要对 flush() 的显式调用。
ob_implicit_flush();

$sk = new Sock('127.0.0.1', 8000);

// 对创建的socket进行循环监听, 处理数据
$sk->run();

class Sock {
    /**
     * socket的连接池, 即client连接进来的socket标志
     * @var array
     */
    public $sockets;

    /**
     * 所有client连接进来的信息, 包括socket, client名字等
     * @var array
     */
    public $users;

    /**
     * socket的resource, 即前期初始化socket时返回的socket资源
     * @var resource
     */
    public $master;

    /**
     * 已接收的数据
     * @var array
     */
    private $sda = [];

    /**
     * 数据总长度
     * @var array
     */
    private $slen = [];

    /**
     * 接收数据的长度
     * @var array
     */
    private $sjen = [];

    /**
     * 加密key
     * @var array
     */
    private $ar = [];

    private $n = [];

    public function __construct($address, $port) {
        // 创建socket并把socket资源保存在$this->master
        $this->master = $this->createSocket($address, $port);

        // 创建socket连接池
        $this->sockets = [$this->master];
    }

    /**
     * 创建并设置服务端套接字 -  套接字，也称作一个通讯节点。一个典型的网络连接由 2 个套接字构成，一个运行在客户端，另一个运行在服务器端。
     * @param $address
     * @param $port
     * @return resource
     */
    public function createSocket($address, $port) {
        // 创建服务端socket
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        // 1表示接收所有的数据包
        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);

        // 绑定 address 到 socket。 该操作必须是在使用 socket_connect() 或者 socket_listen() 建立一个连接之前。
        socket_bind($server, $address, $port);

        socket_listen($server);

        $this->e('Server Started : ' . date('Y-m-d H:i:s'));
        $this->e('Listening on : ' . $address . ' port ' . $port);

        return $server;
    }

    /**
     * 对创建的socket进行循环监听, 处理数据
     */
    public function run() {
        // 死循环, 直到socket断开
        while (true) {
            $changes = $this->sockets;
            $write = null;
            $except = null;

            /*
             * 这个函数时同时接收多个连接的关键, 我的理解它是为了阻塞程序继续往下执行
             * socket_select($sockets, $write = null, $except = null, null);
             *
             * $sockets可以理解为一个数组, 这个数组中存放的是文件描述符.
             * 当它有变化(就是有新消息或者有客户端连接/断开)时, socket_select函数才会返回, 继续往下执行.
             *
             * $write是监听是否有客户端写数据, 传入null是不关心是否有写变化.
             * $except是$sockets里面要被排除的元素, 传入null是"监听"全部.
             *
             * 最后一个参数是超时时间:
             * 0 - 立即结束
             * n > 1 - 最多在n秒后结束, 如遇某一个连接有新动态, 则提前返回
             * null - 如遇某一个连接有新动态, 则返回
             */
            socket_select($changes, $write, $except, null);
            foreach ($changes as $sock) {
                if ($sock == $this->master) { // 有新的client连接进来
                    // 接收一个socket连接(类型为resource)
                    $client = socket_accept($this->master);

                    // 给新连接进来的socket一个唯一的id
                    $key = uniqid();

                    // 将新连接进来的socket存进连接池
                    $this->sockets[] = $client;

                    $this->users[$key] = [
                        'socket' => $client, // 记录新连接进来client的socket信息
                        'shou' => false // 标志该socket资源没有完成握手
                    ];
                } else { // client断开socket连接或者client发送信息
                    $len = 0;
                    $buffer = '';

                    // 读取该socket的信息. 第二个参数是引用传参即接收数据, 第三个参数是接收数据的长度
                    do {
                        $l = socket_recv($sock, $buf, 1000, 0);
                        $len += $l;
                        $buffer .= $buf;
                    } while ($l == 1000);

                    // 根据socket在user池里面查找相应的$k, 即键id
                    $k = $this->search($sock);

                    // 如果接收的信息长度小于7, 则该client的socket为断开连接
                    if ($len < 7) {
                        // 给该client的socket进行断开操作, 并在$this->sockets和$this->users里面进行删除
                        $this->send2($k);
                        continue;
                    }

                    // 判断该socket是否已经握手
                    if (!$this->users[$k]['shou']) {
                        // 如果没有握手, 则进行握手处理
                        $this->handShake($k, $buffer);
                    } else {
                        // 走到这里就是该client发送信息了, 对接收到的信息进行decode处理
                        $buffer = $this->decode($buffer, $k);
                        if ($buffer == false) {
                            continue;
                        }

                        // 如果不为空, 则进行消息推送操作
                        $this->send($k, $buffer);
                    }
                }
            }
        }
    }

    /**
     * 关闭$k对应的socket
     * @param $k
     */
    public function close($k) {
        // 断开相应socket
        socket_close($this->users[$k]['socket']);

        // 删除相应的user信息
        unset($this->users[$k]);

        // 重新定义sockets连接池
        $this->sockets = [$this->master];
        foreach ($this->users as $v) {
            $this->sockets[] = $v['socket'];
        }

        // 输出日志
        $this->e("key: $k close");
    }

    /**
     * 根据sock在users里面查找相应的$k
     * @param $sock
     * @return bool|int|string
     */
    public function search($sock) {
        foreach ($this->users as $k => $v) {
            if ($sock == $v['socket']) {
                return $k;
            }
            return false;
        }
    }

    /**
     * 对client的请求进行回应, 即握手
     * @param $k - client的socket对应的键, 即每个用户有唯一$k并对应socket
     * @param $buffer - 接收client请求的所有信息
     * @return bool
     */
    public function handShake($k, $buffer) {
        // 截取Sec-WebSocket-Key的值并加密, 其中$key后面的一部分258EAFA5-E914-47DA-95CA-C5AB0DC85B11字符串应该是固定的
        $buf = substr($buffer, strpos($buffer, 'Sec-WebSocket-Key:') + 18);
        $key = trim(substr($buf, 0, strpos($buf, "\r\n")));
        $newKey = base64_encode(sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));

        // 按照协议返回信息
        $newMsg = "HTTP/1.1 101 Switching Protocols\r\n";
        $newMsg .= "Upgrade: websocket\r\n";
        $newMsg .= "Sec-WebSocket-Version\r\n";
        $newMsg .= "Connection: Upgrade\r\n";
        $newMsg .= "Sec-WebSocket-Accept: " . $newKey . "\r\n\r\n";
        socket_write($this->users[$k]['socket'], $newMsg, strlen($newMsg));

        // 对已经握手的client做标志
        $this->users[$k]['shou'] = true;

        return true;
    }

    /**
     * 解码
     * @param $str
     * @param $key
     */
    public function decode($str, $key) {
        $mask = [];
        $data = '';
        $msg = unpack('H*', $str);
        $head = substr($msg[1], 0, 2);
        if ($head == '81' && !isset($this->slen[$key])) {
            $len = substr($msg[1], 2, 2);

            // 把16进制的转换为10进制
            $len = hexdec($len);

            if (substr($msg[1], 2, 2) == 'fe') {
                $len = substr($msg[1], 4, 4);
                $len = hexdec($len);
                $msg[1] = substr($msg[1], 4);
            } else if (substr($msg[1], 2, 2) == 'ff') {
                $len = substr($msg[1], 4, 16);
                $len = hexdec($len);
                $msg[1] = substr($msg[1], 16);
            }
            $mask[] = hexdec(substr($msg[1], 4, 2));
            $mask[] = hexdec(substr($msg[1], 6, 2));
            $mask[] = hexdec(substr($msg[1], 8, 2));
            $mask[] = hexdec(substr($msg[1], 10, 2));
            $s = 12;
            $n = 0;
        } else if ($this->slen[$key] > 0) {
            $len = $this->slen[$key];
            $mask = $this->ar[$key];
            $n = $this->n[$key];
            $s = 0;
        }

        $e = strlen($msg[1]) - 2;
        for ($i = $s; $i <= $e; $i +=2) {
            $data .= chr($mask[$n % 4] ^ hexdec(substr($msg[1], $i, 2)));
            $n++;
        }
        $dlen = strlen($data);

        if ($len > 255 && $len > $dlen + intval($this->sjen[$key])) {
            $this->ar[$key] = $mask;
            $this->slen[$key] = $len;
            $this->sjen[$key] = $dlen + intval($this->sjen[$key]);
            $this->sda[$key] = $this->sda[$key] . $data;
            $this->n[$key] = $n;
            return false;
        } else {
            unset($this->ar[$key], $this->slen[$key], $this->sjen[$key], $this->n[$key]);
            $data = $this->sda[$key] . $data;
            unset($this->sda[$key]);
            return $data;
        }
    }

    /**
     * 编码
     * @param $msg
     * @return string
     */
    public function code($msg) {
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
        $frame[2] = $this->ordHex($msg);
        $data = implode('', $frame);

        // 将数据打包成二进制字符串
        return pack("H*", $data);
    }

    /**
     * 将字符串中的字母依次转换为ascii码, 并从10进制转换为16进制
     * @param $data
     * @return string
     */
    public function ordHex($data) {
        $msg = '';
        $l = strlen($data);
        for ($i = 0; $i < $l; $i++) {
            $msg .= dechex(ord($data{$i}));
        }

        return $msg;
    }

    /**
     * 用户加入或client发送信息
     * @param $k
     * @param $msg
     * @return array
     */
    public function send($k, $msg) {
        // 将查询字符串解析到第二个参数变量中, 以数组的形式保存如: parse_str("name=Bill&age=60", $arr)
        parse_str($msg, $g);
        $ar = [];

        if ($g['type'] === 'add') { // 第一次进入添加聊天名字, 把姓名保存在相应的users里面
            $this->users[$k]['name'] = $g['ming'];
            $ar['type'] = 'add';
            $ar['name'] = $g['ming'];
            $key = 'all';
        } else { // 发送信息行为, 其中$g['key']表示面对大家还是个人, 是前端传过来的信息
            $ar['nrong'] = $g['nr'];
            $key = $g['key'];
        }

        // 推送信息
        $this->send1($k, $ar, $key);
    }

    /**
     * 发送信息
     * @param $k - 发送者的socketId
     * @param $ar
     * @param string $key - 接收者的socketId, 根据这个socketId可以查找相应的client进行消息推送, 即指定client进行发送
     */
    public function send1($k, $ar, $key = 'all') {
        $ar['code1'] = $key;
        $ar['code'] = $k;
        $ar['time'] = date('m-d H:i:s');

        // 对发送信息进行编码处理
        $str = $this->code(json_encode($ar));

        if ($key == 'all') { // 面对大家即所有在线者发送信息
            $users = $this->users;

            if ($ar['type'] == 'add') { // 如果是add表示新加的client
                $ar['type'] = 'madd';
                $ar['users'] = $this->getUsers(); // 取出所有在线者, 用于显示在在线用户列表中
                $str1 = $this->code(json_encode($ar)); // 单独对新client进行编码处理, 数据不一样

                // 对新client自己单独发送, 因为有些数据是不一样的
                socket_write($users[$k]['socket'], $str1, strlen($str1));

                // 上面已经对client自己单独发送的, 后面就无需再次发送, 故unset
                unset($users[$k]);
            }

            // 对 除了新client外 的其他client发送信息. 数据量大时, 就要考虑延时等问题了
            foreach ($users as $v) {
                socket_write($v['socket'], $str, strlen($str));
            }
        } else { // 单独对个人发送信息, 即双方聊天
            socket_write($this->users[$k]['socket'], $str, strlen($str));
            socket_write($this->users[$key]['socket'], $str, strlen($str));
        }
    }

    /**
     * 用户退出向所有client发送消息
     * @param $k
     */
    public function send2($k) {
        $this->close($k);
        $ar['type'] = 'rmove';
        $ar['nrong'] = $k;

        $this->send1(false, $ar, 'all');
    }

    /**
     * 对新加入的client推送已经在线的client
     * @return array
     */
    public function getUsers() {
        $ar = [];
        foreach ($this->users as $k => $v) {
            $ar[] = ['code' => $k, 'name' => $v['name']];
        }

        return $ar;
    }

    /**
     * 记录日志
     * @param $str
     */
    public function e($str) {
        $str = $str . "\n";

        // 对字符串进行编码转换
        echo iconv('utf-8', 'gbk//IGNORE', $str);
    }
}