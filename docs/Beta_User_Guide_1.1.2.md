# Plentymarkets Beta Program Guide for v1.1.2

#### Prerequisites for beta testing Wayfair's plentymarkets plugin:

- Supplier is onboarded for API usage by Wayfair.
- Supplier is currently a plentymarkets user.
- Supplier has a github.com account.

### Follow these steps to download and test with a beta version of Wayfair&#39;s plentymarkets plugin:

1. Capture the current settings for the Wayfair plugin

    1.1. Supplier client settings
    - Login to [Plentymarkets](https://plentymarkets-cloud-de.com).
    - Go to **Plugins > Plugin Overview** to view plugin set(s)
    - click on the name of the plugin set currently in use (will be marked as &quot;Linked to&quot; the shop(s)).
    - Click the **Plugin Set Settings** button ![] ![picture alt](http://via.placeholder.com/20x20 "Title is optional")
    - Click the **Export Settings** button ![] to save a copy of the current settings for the plugins in use

    1.2. Plugin configuration (Just in case. Following the process should retain this information for you.)
    - Go to **Setup > Markets > Wayfair > Home**
    - Make note of the values for the fields in each page of settings
      1. Warehouses
      2. Settings
      3. Ship Confirmation (ASN)

2. Create an account with github.com if you don't already have one
    
    2.1. Github is where Wayfair hosts our plugin code and maintains the different plugin versions as we develop and deploy them. In order to get beta code you need to have an account.

    2.2. Visit [https://github.com/join](https://github.com/join) to create a free account. Wayfair recommends making an account for your business instead of using an employee's personal account.


3. Create and/or fetch your github.com API token.

    3.1. Sign into github and navigate to [https://github.com/settings/tokens](https://github.com/settings/tokens).
 
    3.2. If you don't already have an API token, click the "generate new token" button. [Here are steps to create an access token](https://help.github.com/en/github/authenticating-to-github/creating-a-personal-access-token-for-the-command-line). No access to your personal information is required, so you do not need to check any of the boxes.


4. Add a new git repository in your plentymarkets system
  
    4.1. Go to **Plugins > Git** to add a new repository, click on + (Add) button and enter the following information:
    - Repository URL: Wayfair's plentymarkets repo URL - [https://github.com/wayfair-contribs/plentymarkets-plugin](https://github.com/wayfair-contribs/plentymarkets-plugin)
    - Username: Your github username created in step 2
    - Token: access token created in step 3

5. Add the beta plugin to a plugin set
  
    5.1. Go to **Plugin > Plugin Overview** to view plugin set(s)
  
    5.2. Observe which plugin set is currently &quot;Linked to&quot; the shop on which wayfair will be used - in a basic setup, this is named &quot;Standard Shop.&quot;

    5.3. Create a new plugin set in order to easily revert back to the current plugin set that is using official Wayfair releases, and prevent accidental loss of settings.
    - Click the add ![picture alt](http://via.placeholder.com/20x20 "Title is optional")button at the top left above the plugin set(s) list
    - Enter a unique name for the new plugin set. We recommend &quot;Wayfair Beta&quot;
    - In the "copy plugin set" field, enter the name of the current plugin set from step 5.2
    - Verify that the screen looks like this: ![picture alt](http://via.placeholder.com/150x150 "Title is optional")
    - Click the save icon ![picture alt](http://via.placeholder.com/20x20 "Title is optional")

    5.4. Click on the name of the new plugin set. The list of plugins will match that of the plugin set that is currently in use. All of the plugin's settings have also been copied to this new plugin set.

    5.5. Remove the Plentymarkets Marketplace version of Wayfair from the new plugin set by clicking the remove button ![picture alt](http://via.placeholder.com/20x20 "Title is optional") to allow installation of the Wayfair plugin from git
   
    5.6. Set the plugin filters to "Git"; and "Not Installed"![picture alt](http://via.placeholder.com/150x150 "Title is optional")
  
    5.7. Click the search button ![picture alt](http://via.placeholder.com/20x20 "Title is optional")
  
    5.8. Locate the Wayfair result in the list
  
    5.9. Confirm that the Source is Git ![picture alt](http://via.placeholder.com/20x20 "Title is optional")
  
    5.10. Click install ![picture alt](http://via.placeholder.com/20x20 "Title is optional")
    
    5.11. There will be a prompt for choosing the desired **branch** of the plugin
    - Select the branch **release\_1\_1\_2\_beta** (This list is **NOT** alphabetically ordered).
    - Click "install"
    - Click the save icon ![picture alt](http://via.placeholder.com/20x20 "Title is optional") above the list, and wait for the progress bar to complete - a timestamp will display at completion

6. Import the Wayfair configuration (client ID, etc) that was used in the older Wayfair version
  
    6.1. Go to **Plugins > Plugin Overview**
    
    6.2. Click on the name of the new plugin set that has the beta Wayfair plugin

    6.3. Click the **Plugin Set Settings** button ![picture alt](http://via.placeholder.com/20x20 "Title is optional")
  
    6.4. In the **Import configuration** area, click the **Select file** button ![picture alt](http://via.placeholder.com/20x20 "Title is optional")
  
    6.5. Select the file from step 1
  
    6.6. Click the **Import** button ![picture alt](http://via.placeholder.com/20x20 "Title is optional")

    6.7. A confirmation will appear, suggesting that the plugin set page needs to be reloaded.

7. Activate the new Wayfair plugin

    7.1. Go to **Plugins > Plugin Overview**
  
    7.2. Click the Activate button ![picture alt](http://via.placeholder.com/20x20 "Title is optional")to the left of "Wayfair"


    7.3. Click the save icon ![picture alt](http://via.placeholder.com/20x20 "Title is optional") above the plugin set list, and wait for the progress bar to complete - a timestamp will display at completion


8. Switch to using the plugin set that contains the beta plugin
  
    8.1. Go to **Plugins > Plugin Overview** to view plugin set(s)
 
    8.2. Click the Link Plugin Sets button ![picture alt](http://via.placeholder.com/20x20 "Title is optional")
  
    8.3. In the Plugin Set field, choose the new plugin set that uses the beta version of the plugin ![picture alt](http://via.placeholder.com/750x150 "Title is optional")
    
    8.4. Click save
  
    8.5. Log out of plentymarkets, and log back in, to ensure that the latest changes are loaded.

9. Confirm wayfair plugin configuration
 
    9.1. Go to **Setup > Markets > Wayfair > Home**
  
    9.2. Ensure the settings on each page match those of the previous installation
    - Warehouses
    - Settings
    - Ship Confirmation (ASN)

10. Configure logging settings

    10.1. Go to **Data > Logs**
    
    10.2. Click the settings button ![picture alt](http://via.placeholder.com/20x20 "Title is optional")

    10.3. Click on &quot;Wayfair&quot; in the list, so that the Settings for Wayfair are loaded
  
    10.4. Enable the **active** checkbox
  
    10.5. Set the **Duration** to the expected length of the beta test period
 
    10.6. Set the **Log Level** to **Debug**
 
    10.7. Verify that the settings resemble this image:![picture alt](http://via.placeholder.com/650x150 "Title is optional")
 
    10.8. Click **Save**
    
    10.9. Exit the logging settings by clicking the X at the top right


11. Validating plugin behaviors
  
    11.1. **After some usage of the Wayfair plugin,** go to **Data > Logs**
    
    11.2. Check for any new system errors
  
    11.3. Type "Wayfair" in the **Integration** field on the left side  and click on it

    11.4. Click the search button ![picture alt](http://via.placeholder.com/20x20 "Title is optional") to see only the Wayfair log entries

    11.5. Report any issues to Wayfair - important issue details are displayed by clicking on an individual row in the logs.


12. How to report issues
    12.1. Go to the plugin's issues site at [https://github.com/wayfair-contribs/plentymarkets-plugin/issues?q=is%3Aissue](https://github.com/wayfair-contribs/plentymarkets-plugin/issues?q=is%3Aissue)

    12.2. Clear make sure that the &quot;is:open&quot; filter is NOT enabled.
  
    12.3. Review the current set of issues to see if yours has already been reported. If so, please provide your information and insights on that existing issue rather than creating a new one.
  
    12.4. Click "New Issue" to author a new issue
  
    12.5. Provide the following details:

    - Supplier name
    - Contact information for someone in your organization who is able to discuss the issue with representatives from Wayfair
    - Version of plugin

      1. Go to **Plugins > Plugin Overview**
      2. In the plugin set list, find the set that is currently linked to your shop, and click on it
      3. The version is listed in the "Active Version" column in the Wayfair plugin's row.

    - Date and time of occurence
    - Summary of what went wrong
    - What were you doing when the issue occurred? Did you click any buttons?
    - What is present in the logs? (See step 10 for instructions)
    
    12.6. Monitor the email address for the github account, for notifications about the issue.

##### 13. How to ask questions

    13.1. You may contact us at [ERPSupport@wayfair.com] for support with installing and using the Wayfair plugin.


# To revert back to the official version of the Wayfair plugin, follow these steps:

1. Go to **Plugins > Plugin Overview** to view plugin set(s)

2. Click the Link Plugin Sets button ![picture alt](http://via.placeholder.com/20x20 "Title is optional")

3. In the Plugin Set field, choose the plugin set that was in use before switching to Beta ![]![picture alt](http://via.placeholder.com/600x150 "Title is optional")

4. Click Save

5. Log out of plentymarkets then log back in, to ensure the correct plugin set is loaded.


# To update the Beta version of the Wayfair plugin:

1. Go to **Plugins > Plugin Overview** to view plugin set(s)

2. Click on the name of the plugin set that contains the Beta Wayfair plugin

3. Click on the name "Wayfair" in the list. A blank page will open, with a menu on the left.

4. Click "Git" in the menu.

5. Click "Pull".

6. Go back to **Plugins > Plugin Overview**

7. Click on the name of the plugin set that contains the Beta Wayfair plugin

8. Click the save icon ![picture alt](http://via.placeholder.com/20x20 "Title is optional") above the list, and wait for the progress bar to complete - a timestamp will display at completion. Though the "version" of the Wayfair plugin may not update, even after reloading the Plugin Overview page, the latest changes to the git branch have now been applied.

