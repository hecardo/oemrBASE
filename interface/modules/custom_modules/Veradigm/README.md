OpenEMR v7 Module Skeleton
--------------------------

Replace all instances of "XXX", "MODULE_NAME", "MODULE NAME", "MODULE_NAME_", "JS_NAME", "STYLESHEET_NAME", and any other placeholders with the appropriate data in all of the files (including file names).

All files under `./public` and `./templates` must be thoroughly reviewed and edited as needed as they are currently dummy examples. Remove any unused directories under `./public/assets/` including `./public/assets/` itself.

If `./templates/example.html.twig` is not used/needed, delete the file. If `./templates` is empty, then delete it.

- Edit the `composer.json` file as needed.
- The file `./src/GlobalConfig.php` is used to create the global settings provided by the module.
- The file `./src/Bootstrap.php` is used to provide the following
  - Add menu items
  - Subscribe to system events
  - Insert global settings
  - Adjust the OpenEMR API
  - Add routes
- `table.sql` is used to add tables to the database when the module is installed. This file must have that specific name so that the module installer can find the file.
- Use the below files as examples/templates:
  - `MODULE_NAME_DataStore.php` is the class that opens and accesses data whether that data be a local file, socket, remote API, database, etc.
  - `MODULE_NAME_RestController.php` is the Rest controller that will receive and respond to API requests
  - `MODULE_NAME_FHIRResource.php` is the object that represents a FHIR resource (such as a patient, encounter, provider, etc.) and stores the current state of the object
  - `MODULE_NAME_FHIRResourceService.php` provides methods for performing the processing and data manipulations for the object created in `MODULE_NAME_FHIRResource.php`


Installing the Module
=====================

Install the module by placing the module in the `<OPENEMR_INSTALLATION_DIR>/interface/modules/custom_modules/`.

Next, add a line for the module to the `psr-4` entry in OpenEMR's composer.json file. Below is an example.

```
"psr-4": {
    "OpenEMR\\": "src",
    "OpenEMR\\Modules\\Veradigm\\": "interface/modules/custom_modules/Veradigm/src"
}
```

Finally, run `composer dump-autoload` in the same directory as OpenEMR's composer.json file.


Activating the Module
=====================

Once the module is installed in OpenEMR `custom_modules` folder, activate the module in OpenEMR by doing the following

  1. Login to OpenEMR as the administrator
  2. Go to the menu and select Modules -> Manage Modules
  3. Click on the Unregistered tab in the modules list
  4. Find the module and click the *Register* button
  5. Click the *Install* button next to the module name
  6. Click the *Enable* button for the module
