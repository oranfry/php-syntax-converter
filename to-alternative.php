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
    public $ignore_next_token = false;

    public function set_signaller(signaller $signaller): void
    {
        $this->signaller = $signaller;
    }

    public function enter_context(array &$tokens, ?string $context_opener, ?array $context_closers)
    {
        $opening_tag_suppressed = false;

        $id = ++$this->id;

        if ($this->control_body_starting) {
            $this->control_body_starting = false;

            if ($context_opener) {
                $this->ignore_next_token = true;
            }

            if ($context_opener == '{') {
                $opening_tag_suppressed = true;
                array_shift($tokens);
            } elseif ($context_opener == ':') {
                array_shift($tokens); // our enter_control_body will print it
            }
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

        $got = @$tokens[0];

        if (!$got || $got->getTokenName() !== $control) {
            error_log('expected ' . $control . ', got ' ($got ? $got->getTokenName() : 'null'));

            exit(1);
        }

        $this->level++;

        return (object) compact('id');
    }

    public function enter_control_body(array &$tokens, string $name)
    {
        $this->control_body_starting = true;

        echo ':';

        $id = ++$this->id;

        $end = ENDS[$name];

        $this->level++;

        return (object) compact('id', 'end');
    }

    public function handle_tokens(array &$tokens): void
    {
        if ($this->ignore_next_token) {
            $this->ignore_next_token = false;

            return;
        }

        $token = array_shift($tokens);

        if ($token) {
            echo $token->text;
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
    }

    public function leave_control(array &$tokens, string $name, ?string $daisychain, $message): void
    {
        $this->level--;
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
            } elseif (!in_array($context_closer, ['T_ELSE', 'T_ELSEIF'])) {
                $this->handle_tokens($tokens);
            }
        }
    }
}

convert(stream_get_contents(STDIN));
