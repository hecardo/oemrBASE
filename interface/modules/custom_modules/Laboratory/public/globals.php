<?php

/** HTML page with display of global settings */

require_once '../../../../globals.php';
use OpenEMR\Modules\Veradigm\Bootstrap;

$bootstrap = new Bootstrap($GLOBALS['kernel']->getEventDispatcher());
$globalsConfig = $bootstrap->getGlobalConfig();

?>

<!DOCTYPE html>
<html lang="en">

<head>
	<title>Veradigm Settings</title>
</head>

<body>

<ul>
	<li>Global Site License: <?php echo $globalsConfig->getGlobalSiteLicense(); ?></li>
	<li>Partner Username: <?php echo $globalsConfig->getPartnerUsername(); ?></li>
</ul>
<a href="index.php">Back to index</a>

</body>

</html>
