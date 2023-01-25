<?php

/**
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  Copyright (c) 2022.
 *  All rights reserved
 */

$dir = '/interface/modules/custom_modules/text-messaging-app';

?>
<!doctype html>
<html>
<title><?php echo xlt('Home page'); ?></title>
<?php \OpenEMR\Core\Header::setupHeader(['common']) ?>
<head>

</head>
<body>
<div class="container m-5">
    <h1>Home View!</h1>
    <?php include_once "nav.php"; ?>
</div>
</body>
</html>
