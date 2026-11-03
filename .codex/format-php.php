<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$file = $argv[1] ?? '';
$code = file_get_contents($file);

if ($code === false) {
    fwrite(STDERR, "Unable to read file.\n");
    exit(1);
}

$parser = (new PhpParser\ParserFactory())
    ->createForNewestSupportedVersion();
$ast = $parser->parse($code);

if ($ast === null) {
    fwrite(STDERR, "Unable to parse file.\n");
    exit(1);
}

echo (new PhpParser\PrettyPrinter\Standard())
    ->prettyPrintFile($ast);
