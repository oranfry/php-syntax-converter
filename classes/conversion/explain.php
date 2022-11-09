<?php

namespace conversion;

use conversion_handler;
use signaller;

class explain implements conversion_handler
{
    public $stream;

    public function set_signaller(signaller $signaller): void
    {
    }

    public function set_output_stream($stream): void
    {
        $this->stream = $stream;
    }

    public function handle_tokens(array &$tokens): void
    {
        $token = array_shift($tokens);

        fwrite($this->stream, 'Line ' . $token->line . ': ' . $token->getTokenName() . ' (' . str_replace("\n", '\\n', $token->text) . ')' . PHP_EOL);
    }
}
