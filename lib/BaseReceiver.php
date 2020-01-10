<?php
/**
 * 基于imap协议接收邮件
 * Copyright: Foudaful
 * @Author: sky <sky.nie@qq.com>
 * @Date: 2020/01/02 09:30
 * 使用方法：
 * $receiver = BaseReceiver::getInstance();
 */

namespace lib;

class BaseReceiver
{

    const MAX_READ_SIZE = 100000000;
    const PATTERN_REQUEST_STRING_SEQUENCE = '/{\d+}/';
    const PATTERN_RESPONSE_STRING_SEQUENCE = '/{(\d+)}$/';
    const SEQUENCE_PARAM_NAME = '[]';
    const PARTIAL_PARAM_NAME = '<>';
    const PARAM_NO = 1;
    const PARAM_SINGLE = 2;
    const PARAM_PAIR = 4;
    const PARAM_LIST = 8;
    const PARAM_STRING = 16;
    const PARAM_NUMBER = 32;
    const PARAM_DATE = 64;
    const PARAM_FLAG = 128;
    const PARAM_SEQUENCE = 256;
    const PARAM_SEARCH = 512;
    const PARAM_BODY = 1024;
    const PARAM_PARTIAL = 2048;
    const PARAM_EXCLUSIVE = 4096;
    private static $statusKeywords = [
        'MESSAGES' => self::PARAM_NO,
        'RECENT' => self::PARAM_NO,
        'UIDNEXT' => self::PARAM_NO,
        'UIDVALIDITY' => self::PARAM_NO,
        'UNSEEN' => self::PARAM_NO
    ];
    private static $searchKeywords = [
        'ALL' => self::PARAM_NO,
        'ANSWERED' => self::PARAM_NO,
        'BCC' => 18, // self::PARAM_SINGLE | self::PARAM_STRING,
        'BEFORE' => 66, // self::PARAM_SINGLE | self::PARAM_DATE,
        'BODY' => 18, // self::PARAM_SINGLE | self::PARAM_STRING,
        'CC' => 18, // self::PARAM_SINGLE | self::PARAM_STRING,
        'DELETED' => self::PARAM_NO,
        'DRAFT' => self::PARAM_NO,
        'FLAGGED' => self::PARAM_NO,
        'FROM' => 18, // self::PARAM_SINGLE | self::PARAM_STRING,
        'HEADER' => 20, // self::PARAM_PAIR | self::PARAM_STRING,
        'KEYWORD' => 130, // self::PARAM_SINGLE | self::PARAM_FLAG,
        'LARGER' => 34, // self::PARAM_SINGLE | self::PARAM_NUMBER,
        'NEW' => self::PARAM_NO,
        'NOT' => 514, // self::PARAM_SINGLE | self::PARAM_SEARCH,
        'OLD' => self::PARAM_NO,
        'ON' => 66, // self::PARAM_SINGLE | self::PARAM_DATE,
        'OR' => 516, // self::PARAM_PAIR | self::PARAM_SEARCH,
        'RECENT' => self::PARAM_NO,
        'SEEN' => self::PARAM_NO,
        'SENTBEFORE' => 66, // self::PARAM_SINGLE | self::PARAM_DATE,
        'SENTON' => 66, // self::PARAM_SINGLE | self::PARAM_DATE,
        'SENTSINCE' => 66, // self::PARAM_SINGLE | self::PARAM_DATE,
        'SINCE' => 66, // self::PARAM_SINGLE | self::PARAM_DATE,
        'SMALLER' => 34, // self::PARAM_SINGLE | self::PARAM_NUMBER,
        'SUBJECT' => 18, // self::PARAM_SINGLE | self::PARAM_STRING,
        'TEXT' => 18, // self::PARAM_SINGLE | self::PARAM_STRING,
        'TO' => 18, // self::PARAM_SINGLE | self::PARAM_STRING,
        'UID' => 258, // self::PARAM_SINGLE | self::PARAM_SEQUENCE,
        'UNANSWERED' => self::PARAM_NO,
        'UNDELETED' => self::PARAM_NO,
        'UNDRAFT' => self::PARAM_NO,
        'UNFLAGGED' => self::PARAM_NO,
        'UNKEYWORD' => 130, // self::PARAM_SINGLE | self::PARAM_FLAG,
        'UNSEEN' => self::PARAM_NO
    ];
    private static $fetchKeywords = [
        'ALL' => 4097, // self::PARAM_NO | self::PARAM_EXCLUSIVE,
        'FAST' => 4097, // self::PARAM_NO | self::PARAM_EXCLUSIVE,
        'FULL' => 4097, // self::PARAM_NO | self::PARAM_EXCLUSIVE,
        'BODY' => 3075, // self::PARAM_NO | self::PARAM_SINGLE | self::PARAM_BODY | self::PARAM_PARTIAL,
        'BODY.PEEK' => 3074, // self::PARAM_SINGLE | self::PARAM_BODY | self::PARAM_PARTIAL,
        'BODYSTRUCTURE' => self::PARAM_NO,
        'ENVELOPE' => self::PARAM_NO,
        'FLAGS' => self::PARAM_NO,
        'INTERNALDATE' => self::PARAM_NO,
        'RFC822' => self::PARAM_NO,
        'RFC822.HEADER' => self::PARAM_NO,
        'RFC822.SIZE' => self::PARAM_NO,
        'RFC822.TEXT' => self::PARAM_NO,
        'UID' => self::PARAM_NO
    ];
    private $sock = null;
    private $timeout = 120;
    private $ts = 0;
    private $tagName = 'A';
    private $tagId = 0;
    private $capabilities = [];
    private $folders = [];
    private $currentFolder = null;
    private $currentCommand = null;
    private $lastSend = '';
    private $lastRecv = '';
    private $params = null;
    private static $instance = [];

