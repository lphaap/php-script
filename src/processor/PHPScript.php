<?php

require_once('Context.php');

class PHPScript {
    public static function require($path, $context = []) {
        $processor = new self($context);
        $processor->process($path);
    }

    private $context;

    private function __construct($context) {
        $this->context = new Context();
        foreach($context as $var => $value) {
            $this->context->set($var, $value);
        }
    }

    private function process ($path) {
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

    private function parse($pscript) {
        $parsed_rows = [];

        $parsed_script = str_replace(
            '<?pscript',
            '<?php',
            $pscript
        );

        preg_match_all(
            '/(\$[\w]+)\s*=\s*(.*?);/',
            $parsed_script,
            $php_variable_clauses,
            PREG_SET_ORDER
        );

        $js_variable_clauses = [];
        foreach ($php_variable_clauses as $parsed_clause) {
            eval($parsed_clause[0]); // Evaluate variable to local scope
            $variable_name = str_replace('$', '', $parsed_clause[1]);
            $this->context->set($variable_name, $$variable_name);
        }

        preg_match_all(
            '/client \{(?s)(.*?)(?s)\}/',
            $parsed_script,
            $client_blocks
        );

        $block_index = 0;
        foreach($client_blocks[1] ?? [] as $block) {

            $parsed_rows = [];
            $rows = preg_split("/\r\n|\n|\r/", $block);
            foreach ($rows as $row) {
                $trimmed_row = trim($row);
                if (!empty($trimmed_row)) {

                    if (str_contains($trimmed_row, '$')) {
                        $parsed_row = $trimmed_row;
                        preg_match_all('/\$\w+/', $trimmed_row, $matched_variables);
                        foreach ($matched_variables[0] as $variable_clause) {
                            $var_name = str_replace('$', '', $variable_clause);
                            list($hygienic_var_name, $var_clause) = $this->parse_variable($var_name);

                            array_unshift($parsed_rows, $var_clause);
                            $parsed_row = str_replace($variable_clause, $hygienic_var_name, $parsed_row);
                        }
                        $parsed_rows[] = $parsed_row;
                        continue;
                    }

                    $parsed_rows[] = $trimmed_row;
                }
            }

            $parsed_script = preg_replace(
                '/client \{(?s)(.*?)(?s)\}/',
                "<script id=\"pscript-block-{$block_index}\">\n"
                    . implode("\n", $parsed_rows) .
                "\n</script>",
                $parsed_script,
                1
            );

            $block_index++;
        }

        return $parsed_script;
    }

    private function parse_variable($name) {
        $value = $this->context->get($name);
        if ($value === null) {
            throw new Exception("Variable '{$name}' not found in context");
        }

        $converted_value = $this->convert_variable($value);
        $hygienic_name = $this->get_hygienic_name($name);

        $clause = "const {$hygienic_name} = {$converted_value};";

        return [$hygienic_name, $clause];
    }

    function get_hygienic_name($name, $subfix_length = 8) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';

        // Start with a random letter or underscore
        $hygienic_name = $name . '_' . $chars[rand(0, strlen($chars) - 1)];

        // Generate the rest of the variable name
        for ($index = 1; $index < $subfix_length; $index++) {
            // Mix letters and numbers for the rest of the variable name
            $char = (rand(0, 10) > 5) ?
                $chars[rand(0, strlen($chars) - 1)] :
                $numbers[rand(0, strlen($numbers) - 1)];

            $hygienic_name .= $char;
        }

        return $hygienic_name;
    }

    function convert_variable($value) {
        if ($value === null) {
            return "null";
        }

        if (is_numeric($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? "true" : "false";
        }

        if (is_string($value)) {
            return "\"$value\"";
        }

        if (is_object($value) || is_callable($value)) {
            throw new Error("Not implemented yet.");
        }

        if (is_array($value)) {
            $parsed_values = [];
            foreach ($value as $sub_value) {
                $parsed_values[] = $this->convert_variable($sub_value);
            }
            return "[" . implode(", ", $parsed_values) . "]";
        }
    }
}
