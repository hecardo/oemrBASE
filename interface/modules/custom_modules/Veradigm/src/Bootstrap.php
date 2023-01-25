<?php

declare(strict_types=1);


namespace OpenEMR\Modules\Veradigm;

// Core Classes
use OpenEMR\Common\Logging\SystemLogger;
use OpenEMR\Core\Kernel;
use OpenEMR\Events\Globals\GlobalsInitializedEvent;
use OpenEMR\Events\Main\Tabs\RenderEvent;
use OpenEMR\Events\RestApiExtend\RestApiResourceServiceEvent;
use OpenEMR\Events\RestApiExtend\RestApiScopeEvent;
use OpenEMR\Services\Globals\GlobalSetting;
use OpenEMR\Services\Globals\GlobalsService;
use OpenEMR\Menu\MenuEvent;
use OpenEMR\Menu\PatientMenuEvent;
use OpenEMR\Events\RestApiExtend\RestApiCreateEvent;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;


/** Register and setup module components */
class Bootstrap {

	const MOD_INSTALLATION_PATH = '/interface/modules/Veradigm/';

	public $mod_name = '';

	/** @var EventDispatcherInterface The object responsible for sending and subscribing to events through the OpenEMR system */
	private $eventDispatcher;

	/** @var GlobalConfig Holds our module global configuration values that can be used throughout the module. */
	private $globalsConfig;

	/** @var string The folder name of the module. Set dynamically from searching the filesystem. */
	private $moduleDirectoryName;

	/** @var SystemLogger */
	private $logger;

	private $subscribed;

	public function __construct(EventDispatcherInterface $eventDispatcher, ?Kernel $kernel = null) {
		global $GLOBALS;
		if (empty($kernel)) {
			$kernel = new Kernel();
		}
		$this->subscribed = false;
		$this->kernel = $kernel;
		$this->moduleDirectoryName = basename(dirname(__DIR__));
		$this->eventDispatcher = $eventDispatcher;
		// Inject globals
		$this->globalsConfig = new GlobalConfig($GLOBALS);
		$this->mod_name = $this->globalsConfig->getGlobalSectionName();
		$this->logger = new SystemLogger();
		$this->subscribeToEvents();
	}

	/** Path to module public files */
	private function getPublicPath(): string {
		return self::MODULE_INSTALLATION_PATH . ($this->moduleDirectoryName ?? '') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR;
	}

	/** Path to module assets */
	private function getAssetPath(): string {
		return $this->getPublicPath() . 'assets' . DIRECTORY_SEPARATOR;
	}

	/** Register menu items, settings, and templates; then, subscribe to API events. */
	public function subscribeToEvents() {
		if ($this->subscribed) {
			return;
		}
		$this->addGlobalSettings();
		// Only add the rest of the event listeners and configuration if module setup and configuration is complete
		if ($this->globalsConfig->isConfigured()) {
			$this->registerMenuItems();
			$this->registerTemplateEvents();
			$this->subscribeToApiEvents();
		}
		$this->subscribed = true;
	}

	/** @return GlobalConfig */
	public function getGlobalConfig() {
		return $this->globalsConfig;
	}

	/** Add the module global settings */
	public function addGlobalSettings() {
		$this->eventDispatcher->addListener(GlobalsInitializedEvent::EVENT_HANDLE, [$this, 'addGlobalSettingsSection']);
	}

	/** Inject the settings section for this module */
	public function addGlobalSettingsSection(GlobalsInitializedEvent $event) {
		global $GLOBALS;
		$service = $event->getGlobalsService();
		$service->createSection($this->mod_name);
		$settings = $this->globalsConfig->OptSections;
		foreach ($settings as $key => $config) {
			$value = $GLOBALS[$key] ?? $config['default'];
			$service->appendToSection(
				$this->mod_name,
				$key,
				new GlobalSetting(
					xlt($config['title']),
					$config['type'],
					$value,
					xlt($config['description']),
					true
				)
			);
		}
	}

	/** Tie into any events dealing with the templates/page rendering of the system here */
	public function registerTemplateEvents() {
		if ($this->globalsConfig->isConfigured()) {
			$this->eventDispatcher->addListener(RenderEvent::EVENT_BODY_RENDER_POST, [$this, 'renderMainBodyScripts']);
		}
	}

