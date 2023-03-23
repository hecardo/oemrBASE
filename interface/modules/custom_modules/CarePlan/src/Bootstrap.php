<?php
/**
 * @package   	WMT
 * @subpackage	CarePlan
 * @author    	Ron Criswell <ron.criswell@medtechsvcs.com>
 * @copyright 	Copyright (c)2023 Medical Technilogy Services <https://medtechsvcs.com/>
 * @license   	https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace WMT\CarePlan;

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
class Bootstrap 
{
	const PLAN_INSTALL_PATH = '/interface/modules/custom_modules/CarePlan/';
	const PLAN_PROGRAM_PATH = '/interface/modules/custom_modules/CarePlan/src/';
	const MODULE_MENU_NAME = "Care Plan";
	const MODULE_NAME = "careplan";
	
	/** @var EventDispatcherInterface The object responsible for sending and subscribing to events through the OpenEMR system */
	private $eventDispatcher;

	/** @var SystemLogger */
	private $logger;

	/** @var Boolean Used to prevent multiple subscriptions */
	private $subscribed;
	
	/** Primary class constuction process */
	public function __construct(EventDispatcherInterface $eventDispatcher, ?Kernel $kernel = null) 
	{
		global $GLOBALS;
	
		if (empty($kernel)) {
			$kernel = new Kernel();
		}
		
		$this->eventDispatcher = $eventDispatcher;
		$this->logger = new SystemLogger();
		$this->subscribeToEvents();
	}

	/** Register menu items, settings, and templates; then, subscribe to API events. */
	public function subscribeToEvents() {
		if ($this->subscribed) {
			return;
		}
		
		$this->registerMenuItems();
//		$this->registerTemplateEvents();
//		$this->subscribeToApiEvents();
		
		$this->subscribed = true;
	}
	
	/** Tie into any events dealing with the templates/page rendering of the system here 
	public function registerTemplateEvents() {
		if ($this->globalsConfig->isConfigured()) {
			$this->eventDispatcher->addListener(RenderEvent::EVENT_BODY_RENDER_POST, [$this, 'renderMainBodyScripts']);
		}
	}
	*/
	
	/** Add javascript files for the module to the main tabs page of the system 
	public function renderMainBodyScripts(RenderEvent $event) {
		?><script src="<?php echo $this->getAssetPath();?>js/veradigm.js" type="text/javascript"></script><script type="text/javascript">window.onload = veradigm_attach_listeners();</script><script type="text/javascript">window.onload = veradigm_attach_patient_listeners();</script><?php
	}
	*/
	
	/** Register menu items */
	private function registerMenuItems() {
		$this->eventDispatcher->addListener(MenuEvent::MENU_RESTRICT, [$this, 'updateProceduresMenu']);
		$this->eventDispatcher->addListener(PatientMenuEvent::MENU_RESTRICT, [$this, 'updatePatientMenu']);
	}

	/** Update menu items in primary menu */
	public function updateProceduresMenu(MenuEvent $event) {
		$menu = $event->getMenu();
		foreach ($menu as $item) {
			if ($item->menu_id === 'proimg') {
				$new_submenu = array();
				foreach ($item->children as $subitem) {
					if (in_array($subitem->menu_id, ['orr1', 'lda1', 'dld0', 'orp1', 'orb0'])) {
						continue;
					}
					if ($subitem->menu_id === 'ort0') {
						$subitem->label = "Compendium";
					}
					if ($subitem->menu_id === 'ore0') {
						$subitem->label = "Procedure Processing";
						$subitem->url = self::PLAN_PROGRAM_PATH . 'Custom/custom_list_reports.php';
					}
					if ($subitem->menu_id === 'orl0') {
						$subitem->url = self::PLAN_PROGRAM_PATH . 'Custom/custom_provider_list.php';
					}
					if ($subitem->menu_id === 'orc0') {
						$subitem->url = self::PLAN_PROGRAM_PATH . 'Custom/custom_load_compendium.php';
					}
					$new_submenu[] = $subitem;
				}
				// Add laboratory processing item
				$subItem = new \stdClass();
				$subItem->children = [];
				$subItem->acl_req = ['patients', 'lab'];
				$subItem->label = xlt('Laboratory Processing');
				$subItem->url = self::PLAN_PROGRAM_PATH . 'Reports/lab_batch.php';
				$subItem->requirement = 0;
				$subItem->target = 'rep';
				$subItem->global_req = '';
				$new_submenu[] = $subItem;
				
				$item->children = $new_submenu;
			}
			if ($item->menu_id === 'repimg') {
				$new_submenu = array();
				foreach ($item->children as $submenu) {
					if ($submenu->label === 'Procedures') {
						// insert new submenus
						$new_submenu[] = self::addProcedureMenu();
						$new_submenu[] = self::addLaboratoryMenu();
					} else {
						$new_submenu[] = $submenu;
					}
				}
				$item->children = $new_submenu;
			}
		}
		$event->setMenu($menu);
		return $event;
	}

	/** Create the procedure report menu */
	private function addProcedureMenu() {
		$submenu = new \stdClass();
		$submenu->label = "Procedures";
		$submenu->icon = "fa-caret-right";
		$submenu->requirement = 0;
		$submenu->children = [];
		
		// Submenu: Pending Results
		$subItem = new \stdClass();
		$subItem->children = [];
		$subItem->acl_req = ['patients', 'lab'];
		$subItem->label = xlt('Pending Results');
		$subItem->url = self::PLAN_PROGRAM_PATH . 'Custom/custom_pending_orders.php';
		$subItem->requirement = 0;
		$subItem->target = 'rep';
		$subItem->global_req = '';
		$submenu->children[] = $subItem;
		
		// Submenu: Pending Results
		$subItem = new \stdClass();
		$subItem->children = [];
		$subItem->acl_req = ['patients', 'lab'];
		$subItem->label = xlt('Procedure Results');
		$subItem->url = self::PLAN_PROGRAM_PATH . 'Custom/custom_orders_results.php';
		$subItem->requirement = 1;
		$subItem->target = 'rep';
		$subItem->global_req = '';
		$submenu->children[] = $subItem;
		
		// Submenu: Follow Up Pending
		$subItem = new \stdClass();
		$subItem->children = [];
		$subItem->acl_req = ['patients', 'lab'];
		$subItem->label = xlt('Follow Up Pending');
		$subItem->url = self::PLAN_PROGRAM_PATH . 'Custom/custom_pending_followup.php';
		$subItem->requirement = 0;
		$subItem->target = 'rep';
		$subItem->global_req = '';
		$submenu->children[] = $subItem;
		
		return $submenu;
	}

	/** Create the procedure report menu */
	private function addLaboratoryMenu() {
		$submenu = new \stdClass();
		$submenu->label = "Laboratory";
		$submenu->icon = "fa-caret-right";
		$submenu->requirement = 0;
		$submenu->children = [];
		
		// Submenu: Lab Orders
		$subItem = new \stdClass();
		$subItem->children = [];
		$subItem->acl_req = ['patients', 'lab'];
		$subItem->label = xlt('Laboratory Orders');
		$subItem->url = self::PLAN_PROGRAM_PATH . 'Reports/lab_orders.php';
		$subItem->requirement = 0;
		$subItem->target = 'rep';
		$subItem->global_req = '';
		$submenu->children[] = $subItem;
		
		// Submenu: Lab Orphans
		$subItem = new \stdClass();
		$subItem->children = [];
		$subItem->acl_req = ['patients', 'lab'];
		$subItem->label = xlt('Orphan Results');
		$subItem->url = self::PLAN_PROGRAM_PATH . 'Reports/lab_orphans.php';
		$subItem->requirement = 0;
		$subItem->target = 'rep';
		$subItem->global_req = '';
		$submenu->children[] = $subItem;
		
		// Submenu: Lab Results
		$subItem = new \stdClass();
		$subItem->children = [];
		$subItem->acl_req = ['patients', 'lab'];
		$subItem->label = xlt('Laboratory Results');
		$subItem->url = self::PLAN_PROGRAM_PATH . 'Reports/lab_results.php';
		$subItem->requirement = 0;
		$subItem->target = 'rep';
		$subItem->global_req = '';
		$submenu->children[] = $subItem;
		
		// Submenu: Lab Analysis
		$subItem = new \stdClass();
		$subItem->children = [];
		$subItem->acl_req = ['patients', 'lab'];
		$subItem->label = xlt('Patient Analysis');
		$subItem->url = self::PLAN_PROGRAM_PATH . 'Reports/lab_analysis.php';
		$subItem->requirement = 1;
		$subItem->target = 'rep';
		$subItem->global_req = '';
		$submenu->children[] = $subItem;
		
		return $submenu;
	}
		
	/** Update patient menu */
	public function updatePatientMenu(PatientMenuEvent $event) {
		$new_menu = array();
		$menu = $event->getMenu();
		foreach ($menu as $item) {
			if ($item->menu_id === 'transactions') {
				$new_menu[] = self::addCarePlanMenu();
			}
			$new_menu[] = $item;
		}
		
		$menu[] = self::addCarePlanMenu();
		$event->setMenu($new_menu);
		return $event;
	}
	
	/** Create the Care Plan menu */
	private function addCarePlanMenu() {
		// Submenu: Patient Context Mode
		$subItem = new \stdClass();
		$subItem->acl_req = ['users', 'med'];
		$subItem->pid = TRUE;
		$subItem->children = [];
		$subItem->global_req = '';
		$subItem->label = xlt('Planning');
		$subItem->menu_id = 'care_plan';
		$subItem->on_click = '';
		$subItem->requirement = 0;
		$subItem->target = 'main';
		$subItem->url = self::PLAN_PROGRAM_PATH . 'Planning/list_plans.php?pid=';

		return $subItem;
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
