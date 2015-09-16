<?php

namespace Scanner\Url;

/**
 * Class UrlHelper
 * @package Scanner\Url
 */
class UrlHelper
{
    public function __construct()
    {
    }

    /**
     * @param string $url
     *
     * @return string
     */
    public function processUrl($url)
    {
        if (parse_url($url) === FALSE) {
            return false;
        }
        if (mb_stripos($url, 'http://') !== 0 && mb_stripos($url, 'https://') !== 0) {
            $url = 'http://' . $url;
        }
        return $url;
    }

    /**
     * @param string $url
     *
     * @return string
     */
    public function getHost($url)
    {
        return mb_strtolower(parse_url($url, PHP_URL_HOST));
    }

    /**
     * @param string $url
     *
     * @return string
     */
    public function getPath($url)
    {
        // add schema if missing
        if (mb_stripos($url, 'http') !== 0) {
            $url = 'http://' . $url;
        }

        $info = parse_url($url);
        $path = (empty($info['path']) ? '/' : $info['path']) . (empty($info['query']) ? '' : '?' . $info['query']);
        return $path;
    }

    /**
     * @param string $url
     *
     * @return array
     */
    public function getFullInfo($url)
    {
        $info = parse_url($url);

        $info['fullPath'] = $info['path'] . (empty($info['query']) ? '' : '?' . $info['query']);
        return $info;
    }

    /**
     * @param $href
     * @param string $currentPath
     * @param string $host
     *
     * @return array
     */
    public function toAbsolutePath($href, $currentPath = '/', $host = '')
    {
        $href = trim($href);

        // ignore mailto links
        if (mb_stripos($href, 'mailto:') === 0) {
            return false;
        }

        // check extension
        $end = mb_substr($href, mb_strlen($href) - 4, 4);
        if (in_array($end, array('.jpg', 'jpeg', '.png', '.gif', '.pdf', ',doc', '.xls', '.exe'))) {
            return false;
        }

        // remove comment
        $href = preg_replace('~#.*$~isu', '', $href);
        if (empty($href)) {
            return false;
        }

        // fix shortened links
        if (mb_strpos($href, '//') === 0) {
            $href = 'http:' . $href;
        }

        // absolute url
        if ($href[0] == '/') {
            return $href;
        }

        // full url
        if (mb_strpos($href, '://') !== FALSE) {
            $href = (new \Net_IDNA())->encode($href);
            $linkHost = mb_strtolower(preg_replace('~^www.~isu', '', parse_url($href, PHP_URL_HOST)));
            if ($linkHost != $host) {
                return false;
            }
            return $this->getPath($href);
        }

        // relative path
        $href = preg_replace('~\?.*$~isu', '', $currentPath);
        if ($href[0] == '?') {
            return $currentPath . $href;
        }

        // go one level up for every ../ found
        while (mb_strpos($href, '../') === 0) {
            $parts = explode('/', ltrim($currentPath, '/'));
            array_pop($parts); // last part is always thrown away
            array_pop($parts); // go one level up
            $currentPath = '/' . implode('/', $parts) . '/';
            $href = preg_replace('~^\.\./~isu', '', $href, 1);
        }

        // for relative url we need to throw away last part of current path
        if ($currentPath[ mb_strlen($currentPath) - 1 ] != '/') {
            $parts = explode('/', $currentPath);
            array_pop($parts);
            $currentPath = implode('/', $parts) . '/';
        }
        return $currentPath . $href;
    }
}
