#!/usr/bin/php
<?php

const MATCHING_BRACES = [
    '${' => '}',
    '(' => ')',
    '[' => ']',
    'T_CURLY_OPEN' => '}',
    'T_DOLLAR_OPEN_CURLY_BRACES' => '}',
    '{' => '}',
];

const ENDS = [
    'T_IF' => 'endif',
    'T_ELSEIF' => 'endif',
    'T_ELSE' => 'endif',
    'T_WHILE' => 'endwhile',
    'T_FOR' => 'endfor',
    'T_FOREACH' => 'endforeach',
    'T_SWITCH' => 'endswitch',
];

$elseif = PhpToken::tokenize('<?php if (1) {} elseif (2) {}')[10];

convert(stream_get_contents(STDIN));

function convert($code)
{
    $tokens = PhpToken::tokenize($code);

    replace_structures_r($tokens); // return null at top level
}

function replace_structures_r(array &$tokens, ?string $context = null, $level = 0)
{
    global $elseif;

    $search = null;

    if ($context) {
        if (!$search = @MATCHING_BRACES[$context]) {
            return;
        }
    }

    passthru_whitespace_and_comments($tokens);

    while ($token = array_shift($tokens)) {
        // We have found the close of the current context, fall out

        if ($search && $token->getTokenName() == $search) {
            return $token;
        }

        if (in_array($token->getTokenName(), array_keys(MATCHING_BRACES))) { // found an opening brace other than for a control structure, recurse
            echo $token->text; // echo opening brace
            echo replace_structures_r($tokens, $token->text, $level + 1)->text; // echo closing brace
        } elseif (in_array($token->getTokenName(), array_keys(ENDS))) { // we have found the beginning of a control structure, prepare to recurse
            $opening_token = $token;

            if ($token->getTokenName() == 'T_ELSE') { // combine "else if" into "elseif"
                for ($i = 0; ($peek = @$tokens[$i]) && in_array($peek->getTokenName(), ['T_WHITESPACE', 'T_COMMENT']); $i++);

                if ($peek->getTokenName() == 'T_IF') {
                    for (; $i >= 0; $i--) {
                        array_shift($tokens);
                    }
                    
                    $opening_token = $elseif;
                }
            }

            echo $opening_token->text; // e.g., 'if'

            passthru_whitespace_and_comments($tokens);

            if ($tokens[0]->getTokenName() == '(') {
                echo array_shift($tokens)->text; // '('
                echo replace_structures_r($tokens, '(', $level + 1)->text; // ')'
            }

            passthru_whitespace_and_comments($tokens);

            $token = array_shift($tokens); // '{', ':', etc.

            if (@MATCHING_BRACES[$token->getTokenName()]) {
                echo ':'; // instead of {

                $closing_token = replace_structures_r($tokens, $token->getTokenName(), $level + 1); // ignore closing token

                for ($i = 0; ($peek = @$tokens[$i]) && in_array($peek->getTokenName(), ['T_WHITESPACE', 'T_COMMENT']); $i++);

                if (!$peek || !in_array($peek->getTokenName(), ['T_ELSEIF', 'T_ELSE'])) {
                    echo ENDS[$opening_token->getTokenName()] . ';';
                }
            } else { // ':'
                echo $token->text;
            }
        } else {
            echo $token->text;
        }
    }

    return '';
}

function passthru_whitespace_and_comments(array &$tokens)
{
    while (count($tokens) && in_array($tokens[0]->getTokenName(), ['T_WHITESPACE', 'T_COMMENT'])) {
        echo array_shift($tokens)->text;
    }
}
