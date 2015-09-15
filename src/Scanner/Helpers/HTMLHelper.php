<?php

namespace Scanner\Helpers;

/**
 * Class HTMLHelper
 * @package Helpers
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

    /**
     * @param string $html
     *
     * @return string
     */
    public function cutContent($html)
    {
        $html_copy = $html;
        $html = $this->prepareHTML($html);
        $r = new \Readability($html);
        $text = ($r->init() && $r && @$r->articleContent->innerHTML) ? $r->articleContent->innerHTML : '';

        if (empty($text)) {
            full_echo(' [using regex] ');
            $text = $this->cutContentRegex($html_copy);
        }
        // full_echo($text);
        // rage_quit(400);

        $text = preg_replace("|&[^;]{1,6};|isu", " ", $text);
        $text = strip_tags($text);
        $text = str_replace("—", "-", $text);
        $text = str_replace(array(",", "(", ")", "+"), " ", $text);

        $ukr = 'АаБбВвГгҐґДдЕеЄєЖжЗзИиІіЇїЙйКкЛлМмНнОоПпРрСсТтУуФфХхЦцЧчШшЩщЬьЮюЯя';
        $text = preg_replace("#[^a-zA-Zа-яА-ЯёЁйЙ0-9" . $ukr . "\s-]+#isu", " ", $text);
        $text = preg_replace("#(^|\s)[^a-zA-Zа-яА-Я0-9" . $ukr . "]+($|\s)#isu", " ", $text);

        $text = preg_replace("#\s{2,}#isu", " ", $text);
        $text = mb_strtolower($text);
        return htmlspecialchars($text);
    }

    /*
        Tag attributes like xml:lang="en" kill Apache silently without meaningful logs
        Killing them with this function
    */
    public function prepareHTML($html)
    {
        $html = $this->thinOutHtml($html);

        if (!mb_strpos($html, '</body')) {
            $html .= '</body>';
        }
        if (!mb_strpos($html, '</html')) {
            $html .= '</html>';
        }

        /*
        // remove ALL attributes
        $html = preg_replace_callback('#<([^\s>]+)[^>]*>#isu', function($m) {
            if ($m[1] != 'meta' and $m[1] != '!DOCTYPE') {
                return '<' . $m[1] . '>';
            }
            return $m[0];
        }, $html);
        */

        // remove ALL attributes, but leave id and class alone for Readability
        $dom = new \DOMDocument();

        $html = preg_replace("#(<head[^>]*>)#", '\\1<meta http-equiv="Content-Type" content="text/html; charset=utf-8">', $html, 1);
        // $dom->loadHTML( mb_convert_encoding('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html, 'HTML-ENTITIES', 'UTF-8') );

        @$dom->loadHTML( mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8') );
        $nodes = $dom->getElementsByTagName('*');
        $manual = array(); // save namespaced (xml:lang="ru") attributes for manual deletion (DOM can't)
        for ($i = $nodes->length; --$i >= 0;) {
            if (mb_strtolower($nodes->item($i)->nodeName) == 'meta') {
                continue;
            }
            $attributes = $nodes->item($i)->attributes;
            $min_length = 0;
            while ($attributes->length > $min_length) {
                if ($attributes->item($min_length)->name == 'id' or $attributes->item($min_length)->name == 'class') {
                    $min_length++;
                    continue;
                }
                if (!$nodes->item($i)->removeAttribute( $attributes->item($min_length)->name )) {
                    $manual[] = array($attributes->item($min_length)->name, $attributes->item($min_length)->value);
                    $min_length++;
                }
            }
        }
        $html = html_entity_decode($dom->saveHTML(), ENT_COMPAT, "UTF-8");
        foreach ($manual as $item) {
            $html = str_replace($item[0] . '="' . $item[1] . '"', '', $html);
        }
        return $html;
    }

    /*
        Thin out html from unwanted characters and tags
    */
    public function thinOutHtml($html)
    {
        // shorten content size by removing everything non-interesting
        $html = preg_replace("|&[^;]{1,6};|isu", " ", $html);
        $html = preg_replace("|<script[^>]*>.*?</script>|isu", "", $html);
        $html = preg_replace("|<style[^>]*>.*?</style>|isu", "", $html);
        $html = preg_replace("|<![-][-].*?[-][-]>|isu", "", $html);
        $html = str_replace(
            array('‘', '’', '“', '”', '•', '–', '—', '‚'),
            array("'", "'", '"', '"', '-', '-', '-', ','),
            $html);
        $html = preg_replace("|<([^>]+)>\s*</\\1>|isu", " ", $html);
        $html = preg_replace("#\s{2,}#isu", " ", $html);

        // start with deleting all invailid characters
        $ukr = 'АаБбВвГгҐґДдЕеЄєЖжЗзИиІіЇїЙйКкЛлМмНнОоПпРрСсТтУуФфХхЦцЧчШшЩщЬьЮюЯя';
        $html = preg_replace('#[^'
            . '0-9a-zA-Zа-яА-ЯёЁ' . $ukr
            . '<=>:;!"%&,/_'
            . '\#\[\]\?\|\'\(\)\{\}\*\.\$\^\+\\\-\s'
            . ']+#isu', '', $html);


        return $html;
    }

    private function cutContentRegex($c)
    {
        if (mb_strlen($c) > 200000) {
            $c = mb_substr($c, 0, mb_strpos($c, '>', 200000));
        }
        // step 1
        $c = mb_strtolower($c);
        $c = str_ireplace("&nbsp;", " ", $c);
        $c = preg_replace("|\s{2,}|isu", " ", $c);

        // use only <body>
        $bs = mb_stripos($c, "<body");
        if ($bs) {
            $c = mb_substr($c, $bs + (mb_strpos($c, ">", $bs) - $bs + 1));
        }
        $be = mb_strrpos($c, "</body>");
        if ($be) {
            $c = mb_substr($c, 0, $be);
        }

        // clear major unwanted tags
        $c = preg_replace("|<script[^>]*>.*?</script>|isu", "", $c);
        $c = preg_replace("|<style[^>]*>.*?</style>|isu", "", $c);
        $c = preg_replace("|<![-][-].*?[-][-]>|isu", "", $c);
        #$c = preg_replace("|<form[^>]*>.*?</form>|isu", "", $c);
        $c = preg_replace("|<h(\d+)[^>]*>(.*?)</h\\1>|isu", " \\2 ", $c);

        // clear unwanted symbols
        $c = str_replace(",", " ", $c);
        $c = str_replace("(", " ", $c);
        $c = str_replace(")", " ", $c);
        $c = str_replace("—", "-", $c);
        $c = str_replace("+", " ", $c);
        $c = preg_replace("|&[^;]{1,6};|isu", "", $c);
        $c = preg_replace("|<br\s*/?\s*>|isu", " ", $c);
        $c = preg_replace("|\s{2,}|isu", " ", $c);
        $c = str_replace(array('…', '?', '!', '...', '..'), '.', $c);
        $c = preg_replace("|\s{2,}|isu", " ", $c);

        // cut phones and dates
        $c = preg_replace("|\(\d+\)\s*\d+[ -]*\d+[ -]*\d*[ -]*\d*[ -]*|iu", " ", $c);
        $c = preg_replace("|\d+[ -]*\d+[ -]*\d*[ -]*\d*[ -]*|iu", " ", $c);
        $c = preg_replace("|\d{1,4}\D\d{1,2}\D\d{1,4}|iu", "", $c);
        $c = preg_replace("|\d{1,2}\s*[^\s\d]+\s*\d\d\d\d\s*года|iu", "", $c);

        // clear tags inner content
        $c = preg_replace("|<p[^>]+>|iu", "<p>", $c);
        $c = preg_replace("|<div[^>]+>|iu", "<p>", $c);
        $c = preg_replace("|<td[^>]+>|iu", "<p>", $c);
        #$c = preg_replace("|<tr[^>]+>|iu", "<tr>", $c);
        #$c = preg_replace("|<table[^>]+>|iu", "<table>", $c);
        $c = preg_replace("|<a[^>]*>(.*?)</a>|isu", " <a>\\1</a> ", $c);

        // remove all tags except starting with <[p|div|td|tr|a]
        $c = preg_replace("|<[/]*([^pa/][^>]*)>|iu", "", $c);
        #$c = preg_replace("#</?(table|tr)>#", "", $c);

        // cut everything unnatural
        $ukr = 'АаБбВвГгҐґДдЕеЄєЖжЗзИиІіЇїЙйКкЛлМмНнОоПпРрСсТтУуФфХхЦцЧчШшЩщЬьЮюЯя';
        $c = preg_replace("#[^a-zA-Z0-9а-яёЁА-Я" . $ukr . "<>/. -]+#isu", " ", $c);
        $c = preg_replace("|\s{2,}|isu", " ", $c);

        // normalize all blocks into paragraphs
        #$c = preg_replace("#<(/?)(div|td|p)>#", "<\\1p>", $c);

        // проходим по всем параграфам
        // прочищаем некоторые ссылки и удаляем короткие параграфы
        $j = 0;
        do {
            $c_old = $c;
            $c = preg_replace("#<p>\s*<a>[^<]*</a>\s*</p>#isu", " <p> ", $c); // div (a /a)+ /div

            while (preg_match("#<(p|a)>\s*</\\1>#isu", $c)) {
                $c = preg_replace("#<a>\s*</a>#isu", " ", $c); // empties
                $c = preg_replace("#<a>\s*(</?p>\s*)*</a>#isu", " ", $c); // empties
                $c = preg_replace("#<p>\s*</p>#isu", " <p> ", $c); // empties
            }

            $c = preg_replace("#</?p>(\s*</?p>)+#isu", " <p> ", $c);
            $c = preg_replace("#([^<>])\s*</?p>\s*([^<>])#isu", "\\1 </p><p> \\2", $c);

            // too long content cut
            if (mb_strlen($c) > 100000) {
                #$c = preg_replace("#<a>[^<]+</a>#isu", "", $c);
                $c = preg_replace("#<a>[^<]{,50}</a>#isu", "", $c);
                $c = preg_replace("#<a>([^<]+)</a>#isu", "\\1", $c);
            }

            preg_match_all("#<a>[^<]+</a>#isu", $c, $links);
            reset($links[0]);
            while (list($i, $str) = each($links[0])) {
                $c = str_replace($str, "%" . sprintf("%1$03d", $i), $c); // replacing links with %X
            }

            preg_match_all("#</?p>[^<]+</?p>#isu", $c, $m);
            reset($m[0]);
            while (list($i, $str) = each($m[0])) {
                if ((mb_strlen($str) - mb_substr_count($str, '%') * 5) < 40)
                    $c = str_replace($str, " <p> ", $c); // removing short paragraphs
            }

            reset($links[0]);
            while (list($i, $str) = each($links[0])) {
                $c = str_replace("%" . sprintf("%1$03d", $i), $str, $c); // getting back proper links
            }

            $c = preg_replace("#<(/?)p>[^<]{0,40}<\\1p>#isu", " <p> ", $c);
            $c = (String) trim(preg_replace("#([^<>])\s*</?p>\s*([^<>])#isu", "\\1 </p><p> \\2", $c));
            if (mb_strlen($c) <= 1) {
                continue;
            }
            if ($c{0} != '<') {
                $c = '<p>' . preg_replace("#([^<>])\s*</?p>\s*([^<>])#isu", "\\1 </p><p> \\2", $c) . '</p>';
            }
            $c = preg_replace("|\s{2,}|i", " ", $c);

            if ($c == $c_old) {
                break;
            }
        } while (++$j < 6);

        // finally remove all tags
        $c = preg_replace("|<[^>]*>|isu", " ", $c);

        // cut all unwanted symbols
        $c = str_replace("\r\n", " ", $c);
        $c = str_replace("\n", " ", $c);
        $c = preg_replace("#\s[^a-zA-Zа-яА-ЯёЁ" . $ukr . "0-9]+\s#isu", " ", $c);
        $c = preg_replace("|\s{2,}|i", " ", $c);

        return trim($c);
    }
}
