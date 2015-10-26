<?php

namespace Scanner\Formatter;

use Scanner\Helpers\WebsiteScannerHelper;
use Scanner\Page;

/**
 * Class AbstractFormatter
 * @package Scanner\Formatter
 */
abstract class AbstractFormatter implements FormatterInterface
{
    /**
     * @var array
     */
    protected $urls = [];

    /**
     * @var array
     */
    protected $focus;

    /**
     * @var WebsiteScannerHelper
     */
    protected $scannerHelper;

    /**
     * Passed as a callback to curl helper to be executed when handle finishes downloading content of the url
     *
     * @param Page $page
     */
    public abstract function successCallback(Page $page);

    /**
     * @return mixed
     */
    public abstract function getResult();

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
        $this->focus = explode('/', $focus);
        return $this;
    }

    /**
     * @return WebsiteScannerHelper
     */
    public function getScannerHelper()
    {
        return $this->scannerHelper;
    }

    /**
     * @param WebsiteScannerHelper $scannerHelper
     *
     * @return $this
     */
    public function setScannerHelper(WebsiteScannerHelper $scannerHelper)
    {
        $this->scannerHelper = $scannerHelper;
        return $this;
    }

    /**
     * @param array $urls
     *
     * @return $this
     */
    public function addUrls(array $urls)
    {
        foreach ($urls as $url) {
            $this->addUrl($url);
        }
        return $this;
    }

    /**
     * @param string $url
     *
     * @return bool
     */
    public function addUrl($url)
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
        $this->urls[$url] = 1;
        return true;
    }
}
