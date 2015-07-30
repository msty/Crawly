<?php

function my_json_encode($arr)
{
    // Convmap since 0x80 char codes so it takes all multibyte codes (above ASCII 127)
    // So such characters are being "hidden" from normal json_encoding
    array_walk_recursive($arr, function (&$item, $key) {
        if (is_string($item)) {
            $item = mb_encode_numericentity($item, array (0x80, 0xffff, 0, 0xffff), 'UTF-8');
        }
    });
    return mb_decode_numericentity(json_encode($arr), array (0x80, 0xffff, 0, 0xffff), 'UTF-8');
}

function trim_url_length($url, $maxLength = 80)
{
    if (mb_strlen($url) > intval($maxLength)) {
        $length = mb_strrpos_array($url, array('/', '-', '&'), 80 - mb_strlen($url)) + 1;
        $url = mb_substr($url, 0, $length) . '...';
    }
    return $url;
}

function mb_strrpos_array($haystack, $needle, $offset = 0) {
    $return = false;
    if(!is_array($needle))
        $needle = array($needle);
    foreach($needle as $what)
        if(($pos = mb_strrpos($haystack, $what, $offset)) !== false and (!$return or $pos > $return))
            $return = $pos;
    return $return;
}
