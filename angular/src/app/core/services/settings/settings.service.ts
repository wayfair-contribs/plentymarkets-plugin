import { Injectable } from '@angular/core';
import { Http } from "@angular/http";
import { TerraBaseService, TerraLoadingSpinnerService } from "@plentymarkets/terra-components";
import { UrlHelper } from '../../helpers/url-helper';
import { Observable } from 'rxjs';
import { SettingsInterface } from './data/settings.interface';
@Injectable()
export class SettingsService extends TerraBaseService {
    
    constructor(loadingBarService:TerraLoadingSpinnerService, http: Http) {
        super(loadingBarService, http, UrlHelper.getWayfairUrl(UrlHelper.URL_WAYFAIR_SETTINGS))
    }

    fetch(): Observable<SettingsInterface>
    {
        this.setAuthorization()
        return this.mapRequest(
            this.http.get(this.url)
        )
    }

    save(data): Observable<SettingsInterface>
    {
        this.setAuthorization()
        return this.mapRequest(
            this.http.post(this.url, {data: data})
        )
    }
}