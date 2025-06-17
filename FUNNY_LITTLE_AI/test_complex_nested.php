<?php
require_once 'dev.hop.pr/libfeta/tag_parser.php';

// Test case with attributes and more complex nesting
$testHtml = '<div class="outer">Outer <div id="inner">Inner content</div> More outer</div>';

echo "Testing divs with attributes:\n";
echo "HTML: $testHtml\n\n";

$parser = new TagParser(true);
$result = $parser->parse($testHtml, ['div']);

echo "Results:\n";
foreach ($result as $index => $item) {
    echo "[$index] Tag: {$item['tag']}, Content: {$item['content']}\n";
}

echo "\n" . str_repeat("=", 50) . "\n";

// Test case that might cause issues - unclosed tags
$testHtml2 = '<div>Start <div>Nested but no close';

echo "Testing unclosed nested divs:\n";
echo "HTML: $testHtml2\n\n";

$parser2 = new TagParser(true);
$result2 = $parser2->parse($testHtml2, ['div']);

echo "Results:\n";
foreach ($result2 as $index => $item) {
    echo "[$index] Tag: {$item['tag']}, Content: {$item['content']}\n";
}

echo "\n" . str_repeat("=", 50) . "\n";

// Test case with unexpected closing tag
$testHtml3 = '<div>Start content</div></div>';

echo "Testing unexpected closing tag:\n";
echo "HTML: $testHtml3\n\n";

$parser3 = new TagParser(true);
$result3 = $parser3->parse($testHtml3, ['div']);

echo "Results:\n";
foreach ($result3 as $index => $item) {
    echo "[$index] Tag: {$item['tag']}, Content: {$item['content']}\n";
}