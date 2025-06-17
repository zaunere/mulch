<?php
require_once 'dev.hop.pr/libfeta/tag_parser.php';

// Let's debug the extractTagInfo method directly
$parser = new TagParser(true);

// Use reflection to access the protected method
$reflection = new ReflectionClass($parser);
$method = $reflection->getMethod('extractTagInfo');
$method->setAccessible(true);

$testHtml = '<div class="outer">Outer <div id="inner">Inner content</div> More outer</div>';
echo "Testing HTML: $testHtml\n\n";

$length = strlen($testHtml);
$pos = 0;

echo "Analyzing each tag:\n";
while ($pos < $length) {
    $startTagPos = strpos($testHtml, '<', $pos);
    if ($startTagPos === false) {
        break;
    }
    
    echo "Found '<' at position $startTagPos\n";
    
    $tagInfo = $method->invoke($parser, $testHtml, $startTagPos, $length);
    
    if ($tagInfo === null) {
        echo "  -> extractTagInfo returned NULL\n";
        $pos = $startTagPos + 1;
        continue;
    }
    
    echo "  -> Tag: '{$tagInfo['tag']}', isClosing: " . ($tagInfo['isClosing'] ? 'true' : 'false') . ", endPos: {$tagInfo['endPos']}\n";
    echo "  -> Full tag: '" . substr($testHtml, $startTagPos, $tagInfo['endPos'] - $startTagPos + 1) . "'\n";
    
    $pos = $tagInfo['endPos'] + 1;
}