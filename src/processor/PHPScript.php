<?php

namespace PScript;

require_once('Context.php');

class PScript {

    public const PSCRIPT_CACHE = __DIR__ . "/__pscript_cache/";

    private const CACHE_ENABLED = false;

    private const EVAL_NAMESPACE = "namespace PScript; ";

    private static function debug($source_file_path, $cache_file_path) {
        $source = file_get_contents($source_file_path);
        $compiled = file_get_contents($cache_file_path);

        echo
            '- - - - - - - - - - << DEBUG >> - - - - - - - - - - </br></br>
            <b>SOURCE:</b>
            <pre style="">' .
                htmlspecialchars($source) .
            '</pre>'
        ;

        echo
            '</br>
            <b>COMPILED:</b>
            <pre style="">' .
                htmlspecialchars($compiled) .
            '</pre>
            </br>- - - - - - - - - - << DEBUG >> - - - - - - - - - - </br></br>'

        ;
    }

    public static function require($source_file_path, $context = []) {
        $cache_file_hash = hash('sha256', $source_file_path);
        $cache_file_path = self::PSCRIPT_CACHE . $cache_file_hash . '.php';

        if (self::CACHE_ENABLED && file_exists($cache_file_path)) {
            $cache_last_modified = filemtime($cache_file_path);
            $source_last_modified = filemtime($source_file_path);
            if ($cache_last_modified > $source_last_modified) {
                self::debug($source_file_path, $cache_file_path);
                return $cache_file_path;
            }
        }

        // Run pre-processor
        $processor = new self($context);
        $php = $processor->process($source_file_path);

        file_put_contents($cache_file_path, $php);

        self::debug($source_file_path, $cache_file_path);
        return $cache_file_path;
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
        return $pscript;
    }

    private function parse($pscript) {
        //Wrap everything in a namespace to avoid unhygienic definitons

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
            eval(self::EVAL_NAMESPACE . $parsed_clause[0]); // Evaluate variable to local scope
            $variable_name = str_replace('$', '', $parsed_clause[1]);
            $this->context->set($variable_name, $$variable_name);
        }

        preg_match_all(
            '/<\?php(.*?)\?>/s',
            $parsed_script,
            $php_expressions
        );
        if (!empty($php_expressions[1][0])) {
            eval(self::EVAL_NAMESPACE . $php_expressions[1][0]);
        }

        preg_match_all(
            '/client\s*(?= (\{ (?: [^{}]+ | (?1) )*+ \}) )/x',
            $parsed_script,
            $client_blocks
        );

        $block_index = 0;
        foreach($client_blocks[1] ?? [] as $block) {
            $parsed_block =  preg_replace('/^\{\s*(.*)\s*\}$/s', '$1', $block);

            $inline_expression_pattern = '/(\$\s*\[\s*)([^]]+)(\s*\])/';
            preg_match_all($inline_expression_pattern, $parsed_block, $inline_expressions);

            foreach ($inline_expressions[2] ?? [] as $expression) {
                $parsed_expression = trim($expression);

                $tmp_variable_name = $this->get_hygienic_name('tmp');
                if (!str_ends_with($parsed_expression, ';')) {
                    $parsed_expression = $parsed_expression . ';';
                }

                $this->context->set($tmp_variable_name, $parsed_expression);
                eval(self::EVAL_NAMESPACE . "$" . $tmp_variable_name . " = " . $parsed_expression);

                $js_value = $this->convert_variable($$tmp_variable_name);
                $parsed_block = preg_replace(
                    $inline_expression_pattern,
                    $js_value,
                    $parsed_block,
                    1
                );
            }

            $parsed_rows = [];
            $rows = preg_split("/\r\n|\n|\r/", $parsed_block);
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
                '/client\s*(?= (\{ (?: [^{}]+ | (?1) )*+ \}) )/x',
                "<script \"id\"=\"pscript-block-{$block_index}\">\n"
                    . implode("\n", $parsed_rows) .
                "\n</script>",
                $parsed_script,
                1
            );

            // Remove the parsed client block
            $parsed_script = str_replace($block, "", $parsed_script);;

            $block_index++;
        }

        // Remove existing client keywords
        $parsed_script = str_replace('client', "", $parsed_script);

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
