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

    public function enter_context(array &$tokens, ?string $context_opener, ?array $context_closers)
    {
    }

    public function enter_control(array &$tokens, string $control)
    {
    }

    public function enter_control_body(array &$tokens, string $name)
    {
    }

    public function handle_tokens(array &$tokens): void
    {
        echo array_shift($tokens)->text;
    }

    public function leave_control_body(array &$tokens, string $control, ?string $daisychain, $message): void
    {
    }

    public function leave_control(array &$tokens, string $name, ?string $daisychain, $message): void
    {
    }

    public function leave_context(array &$tokens, ?string $context_opener, ?string $context_closer, $message): void
    {
    }

    public function left_context(array &$tokens): void
    {
    }
}

convert(stream_get_contents(STDIN));
