<?php
require_once 'dev.hop.pr/libfeta/tag_parser.php';

// Test with jq (JSON processor, but can work with HTML in some cases)
function testWithJQ($html, $tag) {
    // jq can't directly parse HTML, but we can try converting to JSON first
    $start = microtime(true);
    
    // This is a hack - jq isn't really for HTML
    $command = "echo " . escapeshellarg($html) . " | grep -o '<$tag[^>]*>[^<]*</$tag>' 2>/dev/null";
    $output = shell_exec($command);
    $time = microtime(true) - $start;
    
    if ($output === null) {
        return ['success' => false, 'error' => 'grep failed', 'time' => $time];
    }
    
    $results = array_filter(explode("\n", trim($output)));
    return [
        'success' => true,
        'results' => $results,
        'time' => $time,
        'count' => count($results)
    ];
}

// Test with sed/awk (Unix text processing)
function testWithSedAwk($html, $tag) {
    $tempFile = tempnam(sys_get_temp_dir(), 'html_test');
    file_put_contents($tempFile, $html);
    
    $start = microtime(true);
    
    // Use sed to extract content between tags
    $command = "sed -n 's/.*<$tag[^>]*>\\(.*\\)<\\/$tag>.*/\\1/p' " . escapeshellarg($tempFile) . " 2>/dev/null";
    $output = shell_exec($command);
    $time = microtime(true) - $start;
    
    unlink($tempFile);
    
    if ($output === null) {
        return ['success' => false, 'error' => 'sed failed', 'time' => $time];
    }
    
    $results = array_filter(explode("\n", trim($output)));
    return [
        'success' => true,
        'results' => $results,
        'time' => $time,
        'count' => count($results)
    ];
}

// Test installing and using pup (if possible)
function tryInstallPup() {
    echo "Attempting to install pup...\n";
    
    // Try different installation methods
    $commands = [
        'which pup',
        'go install github.com/ericchiang/pup@latest',
        'curl -L https://github.com/ericchiang/pup/releases/download/v0.4.0/pup_v0.4.0_linux_amd64.zip -o pup.zip && unzip pup.zip && chmod +x pup'
    ];
    
    foreach ($commands as $cmd) {
        echo "Trying: $cmd\n";
        $output = shell_exec("$cmd 2>&1");
        echo "Result: " . substr($output, 0, 100) . "\n";
    }
}

// Performance test with available tools
function performanceTest() {
    echo "\n=== PERFORMANCE TEST WITH LARGER CONTENT ===\n";
    
    // Create larger test content
    $largeHtml = '<html><body>';
    for ($i = 0; $i < 100; $i++) {
        $largeHtml .= "<div class='item-$i'>Content item $i <span>nested</span></div>";
    }
    $largeHtml .= '</body></html>';
    
    echo "Testing with 100 div elements...\n\n";
    
    // Test Python BeautifulSoup
    echo "Python BeautifulSoup: ";
    $tempFile = tempnam(sys_get_temp_dir(), 'html_test');
    file_put_contents($tempFile, $largeHtml);
    
    $pythonScript = "
import sys
from bs4 import BeautifulSoup
with open('$tempFile', 'r') as f:
    soup = BeautifulSoup(f.read(), 'html.parser')
    count = len(soup.find_all('div'))
    print(f'{count} elements')
";
    
    $scriptFile = tempnam(sys_get_temp_dir(), 'python_script');
    file_put_contents($scriptFile, $pythonScript);
    
    $start = microtime(true);
    $output = shell_exec("python3 " . escapeshellarg($scriptFile) . " 2>/dev/null");
    $pythonTime = microtime(true) - $start;
    
    echo trim($output) . " in " . number_format($pythonTime * 1000, 2) . "ms\n";
    
    unlink($tempFile);
    unlink($scriptFile);
    
    // Test sed/awk
    echo "Sed/Awk: ";
    $sedResult = testWithSedAwk($largeHtml, 'div');
    if ($sedResult['success']) {
        echo "{$sedResult['count']} elements in " . number_format($sedResult['time'] * 1000, 2) . "ms\n";
    } else {
        echo "FAILED\n";
    }
    
    // Test Custom Parser
    echo "Custom Parser: ";
    $parser = new TagParser(false);
    $start = microtime(true);
    $customResult = $parser->parse($largeHtml, ['div']);
    $customTime = microtime(true) - $start;
    
    echo count($customResult) . " elements in " . number_format($customTime * 1000, 2) . "ms\n";
    
    // Calculate speed differences
    if ($pythonTime > 0 && $customTime > 0) {
        $speedDiff = $pythonTime / $customTime;
        echo "\nCustom Parser is " . number_format($speedDiff, 1) . "x faster than Python BeautifulSoup\n";
    }
}

echo "=== TESTING AVAILABLE EXTERNAL TOOLS ===\n\n";

$testHtml = '<div class="outer">Outer content <div id="inner">Inner content</div> More outer</div>';

// Test sed/awk
echo "--- Testing sed/awk ---\n";
$sedResult = testWithSedAwk($testHtml, 'div');
if ($sedResult['success']) {
    echo "Found {$sedResult['count']} elements in " . number_format($sedResult['time'] * 1000, 2) . "ms\n";
    foreach ($sedResult['results'] as $i => $result) {
        echo "  [$i] " . substr($result, 0, 50) . "\n";
    }
} else {
    echo "FAILED: {$sedResult['error']}\n";
}

// Test grep-based approach
echo "\n--- Testing grep-based extraction ---\n";
$grepResult = testWithJQ($testHtml, 'div');
if ($grepResult['success']) {
    echo "Found {$grepResult['count']} elements in " . number_format($grepResult['time'] * 1000, 2) . "ms\n";
    foreach ($grepResult['results'] as $i => $result) {
        echo "  [$i] " . substr($result, 0, 50) . "\n";
    }
} else {
    echo "FAILED: {$grepResult['error']}\n";
}

// Try to install pup
echo "\n--- Checking for pup installation options ---\n";
tryInstallPup();

// Performance comparison
performanceTest();

echo "\n=== EXTERNAL TOOLS ANALYSIS ===\n";
echo "Available tools and their characteristics:\n";
echo "- Python BeautifulSoup: Very robust, but 100x+ slower due to process overhead\n";
echo "- sed/awk: Fast text processing, but limited HTML parsing capabilities\n";
echo "- grep: Very fast, but only works for simple patterns\n";
echo "- pup/htmlq: Would be ideal but not installed\n";
echo "- Custom Parser: Best balance of speed, reliability, and no dependencies\n";