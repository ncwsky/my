<?php

declare(strict_types=1);

namespace common;

class Rsa
{
    private $privateKey;
    private $publicKey;
    private $keyLen;

    public function __construct($privateKey, $publicKey)
    {
        $this->privateKey = $this->_getContents($privateKey);
        $this->publicKey = $this->_getContents($publicKey);

        $publicKey = $this->_getPublicKey();
        $this->keyLen = openssl_pkey_get_details($publicKey)["bits"];
        openssl_free_key($publicKey);
    }

    /**
     * @uses 获取文件内容
     * @param $fileContent string
     * @return bool|string
     */
    private function _getContents($fileContent)
    {
        if (strlen($fileContent) < 128 && (is_file($fileContent) || substr($fileContent, 0, 4) == "http")) {
            return file_get_contents($fileContent);
        }
        return $fileContent;
    }

    /**
     * @uses 获取私钥
     * @return bool|resource
     */
    private function _getPrivateKey()
    {
        return openssl_pkey_get_private($this->privateKey);
    }

    /**
     * @uses 获取公钥
     * @return bool|resource
     */
    private function _getPublicKey()
    {
        return openssl_pkey_get_public($this->publicKey); //老方法 openssl_get_publickey
    }

    /**
     * @uses 私钥加密
     * @param string $data
     * @return null|string
     */
    public function privateEncrypt($data = '')
    {
        if (!is_string($data)) {
            return null;
        }
        $encrypted = "";
        $tmpEncrypted = "";
        $partLen = $this->keyLen / 8 - 11;
        $data = str_split($data, $partLen);
        $privateKey = $this->_getPrivateKey();
        foreach ($data as $item) {
            openssl_private_encrypt($item, $tmpEncrypted, $privateKey);#die(var_dump($data));
            $encrypted .= $tmpEncrypted;
        }
        openssl_free_key($privateKey);
        return $encrypted;#base64_encode($encrypted);
    }

    /**
     * @uses 公钥加密
     * @param string $data
     * @return null|string
     */
    public function publicEncrypt($data = "")
    {
        if (!is_string($data)) {
            return null;
        }
        $encrypted = "";
        $tmpEncrypted = "";
        $partLen = $this->keyLen / 8 - 11;
        $data = str_split($data, $partLen);
        $publicKey = $this->_getPublicKey();
        foreach ($data as $item) {
            openssl_public_encrypt($item, $tmpEncrypted, $publicKey);#die(var_dump($data));
            $encrypted .= $tmpEncrypted;
        }
        openssl_free_key($publicKey);
        return $encrypted;
    }

    /**
     * @uses 私钥解密
     * @param string $encrypted
     * @return null
     */
    public function privateDecrypt($encrypted = '')
    {
        if (!is_string($encrypted)) {
            return null;
        }
        $decrypted = '';
        $tmpDecrypted = '';
        $privateKey = $this->_getPrivateKey();
        $partLen = $this->keyLen / 8;
        foreach (str_split($encrypted, $partLen) as $chunk) {
            $res = openssl_private_decrypt($chunk, $tmpDecrypted, $privateKey);
            if (!$res) {
                echo openssl_error_string()."\r\n";
            }
            $decrypted .= $tmpDecrypted;
        }
        openssl_free_key($privateKey);
        return $decrypted;
    }

    /**
     * @uses 公钥解密
     * @param string $encrypted
     * @return null
     */
    public function publicDecrypt($encrypted = '')
    {
        if (!is_string($encrypted)) {
            return null;
        }
        $decrypted = "";
        $tmpDecrypted = "";
        $publicKey = $this->_getPublicKey();
        $partLen = $this->keyLen / 8;
        foreach (str_split($encrypted, $partLen) as $chunk) {
            openssl_public_decrypt($chunk, $tmpDecrypted, $publicKey);
            $decrypted .= $tmpDecrypted;
        }
        openssl_free_key($publicKey);
        return $decrypted;
    }

    /**
     * 签名
     * @param string $data 密文
     * @param string $private_key
     * @param int $alg 默认签名算法SHA256withRSA
     * @return string base64
     */
    public static function sign($data, $private_key, $alg = OPENSSL_ALGO_SHA256)
    {
        return openssl_sign($data, $signature, $private_key, $alg) ? base64_encode($signature) : null;
    }

    /**
     * 验证签名
     * @param string $data
     * @param string $sign base64
     * @param string $pubKey
     * @param int $alg
     * @return bool
     */
    public static function verify($data, $sign, $pubKey, $alg = OPENSSL_ALGO_SHA256)
    {
        return openssl_verify($data, base64_decode($sign), $pubKey, $alg) > 0;
    }

    /**
     * rsa用公钥加密
     * @param string $data
     * @param string $public_key
     * @return string|null
     */
    public static function public_encrypt($data, $public_key)
    {
        return openssl_public_encrypt($data, $encrypted, $public_key) ? base64_encode($encrypted) : null;
    }

