<?php

namespace conversion;

use conversion_handler;
use out_of_tokens_listener;
use signaller;

class equality_coercion_analyzer implements conversion_handler, out_of_tokens_listener
{
    public $signaller = null;
    public $stream;
    private $seen = [];
    private $buffer = [];

    private $bracketPairs = [
        '(' => ')',
        '[' => ']',
        '{' => '}',
        'T_CURLY_OPEN' => '}',
    ];

    private static $lowerPrecedenceOperators = [
        'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG',
        '^',
        '|',
        'T_BOOLEAN_AND',
        'T_BOOLEAN_OR',
        'T_COALESCE',
        '?',
        ':',
        '=',
        'T_PLUS_EQUAL',
        'T_MINUS_EQUAL',
        'T_MUL_EQUAL',
        'T_POW_EQUAL',
        'T_DIV_EQUAL',
        'T_CONCAT_EQUAL',
        'T_MOD_EQUAL',
        'T_AND_EQUAL',
        'T_OR_EQUAL',
        'T_XOR_EQUAL',
        'T_SL_EQUAL',
        'T_SR_EQUAL',
        'T_COALESCE_EQUAL',
        'T_YIELD_FROM',
        'T_YIELD',
        'T_PRINT',
        'T_LOGICAL_AND',
        'T_LOGICAL_XOR',
        'T_LOGICAL_OR',
    ];

    private bool $verbose = false;

    public function set_signaller(signaller $signaller): void
    {
        $this->signaller = $signaller;
    }

    public function set_output_stream($stream): void
    {
        $this->stream = $stream;
    }

    public function handle_tokens(array &$tokens): void
    {
        if ($token = array_shift($tokens)) {
            if (
                in_array($token->getTokenName(), ['T_IS_EQUAL', 'T_IS_NOT_EQUAL'])
                && (
                    !$tokens[0]
                    || $tokens[0]->getTokenName() !== 'T_COMMENT'
                    || $tokens[0]->text !== '/* EQUALITY_COERCION_ANALYZER_IGNORE */'
                )
                && (
                    !@$tokens[1]
                    || $tokens[0]->getTokenName() !== 'T_WHITESPACE'
                    || $tokens[1]->getTokenName() !== 'T_COMMENT'
                    || $tokens[1]->text !== '/* EQUALITY_COERCION_ANALYZER_IGNORE */'
                )
            ) {
                $function = $token->getTokenName() == 'T_IS_EQUAL' ? 'loosely_equal' : 'loosely_not_equal';
                $a = $this->findExpression(); // backwards
                $b = $this->findExpression($tokens); // forwards

                $spare = \PhpToken::tokenize("<?php $function(1, 2)");

                $tokens = array_merge(
                    [$spare[1], $spare[2]],
                    $a,
                    [$spare[4], $spare[5]],
                    $b,
                    [$spare[7]],
                    $tokens,
                );
            } else {
                $this->seen[] = $token;
            }
        }
    }

    public function findExpression(?array &$tokens = null)
    {
        $bracketStack = [];
        $ttl = INF;
        $this->buffer = [];
        $backwards = !$tokens;

        if ($backwards) {
            if ($this->verbose) {
                error_log('>> Looking backwards');
            }

            $bracketOpeners = array_values($this->bracketPairs);
            $bracketClosers = array_keys($this->bracketPairs);
        } else {
            if ($this->verbose) {
                error_log('>> Looking forwards');
            }

            $bracketOpeners = array_keys($this->bracketPairs);
            $bracketClosers = array_values($this->bracketPairs);
        }

        while ($ttl && $token = $backwards ? array_pop($this->seen) : array_shift($tokens)) {
            if ($this->verbose) {
                error_log('>> token name: ' . $token->getTokenName());
            }

            // check for "opening" bracket

            if (in_array($token->getTokenName(), $bracketOpeners)) {
                array_push($bracketStack, $token->getTokenName());

                if ($this->verbose) {
                    error_log('>> +' . $token->getTokenName() . ' > ' . implode('', $bracketStack));
                }
            }

            if (!count($bracketStack) && static::is_terminator($token)) {
                return $this->restore_and_apply_buffer($token, $tokens, $backwards);
            }

            // check for "closing" bracket

            if (in_array($token->getTokenName(), $bracketClosers)) {
                $opener = array_pop($bracketStack);

                if ($this->verbose) {
                    error_log('>> -' . $token->getTokenName() . ' > ' . implode('', $bracketStack));
                }

                if (null === $opener) {
                    return $this->restore_and_apply_buffer($token, $tokens, $backwards);
                }

                $left = $backwards ? $token->getTokenName() : $opener;
                $right = $backwards ? $opener : $token->getTokenName();

                if ($this->bracketPairs[$left] !== $right) {
                    error_log(
                        'bracket mismatch '
                        . $left
                        . $right
                        . ' on line '
                        . $token->line
                    );
                    die(1);
                }
            }

            if ($backwards) {
                array_unshift($this->buffer, $token);
            } else {
                array_push($this->buffer, $token);
            }

            $ttl--;
        }

        return $this->apply_buffer($tokens, $backwards);
    }

    private function apply_buffer(?array &$tokens = null, $backwards = false)
    {
        $b = $this->buffer;

        $this->buffer = [];

        $b = static::trim_whitespace($b, $leading, $trailing);

        if ($backwards) {
            $this->seen = array_merge($this->seen, $leading);
        } else {
            $tokens = array_merge($trailing, $tokens);
        }

        return $b;
    }

    private function restore_and_apply_buffer($token, ?array &$tokens = null, $backwards = false)
    {
        if ($backwards) {
            array_push($this->seen, $token);
        } else {
            array_unshift($tokens, $token);
        }

        return $this->apply_buffer($tokens, $backwards);
    }
    public static function trim_whitespace(array $bits, array &$leading = null, array &$trailing = null)
    {
        $trimmed = [];
        $collected = [];

        for ($i = 0; $i < count($bits) && $bits[$i]->getTokenName() == 'T_WHITESPACE'; $i++) ;

        $leading = array_slice($bits, 0, $i);

        for (; $i < count($bits); $i++) {
            $collected[] = $bits[$i];

            if ($bits[$i]->getTokenName() != 'T_WHITESPACE') {
                $trimmed = array_merge($trimmed, $collected);
                $collected = [];
            }
        }

        $trailing = $collected;

        return $trimmed;
    }

    public function out_of_tokens(): void
    {
        foreach ($this->seen as $token) {
            fwrite($this->stream, $token->text);
        }
    }

    public static function is_terminator($token): bool
    {
        return in_array($token->getTokenName(), array_merge(
            [',', ';', 'T_RETURN', 'T_DOUBLE_ARROW', 'T_ECHO', 'T_OPEN_TAG', 'T_OPEN_TAG_WITH_ECHO', 'T_CLOSE_TAG', 'T_CASE'],
            static::$lowerPrecedenceOperators,
        ));
    }
}
