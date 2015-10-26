<?php

namespace Scanner;
use Scanner\Helpers\WebsiteScannerHelper;

/**
 * Class Scanner
 * @package Scanner
 */
class Scanner
{
    /** @var WebsiteScannerHelper */
    protected $scannerHelper;

    /** @var int */
    protected $scanPages = 100;

    /** @var int */
    protected $scanned = false;

    /**
     * @param $url
     * @param bool|true $persistProgress
     * @throws \Exception
     */
    public function __construct($url, $persistProgress = true)
    {
        $this->scannerHelper = new WebsiteScannerHelper($url, $persistProgress);
        if (!WebsiteScannerHelper::validateInputUrl($url)) {
            throw new \Exception('Invalid url');
        }
    }

    /**
     * @param $filterSections
     * @return array
     */
    public function getUrls($filterSections)
    {
        $this->scan();
        return $this->scannerHelper->getUrlsOfSections($filterSections);
    }

    /**
     * @return array
     */
    public function getSections()
    {
        $this->scan();
        return $this->scannerHelper->getSections();
    }

    protected function scan()
    {
        if (!$this->scanned) {
            $this->scannerHelper->scan($this->scanPages);
            $this->scanned = true;
        }
    }
}
