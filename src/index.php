<?php

define('ROOT_PATH', __DIR__);

require_once(ROOT_PATH . "/Router.php");

?>

<div style="padding: 5px;">
    <a href="/" style>
        <h1>PHP SCRIPT DEMO</h1></br>
    </a>
    <?php
        $found = Router::get($_SERVER['REQUEST_URI']);
        if (!$found) {
    ?>
            <h1>Not found :(</h1>
    <?php } ?>
</div>
