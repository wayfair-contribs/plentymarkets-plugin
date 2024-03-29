import { Injectable } from '@angular/core';
import { Http } from "@angular/http";
import { TerraBaseService, TerraLoadingSpinnerService } from "@plentymarkets/terra-components";
import { UrlHelper } from '../../helpers/url-helper';
import { Observable } from 'rxjs';
import { WarehouseInterface } from './data/warehouse.interface';

@Injectable()
export class WarehouseService extends TerraBaseService {
    
    constructor(loadingBarService:TerraLoadingSpinnerService, http: Http) {
        super(loadingBarService, http, UrlHelper.getWayfairUrl(UrlHelper.URL_WAYFAIR_WAREHOUSES))
    }

    fetch(): Observable<Array<WarehouseInterface>>
    {
        this.setAuthorization()
        return this.mapRequest(
            this.http.get(this.url)
        )
    }
}