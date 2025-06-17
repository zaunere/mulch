<?php

/**
 * TagParser - A fast, lightweight tag parser for extracting content between specified tags.
 * 
 * This class provides a static method to parse a string for given tags and return
 * an ordered array of tag-content pairs. Optimized for speed with minimal overhead.
 */
class TagParser
{
    private $displayErrors;
    private $errors = [];

    /**
     * Constructor for TagParser.
     *
     * @param bool $displayErrors Whether to display errors immediately or store them internally.
     */
    public function __construct($displayErrors = true)
    {
        $this->displayErrors = $displayErrors;
    }

    /**
     * Get stored errors if displayErrors is false.
     *
     * @return array List of error messages.
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Check if errors are set to be displayed.
     *
     * @return bool Whether errors are displayed.
     */
    public function getDisplayErrors()
    {
        return $this->displayErrors;
    }

    /**
     * Parse a string for specified tags and return content within them.
     *
     * @param string $input The input string to parse.
     * @param array|string $tags A single tag or array of tags to parse for (without angle brackets).
     * @return array An ordered array of associative arrays with 'tag' and 'content' keys.
     */
    public function parse($input, $tags)
    {
        if (empty($input) || empty($tags)) {
            return [];
        }

        // Convert single tag to array for uniform processing
        $tags = (array) $tags;
        $result = [];
        $length = strlen($input);
        $pos = 0;

        $this->iterativeParse($input, $tags, $result, $pos, $length);
        return $result;
    }

    protected function iterativeParse($input, $tags, &$result, &$pos, $length)
    {
        $openTags = [];
        
        while ($pos < $length) {
            $startTagPos = strpos($input, '<', $pos);
            if ($startTagPos === false) {
                break;
            }

            $tagInfo = $this->extractTagInfo($input, $startTagPos, $length);
            if ($tagInfo === null) {
                $pos = $startTagPos + 1;
                continue;
            }

            $potentialTag = $tagInfo['tag'];
            $isClosingTag = $tagInfo['isClosing'];
            $tagEndPos = $tagInfo['endPos'];

            if ($isClosingTag) {
                if (!empty($openTags)) {
                    // Find the most recent matching opening tag
                    $foundIndex = -1;
                    for ($i = count($openTags) - 1; $i >= 0; $i--) {
                        if ($openTags[$i]['tag'] === $potentialTag) {
                            $foundIndex = $i;
                            break;
                        }
                    }
                    
                    if ($foundIndex >= 0) {
                        // Close all tags from the found index to the end (handles improperly nested tags)
                        $tagsToClose = array_slice($openTags, $foundIndex);
                        $openTags = array_slice($openTags, 0, $foundIndex);
                        
                        foreach (array_reverse($tagsToClose) as $closingEntry) {
                            $closingTag = $closingEntry['tag'];
                            $startContent = $closingEntry['startContent'];
                            $index = $closingEntry['resultIndex'];
                            $contentLength = $startTagPos - $startContent;
                            
                            
                            if ($contentLength >= 0) {
                                $content = substr($input, $startContent, $contentLength);
                                $result[$index]['content'] = $content;
                            } else {
                                $result[$index]['content'] = '';
                            }
                        }
                        $pos = $tagEndPos + 1;
                    } else {
                        // Unexpected closing tag
                        if (in_array($potentialTag, $tags)) {
                            $errorMsg = "Unexpected closing tag </$potentialTag> at position $startTagPos";
                            $result[] = [
                                'tag' => 'MALFORMED',
                                'content' => $errorMsg
                            ];
                            if ($this->displayErrors) {
                                echo "Error: $errorMsg\n";
                            } else {
                                $this->errors[] = "Error: $errorMsg";
                            }
                        }
                        $pos = $tagEndPos + 1;
                    }
                } else {
                    // No open tags, but found a closing tag
                    if (in_array($potentialTag, $tags)) {
                        $errorMsg = "Unexpected closing tag </$potentialTag> at position $startTagPos";
                        $result[] = [
                            'tag' => 'MALFORMED',
                            'content' => $errorMsg
                        ];
                        if ($this->displayErrors) {
                            echo "Error: $errorMsg\n";
                        } else {
                            $this->errors[] = "Error: $errorMsg";
                        }
                    }
                    $pos = $tagEndPos + 1;
                }
            } else {
                // Opening tag
                if (in_array($potentialTag, $tags)) {
                    $startContent = $tagEndPos + 1; // Start after the '>'
                    $resultIndex = count($result);
                    $result[] = [
                        'tag' => $potentialTag,
                        'content' => 'Pending - awaiting closing tag'
                    ];
                    $openTags[] = [
                        'tag' => $potentialTag,
                        'startContent' => $startContent,
                        'resultIndex' => $resultIndex
                    ];
                }
                $pos = $tagEndPos + 1;
            }
        }
        
        // Handle any remaining unclosed tags
        if (!empty($openTags)) {
            $this->handleUnclosedTags($result, $openTags);
        }
    }

