<?php

namespace conversion;

use conversion_handler;
use enter_context_listener;
use enter_control_body_listener;
use enter_control_listener;
use leave_context_listener;
use leave_control_body_listener;
use signaller;

class to_alternative implements conversion_handler, enter_context_listener, enter_control_listener, enter_control_body_listener, leave_control_body_listener, leave_context_listener
{
    public $control_body_starting = false;
    public $id = 0;
    public $ignore_next_token = false;
    public $signaller = null;
    public $stream;

    public function set_signaller(signaller $signaller): void
    {
        $this->signaller = $signaller;
    }

    public function set_output_stream($stream): void
    {
        $this->stream = $stream;
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

        return (object)compact('id', 'opening_tag_suppressed');
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

        return (object)compact('id');
    }

    public function enter_control_body(array &$tokens, string $name)
    {
        $this->control_body_starting = true;

        fwrite($this->stream, ':');

        $id = ++$this->id;

        $end = ENDS[$name];

        return (object)compact('id', 'end');
    }

    public function handle_tokens(array &$tokens): void
    {
        if ($this->ignore_next_token) {
            $this->ignore_next_token = false;

            return;
        }

        $token = array_shift($tokens);

        if ($token) {
            fwrite($this->stream, $token->text);
        } else {
            error_log('Asked to handle token but there is none');
            error_log(print_r(debug_backtrace(), true));
        }
    }

    public function leave_control_body(array &$tokens, string $control, ?string $daisychain, $message): void
    {
        if (!$daisychain) {
            fwrite($this->stream, $message->end . ';');
        }
    }

    public function leave_context(array &$tokens, ?string $context_opener, ?string $context_closer, $message): void
    {
        $this->ignore_next_token = true;

        if ($message->opening_tag_suppressed) {
            $got = array_shift($tokens);

            if ($got->getTokenName() !== '}') {
                error_log('expected }, got ' . $got->getTokenName());
                exit(1);
            }
        } elseif ($context_closer && in_array($context_closer, ENDERS)) {
            $got = array_shift($tokens);

            if ($context_closer !== $got->getTokenName()) {
                error_log('expected ' . $context_closer . ', got ' . $got->getTokenName());

                exit(1);
            }

            $peek = $this->signaller->peek(['T_WHITESPACE', 'T_COMMENT'], $peek_index);

            if ($peek && $peek->getTokenName() === ';') {
                array_splice($tokens, $peek_index, 1);
            }
        } else {
            $this->ignore_next_token = false;
        }
    }
}

