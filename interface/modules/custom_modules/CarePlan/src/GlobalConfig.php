<?php

declare(strict_types=1);


namespace WMT\Laboratory;

use OpenEMR\Common\Crypto\CryptoGen;
use OpenEMR\Services\Globals\GlobalSetting;
use OpenEMR\Services\Globals\GlobalsService;


/** Global administration settings */
class GlobalConfig {

	const GLOBAL_SECTION_NAME = 'WMT-Laboratory';

	const CONFIG_ENABLE_VERADIGM = 'mod_veradigm_enable';
	const CONFIG_ENABLE_GLOBAL_LICENSE = 'mod_veradigm_enable_global_license';
	const CONFIG_ENABLE_TEST_MODE = 'mod_veradigm_test_mode';
	const CONFIG_ENABLE_SAVE_CCR = 'mod_veradigm_save_ccr';
	const CONFIG_ENABLE_EXTRA_LOGGING = 'mod_veradigm_extra_logging';
	const CONFIG_ENABLE_LOGGING = 'mod_veradigm_logging';
	const CONFIG_GLOBAL_SITE_LICENSE = 'mod_veradigm_global_site_license';
	const CONFIG_ACCOUNT_PASSWORD = 'mod_veradigm_account_password';
	const CONFIG_ISSUER_NAME = 'mod_veradigm_issuer_name';
	const CONFIG_PARTNER_USERNAME = 'mod_veradigm_partner_username';
	const CONFIG_ACCOUNT_PARTNER_ID = 'mod_veradigm_account_partner_id';
	const CONFIG_DEFAULT_FACILITY_TIMEZONE = 'mod_veradigm_default_facility_timezone';
	
	const CONFIGURED_ARRAY = [self::CONFIG_ACCOUNT_PARTNER_ID, self::CONFIG_ACCOUNT_PASSWORD, self::CONFIG_ENABLE_VERADIGM, self::CONFIG_ISSUER_NAME, self::CONFIG_PARTNER_USERNAME];
	
	private $globalsArray;
	public $OptSections = null;
	
	/** @var CryptoGen */
	private $cryptoGen;
	
	public function __construct(array &$globalsArray) {
		$this->globalsArray = $globalsArray;
		$this->cryptoGen = new CryptoGen();
		$this->OptSections = self::getGlobalSettingSectionConfiguration();
	}

	/** List the options */
	private function getOptSections() {
		if (empty($this->OptSections)) {
			$this->OptSections = self::getGlobalSettingSectionConfiguration();
		}
		return $this->OptSections;
	}

	/** Returns true if all of the settings have been configured. Otherwise, it returns false. */
	public function isConfigured(): bool {
		$keys = self::CONFIGURED_ARRAY;
		foreach ($keys as $key) {
			$value = $this->getGlobalSetting($key);
			if (empty($value)) {
				return false;
			}
		}
		$key_file = $this->getGlobalSetting('key_file');
		$crt_file = $this->getGlobalSetting('crt_file');
		if (empty($key_file) || empty($crt_file) || (!file_exists($key_file) || !file_exists($crt_file))) {
			return false;
		}
		return true;
	}

	/** Returns true if test-mode is active. */
	public function isTestMode(): bool {
		return $this->getGlobalSetting(self::CONFIG_ENABLE_TEST_MODE);
	}

	/** Returns true if the CCR must be saved. */
	public function saveCCREnabled(): bool {
		return $this->getGlobalSetting(self::CONFIG_ENABLE_SAVE_CCR);
	}

	/** Returns true if logging is enabled. */
	public function loggingEnabled(): bool {
		return $this->getGlobalSetting(self::CONFIG_ENABLE_LOGGING);
	}

	/** Returns true if extra logging is enabled. */
	public function extraLoggingEnabled(): bool {
		return $this->getGlobalSetting(self::CONFIG_ENABLE_LOGGING) && $this->getGlobalSetting(self::CONFIG_ENABLE_EXTRA_LOGGING);
	}

	/** Returns true if a global site license is used. */
	public function hasGlobalSiteLicense(): bool {
		return $this->getGlobalSetting(self::CONFIG_ENABLE_GLOBAL_LICENSE) && $this->getGlobalSetting(self::CONFIG_GLOBAL_SITE_LICENSE);
	}

	public function getDefaultFacilityTimezone(): string {
		return $this->getGlobalSetting(self::CONFIG_DEFAULT_FACILITY_TIMEZONE);
	}

	public function getGlobalSiteLicense(): string {
		return $this->getGlobalSetting(self::CONFIG_GLOBAL_SITE_LICENSE);
	}

	public function getPartnerUsername(): string {
		return $this->getGlobalSetting(self::CONFIG_PARTNER_USERNAME);
	}

	public function getPartnerID(): string {
		return $this->getGlobalSetting(self::CONFIG_ACCOUNT_PARTNER_ID);
	}

	public function getPartnerPswd(): string {
		return $this->getGlobalSetting(self::CONFIG_ACCOUNT_PASSWORD);
	}

