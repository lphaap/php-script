<?php

require_once(ROOT_PATH . "/processor/PHPScript.php");

?>

<h1>PHP SCRIPT DEMO</h1>

<?=
    PHPScript::require(ROOT_PATH . "/public/page.pscript");
?>
