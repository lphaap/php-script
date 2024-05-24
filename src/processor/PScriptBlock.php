<?php

namespace PScript;

class PScriptBlock {

    public static function create($code) {
        return new PScriptBlock($code);
    }

    private $block;

    function __construct($code) {
        $this->block = $code;
        return $this;
    }

    public function get() {
        $code = $this->block;
        $code = preg_replace(
            '/<script\s+"id"="pscript-block(.*?)>|<\/script>/',
            '',
            $code
        );

        $code = trim($code);

        preg_match('/client\s*\{/s', $code, $client_block);
        if (!empty($client_block)) {
            $code = preg_replace('/client\s*\{/s', '', $code);
            $code = rtrim($code, "}");
        }

        return $code;
    }

}
