
<h2>Select a demo:</h2>

<ul>
<?php
foreach (scandir("public/demo/") as $name) {
        if (in_array($name, [".", ".."])) {
            continue;
        }
        if (!DEBUG && $name == "wip") {
            continue;
        }
        $parsed_name = str_replace(".pscript", "", $name);
?>
    <li>
        <a href="/<?= $parsed_name ?>/">
            <?= ucfirst($parsed_name) ?>
        </a>
    </li>
<?php } ?>
</ul>


<style>
    li {
        font-size: 20px;
        margin-bottom: 5px;
    }
</style>
