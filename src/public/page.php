<?php

require_once(ROOT_PATH . "/processor/PHPScript.php");

use \PScript\PScript;

?>

<h1>PHP SCRIPT DEMO</h1>

<?=
    require_once PScript::require(ROOT_PATH . "/public/page.pscript");
?>


<script>
    setTimeout(() => window.location.reload(), 2000)
</script>
