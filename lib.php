<?php

const MATCHING_BRACES = [
    '${' => '}',
    '(' => ')',
    '[' => ']',
    'T_CURLY_OPEN' => '}',
    'T_DOLLAR_OPEN_CURLY_BRACES' => '}',
    '{' => '}',
];

const CONTROL_STRUCTURES = [
    'T_IF' => ['T_ELSEIF', 'T_ELSE', 'T_ENDIF'],
    'T_ELSEIF' => ['T_ELSEIF', 'T_ELSE', 'T_ENDIF'],
    'T_ELSE' => ['T_ENDIF'],
    'T_WHILE' => ['T_ENDWHILE'],
    'T_FOR' => ['T_ENDFOR'],
    'T_FOREACH' => ['T_ENDFOREACH'],
    'T_SWITCH' => ['T_ENDSWITCH'],
];

function convert_r(conversion_handler $handler, array &$tokens, array $context_closers = [], ?string $control = null)
{
    $last_control = null;
    $ternary_level = 0;

    while ($peek = @$tokens[0]) {
        if (in_array($peek->getTokenName(), $context_closers)) {
            // We have found the close of the current context, fall out

            $handler->leave_context($tokens, $control);

            return $peek->getTokenName();
        }

        if (
            $last_control
            && (
                ($peek->getTokenName() === ':' && !$ternary_level)
                || $peek->getTokenName() === '{'
            )
        ) {
            // We have found the opening brace of a control structure
            // Call the handler so it may recurse in turn if it wants to

            $callee_context_closers = $peek->getTokenName() === ':' ? CONTROL_STRUCTURES[$last_control] : [MATCHING_BRACES[$peek->getTokenName()]];
            $handler->enter_context($tokens, $callee_context_closers, $last_control);
            $closed_by = convert_r($handler, $tokens, $callee_context_closers, $last_control);

            if (in_array($closed_by, ['T_ELSEIF', 'T_ELSE'])) {
                $last_control = $closed_by;
            } else {
                $last_control = null;
            }

            continue;
        }

        if (in_array($peek->getTokenName(), array_keys(MATCHING_BRACES))) {
            // We have found an opening brace other than for a control structure
            // Call the handler so it may recurse in turn if it wants to

            $callee_context_closers = [MATCHING_BRACES[$peek->getTokenName()]];

            $handler->enter_context($tokens, $callee_context_closers);

            convert_r($handler, $tokens, $callee_context_closers);

            continue;
        }

        if (in_array($peek->getTokenName(), array_keys(CONTROL_STRUCTURES))) {
            // found a control structure, which can use 'X (): ...; endX' instead of '{ ... }'

            $last_control = $peek->getTokenName();
        }

        if ($peek->getTokenName() == '?') {
            $ternary_level++;
        }

        if ($peek->getTokenName() == ':' && $ternary_level) {
            $ternary_level--;
        }

        $handler->handle_tokens($tokens);
    }
}

interface conversion_handler {
    public function enter_context(array &$tokens, array $context_closers, ?string $control): void;
    public function handle_tokens(array &$tokens): void;
    public function leave_context(array &$tokens, ?string $control): void;
}