    /**
     * rsa 用私钥加密
     * @param string $data
     * @param string $private_key
     * @return string|null
     */
    public static function private_encrypt($data, $private_key)
    {
        return openssl_private_encrypt($data, $encrypted, $private_key) ? base64_encode($encrypted) : null;
    }

    /**
     * rsa公钥解密
     * @param string $encrypted base64密文
     * @param string $public_key
     * @return string|null
     */
    public static function public_decode($encrypted, $public_key)
    {
        if (!is_string($encrypted)) {
            return null;
        }
        return openssl_public_decrypt(base64_decode($encrypted), $decrypted, $public_key) ? $decrypted : null;
    }

    /**
     * rsa私钥解密
     * @param string $encrypted base64密文
     * @param string $private_key
     * @return string|null
     */
    public static function private_decode($encrypted, $private_key)
    {
        if (!is_string($encrypted)) {
            return null;
        }
        return (openssl_private_decrypt(base64_decode($encrypted), $decrypted, $private_key)) ? $decrypted : null;
    }

    public static function formatPublicKey($publicKey)
    {
        $start = "-----BEGIN PUBLIC KEY-----\n";
        if (strpos($publicKey, $start) !== false) {
            return $publicKey;
        }
        return $start . wordwrap($publicKey, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
    }

    public static function formatPrivateKey($privateKey)
    {
        $start = '-----BEGIN RSA PRIVATE KEY-----' . "\n";
        if (strpos($privateKey, $start) !== false) {
            return $privateKey;
        }
        return $start . wordwrap($privateKey, 64, "\n", true) . "\n" . '-----END RSA PRIVATE KEY-----';
    }

    public static function url_safe_base64_encode($data)
    {
        return str_replace(['+','/', '='], ['-','_', ''], base64_encode($data));
    }

    public static function url_safe_base64_decode($data)
    {
        $base_64 = str_replace(['-','_'], ['+','/'], $data);
        return base64_decode($base_64);
    }

    /**
     * 通过指定模数与指数按ASN.1编码方式生成公钥
     * @param $modulus
     * @param $exponent
     * @return string
     */
    public static function createPublicKey($modulus, $exponent)
    {
        // 将十六进制转换为二进制
        $modBin = pack('H*', $modulus);
        $expBin = pack('H*', $exponent);

        // 构建ASN.1序列
        $modLen = strlen($modBin);
        $expLen = strlen($expBin);

        // 确保模数为正数，如果第一个字节大于0x7F，需要添加0x00
        if (ord($modBin[0]) & 0x80) {
            $modBin = "\x00" . $modBin;
            $modLen++;
        }

        //0x02	INTEGER	用于表示整数值，可以是正数、负数或零
        //0x03	BIT_STRING	位字符串，每个位可以独立设置为0或1
        //0x30	SEQUENCE	包含一个或多个类型的有序字段系列
        //0x06	OID	用于唯一标识ASN.1定义的对象或类型

        // 构建模数和指数的ASN.1序列
        $modSeq = "\x02" . self::encodeLength($modLen) . $modBin;
        $expSeq = "\x02" . self::encodeLength($expLen) . $expBin;

        // 组合RSA公钥序列
        $rsaSeq = "\x30" . self::encodeLength(strlen($modSeq) + strlen($expSeq)) . $modSeq . $expSeq;

        $rsaSeq = "\x03".self::encodeLength(strlen($rsaSeq) + 1)."\x0".$rsaSeq;
        // 添加RSA算法标识符
        $algoSeq = "\x30\x0D" .
            "\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01" . // RSA加密的OID  1.2.840.113549.1.1.1
            "\x05\x00"; // NULL
        // 最终的公钥结构
        $pubKeySeq = "\x30" . self::encodeLength(strlen($algoSeq) + strlen($rsaSeq)) . $algoSeq . $rsaSeq;

        // 转换为PEM格式
        return "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split(base64_encode($pubKeySeq), 64, "\n") .
            "-----END PUBLIC KEY-----\n";
    }

    public static function encodeLength($length)
    {
        if ($length < 128) {
            return chr($length);
        }

        $temp = "";
        while ($length > 0) {
            $temp = chr($length & 0xFF) . $temp;
            $length >>= 8;
        }
        return chr(0x80 | strlen($temp)) . $temp;
    }

    /**
     * 通过公钥生获取模数和指数
     * @param string $publicKey
     * @return array|bool
     */
    public static function pub2modExp($publicKey)
    {
        $key = openssl_pkey_get_public($publicKey);
        if (!$key) {
            return false;
        }
        $pKey = openssl_pkey_get_details($key);
        if (!$pKey) {
            return false;
        }
        return ['n' => bin2hex($pKey['rsa']['n']), 'e' => bin2hex($pKey['rsa']['e'])];
    }
}
