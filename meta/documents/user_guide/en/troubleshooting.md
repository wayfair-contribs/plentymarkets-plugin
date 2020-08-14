# Wayfair Plugin: Troubleshooting

## Plentymarkets logs
The Wayfair plugin produces information in the Plentymarkets logs, which contains critical information for investigating and resolving issues.

### Viewing the logs
To view the Plentymarkets logs, from the main Plentymarkets page, go to `Data` >> `Log`.

![log menu entry](../../../images/en/troubleshooting/menu_data_log.png)

### Setting the log level for Wayfair
The default settings for the logs do not show all messages from the Wayfair plugin. To get more details in the logs, change the level of logging for Wayfair to `Debug`:

1. Open the Plentymarkets `Log` page if it is not already open.

2. Click on the `Configure Logs` gear-shaped button ![gear button](../../../images/common/button_gear.png).

3. Click on `Wayfair` in the list on the left.
    ![wayfair in list](../../../images/en/troubleshooting/wayfair_log_category.png)

4. Check the `Active` box

5. Set the `Log level` to  `Debug`

6. Ensure the settings look like this:

    ![wayfair set to debug](../../../images/en/troubleshooting/wayfair_logs_active_debug.png)

7. Click the `save` button at the bottom of the form to save the settings. **Notice:** There is no indication that the settings have saved.

### Filtering the logs to show only Wayfair logs

1. Open the Plentymarkets `Log` page if it is not already open.

2. In the `Integration` field on the left side of the log viewer, enter "Wayfair"

    ![wayfair in filter](../../../images/en/troubleshooting/filter_logs_wayfair.png)

3. Click on the magnifying glass button ![search button](../../../images/common/button_search.png).
