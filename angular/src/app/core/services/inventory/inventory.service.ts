import { Injectable } from "@angular/core";
import { Http } from "@angular/http";
import {
  TerraBaseService,
  TerraLoadingSpinnerService,
} from "@plentymarkets/terra-components";
import { UrlHelper } from "../../helpers/url-helper";
import { Observable } from "rxjs";
import { InventoryStatusInterface } from "./data/inventoryStatus.interface";
@Injectable()
export class InventoryService extends TerraBaseService {
  constructor(loadingBarService: TerraLoadingSpinnerService, http: Http) {
    super(
      loadingBarService,
      http,
      UrlHelper.getWayfairUrl(UrlHelper.URL_WAYFAIR_INVENTORY)
    );
  }

  getState(): Observable<InventoryStatusInterface> {
    this.setAuthorization();
    return this.mapRequest(this.http.get(this.url));
  }
}
