<?php

namespace Scanner;

/**
 * Class TextTarget
 */
class TextTarget extends Text
{
    /**
     * @var array
     */
    protected $curlInfo = [];

    /**
     * @var string
     */
    protected $header = '';

    /**
     * @var int
     */
    protected $ipdId = null;

    /**
     * @var string
     */
    protected $ip = null;

    /**
     * @var string
     */
    protected $url = '';

    /**
     * Id of the previous check (when pages are checked during regular checking)
     *
     * @var int
     */
    protected $previousCheckId = 0;

    /**
     * Found link legal status
     *
     * @var int
     */
    protected $legal = 0;

    public function __construct($content = '')
    {
        parent::__construct($content);
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

    public function setIpId($ipId)
    {
        $this->ipId = $ipId;
    }
    public function getIpId()
    {
        return $this->ipId;
    }

    public function setIp($ip)
    {
        $this->ip = $ip;
    }
    public function getIp()
    {
        return $this->ip;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }
    public function getUrl()
    {
        return $this->url;
    }

    public function setPreviousCheckId($previousCheckId)
    {
        $this->previousCheckId = $previousCheckId;
    }
    public function getPreviousCheckId()
    {
        return $this->previousCheckId;
    }

    public function setLegal($legal)
    {
        $this->legal = $legal;
    }
    public function getLegal()
    {
        return $this->legal;
    }
}
