import { Injectable } from '@angular/core';
import { Http } from "@angular/http";
import { TerraBaseService, TerraLoadingSpinnerService } from "@plentymarkets/terra-components";
import { UrlHelper } from '../../helpers/url-helper';
import { Observable } from 'rxjs';
import { OrderStatusInterface } from './data/orderStatus.interface';

@Injectable()
export class OrderStatusService extends TerraBaseService {

    constructor(loadingBarService:TerraLoadingSpinnerService, http: Http) {
        super(loadingBarService, http, UrlHelper.getWayfairUrl(UrlHelper.URL_WAYFAIR_WAREHOUSES))
    }

    fetch(): Observable<Array<OrderStatusInterface>>
    {
        this.setAuthorization()
        return this.mapRequest(
            this.http.get(this.url)
        )
    }
}
