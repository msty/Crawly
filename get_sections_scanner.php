<?php

require_once('includes/preconfig.php');

$url = urldecode($_POST['url']);
$sections = (array) $_POST['sections'];
if (!\Helpers\WebsiteScannerHelper::validateInputUrl($url)) {
    throw new \Exception('Invalid url');
}

// initialize scanner
$scanner = new \Helpers\WebsiteScannerHelper($dbh, $url);

// scan up to N pages
$scanner->scan(25);

// map found pages and internal links to website sections
$urls = $scanner->getSections($sections);

echo my_json_encode(['urls' => $urls]);

