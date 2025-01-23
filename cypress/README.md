# Cypress tests

## Setup
To run Cypress browser tests against a local mediawiki install, set these environment variables. Depending on your local
setup, these might be different.
```bash
export MW_SERVER=http://default.mediawiki.mwdd.localhost:8080/
export MW_SCRIPT_PATH=w/
export MEDIAWIKI_USER=an_admin_username
export MEDIAWIKI_PASSWORD=the_password_for_that_user
```

In running the tests in headless mode requires executing a maintenance script.
If that works for your local setup in a non-standard way (i.e., not using `php` and `maintenance/run.php`),
then you can configure your custom setup with further variables:
```bash
export MW_MAINTENANCE_COMMAND=mw
export MW_MAINTENANCE_ARGS='docker mediawiki mwscript --'
```

## Run the tests
### Headless like in CI
Use this command to run the tests in a terminal:
```bash
npm run cypress:run
```

### With a GUI
When running with a GUI, you need to include the config-overrides at the end of your LocalSettings.php file.
For example by appending the following, though you may need to adjust the path for your local setup:

```php
require "$IP/extensions/GrowthExperiments/cypress/support/setupFixtures/GrowthExperiments.LocalSettings.php";
```

Next, prepare your local system by executing the `cypress/support/PrepareBrowserTestss.php` maintenance script.
This will import some pages and recommendations into the system.

Then open Cypress's GUI with this command:
```bash
npm run cypress:open
```
Some tests complete suggestions and edit pages, so it might be necessary to run the above script again before running
those test again.

Remove the configuration overrides again after you're done running the browser tests.
