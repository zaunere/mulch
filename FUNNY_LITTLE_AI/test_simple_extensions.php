<?php
require_once 'dev.hop.pr/libfeta/tag_parser.php';

// Test XMLReader (streaming parser)
function testXMLReader($html, $tagName) {
    $start = microtime(true);
    
    try {
        $reader = new XMLReader();
        $reader->XML("<root>$html</root>");
        
        $results = [];
        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT && $reader->localName == $tagName) {
                $content = $reader->readInnerXML();
                $results[] = [
                    'tag' => $tagName,
                    'content' => $content
                ];
            }
        }
        
        $time = microtime(true) - $start;
        $reader->close();
        
        return ['results' => $results, 'time' => $time, 'success' => true];
    } catch (Exception $e) {
        return ['results' => [], 'time' => microtime(true) - $start, 'success' => false, 'error' => $e->getMessage()];
    }
}

// Test regex approach
function testRegex($html, $tagName) {
    $start = microtime(true);
    
    $pattern = "/<$tagName\b[^>]*>(.*?)<\/$tagName>/s";
    preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
    
    $results = [];
    foreach ($matches as $match) {
        $results[] = [
            'tag' => $tagName,
            'content' => $match[1]
        ];
    }
    
    return ['results' => $results, 'time' => microtime(true) - $start, 'success' => true];
}

// Test cases
$testCases = [
    'Simple' => '<div>Simple content</div>',
    'Nested' => '<div>Outer <div>Inner</div> More</div>',
    'Attributes' => '<div class="test">Content with <div id="inner">nested</div></div>',
    'Malformed' => '<div>Start <div>No close'
];

echo "=== EXTENSION COMPARISON ===\n\n";

foreach ($testCases as $name => $html) {
    echo "Test: $name\n";
    echo "HTML: $html\n\n";
    
    // XMLReader
    echo "XMLReader: ";
    $xmlResult = testXMLReader($html, 'div');
    if ($xmlResult['success']) {
        echo count($xmlResult['results']) . " elements, " . number_format($xmlResult['time'] * 1000, 2) . "ms\n";
    } else {
        echo "FAILED - " . ($xmlResult['error'] ?? 'Unknown error') . "\n";
    }
    
    // Regex
    echo "Regex: ";
    $regexResult = testRegex($html, 'div');
    echo count($regexResult['results']) . " elements, " . number_format($regexResult['time'] * 1000, 2) . "ms\n";
    
    // Custom Parser
    echo "Custom: ";
    $parser = new TagParser(false);
    $start = microtime(true);
    $customResult = $parser->parse($html, ['div']);
    $customTime = microtime(true) - $start;
    echo count($customResult) . " elements, " . number_format($customTime * 1000, 2) . "ms";
    if (count($parser->getErrors()) > 0) {
        echo " (" . count($parser->getErrors()) . " errors)";
    }
    echo "\n\n";
}

// Performance test
echo "=== PERFORMANCE TEST (1000 simple divs) ===\n";
$largeHtml = str_repeat('<div class="item">Content</div>', 1000);

echo "Testing 1000 div elements...\n";

$xmlResult = testXMLReader($largeHtml, 'div');
echo "XMLReader: " . ($xmlResult['success'] ? count($xmlResult['results']) . " elements, " . number_format($xmlResult['time'] * 1000, 2) . "ms" : "FAILED") . "\n";

$regexResult = testRegex($largeHtml, 'div');
echo "Regex: " . count($regexResult['results']) . " elements, " . number_format($regexResult['time'] * 1000, 2) . "ms\n";

$parser = new TagParser(false);
$start = microtime(true);
$customResult = $parser->parse($largeHtml, ['div']);
$customTime = microtime(true) - $start;
echo "Custom: " . count($customResult) . " elements, " . number_format($customTime * 1000, 2) . "ms\n";

echo "\n=== SUMMARY ===\n";
echo "XMLReader: Good for streaming large XML, but requires well-formed markup\n";
echo "Regex: Fastest but unreliable for complex nesting\n";
echo "Custom Parser: Best balance of speed, reliability, and error detection\n";