    private function __construct(array $params, int $connectTimeout, int $timeout)
    {
        empty($params['port']) && ($params['port'] = '993');
        $this->sock = new BaseSocket($params['gateway'] . ':' . $params['port'], $timeout);
        //
        $this->params = $params;
        $this->connect($params, $connectTimeout);
    }
    
    public static function getInstance(array $params, int $connectTimeout = 30, int $timeout = 28800)
    {
        if (empty($params['gateway']) || empty($params['account']) || empty($params['password'])) {
            throw new \Exception('missing parameters');
        }
        $a = $params['account'];
        if (empty(self::$instance[$a])) {
            self::$instance[$a] = new self($params, $connectTimeout, $timeout);
        } else {
            self::$instance[$a]->select($params['mailbox'] ?? 'INBOX');
        }
        return self::$instance[$a];
    }
    
    public function close()
    {
        $this->sock->close();
        self::$instance = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function connect(array $params, int $timeout = 30)
    {
        $this->sock->connect($params, $timeout);
        $this->getResponse();
        if (isset($params['secure']) && strtolower($params['secure']) == 'tls') {
            $this->request('STARTTLS');
            $this->sock->startTLS();
        }
        
        $this->login($params['account'], $params['password']);
        $this->select($params['mailbox'] ?? 'INBOX');
    }
    
    private function login($username, $password)
    {
        try {
            $this->request('login', [$username, $password]);
        } catch (\Throwable $ex) {
            throw new \Exception($ex->getMessage(), $ex->getCode());
        }
    }
    
    public function logout()
    {
        $this->request('logout');
    }

    public function capability()
    {
        $res = $this->request('capability');
        if (isset($res[0][0]) && $res[0][0] == '*' && isset($res[0][1]) && strcasecmp($res[0][1], 'capability') == 0) {
            for ($i = 2, $n = count($res[0]); $i < $n; ++ $i) {
                $this->capabilities[strtoupper($res[0][$i])] = true;
            }
        }
    }

    public function id($data)
    {
        if (isset($this->capabilities['ID'])) {
            $this->request('id', [$data]);
        }
    }

    public function getList($reference = '', $wildcard = '')
    {
        $res = $this->request('list', [$reference, $wildcard]);
        foreach ($res as &$r) {
            if (isset($r[0]) && $r[0] == '*' && isset($r[1]) && strcasecmp($r[1], 'list') == 0 && isset($r[4])) {
                $this->folders[$r[4]] = [
                    'id' => $r[4],
                    'name' => mb_convert_encoding($r[4], 'UTF-8', 'UTF7-IMAP'),
                    'path' => $r[3],
                    'attr' => $r[2]
                ];
            }
        }
        return $this->folders;
    }

    public function status($folder, $data)
    {
        $args = $this->formatArgsForCommand($data, self::$statusKeywords);
        $res = $this->request('status', [$folder, $args]);
        $status = [];
        if (! empty($res)) {
            foreach ($res as &$r) {
                if (isset($r[0]) && $r[0] == '*' && isset($r[1]) && strcasecmp($r[1], 'status') == 0 && isset($r[3]) && is_array($r[3])) {
                    for ($i = 0, $n = count($r[3]); $i < $n; $i += 2) {
                        $status[$r[3][$i]] = $r[3][$i + 1];
                    }
                }
            }
        }
        return $status;
    }

    public function select($folder)
    {
        $res = $this->request('select', [$folder]);
        $status = [];
        if (! empty($res)) {
            foreach ($res as $r) {
                if (isset($r[0]) && $r[0] == '*') {
                    if (isset($r[1]) && isset($r[2])) {
                        if (strcasecmp($r[1], 'ok') == 0 && is_array($r[2])) {
                            for ($i = 0, $n = count($i); $i < $n; $i += 2) {
                                $status[$r[2][$i]] = $r[2][$i + 1];
                            }
                        } elseif (ctype_digit($r[1])) {
                            $status[$r[2]] = $r[1];
                        } else {
                            $status[$r[1]] = $r[2];
                        }
                    }
                }
            }
        }
        $this->currentFolder = $folder;
        return $status;
    }

    public function search(array $data)
    {
        $args = $this->formatArgsForCommand($data, self::$searchKeywords, true);
        $res = $this->request('search', $args);
        $ls = [];
        foreach ($res as &$r) {
            if (isset($r[0]) && $r[0] == '*' && isset($r[1]) && strcasecmp($r[1], 'search') == 0) {
                for ($i = 2, $n = count($r); $i < $n; ++ $i) {
                    $ls[] = $r[$i];
                }
            }
        }
        return $ls;
    }

    /**
     * 获取邮件数据
     * @param int $mailid
     * @param array $params
     * @param bool $isuid
     * @return mixed
     */
    public function fetch(int $mailid, array $params, bool $isuid = true): array
    {
        $seqStr = $this->formatSequence($mailid ? [$mailid] : []);
        $args = $this->formatArgsForCommand($params, self::$fetchKeywords);
        $data = $this->request($isuid && $mailid ? 'uid fetch' : 'fetch', [$seqStr, $args]);
        $result = [];
        foreach ($data as $r) {
            if (isset($r[0]) && $r[0] == '*' && isset($r[1]) && is_numeric($r[1]) && isset($r[2]) && strcasecmp($r[2], 'fetch') == 0 && isset($r[3]) && is_array($r[3])) {
                //
                $a = [];
                for ($i = 0, $n = count($r[3]); $i < $n; $i += 2) {
                    $key = $r[3][$i];
                    isset($r[3][$i + 1]) || ($r[3][$i + 1] = '');
                    if (is_array($key)) {
                        $key = json_encode($key);
                    } elseif (((strcasecmp($key, 'BODY') == 0 && isset($args['BODY']) && is_array($args['BODY'])) || (strcasecmp($key, 'BODY.PEEK') == 0 && isset($args['BODY.PEEK']) && is_array($args['BODY.PEEK']))) && is_array($r[3][$i + 1])) {
                        $key = trim($this->formatRequestArray([$key => $r[3][$i + 1]], $placeHolder, 0), '()');
                        $i++;
                    } elseif (strcasecmp($key, 'BODY') == 0 && isset($args['BODY']) && preg_match('/^\[\d*(\.\d+)*\]$/', $args['BODY']) && is_array($r[3][$i + 1])) {
                        $i++;
                    }
                    $a[$key] = (isset($r[3][$i + 1]) && $r[3][$i + 1] != 'NIL' ? $r[3][$i + 1] : '');
                }
                if (! empty($a)) {
                    if (1 == count($params) && isset($params[0]) && strtoupper($params[0]) == 'UID') {
                        $result[] = intval(current($a));
                    } else {
                        $result[] = $a;
                    }
                    unset($a);
                }
            }
        }
        unset($data);
        return $result;
    }

    /**
     * 获取邮件头
     * @param int $mailid
     * @param string $isuid
     * @return string
     */
    public function fetchHeader(int $mailid, $isuid = true): string
    {
        $res = $this->fetch($mailid, ['RFC822.HEADER'], $isuid);
        return $res[0]['RFC822.HEADER'] ?? '';
    }
    
    public function fetchStructure(int $mailid, $isuid = true)
    {
        $res = $this->fetch($mailid, ['BODYSTRUCTURE'], $isuid);
        return $this->genStructure($res[0]['BODYSTRUCTURE'] ?? []);
    }

    /**
     * 获取邮件body
     * @param int $mailid
     * @param $partNum
     * @param $isuid
     */
    public function fetchBody(int $mailid, $partNum, $isuid = true): string
    {
        if ($partNum) {
            $field = 'BODY';
            $params = [
                $field => '[' . $partNum . ']'
            ];
        } else {
            $field = 'RFC822.TEXT';
            $params = [$field];
        }
        $res = $this->fetch($mailid, $params, $isuid);
        return $res[0][$field] ?? '';
    }

    private function doParameters(array $arr)
    {
        $res = [];
        for ($i = 0, $n = count($arr); $i < $n; $i = $i + 2) {
            $obj = new \stdClass();
            $obj->attribute = $arr[$i];
            $obj->value = $arr[$i + 1] ?? '';
            $res[] = $obj;
        }
        return $res;
    }

    private function doParts(array $arr)
    {
        $typeArr = [
            'text' => 0,
            'multipart' => 1,
            'message' => 2,
            'application' => 3,
            'audio' => 4,
            'image' => 5,
            'video' => 6,
            'model' => 7,
            'other' => 8
        ];
        $encodeArr = [
            '7bit' => 0,
            '8bit' => 1,
            'binary' => 2,
            'base64' => 3,
            'quoted-printable' => 4,
            'other' => 5
        ];
        $arr[0] = strtolower($arr[0]);
        $arr[5] = strtolower($arr[5]);
        $obj = new \stdClass();
        $obj->type = $typeArr[$arr[0]];
        $obj->encoding = $encodeArr[$arr[5]];
        
        $obj->ifsubtype = 0;
        if (strtoupper($arr[1]) != 'NIL') {
            $obj->ifsubtype = 1;
            $obj->subtype = $arr[1];
        }
        $obj->ifparameters = 0;
        if (is_array($arr[2])) {
            $obj->ifparameters = 1;
            $obj->parameters = $this->doParameters($arr[2]);
        }
        $obj->ifdescription = 0;
        $obj->ifid = 0;
        if (strtoupper($arr[3]) != 'NIL') {
            $obj->ifid = 0;
            $obj->id = $arr[3];
        }
        if (strtoupper($arr[4]) != 'NIL') {
            $obj->ifdescription = 1;
            $obj->description = $arr[4];
        }
        
        $obj->bytes = $arr[6];
        $obj->lines = is_numeric($arr[7]) ? $arr[7] : 0;
        $obj->ifdisposition = 0;
        $obj->ifdparameters = 0;
        if (is_array($arr[8])) {
            $obj->ifdisposition = 1;
            $obj->disposition = $arr[8][0];
            $obj->ifdparameters = 1;
            $obj->dparameters = $this->doParameters($arr[8][1]);
        }
        return $obj;
    }

    protected function genStructure(array $params, int $type = 0)
    {
        if (count($params) >= 8 && is_string($params[0])) {
            return $this->doParts($params);
        }
        $obj = new \stdClass();
        $obj->type = $type;
        $obj->encoding = 0;
        
        $obj->ifdescription = 0;
        // $obj->description = '';
        $obj->ifid = 0;
        // $obj->id = '';
        $obj->ifdisposition = 0;
        // $obj->disposition = '';
        $obj->ifdparameters = 0;
        // $obj->dparameters = '';
        
        // $obj->lines = 0;
        // $obj->bytes = 0;
        //
        $begin = false;
        foreach ($params as $k => $v) {
            if ($begin) {
                if (is_array($v)) {
                    $obj->ifparameters = 1;
                    $obj->parameters = $this->doParameters($v);
                } elseif (! isset($obj->ifdisposition)) {
                    // 主体部署
                    $obj->ifdisposition = 0;
                    if ((strtoupper($v) != 'NIL')) {
                        $obj->ifdisposition = 1;
                        $obj->disposition = $v;
                    }
                }
            } else {
                if (is_array($v)) {
                    $obj->type = 1;
                    if (is_array($v[0])) {
                        $obj->parts[] = $this->genStructure($v, 1);
                    } else {
                        // attachment
                        $obj->parts[] = $this->doParts($v);
                    }
                } elseif (! isset($obj->ifsubtype)) {
                    $begin = true;
                    $obj->ifsubtype = 0;
                    if (strtoupper($v) != 'NIL') {
                        $obj->ifsubtype = 1;
                        $obj->subtype = $v;
                    }
                }
            }
        }
        return $obj;
    }
    
    protected function errorHandler($errNo, $errStr, $errFile = '', $errLine = 0)
    {
        if (stripos($errStr, 'resource is not a valid stream') !== false) {
            throw new \Exception($errStr, 404);
        } else {
            throw new \Exception('no:'. $errNo .',m:'. $errStr .',f:'.$errFile .',l:'.$errLine);
        }
    }

    private function nextTag()
    {
        $this->tagId++;
        return sprintf('%s%d', $this->tagName, $this->tagId);
    }

    private function request($cmd, $args = [])
    {
        $this->currentCommand = strtoupper(trim($cmd));
        $tag = $this->nextTag();
        $req = $tag . ' ' . $this->currentCommand;
        
        // 格式化参数列表
        $strSeqList = [];
        if (is_array($args)) {
            $argStr = $this->formatRequestArray($args, $strSeqList);
        } else {
            $argStr = $this->formatRequestString($args, $strSeqList);
        }
        
        $subReqs = [];
        if (isset($argStr[0])) {
            $req .= ' ' . $argStr;
            // 如果参数中包括需要序列化的数据，根据序列化标识{length}将命令拆分成多条
            if (! empty($strSeqList) && preg_match_all(self::PATTERN_REQUEST_STRING_SEQUENCE, $req, $matches, PREG_OFFSET_CAPTURE)) {
                $p = 0;
                foreach ($matches[0] as $m) {
                    $e = $m[1] + strlen($m[0]);
                    $subReqs[] = substr($req, $p, $e - $p);
                    $p = $e;
                }
                $subReqs[] = substr($req, $p);
                // 校验序列化标识与需要序列化的参数列表数量是否一致
                if (count($subReqs) != count($strSeqList) + 1) {
                    $subReqs = null;
                }
            }
        }
        $num = 0;
        try {
            set_error_handler([$this, 'errorHandler']);
            REDO:
            if (empty($subReqs)) {
                // 处理单条命令
                $this->sock->writeLine($req);
                $this->lastSend = $req;
                $res = $this->getResponse($tag);
            } else {
                // 处理多条命令
                $this->lastSend = '';
                foreach ($subReqs as $id => $req) {
                    $this->sock->writeLine($req);
                    $this->lastSend .= $req;
                    $res = $this->getResponse($tag);
                    if (isset($res[0][0]) && $res[0][0] == '+') {
                        $this->sock->write($strSeqList[$id]);
                        $this->lastSend .= "\r\n" . $strSeqList[$id];
                    } else {
                        // 如果服务器端返回其他相应，则定制后续执行
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            $num++;
            if ($num < 2 && $e->getCode() == 404) {
                if ($this->params) {
                    $this->connect($this->params);
                }
                goto REDO;
            } else {
                restore_error_handler();
                throw $e;
            }
        }
        restore_error_handler();
        return $res;
    }

    private function formatRequestString($s, &$strSeqList)
    {
        $s = trim($s);
        $needQuote = false;
        if (! isset($s[0])) {
            $needQuote = true;
        } elseif ($this->currentCommand == 'ID') {
            $needQuote = true;
        } else {
            // 参数包含多行时，需要进行序列化
            if (strpos($s, "\r") !== false || strpos($s, "\n") !== false) {
                $strSeqList[] = $s;
                $s = sprintf('{%d}', strlen($s));
            } else {
                // 参数包含双引号或空格时，需要将使用双引号括起来
                if (strpos($s, '"') !== false) {
                    $s = addcslashes($s, '"');
                    $needQuote = true;
                }
                if (strpos($s, ' ') !== false) {
                    $needQuote = true;
                }
            }
        }
        if ($needQuote) {
            return sprintf('"%s"', $s);
        } else {
            return $s;
        }
    }

    private function formatRequestArray($arr, &$strSeqList, $level = -1)
    {
        $a = [];
        foreach ($arr as $k => $v) {
            $isBody = false;
            $supportPartial = false;
            $partialStr = '';
            if (substr($this->currentCommand, -5) == 'FETCH') {
                // 识别是否body命令，是否可以包含<partial>
                $kw = strtoupper($k);
                if (isset(self::$fetchKeywords[$kw]) && (self::$fetchKeywords[$kw] & self::PARAM_BODY) > 0) {
                    $isBody = true;
                }
                if (isset(self::$fetchKeywords[$kw]) && (self::$fetchKeywords[$kw] & self::PARAM_PARTIAL) > 0) {
                    $supportPartial = true;
                }
            }
            if (is_array($v)) {
                if ($supportPartial && isset($v[self::PARTIAL_PARAM_NAME]) && is_array($v[self::PARTIAL_PARAM_NAME])) {
                    // 处理包含<partial>的命令
                    foreach ($v[self::PARTIAL_PARAM_NAME] as $spos => $mlen) {
                        $partialStr = sprintf('<%d.%d>', $spos, $mlen);
                    }
                    unset($v[self::PARTIAL_PARAM_NAME]);
                }
                $s = $this->formatRequestArray($v, $strSeqList, $level + 1);
            } else {
                $s = $this->formatRequestString($v, $strSeqList);
            }
            if (! is_numeric($k)) {
                // 字典方式需要包含键名
                $k = $this->formatRequestString($k, $strSeqList);
                if ($isBody) {
                    $s = $k . $s;
                } else {
                    $s = $k . ' ' . $s;
                }
                // 包含<partial>
                if ($supportPartial) {
                    $s .= $partialStr;
                }
            }
            $a[] = $s;
        }
        if ($level < 0) {
            return implode(' ', $a);
        } elseif (($level % 2) == 0) {
            return sprintf('(%s)', implode(' ', $a));
        } else {
            return sprintf('[%s]', implode(' ', $a));
        }
    }

    private function formatSequence($seq)
    {
        $n = count($seq);
        if ($n == 0) {
            return '1:*';
        } elseif ($n == 1) {
            if (isset($seq[0])) {
                return strval($seq[0]);
            } else {
                foreach ($seq as $k => $v) {
                    return $k . ':' . $v;
                }
            }
        } else {
            return implode(',', $seq);
        }
    }

    private function formatArgsForCommand(&$data, &$fields, $asList = false)
    {
        $args = [];
        foreach ($data as $k => $v) {
            if (is_numeric($k)) {
                // 无值参数
                $name = strtoupper($v);
                if (isset($fields[$name])) {
                    // 对于排他性属性，直接返回
                    if (($fields[$name] & self::PARAM_EXCLUSIVE) > 0) {
                        return $name;
                    } elseif (($fields[$name] & self::PARAM_NO) > 0) {
                        $args[] = $name;
                    } else {
                        // my add
                        return $name;
                    }
                }
            } elseif ($k == self::SEQUENCE_PARAM_NAME) {
                // 序列
                $args[] = $this->formatSequence($v);
            } else {
                $name = strtoupper($k);
                if (isset($fields[$name])) {
                    $paramType = $fields[$name];
                    // 格式化参数类型
                    if (($paramType & self::PARAM_DATE) > 0) {
                        $v = date('j-M-Y', $v);
                    } elseif (($paramType & self::PARAM_SEQUENCE) > 0) {
                        $v = $this->formatSequence($v);
                    }
                    
                    // 根据参数定义拼组参数列表
                    if (($paramType & self::PARAM_SINGLE) > 0) {
                        // 单值参数
                        if ($asList) {
                            $args[] = $name;
                            $args[] = $v;
                        } else {
                            $args[$name] = $v;
                        }
                    } elseif (($paramType & self::PARAM_PAIR) > 0) {
                        // 键值对参数
                        if (is_array($v)) {
                            foreach ($v as $x => $y) {
                                $pk = $x;
                                $pv = $y;
                                break;
                            }
                        } else {
                            $pk = $v;
                            $pv = '';
                        }
                        if ($asList) {
                            $args[] = $name;
                            $args[] = $pk;
                            $args[] = $pv;
                        } else {
                            $args[$name] = [
                                $pk => $pv
                            ];
                        }
                    } elseif (($paramType & self::PARAM_LIST) > 0) {
                        // 列表参数
                        if ($asList) {
                            $args[] = $name;
                            foreach ($v as $i) {
                                $args[] = $i;
                            }
                        } else {
                            $args[$name] = $v;
                        }
                    } elseif (($paramType & self::PARAM_NO) > 0) {
                        // 无值参数
                        $args[] = $name;
                    }
                }
            }
        }
        return $args;
    }

    private function getResponse($tag = null)
    {
        $r = [];
        $readMore = true;
        while ($readMore) {
            $ln = trim($this->sock->readLine());
            if (empty($ln)) {
                // connection closed or read empty string, throw exception to avoid dead loop and reconnect
                throw new \Exception('read response failed', 404);
            }
            
            $matches = null;
            $strSeqKey = null;
            $strSeq = null;
            if (preg_match(self::PATTERN_RESPONSE_STRING_SEQUENCE, $ln, $matches)) {
                $strSeqKey = $matches[0];
                $this->readSequence($ln, $strSeq, $matches[1]);
            }
            //$this->lastRecv = $ln;
            // 区分处理不同种响应
            switch ($ln[0]) {
                case '*':
                    $r[] = $this->parseLine($ln, $strSeqKey, $strSeq);
                    if (! $tag) {
                        $readMore = false;
                    }
                    break;
                case $this->tagName:
                    $r[] = $this->parseLine($ln);
                    if ($tag) {
                        $readMore = false;
                    } else {}
                    break;
                case '+':
                    $r[] = $this->parseLine($ln);
                    $readMore = false;
                    break;
                default:
                    $r[] = $ln;
                    break;
            }
        }
        $ln = null;
        if (empty($r)) {
            throw new \Exception('no response', 404);
        }
        
        $last = $r[count($r) - 1];
        if (isset($last[0]) && $last[0] == '+') {
            // 继续发送请求数据
        } else {
            if ($tag) {
                if (! isset($last[0]) || strcasecmp($last[0], $tag) != 0) {
                    throw new \Exception($tag .' tag no match:['. $this->lastSend . $this->sock->uri .']', 404);
                }
            } else {
                if (! isset($last[0]) || strcasecmp($last[0], '*') != 0) {
                    throw new \Exception('untag no match');
                }
            }
            if (isset($last[1])) {
                // 处理响应出错的情况
                if (strcasecmp($last[1], 'bad') == 0) {
                    throw new \Exception(implode(' ', $last) .'['. $this->lastSend .']');
                } elseif (strcasecmp($last[1], 'no') == 0) {
                    if (substr($this->lastSend, -5) == '(UID)') {
                        // no uid
                        $r = [];
                    } else {
                        throw new \Exception(implode(' ', $last) .'['. $this->lastSend .']');
                    }
                }
            }
            // $this->currentCommand = null;
        }
        $last = null;
        return $r;
    }

    private function readSequence(&$ln, &$strSeq, $seqLength)
    {
        // 对于字符串序列，读取完整内容后再拼接响应
        $readLen = 0;
        $st = microtime(true);
        // 网络请求多次读取字符串序列内容，直到读好为止
        while ($readLen < $seqLength) {
            $sb = $this->sock->read($seqLength - $readLen);
            if (isset($sb[0])) {
                $strSeq .= $sb;
                $readLen = strlen($strSeq);
            }
            if ((microtime(true) - $st) > $this->timeout) {
                throw new \Exception('read sequence timeout');
            }
        }
        // 读取字符串序列后的剩余命令
        $leftLn = rtrim($this->sock->readLine());
        $ln = $ln . $leftLn;
    }

    private function parseLine(&$ln, $strSeqKey = null, $strSeq = null)
    {
        $p = [];
        $stack = [];
        $token = '';
        $escape = false;
        $inQuote = false;
        for ($i = 0, $n = strlen($ln); $i < $n; ++ $i) {
            $ch = $ln[$i];
            if ($ch == '"') {
                // 处理双引号括起的字符串
                if (! $inQuote) {
                    $inQuote = true;
                } else {
                    $inQuote = false;
                }
            } elseif ($inQuote) {
                // 对于括起的字符串，处理双引号转义
                if ($ch == '\\') {
                    if (! $escape && isset($ln[$i + 1]) && $ln[$i + 1] == '"') {
                        $token .= '"';
                        $i++;
                    } else {
                        $token .= $ch;
                        $escape = ! $escape;
                    }
                } else {
                    $token .= $ch;
                }
            } elseif ($ch == ' ' || $ch == '(' || $ch == ')' || $ch == '[' || $ch == ']') {
                // 处理子列表
                if (isset($token[0])) {
                    // 将字符串序列标识：{length}，替换为真实字符串
                    if ($strSeqKey && $token == $strSeqKey) {
                        $p[] = $strSeq;
                    } else {
                        $p[] = $token;
                    }
                    $token = '';
                }
                if ($ch == '(' || $ch == '[') {
                    $p[] = [];
                    $stack[] = & $p;
                    $p = & $p[count($p) - 1];
                } elseif ($ch == ')' || $ch == ']') {
                    $p = & $stack[count($stack) - 1];
                    array_pop($stack);
                }
            } else {
                // 处理字符串字面量
                $token .= $ch;
            }
        }
        if (isset($token[0])) {
            // 将字符串序列标识：{length}，替换为真实字符串
            if ($strSeqKey && $token == $strSeqKey) {
                $p[] = $strSeq;
            } else {
                $p[] = $token;
            }
        }
        return $p;
    }
}

class BaseSocket
{

    const DEFAULT_READ_SIZE = 8192;
    const CRTL = "\r\n";
    public $uri = null;
    private $timeout = null;
    private $sock = null;
    private $connected = false;

    public function __construct(string $uri, int $timeout)
    {
        $this->uri = $uri;
        $this->timeout = $this->formatTimeout($timeout);
    }
    
    public function startTLS()
    {
        $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }
        $crypto_ok = stream_socket_enable_crypto($this->sock, true, $crypto_method);
        return (bool) $crypto_ok;
    }

    protected function startSSL()
    {
        $crypto_ok = stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_ANY_CLIENT);
        return (bool) $crypto_ok;
    }

    protected function proxyConnect($proxy, $host, $port)
    {
        fwrite($this->sock, pack("C4", 0x05, 0x02, 0x00, 0x02));
        $ret = fread($this->sock, 512);
        if ($ret == pack('C2', 0x05, 0x00)) {
        } elseif ($ret !== pack('C2', 0x05, 0x02)) {
            throw new \Exception('socks服务器不支持密码认证模式['. $this->uri .']');
        } else {
            fwrite($this->sock, pack('C2', 0x01, strlen($proxy['username'])) . $proxy['username'] . pack('C1', strlen($proxy['password'])) . $proxy['password']);
            if (fread($this->sock, 512) !== pack('C2', 0x01, 0x00)) {
                throw new \Exception('socks服务器账号密码认证失败['. $this->uri .']');
            }
        }
        fwrite($this->sock, pack("C5", 0x05, 0x01, 0x00, 0x03, strlen($host)) . $host . pack("n", $port));
        if (substr(fread($this->sock, 512), 0, 4) !== pack('C4', 0x05, 0x00, 0x00, 0x01)) {
            throw new \Exception('socks服务器连接imap服务器失败['. $this->uri .']');
        }
    }

    public function connect(array $params, int $timeout)
    {
        if ($this->connected) {
            $this->close();
        }
        $errno = 0;
        $error = '';
        $secure = $params['secure'] ?? 'ssl';
        $retryTimes = $params['retryTimes'] ?? 1;
        $connTimeout = $this->formatTimeout($timeout, $this->timeout);
        $options = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        $socket_context = stream_context_create($options);
        if (isset($params['proxy'])) {
            $this->uri = $params['proxy']['host'] . ':' . $params['proxy']['port'];
        }
        for ($i = 0; $i < $retryTimes; ++ $i) {
            $this->sock = stream_socket_client($this->uri, $errno, $error, $connTimeout, STREAM_CLIENT_CONNECT, $socket_context);
            if ($this->sock) {
                break;
            }
        }
        if (! $this->sock) {
            throw new \Exception('socks5服务器连接失败,' . $errno . ':' . $error . '['. $this->uri .']');
        }
        if (isset($params['proxy'])) {
            $this->proxyConnect($params['proxy'], $params['gateway'], $params['port']);
        }
        if ($secure == 'ssl') {
            if (! $this->startSSL()) {
                throw new \Exception('开启ssl失败['. $this->uri .']');
            }
        }
        stream_set_timeout($this->sock, $this->timeout);
        $this->connected = true;
    }

    public function read($size)
    {
        $buf = fread($this->sock, $size);
        if ($buf === false) {
            $this->checkReadTimeout();
        }
        return $buf;
    }

    public function readLine()
    {
        $buf = '';
        while (true) {
            $s = fgets($this->sock, self::DEFAULT_READ_SIZE);
            if ($s === false) {
                $this->checkReadTimeout();
                break;
            }
            $n = strlen($s);
            if (! $n) {
                break;
            }
            $buf .= $s;
            if ($s[$n - 1] == "\n") {
                break;
            }
        }
        $s = null;
        return $buf;
    }

    public function readAll()
    {
        $buf = '';
        while (true) {
            $s = fread($this->sock, self::DEFAULT_READ_SIZE);
            if ($s === false) {
                $this->checkReadTimeout();
            }
            if (empty($s)) {
                break;
            }
            $buf .= $s;
        }
        $s = null;
        return $buf;
    }

    public function write($s)
    {
        $n = strlen($s);
        $w = 0;
        while ($w < $n) {
            $buf = substr($s, $w, self::DEFAULT_READ_SIZE);
            $r = fwrite($this->sock, $buf);
            if ($r === false) {
                $this->close();
                break;
            }
            $w += $r;
        }
    }

    public function writeLine($s)
    {
        $this->write($s . self::CRTL);
    }

    public function close()
    {
        if ($this->connected) {
            $meta = stream_get_meta_data($this->sock);
            if (empty($meta['timed_out'])) {
                fclose($this->sock);
            }
            $this->connected = false;
        }
    }

    private function formatTimeout($timeout, $default = null)
    {
        $t = intval($timeout);
        if ($t <= 0) {
            if (! $default) {
                $t = ini_get('default_socket_timeout');
            } else {
                $t = $default;
            }
        }
        return $t;
    }

    private function checkReadTimeout()
    {
        $meta = stream_get_meta_data($this->sock);
        if (! empty($meta['timed_out'])) {
            $this->close();
            throw new \Exception('server closed connection', 404);
        }
    }
}