    protected function recursiveParse($input, $tags, &$result, &$pos, $length, $parentTag = null, $startContentPos = 0, $depth = 0)
    {
        $openTags = [];
        $maxDepth = 100; // Prevent infinite recursion
        if ($depth > $maxDepth) {
            $errorMsg = "Maximum recursion depth exceeded at position $pos";
            $result[] = [
                'tag' => 'MALFORMED',
                'content' => $errorMsg
            ];
            if ($this->displayErrors) {
                echo "Error: $errorMsg\n";
            } else {
                $this->errors[] = "Error: $errorMsg";
            }
            return;
        }

        while ($pos < $length) {
            $startTagPos = strpos($input, '<', $pos);
            if ($startTagPos === false) {
                if ($parentTag !== null && !empty($openTags)) {
                    $this->handleUnclosedTags($result, $openTags);
                }
                break;
            }

            $tagInfo = $this->extractTagInfo($input, $startTagPos, $length);
            if ($tagInfo === null) {
                $pos++;
                continue;
            }

            $potentialTag = $tagInfo['tag'];
            $isClosingTag = $tagInfo['isClosing'];
            $tagEndPos = $tagInfo['endPos'];

            if ($isClosingTag) {
                if (!empty($openTags)) {
                    $foundIndex = -1;
                    for ($i = count($openTags) - 1; $i >= 0; $i--) {
                        if ($openTags[$i]['tag'] === $potentialTag) {
                            $foundIndex = $i;
                            break;
                        }
                    }
                    if ($foundIndex >= 0) {
                        $tagsToClose = array_slice($openTags, $foundIndex);
                        $openTags = array_slice($openTags, 0, $foundIndex);
                        foreach (array_reverse($tagsToClose) as $closingEntry) {
                            $closingTag = $closingEntry['tag'];
                            $startContent = $closingEntry['startContent'];
                            $index = $closingEntry['resultIndex'];
                            // Adjust content length to avoid including closing tags of outer levels
                            $contentLength = $tagEndPos - $startContent;
                            echo "Extracting content for tag '$closingTag', start: $startContent, end: $tagEndPos, length: $contentLength\n";
                            if ($contentLength >= 0) {
                                $content = substr($input, $startContent, $contentLength);
                                // Trim any trailing closing tags that might belong to outer levels
                                $content = rtrim($content, '</>');
                                $result[$index]['content'] = $content;
                            }
                        }
                        $pos = $tagEndPos + 1;
                    }
                } else if (in_array($potentialTag, $tags)) {
                    $errorMsg = "Unexpected closing tag </$potentialTag> at position $startTagPos";
                    $result[] = [
                        'tag' => 'MALFORMED',
                        'content' => $errorMsg
                    ];
                    if ($this->displayErrors) {
                        echo "Error: $errorMsg\n";
                    } else {
                        $this->errors[] = "Error: $errorMsg";
                    }
                    $pos = $tagEndPos + 1; // Move past this tag to avoid looping
                }
            } else {
                if (in_array($potentialTag, $tags)) {
                    $currentTag = $potentialTag;
                    $startContent = $startTagPos + strlen("<$currentTag>");
                    $resultIndex = count($result);
                    $result[] = [
                        'tag' => $currentTag,
                        'content' => 'Pending - awaiting closing tag',
                        'startContent' => $startContent
                    ];
                    $openTags[] = ['tag' => $currentTag, 'startContent' => $startContent, 'resultIndex' => $resultIndex];
                    $newPos = $startContent;
                    echo "Before recursive call for tag '$currentTag' at depth $depth, position: $newPos\n";
                    $this->recursiveParse($input, $tags, $result, $newPos, $length, $currentTag, $startContent, $depth + 1);
                    echo "After recursive call for tag '$currentTag' at depth $depth, updated position: $newPos\n";
                    $pos = $newPos;
                    // After recursive call, ensure position is past the closing tag of the current tag if it exists
                    if ($pos < $length && !empty($openTags)) {
                        $currentOpenTag = end($openTags)['tag'];
                        $closingTagStr = "</$currentOpenTag>";
                        $closingTagPos = strpos($input, $closingTagStr, $pos);
                        if ($closingTagPos !== false) {
                            $closingTagEnd = $closingTagPos + strlen($closingTagStr) + 1; // Account for '>'
                            if ($closingTagEnd <= $length) {
                                $pos = $closingTagEnd;
                                echo "Adjusted position to end of closing tag '</$currentOpenTag>' at: $pos\n";
                            }
                        }
                    }
                } else {
                    $pos = $tagEndPos + 1;
                }
            }
        }
        if (!empty($openTags)) {
            $this->handleUnclosedTags($result, $openTags);
        }
    }

