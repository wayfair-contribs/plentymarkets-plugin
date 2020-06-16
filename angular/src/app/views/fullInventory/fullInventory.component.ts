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

  public constructor(
    private fullInventoryService: FullInventoryService,
    private translation: TranslationService
  ) {}

  public ngOnInit(): void {
    this.refreshState();
  }

  public syncFullInventory(): void {
    this.updateSyncButton(FullInventoryComponent.STATE_RUNNING);

    this.fullInventoryService.syncFullInventory().subscribe(
      (data) => {
        this.refreshStateFromData(data);
      },
      (err) => {
        this.showError();
      }
    );
  }

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
   *
   * @param data FullInventoryInterface
   */
  private refreshStateFromData(data: FullInventoryInterface): string {
    let unknown = this.translation.translate(FullInventoryComponent.TRANSLATION_KEY_UNKNOWN);
    // service may not know last completion datestamp. Don't clear out a value if we already had one.

    if (data.lastAttemptSucceeded == null)
    {
      this.lastResult = unknown;
    }
    else if (data.status == FullInventoryComponent.STATE_IDLE)
    {
      this.lastResult = this.translation.translate(
        data.lastAttemptSucceeded == null
          ? FullInventoryComponent.TRANSLATION_KEY_COMPLETE
          : FullInventoryComponent.TRANSLATION_KEY_FAILED
      );
    }
    else
    {
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
