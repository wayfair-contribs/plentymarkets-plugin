# Wayfair plugin: Using the Test mode

## Introduction
The Wayfair plugin comes with a `Test` mode for use in evaluating its features without impacting live production data in Wayfair's systems. **The `Mode` setting, just like the API credentials, applies separately to each Plugin Set in Plentymarkets.**

**IMPORTANT: This setting does not change the way that Plentymarkets operates, meaning Order information may differ between Plentymarkets's systems and Wayfair's systems.**

## Enabling Test mode

1. If your organization does not have any **Wayfair API Sandbox** applications, create a new one. The procedure matches [the instructions provided for obtaining credentials](obtaining_credentials.md), except that the slider switch in the creation dialog should be left in the `Sandbox` position.  You may discard the credentials for the new Sandbox application. The Wayfair plugin must continue to use its Production credentials for proper functionality, even in `Test` mode.

2. In the [Global Settings for the Wayfair plugin](initial_setup.md#1-authorizing-the-wayfair-plugin-to-access-wayfair-interfaces) in the active Plugin Set, use the `Mode` setting selector to set it to `Test`

3. Save the Global Settings for the Wayfair plugin

4. Log out of Plentymarkets and Log back in, to ensure that the settings take effect.

## Technical details
* When using `Test` mode, an interaction with Wayfair that would normally change the state of Wayfair order data is requested with a `dryMode` flag, which instructs Wayfair's systems to avoid changing state while processing the request.

* **Though the `Test` mode does not currently use the Wayfair API Sandbox, changes to API Sandbox data may impact the behavior of the Wayfair plugin when it is in `Test` mode.**
