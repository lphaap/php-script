<?php

class PHPScript {
    public static function require($path) {
        $original_pscript = file_get_contents($path);
        $pscript = self::parse($original_pscript);

        echo
            '</br></br>
            <b>SOURCE:</b>
            <pre style="">' .
                htmlspecialchars($original_pscript) .
            '</pre>'
        ;

        echo
            '</br></br>
            <b>COMPILED:</b>
            <pre style="">' .
                htmlspecialchars($pscript) .
            '</pre>'
        ;
    }

    private static function parse($pscript) {
        $parsed_script = str_replace('<?pscript', '<?php', $pscript);
        $parsed_script = preg_replace('/client \{(?s)(.*?)(?s)\}/', '<script>$2 $1</script>', $parsed_script);
        return $parsed_script;
    }
}
