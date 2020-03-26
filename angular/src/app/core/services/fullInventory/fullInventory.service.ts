import { Injectable } from '@angular/core';
import { Http } from "@angular/http";
import { TerraBaseService, TerraLoadingSpinnerService } from "@plentymarkets/terra-components";
import { UrlHelper } from '../../helpers/url-helper';
import { Observable } from 'rxjs';
@Injectable()
export class FullInventoryService extends TerraBaseService {

    constructor(loadingBarService:TerraLoadingSpinnerService, http: Http) {
        super(loadingBarService, http, UrlHelper.getWayfairUrl(UrlHelper.URL_WAYFAIR_FULL_INVENTORY))
    }

    syncFullInventory()
    {
        this.setAuthorization()
        return this.mapRequest(
            this.http.post(this.url, {})
        )
    }
}