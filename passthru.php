#!/usr/bin/php
<?php

require __DIR__ . '/lib.php';

function convert($code)
{
    $tokens = PhpToken::tokenize($code);
    $handler = new to_alternative;
    $signaller = (new signaller($handler, $tokens))->convert();
}

class to_alternative implements conversion_handler
{
    public function set_signaller(signaller $signaller): void
    {
    }

    public function handle_tokens(array &$tokens): void
    {
        echo array_shift($tokens)->text;
    }
}

convert(stream_get_contents(STDIN));
