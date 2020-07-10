# Wayfair Plugin: Wayfair Market Settings
The Wayfair plugin comes with a collection of settings for controlling the plugin's behavior.
These settings should only be configured after the authorization settings for the plugin have been configured in the active Plugin Set.

To locate the settings:
1. Log in to Plentymarkets as a user with administrative rights
2. Click `Setup` in the top navigation bar of Plentymarkets
3. Click `Markets` in the left navigation bar of the `Settings` interface
4. Click `Wayfair` in the list of `Markets`. It may appear at the bottom, rather than being alphabetically situated.
5. Click `Home` under `Wayfair`
6. You may now use the Wayfair navigation bar to choose a settings page such as [`Warehouses`](#warehouses-page).

## Warehouses page
The Warehouses page is used for associating the Warehouses that the supplier is using in Plentymarkets with the Wayfair Supplier IDs that have been issued to the supplier. The mappings are utilized by the Wayfair plugin when it reports inventory to Wayfair and also when it is processing Wayfair orders coming into Plentymarkets.

The topography of the Plentymarkets system may not match the amount of Wayfair Supplier IDs. It is acceptable to use a Warehouse Supplier ID for more than one of the Plentymarkets Warehouses. Beware the [Stock Buffer](#stock-buffer) setting.

### Adding a Warehouse mapping
1. Click on the `Add Mapping` button
2. Use the left column to choose a Plentymarkets Warehouse by name
3. Use the right column to enter a numeric Supplier ID
4. Click the `Save` button once the new row(s) have been completed

### Removing a Warehouse mapping
1. Locate the row to be removed
2. Click on the `delete` button ![delete icon](../../../images/icon_trash_can.png) in the row
3. Click the `Save` button once the desired row(s) have been removed


## Settings page
The Settings page contains general settings for the operation of the Wayfair plugin.

### Stock Buffer
The `Stock Buffer` setting is a non-negative integer that sets a reserved amount of stock for each Item Variation, for each Wayfair Supplier ID. The buffer amount is subtracted after all other stock calculations are made, including aggregating the stocks for multiple Plentymarkets Warehouses that have been assigned the same Wayfair Supplier ID.

To disable the `Stock Buffer`, leave this setting empty, or set it to `0`.

### Default Order Status
...

### Default Shipping Provider **(deprecated)**
The `Default Shipping Provider` setting is a legacy setting that no longer impacts the behavior of the plugin. **If this setting appears in your system, Wayfair strongly recommends that you upgrade your plugin to a newer version.**


### Item Mapping Method
...

### Import orders since
...

### Send all inventory items to Wayfair?
...
