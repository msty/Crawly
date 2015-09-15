<?php

namespace Scanner;

/**
 * Class Page
 */
class Page
{
    /**
     * @var string
     */
    protected $content;

    /**
     * @var bool
     */
    protected $changed = true;

    /**
     * @var int
     */
    protected $length = 0;

    /**
     * @var array
     */
    protected $curlInfo = [];

    /**
     * @var string
     */
    protected $header = '';

    /**
     * @var string
     */
    protected $url = '';

    public function __construct($content = '')
    {
        $this->setContent($content);
    }

    public function setContent($content)
    {
        $this->content = trim($content);
    }

    public function getContent()
    {
        return $this->content;
    }

    protected function onChange()
    {
        if (!$this->changed) {
            return;
        }
        $this->length = mb_strlen($this->content);
        $this->changed = false;
    }

    public function length()
    {
        $this->onChange();
        return $this->length;
    }

    public function setCurlInfo(array $curlInfo)
    {
        $this->curlInfo = $curlInfo;
    }
    public function getCurlInfo()
    {
        return $this->curlInfo;
    }

    public function setHeader($header)
    {
        $this->header = $header;
    }
    public function getHeader()
    {
        return $this->header;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function getUrl()
    {
        return $this->url;
    }
}
