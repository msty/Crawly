<?php

namespace Scanner\Request;

use Scanner\Html\HTMLHelper;
use Scanner\Page;

/**
 * Class RequestHelper
 * @package Scanner\Request
 */
class RequestHelper
{
    /**
     * @var array
     */
    protected $lastInfo = [];

    /**
     * @var HTMLHelper
     */
    protected $htmlHelper = null;

    /**
     * @var resource[]
     */
    protected $chs = [];

    /**
     * @var Page[]
     */
    protected $pages = [];

    /**
     * @var array
     */
    protected $headers = [];

    /**
     * @var array
     */
    protected $contents = [];

    /**
     * @var array
     */
    protected $lengths = [];

    /**
     * @var callable[]
     */
    protected $successCallbacks = [];

    /**
     * @var string
     */
    const USER_AGENT = 'Mozilla/5.0 (Windows NT 6.1; rv:29.0) Gecko/20100101 Firefox/29.0';

    /**
     * @var int
     */
    const REQUEST_TIMEOUT = 10;

    /**
     * @var int
     */
    const CONNECT_TIMEOUT = 11;

    public function __construct()
    {
    }

    /**
     * @param string $url
     *
     * @return string
     */
    public function fetchMainUrl($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        $content = $this->curl_exec_follow($ch);
        $this->lastInfo = curl_getinfo($ch);
        curl_close($ch);
        return $content;
    }

    /**
     * @return array
     */
    public function getLastInfo()
    {
        return $this->lastInfo;
    }

    /**
     * @param $callable
     */
    public function addSuccessCallback($callable)
    {
        $this->successCallbacks[] = $callable;
    }

    public function removeSuccessCallbacks()
    {
        $this->successCallbacks = [];
    }

