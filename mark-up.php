#!/usr/bin/env php
<?php

const DEBUG_PREFIX = '        ';

require __DIR__ . '/lib.php';

?>
    <style>
        body {
            font-family: monospace;
            font-size: 12px;
        }

        div {
            padding: 0 0 0 1em;
        }

        div > div {
            margin: 0 0 0.5em 1em;
        }

        .context {
            border-left: 3px solid blue;
        }

        .control {
            border-left: 3px solid red;
        }

        .controlbody {
            border-left: 3px solid green;
        }
    </style>
<?php

function convert($code)
{
    $tokens = PhpToken::tokenize($code);
    $handler = new to_alternative;

    (new signaller($handler, $tokens))->convert();
}

class to_alternative implements conversion_handler, enter_context_listener, enter_control_listener, enter_control_body_listener, leave_control_body_listener, leave_control_listener, leave_context_listener, left_context_listener
{
    public $control_body_starting = false;
    public int $level = 0;
    public $id = 0;
    public $signaller = null;
    public $context_id = null;

    public function set_signaller(signaller $signaller): void
    {
        $this->signaller = $signaller;
    }

    public function enter_context(array &$tokens, ?string $context_opener, ?array $context_closers)
    {
        $id = ++$this->id;

        echo str_repeat(DEBUG_PREFIX, $this->level) . '<div class="context" id="' . $id . '">' . "\n";

        $this->level++;

        return (object) compact('id');
    }

    public function enter_control(array &$tokens, string $control)
    {
        $id = ++$this->id;

        echo str_repeat(DEBUG_PREFIX, $this->level) . '<div class="control" id="' . $id . '">' . "\n";

        $this->level++;

        return (object) compact('id');
    }

    public function enter_control_body(array &$tokens, string $name)
    {
        $id = ++$this->id;

        echo str_repeat(DEBUG_PREFIX, $this->level) . '<div class="controlbody" id="' . $id . '">' . "\n";

        $this->level++;

        return (object) compact('id');
    }

    public function handle_tokens(array &$tokens): void
    {
        $token = array_shift($tokens);

        if ($token->getTokenName() !== 'T_INLINE_HTML') {
            echo str_repeat(DEBUG_PREFIX, $this->level) . htmlspecialchars($token->text) . "<br>\n";
        } else {
            echo str_repeat(DEBUG_PREFIX, $this->level) . '«HTML»' . "<br>\n";
        }
    }

    public function leave_control_body(array &$tokens, string $control, ?string $daisychain, $message): void
    {
        $this->level--;

        echo str_repeat(DEBUG_PREFIX, $this->level) . '</div><!-- /controlbody ' . $message->id . ' -->' . "\n";
    }

    public function leave_control(array &$tokens, string $name, ?string $daisychain, $message): void
    {
        $this->level--;

        echo str_repeat(DEBUG_PREFIX, $this->level) . '</div><!-- /control ' . $message->id . ' -->' . "\n";
    }

    public function leave_context(array &$tokens, ?string $context_opener, ?string $context_closer, $message): void
    {
        $this->level--;
        $this->context_id = $message->id;
    }

    public function left_context(array &$tokens): void
    {
        echo str_repeat(DEBUG_PREFIX, $this->level) . '</div><!-- context ' . $this->context_id . ' -->' . "\n";
    }
}

convert(stream_get_contents(STDIN));
