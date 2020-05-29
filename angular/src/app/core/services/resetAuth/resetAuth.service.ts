import { Injectable } from "@angular/core";
import { Http } from "@angular/http";
import {
  TerraBaseService,
  TerraLoadingSpinnerService,
} from "@plentymarkets/terra-components";
import {Observable} from 'rxjs';
import { UrlHelper } from "../../helpers/url-helper";
import { ResetAuthInterface } from "./data/resetAuth.interface";
@Injectable()
export class ResetAuthService extends TerraBaseService {
  constructor(loadingBarService: TerraLoadingSpinnerService, http: Http) {
    super(
      loadingBarService,
      http,
      UrlHelper.getWayfairUrl(UrlHelper.URL_WAYFAIR_REST_AUTH)
    );
  }

  resetAuth(): Observable<ResetAuthInterface> {
    this.setAuthorization();
    return this.mapRequest(this.http.post(this.url, {}));
  }
}
