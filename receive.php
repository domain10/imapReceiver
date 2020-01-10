<?php
include './lib/BaseReceiver.php';

$config = [
    'gateway' => 'imap-mail.outlook.com',
    'port' => '993',
    'account' => 'jingskjc@outlook.com',
    'password' => 'snh87BUJH',
    'proxy' => [
        'host' => '140.143.153.29',
        'port' => '34502',
        'username' => 'rondaful',
        'password' => 'wuchuwei'
    ],
//    'mailbox' => 'Sent'
];
$imap = \lib\BaseReceiver::getInstance($config);

// 获取邮件id
$ls = $imap->fetch(0, ['uid']);
foreach($ls as $mailid){
	//$data = $imap->fetchHeader($mailid);
	//$head = imap_rfc822_parse_headers($data);
	
	$mailStructure = $imap->fetchStructure($mailid);
	//$body = $imap->fetchBody($mailid, null);
    var_dump($mailStructure);
    break;
}