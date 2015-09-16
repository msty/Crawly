<?php

namespace Scanner\Html;

/**
 * Class HTMLHelper
 * @package Scanner\Html
 */
class HTMLHelper
{
    /**
     * @var array
     */
    protected $restrictions = [];

    public function __construct()
    {
    }

    /**
     * @param string $html
     * @param string $contentType
     * @param string $url
     *
     * @return string
     */
    public function toUTF8($html, $contentType, $url = null)
    {
        $charset = $this->detectEncoding($html, $contentType, $url);
        if ($charset === null) {
            return '';
        }

        /* Convert it if it is anything but UTF-8 */
        if ($charset != 'utf-8') {
            $html = @iconv($charset, 'UTF-8//IGNORE', $html);
            $html = preg_replace('#charset=([^>/\s"]+)#isu', 'charset=utf-8', $html);
        }
        return $html;
    }

    /**
     * @param string $encoding
     *
     * @return string
     */
    public function hotfixEncoding($encoding)
    {
        $encoding = trim(mb_strtolower($encoding));
        if ($encoding === 'utf8') {
            $encoding = 'utf-8';
        }
        return $encoding;
    }

    /**
     * @param string $html
     * @param string $contentType
     * @param string $url
     *
     * @return string
     */
    public function detectEncoding($html, $contentType, $url = null)
    {
        if (mb_strpos($contentType, 'application') !== FALSE) {
            return null;
        }

        /* 1: HTTP Content-Type: header */
        preg_match('@([\w/+]+)(;\s*charset=(\S+))?@i', $contentType, $matches);
        if (isset($matches[3])) {
            return $this->hotfixEncoding($matches[3]);
        }

        /* 2: <meta> element in the page */
        $p = '@<meta\s+http-equiv="?Content-Type"?\s+content="?([\w/]+)(;\s*charset=([^\s"]+))?@i';
        if (preg_match($p, $html, $matches) && isset($matches[3])) {
            return $this->hotfixEncoding($matches[3]);
        }

        $p = '@<meta\s+content="?([\w/]+)(;\s*charset=([^\s"]+))?@i';
        if (preg_match($p, $html, $matches) && isset($matches[3])) {
            return $this->hotfixEncoding($matches[3]);
        }

        if (preg_match('#<meta\s*charset\s*=[^a-z]*([^">\s]+)#i', $html, $matches) && isset($matches[1])) {
            return $this->hotfixEncoding($matches[1]);
        }

        /* 3: <xml> element in the page */
        if (preg_match('@<\?xml[^>]+encoding="([^\s"]+)@si', $html, $matches) && isset($matches[1])) {
            return $this->hotfixEncoding($matches[1]);
        }

        /* 4: PHP's heuristic detection */
        $encoding = mb_detect_encoding($html);
        if ($encoding) {
            return $this->hotfixEncoding($encoding);
        }

        /* 5: Default for HTML */
        if (strstr($contentType, "text/html") === 0) {
            return $this->hotfixEncoding('ISO 8859-1');
        }

        return $this->hotfixEncoding('utf-8');
    }
}
