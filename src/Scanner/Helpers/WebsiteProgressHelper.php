<?php

namespace Scanner\Helpers;

/**
 * Class WebsiteProgressHelper
 * @package Helpers
 */
class WebsiteProgressHelper
{
    /**
     * @var int
     */
    protected $progressId = null;

    /**
     * @var string
     */
    protected $focus = null;

    /**
     * @var string
     */
    protected $host = null;

    /**
     * @var array
     */
    protected $visited = [];

    /**
     * @var array
     */
    protected $notVisited = [];

    public function __construct()
    {
    }

    /**
     * @throws \Exception
     */
    public function saveProgress()
    {
        if ($this->getHost() === null) {
            throw new \Exception('Host must be defined to save progress');
        }

        $fileName = sprintf('%s/tmp/%s.txt', SCANNER_ROOT, $this->getHost());
        $progress = [
            (new \DateTime())->format('c'),
            Functions::my_json_encode($this->visited),
            Functions::my_json_encode($this->notVisited),
        ];
        file_put_contents($fileName, implode("\n", $progress));
    }

    /**
     * @return bool
     *
     * @throws \Exception
     */
    public function loadProgress()
    {
        if ($this->getHost() === null) {
            throw new \Exception('Host must be defined to load progress');
        }

        $fileName = sprintf('%s/tmp/%s.txt', SCANNER_ROOT, $this->getHost());
        if (!file_exists($fileName)) {
            return false;
        }

        $data = file($fileName);
        if (count($data) != 3 || new \DateTime($data[0]) < new \DateTime('now - 1 week')) {
            return false;
        }

        $this->visited = json_decode($data[1], true);
        $this->notVisited = json_decode($data[2], true);
        return true;
    }

    /**
     * @return bool
     */
    public function hasNotVisitedPages()
    {
        if (!$this->getFocus()) {
            return !empty($this->notVisited);
        }

        // if focus is set, find at least one page with focus in the beginning of an url
        foreach ($this->notVisited as $url => $priority) {
            if (mb_strpos($url, $this->getFocus()) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array
     */
    public function getAllUrls()
    {
        return array_merge(array_keys($this->visited), array_keys($this->notVisited));
    }

    /**
     * @param int $limit
     *
     * @return array
     */
    public function getNotVisitedUrls($limit)
    {
        // echo 'getNotVisitedUrls: visited ' . count($this->visited) . ' / ' . count($this->notVisited) . '<br>';
        $return = [];
        arsort($this->notVisited);
        $limit = min($limit, count($this->notVisited));
        foreach ($this->notVisited as $url => $priority) {
            if ($this->getFocus() !== null && mb_strpos($url, $this->getFocus()) !== 0) {
                continue;
            }
            $return[] = $url;
            if (--$limit <= 0) {
                break;
            }
        }

        // remove urls from not visited and consider visited
        foreach ($return as $url) {
            unset($this->notVisited[$url]);
            $this->visited[$url] = 1;
        }
        return $return;
    }

    /**
     * @param array $urls
     * @param int $priority
     *
     * @return $this
     */
    public function addUrls(array $urls, $priority = 1)
    {
        foreach ($urls as $url) {
            $this->addUrl($url, $priority);
        }
        return $this;
    }

    /**
     * @param string $url
     * @param int $priority
     *
     * @return bool
     */
    public function addUrl($url, $priority = 1)
    {
        if (is_array($url)) {
            $url = $url['href'];
        }
        $url = trim($url);
        if ($url === '') {
            $url = '/';
        }
        if ($url{0} != '/' || isset($this->visited[$url])) {
            return false;
        }
        if (!isset($this->notVisited[$url])) {
            $this->notVisited[$url] = 0;
        }
        $this->notVisited[$url] += $priority;
        return true;
    }

    /**
     * @param array $urls
     * @param int $priority
     *
     * @return $this
     */
    public function addVisitedUrls(array $urls)
    {
        foreach ($urls as $url) {
            $this->addVisitedUrl($url);
        }
        return $this;
    }

    /**
     * @param string $url
     *
     * @return bool
     */
    public function addVisitedUrl($url)
    {
        if (is_array($url)) {
            $url = $url['href'];
        }
        $url = trim($url);
        if ($url === '') {
            $url = '/';
        }
        if ($url{0} != '/') {
            return false;
        }
        $this->visited[$url] = 1;
        return true;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param string $host
     *
     * @return $this
     */
    public function setHost($host)
    {
        $this->host = $host;
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
        $this->focus = $focus;
        return $this;
    }
}