	/** Add javascript files for the module to the main tabs page of the system */
	public function renderMainBodyScripts(RenderEvent $event) {
		?><script src="<?php echo $this->getAssetPath();?>js/veradigm.js" type="text/javascript"></script><script type="text/javascript">window.onload = veradigm_attach_listeners();</script><script type="text/javascript">window.onload = veradigm_attach_patient_listeners();</script><?php
	}

	/** Register menu items */
	private function registerMenuItems() {
		if ($this->getGlobalConfig()->isConfigured()) {
			$this->eventDispatcher->addListener(MenuEvent::MENU_RESTRICT, [$this, 'addCustomModuleMenuItem']);
			$this->eventDispatcher->addListener(PatientMenuEvent::MENU_RESTRICT, [$this, 'addCustomPatientMenuItem']);
		}
	}

	/** Add menu items */
	public function addCustomModuleMenuItem(MenuEvent $event) {
		if (!$this->getGlobalConfig()->isConfigured()) {
			return null;
		}
		return $this->insertMenu($event, $this->veradigmMenu());
	}

	/** Add patient menu items */
	public function addCustomPatientMenuItem(PatientMenuEvent $event) {
		if (!$this->getGlobalConfig()->isConfigured()) {
			return null;
		}
		return $this->insertMenu($event, $this->veradigmPatientMenu());
	}

	/** Insert a new menu item into the existing menu */
	private function insertMenu($event, $menuItem) {
		$menu = $event->getMenu();
		foreach ($menu as $item) {  // Inject the menu entry into the modules menu
			if ($item->menu_id === 'modimg') {
				$item->children[] = $menuItem;
				break;
			}
		}
		$event->setMenu($menu);
		return $event;
	}

	/** Create the Veradigm menu */
	private function veradigmMenu() {
		$submenus = [];
		// Submenu: Veradigm Logs
		$subItem = new \stdClass();
		$subItem->acl_req = ['admin', 'users'];
		$subItem->children = [];
		$subItem->global_req = self::CONFIGURED_ARRAY;
		$subItem->label = xlt('Veradigm Logs');
		$subItem->menu_id = 'mod_veradigm_logs';
		$subItem->requirement = 0;
		$subItem->target = 'mod';
		$subItem->url = self::MOD_INSTALLATION_PATH . 'src/logview.php';
		$submenus[] = $subItem;
		// Submenu: Reset Patient GUID
		$subItem = new \stdClass();
		$subItem->acl_req = ['admin', 'users'];
		$subItem->children = [];
		$subItem->global_req = self::CONFIGURED_ARRAY;
		$subItem->label = xlt('Reset Patient GUID');
		$subItem->menu_id = 'mod_veradigm_reset_patient_guid';
		$subItem->requirement = 0;
		$subItem->target = 'mod';
		$subItem->url = self::MOD_INSTALLATION_PATH . 'src/patient_guid.php';
		$submenus[] = $subItem;
		// Submenu: Utility Mode
		$subItem = new \stdClass();
		$subItem->acl_req = ['admin', 'users'];
		$subItem->children = [];
		$subItem->global_req = self::CONFIGURED_ARRAY;
		$subItem->label = xlt('Utility Mode');
		$subItem->menu_id = 'mod_veradigm_utility';
		$subItem->requirement = 0;
		$subItem->target = 'mod';
		$subItem->url = '';
		$submenus[] = $subItem;
		// Top Veradigm Menu Item
		$primaryItem = new \stdClass();
		$primaryItem->children = $submenus;
		$primaryItem->label = xlt('Veradigm');
		$primaryItem->menu_id = 'mod_veradigm';
		$primaryItem->requirement = 0;
		$primaryItem->target = 'mod';
		$primaryItem->url = '';
		/** Restrict this menu to logged in users who can access+write patient medical records */
		$primaryItem->acl_req = ['users', 'med'];
		/** This menu flag takes a boolean property defined in the $GLOBALS array that OpenEMR populates. It allows a menu item to display if the property is true, and be hidden if the property is false. */
		$primaryItem->global_req = self::CONFIGURED_ARRAY;
		return $primaryItem;
	}

