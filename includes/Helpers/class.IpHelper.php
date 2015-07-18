<?php

namespace Helpers;

/**
 * Class IpHelper
 * @package Helpers
 */
class IpHelper
{
    /**
     * @var \PDO
     */
    protected $dbh;

    /**
     * @var array
     */
    protected $ips = [];

    /**
     * @var int
     */
    protected $pseudoRandomIndex = 0;

    public function __construct(\PDO $dbh)
    {
        $this->dbh = $dbh;
    }

    /**
     * @return string
     */
    public function getRandomIp()
    {
        $this->prepareIps();
        $index = array_rand($this->ips);
        $ip = $this->ips[$index];
        return $ip[1];
    }

    /**
     * @return array
     */
    public function getPseudoRandomIpAndId()
    {
        $this->prepareIps();
        $return = $this->ips[$this->pseudoRandomIndex];
        $this->advancePseudoRandomIndex();
        return $return;
    }

    public function prepareIps()
    {
        if (!empty($this->ips)) {
            return;
        }
        $sth = $this->dbh->query("select ip, id from iplist where 1 and id > 1");
        while ($item = $sth->fetch()) {
            $this->ips[] = [$item['id'], $item['ip']];
        }
        shuffle($this->ips);
    }

    public function advancePseudoRandomIndex()
    {
        $this->pseudoRandomIndex++;
        if ($this->pseudoRandomIndex >= count($this->ips)) {
            $this->pseudoRandomIndex = 0;
        }
    }
}
