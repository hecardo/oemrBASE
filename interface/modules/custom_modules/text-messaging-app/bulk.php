<?php

/*
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  Copyright (c) 2022.
 *  All Rights Reserved
 */

require_once dirname(__DIR__, 3 ) . '/globals.php';

use OpenEMR\Core\Header;

$key = $GLOBALS['texting_enables'];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Texting with TextBelt Service</title>
    <?php Header::setupHeader() ?>
    <script src="lib/js/nav.js"></script>
</head>
<body>
    <div class="container m-5">
        <?php
            if (empty($key)) {
                die(xlt('You have to enter a key in the globals to use this module'));
            }
            require_once dirname(__FILE__) . "/views/nav_top.php";
        ?>
        <h1><?php echo xlt('Bulk Texting'); ?></h1>
        <form method="post" action="public/index.php/texting/bulk" name="textcrude" >
            <input type="text" name="pnumbers" value="" class="form-control m-2" placeholder="place commas between numbers">
            <textarea name="message" class="form-control m-2" placeholder="Enter mass message here"></textarea>
            <input class="btn btn-primary mt-3" type="submit" value="Send Message">
        </form>
    </div>
</body>
</html>

