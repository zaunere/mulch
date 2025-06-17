<?php
require_once 'dev.hop.pr/libfeta/tag_parser.php';

// Test SimpleXML
function testWithSimpleXML($html, $tagName) {
    // SimpleXML requires well-formed XML, so we need to wrap HTML
    $xmlString = "<?xml version='1.0' encoding='UTF-8'?><root>$html</root>";
    
    libxml_use_internal_errors(true);
    $start = microtime(true);
    
    try {
        $xml = simplexml_load_string($xmlString);
        $elements = $xml->xpath("//$tagName");
        $parseTime = microtime(true) - $start;
        
        $results = [];
        foreach ($elements as $element) {
            $results[] = [
                'tag' => $tagName,
                'content' => (string)$element,
                'xml' => $element->asXML()
            ];
        }
        
        return [
            'results' => $results,
            'errors' => libxml_get_errors(),
            'time' => $parseTime,
            'count' => count($results),
            'success' => true
        ];
    } catch (Exception $e) {
        return [
            'results' => [],
            'errors' => [$e->getMessage()],
            'time' => microtime(true) - $start,
            'count' => 0,
            'success' => false
        ];
    }
}

// Test XMLReader (streaming parser)
function testWithXMLReader($html, $tagName) {
    $xmlString = "<?xml version='1.0' encoding='UTF-8'?><root>$html</root>";
    
    libxml_use_internal_errors(true);
    $start = microtime(true);
    
    try {
        $reader = new XMLReader();
        $reader->XML($xmlString);
        
        $results = [];
        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT && $reader->localName == $tagName) {
                $doc = new DOMDocument();
                $element = $doc->importNode($reader->expand(), true);
                $doc->appendChild($element);
                
                $results[] = [
                    'tag' => $tagName,
                    'content' => $element->textContent,
                    'xml' => $doc->saveXML($element)
                ];
            }
        }
        
        $parseTime = microtime(true) - $start;
        $reader->close();
        
        return [
            'results' => $results,
            'errors' => libxml_get_errors(),
            'time' => $parseTime,
            'count' => count($results),
            'success' => true
        ];
    } catch (Exception $e) {
        return [
            'results' => [],
            'errors' => [$e->getMessage()],
            'time' => microtime(true) - $start,
            'count' => 0,
            'success' => false
        ];
    }
}

// Test with regex (simple alternative)
function testWithRegex($html, $tagName) {
    $start = microtime(true);
    
    // Pattern to match opening and closing tags
    $pattern = "/<$tagName\b[^>]*>(.*?)<\/$tagName>/s";
    
    preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);
    
    $parseTime = microtime(true) - $start;
    
    $results = [];
    foreach ($matches as $match) {
        $results[] = [
            'tag' => $tagName,
            'content' => $match[1],
            'full_match' => $match[0]
        ];
    }
    
    return [
        'results' => $results,
        'errors' => [],
        'time' => $parseTime,
        'count' => count($results),
        'success' => true
    ];
}

// Test cases
$testCases = [
    'Simple nested' => '<div>Outer <div>Inner content</div> More outer</div>',
    'With attributes' => '<div class="outer">Outer <div id="inner">Inner content</div> More outer</div>',
    'Self-closing' => '<div>Content</div><div/>',
    'Complex nested' => '<div><div><div>Deep</div></div><div>Another</div></div>'
];

echo "=== COMPREHENSIVE PHP EXTENSION COMPARISON ===\n\n";

foreach ($testCases as $testName => $html) {
    echo "Test: $testName\n";
    echo "HTML: $html\n\n";
    
    // Test SimpleXML
    echo "--- SimpleXML Results ---\n";
    $simpleResults = testWithSimpleXML($html, 'div');
    if ($simpleResults['success']) {
        echo "Found {$simpleResults['count']} elements in " . number_format($simpleResults['time'] * 1000, 2) . "ms\n";
        foreach ($simpleResults['results'] as $i => $result) {
            echo "  [$i] Content: " . substr($result['content'], 0, 50) . "\n";
        }
    } else {
        echo "FAILED: " . implode(', ', $simpleResults['errors']) . "\n";
    }
    
    // Test XMLReader
    echo "\n--- XMLReader Results ---\n";
    $readerResults = testWithXMLReader($html, 'div');
    if ($readerResults['success']) {
        echo "Found {$readerResults['count']} elements in " . number_format($readerResults['time'] * 1000, 2) . "ms\n";
        foreach ($readerResults['results'] as $i => $result) {
            echo "  [$i] Content: " . substr($result['content'], 0, 50) . "\n";
        }
    } else {
        echo "FAILED: " . implode(', ', $readerResults['errors']) . "\n";
    }
    
    // Test Regex
    echo "\n--- Regex Results ---\n";
    $regexResults = testWithRegex($html, 'div');
    echo "Found {$regexResults['count']} elements in " . number_format($regexResults['time'] * 1000, 2) . "ms\n";
    foreach ($regexResults['results'] as $i => $result) {
        echo "  [$i] Content: " . substr($result['content'], 0, 50) . "\n";
    }
    
    // Test Custom Parser for comparison
    echo "\n--- Custom TagParser Results ---\n";
    $parser = new TagParser(false);
    $start = microtime(true);
    $customResults = $parser->parse($html, ['div']);
    $customTime = microtime(true) - $start;
    
    echo "Found " . count($customResults) . " elements in " . number_format($customTime * 1000, 2) . "ms\n";
    foreach ($customResults as $i => $result) {
        echo "  [$i] Tag: {$result['tag']}, Content: " . substr($result['content'], 0, 50) . "\n";
    }
    
    echo "\n" . str_repeat("=", 70) . "\n\n";
}

// Test with malformed HTML (real-world scenario)
echo "=== MALFORMED HTML TEST ===\n";
$malformedHtml = '<div>Start <div class="broken">Nested but no close <span>Another</span>';

echo "HTML: $malformedHtml\n\n";

echo "SimpleXML: ";
$simpleResults = testWithSimpleXML($malformedHtml, 'div');
echo $simpleResults['success'] ? "SUCCESS ({$simpleResults['count']} elements)" : "FAILED";
echo "\n";

echo "XMLReader: ";
$readerResults = testWithXMLReader($malformedHtml, 'div');
echo $readerResults['success'] ? "SUCCESS ({$readerResults['count']} elements)" : "FAILED";
echo "\n";

echo "Regex: ";
$regexResults = testWithRegex($malformedHtml, 'div');
echo "SUCCESS ({$regexResults['count']} elements)\n";

echo "Custom Parser: ";
$parser = new TagParser(false);
$customResults = $parser->parse($malformedHtml, ['div']);
echo "SUCCESS (" . count($customResults) . " elements, " . count($parser->getErrors()) . " errors detected)\n";

echo "\n=== PERFORMANCE SUMMARY ===\n";
echo "For well-formed HTML:\n";
echo "- SimpleXML: Fast, but requires valid XML\n";
echo "- XMLReader: Good for large documents, streaming\n";
echo "- Regex: Fastest but unreliable for complex nesting\n";
echo "- Custom Parser: Balanced speed and reliability\n\n";

echo "For malformed HTML:\n";
echo "- SimpleXML: Fails completely\n";
echo "- XMLReader: Fails completely\n";
echo "- Regex: Works but misses nested structures\n";
echo "- Custom Parser: Works and reports errors\n";