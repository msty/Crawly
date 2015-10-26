<?php
/**
 * Website scanner, command line interface
 */

require_once('vendor/autoload.php');

printf("Website scanner\n");

// show usage information, when no arguments passed
if ($argc < 3) {
    printf("Usage: %s <url> [type]\n", $argv[0]);
    printf("\nArguments:\n");
    printf("\t<url>\twebsite URL to scan\n");
    printf("\t[type]\turl - retrieve URLs only (default)\n");
    printf("\t\tsections - retrieve URLs with detailed info on sections\n");
    exit(1);
}

$defaults = [
    'type' => 'urls',
    'host' => '',
    'sections' => [],
];
$request = array_merge($defaults, [
    'host' => $argv[1],
    'type' => $argv[2]
]);

// initialize scanner
$scanner = new \Scanner\Scanner($request['host'], new \Scanner\Formatter\UrlListFormatter());
$urls = $scanner->getResult();

// output results
printf(
    "\nScan Results for %s:\n%s\n",
    $request['host'],
    print_r($urls, true)
);