	public function getIssuerName(): string {
		return $this->getGlobalSetting(self::CONFIG_ISSUER_NAME);
	}

	/**
	 * Returns the decrypted value of the account password. Otherwise, return false if the value could not be decrypted or is empty.
	 * @return bool|string
	 */
	public function getPswd() {
		$encryptedValue = $this->getGlobalSetting(self::CONFIG_ACCOUNT_PASSWORD);
		return $this->cryptoGen->decryptStandard($encryptedValue);
	}

	public function getGlobalSetting($settingKey) {
		return $this->globalsArray[$settingKey] ?? null;
	}

	/** Return the module's global section name */
	public function getGlobalSectionName(): string {
		return self::GLOBAL_SECTION_NAME;
	}

	/** New global settings provided by the module */
	public function getGlobalSettingSectionConfiguration(): array {
		$settings = [
			self::CONFIG_DEFAULT_FACILITY_TIMEZONE => [
				'default' => '',
				'description' => 'Default Patient Country sent to Veradigm eRx, only if patient country is not set',
				'title' => 'eRx Default Facility Timezone',
				'type' => ['' => '', 'AKDT' => 'US Alaska Daylight Time', 'AKST' => 'US Alaska Standard Time', 'CDT' => 'US Central Daylight Time', 'CST' => 'US Central Standard Time', 'EDT' => 'US Eastern Daylight Time', 'EST' => 'US Eastern Standard Time', 'HADT' => 'US Hawaii-Aleutian Daylight Time', 'HAST' => 'US Hawaii-Aleutian Standard Time', 'MDT' => 'US Mountain Daylight Time', 'MST' => 'US Mountain Standard Time', 'PDT' => 'US Pacific Daylight Time', 'PST' => 'US Pacific Standard Time']  // DATA_TYPES_WITH_OPTIONS
			],
			self::CONFIG_ENABLE_LOGGING => [
				'default' => '',
				'description' => 'Enable/Disable logging Veradigm eRx Requests and Responses (HIGHLY RECOMMENDED)',
				'title' => 'Enable Logging',
				'type' => GlobalSetting::DATA_TYPE_BOOL
			],
			self::CONFIG_ENABLE_EXTRA_LOGGING => [
				'default' => '',
				'description' => 'Enable/Disable extra logging Veradigm eRx Requests and Responses (USED FOR DEBUGGING)',
				'title' => 'Enable Extra Logging',
				'type' => GlobalSetting::DATA_TYPE_BOOL
			],
			self::CONFIG_ENABLE_VERADIGM => [
				'default' => '',
				'description' => 'Enable Veradigm eRx Service',
				'title' => 'Enable Veradigm eRx Service',
				'type' => GlobalSetting::DATA_TYPE_BOOL
			],
			self::CONFIG_ENABLE_GLOBAL_LICENSE => [
				'default' => '',
				'description' => 'Allow using the below site license for all sites',
				'title' => 'Enable Global Site License',
				'type' => GlobalSetting::DATA_TYPE_BOOL
			],
			self::CONFIG_ENABLE_TEST_MODE => [
				'default' => '',
				'description' => 'Put Veradigm on Test-mode (used for developers and debugging)',
				'title' => 'Enable Test-mode',
				'type' => GlobalSetting::DATA_TYPE_BOOL
			],
			self::CONFIG_ENABLE_SAVE_CCR => [
				'default' => '',
				'description' => 'Save Veradigm CCRs',
				'title' => 'Save CCRs',
				'type' => GlobalSetting::DATA_TYPE_BOOL
			],
			self::CONFIG_ACCOUNT_PASSWORD => [
				'default' => '',
				'description' => 'Account password issued for Veradigm eRx service',
				'title' => 'Veradigm Password',
				'type' => GlobalSetting::DATA_TYPE_ENCRYPTED
			],
			self::CONFIG_GLOBAL_SITE_LICENSE => [
				'default' => '',
				'description' => 'Facility GUID to use for all sites',
				'title' => 'Global Site License',
				'type' => GlobalSetting::DATA_TYPE_TEXT
			],
			self::CONFIG_ACCOUNT_PARTNER_ID => [
				'default' => '',
				'description' => 'Partner ID issued for Veradigm eRx service',
				'title' => 'eRx Partner ID',
				'type' => GlobalSetting::DATA_TYPE_TEXT
			],
			self::CONFIG_ISSUER_NAME => [
				'default' => '',
				'description' => 'Issuer name issued for Veradigm eRx service',
				'title' => 'eRx Issuer Name',
				'type' => GlobalSetting::DATA_TYPE_TEXT
			],
			self::CONFIG_PARTNER_USERNAME => [
				'default' => '',
				'description' => 'Partner Username issued for Veradigm eRx service',
				'title' => 'eRx Partner Username',
				'type' => GlobalSetting::DATA_TYPE_TEXT
			]
		];
		return $settings;
	}
}
