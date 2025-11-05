<?php
namespace common\weixin;

use common\Weixin;

class RedisQRCode implements QRCodeInterface
{
    use \MyMsg;
    private $wx = '';
    /**
     * @var \lib_redis
     */
    private $redis;
    private $prefix = '';

    public function __construct($wx = Weixin::WX_CFG, $name = 'redis', $prefix = '__qr')
    {
        $this->wx = $wx;
        $this->redis = redis($name);
        $this->prefix = $prefix;
    }

    /**
     * 场景自增id
     * @return int
     */
    public function incrId()
    {
        $key = '_qr_scene_id';
        $id = $this->redis->incr($key);
        if ($id >= 0xFFFFFFFF) { #0xffffff
            $this->redis->set($key, 0);
        }
        return $id;
    }
    public function create(int $type, array $data = [], $timeout = 610): ?array
    {
        $sceneId = $this->incrId();
        $ret = Weixin::qr($sceneId, $timeout, $this->wx);
        if (!$ret) {
            return null;
        }
        $data['type'] = $type;
        $this->redis->set($this->prefix.':'.$sceneId, $data, $timeout);
        return $ret;
    }

    public function read(int $sceneId): ?array
    {
        return $this->redis->get($this->prefix . ':' . $sceneId);
    }

    public function done(int $sceneId): void
    {
        $this->redis->del($this->prefix . ':' . $sceneId);
    }
}
