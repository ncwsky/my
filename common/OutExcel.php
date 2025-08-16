<?php

declare(strict_types=1);

namespace common;

use myphp\Helper;
use PHPExcel_Style_Alignment;

//excel导出
class OutExcel
{
    use \MyMsg;

    public static $csv = false;
    public static $pFilename = 'php://output';

    /**
     * @param string $title
     * @param array $data
     * @param array $headers
     * @param \Closure|null $eachFun
     * @param bool $isOver 是否写入结束 默认是
     */
    public static function putCsv(string $title, array $data, array $headers, \Closure $eachFun = null, bool $isOver = true)
    {
        static $fp;
        $io_out = self::$pFilename == 'php://output';
        if ($io_out) {
            //header('Content-Type: text/csv; charset=utf-8');
            //header('Content-Disposition: ' . self::disToName($title . '.csv'));
            $res = \myphp::res();
            $first = $res->getHeaderLine('Content-Type') != 'text/csv; charset=utf-8';
            $res->setDownloadHeaders($title . '.csv', 'text/csv; charset=utf-8');

            if (IS_CLI) {
                $first && ob_start();
            } else {
                $res->send();
            }
        }
        if (!isset($fp)) {
            $fp = fopen(self::$pFilename, 'w'); #直接输出
            fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF)); #指定为utf-8

