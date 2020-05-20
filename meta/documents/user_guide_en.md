# test change
# Wayfair plugin user guide
<div class="container-toc"></div>

## 1. Registering with Wayfair
Wayfair is a closed marketplace. In order to use this plugin, you have to be a registered supplier with Wayfair. 

Please send us an email to ERPSupport@wayfair.com

**Notice (March 2020):** This is a new email address for Wayfair. Please update the email you have on file.

After you have successfully registered as a supplier on Wayfair, you will have to go through the standard instructions seen below.

The installation of the Wayfair plugin allows for the following automatic processes to take place:

- periodic order import
- periodic stock synchronization


## 2. Retrieving your credentials

In order to connect Wayfair to Plentymarkets, you need to enter API credentials. To receive them, you must first send an email to ERPSupport@wayfair.com and provide the following information:

- Subject : Access to Plentymarkets plugin / "Name of your company" (SuID)
- Information contact
- Which functionalities you plan to use.

You will shortly receive an email back, containing a confirmation that you have been granted access to the API tools, as well as your **Supplier ID(s)**. Next, you need to head over to your Extranet account at partners.wayfair.com. In the banner, you should see a tab named "Developer". If you don’t see it in the banner, then hover over the "More" tab, and it should appear in the dropdown menu.

Hover over the "Developer" tab, and click on "Application". You should be redirected to a new page. On this new page, click on the button named " + New Application", and give a name and description to your application

Click on "Save", then you should be shown a ClientID and Client Secret, which are the credentials you will need to use for the Wayfair plugin. Save these somewhere secure, especially the Client Secret, as you will only be able to see it once.

## 3. Installing the Wayfair plugin in Plentymarkets

1. Login to the Plentymarkets system

2. Go to **plugins >> plentyMarketplace**, which will use the system owner’s credentials to log you in on the Marketplace

3. Click the search button  in the top-right corner of the page

4. Enter “Wayfair” into the box and press enter

5. If “Already purchased” appears on the page, skip to step 8

6. Click the **go to checkout** button 
7. Complete the purchasing form
    1. Accept terms and conditions
    2. Click **order now**

8. Return to the Plentymarkets system, and go to **Plugins >> Plugin Overview**.

9. Find/Create a Plugin Set where the plugin is to be installed - the proper plugin set will be marked as **Linked to** your "shop."

10. Un-check the **installed** checkbox

11. Check the **plentyMarketplace** checkbox

12. Type “Wayfair” into the **name** field

13. Click **search** to find the appropriate plugin - only one should appear, with the marketplace icon in the Source column.

14. Click the **install** button in the Wayfair row

15. Complete the pop-up modal
    1. (Optional) in the drop-down, choose a different version to install - you probably should be using the newest one.

    2. Click **install**

16. Refresh the Plugin Overview page

17. Click on the name of the plugin set where the plugin was installed

18. Click the **Activate** button in the Wayfair plugin row of the plugin set

19. Click the save icon  above the list, and wait for the progress bar to complete. A timestamp will display at successful completion.

20. If an error symbol appears, check the Plentymarkets logs for plugin build and deployment failures


### Authentication

After the plugin is installed in your Plentymarkets system, carry out the authentication to enable access to the Wayfair interfaces.

#### Activating the access to the Wayfair interfaces

1. Go to **Plugins >> Plugin Overview**

2. Select the plugin set that contains the Wayfair plugin

3. Click on the "Wayfair " name in the plugin set details

4. Go to **Configuration >> Global Settings**

5. Enter the Client ID and Client Secret you received when creating the application in Extranet into the fields **Client ID** and **Client Secret**.

6. Change **Mode** to **Live**.
7. **Save** the settings.

## 4. Activating the order referrer

An order referrer indicates the sales channel on which an order was generated. You have to activate the Wayfair order referrer in order to link items, properties etc. with Wayfair.

### Activating the order referrer for Wayfair:

1. Go to **System >> Orders >> Order referrer**.

2. Place a check mark next to the **Wayfair** order referrer.

