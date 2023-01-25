<?php
/**
 * Bootstrap custom module for WMT Laboratory module.
 *
 * @package   wmt\laboratory
 * @author    Ron Criswell <ron@medtechsvcs.com>
 * @copyright Copyright (c) 2023 Medical Technology Services <https://medtechsvcs.com/>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\Laboratory;

/**
 * @global OpenEMR\Core\ModulesClassLoader $classLoader
 */
$library = $GLOBALS['srcdir'] . DIRECTORY_SEPARATOR . 'wmt';
//$library = 'c:\Projects\oemrBASE\library\wmt';

$classLoader->registerNamespaceIfNotExists('WMT\\Classes\\', $library . DIRECTORY_SEPARATOR . 'classes');
$classLoader->registerNamespaceIfNotExists('WMT\\Objects\\', $library . DIRECTORY_SEPARATOR . 'objects');
$classLoader->registerNamespaceIfNotExists('WMT\\Laboratory\\', __DIR__ . DIRECTORY_SEPARATOR . 'src');
//$classLoader->registerNamespaceIfNotExists('WMT\\Laboratory\\Common\\', __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Common');

/**
 * @global EventDispatcher $eventDispatcher Injected by the OpenEMR module loader;
 */
$bootstrap = new Bootstrap($eventDispatcher, $GLOBALS['kernel']);
$bootstrap->subscribeToEvents();
