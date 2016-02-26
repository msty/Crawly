<?php

namespace Scanner\Formatter;

use Scanner\Helpers\WebsiteScannerHelper;
use Scanner\Page;

/**
 * Interface FormatterInterface
 * @package Scanner\Formatter
 */
interface FormatterInterface
{
    /**
     * Passed as a callback to curl helper to be executed when handle finishes downloading content of the url
     *
     * @param Page $page
     */
    public function successCallback(Page $page);

    /**
     * @return mixed
     */
    public function getResult();

    /**
     * @return string
     */
    public function getFocus();

    /**
     * @param string $focus
     * @return $this
     */
    public function setFocus($focus);

    /**
     * @param WebsiteScannerHelper $scannerHelper
     * @return $this
     */
    public function setScannerHelper(WebsiteScannerHelper $scannerHelper);

    /**
     * @param array $urls
     * @return $this
     */
    public function addUrls(array $urls);

    /**
     * @param string $url
     * @return bool
     */
    public function addUrl($url);
}
