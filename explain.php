#!/usr/bin/env php
<?php

$code = stream_get_contents(STDIN);
$tokens = PhpToken::tokenize($code);

while ($token = array_shift($tokens)) {
    echo "Line {$token->line}: {$token->getTokenName()} ('{$token->text}')", PHP_EOL;
}
