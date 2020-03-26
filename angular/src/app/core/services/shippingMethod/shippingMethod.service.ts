import {Injectable} from '@angular/core';
import {Http} from "@angular/http";
import {TerraBaseService, TerraLoadingSpinnerService} from "@plentymarkets/terra-components";
import {UrlHelper} from '../../helpers/url-helper';
import {Observable} from 'rxjs';
import {ShippingMethodInterface} from "./data/shippingMethod.interface";

@Injectable()
export class ShippingMethodService extends TerraBaseService {
    constructor(loadingBarService: TerraLoadingSpinnerService, http: Http) {
        super(loadingBarService, http, UrlHelper.getWayfairUrl(UrlHelper.URL_WAYFAIR_SHIPPING_METHOD))
    }

    fetch(): Observable<ShippingMethodInterface> {
        this.setAuthorization()
        return this.mapRequest(this.http.get(this.url))
    }

    post(data): Observable<ShippingMethodInterface> {
        this.setAuthorization()
        return this.mapRequest(this.http.post(this.url, {data: data}))
    }
}
