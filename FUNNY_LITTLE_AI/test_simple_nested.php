<?php
require_once 'dev.hop.pr/libfeta/tag_parser.php';

// Simple test case that should work but likely fails
$testHtml = '<div>Outer <div>Inner content</div> More outer</div>';

echo "Testing simple nested divs:\n";
echo "HTML: $testHtml\n\n";

$parser = new TagParser(true);
$result = $parser->parse($testHtml, ['div']);

echo "Results:\n";
foreach ($result as $index => $item) {
    echo "[$index] Tag: {$item['tag']}, Content: {$item['content']}\n";
}