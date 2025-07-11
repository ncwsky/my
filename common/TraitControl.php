<?php

declare(strict_types=1);

namespace common;


use myphp\Control;
use myphp\Value;

trait TraitControl
{
    /**
     * 临时文件上传 成功处理后移动 /index/tmp-upload
     * 直接上传到正式目录 /index/tmp-upload?tmp=no
     * 使用原始内容上传 /index/tmp-upload?raw=1&file_name=test.jpg  post  raw
     * 使用原始base64内容上传 /index/tmp-upload?raw=base64&file_name=test.jpg  post  base64(raw)
     * @return array
     */
    public function tmpUpload()
    {
        $upload = \Upload::getInstance();
        $upload->fileSize = 5;
        $upload->uploadPath = ROOT_DIR . '/tmp/'; //设置文件相对的上传路径
        $tmp = $_POST['tmp'] ?? ($_GET['tmp'] ?? 'no');
        if ($tmp == 'no') {
            $upload->uploadPath = ROOT_DIR . '/up/y' . date('Y/md/');
        }
        $upload->fileType = $upload->fileType . ',ico';
        $upload->realPath = SITE_WEB . $upload->uploadPath; //设置文件的绝对路径
        $upload->imgOptimize = true;
        $isEditor = isset($_GET['editor']) ? (bool)$_GET['editor'] : null;
        $name = $_POST['name'] ?? ($_GET['name'] ?? 'files');
        if ($isEditor) $name = 'upfile';

        $raw = $_GET['raw'] ?? '';
        if ($raw) {
            $_FILES[$name] = [
                "tmp_name" => \myphp::rawBody(),
                "name" => isset($_GET['file_name']) ? trim($_GET['file_name']) : '',
                "size" => 0,
                "error" => UPLOAD_ERR_OK,
                "type" => "raw_string",
            ];
            if ($raw == 'base64') {
                $str = substr($_FILES[$name]['tmp_name'], 0, 32);
                if ($pos = strpos($str, ';base64,')) { //带有base64前缀的截取处理
                    $_FILES[$name]['tmp_name'] = substr($_FILES[$name]['tmp_name'], $pos + 8);
                    //未指定文件名时自动处理
                    if ($_FILES[$name]['name'] === '' && preg_match('/^data:\s*(\w+)\/(\w+);base64,/', $str, $result)) {
                        $_FILES[$name]['name'] = uniqid() . '.' . $result[2];
                    }
                }
                $_FILES[$name]['tmp_name'] = base64_decode($_FILES[$name]['tmp_name']);
            }
            $_FILES[$name]['size'] = strlen($_FILES[$name]['tmp_name']);
        }

        $data = $upload->upload($name);
        $picErr = [];
        $pics = [];
        if (isset($data[0]['state'])) {//多个文件上传
            for ($i = 0; $i < count($data); $i++) {
                if ($data[$i]['state'] == '1') {
                    $pics[$i] = $data[$i]['url'];
                } else {
                    $picErr[] = "[" . ($i + 1) . "]" . ($data[$i]['title'] ? "【" . $data[$i]['title'] . "】" : '') . $data[$i]['state'];
                }
            }
        } else {//单个文件上传
            if ($data['state'] == '1') {
                $pics[] = $data['url'];
            } else {
                $picErr[] = ($data['title'] ? "【" . $data['title'] . "】" : '') . $data['state'];
            }
        }
        if (empty($pics)) { //&& $picErr
            if ($isEditor) {
                return ['state' => $picErr ? implode(" \n", $picErr) : '上传失败', 'url' => ''];
            }
            return Control::fail(implode(" \n", $picErr));
        }

        if ($isEditor) {
            /**
             * 得到上传文件所对应的各个参数,数组结构
             * array(
             *     "state" => "",          //上传状态，上传成功时必须返回"SUCCESS"  或失败信息
             *     "url" => "",            //返回的地址
             *     "title" => "",          //新文件名
             *     "original" => "",       //原始文件名
             *     "type" => ""            //文件类型
             *     "size" => "",           //文件大小
             * )
             */
            if (count($pics) > 1) {
                $list = [];
                foreach ($pics as $pic) {
                    $list[] = ['url' => $pic];
                }
                return ['state' => 'SUCCESS', 'list' => $list];
            }
            return ['state' => 'SUCCESS', 'url' => $pics[0], 'type' => '', 'original' => ''];
        }

        return Control::ok($pics);
    }

    //生成随机验证码 /index/captcha[?name=captcha]
    public function captcha()
    {
        $name = Value::get($_GET, 'name', ['min' => 1, 'max' => 30], 'captcha');
        $w = isset($_GET['w']) ? (int)($_GET['w']) : 120;
        $h = isset($_GET['h']) ? (int)($_GET['h']) : 36;
        $size = isset($_GET['size']) ? (int)($_GET['size']) : 18;
        $len = isset($_GET['len']) ? (int)($_GET['len']) : 4;
        $type = isset($_GET['number']) ? (int)($_GET['number']) : 0;

        \Image::$outputString = true;

        if (!empty($_GET['raw'])) { //返回sid用于cookie处理
            $raw = \Image::code($w, $h, $size, $len, $type, function ($code) use ($name) {
                session($name, strtolower($code));
            });
            $raw = \myphp\Session::getId() . ':' . $raw;
            return Control::html($raw);
        }

        \myphp::setHeader("Content-Type", "image/png");
        return \Image::code($w, $h, $size, $len, $type, function ($code) use ($name) {
            session($name, strtolower($code));
        });
    }
}
