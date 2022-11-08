<?php

class mark_up implements conversion_handler, enter_context_listener, enter_control_listener, enter_control_body_listener, leave_control_body_listener, leave_control_listener, leave_context_listener, left_context_listener, before_listener
{
    public $context_id = null;
    public $id = 0;
    public $signaller = null;
    public $stream = null;
    public int $level = 0;

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
        $id = ++$this->id;

        fwrite($this->stream, str_repeat(DEBUG_PREFIX, $this->level) . '<div class="context" id="' . $id . '">' . "\n");

        $this->level++;

        return (object) compact('id');
    }

    public function enter_control(array &$tokens, string $control)
    {
        $id = ++$this->id;

        fwrite($this->stream, str_repeat(DEBUG_PREFIX, $this->level) . '<div class="control" id="' . $id . '">' . "\n");

        $this->level++;

        return (object) compact('id');
    }

    public function enter_control_body(array &$tokens, string $name)
    {
        $id = ++$this->id;

        fwrite($this->stream, str_repeat(DEBUG_PREFIX, $this->level) . '<div class="controlbody" id="' . $id . '">' . "\n");

        $this->level++;

        return (object) compact('id');
    }

    public function handle_tokens(array &$tokens): void
    {
        $token = array_shift($tokens);

        if ($token->getTokenName() !== 'T_INLINE_HTML') {
            fwrite($this->stream, str_repeat(DEBUG_PREFIX, $this->level) . htmlspecialchars($token->text) . "<br>\n");
        } else {
            fwrite($this->stream, str_repeat(DEBUG_PREFIX, $this->level) . '«HTML»' . "<br>\n");
        }
    }

    public function leave_control_body(array &$tokens, string $control, ?string $daisychain, $message): void
    {
        $this->level--;

        fwrite($this->stream, str_repeat(DEBUG_PREFIX, $this->level) . '</div><!-- /controlbody ' . $message->id . ' -->' . "\n");
    }

    public function leave_control(array &$tokens, string $name, ?string $daisychain, $message): void
    {
        $this->level--;

        fwrite($this->stream, str_repeat(DEBUG_PREFIX, $this->level) . '</div><!-- /control ' . $message->id . ' -->' . "\n");
    }

    public function leave_context(array &$tokens, ?string $context_opener, ?string $context_closer, $message): void
    {
        $this->level--;
        $this->context_id = $message->id;
    }

    public function left_context(array &$tokens): void
    {
        fwrite($this->stream, str_repeat(DEBUG_PREFIX, $this->level) . '</div><!-- context ' . $this->context_id . ' -->' . "\n");
    }
    public function before(array &$tokens): void
    {
        fwrite($this->stream, '
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
        ');
    }
}
