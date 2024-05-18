<?php

require_once(ROOT_PATH . "/processor/PHPScript.php");

use \PScript\PScript;

class Router {

    public static function get($path) {
        try {
            if (empty($path) || $path === "/") {
                require_once(ROOT_PATH . "/public/navigate.php");
                return true;
            }

            foreach (scandir("public/demo/") as $name) {
                if (!str_contains($name, '.pscript')) {
                    continue;
                }

                $pscript_file_name = str_replace(".pscript", '', $name);

                if (str_contains($path, $pscript_file_name)) {
                    $pscript_file_path = __DIR__ . "/public/demo/" . $pscript_file_name . ".pscript";
                    require_once(PScript::require($pscript_file_path));
                    return true;
                }
            }

            return false;
        }
        catch (Exception $e) {
            if (DEBUG) {
                throw $e;
            }
            return false;
        }
    }

}
