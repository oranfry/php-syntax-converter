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

function convert_r(conversion_handler $handler, array &$tokens, ?array $context_closers = null, ?string $control = null)
{
    $last_control = null;
    $expect_control_details = (bool) $control;
    $control_body_closers = null;
    $ternary_level = 0;

    while ($peek = @$tokens[0]) {
        if ($context_closers && in_array($peek->getTokenName(), $context_closers)) {
            // We have found the close of the current context, fall out

            $handler->leave_context($tokens, $control);

            return $peek->getTokenName();
        }

        if ($control) {
            if ($expect_control_details && $peek->getTokenName() == '(') {
                $expect_control_details = false;
            } elseif (!in_array($peek->getTokenName(), ['T_WHITESPACE', 'T_COMMENT'])) {
                // We have found the beginning of the "body" of a control structure

                $handler->enter_control_body($tokens, $last_control);

                $braceless = false;

                if ($peek->getTokenName() == ':') {
                    $callee_context_closers = CONTROL_STRUCTURES[$last_control];
                } elseif (in_array($peek->getTokenName(), array_keys(CONTROL_STRUCTURES))) {
                } elseif ($peek->getTokenName() == '{') {
                    $callee_context_closers = ['}'];
                } else {
                    $braceless = true;
                    $callee_context_closers = [';', 'T_CLOSE_TAG'];
                }

                $handler->enter_context($tokens, $braceless);
                $closed_by = convert_r($handler, $tokens, $callee_cexpect_control_detailsontext_closers, $last_control);

                if (in_array($closed_by, ['T_ELSEIF', 'T_ELSE'])) {
                    $last_control = $closed_by;
                } else {
                    $last_control = null;
                }
            }
        }

        if (in_array($peek->getTokenName(), array_keys(MATCHING_BRACES))) {
            // We have found the beginning of a context

            if ($control_body_closers) {
                $callee_context_closers = &$control_body_closers;
                $control_body_closers = null;
                $expect_control_details = false;
                $last_control = null;
            } elseif ($matching_brace = @MATCHING_BRACES[$peek->getTokenName()]) {
                $callee_context_closers = [$matching_brace];
            } else {
                $callee_context_closers = [';'];
            }

            $handler->enter_context($tokens);

            convert_r($handler, $tokens, $callee_context_closers);

            continue;
        }

        if (in_array($peek->getTokenName(), array_keys(CONTROL_STRUCTURES))) {
            // found a control structure (if, foreach ,...)

            $handler->enter_control($tokens, $peek->getTokenName());

            convert_r($handler, $tokens, null, $peek->getTokenName());
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
    public function enter_context(array &$tokens, bool $braceless = false): void;
    public function enter_control_body(array &$tokens, string $control): void;
    public function handle_tokens(array &$tokens): void;
    public function leave_context(array &$tokens, ?string $control): void;
}