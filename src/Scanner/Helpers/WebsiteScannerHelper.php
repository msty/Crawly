<?php

namespace Scanner\Helpers;

use Scanner\Formatter\FormatterInterface;
use Scanner\Page;
use Scanner\Request\RequestHelper;
use Scanner\Url\UrlHelper;

/**
 * Class WebsiteScannerHelper
 * @package Scanner\Helpers
 */
class WebsiteScannerHelper
{
    /**
     * @var WebsiteProgressHelper
     */
    protected $progressHelper;

    /**
     * @var UrlHelper
     */
    protected $urlHelper;

    /**
     * @var RequestHelper
     */
    protected $requestHelper;

    /**
     * @var FormatterInterface
     */
    protected $formatter;

    /**
     * @var int
     */
    protected $totalResponseLength;

    /**
     * @var int
     */
    protected $totalResponseCount;

    /**
     * @var string
     */
    protected $focus;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var bool
     */
    protected $www = false;

    /**
     * @var bool
     */
    protected $https = false;

    /**
     * @var bool
     */
    protected $initialized = false;

    /**
     * @param string $url
     * @return bool
     */
    public static function validateInputUrl($url)
    {
        $url = trim($url);
        if (mb_strpos($url, '.') === false) {
            return false;
        }

        // add schema if missing
        if (mb_stripos($url, 'http') !== 0) {
            $url = 'http://' . $url;
        }

        if (!parse_url($url, PHP_URL_HOST)) {
            return false;
        }
        return true;
    }

    /**
     * @param string $url
     * @param FormatterInterface $formatter
     * @throws \Exception
     */
    public function __construct($url, FormatterInterface $formatter)
    {
        $formatter->setScannerHelper($this);
        $this->setFormatter($formatter);
        $this->setProgressHelper(new WebsiteProgressHelper());
        $this->parseInputUrl($url);
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->getFormatter()->getResult();
    }

    /**
     * Passed as a callback to curl helper to be executed when handle finishes downloading content of the url
     *
     * @param Page $page
     */
    public function genericSuccessCallback(Page $page)
    {
        $this->trackResponseLength($page->length());

        $path = $this->getUrlHelper()->getPath($page->getUrl()) ?: '/';
        $urls = $this->getValidLinks($page->getContent(), $path);
        $this->getProgressHelper()->addUrls($urls);
    }

    /**
     * Track average response length of received content to adjust amount of requests per stack
     *
     * @param int $length length of content received
     */
    public function trackResponseLength($length)
    {
        $this->totalResponseLength += $length;
        $this->totalResponseCount++;
    }

    /**
     * @param int $maxPages maximum amount of pages to fetch
     */
    public function scan($maxPages = 100)
    {
        if (!$this->getInitialized()) {
            $this->startScan();
        }

        while ($this->getProgressHelper()->hasNotVisitedPages() && $this->totalResponseCount < $maxPages) {
            $this->getRequestHelper()->fetchUrls(
                $this->prepareUrlsForRequest(
                    $this->getProgressHelper()->getNotVisitedUrls(min(25, $maxPages - $this->totalResponseCount))
                )
            );
        }
    }

    /**
     * Starts scanning by fetching main page and website map
     */
    public function startScan()
    {
        $urls = [
            ($this->getHttps() ? 'https://' : 'http://') . $this->getHost(),
            ($this->getHttps() ? 'https://' : 'http://') . 'www.' . $this->getHost(),
            ($this->getHttps() ? 'https://' : 'http://') . $this->getHost() . '/sitemap.xml',
            ($this->getHttps() ? 'https://' : 'http://') . 'www.' . $this->getHost() . '/sitemap.xml',
        ];
        // set empty callback, because we manually handle output
        $this->getRequestHelper()->removeSuccessCallbacks();
        $pages = $this->getRequestHelper()->fetchUrls($urls);
        $this->getProgressHelper()->addVisitedUrl('/');

        // parsing site map xml if found
        $this->parseSiteMap($pages[2]->getContent());
        $this->parseSiteMap($pages[3]->getContent());

        // looking for website map link on main page
        $links = $this->getValidLinks($pages[0]->getContent());
        $linksWWW = $this->getValidLinks($pages[1]->getContent());
        if (count($linksWWW) > count($links)) {
            $this->setWWW(true);
        }
        $links = array_merge($links, $linksWWW);
        $siteMapLinks = $this->findSiteMapLinks($links);
        $this->getProgressHelper()->addUrls($links);

        // if site map links are found, request them as usual with high priority
        if ($siteMapLinks) {
            $this->getProgressHelper()->addUrls($siteMapLinks, 1000);
        }

        $this->getRequestHelper()->addSuccessCallback([$this, 'genericSuccessCallback']);
        $this->getRequestHelper()->addSuccessCallback([$this->getFormatter(), 'successCallback']);
        $this->setInitialized(true);
    }

    /**
     * @param array $links
     *
     * @return array
     */
    protected function findSiteMapLinks(array $links)
    {
        $return = [];
        $keyWordsText = ['site', 'map', 'карта', 'сайта'];
        $keyWordsHref = ['site', 'map'];
        foreach ($links as $link) {
            $matched = 0;
            $href = mb_strtolower($link['href']);
            foreach ($keyWordsHref as $word) {
                if (mb_strpos($href, $word) !== false) {
                    $matched++;
                }
            }
            if ($matched >= 2) {
                $return[] = $link['href'];
                continue;
            }

            $matched = 0;
            $text = mb_strtolower($link['text']);
            foreach ($keyWordsText as $word) {
                if (mb_strpos($text, $word) !== false) {
                    $matched++;
                }
            }
            if ($matched >= 2) {
                $return[] = $link['href'];
            }
        }
        return $return;
    }

