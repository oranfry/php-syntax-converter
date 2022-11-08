<?php

interface after_listener {
    public function after(array &$tokens): void;
}

interface before_listener {
    public function before(array &$tokens): void;
}

interface conversion_handler {
    public function handle_tokens(array &$tokens): void;
    public function set_signaller(signaller $signaller): void;
    public function set_output_stream($stream): void;
}

interface enter_context_listener {
    public function enter_context(array &$tokens, ?string $context_opener, ?array $context_closers);
}

interface enter_control_body_listener {
    public function enter_control_body(array &$tokens, string $name);
}

interface enter_control_listener {
    public function enter_control(array &$tokens, string $name);
}

interface enter_php_listener {
    public function enter_php(array &$tokens, bool $with_echo): void;
}

interface leave_context_listener {
    public function leave_context(array &$tokens, ?string $context_opener, ?string $context_closer, $message): void;
}

interface leave_control_body_listener {
    public function leave_control_body(array &$tokens, string $name, ?string $daisychain, $message): void;
}

interface leave_control_listener {
    public function leave_control(array &$tokens, string $name, ?string $daisychain, $message): void;
}

interface left_context_listener {
    public function left_context(array &$tokens): void;
}

interface leave_php_listener {
    public function leave_php(array &$tokens): void;
}

interface left_php_listener {
    public function left_php(array &$tokens): void;
}

interface out_of_tokens_listener {
    public function out_of_tokens(): void;
}