    /**
     * Handle unclosed tags at the end of parsing.
     *
     * @param array &$result The result array to update.
     * @param array &$openTags The stack of open tags.
     */
    protected function handleUnclosedTags(&$result, &$openTags)
    {
        while (!empty($openTags)) {
            $unclosedEntry = array_pop($openTags);
            $unclosedTag = $unclosedEntry['tag'];
            for ($i = count($result) - 1; $i >= 0; $i--) {
                if ($result[$i]['tag'] === $unclosedTag && strpos($result[$i]['content'], 'Pending') === 0) {
                    $errorMsg = 'MALFORMED - Missing closing tag';
                    $result[$i]['content'] = $errorMsg;
                    if ($this->displayErrors) {
                        echo "Error: $errorMsg for tag '$unclosedTag'\n";
                    } else {
                        $this->errors[] = "Error: $errorMsg for tag '$unclosedTag'";
                    }
                    break;
                }
            }
        }
    }

    /**
     * Extract tag information from the input string at the given position.
     *
     * @param string $input The input string.
     * @param int $startTagPos The starting position of the tag.
     * @param int $length The length of the input string.
     * @param array $tags The list of tags to parse for.
     * @return array|null Returns tag information or null if not a valid tag.
     */
    protected function extractTagInfo($input, $startTagPos, $length)
    {
        $startAfterBracket = $startTagPos + 1;
        if ($startAfterBracket >= $length) {
            return null;
        }

        $isClosingTag = ($input[$startAfterBracket] === '/');
        if ($input[$startAfterBracket] === '!' || $input[$startAfterBracket] === '?') {
            return null;
        }

        // Extract tag name (stop at space, newline, tab, or >)
        $tagNameStart = $startAfterBracket + ($isClosingTag ? 1 : 0);
        $tagNameEnd = $tagNameStart;
        while ($tagNameEnd < $length) {
            $char = $input[$tagNameEnd];
            if ($char === '>' || $char === ' ' || $char === "\n" || $char === "\r" || $char === "\t") {
                break;
            }
            $tagNameEnd++;
        }

        // Now find the actual end of the tag (the '>')
        $tagEndPos = $tagNameEnd;
        while ($tagEndPos < $length && $input[$tagEndPos] !== '>') {
            $tagEndPos++;
        }

        if ($tagEndPos >= $length) {
            return null;
        }

        $potentialTag = substr($input, $tagNameStart, $tagNameEnd - $tagNameStart);
        return [
            'isClosing' => $isClosingTag,
            'tag' => $potentialTag,
            'endPos' => $tagEndPos
        ];
    }

    /**
     * Process a tag (opening or closing) and update results and position.
     *
     * @param string $input The input string.
     * @param array $tagInfo The tag information.
     * @param int $startTagPos The starting position of the tag.
     * @param int $length The length of the input string.
     * @param array $tags The list of tags to parse for.
     * @param array &$result The result array to update.
     * @param array &$openTags The stack of open tags.
     * @return int The new position in the input string.
     */
    protected function processTag($input, $tagInfo, $startTagPos, $length, $tags, &$result, &$openTags)
    {
        // This method is no longer used with the recursive approach
        return 0;
    }

