import { Injectable } from "@angular/core";
import { Http } from "@angular/http";
import {
  TerraBaseService,
  TerraLoadingSpinnerService,
} from "@plentymarkets/terra-components";
import {Observable} from 'rxjs';
import { UrlHelper } from "../../helpers/url-helper";
import { ResetAuthenticationInterface } from "./data/resetAuthentication.interface";
@Injectable()
export class ResetAuthenticationService extends TerraBaseService {
  constructor(loadingBarService: TerraLoadingSpinnerService, http: Http) {
    super(
      loadingBarService,
      http,
      UrlHelper.getWayfairUrl(UrlHelper.URL_WAYFAIR_RESET_AUTHENTICATION)
    );
  }

  resetAuthentication(): Observable<ResetAuthenticationInterface> {
    this.setAuthorization();
    return this.mapRequest(this.http.post(this.url, {}));
  }
}
