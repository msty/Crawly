<?php

namespace Scanner;

use Scanner\Formatter\FormatterInterface;
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
     * @param string $url
     * @param FormatterInterface $formatter
     * @throws \Exception
     */
    public function __construct($url, FormatterInterface $formatter)
    {
        $this->scannerHelper = new WebsiteScannerHelper($url, $formatter);
        if (!WebsiteScannerHelper::validateInputUrl($url)) {
            throw new \Exception('Invalid url');
        }
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        $this->scan();
        return $this->scannerHelper->getResult();
    }

    protected function scan()
    {
        if (!$this->scanned) {
            $this->scannerHelper->scan($this->scanPages);
            $this->scanned = true;
        }
    }
}
