# Wayfair plugin: Getting API Credentials

In order for the Wayfair plugin to connect to Wayfair's systems, you need to provide your unique API credentials.

To receive the API credentials for you organization, follow these steps:

## 1. Send an Email to Wayfair

Send an email to ERPSupport@wayfair.com to request assistance:

- Subject : Access to Plentymarkets plugin / "Name of your company" (SuID)
- Body:
    - Contact information
    - Descriptions of your organizations' plans and needs
    - Any important dates regarding Plentymarkets >> Wayfair integrations.

You will promptly receive a response containing these details:
- Confirmation of access to
- Supplier ID(s)

## 2. Generate Application Credentials

1. Log in to your Partner Home account at partners.wayfair.com

2. Find the `Developer` menu. It may be on the top banner of the page. Otherwise, it is accessible in the `More` menu in the banner.

3. Activate the `Developer` menu, and click on `Application`. You should be redirected to a new page.

4. On the `Application Management` page, click the `+ New Application` button at the bottom.

5. Provide a `Name` and `Description` for the new Application

6. Use the slider switch at the bottom of dialog to set it to `Production`, unless otherwise instructed by Wayfair.

7. Click `Save` on the dialog, which will display the application's credentials - `Client ID` and `Client Secret`.

8. Copy the `Client ID` and `Client Secret` to a secure location.
    * **The `Client Secret` cannot be retrieved after this point and a new one must be generated if the original is lost.**

    * These credentials will be used for [authorizing the Wayfair plugin](initial_setup.md#1-authorizing-the-wayfair-plugin-to-access-wayfair-interfaces) for use of Wayfair's systems.

9. Close the credentials dialog to protect the information.

10. Review the [supplementary information on credentials](tips_and_tricks.md#protecting-your-credentials).
