import { Injectable } from "@angular/core";
import { Http } from "@angular/http";
import {
  TerraBaseService,
  TerraLoadingSpinnerService,
} from "@plentymarkets/terra-components";
import { UrlHelper } from "../../helpers/url-helper";
import { Observable } from "rxjs";
import { InventoryStatusInterface } from "./data/inventoryStatus.interface";
import { InventorySyncRequestInterface } from "./data/inventorySyncRequest.interface";
import { InventorySyncResponseInterface } from "./data/inventorySyncResponse.interface";
@Injectable()
export class InventoryService extends TerraBaseService {
  constructor(loadingBarService: TerraLoadingSpinnerService, http: Http) {
    super(
      loadingBarService,
      http,
      UrlHelper.getWayfairUrl(UrlHelper.URL_WAYFAIR_INVENTORY)
    );
  }

  public getState(): Observable<InventoryStatusInterface> {
    this.setAuthorization();
    return this.mapRequest(this.http.get(this.url));
  }

  public sync(request: InventorySyncRequestInterface): void {
    this.setAuthorization();
    this.mapRequest(this.http.post(this.url, { data: request })).subscribe();
  }

  /**
   * Check if a Full Sync should be performed now, separately from the Cron Job
   * - Cron Job is not allowed to run until 24 hours after plugin deployment
   * - Cron Job could have failed last time
   *
   * @param statusObject a status result from the backend inventory service
   */
  public static needsFullSync(statusObject: InventoryStatusInterface): boolean {
    return (
      statusObject &&
      statusObject.status !== "full" &&
      (!InventoryService.syncsAttempted(statusObject, "full") ||
        InventoryService.overdue(statusObject, "full"))
    );
  }

  /**
   * Check if any syncs were attempted
   * @param statusObject a status result from the backend inventory service
   * @param syncKind 'full' or 'partial' sync, or null for "any of the syncs"
   */
  public static syncsAttempted(
    statusObject: InventoryStatusInterface,
    syncKind?: string
  ): boolean {
    if (!statusObject || !statusObject.details) {
      return false;
    }

    if (syncKind) {
      return (
        statusObject.details[syncKind] &&
        statusObject.details[syncKind].attemptedStart &&
        statusObject.details[syncKind].attemptedStart.length > 0
      );
    }

    for (const key in statusObject.details) {
      if (
        statusObject.details[key] &&
        statusObject.details[key].attemptedStart &&
        statusObject.details[key].attemptedStart.length > 0
      )
        return true;
    }

    return false;
  }

  /**
   * Check for the overdue flag
   * @param statusObject a status result from the backend inventory service
   * @param syncKind 'full' or 'partial' sync, or null for "any of the syncs"
   */
  public static overdue(
    statusObject: InventoryStatusInterface,
    syncKind?: string
  ): boolean {
    if (!statusObject || !statusObject.details) {
      return true;
    }

    if (syncKind) {
      return (
        statusObject.details[syncKind] && statusObject.details[syncKind].overdue
      );
    }

    for (const key in statusObject.details) {
      if (statusObject.details[key] && statusObject.details[key].overdue)
        return true;
    }

    return false;
  }

  /**
   * Tell the backend to perform a full inventory sync, if it seems to be overdue.
   *
   * Some reasons it could be needed:
   *  - plugin was just installed
   *  - last full sync failed
   * @param statusObject a status result from the backend inventory service
   */
  public performFullSyncIfNeeded(statusObject: InventoryStatusInterface): void {
    if (InventoryService.needsFullSync(statusObject)) {
      this.sync({ full: true });
    }
  }
}
