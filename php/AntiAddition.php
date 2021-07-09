<?php

require_once "AESGCM.php";

AntiAddition::testCase();

class AntiAddition
{
    private static $_secreKey = '**';
    private static $_appId = '**';
    private static $_bizId = '**';

    public static function testCase(){
        self::checkIdentity('某一一', '110000190101010001', '100000000000000001', 'CUkHCa');
        self::checkIdentity('某一二', '110000190101020007', '100000000000000002', '5DAXKs');
        self::checkIdentity('某一三', '110000190101030002', '100000000000000003', '5DAXKs');

        self::queryIdentity('100000000000000001', '9a4YVx');
        self::queryIdentity('200000000000000001', 'htRquS');
        self::queryIdentity('300000000000000001', 'x9jgDb');

        // 测试数据上报接口的时候，注意 ct （isGuest）参数要分别设置为0, 1，不然测试的时候，只有一个会通过. pi 值都要不同的
        self::report(self::testCreateBehaviorData1(), 'XmexPq');
        self::report(self::testCreateBehaviorData2(), 'fjsTqG');
    }

    /**
     * 身份认证.
     *
     * 接口调用地址：https://api.wlc.nppa.gov.cn/idcard/authentication/check
     * 接口请求方式：POST
     * 接口理论响应时间：300ms
     * 报文超时时间（TIMESTAMPS）：5s
     * 客户端接口超时时间（建议）：5s
     * 接口限流：100 QPS（超出后会被限流 1 分钟
     *
     * @param string $name  玩家姓名
     * @param string $idNum  玩家身份证
     * @param int    $ai    自定义标识id
     * @param string $testCode 测试码
     */
    public static function checkIdentity($name, $idNum, $ai, $testCode = '')
    {
        $postParams = array(
            'ai' => $ai,
            'name' => $name,
            'idNum' => $idNum
        );
        $url = 'https://api.wlc.nppa.gov.cn/idcard/authentication/check';
        if (!empty($testCode)){
            $url = "https://wlc.nppa.gov.cn/test/authentication/check/$testCode";
        }
        $response = self::curl('POST', $url, [], $postParams);
        if (!empty($response['data']['result']['status'])){
            $status = $response['data']['result']['status'];
            if ($status == 0) {
                return $response['data']['result']['pi'];
            } elseif ($status == 1) {
                return -1;
            } elseif ($status == 2) {
                return -2;
            }
        }
        return -3;
    }

    /**
     * 身份证查询.
     *
     * 接口调用地址：http://api2.wlc.nppa.gov.cn/idcard/authentication/query
     * 接口请求方式：GET
     * 接口理论响应时间：300ms
     * 报文超时时间（TIMESTAMPS）：5s
     * 客户端接口超时时间（建议）：5s
     * 接口限流：300 QPS（超出后会被限流 1 分钟
     *
     * @param int    $ai    自定义标识id
     * @param string $testCode 测试码
     */
    public static function queryIdentity($ai, $testCode = '')
    {
        // 查询参数
        $queryParams = array(
            'ai' => $ai,
        );
        $url = 'http://api2.wlc.nppa.gov.cn/idcard/authentication/query/';
        if (!empty($testCode)){
            $url = "https://wlc.nppa.gov.cn/test/authentication/query/$testCode";
        }
        $response = self::curl('GET', $url, $queryParams, []);
        if (!empty($response['data']['result']['status'])){
            $status = $response['data']['result']['status'];
            if ($status == 0) {
                return $response['data']['result']['pi'];
            } elseif ($status == 1) {
                return -1;
            } elseif ($status == 2) {
                return -2;
            }
        }
        return -3;
    }

    /**
     * 数据上报.
     *
     * 接口调用地址：http://api2.wlc.nppa.gov.cn/behavior/collection/loginout
     * 接口请求方式：POST
     * 接口理论响应时间：300ms
     * 报文超时时间（TIMESTAMPS）：5s
     * 客户端接口超时时间（建议）：5s
     * 接口限流：10 QPS（超出后会被限流 1 分钟）
     *
     * @param array $collections
     * @param string $testCode
     */
    public static function report($collections, $testCode = '')
    {
        $postParams = array(
            'collections' => $collections
        );
        $url = 'http://api2.wlc.nppa.gov.cn/behavior/collection/loginout';
        if (!empty($testCode)){
            $url = "https://wlc.nppa.gov.cn/test/collection/loginout/$testCode";
        }
        $response = self::curl('POST', $url, [], $postParams);
        if (!empty($response['errcode'])){
            return 0;
        }
        return -1;
    }

