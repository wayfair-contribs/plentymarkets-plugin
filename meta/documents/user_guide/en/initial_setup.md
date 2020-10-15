# Wayfair plugin: Initial Setup

## Prerequisites

* [A Plentymarkets system](https://www.plentymarkets.co.uk).

* Administrative rights on the Plentymarkets system where the Wayfair plugin will be used
    - The Plentymarkets user's `Access` setting must be `Admin`
    - The Plentymarkets user must be able to modify Plugin Sets

* Active Wayfair supplier status
    * A Wayfair Supplier ID is required
    * [Information for prospective suppliers](https://partners.wayfair.com/d/onboarding/sell-on-wayfair)

* [Wayfair API credentials](obtaining_credentials.md).

* [Installation of the Wayfair plugin](plugin_installation.md).


## 1. Authorizing the Wayfair Plugin to access Wayfair interfaces
After the plugin is installed in your Plentymarkets Plugin Set, the plugin must be configured to use the correct credentials when connecting to Wayfair's interfaces.

* **The authorization procedure must be performed for any Plugin Set that contains the Wayfair plugin**.

* Copying a Plugin Set will copy the authorization information to the new plugin set.

* An exported or imported Plugin Set may include the authorization information.

The authorization steps are as follows:
1. From the main Plentymarkets page, go to `Plugins` >> `Plugin set overview`:

    ![plugins_menu_plugin_set_overview](../../../images/en/plugins_menu_plugin_set_overview.png)

2. Locate the Plugin Set that is linked to the client with which Wayfair will be used:

    ![linked clients](../../../images/en/plugin_sets_linked_clients.png)

3. Click on the desired Plugin set.

4. In the Wayfair row of the Plugin set, click on the `Settings` button ![gear button](../../../images/common/button_gear.png).

4. In the left-side menu, go to `Configuration` >> `Global Settings`:

    ![global settings in menu](../../../images/en/menu_global_settings.png)

5. In the `Supplier Settings` area, enter the `Client ID` and `Client Secret` values that correspond with your Wayfair API credentials.

6. Change the `Mode` setting to `Live` - see [information on `Test` mode](test_mode.md):

    ![global settings live](../../../images/en/global_settings_live.png)

7. Click the `Save` button ![save button](../../../images/common/button_save.png) in the toolbar above the settings

## 2. Activating the order referrer
An order referrer in Plentymarkets identifies the sales channel on which an order was generated. To get the Plentymarkets system to properly import orders from the Wayfair API, the Wayfair order referrer must be activated:

1. From the main Plentymarkets page, go to `Setup` >> `Orders` >> `Order referrer`:

    ![order referrer menu](../../../images/en/menu_order_referrer.png)

2. Place a check mark next to the `Wayfair` order referrer:

    ![wayfair order referrer](../../../images/common/wayfair_referrer_checked.png)

3. Click the `Save` button ![save button](../../../images/common/button_save.png).

## 3. Setting up Plentymarkets for shipping through Wayfair
To ensure proper integrations with Wayfair when shipping order items, follow the procedures outlined in  [the Wayfair Shipping instructions](wayfair_shipping.md).

## 4. Matching items ordered on Wayfair with Item Variations in Plentymarkets:
In order to properly handle incoming orders from Wayfair, the Wayfair plugin must match the Supplier Part Numbers in Wayfair's systems with a specific field of Item Variations in Plentymarkets. By default, the Wayfair plugin operates on the assumption that the `Variation Number` **(not to be confused with the Variation's ID)** of an Item's Variation in Plentymarkets will match the Wayfair Supplier Part Number.

![variation number field](../../../images/en/variation_number_field.png)

If the Wayfair Supplier Part Numbers for your organization are to be reflected in an alternative field in your Plentymarkets Item Variations, change the value of the  [`Item Mapping Method`](settings_guide.md#item-mapping-method) setting and update the Variations accordingly.

## 5. Making items available for sale on Wayfair
Items that you want to sell on the Wayfair market must be considered active in Plentymarkets. The Plentymarkets user may also choose to limit which Items are for sale on Wayfair. **Note that Inventory and ordered items are controlled at the `Variation` level.**

This procedure is required only if [the `Send all inventory items to Wayfair` setting](settings_guide.md#send-all-inventory-items-to-wayfair) is **disabled.**:

1. From the main Plentymarkets page, go to `Item` >> `Edit item`

2. Search for item(s) and open them
    **Note:** Verify item variation  says **`main variation`** see image below

    ![item main variation](../../../images/en/item_main_variation.png)


     **`For each Item`**

3. On the `Settings` tab, make sure that the `Active` checkbox in the `Availability` Section is checked:

    ![variation active](../../../images/en/variation_active_field.png)

4. If the [`Send all inventory items to Wayfair?`](settings_guide.md#send-all-inventory-items-to-wayfair) setting is **disabled**, go to the `Availability` tab of the Item and add "Wayfair" to the list in the `Markets` area:

    1. Click in the area that says `Click to select markets`.

    2. Scroll down to the `Wayfair` entry

    3. Place a check mark next to `Wayfair`

    4. Click the `+` button ![add button](../../../images/common/button_plus.png) in the `Markets` area.

        ![adding wayfair market to variation](../../../images/en/variation_wayfair_market.png)

    5. Observe that a `Wayfair` row now exists in `Markets`

        ![adding wayfair market to variation](../../../images/en/variation_wayfair_market_added.png)


5. Click the `Save` left of the `Variation ID`, below the `Global` tab (not the higher-up button for the Item):

    ![variation saving](../../../images/common/variation_save.png)

**Note:** All variations of an item will inherit from the  main variation, unless **`Inheritance is deactivated`**
    ![inheritance status](../../../images/en/alternate_inheritance.png)

## 6. Configuring the Warehouse mappings to match Wayfair Supplier IDs.

In order to update the inventory data in Wayfair's system, you need to map the Warehouses in your Plentymarkets system to the Supplier IDs in Wayfair's system, on the [Warehouses](settings_guide.md#warehouses-page) page of [the plugin's settings](settings_guide.md).

## 7. Configuring Plentymarkets to send Confirmation of Delivery (ASN) to Wayfair

### 7.1 Setting the Wayfair Plugin to send the correct shipping information to Wayfair
Wayfair Plugin users that wish to ship orders by using their own accounts (rather than using Wayfair's shipping services) must update the [Ship Confirmation (ASN) configuration settings](settings_guide.md#ship-confirmation-asn-page) to reflect their specific configuration.

If Wayfair's shipping services are to be used, the Wayfair plugin's ASN settings should be left in their default (`Wayfair Shipping`) state.

### 7.2 Creating an Event for Plentymarkets Orders that sends shipment information to Wayfair

1. From the main Plentymarkets page, go to `Setup` >> `Orders` >> `Events`:

    ![order events](../../../images/en/menu_order_events.png)

2.	Click on `Add event procedure` (the `+` button on the bottom left-hand side of the page):

    ![add order event](../../../images/en/add_order_event.png)

3.	Enter any `Name` in the appropriate field.

4.	In the `Event` drop down, select `Status change` (in the category `Order Change`):

    ![choose event](../../../images/en/shipping/choose_event.png)

5.	In the field below `Event` select the status change that should initiate the sending of an ASN to Wayfair, such as `In preparation for shipping`:

    ![choose status](../../../images/en/shipping/choose_status.png)

6.	Click the `Save` button ![save button](../../../images/common/button_save.png).

7.	You should automatically be redirected to the newly created event procedure. In the `Settings` section of the event procedure, place a checkmark next to `Active`:


    ![event active](../../../images/en/shipping/event_active.png)

8. Click on the `+` symbol next to `Filter`:

    ![add filter](../../../images/en/shipping/add_filter.png)

9.  Choose `Referrer` in the `Order` category:

    ![choose filter](../../../images/en/shipping/choose_filter.png)

10. Click the `Add` button ![plus add button](../../../images/en/button_plus_add.png).

11.	In the `Filter` section, a box should appear with a list of all available Order referrers. Place a checkmark next to all "Wayfair" order referrers:

    ![wayfair selected](../../../images/en/shipping/filter_wayfair_selected.png)

12. Click on the `+` next to `Procedures`:

    ![add procedure](../../../images/en/shipping/add_procedure.png)

13. Choose `Send Ship Confirmation (ASN) to Wayfair` in the `Plugins` category:

    ![choose procedure](../../../images/en/shipping/choose_procedure.png)

14. Click the `Add` button ![plus add button](../../../images/en/button_plus_add.png).

15. Verify that the Event's settings finally look similar to this:

    ![finished event](../../../images/en/shipping/finished_event.png)

16. Click the `Save` button ![save button](../../../images/common/button_save.png).

## 8. First inventory synchronization
Once everything has been set up, then it is time for the Wayfair plugin to start listing items for sale on Wayfair.

### Version 1.1.5 and later

As of version 1.1.4, the Wayfair plugin's Inventory Synchronization process is fully automated and cannot be started by the Plentymarkets user. After completing all earlier steps in this guide, it is important to ensure that the Wayfair plugin is able to perform Inventory Synchronization:

1. Navigate to [the `Inventory` page](settings_guide.md#inventory-page) in the [Wayfair Market Settings](settings_guide.md) **-- If the `Inventory` page does not exist, do not continue - you must follow [the steps outlined for early versions of the plugin](#version-1.1.3-and-earlier)**.

2. The page may initially display errors, if inventory synchronizations have already been attempted. Wait a few minutes while the system initializes.

3. Review the information displayed on the page.

4. If issues are reported for the full inventory synchronization, attempt to resolve them by reviewing [the instructions for the `Inventory` page](settings_guide.md#inventory-page), viewing [the Plentymarkets logs](troubleshooting.md#plentymarkets-logs) and using [the troubleshooting guide](troubleshooting.md).


### Version 1.1.3 and earlier

Older versions **(before 1.1.4)** of the Wayfair plugin are not able to automatically perform the first inventory synchronization, and require manual intervention:

1. Locate the `Full Inventory` page in the [Wayfair Market Settings](settings_guide.md). **-- If the `Full Inventory` page does not exist, do not continue - view [the information for modern versions of the plugin](#version-1.1.4-and-later)**.

2. Click the `Start Synchronization` button.

3. Await the results.

4. If the results indicate issues, attempt to resolve them by [viewing the Plentymarkets logs](troubleshooting.md#plentymarkets-logs) and using [the troubleshooting guide](troubleshooting.md).


### Subsequent synchronizations

**After the first inventory synchronization, the Wayfair plugin will periodically send inventory updates to Wayfair, without manual activation.**
