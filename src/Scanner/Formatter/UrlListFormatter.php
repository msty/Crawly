<?php

namespace Scanner\Formatter;

use Scanner\Page;

/**
 * Class UrlListFormatter
 * @package Scanner\Formatter
 */
class UrlListFormatter extends AbstractFormatter
{
    /**
     * Passed as a callback to curl helper to be executed when handle finishes downloading content of the url
     *
     * @param Page $page
     */
    public function successCallback(Page $page)
    {
        $path = $this->getScannerHelper()->getUrlHelper()->getPath($page->getUrl()) ?: '/';
        $urls = $this->getScannerHelper()->getValidLinks($page->getContent(), $path);
        $this->addUrls($urls);
    }

    /**
     * @return array
     */
    public function getResult()
    {
        $urls = [];
        foreach ($this->urls as $url => $amount) {
            $urls[] = $this->getScannerHelper()->getHostWithSchema() . $url;
        }
        return $urls;
    }
}
