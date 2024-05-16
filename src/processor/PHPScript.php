<?php

namespace PScript;

use Attribute;

require_once('Context.php');
require_once('PScriptVar.php');

class PScript {

    public const PSCRIPT_CACHE = __DIR__ . "/__pscript_cache/";

    private const CACHE_ENABLED = false;

    private const EVAL_NAMESPACE = "namespace PScript; ";

    private static function debug($source_file_path, $cache_file_path) {
        $source = file_get_contents($source_file_path);
        $compiled = file_get_contents($cache_file_path);

        if (DEBUG) {
            echo
            '</br></br>- - - - - - - - - - << DEBUG >> - - - - - - - - - - </br></br>
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

        // Replace PScript tag
        $parsed_script = str_replace(
            '<?pscript',
            '<?php',
            $pscript
        );

        // Evaluate PHP expressions into the current context
        preg_match_all(
            '/(\$[\w]+)(?:\[\'(\w+)\'\]|\->(\w+))?\s*=\s*(.*?);/',
            $parsed_script,
            $php_variable_clauses
        );

        $clause_index = 0;
        foreach ($php_variable_clauses[0] as $full_clause) {
            $variable_reference = $php_variable_clauses[1][$clause_index];
            $array_attribute = $php_variable_clauses[2][$clause_index] ?? null;
            $object_attribute = $php_variable_clauses[3][$clause_index] ?? null;
            $value = $php_variable_clauses[4][$clause_index];

            $variable_name = str_replace('$', '', $variable_reference);

            $object_reference = '';
            if ($array_attribute) {
                $object_reference = "['{$array_attribute}']";
            }
            else if ($object_attribute) {
                $object_reference = "->{$array_attribute}";
            }

            if (str_contains($full_clause, ' client ')) {
                $client_reference = trim(str_replace('client', '', $value));
                $parsed_script = str_replace($full_clause, "", $parsed_script);

                $full_clause = $variable_reference . $object_reference .
                ' = PScriptVar::reference("' . $client_reference . '");';
            }

            // Evaluate variable to local scope
            eval(self::EVAL_NAMESPACE . $full_clause);
            $defined_variable = $$variable_name;

            if ($array_attribute) {
                $defined_variable = $defined_variable[$array_attribute];
                $this->context->set_attribute($variable_name, $array_attribute, $defined_variable);
            }
            else if ($object_attribute) {
                $defined_variable = $defined_variable->$array_attribute;
                $this->context->set_attribute($variable_name, $object_attribute, $defined_variable);
            }
            else {
                $this->context->set($variable_name, $defined_variable);
            }

            $clause_index++;
        }

        // Start client block parsing
        preg_match_all(
            '/client\s.*(?=(\{(?:[^{}]+|(?1))*+\}))/x',
            $parsed_script,
            $client_blocks
        );

        $block_index = 0;
        foreach($client_blocks[1] ?? [] as $block) {
            // Save block and keyword for later use
            $keyword_block = $client_blocks[0][$block_index];
            $parsed_block =  preg_replace('/^\{\s*(.*)\s*\}$/s', '$1', $block);

            // Parse all inline PHP expression injections
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

            // Parse each client block row
            $parsed_rows = [];
            $rows = preg_split("/\r\n|\n|\r/", $parsed_block);
            foreach ($rows as $row) {
                $parsed_row = trim($row);
                if (!empty($parsed_row)) {

                    // Evaluate PHP variables into JS format
                    if (str_contains($parsed_row, '$')) {
                        preg_match_all(
                            '/\$(\w+)(?:->(\w+)|\[[\'"](\w+)[\'"]\])?/',
                            $parsed_row,
                            $matched_variables,
                            PREG_SET_ORDER
                        );
                        foreach ($matched_variables as $match) {
                            $variable_reference = $match[0];
                            $variable_name = $match[1];

                            $array_key = !empty($match[2]) ? $match[2] : null;
                            $obj_key = !empty($match[3]) ? $match[3] : null;
                            $attribute = $array_key ?? $obj_key;

                            $parsed_js_variable = $this->parse_variable($variable_name, $attribute);
                            $js_reference = $parsed_js_variable[0];
                            $js_expression = $parsed_js_variable[1];

                            array_unshift($parsed_rows, $js_expression);
                            $parsed_row = str_replace(
                                $variable_reference,
                                $js_reference,
                                $parsed_row
                            );
                        }
                    }

                    $parsed_rows[] = $parsed_row;
                }
            }

            // Parse client function borders
            if (preg_match('/^client\sfunction/', $keyword_block)) {
                $parsed_keyword_block = preg_replace('/^client\s+/', '', $keyword_block);
                array_unshift($parsed_rows, $parsed_keyword_block . " {");
                $parsed_rows[] = "}";
            }

            // Inject Parsed client block with script tags
            $parsed_script = preg_replace(
                '/client\s.*(?=(\{(?:[^{}]+|(?1))*+\}))/x',
                "<script \"id\"=\"pscript-block-{$block_index}\">\n"
                    . implode("\n", $parsed_rows) .
                "\n</script>",
                $parsed_script,
                1
            );

            // Remove the parsed client block
            $parsed_script = str_replace($block, "", $parsed_script);

            $block_index++;
        }

        // Remove existing client keywords
        $parsed_script = str_replace('client', "", $parsed_script);

        return $parsed_script;
    }

    private function parse_variable($variable_name, $attribute = null) {
        if (!empty($attribute)) {
            $php_value = $this->context->get_attribute($variable_name, $attribute);
        }
        else {
            $php_value = $this->context->get($variable_name);
        }

        if ($php_value === null) {
            throw new \Exception("Variable '{$variable_name}' not found in context");
        }

        $converted_value = $this->convert_variable($php_value);
        $hygienic_name = $this->get_hygienic_name($variable_name . ($attribute ? "_$attribute" : ""));

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

        if (is_a($value, 'PScript\PScriptVar')) {
            return $value->get();
        }

        if (is_object($value) || is_callable($value)) {
            throw new \Error("Not implemented yet.");
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
