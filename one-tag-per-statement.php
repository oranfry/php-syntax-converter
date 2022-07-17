#!/usr/bin/php
<?php

$code = stream_get_contents(STDIN);

$sample = PhpToken::tokenize('a <?php echo "b"; ?> c');
$opening = $sample[1];
$closing = $sample[7];

$in_php = false;
$just_opened_php = false;
$just_opened_switch = false;
$just_saw_comment = false;
$phpbits = [];
$prev = null;
$ternary_level = 0;

$tokens = PhpToken::tokenize($code);

while ($token = array_shift($tokens)) {
    if ($in_php) {
        $extra = null;
        $name = $token->getTokenName();

        if ($name !== 'T_WHITESPACE') {
            $just_saw_comment = false;   
        }

        switch ($name) {
            case 'T_CLOSE_TAG':
                $in_php = false;
                $extra = @$token->text[2];

            case ';':
            case ':':
            case 'T_COMMENT':
            case 'T_DOC_COMMENT':
                if ($is_comment = in_array($name, ['T_COMMENT', 'T_DOC_COMMENT'])) {
                    $just_saw_comment = true;   
                }

                if ($name == ':' && ($just_opened_switch || $ternary_level)) {
                    $just_opened_switch = false;

                    if ($ternary_level) {
                        $ternary_level--;
                    }

                    $phpbits[] = $token;

                    break;
                }

                $leading_whitespace = [];
                $trailing_whitespace = [];
                $trimmed = trim_whitespace($phpbits, $leading_whitespace, $trailing_whitespace);

                if ($trimmed || $is_comment) {
                    if (!$just_opened_php) {
                        foreach ($leading_whitespace as $whitespace) {
                            echo $whitespace->text;
                        }
                    }

                    echo $opening->text . implode('', array_map(fn ($bit) => $bit->text, $trimmed));

                    if ($name === 'T_CLOSE_TAG') {
                        if (!$just_saw_comment) {
                            echo ';;;';
                        }
                    } elseif ($name === ':') {
                        echo ' ' . $token->text;
                    } else {
                        echo $token->text;
                    }

                    echo ' ' . $closing->text;

                    foreach ($trailing_whitespace as $whitespace) {
                        echo $whitespace->text;
                    }

                    $just_opened_php = false;
                }

                echo $extra;

                $phpbits = [];

                break;

            case 'T_SWITCH':
                $just_opened_switch = true;

            case '?':
                if ($name == '?') {
                    $ternary_level++;
                }

            default:
                $phpbits[] = $token;
        }
    } elseif ($token->getTokenName() == 'T_OPEN_TAG') {
        $in_php = true;
        $just_opened_php = true;
    } else {
        echo $token->text;
    }
}

function trim_whitespace(array $bits, array &$leading, array &$trailing)
{
    $trimmed = [];
    $collected = [];

    for ($i = 0; $i < count($bits) && $bits[$i]->getTokenName() == 'T_WHITESPACE'; $i++);

    $leading = array_slice($bits, 0, $i);

    for (; $i < count($bits); $i++) {
        if ($bits[$i]->getTokenName() != 'T_WHITESPACE') {
            $trimmed = array_merge($trimmed, $collected, [$bits[$i]]);
            $collected = [];
        } else {
            $collected[] = $bits[$i];
        }
    }

    $trailing = &$collected;

    return $trimmed;
}