    /**
     * @param resource $ch
     * @param int      $maxRedirect
     *
     * @return bool|mixed
     */
    protected function curl_exec_follow($ch, $maxRedirect = 5)
    {
        $mr = $maxRedirect === null ? 5 : intval($maxRedirect);
        if ($mr > 0) {
            $newurl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

            $rch = curl_copy_handle($ch);
            curl_setopt($rch, CURLOPT_HEADER, 1);
            curl_setopt($rch, CURLOPT_NOBODY, 1);
            curl_setopt($rch, CURLOPT_FORBID_REUSE, 0);
            curl_setopt($rch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($rch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            curl_setopt($rch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            do {
                curl_setopt($rch, CURLOPT_URL, $newurl);
                $header = curl_exec($rch);
                if (curl_errno($rch)) {
                    $code = 0;
                } else {
                    $code = curl_getinfo($rch, CURLINFO_HTTP_CODE);
                    if ($code == 301 || $code == 302) {
                        preg_match('/Location:(.*?)\n/', $header, $matches);
                        $newurltemp = trim(array_pop($matches));

                        // absolute url
                        if (strlen($newurltemp) > 0 && $newurltemp{0} == '/') {
                            $newurl = 'http://' . parse_url($newurl, PHP_URL_HOST) . $newurltemp;
                        }
                        // relative url
                        else if (mb_strpos($newurltemp, '://') === FALSE) {
                            if ($newurl == rtrim($newurl, '/')) {
                                $path = parse_url($newurl, PHP_URL_PATH);
                                $newpath = mb_substr($path, 0, mb_strrpos($path, '/'));
                                $newurl = 'http://' . parse_url($newurl, PHP_URL_HOST) . $newpath . '/';
                            }
                            $newurl .= $newurltemp;
                        }
                        // url with domain
                        else {
                            $newurl = $newurltemp;
                        }
                    } else {
                        $code = 0;
                    }
                }
            } while ($code && --$mr);
            curl_close($rch);
            if (!$mr) {
                return false;
            }
            curl_setopt($ch, CURLOPT_URL, $newurl);
        }
        return curl_exec($ch);
    }

    /**
     * @param array$urls
     *
     * @return Page[]
     */
    public function fetchUrls($urls)
    {
        $cmh = $this->prepareMultiRequest($urls);
        $chsInProgress = $this->chs;
        do {
            curl_multi_select($cmh);
            curl_multi_exec($cmh, $active);
            while (!($info = curl_multi_info_read($cmh)) === false) {
                if ($info['msg'] !== CURLMSG_DONE) {
                    continue;
                }
                $index = array_search($info['handle'], $chsInProgress);
                if ($index === false) {
                    continue;
                }
                $this->onComplete($index);
                curl_multi_remove_handle($cmh, $chsInProgress[$index]);
                unset($chsInProgress[$index]);
            }
        } while ($active > 0);
        return $this->pages;
    }

    /**
     * Prepares arrays for multi request
     *
     * @param array $urls
     *
     * @return resource
     */
    protected function prepareMultiRequest($urls)
    {
        $urlsCount = count($urls);
        $this->headers = array_fill(0, $urlsCount + 1, '');
        $this->contents = array_fill(0, $urlsCount + 1, '');
        $this->lengths = array_fill(0, $urlsCount + 1, 0);
        $this->chs = [];
        $this->pages = [];
        $cmh = curl_multi_init();

        for ($t = 0; $t < $urlsCount; $t++) {
            $this->pages[$t] = new Page();
            $this->chs[$t] = curl_init();

            $this->pages[$t]->setUrl($urls[$t]);
            curl_setopt($this->chs[$t], CURLOPT_URL, $urls[$t]);

            foreach ($this->getMultiCurlOptions() as $option => $value) {
                curl_setopt($this->chs[$t], $option, $value);
            }
            curl_multi_add_handle($cmh, $this->chs[$t]);
        }
        return $cmh;
    }

    protected function getMultiCurlOptions()
    {
        return [
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_WRITEFUNCTION => [$this, "writefn"],
            CURLOPT_HEADERFUNCTION => [$this, "headerfn"],
            CURLOPT_FOLLOWLOCATION => 1,
        ];
    }

    /**
     * @return HTMLHelper
     */
    protected function getHtmlHelper()
    {
        if ($this->htmlHelper === null) {
            $this->htmlHelper = new HTMLHelper();
        }
        return $this->htmlHelper;
    }

    /**
     * функция для ограничения количества скачиваемого контента
     *
     * @param $ch
     * @param $chunk
     *
     * @return int
     */
    public function writefn($ch, $chunk)
    {
        $index = array_search($ch, $this->chs);
        $chunkLength = strlen($chunk);

        if (($this->pages[$index]->length() + $chunkLength) >= 100000) {
            $this->pages[$index]->setContent(
                $this->pages[$index]->getContent() . substr($chunk, 0, 100000 - $this->pages[$index]->length())
            );
            return -1;
        }

        $this->pages[$index]->setContent(
            $this->pages[$index]->getContent() . $chunk
        );
        return strlen($chunk);
    }

    /**
     * функция для сохранения заголовков
     *
     * @param $ch
     * @param $chunk
     *
     * @return int
     */
    public function headerfn($ch, $chunk)
    {
        $index = array_search($ch, $this->chs);
        $this->pages[$index]->setHeader(
            $this->pages[$index]->getHeader() . $chunk
        );
        return strlen($chunk);
    }

    /**
     * функция для успешного завершения запроса
     *
     * @param int $index
     */
    protected function onComplete($index)
    {
        $this->pages[$index]->setCurlInfo(curl_getinfo($this->chs[$index]));
        $contentType = $this->pages[$index]->getCurlInfo()['content_type'];
        $this->pages[$index]->setContent(
            $this->getHtmlHelper()->toUTF8($this->pages[$index]->getContent(), $contentType)
        );

        // extracting content by default. user can pass callback to change this
        foreach ($this->successCallbacks as $callback) {
            if (is_callable($callback)) {
                call_user_func($callback, $this->pages[$index]);
            }
        }
    }
}
