import {Injectable} from '@angular/core';
import {Http} from "@angular/http";
import {TerraBaseService, TerraLoadingSpinnerService} from "@plentymarkets/terra-components";
import {UrlHelper} from '../../helpers/url-helper';
import {Observable} from 'rxjs';
import {CarrierInterface} from "./data/carrier.interface";

@Injectable()
export class CarrierService extends TerraBaseService {
    constructor(loadingBarService: TerraLoadingSpinnerService, http: Http) {
        super(loadingBarService, http, UrlHelper.getWayfairUrl(UrlHelper.URL_WAYFAIR_CARRIERS))
    }

    fetchCarriers(): Observable<CarrierInterface> {
        this.setAuthorization()
        return this.mapRequest(this.http.get(this.url))
    }
}
