<!DOCTYPE html>
<html>
<head lang="en">
    <meta charset="UTF-8">
    <title>Scanner</title>
</head>
<body>
<div>
    <h2>Scan host</h2>
    <form method="POST">
        <input name="host" value="content-watch.ru"/>
        <label>
            <input type="radio" name="type" value="sections" checked/> sections
        </label>
        <label>
            <input type="radio" name="type" value="urls"/> urls
        </label>
        <input type="submit" value="Scan"/>
    </form>
</div>

<?php

require_once('config/bootstrap.php');

use Scanner\Helpers\WebsiteScannerHelper;

if (!empty($_POST)) {

    $defaults = [
        'type' => 'sections',
        'host' => '',
        'sections' => [],
    ];
    $request = array_merge($defaults, array_intersect_key($_POST, $defaults));

    $host = urldecode($request['host']);
    $sections = (array) $request['sections'];
    if (!WebsiteScannerHelper::validateInputUrl($host)) {
        throw new \Exception('Invalid url');
    }

    // initialize scanner
    $scanner = new WebsiteScannerHelper($host);

    // scan up to N pages
    $scanner->scan(5);

    if ($request['type'] === 'sections') {
        // map found pages and internal links to website sections
        $urls = $scanner->getSections($sections);
    } else {
        // return list of found urls
        $urls = $scanner->getUrlsOfSections($sections);
    }
    ?>

    <div>
        <h2>Scan Results</h2>
        <textarea rows="20" cols="100">
            <?php
            print_r($urls);
            ?>
        </textarea>
    </div>
<?php
}
?>

</body>
</html>