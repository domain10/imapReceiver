<?php
include './lib/PHPMailer.php';
include './lib/SMTP.php';
include './lib/Exception.php';

/**
 * 使用例子
 *smtp服务器地址 端口 加密方式需要一一对应
 *qq邮箱：smtp.qq.com  587对应tls 465对应ssl 账号qq邮箱 密码为qq邮箱设置中获取的授权码
 *网易邮箱：smtp.163.com 465对应ssl 账号为网易邮箱 密码为后台设置的授权码
 *微软outlook邮箱：smtp.office365.com 587对应tls 账号为outlook邮箱 密码为邮箱登陆密码
 *谷歌gmail邮箱：smtp.gmail.com 587对应tls 465对应ssl 账号为gmail邮箱 密码为邮箱登陆密码
 *新浪邮箱：smtp.sina.com 587对应tls 465对应ssl 账号为新浪邮箱 密码为邮箱登陆密码
 */
 
$attachment = '';//文件1路径：1.jpg
$attachment2 = '';//文件2路径：2.jpg
$mail = new \lib\PHPMailer();
// 是否启用smtp的debug进行调试 开发环境建议开启 生产环境注释掉即可 默认关闭debug调试模式
$mail->SMTPDebug = 1;
// 自己定义发邮件的域名
$mail->Helo = 'www.rondaful.com';
// 自己定义发邮件头中的Message-ID 和Message-ID@后面对应的域名匹配正则/^<.*@.*>$/ MessageID为空时 使用generateId方法生成随机字符串
//$mail->MessageID='<132456789@www.abc.com>';
// 使用smtp发送邮件
$mail->isSMTP();
// 使用smtp时，SMTPAuth必须是true
$mail->SMTPAuth = true;
// 链接qq域名邮箱的服务器地址
$mail->Host = 'smtp-mail.outlook.com';
// 设置使用加密方式登录鉴权 tls或者ssl
$mail->SMTPSecure = 'tls';
// 设置连接smtp服务器的远程服务器端口号需要对应 ssl  tls端口
$mail->Port ='587';
// 设置发送的邮件的编码
$mail->CharSet = 'UTF-8';
// 设置发件人昵称 显示在收件人邮件的发件人邮箱地址前的发件人姓名
$mail->FromName = 'abc';
// smtp登录的账号
$mail->Username = 'abc@outlook.com';
// smtp登录的密码 使用生成的授权码
$mail->Password = '123456';
// 设置发件人邮箱地址 同登录账号
$mail->From = 'abc@outlook.com';
// 设置收件人邮箱地址 多个收件人时 多次使用addAddress方法
$mail->addAddress('123@qq.com');
$mail->isHTML();
// 添加该邮件的主题
$mail->Subject = '你好，molie'. date('Y-m-d');
// 添加邮件正文
$mail->Body = date('Y-m-d H:i:s') .'I am sky';
//配置代理信息proxy为空时不使用代理 如果socks 不需要认证 socks服务器会返回 0x05 0x00 需要密码 0x05 0x02
// $mail->proxy = [
// 'proxyHost'=>'代理ip',
// 'proxyPort'=>'端口',
// 'proxyUsername'=>'代理账号',
// 'proxyPassword'=>'代理密码',
// ];
//不需要认证的不需要配置 proxyUsername和proxyPassowrd
$mail->Proxy = [
    'host' => '',
    'port' => ''
];
// 为该邮件添加附件 多个附件时 多次使用addAttachment方法
if($attachment){
$mail->addAttachment($attachment);
}
if($attachment2){
$mail->addAttachment($attachment2);
}
// 发送邮件 返回状态 成功返回true
$status = $mail->send();
if($status){
    echo '发送成功';
}
