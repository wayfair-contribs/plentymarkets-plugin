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
  private static readonly TRANSLATION_KEY_ERROR = "error";
  private static readonly TRANSLATION_KEY_REFRESH = "refresh_status";
  private static readonly TRANSLATION_KEY_COMPLETE = "complete";
  private static readonly TRANSLATION_KEY_FAILED = "failed";
  private static readonly TRANSLATION_KEY_UNKNOWN = "unknown";

  private static readonly TEXT_CLASS_WARNING = "warning";
  private static readonly TEXT_CLASS_SUCCESS = "success";
  private static readonly TEXT_CLASS_DANGER = "warning";
  private static readonly TEXT_CLASS_INFO = "info";

  private static readonly STATE_RUNNING = "running";
  private static readonly STATE_LOADING = "loading";
  private static readonly STATE_IDLE = "idle";

  /**
   * The maximum amount of time to stay subscribed to a manual sync
   */
  private static readonly SYNC_UI_TIMEOUT = 30000;

  /**
   * The interval on which the UI will automatically refresh
   */
  private static readonly REFRESH_INTERVAL = 300000;

  @Language()
  public lang: string;

  public lastResult = {
    text: FullInventoryComponent.TRANSLATION_KEY_UNKNOWN,
    type: FullInventoryComponent.TEXT_CLASS_WARNING,
  };
  public successfulServiceCompletionTimestamp: string = null;
  public latestInteractionTimestamp: string = null;

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
    setInterval(
      () => this.refreshState(),
      FullInventoryComponent.REFRESH_INTERVAL
    );
  }

  /**
   * Attempt to start the Full Inventory service,
   * Update the UI accordingly
   */
  public syncFullInventory(): void {
    this.showRunning();

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
        text: FullInventoryComponent.STATE_LOADING,
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
    let text = this.translation.translate(
      FullInventoryComponent.TRANSLATION_KEY_UNKNOWN
    );
    let style = FullInventoryComponent.TEXT_CLASS_WARNING;

    if (data.stateChangeTimestamp) {
      this.latestInteractionTimestamp = new Date(
        data.stateChangeTimestamp
      ).toLocaleString();

      if (data.status == FullInventoryComponent.STATE_IDLE) {
        if (data.lastAttemptSucceeded) {
          text = FullInventoryComponent.TRANSLATION_KEY_COMPLETE;
          style = FullInventoryComponent.TEXT_CLASS_SUCCESS;
        } else {
          text = FullInventoryComponent.TRANSLATION_KEY_FAILED;
          style = FullInventoryComponent.TEXT_CLASS_DANGER;
        }
      } else {
        text = data.status;
        style = FullInventoryComponent.TEXT_CLASS_INFO;
      }
    }

    this.lastResult.text = this.translation.translate(text);
    this.lastResult.type = style;

    this.updateLastCompletion(data.lastCompletion);

    this.updateSyncButton(data.status);
    this.updateRefreshButton();

    return data.status;
  }

  private updateLastCompletion(rawDate: string)
  {
    if (rawDate) {
      this.successfulServiceCompletionTimestamp = new Date(rawDate).toLocaleString();
      return;
    }

    if (!this.successfulServiceCompletionTimestamp) {
      this.successfulServiceCompletionTimestamp = this.translation.translate(
        FullInventoryComponent.TRANSLATION_KEY_UNKNOWN
      );
    }
  }

  private showLoading() {
    let loading = this.translation.translate(
      FullInventoryComponent.STATE_LOADING
    );

    this.lastResult = {
      text: loading,
      type: FullInventoryComponent.TEXT_CLASS_INFO,
    };
    this.latestInteractionTimestamp = loading;
    this.updateSyncButton();
    this.updateRefreshButton(FullInventoryComponent.STATE_LOADING);
  }

  private showRunning() {
    let running = this.translation.translate(
      FullInventoryComponent.STATE_RUNNING
    );

    this.lastResult = {
      text: running,
      type: FullInventoryComponent.TEXT_CLASS_INFO,
    };
    this.latestInteractionTimestamp = new Date().toLocaleString();
    this.updateSyncButton(FullInventoryComponent.STATE_RUNNING);
    this.updateRefreshButton(FullInventoryComponent.STATE_RUNNING);
  }

  private showError() {
    let error = this.translation.translate(
      FullInventoryComponent.TRANSLATION_KEY_ERROR
    );
    this.lastResult = {
      text: error,
      type: FullInventoryComponent.TEXT_CLASS_DANGER,
    };
    this.latestInteractionTimestamp = error;
    this.updateSyncButton();
    this.updateRefreshButton();
  }
}
