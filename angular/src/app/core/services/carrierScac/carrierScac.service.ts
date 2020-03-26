import {Injectable} from '@angular/core';
import {Http} from "@angular/http";
import {TerraBaseService, TerraLoadingSpinnerService} from "@plentymarkets/terra-components";
import {UrlHelper} from '../../helpers/url-helper';
import {Observable} from 'rxjs';
import {CarrierScacInterface} from './data/carrierScac.interface';

@Injectable()
export class CarrierScacService extends TerraBaseService {

    constructor(loadingBarService: TerraLoadingSpinnerService, http: Http) {
        super(loadingBarService, http, UrlHelper.getWayfairUrl(UrlHelper.URL_WAYFAIR_CARRIER_SCACS))
    }

    fetchMappings(): Observable<Array<CarrierScacInterface>> {
        this.setAuthorization()
        return this.mapRequest(
            this.http.get(this.url)
        )
    }

    postMappings(data): Observable<Array<CarrierScacInterface>> {
        this.setAuthorization()
        return this.mapRequest(
            this.http.post(this.url, {data: data})
        )
    }
}