            $head = []; #标头
            foreach ($headers as $key => $value) {
                $head[] = $value['name'];
            }
            fputcsv($fp, $head);
        }
        #数据
        foreach ($data as $item) {
            if ($eachFun instanceof \Closure) {
                $eachFun($item); //传址处理
            }
            $v = [];
            foreach ($headers as $key => $value) {
                if ($value['key']) {
                    $v[] = $value['key'] instanceof \Closure ? $value['key']($item) : $item[$value['key']] ?? '';
                } else {
                    $v[] = '';
                }
            }
            fputcsv($fp, $v);
        }
        if ($isOver) {
            if ($io_out && IS_CLI) {
                $res->withBody(ob_get_clean());
            }
            fclose($fp);
            self::$pFilename = 'php://output'; //重置
            $fp = null;
        }
    }

    /**
     * 暂无发现Spout的表格合并功能
     * @param string $title
     * @param array $data
     * @param array $headers
     * @param \Closure|null $eachFun
     * @param bool $isOver
     * @throws \Box\Spout\Common\Exception\IOException
     * @throws \Box\Spout\Writer\Exception\WriterNotOpenedException
     */
    public static function putSpout(string $title, array $data, array $headers, \Closure $eachFun = null, bool $isOver = true)
    {
        static $writer;
        $io_out = self::$pFilename == 'php://output';
        if ($io_out) {
            //header('Content-Type: text/csv; charset=utf-8');
            //header('Content-Disposition: ' . self::disToName($title . '.csv'));
            $res = \myphp::res();
            $first = $res->getHeaderLine('Content-Type') != 'application/octet-stream';
            $res->setDownloadHeaders($title . '.xlsx');

            if (IS_CLI) {
                $first && ob_start();
            } else {
                $res->send();
            }
        }
        if (!isset($writer)) {
            $writer = \Box\Spout\Writer\Common\Creator\WriterEntityFactory::createXLSXWriter();
            // $writer = WriterEntityFactory::createODSWriter();
            // $writer = WriterEntityFactory::createCSVWriter();
            $writer->openToFile(self::$pFilename);

            //使用样式生成器创建样式
            $style = (new \Box\Spout\Writer\Common\Creator\Style\StyleBuilder())
                ->setFontBold()
                ->setShouldWrapText()
                ->build();

            $head = []; #标头
            foreach ($headers as $key => $value) {
                $head[] = \Box\Spout\Writer\Common\Creator\WriterEntityFactory::createCell($value['name'], $style);
            }
            /** 一次添加一行 */
            $singleRow = \Box\Spout\Writer\Common\Creator\WriterEntityFactory::createRow($head);
            $writer->addRow($singleRow);

            /*
            // 一次添加多行
            $multipleRows = [
                \Box\Spout\Writer\Common\Creator\WriterEntityFactory::createRow($head),
                ...
            ];
            $writer->addRows($multipleRows);
            */
        }
        #数据
        foreach ($data as $item) {
            if ($eachFun instanceof \Closure) {
                $eachFun($item); //传址处理
            }
            $v = [];
            foreach ($headers as $key => $value) {
                if ($value['key']) {
                    $v[] = $value['key'] instanceof \Closure ? $value['key']($item) : $item[$value['key']] ?? '';
                } else {
                    $v[] = '';
                }
            }
            /** 从值数组中添加一行 */
            $writer->addRow(\Box\Spout\Writer\Common\Creator\WriterEntityFactory::createRowFromArray($v));
        }
        if ($isOver) {
            if ($io_out && IS_CLI) {
                $res->withBody(ob_get_clean());
            }
            $writer->close();
            self::$pFilename = 'php://output'; //重置
            $writer = null;
        }
    }

    /**
     * @param string $title
     * @param array $data
     * @param array $headers 头与数组关联
     * @param \Closure|null $eachFun 单独对每行数据处理
     * @param \Closure|null $headFun 格式头处理 func($sheetAct, $phpExcel) use($headerKey)
     *  $headerKey = [
            'A1' => ['name' => '日期', 'merge' => 'A1:A2'],
            'B1' => ['name' => '星期', 'merge' => 'B1:B2'],
        ];
     * @param int $rowIndex
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public static function put(string $title, array $data, array $headers, \Closure $eachFun = null, \Closure $headFun = null, int $rowIndex = 2)
    {
        if (self::$csv) {
            self::$csv = false;
            self::putCsv($title, $data, $headers, $eachFun);
            return;
        }
        // 创建 Excel
        $phpExcel = new \PHPExcel();
        $phpExcel->getDefaultStyle()->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

        $sheetAct = $phpExcel->setActiveSheetIndex(0);
        $sheetAct->setTitle($title);

        if ($headFun instanceof \Closure) {
            $headFun($sheetAct, $phpExcel);
        }
        $headN = $rowIndex - 1;
        // 设置表头
        self::setHeader($sheetAct, $headers, $headN);

        #$objPHPExcel->getActiveSheet()->mergeCells('A1:G1');
        #$PHPExcel_Style->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
        //输出行记录
        self::setData($sheetAct, $headers, $data, $rowIndex, $eachFun);

        // 输出
        $objWriter = \PHPExcel_IOFactory::createWriter($phpExcel, 'Excel2007');
        $io_out = self::$pFilename == 'php://output';
        if ($io_out) { //浏览器输出下载
            $res = \myphp::res();
            $res->setDownloadHeaders($title . '.xlsx');
            if (IS_CLI) {
                self::$pFilename = tempnam(SITE_WEB . '/tmp', 'xls_');
            } else {
                $res->send();
            }
        }
        $objWriter->save(self::$pFilename); //生成文件
        if ($io_out && IS_CLI) {
            $res->withBody(file_get_contents(self::$pFilename));
            @unlink(self::$pFilename);
        }
        self::$pFilename = 'php://output'; //重置
    }
    // 设置表头
    public static function setHeader(\PHPExcel_Worksheet $sheetAct, array &$headers, int $headN = 1)
    {
        if (isset($headers[0])) { #普通数组 自动生成列名
            $_headers = [];
            $n = 0;
            $_pre = '';
            foreach ($headers as $head) {
                $_n = $n % 26;
                $_c = intval($n / 26);
                if ($_c > 0) {
                    $_c--;
                    $_pre = chr($_c + 65);
                }
                $key = $_pre.chr($_n + 65);
                $_headers[$key] = $head;
                $n++;
            }
            $headers = $_headers;
            unset($_headers);
            #print_r($headers);die();
        }

        foreach ($headers as $key => $value) {
            // 合并
            if ($merge = $value['merge'] ?? false) {
                $sheetAct->mergeCells($merge);
            }
            // 行宽高
            $sheetAct->getRowDimension($key)->setRowHeight(20);
            $sheetAct->getColumnDimension($key)->setWidth($value['width'] ?? 20);
            // 设置样式
            $PHPExcel_Style = $sheetAct->setCellValue($key . $headN, $value['name'])->getStyle($key . $headN);
            $PHPExcel_Style->getFont()->setBold(true);
            $PHPExcel_Style->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
        }
    }
    public static function setData(\PHPExcel_Worksheet $sheetAct, array $headers, array $data, int &$rowIndex = 2, $eachFun = null)
    {
        //输出行记录
        foreach ($data as $item) {
            if ($eachFun instanceof \Closure) {
                $eachFun($item); //传址处理
            }
            foreach ($headers as $key => $value) {
                if ($value['key'] !== null) {
                    $res = $value['key'] instanceof \Closure ? $value['key']($item) : $item[$value['key']] ?? '';
                    $dataType = $value['type'] ?? false;
                    if ($dataType && in_array($dataType, [
                            \PHPExcel_Cell_DataType::TYPE_STRING2,
                            \PHPExcel_Cell_DataType::TYPE_STRING,
                            \PHPExcel_Cell_DataType::TYPE_FORMULA,
                            \PHPExcel_Cell_DataType::TYPE_NUMERIC,
                            \PHPExcel_Cell_DataType::TYPE_BOOL,
                            \PHPExcel_Cell_DataType::TYPE_NULL,
                            \PHPExcel_Cell_DataType::TYPE_INLINE,
                            \PHPExcel_Cell_DataType::TYPE_ERROR,
                        ])) { // 设置单元格类型
                        $sheetAct->setCellValueExplicit($key . $rowIndex, $res, $dataType);
                    } else {
                        $sheetAct->setCellValue($key . $rowIndex, (string)$res);
                    }
                } else {
                    $sheetAct->setCellValue($key . $rowIndex, '');
                }
            }

            // 是否需要合并
            if ($merge = $item['merge'] ?? false) {
                $sheetAct->mergeCells($merge);
                $sheetAct->getStyle($merge)->getAlignment()
                    ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER)
                    ->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            }
            $rowIndex++;
        }
    }

    /**
     * 十进制转N进制
     * 根据EXCEL十进制列数，获取对应编号
     *
     * @param int $index
     * @return string
     */
    public static function getPrefix(int $index): string
    {
        $prefix = '';
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $len = 26; //strlen($characters);
        do {
            $remainder = $index / $len;  // 商数
            $quotient = $index % $len;  // 余数
            if ($remainder < 1) {
                $quotient -= 1;
            }
            $char = $characters[$quotient]; // 字符位置
            $prefix = $char . $prefix;
            $index = $remainder;
        } while ($index >= 1);
        return $prefix;
    }

    public const ADM_EXPORT_ORDER = 'admin/order/export';

    public const EXPIRE = 7200; //下载及缓存有效期
    #是否有导出或生成下载列表
    public static function hasExportList($uid, string $cmd): bool
    {
        $redis = GetC('redis') ? redis() : getCache();
        return (bool)$redis->keys($cmd . ':' . $uid . ':*');
    }
    #获取指定导出的生成列表
    public static function toExportList($uid, string $cmd): array
    {
        $redis = GetC('redis') ? redis() : getCache();
        $keys = $redis->keys($cmd.':'.$uid.':*');
        $list = [];
        foreach ($keys as $v) {
            $val = $redis->get($v);
            $val['expire'] = time() + $redis->ttl($v);
            $list[] = $val;
        }
        return $list;
    }

    /**
     * 设置导出的生成状态
     * @param int|string $uid
     * @param string $cmd
     * @param array $params
     * @return bool
     */
    public static function toExportStatusOk($uid, string $cmd, array $params): bool
    {
        $redis = GetC('redis') ? redis() : getCache();
        $name = md5(toJson($params));
        $key = $cmd . ':' . $uid . ':' . $name;
        $data = $redis->get($key);
        if (!$data) {
            return false;
        }
        $data['mtime'] = time(); //完成时间
        $data['status'] = 1;
        $redis->set($key, $data, self::EXPIRE);
        return true;
    }

    /**
     * @param int|string $uid
     * @param array $params
     * @param bool|null $csv
     * @param string $prefixName
     * @return string
     */
    public static function realExportFile($uid, array $params, bool $csv = null, string $prefixName = ''): string
    {
        $name = md5(toJson($params));
        $dir = SITE_WEB . '/up/export';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if ($csv === null) {
            $csv = self::$csv;
        }
        $suffix = $csv ? '.csv' : '.xlsx';
        return $dir . '/' . $prefixName.$name . $suffix;
    }

    /**
     * @param int|string $uid
     * @param string $cmd
     * @param array $params
     * @param bool|null $csv
     * @param string $prefixName
     * @return bool
     */
    public static function toExport($uid, string $cmd, array $params, bool $csv = null, string $prefixName = ''): bool
    {
        if ($csv === null) {
            $csv = self::$csv;
        }
        $name = md5(toJson($params));
        $suffix = $csv ? '.csv' : '.xlsx';

        $redis = GetC('redis') ? redis() : getCache();
        $key = $cmd . ':' . $uid . ':' . $name;
        if ($redis->exists($key) || !redisLockOnce('_' . $uid . '.' . $name)) {
            self::err('已发送生成指令，1小时内请不要重复操作！');
            return false;
        }
        try {
            $time = time();
            $fileName = $prefixName.$name;
            $url = U('/up/export/' . $fileName . $suffix . '?t=' . $time);
            $data = ['name' => $fileName, 'ctime' => $time, 'mtime' => 0, 'status' => 0, 'url' => $url]; //0表示生成中
            $redis->set($key, $data, self::EXPIRE);

            $params['_uid'] = $uid; //标识操作人
            //脚本入列
            queueCli($cmd, $params);

            //$cmd, $uid, $name, $dir, $suffix
        } catch (\Exception $e) {
            self::err($e->getMessage());
            return false;
        }
        return true;
    }

    public static function toExportClean(): string
    {
        $dirs = [
            SITE_WEB.'/up/export'
        ];
        $time = time();
        $n = 0;
        $out = '';
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            foreach (glob($dir.'/*.{csv,xls,xlsx,json}', GLOB_NOSORT | GLOB_BRACE) as $export) { #GLOB_BRACE - 扩充 {a,b,c} 来匹配 'a'，'b' 或 'c'
                $diff = $time - filemtime($export);
                if ($diff > OutExcel::EXPIRE) { #超过x的导出清除
                    $out .= $export . '->' . date("Y-m-d H:i:s", filemtime($export)). PHP_EOL;
                    @unlink($export);
                    $n++;
                }
            }
        }
        $out .= "clear:$n\r\n";
        Helper::toFileLog(ROOT.'/log/export.log', $out);
        return $out;
    }
}
