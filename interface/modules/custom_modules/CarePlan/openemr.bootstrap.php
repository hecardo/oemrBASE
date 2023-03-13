<?php
/**
 * Bootstrap custom module for WMT Care Plan module.
 *
 * @package   wmt\careplan
 * @author    Ron Criswell <ron@medtechsvcs.com>
 * @copyright Copyright (c) 2023 Medical Technology Services <https://medtechsvcs.com/>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\CarePlan;

/**
 * @global OpenEMR\Core\ModulesClassLoader $classLoader
 */
$library = $GLOBALS['srcdir'] . DIRECTORY_SEPARATOR . 'WMT';

$classLoader->registerNamespaceIfNotExists('WMT\\Classes\\', $library . DIRECTORY_SEPARATOR . 'Classes');
$classLoader->registerNamespaceIfNotExists('WMT\\Objects\\', $library . DIRECTORY_SEPARATOR . 'Objects');
$classLoader->registerNamespaceIfNotExists('WMT\\CarePlan\\', __DIR__ . DIRECTORY_SEPARATOR . 'src');

/**
 * @global EventDispatcher $eventDispatcher Injected by the OpenEMR module loader;
 */
$bootstrap = new Bootstrap($eventDispatcher, $GLOBALS['kernel']);
$bootstrap->subscribeToEvents();