3. Click on **Save**.

## 5. Making an item available for Wayfair.

Items that you want to sell on the Wayfair website have to be active and available for Wayfair. These settings are carried out in the **Item >> Edit item >> Open item>> Tab: Variation ID**. Please keep in mind that you have to carry out these settings for all the items that you wish to sell on Wayfair.

### Setting the item availability for Wayfair:

1. Go to **Item >> Edit Item** then click on the **Item ID** of the item that you wish to make available for Wayfair.

2. You will be redirected to the **Settings** tab of the chosen item. In this tab, there is a section called **Availability**. Place a check mark next to the option **Active** in this section.

3. Click on the tab **Availability**.

4. Click in the selection field in the **Markets** section. A list with all available markets is displayed.

5. Place a check mark next to the option **Wayfair**.

6. Click on **Add**. The market is now added.

7. Click on **Save**. The item is now available for Wayfair.

### 6. Matching an incoming order with your items in Plentymarkets:
In order to match an item in Wayfair's database, which is defined by the Supplier Part Number you have provided to Wayfair, with your items in Plentymarkets, you need to configure which Plentymarkets field the Wayfair Supplier Part Number should match with.

To do so, follow the instructions below:

1. Go to **Setup >> System >> Markets >> Wayfair >> Home**.

2. Once the homepage of the plugin has loaded, click on **Settings**.

3. Under **Item Mapping Method**, choose Plentymarkets field from which the Supplier Part Number you provided to Wayfair comes from. You can choose from these three fields:
           **a.** *Variation No.*
           **b.**  *EAN*
           **c.** *Marketplace-specific SKU*: choose this option only if none of the two Plentymarkets fields above match with the Supplier Part Number you provided to Wayfair. If you choose this option, please follow the instructions below (Matching an incoming order using the marketplace-specific SKU).

4. Click **Save**.

#### Matching an incoming order using the marketplace-specific SKU.

1. Go to **Item >> Edit Item** and click on the **Item ID** of the item that you wish to match with incoming Wayfair orders.

2. You will be redirected to the **Settings** tab of the chosen item. Click on the tab **Availability**.

3. In this tab, there are four different sections. The one that we want is the **SKU** section.

4. Click on the **Add** button (the with cross in grey background)

5. A new window will be opened. In the first field(**Referrer**), choose the **Wayfair** order referrer from the dropdown menu.

6. In the third field (**SKU**), enter the **Supplier Part Number**.
7. Click on **Add**.

8. Click on **Save**.

9. Repeat the same process for all items that you are selling on Wayfair.

## Setting up the order fulfillment.

A working order fulfillment with the Wayfair plugin requires to set up **Shipping Profile Mappings** and **Event Procedures**. Carry out the following instructions:

### Create a new shipping service provider

1. Go to **System >> Orders >> Shipping >> Settings >> Tab: Shipping service provider**

2. Click on **+ New** in order to create a new **shipping service provider**.

3. Enter **Wayfair Shipping** in the fields **Name (de)** and **Name (backend)**.

4. Click in the field **Shipping service provider** and choose **WayfairShipping**.

5. Click on **Save**.

### Create a new shipping profile

1. Go to **System >> Orders >> Shipping >> Settings >> Tab: Shipping Profiles**

2. Click on **+ New** to create a new shipping profile.

3. You will see a table in which you have to enter data.

4. In the first row, click on the dropdown menu, and choose the *Shipping service provider** you have just created (should be **WayfairShipping** if you followed our instructions)

5. In the second and third row, enter a name (we recommend **WayfairShipping** for simplicity). You should also choose a language in the second row, third column.

6. In the fourth row, choose the flag number 6 or 126 (which represent the Wayfair colors).

7. In the fifth column, choose **priority n1** (the two stars).

8. In the seventeenth column (**Order referrer**), place a check mark next to **Wayfair** (if there are more than one, choose all).

9. Scroll to the top of the page, and click on the **save** button. All the other rows and their respective data entries can be left empty.

