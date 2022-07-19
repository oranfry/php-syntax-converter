#!/usr/bin/php
<?php

require __DIR__ . '/lib.php';

const ENDS = [
    'T_IF' => 'endif',
    'T_ELSEIF' => 'endif',
    'T_ELSE' => 'endif',
    'T_WHILE' => 'endwhile',
    'T_FOR' => 'endfor',
    'T_FOREACH' => 'endforeach',
    'T_SWITCH' => 'endswitch',
];

function convert($code)
{
    $tokens = PhpToken::tokenize($code);
    $handler = new to_alternative;

    convert_r($handler, $tokens);

    if ($handler->level !== 0) {
        error_log('level not zero at the end, [' . $handler->level . '] instead');

        exit(1);
    }
}

function passthru_whitespace_and_comments(array &$tokens)
{
    while (count($tokens) && in_array($tokens[0]->getTokenName(), ['T_WHITESPACE', 'T_COMMENT'])) {
        echo array_shift($tokens)->text;
    }
}

class to_alternative implements conversion_handler
{
    public int $level = 0;
    public bool $colin = false;

    public function enter_context(array &$tokens, bool $braceless = false): void
    {
        $token = array_shift($tokens);

        if ($this->colin) {
            $this->colin = false;
        } else {
            echo $token->text; // echo opening brace
        }

        $this->level++;
    }

    public function enter_control_body(array &$tokens, string $control): void
    {
        echo ':';

        $this->colin = true;
    }

    public function handle_tokens(array &$tokens): void
    {
        $token = array_shift($tokens);

        echo $token->text; // echo stuff in the middle
    }

    public function leave_context(array &$tokens, ?string $control): void
    {
        $token = array_shift($tokens);

        $this->level--;

        if ($control && $token->getTokenName() === '}') {
            for ($i = 0, $peek = null; ($peek = @$tokens[$i]) && in_array($peek->getTokenName(), ['T_WHITESPACE', 'T_COMMENT']); $i++);

            if (!$peek || !in_array($peek->getTokenName(), ['T_ELSEIF', 'T_ELSE'])) {
                echo ENDS[$control] . ';';
            }
        } else {
            echo $token->text;
        }
    }
}

convert(stream_get_contents(STDIN));
