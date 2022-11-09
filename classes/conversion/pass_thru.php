<?php

namespace conversion;

use conversion_handler;
use signaller;

class pass_thru implements conversion_handler
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
        fwrite($this->stream, array_shift($tokens)->text);
    }
}