    /**
     * @param array $urls
     *
     * @return array
     */
    protected function prepareUrlsForRequest(array $urls)
    {
        $return = [];
        foreach ($urls as $url) {
            $return[] = $this->getHostWithSchema() . $url;
        }
        return $return;
    }

    /**
     * @param string $html
     * @param string $currentPath
     *
     * @return array
     */
    public function getValidLinks($html, $currentPath = '/')
    {
        $links = $this->extractLinks($html);
        $linksCount = count($links);
        for ($i = 0; $i < $linksCount; $i++) {
            $links[$i]['href'] = $this->getUrlHelper()->toAbsolutePath($links[$i]['href'], $currentPath, $this->getHost());
            if (!$links[$i]['href']) {
                unset($links[$i]);
            }
        }
        return array_values($links);
    }

    /**
     * @param $html
     * @return array
     */
    public function extractLinks($html)
    {
        $links = array();
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $nodes = $dom->getElementsByTagName('a');
        for ($i = $nodes->length - 1; $i >= 0; $i--) {
            $href = $nodes->item($i)->getAttribute('href');
            if (empty($href)) {
                continue;
            }
            $links[] = [
                'href' => $href,
                'text' => $nodes->item($i)->nodeValue,
            ];
        }
        $links = array_values($links);
        return $links;
    }

    /**
     * @param string $html
     *
     * @return array
     */
    public function parseSiteMap($html)
    {
        $xml = @simplexml_load_string(trim($html), null, LIBXML_NOCDATA);
        if ($xml === false) {
            return false;
        }
        $map = json_decode(json_encode($xml), true);
        if (!isset($map['url']) || !is_array($map['url'])) {
            return false;
        }

        foreach ($map['url'] as $url) {
            if (!isset($url['loc'])) {
                continue;
            }
            $info = $this->getUrlHelper()->getFullInfo($url['loc']);
            if ($info['host'] != $this->getHost()) {
                continue;
            }
            $this->getFormatter()->addUrl($info['fullPath']);
        }
    }

    public function setHost($host)
    {
        $this->host = $host;
        $this->getProgressHelper()->setHost($host);
    }

    public function getHostWithSchema()
    {
        return ($this->https ? 'https://' : 'http://') . ($this->www ? 'www.' : '') . $this->host;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param bool $www
     *
     * @return $this
     */
    public function setWWW($www)
    {
        $this->www = $www;
        return $this;
    }

    /**
     * @return string
     */
    public function getFocus()
    {
        return $this->focus;
    }

    /**
     * @param string $focus
     *
     * @return $this
     */
    public function setFocus($focus)
    {
        $focus = trim($focus);
        if (empty($focus) || $focus == '/') {
            return $this;
        }
        if ($focus{0} != '/') {
            $focus = $this->getUrlHelper()->getPath($focus);
        }

        // skip focus if it points to sitemap
        if (mb_strpos($focus, 'sitemap') !== false) {
            return $this;
        }

        $this->focus = $focus;
        $this->getProgressHelper()->setFocus($focus);
        $this->getFormatter()->setFocus($focus);
        return $this;
    }

    /**
     * @param string $url
     *
     * @return $this
     */
    public function parseInputUrl($url)
    {
        $url = trim($url);
        $this->setHttps(mb_stripos($url, 'https') === 0);

        // add schema if missing
        if (mb_stripos($url, 'http') !== 0) {
            $url = 'http://' . $url;
        }

        // figure www or not
        $encodedUrl = (new \Net_IDNA())->encode($url);
        $host = parse_url($encodedUrl, PHP_URL_HOST);
        if (mb_strpos($host, 'www.') === 0) {
            $this->setWWW(true);
            $host = preg_replace('#^www\.#isu', '', $host);
        }

        $this->setHost($host);
        $this->setFocus($this->getUrlHelper()->getPath($url));
        return $this;
    }

    /**
     * @return bool
     */
    public function getWWW()
    {
        return $this->www;
    }

    /**
     * @param bool $https
     *
     * @return $this
     */
    public function setHttps($https)
    {
        $this->https = $https;
        return $this;
    }

    /**
     * @return bool
     */
    public function getHttps()
    {
        return $this->https;
    }

    /**
     * @param bool $initialized
     *
     * @return $this
     */
    public function setInitialized($initialized)
    {
        $this->initialized = $initialized;
        return $this;
    }

    /**
     * @return bool
     */
    public function getInitialized()
    {
        return $this->initialized;
    }

    /**
     * @param WebsiteProgressHelper $progress
     *
     * @return $this
     */
    public function setProgressHelper(WebsiteProgressHelper $progress)
    {
        $this->progressHelper = $progress;
        return $this;
    }

    /**
     * @return WebsiteProgressHelper
     */
    public function getProgressHelper()
    {
        return $this->progressHelper;
    }

    /**
     * @return UrlHelper
     */
    public function getUrlHelper()
    {
        if ($this->urlHelper === null) {
            $this->urlHelper = new UrlHelper();
        }
        return $this->urlHelper;
    }

    /**
     * @return RequestHelper
     */
    public function getRequestHelper()
    {
        if ($this->requestHelper === null) {
            $this->requestHelper = new RequestHelper();
            $this->requestHelper->addSuccessCallback([$this, 'genericSuccessCallback']);
            $this->requestHelper->addSuccessCallback([$this->getFormatter(), 'successCallback']);
        }
        return $this->requestHelper;
    }

    /**
     * @return FormatterInterface
     */
    public function getFormatter()
    {
        return $this->formatter;
    }

    /**
     * @param FormatterInterface $formatter
     */
    public function setFormatter(FormatterInterface $formatter)
    {
        $this->formatter = $formatter;
    }
}