    /**
     * @param int $objId  在批量模式中标识一条行为数据，取值范围 1-128
     * @param string $sessionId
     * <pre>
     * 一个会话标识只能对应唯一的
     * 实名用户，一个实名用户可以
     * 拥有多个会话标识；同一用户
     * 单次游戏会话中，上下线动作
     * 必须使用同一会话标识上报
     * 备注：会话标识仅标识一次用
     * 户会话，生命周期仅为一次上
     * 线和与之匹配的一次下线，不
     * 会对生命周期之外的任何业务
     * 有任何影响
     * </pre>
     * @param int $behaviorId   游戏用户行为类型  0：下线 1：上线
     * @param long $timestamp 行为发生时间戳，单位秒
     * @param int $isGuest  用户行为数据上报类型 0：已认证通过用户 2：游客用户
     * @param string $deviceId  游客模式设备标识，由游戏运营单位生成，游客用户下必填
     * @param string $identityId  已通过实名认证用户的唯一标识，已认证通过用户必填
     */
    public static function createBehaviorObject($objId, $sessionId, $behaviorId, $timestamp, $isGuest, $deviceId, $identityId){
        return array(
            'no' => $objId,
            'si' => $sessionId,
            'bt' => $behaviorId,
            'ot' => $timestamp,
            'ct' => $isGuest,
            'di' => $deviceId,
            'pi' => $identityId
        );
    }

    public static function testCreateBehaviorData1(){
        $list = array();
        $list[] = self::createBehaviorObject(1, str_repeat(1,32), 1, time() - 10, 2, 'lpl', '1fffbjzos82bs9cnyj1dna7d6d29zg4esnh99u');
        $list[] = self::createBehaviorObject(2, str_repeat(1, 32), 0, time(), 2, 'lpl', '1fffbkmd9ebtwi7u7f4oswm9li6twjydqs7qjv');
        return $list;
    }

    public static function testCreateBehaviorData2(){
        $list = array();
        $list[] = self::createBehaviorObject(1, str_repeat(2,32), 1, time() - 10, 0, 'lpl1', '1fffbmzwmr1k3y8bri2linqbhnvmu510u5jj6z');
        return $list;
    }

    /**
     * @param $method
     * @param $url
     * @param $queryParams
     * @param $postParams
     * @return bool|string
     */
    private static function curl($method, $url, $queryParams, $postParams)
    {
        if (empty($method)){
            // 请求方法
            $method = 'POST';
        }
        if (count($queryParams) > 0) {
            $url .= '?' . http_build_query($queryParams);
        }
        $bodyParams = array();
        if (!empty($postParams)){
            // body参数（POST方法下）
            $bodyParams = array(
                'data' => self::aesGcmEncrypt($postParams)
            );
        }
        $subHeaders = array(
            'appId' => self::$_appId,
            'bizId' => self::$_bizId,
            'timestamps' => self::microsecond()
        );
        // 签名
        $sign = self::sha256Params($subHeaders, $queryParams, $bodyParams);
        // 请求头
        $headers = array_merge(array(
            'Content-type' => 'application/json; charset=utf-8',
            'sign' => $sign,
        ), $subHeaders);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($v, $k) {
            return $k . ': ' . $v;
        }, array_values($headers), array_keys($headers)));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        // https请求 不验证证书和hosts
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        // 从证书中检查SSL加密算法是否存在(默认不需要验证）
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (in_array($method, array('POST', 'PUT', 'PATCH'), true)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($bodyParams));
        }
        $data = curl_exec($ch);
         if (curl_errno($ch)) {
            echo "Error: " . curl_error($ch);
            curl_close($ch);
            return false;
        } else {
            curl_close($ch);
            var_dump($data);
            return json_decode($data, true);
        }
    }

    /**
     * 参数签名
     * @param $sub_headers
     * @param $queryParams
     * @param $bodyParams
     * @return string
     */
    private static function sha256Params($sub_headers, $queryParams, $bodyParams)
    {
        $merge_params = array_merge($sub_headers, $queryParams);
        ksort($merge_params);
        $str = '';
        foreach ($merge_params as $key => $value) {
            $str .= "{$key}{$value}";
        }
        if (!empty($bodyParams)) {
            $str .= json_encode($bodyParams);
        }
        $str = self::$_secreKey . $str;
        return hash("sha256", $str);
    }

    /**
     * //获取毫秒时间
     * @return long
     */
    private static function microsecond()
    {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }

    /**
     * ASE-128-GCM 加密
     * @param $data
     * @return string
     */
    public static function aesGcmEncrypt($data)
    {
        if (is_array($data)){
            $data = json_encode($data, JSON_UNESCAPED_SLASHES);
            echo "请求body: $data\n";
        }
        $cipher = strtolower('AES-128-GCM');
        //二进制key
        $skey = hex2bin(self::$_secreKey);
        //二进制iv
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
        //如果环境是php7.1+,直接使用下面的方式
        if (version_compare(PHP_VERSION, '7.1') >= 0) {
            $tag = NULL;
            $content = openssl_encrypt($data, $cipher, $skey,OPENSSL_RAW_DATA,$iv,$tag);
        } else {
            list($content, $tag) = AESGCM::encrypt($skey, $iv, $data);
        }
        $str = bin2hex($iv) .  bin2hex($content) . bin2hex($tag);
        return base64_encode(hex2bin($str));
    }
}


