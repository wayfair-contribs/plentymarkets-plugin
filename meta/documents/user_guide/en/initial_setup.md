# Wayfair plugin: Initial Setup

## Prerequisites
* [Wayfair API credentials](obtaining_credentials.md).

* Administrative rights on the Plentymarkets system

* [Installation](plugin_installation.md) of the Wayfair plugin - [view release notes](https://github.com/wayfair-contribs/plentymarkets-plugin/releases)


## 1. Authorizing the Wayfair Plugin to access Wayfair interfaces
After the plugin is installed in your Plentymarkets system, the plugin must be configured to use the correct credentials when connecting to Wayfair's interfaces:

1. From the main Plentymarkets page, go to `Plugins` >> `Plugin set overview`

2. Locate the Plugin Set that is linked to the client with which Wayfair will be used.

3. Click on the `Edit` button for the desired Plugin set

4. In the Wayfair row of the Plugin set, click on the `Settings` button.

4. In the left-side menu, go to `Configuration` >> `Global Settings`.

5. In the `Supplier Settings` area, enter the `Client ID` and `Client Secret` values that correspond with your Wayfair API credentials

6. Change the `Mode` setting to `Live` - see [information on `Test` mode](test_mode.md)

7. Click the `Save` button in the toolbar above the settings

## 2. Activating the order referrer
An order referrer in Plentymarkets denotes the sales channel on which an order was generated. To get the Plentymarkets system to properly import orders from the Wayfair API, the Wayfair order referrer must be activated:

1. From the main Plentymarkets page, go to `Setup` >> `Orders` >> `Order referrer`.

2. Place a check mark next to the **Wayfair** order referrer.

3. Click on **Save**.

## 3. Creating a new shipping profile for shipping through Wayfair
**TODO: when and why**

**TODO: what comes with the plugin**

**TODO: what needs to be manually created - ???different for "Wayfair shipping" VS "Own account" shipping???**


1. Go to **System >> Orders >> Shipping >> Settings >> Tab: Shipping Profiles**

2. Click on **+ New** to create a new shipping profile.

3. You will see a table in which you have to enter data.

4. In the first row, click on the dropdown menu, and choose the *Shipping service provider** you have just created (should be **WayfairShipping** if you followed our instructions)

5. In the second and third row, enter a name (we recommend **WayfairShipping** for simplicity). You should also choose a language in the second row, third column.

6. In the fourth row, choose the flag number 6 or 126 (which represent the Wayfair colors).

7. In the fifth column, choose **priority n1** (the two stars).

8. In the seventeenth column (**Order referrer**), place a check mark next to **Wayfair** (if there are more than one, choose all).

9. Scroll to the top of the page, and click on the **save** button. All the other rows and their respective data entries can be left empty.

## 4. Automatically selecting the Wayfair Shipping Profile for Wayfair orders

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

## 5. Matching items ordered on Wayfair with Item Variations in Plentymarkets:
In order to properly handle incoming orders from Wayfair, the Wayfair plugin must match the Supplier Part Numbers in Wayfair's systems with a specific field of Item Variations in Plentymarkets. By default, the Wayfair plugin operates on the assumption that the `Variation Number` **(not to be confused with the Variation's ID)** of an Item's Variation in Plentymarkets will match the Wayfair Supplier Part Number.

If the Wayfair Supplier Part Numbers for your organization are to be reflected in an alternative field in your Plentymarkets Item Variations, change the value of [the `Item Mapping Method` setting](settings_guide.md#item-mapping-method) and update the Variations accordingly.

## 6. Making items available for sale on Wayfair
Items that you want to sell on the Wayfair market must be considered active in Plentymarkets. The Plentymarkets user may also choose limit which Items are for sale on Wayfair. **Note that Inventory and ordered items are controlled at the `Variation` level.**

To ensure that an Item is available for sale, follow these instructions:

1. From the main Plentymarkets page, go to `Item` >> `Edit item`

2. Search for item(s) and open them

3. **For each item**, click `Variations` and open them

4. **For each Variation**:

    1. On the `Settings` tab, make sure that the `Active` checkbox in the `Availability Section`is checked.

    2. If [the `Send all inventory items to Wayfair?` setting](settings_guide.md#send-all-inventory-items-to-wayfair) is **disabled**, go to the `Availability` tab of the Variation and add "Wayfair" to the list in the `Markets` area.

    3. Click the `Save` button next to the Variation `ID` (not the higher-up button for the Item).


## 7. Configuring the Warehouse mappings to match Wayfair Supplier IDs.

In order to update the inventory data in Wayfair's system, you need to map the Warehouses in your Plentymarkets system to the Supplier IDs in Wayfair's system, on the [Warehouses](settings_guide.md#warehouses-page) page of the plugin's settings.

## 8. Configuring Plentymarkets to send Confirmation of Delivery (ASN) to Wayfair

### 8.1 Setting the Wayfair Plugin to send the correct shipping information to Wayfair
Wayfair Plugin users that wish to ship orders by using their own accounts (rather than using Wayfair's shipping services) must update the [Ship Confirmation (ASN) configuration settings](https://github.com/wayfair-contribs/plentymarkets-plugin/blob/master/meta/documents/user_guide/en/settings_guide.md#ship-confirmation-asn-page) to reflect their specific configuration.

If Wayfair's shipping services are to be used, the Wayfair plugin's ASN settings should be left in their default (`Wayfair Shipping`) state.

### 8.2 Creating an Event for Plentymarkets Orders that sends shipment information to Wayfair

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

## 9. Performing the first Inventory Synchronization
Once everything has been set up, the it is time to start listing items for sale on Wayfair.

Finalize the setup by initiating a full inventory synchronization on the [Full Inventory](settings_guide.md#full-inventory-page) page of the [Wayfair Market Settings](settings_guide.md)
