<?php

declare(strict_types=1);

namespace common;

/**
 * zip压缩文件
 * Class Zip
 * @package common
 */
class Zip
{
    public const EOF_CTRL_DIR = "\x50\x4b\x05\x06\x00\x00\x00\x00";
    public const NO_COMPRESS = 0; //不压缩
    public const DEF_COMPRESS = -1; //默认压缩级别，即6。

    protected $ctrl_dir = [];
    protected $old_offset = 0;
    protected $dataSize = 0;
    public $level = self::DEF_COMPRESS; //压缩等级 0-9 0压缩 -1默认压缩级别，即6。

    /**
     * @var false|resource|null
     */
    protected $zip = null;

    public function __construct(string $outputName, int $level = self::DEF_COMPRESS)
    {
        ini_set('memory_limit', '1024M');
        $dir = dirname($outputName);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->zip = fopen($outputName, "wb");
        $this->level = $level;
    }

    public function finish(): bool
    {
        $ctrlDir = implode('', $this->ctrl_dir);
        $out = $ctrlDir .
            self::EOF_CTRL_DIR .
            pack('v', count($this->ctrl_dir)) .
            pack('v', count($this->ctrl_dir)) .
            pack('V', strlen($ctrlDir)) .
            pack('V', $this->dataSize) .
            "\x00\x00";

        fwrite($this->zip, $out, strlen($out));
        return fclose($this->zip);
    }

    /**
     * @param string $fileName 不带/前缀的相对地址
     * @param string $data
     */
    public function addFile(string $fileName, string $data)
    {
        $this->_addFile($data, $fileName);
    }

    public function addFileFromPath(string $fileName, string $path)
    {
        $data = file_get_contents($path);
        $data !== false && $this->_addFile($data, $fileName);
    }

    /**
     * @param string $path 要压缩的目录
     * @param string $dirName
     * @throws \Exception
     */
    public function addPath(string $path, string $dirName = '')
    {
        if (!function_exists('gzcompress')) {
            return;
        }

        $files = $this->_getFileList($path);
        if (count($files) == 0) {
            return;
        }
        $d_len = strlen($path);
        if ($dirName && substr($dirName, -1) != '/') {
            $dirName .= '/';
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                $content = file_get_contents($file);
                if ($content === false) {
                    throw new \Exception($file . ' read fail');
                }
            } else { //目录
                $content = '';
            }
            // 1.删除$dir的字符(./folder/file.txt删除./folder/)
            // 2.如果存在/就删除(/file.txt删除/)
            $file = substr($file, $d_len);
            if (substr($file, 0, 1) == "\\" || substr($file, 0, 1) == "/") {
                $file = substr($file, 1);
            }
            $this->_addFile($content, $dirName . $file);
        }
    }

    private function _getFileList(string $dir): array
    {
        $files = [];
        if (file_exists($dir)) {
            if (substr($dir, -1) != "/") {
                $dir .= "/";
            }
            $dh = opendir($dir);
            $empty = true;
            while ($file = readdir($dh)) {
                if (($file != ".") && ($file != "..")) {
                    $empty = false;
                    if (is_dir($dir . $file)) {
                        $files = array_merge($files, $this->_getFileList($dir . $file));
                    } else {
                        $files[] = $dir . $file;
                    }
                }
            }
            closedir($dh);
            if ($empty) {
                $files[] = $dir;
            }
        }
        return $files;
    }

    private function _unix2DosTime(int $time = 0): int
    {
        $timearray = ($time == 0) ? getdate() : getdate($time);
        if ($timearray['year'] < 1980) {
            $timearray['year'] = 1980;
            $timearray['mon'] = 1;
            $timearray['mday'] = 1;
            $timearray['hours'] = 0;
            $timearray['minutes'] = 0;
            $timearray['seconds'] = 0;
        }
        return (($timearray['year'] - 1980) << 25) | ($timearray['mon'] << 21) | ($timearray['mday'] << 16) |
            ($timearray['hours'] << 11) | ($timearray['minutes'] << 5) | ($timearray['seconds'] >> 1);
    }

    private function _addFile(string $data, string $name, int $time = 0)
    {
        $name = str_replace('\\', '/', $name);

        $hexdtime = pack('V', $this->_unix2DosTime($time));

        $fr = "\x50\x4b\x03\x04";
        $fr .= "\x14\x00"; // ver needed to extract
        $fr .= "\x00\x00"; // gen purpose bit flag
        $fr .= "\x08\x00"; // compression method
        $fr .= $hexdtime; // last mod time and date

        $name_len = strlen($name);
        // "local file header" segment
        $unc_len = strlen($data);
        $crc = crc32($data);
        $zdata = gzcompress($data, $this->level);
        $c_len = strlen($zdata);
        $zdata = substr(substr($zdata, 0, $c_len - 4), 2); // fix crc bug
        $fr .= pack('V', $crc); // crc32
        $fr .= pack('V', $c_len); // compressed filesize
        $fr .= pack('V', $unc_len); // uncompressed filesize
        $fr .= pack('v', $name_len); // length of filename
        $fr .= pack('v', 0); // extra field length
        $fr .= $name;

        // "file data" segment
        $fr .= $zdata;

        // "data descriptor" segment (optional but necessary if archive is not
        // served as file)
        $fr .= pack('V', $crc); // crc32
        $fr .= pack('V', $c_len); // compressed filesize
        $fr .= pack('V', $unc_len); // uncompressed filesize

        $len = strlen($fr);
        $this->dataSize += $len;
        fwrite($this->zip, $fr, $len);

        // now add to central directory record
        $cdrec = "\x50\x4b\x01\x02";
        $cdrec .= "\x00\x00"; // version made by
        $cdrec .= "\x14\x00"; // version needed to extract
        $cdrec .= "\x00\x00"; // gen purpose bit flag
        $cdrec .= "\x08\x00"; // compression method
        $cdrec .= $hexdtime; // last mod time & date
        $cdrec .= pack('V', $crc); // crc32
        $cdrec .= pack('V', $c_len); // compressed filesize
        $cdrec .= pack('V', $unc_len); // uncompressed filesize
        $cdrec .= pack('v', $name_len); // length of filename
        $cdrec .= pack('v', 0); // extra field length
        $cdrec .= pack('v', 0); // file comment length
        $cdrec .= pack('v', 0); // disk number start
        $cdrec .= pack('v', 0); // internal file attributes
        $cdrec .= pack('V', 32); // external file attributes - 'archive' bit set
        $cdrec .= pack('V', $this->old_offset); // relative offset of local header
        $cdrec .= $name;

        $this->ctrl_dir[] = $cdrec;
        $this->old_offset = $this->dataSize;
    }
}
