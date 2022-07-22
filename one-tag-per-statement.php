#!/usr/bin/php
<?php

require __DIR__ . '/lib.php';

class one_tag_per_statement implements conversion_handler, enter_php_listener, leave_php_listener, out_of_tokens_listener
{
    public $extra = null;
    public $fn_level = 0;
    public $for_level = 0;
    public $in_php = false;
    public $just_ended_heredoc = false;
    public $just_opened_php = false;
    public $just_opened_switch = false;
    public $just_saw_comment = false;
    public $php_with_echo = false;
    public $phpbits = [];
    public $signaller = null;
    public $suppress_next = false;
    public $ternary_level = 0;

    public function set_signaller(signaller $signaller): void
    {
        $this->signaller = $signaller;
    }

    public function is_terminator(object $token): bool
    {
        $name = $token->getTokenName();

        if (in_array($name, ['T_DOC_COMMENT', 'T_COMMENT'])) {
            return !$this->phpbits;
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

    public function terminate($token): void
    {
        if ($token && $token->getTokenName() !== 'T_CLOSE_TAG') {
            $this->phpbits[] = $token;
        }

        $leading_whitespace = [];
        $trailing_whitespace = [];

        $trimmed = static::trim_whitespace($this->phpbits, $leading_whitespace, $trailing_whitespace);

        if ($trimmed || $this->just_saw_comment) {
            if (!$this->just_opened_php) {
                foreach ($leading_whitespace as $whitespace) {
                    echo $whitespace->text;
                }
            }

            if ($this->php_with_echo) {
                $this->php_with_echo = false;

                echo '<?= ';
            } else {
                echo '<?php ';
            }

            echo implode('', array_map(fn ($bit) => $bit->text, $trimmed));

            if ($this->just_ended_heredoc) {
                $this->just_ended_heredoc = false;

                echo "\n";
            } else {
                echo ' ';
            }

            echo '?>';

            foreach ($trailing_whitespace as $whitespace) {
                echo $whitespace->text;
            }

            $this->just_saw_comment = false;
            $this->just_opened_php = false;
        }

        $this->phpbits = [];
    }

    public function handle_tokens(array &$tokens): void
    {
        $token = array_shift($tokens);
        $name = $token->getTokenName();

        if ($this->suppress_next) {
            echo $this->extra;

            $this->suppress_next = false;
            $this->extra = null;

            return;
        }

        if (!$this->in_php) {
            echo $token->text;

            return;
        }

        if (in_array($name, ['T_COMMENT', 'T_DOC_COMMENT'])) {
            $this->just_saw_comment = true;
        } elseif ($name !== 'T_WHITESPACE') {
            $this->just_saw_comment = false;
        }

        if ($this->is_terminator($token)) {
            $this->terminate($token);

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

        $this->terminate(@$tokens[0]);
    }

    public static function trim_whitespace(array $bits, array &$leading, array &$trailing)
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

    public function out_of_tokens(): void
    {
        $this->terminate(null);
    }
}

$code = stream_get_contents(STDIN);
$tokens = PhpToken::tokenize($code);
$handler = new one_tag_per_statement;
$signaller = new signaller($handler, $tokens);

$signaller->convert();
