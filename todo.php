<?php

include "config.php";
$mbox = null;

foreach ($folders as $folder) {
    $mbox = doIt($folder, $mbox, $cfg);

}

imap_close($mbox);

function doIt($folder = '', $mbox = null, $cfg) {
    
    if ($mbox) {
        imap_reopen($mbox, $cfg['url'] . "$folder") or die("can't connect: " . imap_last_error() . "\n");
        ;
    } else {
        $mbox = imap_open("{bxmail.liip.ch:993/ssl/imap4/novalidate-cert/readonly}$folder", $cfg['user'], $cfg['pass']) or die("can't connect: " . imap_last_error() . "\n");
        ;
    }
    
    $MC = imap_check($mbox);
    $labelIds = imap_search($mbox, 'UNDELETED KEYWORD "$Label1"', SE_UID);
    if (! file_exists("data")) {
        mkdir("data");
    }
    if ($folder) {
        $todoidsdat = "data/todoids" . md5($folder) . ".dat";
    } else {
        $todoidsdat = "data/todoids.dat";
    }
    $oldIds = unserialize(file_get_contents($todoidsdat));
    if (! is_array($oldIds)) {
        $oldIds = array();
    }
    $newlabelIds = array_diff($labelIds, $oldIds);
    foreach ($newlabelIds as $k => $v) {
        if (! ($v)) {
            unset($newlabelIds[$k]);
        } else 
            if (in_array($v, $oldIds)) {
                unset($newlabelIds[$k]);
            }
    }
    $started = false;
    $headers = imap_fetch_overview($mbox, implode(",", $newlabelIds), FT_UID);
    foreach ($headers as $id) {
        if (! in_array($id->uid, $oldIds)) {
            $subject = decodeMimeString($id->subject);
            $from = decodeMimeString($id->from);
            $date = decodeMimeString($id->date);
            preg_match("#@([^>]+)#", $from, $matches);
            if ($matches[1]) {
                $subject = $matches[1] . " " . $subject;
            }
            
            $cmd = 'echo \'tell application "OmniFocus" to tell document 1 
            set a to make new inbox task with properties {name:"' . substr(escapeshellarg(str_replace(array("ä", "ö", "ü", ":", "'", '"'), array("ae", "oe", "ue", " ", " ", " "), $subject . ' von ' . $from)), 1, - 1) . '",note:"' . substr(escapeshellarg('Datum ' . $date), 1, - 1) . '"}
            end tell\' | osascript -';
            exec($cmd);
            $oldIds[] = $id->uid;
        }
    
    }
    
    file_put_contents($todoidsdat, serialize($oldIds));
    return $mbox;
}
//return supported encodings in lowercase.
function mb_list_lowerencodings() {
    $r = mb_list_encodings();
    for ($n = sizeOf($r); $n --;) {
        $r[$n] = strtolower($r[$n]);
    }
    return $r;
}

//  Receive a string with a mail header and returns it
// decoded to a specified charset.
// If the charset specified into a piece of text from header
// isn't supported by "mb", the "fallbackCharset" will be
// used to try to decode it.
function decodeMimeString($mimeStr, $inputCharset = 'utf-8', $targetCharset = 'utf-8', $fallbackCharset = 'iso-8859-1') {
    $encodings = mb_list_lowerencodings();
    $inputCharset = strtolower($inputCharset);
    $targetCharset = strtolower($targetCharset);
    $fallbackCharset = strtolower($fallbackCharset);
    
    $decodedStr = '';
    $mimeStrs = imap_mime_header_decode($mimeStr);
    for ($n = sizeOf($mimeStrs), $i = 0; $i < $n; $i ++) {
        $mimeStr = $mimeStrs[$i];
        $mimeStr->charset = strtolower($mimeStr->charset);
        if (($mimeStr == 'default' && $inputCharset == $targetCharset) || $mimStr->charset == $targetCharset) {
            $decodedStr .= $mimStr->text;
        } else {
            $decodedStr .= mb_convert_encoding($mimeStr->text, $targetCharset, (in_array($mimeStr->charset, $encodings) ? $mimeStr->charset : $fallbackCharset));
        
        }
    }
    return $decodedStr;
}
?>
