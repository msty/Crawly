## Crawly.net

Crawly is a PHP library that allows you to quickly parse websites and export results in different formats.

Parser starts processing crawled pages instantly, without waiting for the whole batch to load. This ensures memory efficiency and speed.

## Code Example

$scanner = new \Scanner\Scanner($host, new \Scanner\Formatter\UrlListFormatter());
$urls = $scanner->getResult();

## Motivation

Parsers are used in variety of ways, always requiring same core functionality. Crawly separates that core from particular task details.

This allows you to use one of the predefined formatters to solve common tasks AND to extend the library with your own contribution to solve your particular task. All you need is to write a simple formatter following our simple interface.

## Formatters

With Crawly you can export simple URL list, Sitemap, list of sections within a website easily.

If you want to add a new functionality, you just add a formatter and the core keeps working fast and clean.

## Installation

Simply grab it via Composer and it's ready to go

## Contributors

Best way to contribute at the current stage is by writing a formatter for common needs.

However, if you have ideas about how to make parser more efficient or fast, you are welcome as well!
