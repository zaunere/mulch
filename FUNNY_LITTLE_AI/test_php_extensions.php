<?php
require_once 'dev.hop.pr/libfeta/tag_parser.php';

// Test using DOMDocument vs custom TagParser
function testWithDOMDocument($html, $tagName) {
    $dom = new DOMDocument();
    
    // Suppress warnings for malformed HTML
    libxml_use_internal_errors(true);
    
    $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    
    $errors = libxml_get_errors();
    $elements = $dom->getElementsByTagName($tagName);
    
    $results = [];
    foreach ($elements as $element) {
        $results[] = [
            'tag' => $tagName,
            'content' => $element->textContent,
            'innerHTML' => $dom->saveHTML($element)
        ];
    }
    
    return [
        'results' => $results,
        'errors' => $errors,
        'count' => $elements->length
    ];
}

function testWithCustomParser($html, $tagName) {
    $parser = new TagParser(false);
    $start = microtime(true);
    $results = $parser->parse($html, [$tagName]);
    $end = microtime(true);
    
    return [
        'results' => $results,
        'errors' => $parser->getErrors(),
        'time' => $end - $start,
        'count' => count($results)
    ];
}

// Test cases
$testCases = [
    'Simple nested' => '<div>Outer <div>Inner content</div> More outer</div>',
    'With attributes' => '<div class="outer">Outer <div id="inner">Inner content</div> More outer</div>',
    'Malformed' => '<div>Start <div>Nested but no close',
    'Complex nested' => '<div><div><div>Deep</div></div><div>Another</div></div>'
];

echo "=== COMPARISON: DOMDocument vs Custom TagParser ===\n\n";

foreach ($testCases as $testName => $html) {
    echo "Test: $testName\n";
    echo "HTML: $html\n\n";
    
    // Test with DOMDocument
    echo "--- DOMDocument Results ---\n";
    $domResults = testWithDOMDocument($html, 'div');
    echo "Found {$domResults['count']} div elements\n";
    foreach ($domResults['results'] as $i => $result) {
        echo "  [$i] Content: " . substr($result['content'], 0, 50) . "\n";
    }
    if (!empty($domResults['errors'])) {
        echo "  Errors: " . count($domResults['errors']) . " libxml errors\n";
    }
    
    // Test with Custom Parser
    echo "\n--- Custom TagParser Results ---\n";
    $customResults = testWithCustomParser($html, 'div');
    echo "Found {$customResults['count']} div elements\n";
    echo "Parse time: " . number_format($customResults['time'] * 1000, 2) . "ms\n";
    foreach ($customResults['results'] as $i => $result) {
        echo "  [$i] Tag: {$result['tag']}, Content: " . substr($result['content'], 0, 50) . "\n";
    }
    if (!empty($customResults['errors'])) {
        echo "  Errors: " . count($customResults['errors']) . " custom errors\n";
        foreach ($customResults['errors'] as $error) {
            echo "    - $error\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
}

// Performance test with larger content
echo "=== PERFORMANCE TEST ===\n";
$largeHtml = str_repeat('<div class="item">Content ' . rand(1000, 9999) . '</div>', 1000);

echo "Testing with 1000 div elements...\n";

$start = microtime(true);
$domResults = testWithDOMDocument($largeHtml, 'div');
$domTime = microtime(true) - $start;

$start = microtime(true);
$customResults = testWithCustomParser($largeHtml, 'div');
$customTime = microtime(true) - $start;

echo "DOMDocument: " . number_format($domTime * 1000, 2) . "ms, found {$domResults['count']} elements\n";
echo "Custom Parser: " . number_format($customTime * 1000, 2) . "ms, found {$customResults['count']} elements\n";
echo "Speed difference: " . number_format($customTime / $domTime, 2) . "x\n";