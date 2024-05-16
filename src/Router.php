<?php

class Router {

    public static function get($path) {
        try {
            // Navigate
            if (empty($path) || $path === "/") {
                require_once(ROOT_PATH . "/public/navigate.php");
                return true;
            }

            // Expression injection
            if (str_contains($path, 'wip')) {
                require_once(ROOT_PATH . "/public/wip/index.php");
                return true;
            }

            // Client keyword
            if (str_contains($path, 'client-keyword')) {
                require_once(ROOT_PATH . "/public/client-keyword/index.php");
                return true;
            }

            // Variable injection
            if (str_contains($path, 'variable-injection')) {
                require_once(ROOT_PATH . "/public/variable-injection/index.php");
                return true;
            }

            // Expression injection
            if (str_contains($path, 'expression-injection')) {
                require_once(ROOT_PATH . "/public/expression-injection/index.php");
                return true;
            }

            // Cross-language references
            if (str_contains($path, 'cross-language-references')) {
                require_once(ROOT_PATH . "/public/cross-language-references/index.php");
                return true;
            }

            // Cross-language references
            if (str_contains($path, 'namespaces')) {
                require_once(ROOT_PATH . "/public/namespaces/index.php");
                return true;
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
