<?php
require_once 'dev.hop.pr/libfeta/tag_parser.php';

function testDOMDocumentWithRealContent($url, $tagName) {
    echo "Fetching $url with DOMDocument...\n";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
        ]
    ]);
    
    $html = @file_get_contents($url, false, $context);
    if (!$html) {
        echo "Failed to fetch content\n";
        return null;
    }
    
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    
    $start = microtime(true);
    $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $elements = $dom->getElementsByTagName($tagName);
    $parseTime = microtime(true) - $start;
    
    $errors = libxml_get_errors();
    
    echo "  - Content length: " . strlen($html) . " bytes\n";
    echo "  - Parse time: " . number_format($parseTime * 1000, 2) . "ms\n";
    echo "  - Found elements: " . $elements->length . "\n";
    echo "  - LibXML errors: " . count($errors) . "\n";
    
    return [
        'count' => $elements->length,
        'time' => $parseTime,
        'errors' => count($errors),
        'contentLength' => strlen($html)
    ];
}

function testCustomParserWithRealContent($url, $tagName) {
    echo "Fetching $url with Custom Parser...\n";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
        ]
    ]);
    
    $html = @file_get_contents($url, false, $context);
    if (!$html) {
        echo "Failed to fetch content\n";
        return null;
    }
    
    $parser = new TagParser(false);
    
    $start = microtime(true);
    $results = $parser->parse($html, [$tagName]);
    $parseTime = microtime(true) - $start;
    
    $errors = $parser->getErrors();
    
    echo "  - Content length: " . strlen($html) . " bytes\n";
    echo "  - Parse time: " . number_format($parseTime * 1000, 2) . "ms\n";
    echo "  - Found elements: " . count($results) . "\n";
    echo "  - Custom errors: " . count($errors) . "\n";
    
    return [
        'count' => count($results),
        'time' => $parseTime,
        'errors' => count($errors),
        'contentLength' => strlen($html)
    ];
}

echo "=== REAL-WORLD PERFORMANCE COMPARISON ===\n\n";

// Test with a smaller, simpler website first
$testUrl = "https://www.craigslist.org";
$tagName = "div";

echo "Testing with $testUrl (parsing '$tagName' tags)\n\n";

$domResults = testDOMDocumentWithRealContent($testUrl, $tagName);
echo "\n";
$customResults = testCustomParserWithRealContent($testUrl, $tagName);

if ($domResults && $customResults) {
    echo "\n=== COMPARISON SUMMARY ===\n";
    echo "Content size: " . number_format($domResults['contentLength']) . " bytes\n";
    echo "DOMDocument: " . number_format($domResults['time'] * 1000, 2) . "ms, {$domResults['count']} elements, {$domResults['errors']} errors\n";
    echo "Custom Parser: " . number_format($customResults['time'] * 1000, 2) . "ms, {$customResults['count']} elements, {$customResults['errors']} errors\n";
    
    if ($customResults['time'] > 0) {
        $speedRatio = $domResults['time'] / $customResults['time'];
        if ($speedRatio > 1) {
            echo "Custom parser is " . number_format($speedRatio, 2) . "x faster\n";
        } else {
            echo "DOMDocument is " . number_format(1/$speedRatio, 2) . "x faster\n";
        }
    }
}