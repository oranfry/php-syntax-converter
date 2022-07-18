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
 
    public function enter_context(array &$tokens, array $context_closers, ?string $control = null): void
    {
        $token = array_shift($tokens);

        // error_log(str_repeat(' ', $this->level * 2) . $token->text);

        if (defined('DEBUG') && DEBUG) {
            echo "\n";
            echo str_pad($token->line, 10, ' ', STR_PAD_LEFT) . ' ';
            echo str_repeat(' ', $this->level * 2);
            echo $token->text;
            echo '        <' . implode('|', $context_closers) . '>';
            echo "\n";
        }

        if ($control) {
            echo ':';
        } else {
            echo $token->text; // echo opening brace
        }

        $this->level++;
    }
    
    public function handle_tokens(array &$tokens): void
    {
        $token = array_shift($tokens);

        if (defined('DEBUG') && DEBUG) {
            if (in_array($token->getTokenName(), array_keys(CONTROL_STRUCTURES))) {
                echo "\n";
                echo str_pad($token->line, 10, ' ', STR_PAD_LEFT) . ' ';
                echo str_repeat(' ', $this->level * 2);
                echo $token->text;
                echo "\n";
            }
        }

        echo $token->text; // echo stuff in the middle
    }

    public function leave_context(array &$tokens, ?string $control): void
    {
        $token = array_shift($tokens);

        $this->level--;

        if (defined('DEBUG') && DEBUG) {
            // error_log(str_repeat(' ', $this->level * 2) . $token->text);
            echo "\n";
            echo str_pad($token->line, 10, ' ', STR_PAD_LEFT) . ' ';
            echo str_repeat(' ', $this->level * 2);
            echo $token->text;
            echo "\n";
        }

        if ($control && !in_array($token->getTokenName(), ['T_ELSEIF', 'T_ELSE'])) {
            echo ENDS[$control];
        } else {
            echo $token->text; // echo closing brace
        }
    }
}

convert(stream_get_contents(STDIN));
