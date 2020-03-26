import { Injectable } from '@angular/core';
import { Http } from "@angular/http";
import { TerraBaseService, TerraLoadingSpinnerService } from "@plentymarkets/terra-components";
import { UrlHelper } from '../../helpers/url-helper';
import { Observable } from 'rxjs';
import { WarehouseSupplierInterface } from './data/warehouseSupplier.interface';

@Injectable()
export class WarehouseSupplierService extends TerraBaseService {
    
    constructor(loadingBarService:TerraLoadingSpinnerService, http: Http) {
        super(loadingBarService, http, UrlHelper.getWayfairUrl(UrlHelper.URL_WAYFAIR_WAREHOUSE_SUPPLIER))
    }

    fetchMappings(): Observable<Array<WarehouseSupplierInterface>>
    {
        this.setAuthorization()
        return this.mapRequest(
            this.http.get(this.url)
        )
    }

    postMappings(data): Observable<Array<WarehouseSupplierInterface>>
    {
        this.setAuthorization()
        return this.mapRequest(
            this.http.post(this.url, {data: data})
        )
    }

    deleteMapping(data)
    {
        this.setAuthorization();
        return this.mapRequest(
            this.http.delete(this.url, data)
        )
    }
}