### Creating a new event procedure


1. Go to **System >> Orders >> Events**

2. Click on **Add event procedure** (the "+" button on the left of the page).

3. Enter the name **Wayfair order Shipping Mapping**.

4. Select the event **New order** from the dropdown menu.

5. Click on the **save** button.

6. You should automatically be redirected to the newly created **event procedure**. In the **settings** section of the event procedure, place a check mark next to **Active**.

7. Click on **Add filter**, and go to **Order >> Referrer** to add the referrer as a filter.

8. In the **Filter** section, a box should appear with a list of all available **Order referrers**. Place a check mark next to all **Wayfair** order referrers.

9. Click on **Add procedure**, and go to **Order >> Change shipping profile**. Click on **+ add**.

10. In the **Procedures** section, click on the **expand** button next to **change shipping profile** (left-most arrow).

11. Choose the **shipping profile** you created before (should be **WayfairShipping** if you followed our instructions).

12. Click on the **save** button.

## 7. Configuring the warehouse mapping.

In order to update the inventory data in Wayfair's system, you need to map the warehouse ID's in your Plentymarkets system to the warehouse ID in Wayfair's system.

1. Go to **System >> Markets >> Wayfair >> Home >> Tab: Warehouses**

2. Click on **Add mapping**

3. In the field **Warehouse** select the warehouse you want to map.

4. In the field **Supplier ID**, enter your corresponding Wayfair Supplier ID.

5. Click on **Save**

## 8. Send Confirmation of Delivery (ASN) to Wayfair

### Update Settings for carrier if shipping on own account

![Select shipping method](https://i.ibb.co/5L6pxpk/asn-01.png "Select shipping method")

1.	Select **Systems >> Markets >> Wayfair >> Home**

2.	Then select **Ship Confirmation (ASN)** and select either **Wayfair Shipping** or **Own account Shipping** depending on how your logistics setup is with Wayfair (If you are not sure, reach out to your Wayfair contact).

3.	Next, enter then **SCAC codes** provided by Wayfair during the onboarding interview for each of the carriers you ship Wayfair orders with.

### Create Event and bind to WayFair plugin for Sending Shipment Notification

1. Click **Setup** in the top navigation bar then go to **Orders >> Events**
![Create Event](https://i.ibb.co/NjDtY05/asn-02.png "Create Event")

2.	Click on **Add event procedure** (the "+" button on the left of the page)

3.	Enter any **Name**

4.	Select **Status change** (in the category **Order Change**) in the **Event** field.

5.	In the field below **Event** select the status change that should initiate the sending of an ASN to Wayfair, such as **in preparation for shipping**.

6.	Click the **Save** button.

7.	You should automatically be redirected to the newly created **event procedure**. In the **settings** section of the event procedure, place a checkmark next to **Active**.

8.  Click on **Add Filter**, and go to **Order >> Referrer** to add the referrer as a filter (see screenshot below)
![Event Referrer](https://i.ibb.co/TwKLvJ5/asn-03.png "Event Referrer")

9.	In the **Filter** section, a box should appear with a list of all available Order referrers. Place a checkmark next to all **Wayfair** order referrers.
![Wayfair Referrer](https://i.ibb.co/yYpLp8q/asn-04.png "Wayfair Referrer")

10. Click on **Add procedure**, and go to **Plugins >> Send Ship Confirmation (ASN) to Wayfair**. Click on **+ add**.
![Add Procedure](https://i.ibb.co/xfGrhFP/asn-05.png "Add Procedure")

The final settings result should look similar to this:
![Add Procedure Result](https://i.ibb.co/GJPF3ZV/asn-06.png "Add Procedure Result")

## Tips and tricks

### Protecting your credentials
The credentials that your Plentymarkets system uses for communication with Wayfair should not be shared with anyone. You should take care to avoid doing these things:

* Forwarding Wayfair emails
* Sharing screenshots of the Global Settings for the Wayfair plugin
* Exporting the configuration of a Plugin Set that contains the Wayfair plugin
