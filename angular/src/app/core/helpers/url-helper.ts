export class UrlHelper {

    static baseDomainUrl: string = '/wayfair'

    static URL_WAYFAIR_WAREHOUSE_SUPPLIER = 'warehouseSupplier';
    static URL_WAYFAIR_WAREHOUSES = 'warehouses'
    static URL_WAYFAIR_SETTINGS= 'stockBuffer'
    static URL_WAYFAIR_FULL_INVENTORY= 'fullInventory'
    static URL_WAYFAIR_CARRIER_SCACS = 'carrierScacs'
    static URL_WAYFAIR_CARRIERS = 'carriers'
    static URL_WAYFAIR_SHIPPING_METHOD = 'shippingMethod'
    static URL_WAYFAIR_REST_AUTH = 'resetAuth'

    static urls = {
        [UrlHelper.URL_WAYFAIR_WAREHOUSE_SUPPLIER]: '/warehouseSupplier',
        [UrlHelper.URL_WAYFAIR_WAREHOUSES]: '/warehouses',
        [UrlHelper.URL_WAYFAIR_SETTINGS]: '/settings',
        [UrlHelper.URL_WAYFAIR_FULL_INVENTORY]: '/fullInventory',
        [UrlHelper.URL_WAYFAIR_CARRIER_SCACS]: '/carrierScacs',
        [UrlHelper.URL_WAYFAIR_CARRIERS] : '/carriers',
        [UrlHelper.URL_WAYFAIR_SHIPPING_METHOD]: '/shippingMethod',
        [UrlHelper.URL_WAYFAIR_REST_AUTH]: '/resetAuth'
    };

    static getBaseDomainUrl() {
        //return window.location.origin + '/json'
        return this.baseDomainUrl
    }

    static getWayfairUrl(key: string) {
        return this.getBaseDomainUrl() + this.urls[key]
    }

    static getPMUrl(key: string) {
        return '/rest' + this.urls[key];
    }
}