    protected function handleClosingTag($input, $tag, $startTagPos, $tagEndPos, $tags, &$result, &$openTags)
    {
        // This method is no longer used with the recursive approach
        return $tagEndPos + 1;
    }

    protected function handleOpeningTag($input, $tag, $startTagPos, $length, $tags, &$result, &$openTags, $tagEndPos)
    {
        // This method is no longer used with the recursive approach
        return $tagEndPos + 1;
    }
}

// Function to validate error at a specific position
function validateErrorPosition($content, $position) {
    $contextSize = 30; // Characters to show before and after the position
    $start = max(0, $position - $contextSize);
    $end = min(strlen($content), $position + $contextSize);
    $contextBefore = substr($content, $start, $position - $start);
    $contextAt = substr($content, $position, 1);
    $contextAfter = substr($content, $position + 1, $end - ($position + 1));
    echo "  Context around position $position:\n";
    echo "  Before: ...$contextBefore\n";
    echo "  At: $contextAt\n";
    echo "  After: $contextAfter...\n";
    
    // Basic validation logic to check if it's likely a real error
    $tagStart = strpos($content, '<', max(0, $position - 10));
    $tagEnd = strpos($content, '>', $position);
    if ($tagStart !== false && $tagEnd !== false && $tagStart < $position && $tagEnd > $position) {
        $tag = substr($content, $tagStart, $tagEnd - $tagStart + 1);
        if (strpos($tag, '</') === 0) {
            echo "  Validation: Likely a real error - Found closing tag '$tag' without matching opening tag in immediate context.\n";
        } else {
            echo "  Validation: Possible false positive - Found tag '$tag' which may not be malformed.\n";
        }
    } else {
        echo "  Validation: Unable to determine - No clear tag structure found near position.\n";
    }
}


