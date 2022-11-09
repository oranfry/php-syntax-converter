<?php

namespace conversion;

use conversion_handler;
use enter_php_listener;
use leave_php_listener;
use out_of_tokens_listener;
use signaller;

class one_tag_per_statement implements conversion_handler, enter_php_listener, leave_php_listener, out_of_tokens_listener
{
    public $extra = null;
    public $fn_level = 0;
    public $for_level = 0;
    public $in_php = false;
    public $just_ended_heredoc = false;
    public $just_opened_php = false;
    public $just_opened_switch = false;
    public $php_with_echo = false;
    public $phpbits = [];
    public $signaller = null;
    public $stream;
    public $suppress_next = false;
    public $ternary_level = 0;

    public function set_signaller(signaller $signaller): void
    {
        $this->signaller = $signaller;
    }

    public function set_output_stream($stream): void
    {
        $this->stream = $stream;
    }

    public function is_terminator(object $token): bool
    {
        $name = $token->getTokenName();

        if (in_array($name, ['T_DOC_COMMENT', 'T_COMMENT'])) {
            return !$this->phpbits;
        }

        if (
            $this->signaller->has($this->phpbits, ['T_COMMENT']) &&
            $this->signaller->has_only($this->phpbits, ['T_WHITESPACE', 'T_COMMENT'])
        ) {
            return true;
        }

        if ($name == ':') {
            if ($this->just_opened_switch) {
                $this->just_opened_switch = false;

                return false;
            }

            if ($this->fn_level) {
                $this->fn_level--;

                return false;
            }

            if ($this->ternary_level) {
                $this->ternary_level--;

                return false;
            }

            return true;
        }

        if ($name == ';') {
            if ($this->for_level) {
                $this->for_level--;

                return false;
            }

            return true;
        }

        return false;
    }

    public function terminate($token, array &$tokens): void
    {
        if ($token && $token->getTokenName() !== 'T_CLOSE_TAG') {
            $this->phpbits[] = $token;
        }

        while (($peek = @$tokens[0]) && $peek->getTokenName() == 'T_WHITESPACE') {
            $this->phpbits[] = array_shift($tokens);
        }

        $leading_whitespace = [];
        $trailing_whitespace = [];

        $trimmed = static::trim_whitespace($this->phpbits, $leading_whitespace, $trailing_whitespace);

        if ($trimmed) {
            if (!$this->just_opened_php) {
                foreach ($leading_whitespace as $whitespace) {
                    fwrite($this->stream, $whitespace->text);
                }
            }

            if ($this->php_with_echo) {
                $this->php_with_echo = false;

                fwrite($this->stream, '<?= ');
            } else {
                fwrite($this->stream, '<?php ');
            }

            fwrite($this->stream, implode('', array_map(fn($bit) => $bit->text, $trimmed)));

            $sep = ' ';

            if ($this->just_ended_heredoc) {
                $this->just_ended_heredoc = false;
                $sep = "\n";
            }

            fwrite($this->stream, $sep . '?>');

            if (count($trailing_whitespace) > 1) {
                error_log('More than one trailing whitespace?');

                exit(1);
            }

            if ($whitespace = @$trailing_whitespace[0]) {
                $first = substr($whitespace->text, 0, 1);

                if ($first !== $sep) {
                    fwrite($this->stream, $first);
                }

                fwrite($this->stream, substr($whitespace->text, 1));
            }

            $this->just_opened_php = false;
        }

        $this->phpbits = [];
    }

    public function handle_tokens(array &$tokens): void
    {
        $token = array_shift($tokens);
        $name = $token->getTokenName();

        if ($this->suppress_next) {
            if ($this->extra !== null) {
                fwrite($this->stream, $this->extra);
            }

            $this->suppress_next = false;
            $this->extra = null;

            return;
        }

        if (!$this->in_php) {
            fwrite($this->stream, $token->text);

            return;
        }

        if ($this->is_terminator($token)) {
            $this->terminate($token, $tokens);

            return;
        }

        if ($name == 'T_SWITCH') {
            $this->just_opened_switch = true;
        } elseif ($name == '?') {
            $this->ternary_level++;
        } elseif ($name == 'T_FOR') {
            $this->for_level = 2;
        } elseif ($name == 'T_FN') {
            $this->fn_level++;
        } elseif ($name == 'T_END_HEREDOC') {
            $this->just_ended_heredoc = true;
        }

        $this->phpbits[] = $token;
    }

    public function enter_php(array &$tokens, bool $with_echo): void
    {
        $this->suppress_next = true;
        $this->in_php = true;
        $this->just_opened_php = true;

        if ($with_echo) {
            $this->php_with_echo = true;
        }
    }

    public function leave_php(array &$tokens): void
    {
        $this->in_php = false;
        $this->extra = @$tokens[0]->text[2];

        $this->suppress_next = true;

        $this->terminate(@$tokens[0], $tokens);
    }

    public static function trim_whitespace(array $bits, array &$leading, array &$trailing)
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
        $fake_tokens = [];
        $this->terminate(null, $fake_tokens);
    }
}
