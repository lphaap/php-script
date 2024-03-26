<?php

namespace PScript;

class PScriptVar {

    public static function reference($var_name) {
        return new PScriptVar($var_name);
    }

    private $variable_name;

    function __construct($var_name) {
        $this->variable_name = $var_name;
        return $this;
    }

    public function get() {
        return $this->variable_name;
    }

}
