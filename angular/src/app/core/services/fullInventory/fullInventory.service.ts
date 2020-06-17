import { Injectable } from '@angular/core';
import { Http } from "@angular/http";
import { TerraBaseService, TerraLoadingSpinnerService } from "@plentymarkets/terra-components";
import { UrlHelper } from '../../helpers/url-helper';
import { Observable } from 'rxjs';
import { FullInventoryInterface } from './data/fullInventory.interface';
@Injectable()
export class FullInventoryService extends TerraBaseService {

    constructor(loadingBarService:TerraLoadingSpinnerService, http: Http) {
        super(loadingBarService, http, UrlHelper.getWayfairUrl(UrlHelper.URL_WAYFAIR_FULL_INVENTORY))
    }

    syncFullInventory(): Observable<FullInventoryInterface>
    {
        this.setAuthorization()
        return this.mapRequest(
            this.http.post(this.url, {})
        )
    }

    getState(): Observable<FullInventoryInterface>
    {
        this.setAuthorization()
        return this.mapRequest(
            this.http.get(this.url)
        )
    }
}
