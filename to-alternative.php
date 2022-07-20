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

const ENDERS = [
    'T_ENDIF',
    'T_ENDWHILE',
    'T_ENDFOR',
    'T_ENDFOREACH',
    'T_ENDSWITCH',
];

const DEBUG_PREFIX = '        ';

function convert($code)
{
    $tokens = PhpToken::tokenize($code);
    $handler = new to_alternative;
    $signaller = new signaller($handler, $tokens);

    $signaller->convert();

    if ($handler->level !== 0) {
        error_log('level not zero at the end, [' . $handler->level . '] instead');

        exit(1);
    }
}

class to_alternative implements conversion_handler
{
    public $control_body_starting = false;
    public int $level = 0;
    public $id = 0;
    public $signaller = null;

    public function set_signaller(signaller $signaller): void
    {
        $this->signaller = $signaller;
    }

    public function enter_context(array &$tokens, ?string $context_opener, ?array $context_closers)
    {
        $opening_tag_suppressed = false;

        $id = ++$this->id;

        echo str_repeat(DEBUG_PREFIX, $this->level) . '<div class="context">' . "\n";

        if ($this->control_body_starting) {
            $this->control_body_starting = false;

            if ($context_opener == '{') {
                $opening_tag_suppressed = true;
                array_shift($tokens);
            } elseif ($context_opener == ':') {
                array_shift($tokens); // our enter_control_body will print it
            // } else {
            //     echo '«' . @$tokens[0] . '»';
            }
        } elseif ($context_opener) {
            $this->handle_tokens($tokens);
        }

        $this->level++;

        return (object) compact('id', 'opening_tag_suppressed');
    }

    public function enter_control(array &$tokens, string $control)
    {
        if ($this->control_body_starting) {
            $this->control_body_starting = false;
        }

        $id = ++$this->id;

        echo str_repeat(DEBUG_PREFIX, $this->level) . '<div class="control">' . "\n";

        if (!in_array($control, ['T_ELSE', 'T_ELSEIF'])) {
            $this->handle_tokens($tokens);
        }

        $this->level++;

        return (object) compact('id');
    }

    public function enter_control_body(array &$tokens, string $name)
    {
        $this->control_body_starting = true;

        echo ':';

        $id = ++$this->id;

        echo str_repeat(DEBUG_PREFIX, $this->level) . '<div class="controlbody">' . "\n";

        $end = ENDS[$name];

        $this->level++;

        return (object) compact('id', 'end');
    }

    public function handle_tokens(array &$tokens): void
    {
        $token = array_shift($tokens);

        if ($token) {
            if ($token->getTokenName() !== 'T_INLINE_HTML') {
                echo str_repeat(DEBUG_PREFIX, $this->level) . htmlspecialchars($token->text) . "<br>\n"; // echo stuff in the middle
            } else {
                echo str_repeat(DEBUG_PREFIX, $this->level) . '«HTML»' . "<br>\n"; // echo stuff in the middle
            }
        } else {
            error_log('Asked to handle token but there is none');
            error_log(print_r(debug_backtrace(), true));
        }
    }

    public function leave_control_body(array &$tokens, string $control, ?string $daisychain, $message): void
    {
        $this->level--;

        if (!$daisychain) {
            echo $message->end . ';';
        }

        echo str_repeat(DEBUG_PREFIX, $this->level) . '</div><!-- /controlbody -->' . "\n";
    }

    public function leave_control(array &$tokens, string $name, ?string $daisychain, $message): void
    {
        $this->level--;
        echo str_repeat(DEBUG_PREFIX, $this->level) . '</div><!-- /control -->' . "\n";
    }

    public function leave_context(array &$tokens, ?string $context_opener, ?string $context_closer, $message): void
    {
        $this->level--;

        if ($message->opening_tag_suppressed) {
            $got = array_shift($tokens);

            if ($got->getTokenName() !== '}') {
                error_log('expected }, got ' . $got->getTokenName());
                exit(1);
            }
        } elseif ($context_closer) {
            if (in_array($context_closer, ENDERS)) {
                $got = array_shift($tokens);

                if ($context_closer !== $got->getTokenName()) {
                    error_log('expected ' . $context_closer . ', got ' . $got->getTokenName());

                    exit(1);
                }

                $peek = $this->signaller->peek(['T_WHITESPACE', 'T_COMMENT'], $peek_index);

                if ($peek && $peek->getTokenName() === ';') {
                    array_splice($tokens, $peek_index, 1);
                }
            } elseif (!in_array($context_closer, ['T_ELSE', 'T_ELSEIF' /*, 'T_CLOSE_TAG' */])) {
                $this->handle_tokens($tokens);
            // } elseif ($context_closer == 'T_CLOSE_TAG') {
            //     echo '«';
            //     $this->handle_tokens($tokens);
            //     echo '»';
            }
        }

        echo str_repeat(DEBUG_PREFIX, $this->level) . '</div><!-- context -->' . "\n";
    }
}

echo '<style>div { margin: 0 0 0 1em } .context { background-color: lightblue; } .control { background-color: pink; } .controlbody { background-color: lightgreen; }</style>';

convert(stream_get_contents(STDIN));