	/** Create the Veradigm menu */
	private function veradigmPatientMenu() {
		$submenus = [];
		// Submenu: Patient Context Mode
		$subItem = new \stdClass();
		$subItem->acl_req = ['users', 'med'];
		$subItem->children = [];
		$subItem->global_req = self::CONFIGURED_ARRAY;
		$subItem->label = xlt('Patient Context Mode');
		$subItem->menu_id = 'mod_veradigm_patient_context';
		$subItem->requirement = 0;
		$subItem->target = 'mod';
		$subItem->url = '';
		$submenus[] = $subItem;
		// Submenu: Patient Lockdown Mode
		$subItem = new \stdClass();
		$subItem->acl_req = ['users', 'med'];
		$subItem->children = [];
		$subItem->global_req = self::CONFIGURED_ARRAY;
		$subItem->label = xlt('Patient Lockdown Mode');
		$subItem->menu_id = 'mod_veradigm_patient_lockdown';
		$subItem->requirement = 0;
		$subItem->target = 'mod';
		$subItem->url = '';
		$submenus[] = $subItem;
		// Submenu: Standard SSO
		$subItem = new \stdClass();
		$subItem->acl_req = ['users', 'med'];
		$subItem->children = [];
		$subItem->global_req = self::CONFIGURED_ARRAY;
		$subItem->label = xlt('Standard SSO');
		$subItem->menu_id = 'mod_veradigm_standard_sso';
		$subItem->requirement = 0;
		$subItem->target = 'mod';
		$subItem->url = '';
		$submenus[] = $subItem;
		// Submenu: Task Mode
		$subItem = new \stdClass();
		$subItem->acl_req = ['users', 'med'];
		$subItem->children = [];
		$subItem->global_req = self::CONFIGURED_ARRAY;
		$subItem->label = xlt('Task Mode');
		$subItem->menu_id = 'mod_veradigm_task';
		$subItem->requirement = 0;
		$subItem->target = 'mod';
		$subItem->url = '';
		$submenus[] = $subItem;
		// Top Veradigm Menu Item
		$primaryItem = new \stdClass();
		$primaryItem->acl_req = ['users', 'med'];
		$primaryItem->children = $submenus;
		$primaryItem->global_req = self::CONFIGURED_ARRAY;
		$primaryItem->label = xlt('Veradigm');
		$primaryItem->menu_id = 'mod_veradigm_patient';
		$primaryItem->requirement = 0;
		$primaryItem->target = 'mod';
		$primaryItem->url = '';
		return $primaryItem;
	}

	/* API METHODS */

	/** Register listeners for API events */
	private function subscribeToApiEvents() {
		if ($GLOBALS['rest_api'] && $this->globalsConfig->isConfigured()) {
			$this->eventDispatcher->addListener(RestApiCreateEvent::EVENT_HANDLE, [$this, 'addVeradigmApi']);
			$this->eventDispatcher->addListener(RestApiScopeEvent::EVENT_TYPE_GET_SUPPORTED_SCOPES, [$this, 'addApiScope']);
		}
	}

	/** Register API endpoints */
	private function addVeradigmApi(RestApiCreateEvent $event) {
		$apiController = new Veradigm_RestController();
		/** To see the route definitions, see _rest_routes.inc.php in the root of OpenEMR */
		$event->addToRouteMap('GET /api/veradigm/mode/:mode', [$apiController, 'enter_veradigm_interface']);
		$event->addToRouteMap('GET /api/veradigm/hasbuttons', [$apiController, 'has_veradigm_buttons']);
		$event->addToRouteMap('POST /api/veradigm/facility/:fid', [$apiController, 'send_facility']);
		$event->addToRouteMap('POST /api/veradigm/user/:uid', [$apiController, 'send_user']);
		$event->addToRouteMap('POST /api/veradigm/patient/:pid', [$apiController, 'sync_patient']);
		return $event;
	}

	/** Adds the webhook API scopes to the oauth2 scope validation events for the standard API. This allows the webhook to be fired. */
	private function addApiScope(RestApiScopeEvent $event): RestApiScopeEvent {
		if ($event->getApiType() == RestApiScopeEvent::API_TYPE_STANDARD) {
			$scopes = $event->getScopes();
			$scopes[] = 'user/veradigm.read';
			$scopes[] = 'user/veradigm.write';
			if (\RestConfig::areSystemScopesEnabled()) {
				$scopes[] = 'system/veradigm.read';
				$scopes[] = 'system/veradigm.write';
			}
			$event->setScopes($scopes);
		}
		return $event;
	}
}
