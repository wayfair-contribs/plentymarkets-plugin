![Wayfair Logo](https://assets.jibecdn.com/prod/wayfair/0.2.52/assets/logo.png)

# Wayfair PlentyMarkets plugin

This is the **Wayfair** plugin developed by Wayfair Inc. for use with PlentyMarkets Cloud ERP software.

The plugin allows for the following automatic processes to take place:
* Wayfair order imports
* Product stock synchronization
* Shipping label retrieval and printing


## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes. See deployment for notes on how to deploy the project on a live system.

### Prerequisites

This is a plugin for [PlentyMarkets 7](https://www.plentymarkets.com).

Make sure to have Git setup and a GitHub account prepared to interact with this repository. [See instructions](https://help.github.com/en/github/getting-started-with-github/set-up-git).

Clone the repository:
```
git clone git@github.com:wayfair/plentymarkets-plugin.git
```

#### Back-end
* [PHP 7.0](https://www.php.net/releases/7_0_0.php)
* [Composer](https://getcomposer.org/)

#### Front-end
* [Node](https://nodejs.org/en/docs/) >= v8.9.4, [npm](https://www.npmjs.com/)
* [Angular with PlentyMarkets](https://developers.plentymarkets.com/tutorials/angular-plugin)

### Installing

#### Back-end
Install using [Composer](https://getcomposer.org/) by running the following command in the repository directory:
```
composer install
```

For more basic information on package installation via Composer see this [introduction](https://getcomposer.org/doc/01-basic-usage.md).

#### Front-end

For code changes in the `./angular` directory to be reflected in the Wayfair plugin, one has to build the plugin and commit all the files generated in the `./ui` directory.

Once you are ready with the changes in `./angular` run the following commands:
 ```
 npm run reinstall
 npm run build
 ```

* The `npm run reinstall` command will make sure that all required packages are installed to the `./node_packages` folder.
  * The `./requirements.json` file defines what packages are required for building the Wayfair UI component
  * The `./requirements-lock.json` file is **auto-generated** It defines the specific versions of packages to be used in building.
    * Do NOT manually add information to this file
    * The information in this file may become stale, requiring careful manual removal of entries for specific libraries in order to pull new versions and complete the UI build.

* The `npm run build` command will delete the previous `./ui` folder and generate a new one with newly built UI files.
  * The new UI's code is impacted by the packages in the `node_packages` folder.
  * When pushing files in the `/.ui` folder, you MUST push `./requirements.json` and `./requirements-lock.json`.

Once all changes and issues have been taken care of, commit all the newly generated files and push.

To load the UI in PM, `ui.json` has been defined at the root. In that json config we have specified the menu on which our UI should be loaded i.e. "settings/markets/Wayfair"

## Deployment

#### Developers
The following steps are needed for releasing the PlentyMarkets plugin:
* Increase the version in the `plugin.json` file
* Create a patch file that contains all the changes introduced, upload this file to a safe place (currently using gofile.io and remove the file each time it is reviewed), then share it with PlentyMarket developers for review

  Note: when creating a patch, please consider ignoring changes coming from UI/JavaScript elements, see [this StackOverflow thread](https://stackoverflow.com/questions/4380945/exclude-a-directory-from-git-diff) for detailed steps.
* Pull the release branch on your PlentyMarkets account by going to: _Plugins > Plugin sets > Standard_,  look for Wayfair and, on the right side, click the Pull button
* Click on the Wayfair plugin, which would lead to: Plugins > Plugin sets > Filter > Standard > Wayfair > Global Settings, and then Push the `Upload to plentyMarketplace` button, waiting for a confirmation message
* When everything is ready for release, go to the Releases tab on GitHub and click the “Draft a new release” button to create a release zip file. We recommend you name it as your new version

#### Vendors

 Wayfair is a closed marketplace. In order to use this plugin, you have to be a registered supplier with Wayfair.

Please send an email to ERPSupport@wayfair.com for more information.
**Notice (March 2020):** This is a new email address for Wayfair. Please update the email you have on file.

After you have successfully registered as a supplier on Wayfair, you will have to go through the standard instructions found on the [plugin's landing page](https://marketplace.plentymarkets.com/en/plugins/integration/wayfair_6273) in the PlentyMarkets plugin marketplace.


## Testing & Code Style

Unfortunately, due to the difficult nature of developing within the PlentyMarkets ecosystem, it was not easy including automated tests with the plugin repository. Therefore, unit and integration testing needs to be done manually.

For QA, we’re currently carrying out the following process:
* After feature development is complete, the developer pulls their own branch into PlentyMarkets for testing. The developer must ask the team to avoid collisions, as PlentyMarkets won't allow two people to build and deploy at the same time
* After developers test their own work on the PlentyMarkets development account, he or she would then ask the plugin Product Owner to verify if the work is correct
* The Product Owner tests the fix or newly developed features to ensure everything is working correctly, according to our feature request expectation. The Product Owner then approves the release or explains if more changes are required



## Contributing

This project is not currently accepting external pull requests.

Please enquire with Wayfair ERP Support <ERPSupport [at] wayfair.com> with any questions.

## Built With

* [PlentyMarkets Plugin Interface](https://developers.plentymarkets.com/dev-doc/plugin-interface-introduction) - the plugin framework used for compatibility with the PlentyMarkets ERP.


## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [releases of this repository](https://github.com/wayfair/plentymarkets-plugin/releases).

## Authors

#### Supplier Data Integrations Engineering Team

#### Emerging Markets Engineering Team

#### Emerging Markets Product Team

## License

This project is licensed under the 2-Clause BSD License - see the [LICENSE.md](LICENSE.md) file for details

## Acknowledgments

* Wayfair End-to-End Operations
* PlentyMarkets Development teams