/**
 * Test cases for TagParser class to stress test with malformed and dirty input,
 * including simulated content and real content from popular websites corrupted with malformed tags,
 * and nested tag structures.
 */
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    // Function to pretty print results
    function printResults($testName, $results, $parser = null) {
        echo "Test: $testName\n";
        echo "Results:\n";
        foreach ($results as $index => $result) {
            echo "  [$index] Tag: {$result['tag']}, Content: " . substr($result['content'], 0, 50) . (strlen($result['content']) > 50 ? "..." : "") . "\n";
        }
        if ($parser !== null && !$parser->getDisplayErrors()) {
            echo "Stored Errors:\n";
            foreach ($parser->getErrors() as $error) {
                echo "  - $error\n";
            }
        }
        echo "\n";
    }

    // Function to fetch website content with error handling and diagnostics
    function fetchWebsiteContent($url) {
        echo "Fetching content from $url...\n";
        
        // Add more diagnostic logging
        echo "  - Checking network connectivity...\n";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 15, // Increased timeout
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
            ]
        ]);
        
        // Capture error details
        $content = @file_get_contents($url, false, $context);
        
        if ($content === false) {
            $error = error_get_last();
            echo "  - Failed to fetch content from $url\n";
            echo "  - Error details: " . ($error ? $error['message'] : 'Unknown error') . "\n";
            echo "  - Possible causes: Network timeout, anti-bot protection, SSL issues, or rate limiting\n";
            return "";
        }
        
        $contentLength = strlen($content);
        echo "  - Successfully fetched content from $url\n";
        echo "  - Content length: $contentLength bytes\n";
        echo "  - Memory usage: " . memory_get_usage(true) . " bytes\n";
        
        return $content;
    }

    // Instantiate parser with errors displayed
    $parserDisplay = new TagParser(true);
    // Instantiate parser with errors stored
    $parserStore = new TagParser(false);

    // Test 1: Simple malformed input with missing tags - Display Errors
    $test1Input = "<div>Content without closing tag <span>Missing close</span> <div>Another div</div>";
    $test1Result = $parserDisplay->parse($test1Input, ['div', 'span']);
    printResults("Simple Malformed Input - Display Errors", $test1Result);

    // Test 2: Dirty input with random characters and invalid tags - Store Errors
    $test2Input = "<div>Valid content</div> <invalid@tag>Random<stuff> <div>More content</div> <<<random>>>";
    $test2Result = $parserStore->parse($test2Input, ['div']);
    printResults("Dirty Input with Invalid Tags - Store Errors", $test2Result, $parserStore);

    // Test 3: Simulated popular website content with heavy JS/CSS, corrupted - Display Errors
    $test3Input = "
        <html>
        <head>
            <style>body { background: url('<malformed>'); }</style>
            <script>function test() { alert('test<broken>'); }</script>
        </head>
        <body>
            <div>Main content <span>Nested <div>Double nested</div></span></div>
            <div>Another section</div> </div>Malformed close
            <script>var x = '<incomplete>;';</script>
        </body>
        </html>
    ";
    $test3Result = $parserDisplay->parse($test3Input, ['div', 'span']);
    printResults("Simulated Website Content with Corruption - Display Errors", $test3Result);

    // Test 4: Nested tags to verify flattening - Store Errors
    $test4Input = "<div>Outer content <div>Inner content <div>Deepest content</div></div> More outer</div>";
    $test4Result = $parserStore->parse($test4Input, ['div']);
    printResults("Nested Tags Flattening - Store Errors", $test4Result, $parserStore);

    // Test 5: Complex corrupted input with mixed issues - Display Errors
    $test5Input = "<div>Start <span>Inside</span> No close <div>Nested</div> </span>Unexpected close <p>Ignore me</p> <div>Final</div>";
    $test5Result = $parserDisplay->parse($test5Input, ['div', 'span']);
    printResults("Complex Corrupted Input - Display Errors", $test5Result);

    // Test 6: Specific nested div structure to debug nesting issues - Store Errors
    $test6Input = "<div>Outer <div>Middle <div>Inner</div></div> Outer continues</div>";
    $test6Result = $parserStore->parse($test6Input, ['div']);
    printResults("Nested Div Structure - Store Errors", $test6Result, $parserStore);

    // Test 6: Real website content from foxnews.com - Store Errors with Spot Checks
    echo "\n=== REAL WORLD TEST 1: Fox News ===\n";
    $foxNewsContent = fetchWebsiteContent("https://www.foxnews.com");
    if (!empty($foxNewsContent)) {
        $foxNewsResult = $parserStore->parse($foxNewsContent, ['div', 'article']);
        // Limit to first 5 results for spot checking
        $spotCheckResults = array_slice($foxNewsResult, 0, 5);
        printResults("Real Website Content - Fox News - Store Errors (Spot Check Top 5)", $spotCheckResults, $parserStore);
        
        // Check for errors in spot check results and validate position
        foreach ($spotCheckResults as $index => $result) {
            if ($result['tag'] === 'MALFORMED' && strpos($result['content'], 'Unexpected closing tag') !== false) {
                // Extract position from error message
                if (preg_match('/at position (\d+)/', $result['content'], $matches)) {
                    $errorPosition = (int)$matches[1];
                    echo "Spot Check [$index]: Error at position $errorPosition - Validating...\n";
                    validateErrorPosition($foxNewsContent, $errorPosition);
                }
            }
        }
    }

    // Test 7: Real website content from nbc.com - Display Errors
    echo "\n=== REAL WORLD TEST 2: NBC ===\n";
    $nbcContent = fetchWebsiteContent("https://www.nbc.com");

    echo "\n\nLEN: " . strlen($nbcContent) . "\n\n";
    $chunks = explode('<div', $nbcContent);
    foreach( $chunks as $chunk )
    {
        echo "\n".substr(trim($chunk), 0, 25)."\n";
    }

//    var_dump(count($chunks));


    // if (!empty($nbcContent)) {
    //     $nbcResult = $parserDisplay->parse($nbcContent, ['div', 'section']);
    //     printResults("Real Website Content - NBC - Display Errors", $nbcResult);
    // }

    // // Test 8: Real website content from craigslist.org (known for messy content) - Display Errors
    // echo "\n=== REAL WORLD TEST 3: Craigslist ===\n";
    // $craigslistContent = fetchWebsiteContent("https://www.craigslist.org");
    // if (!empty($craigslistContent)) {
    //     $craigslistResult = $parserDisplay->parse($craigslistContent, ['div', 'li']);
    //     printResults("Real Website Content - Craigslist (Messy Content) - Display Errors", $craigslistResult);
    // }
}




