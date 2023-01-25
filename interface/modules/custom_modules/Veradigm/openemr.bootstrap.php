<?php

declare(strict_types=1);


namespace OpenEMR\Modules\Veradigm;

/** @global EventDispatcher $eventDispatcher Injected by the OpenEMR module loader */

$bootstrap = new Bootstrap($eventDispatcher, $GLOBALS['kernel']);
$bootstrap->subscribeToEvents();
