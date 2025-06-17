<?php
require_once 'dev.hop.pr/libfeta/tag_parser.php';

// Test using external CLI tools
function testWithPup($html, $selector) {
    // pup is a command line HTML parser
    $tempFile = tempnam(sys_get_temp_dir(), 'html_test');
    file_put_contents($tempFile, $html);
    
    $start = microtime(true);
    $command = "pup '$selector' < " . escapeshellarg($tempFile) . " 2>/dev/null";
    $output = shell_exec($command);
    $time = microtime(true) - $start;
    
    unlink($tempFile);
    
    if ($output === null) {
        return ['success' => false, 'error' => 'pup not available', 'time' => $time];
    }
    
    $results = array_filter(explode("\n", trim($output)));
    return [
        'success' => true,
        'results' => $results,
        'time' => $time,
        'count' => count($results)
    ];
}

function testWithHtmlq($html, $selector) {
    // htmlq is another CLI HTML parser
    $tempFile = tempnam(sys_get_temp_dir(), 'html_test');
    file_put_contents($tempFile, $html);
    
    $start = microtime(true);
    $command = "htmlq '$selector' < " . escapeshellarg($tempFile) . " 2>/dev/null";
    $output = shell_exec($command);
    $time = microtime(true) - $start;
    
    unlink($tempFile);
    
    if ($output === null) {
        return ['success' => false, 'error' => 'htmlq not available', 'time' => $time];
    }
    
    $results = array_filter(explode("\n", trim($output)));
    return [
        'success' => true,
        'results' => $results,
        'time' => $time,
        'count' => count($results)
    ];
}

function testWithBeautifulSoup($html, $tag) {
    // Python BeautifulSoup via CLI
    $tempFile = tempnam(sys_get_temp_dir(), 'html_test');
    file_put_contents($tempFile, $html);
    
    $pythonScript = "
import sys
from bs4 import BeautifulSoup
with open('$tempFile', 'r') as f:
    soup = BeautifulSoup(f.read(), 'html.parser')
    for tag in soup.find_all('$tag'):
        print(tag.get_text().strip())
";
    
    $scriptFile = tempnam(sys_get_temp_dir(), 'python_script');
    file_put_contents($scriptFile, $pythonScript);
    
    $start = microtime(true);
    $command = "python3 " . escapeshellarg($scriptFile) . " 2>/dev/null";
    $output = shell_exec($command);
    $time = microtime(true) - $start;
    
    unlink($tempFile);
    unlink($scriptFile);
    
    if ($output === null) {
        return ['success' => false, 'error' => 'python3/beautifulsoup not available', 'time' => $time];
    }
    
    $results = array_filter(explode("\n", trim($output)));
    return [
        'success' => true,
        'results' => $results,
        'time' => $time,
        'count' => count($results)
    ];
}

function testWithNodeJS($html, $selector) {
    // Node.js with cheerio
    $tempFile = tempnam(sys_get_temp_dir(), 'html_test');
    file_put_contents($tempFile, $html);
    
    $jsScript = "
const fs = require('fs');
const cheerio = require('cheerio');
const html = fs.readFileSync('$tempFile', 'utf8');
const \$ = cheerio.load(html);
\$('$selector').each((i, el) => {
    console.log(\$(el).text().trim());
});
";
    
    $scriptFile = tempnam(sys_get_temp_dir(), 'js_script');
    file_put_contents($scriptFile, $jsScript);
    
    $start = microtime(true);
    $command = "node " . escapeshellarg($scriptFile) . " 2>/dev/null";
    $output = shell_exec($command);
    $time = microtime(true) - $start;
    
    unlink($tempFile);
    unlink($scriptFile);
    
    if ($output === null) {
        return ['success' => false, 'error' => 'node/cheerio not available', 'time' => $time];
    }
    
    $results = array_filter(explode("\n", trim($output)));
    return [
        'success' => true,
        'results' => $results,
        'time' => $time,
        'count' => count($results)
    ];
}

// Check what tools are available
echo "=== CHECKING AVAILABLE EXTERNAL TOOLS ===\n";

$tools = [
    'pup' => 'pup --version',
    'htmlq' => 'htmlq --version', 
    'python3' => 'python3 --version',
    'node' => 'node --version',
    'jq' => 'jq --version',
    'xmlstarlet' => 'xmlstarlet --version'
];

foreach ($tools as $tool => $command) {
    $output = shell_exec("$command 2>/dev/null");
    echo "$tool: " . ($output ? "✅ Available" : "❌ Not available") . "\n";
}

echo "\n=== TESTING EXTERNAL TOOLS ===\n";

$testHtml = '<div class="outer">Outer content <div id="inner">Inner content</div> More outer</div>';
echo "Test HTML: $testHtml\n\n";

// Test pup
echo "--- Testing pup ---\n";
$pupResult = testWithPup($testHtml, 'div');
if ($pupResult['success']) {
    echo "Found {$pupResult['count']} elements in " . number_format($pupResult['time'] * 1000, 2) . "ms\n";
    foreach ($pupResult['results'] as $i => $result) {
        echo "  [$i] " . substr($result, 0, 50) . "\n";
    }
} else {
    echo "FAILED: {$pupResult['error']}\n";
}

// Test htmlq
echo "\n--- Testing htmlq ---\n";
$htmlqResult = testWithHtmlq($testHtml, 'div');
if ($htmlqResult['success']) {
    echo "Found {$htmlqResult['count']} elements in " . number_format($htmlqResult['time'] * 1000, 2) . "ms\n";
    foreach ($htmlqResult['results'] as $i => $result) {
        echo "  [$i] " . substr($result, 0, 50) . "\n";
    }
} else {
    echo "FAILED: {$htmlqResult['error']}\n";
}

// Test BeautifulSoup
echo "\n--- Testing Python BeautifulSoup ---\n";
$bsResult = testWithBeautifulSoup($testHtml, 'div');
if ($bsResult['success']) {
    echo "Found {$bsResult['count']} elements in " . number_format($bsResult['time'] * 1000, 2) . "ms\n";
    foreach ($bsResult['results'] as $i => $result) {
        echo "  [$i] " . substr($result, 0, 50) . "\n";
    }
} else {
    echo "FAILED: {$bsResult['error']}\n";
}

// Test Node.js
echo "\n--- Testing Node.js Cheerio ---\n";
$nodeResult = testWithNodeJS($testHtml, 'div');
if ($nodeResult['success']) {
    echo "Found {$nodeResult['count']} elements in " . number_format($nodeResult['time'] * 1000, 2) . "ms\n";
    foreach ($nodeResult['results'] as $i => $result) {
        echo "  [$i] " . substr($result, 0, 50) . "\n";
    }
} else {
    echo "FAILED: {$nodeResult['error']}\n";
}

// Test Custom Parser for comparison
echo "\n--- Testing Custom Parser ---\n";
$parser = new TagParser(false);
$start = microtime(true);
$customResult = $parser->parse($testHtml, ['div']);
$customTime = microtime(true) - $start;

echo "Found " . count($customResult) . " elements in " . number_format($customTime * 1000, 2) . "ms\n";
foreach ($customResult as $i => $result) {
    echo "  [$i] " . substr($result['content'], 0, 50) . "\n";
}

echo "\n=== SUMMARY ===\n";
echo "External tools can be powerful but have trade-offs:\n";
echo "- CLI tools: Fast but require external dependencies\n";
echo "- Python/Node: Very robust but slower due to process overhead\n";
echo "- Custom Parser: No dependencies, good performance, full control\n";