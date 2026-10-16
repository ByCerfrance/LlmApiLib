<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests\Transport\Fixture;

header('Content-Type: text/event-stream');
echo "data: first\n\n";
flush();
$deadline = microtime(true) + 5;
while (!is_file($_GET['signal'] ?? '') && microtime(true) < $deadline) {
    usleep(10000);
    clearstatcache();
}
echo is_file($_GET['signal'] ?? '') ? "data: progressive\n\n" : "data: buffered\n\n";
flush();
