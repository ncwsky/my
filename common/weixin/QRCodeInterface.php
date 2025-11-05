<?php
namespace common\weixin;

interface QRCodeInterface
{
    public function create(int $type, array $data = [], $timeout = 610): ?array;

    public function read(int $sceneId): ?array;

    public function done(int $sceneId): void;
}
