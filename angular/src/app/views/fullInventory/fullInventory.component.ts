import { Component } from "@angular/core";
import { FullInventoryInterface } from "../../core/services/fullInventory/data/fullInventory.interface";
import { FullInventoryService } from "../../core/services/fullInventory/fullInventory.service";
import { Language, TranslationService } from "angular-l10n";

@Component({
  selector: "fullInventory",
  template: require("./fullInventory.component.html"),
})
export class FullInventoryComponent {
  private static readonly TRANSLATION_KEY_START_SYNC = "sync";
  private static readonly TRANSLATION_KEY_SYNCHRONIZING = "synchronizing";
  private static readonly TRANSLATION_KEY_LOADING = "loading";
  private static readonly TRANSLATION_KEY_ERROR = "error";
  private static readonly TRANSLATION_KEY_REFRESH = "refresh_status";
  private static readonly TRANSLATION_KEY_COMPLETE = "complete";
  private static readonly TRANSLATION_KEY_FAILED = "failed";
  private static readonly TRANSLATION_KEY_UNKNOWN = "unknown";

  private static readonly STATE_RUNNING = "running";
  private static readonly STATE_LOADING = "loading";
  private static readonly STATE_IDLE = "idle";

  /**
   * The maximum amount of time to stay subscribed to a manual sync
   */
  private static readonly SYNC_UI_TIMEOUT = 120000;

  /**
   * The interval on which the UI will automatically refresh
   */
  private static readonly REFRESH_INTERVAL = 300000;

  @Language()
  public lang: string;

  public lastResult: string = null;
  public successfulServiceCompletionTimestamp: string = null;
  public latestServiceAttemptTimestamp: string = null;

  public stateOfSyncButton = {
    text: FullInventoryComponent.TRANSLATION_KEY_START_SYNC,
    disabled: false,
  };

  public stateOfRefreshButton = {
    text: FullInventoryComponent.TRANSLATION_KEY_REFRESH,
    disabled: false,
  };

  private timeoutForSync = null;

  private syncSubscription = null;

  public constructor(
    private fullInventoryService: FullInventoryService,
    private translation: TranslationService
  ) {}

  public ngOnInit(): void {
    // pull state from the DB on load
    this.refreshState();
    // pull state from the DB every 5 minutes
    setInterval(this.refreshState, FullInventoryComponent.REFRESH_INTERVAL);
  }

  /**
   * Attempt to start the Full Inventory service,
   * Update the UI accordingly
   */
  public syncFullInventory(): void {
    this.updateSyncButton(FullInventoryComponent.STATE_RUNNING);

    this.syncSubscription = this.fullInventoryService
      .syncFullInventory()
      .subscribe(
        (data) => {
          this.onReturnFromSync(data);
        },
        (err) => {
          this.showError();
        }
      );

    this.startSyncTimeoutClock();
  }

  /**
   * Start the clock on waiting for back-end to return data
   * If the time runs out, update the UI from the DB
   */
  private startSyncTimeoutClock() {
    this.timeoutForSync = setTimeout(
      () => this.decoupleFromSyncThenRefresh(),
      FullInventoryComponent.SYNC_UI_TIMEOUT
    );
  }

  /**
   * Cancel the subscription to the back-end's inventory sync,
   * And update the UI using the DB
   */
  private decoupleFromSyncThenRefresh(): void {
    if (null != this.syncSubscription) {
      this.syncSubscription.unsubscribe();
      this.syncSubscription = null;
    }

    this.refreshState();
  }

  /**
   * Logic called when the sync subsrciption returns
   * in the allotted amount of time
   * @param data data from Sync service
   */
  private onReturnFromSync(data: FullInventoryInterface): void {
    if (null != this.timeoutForSync) {
      // cancel the scheduled async update
      clearTimeout(this.timeoutForSync);
      this.timeoutForSync = null;
    }

    this.refreshStateFromData(data);
  }

  /**
   * Load the Full Inventory Sync state from the DB and update UI to match
   */
  public refreshState(): void {
    this.showLoading();

    // TODO: make sure this is not blocked by sync
    this.fullInventoryService.getState().subscribe(
      (data) => {
        this.refreshStateFromData(data);
      },
      (err) => {
        this.showError();
      }
    );
  }

  private updateSyncButton(status = null): void {
    if (status === FullInventoryComponent.STATE_RUNNING) {
      this.stateOfSyncButton = {
        text: FullInventoryComponent.TRANSLATION_KEY_SYNCHRONIZING,
        disabled: true,
      };
      return;
    }

    this.stateOfSyncButton = {
      text: FullInventoryComponent.TRANSLATION_KEY_START_SYNC,
      disabled: false,
    };
  }

  private updateRefreshButton(status = null): void {
    if (status === FullInventoryComponent.STATE_LOADING) {
      this.stateOfRefreshButton = {
        text: FullInventoryComponent.TRANSLATION_KEY_LOADING,
        disabled: true,
      };
      return;
    }
    this.stateOfRefreshButton = {
      text: FullInventoryComponent.TRANSLATION_KEY_REFRESH,
      disabled: false,
    };
  }

  /**
   * Update the UI to match back-end data provided in the argument
   * @param data FullInventoryInterface
   */
  private refreshStateFromData(data: FullInventoryInterface): string {
    let unknown = this.translation.translate(
      FullInventoryComponent.TRANSLATION_KEY_UNKNOWN
    );
    // service may not know last completion datestamp. Don't clear out a value if we already had one.

    if (data.lastAttemptSucceeded == null) {
      this.lastResult = unknown;
    } else if (data.status == FullInventoryComponent.STATE_IDLE) {
      this.lastResult = this.translation.translate(
        data.lastAttemptSucceeded == "true"
          ? FullInventoryComponent.TRANSLATION_KEY_COMPLETE
          : FullInventoryComponent.TRANSLATION_KEY_FAILED
      );
    } else {
      this.lastResult = this.translation.translate(data.status);
    }

    this.successfulServiceCompletionTimestamp = data.lastCompletion
      ? new Date(data.lastCompletion).toLocaleString()
      : unknown;

    this.latestServiceAttemptTimestamp = data.stateChangeTimestamp
      ? new Date(data.stateChangeTimestamp).toLocaleString()
      : unknown;

    this.updateSyncButton(data.status);
    this.updateRefreshButton();

    return data.status;
  }

  private showLoading() {
    let loading = this.translation.translate(
      FullInventoryComponent.TRANSLATION_KEY_LOADING
    );
    this.lastResult = loading;
    this.successfulServiceCompletionTimestamp = loading;
    this.updateSyncButton();
    this.updateRefreshButton(FullInventoryComponent.STATE_LOADING);
  }

  private showError() {
    let error = this.translation.translate(
      FullInventoryComponent.TRANSLATION_KEY_ERROR
    );
    this.lastResult = error;
    this.successfulServiceCompletionTimestamp = error;
    this.latestServiceAttemptTimestamp = error;
    this.updateSyncButton();
    this.updateRefreshButton();
  }
}
