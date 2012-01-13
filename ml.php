<?php 
require("config.php")

function pop3_stat($connection)        
{ 
    $check = imap_mailboxmsginfo($connection); 
    return ((array)$check); 
} 
function pop3_list($connection,$message="") 
{ 
    if ($message) 
    { 
        $range=$message; 
    } else { 
        $MC = imap_check($connection); 
        $range = "1:".$MC->Nmsgs; 
    } 
    $response = imap_fetch_overview($connection,$range); 
    foreach ($response as $msg) $result[$msg->msgno]=(array)$msg; 
        return $result; 
} 
function pop3_retr($connection,$message) 
{ 
    return(imap_fetchheader($connection,$message,FT_PREFETCHTEXT)); 
} 
function pop3_dele($connection,$message) 
{ 
    return(imap_delete($connection,$message)); 
} 
function mail_parse_headers($headers) 
{ 
    $headers=preg_replace('/\r\n\s+/m', '',$headers); 
    preg_match_all('/([^: ]+): (.+?(?:\r\n\s(?:.+?))*)?\r\n/m', $headers, $matches); 
    foreach ($matches[1] as $key =>$value) $result[$value]=$matches[2][$key]; 
    return($result); 
} 
function mail_mime_to_array($imap,$mid,$parse_headers=false) 
{ 
    $mail = imap_fetchstructure($imap,$mid); 
    $mail = mail_get_parts($imap,$mid,$mail,0); 
    if ($parse_headers) $mail[0]["parsed"]=mail_parse_headers($mail[0]["data"]); 
    return($mail); 
} 
function mail_get_parts($imap,$mid,$part,$prefix) 
{    
    $attachments=array(); 
    $attachments[$prefix]=mail_decode_part($imap,$mid,$part,$prefix); 
    if (isset($part->parts)) // multipart 
    { 
        $prefix = ($prefix == "0")?"":"$prefix."; 
        foreach ($part->parts as $number=>$subpart) 
            $attachments=array_merge($attachments, mail_get_parts($imap,$mid,$subpart,$prefix.($number+1))); 
    } 
    return $attachments; 
} 
function mail_decode_part($connection,$message_number,$part,$prefix) 
{ 
    $attachment = array(); 

    if($part->ifdparameters) { 
        foreach($part->dparameters as $object) { 
            $attachment[strtolower($object->attribute)]=$object->value; 
            if(strtolower($object->attribute) == 'filename') { 
                $attachment['is_attachment'] = true; 
                $attachment['filename'] = $object->value; 
            } 
        } 
    } 

    if($part->ifparameters) { 
        foreach($part->parameters as $object) { 
            $attachment[strtolower($object->attribute)]=$object->value; 
            if(strtolower($object->attribute) == 'name') { 
                $attachment['is_attachment'] = true; 
                $attachment['name'] = $object->value; 
            } 
        } 
    } 

    $attachment['data'] = imap_fetchbody($connection, $message_number, $prefix); 
    if($part->encoding == 3) { // 3 = BASE64 
        $attachment['data'] = base64_decode($attachment['data']); 
    } 
    elseif($part->encoding == 4) { // 4 = QUOTED-PRINTABLE 
        $attachment['data'] = quoted_printable_decode($attachment['data']); 
    } 
    return($attachment); 
} 


function GUID()
{
    if (function_exists('com_create_guid') === true)
    {
        return trim(com_create_guid(), '{}');
    }

    return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
}

function parseAllTheEmails($txt) {
    preg_match_all("/([a-zA-Z0-9.!#$%&'*+-\/?^_`{|}~]+@[a-zA-Z0-9-.]+\.[a-zA-Z]+)/", $txt, $matches);
    return $matches[1];
}



function addDestination($m) {
    global $mbox;
    echo "addDestination";
}
function forwardMessage($m) {
    global $mbox;
    echo "forwardMessage";    
}

function generateML($m,$k) {
    global $mbox;
    echo "\ngenerateML\n";

    $uuid = GUID();
    echo "\nUUID: ".$uuid;
    echo "\nNow... emails...\n";
    
    $body = imap_fetchbody($mbox, $m["uid"],1,FT_UID);
    
    $emails = parseAllTheEmails($body);
    
    $sql = "INSERT INTO `mailinglists` (`uuid`, `destinations`) VALUES ('$uuid', '".json_encode($emails)."');";
    mysql_query($sql) or die('Could not query: ' . mysql_error());
    echo "\nSQL: ".$sql."\n";
    
    
/*    $headers =  'From: '.$m[].'. "\r\n" .
                'Reply-To: 'oneupml'.$uuid.'@gmail.com' . "\r\n" .
  */          
    
    
    //imap_mail("", "Welcome to your new 1up Mailing List!", "A new 1up Mailing List was created by ".$m["from"]." and your were included in it.\n\nTo start using this mailing list, just reply to this email and everyone will receive your message!")
    
    // outros headers: in-reply-to, Reply-To, Sender
}




/*
 * App Logic
 *
 */
 
mysql_connect($dbhost, $dbuser, $dbpass) or die('Could not connect: ' . mysql_error());
mysql_select_db($dbname); 




$mbox = imap_open("{$mailhost:993/novalidate-cert/ssl}$mailfolder",$mailuser,$mailpass) or die("can't connect: " . imap_last_error());
$msgs = pop3_list($mbox);
echo "<pre>";
//print_r($msgs);

if (count($msgs) == 0) {
    imap_close($mbox);
    die("no messages");
}
    

foreach ($msgs as $k => $m) {
    echo "\n\n################ NEW EMAIL $k #################";
    print_r($m);
    $uuid = false;

    if (preg_match("/oneupml\+([^@]+)@gmail\.com/", $m["to"], $matches)) {
        $uuid = $matches[1];
    }
    
    if ($uuid) {
        if ($m["subject"] == "ADD") {
            addDestination($m);
        } else {
            forwardMessage($m);
        }
    } else if ($m["subject"] == "MAKE") {
    	generateML($m, $k);
    } else {
        echo "useless...";
#        imap_delete($mbox, $k);
#        echo "marked for deletion.";
    }
}


imap_close($mbox, CL_EXPUNGE);






































