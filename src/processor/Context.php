<?php

namespace PScript;

class Context {
    private $variables = [];

    public function set($name, $value) {
        $this->variables[$name] = $value;
    }

    public function set_attribute($var, $key, $value) {
        $variable = $this->get($var);
        if ($variable === null) {
            return;
        }

        if (is_array($variable)) {
            $variable[$key] = $value;
            $this->set($var, $variable);
        }
        else if (is_object($variable)) {
            $variable->$key = $value;
            $this->set($var, $variable);
        }
    }

    public function get($name) {
        return $this->variables[$name] ?? null;
    }

    public function get_attribute($var, $key) {
        $variable = $this->get($var);
        if ($variable === null) {
            return null;
        }

        if (is_array($variable)) {
            return $variable[$key];
        }
        else if (is_object($variable)) {
            return $variable->$key;
        }

        return null;
    }

    public function reset() {
        $this->variables = [];
    }
}
