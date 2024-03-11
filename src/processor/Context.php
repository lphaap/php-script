<?php

class Context {
    private $variables = [];

    public function set($name, $value) {
        $this->variables[$name] = $value;
    }

    public function get($name) {
        return $this->variables[$name] ?? null;
    }

    public function reset() {
        $this->variables = [];
    }
}