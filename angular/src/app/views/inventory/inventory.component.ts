import { Component } from "@angular/core";
import { InventoryStatusInterface } from "../../core/services/inventory/data/inventoryStatus.interface";
import { InventoryStatusDetailsBodyInterface } from "../../core/services/inventory/data/inventoryStatusDetailsBody.interface";
import { InventoryService } from "../../core/services/inventory/inventory.service";
import { Language, TranslationService } from "angular-l10n";

@Component({
  selector: "inventory",
  template: require("./inventory.component.html"),
})
export class InventoryComponent {
  private static readonly TRANSLATION_KEY_REFRESH = "refresh";
  private static readonly TRANSLATION_KEY_LOADING = "loading";
  private static readonly TRANSLATION_KEY_ERROR_FETCH = "error_fetch";
  private static readonly TRANSLATION_KEY_WAITING_FOR_FIRST_SYNC =
    "inventory_waiting_for_first_sync";
  private static readonly TRANSLATION_KEY_WAITING_FOR_NEXT_SYNC =
    "inventory_waiting_for_next_sync";
  private static readonly TRANSLATION_KEY_IN_PROGRESS = "in_progress";
  private static readonly TRANSLATION_KEY_SYNC_HAS_ISSUES = "sync_has_issues";

  private static readonly STATE_IDLE = "idle";

  private static readonly TEXT_CLASS_WARNING = "warning";
  private static readonly TEXT_CLASS_DANGER = "danger";
  private static readonly TEXT_CLASS_INFO = "info";
  private static readonly TEXT_CLASS_PRIMARY = "primary";

  private static readonly ICON_SIZE_HUGE = "8em";
  private static readonly ICON_SIZE_NORMAL = "2em";

  /**
   * The interval on which the UI will automatically refresh
   */
  private static readonly REFRESH_INTERVAL = 60000;

  @Language()
  public lang: string;

  objectKeysWrapper = Object.keys;

  public statusObject: InventoryStatusInterface = {
    status: InventoryComponent.TRANSLATION_KEY_LOADING,
    details: {
      full: { needsAttention: false },
      partial: { needsAttention: false },
    },
  };

  public displayedState = {
    value: InventoryComponent.TRANSLATION_KEY_LOADING,
    style: InventoryComponent.TEXT_CLASS_INFO,
  };

  public stateOfRefreshButton = {
    text: InventoryComponent.TRANSLATION_KEY_REFRESH,
    disabled: false,
  };

  public fetchTime: string;

  public constructor(
    private inventoryService: InventoryService,
    private translation: TranslationService
  ) {}

  public ngOnInit(): void {
    // pull state from the DB on load
    this.refreshState();
    // pull state from the DB every 5 minutes
    setInterval(() => this.refreshState(), InventoryComponent.REFRESH_INTERVAL);
  }

  /**
   * Load the Full Inventory Sync state from the DB and update UI to match
   */
  public refreshState(): void {
    this.showLoading();

    this.inventoryService.getState().subscribe(
      (data) => {
        this.refreshStateFromData(data);
      },
      (err) => {
        this.statusObject = null;
        this.showError();
      }
    );
  }

  private updateFetchTime(): void {
    this.fetchTime = new Date().toLocaleString();
  }

  private updateRefreshButton(): void {
    if (
      this.displayedState.value === InventoryComponent.TRANSLATION_KEY_LOADING
    ) {
      this.stateOfRefreshButton.disabled = true;
      return;
    }
    this.stateOfRefreshButton.text = InventoryComponent.TRANSLATION_KEY_REFRESH;
    this.stateOfRefreshButton.disabled = false;
  }

  /**
   * Update the UI to match back-end data provided in the argument
   * @param data FullInventoryInterface
   */
  private refreshStateFromData(data: InventoryStatusInterface) {
    this.statusObject = data;

    this.updateFetchTime();

    this.updateDisplayedState();

    this.updateRefreshButton();
  }

  private updateDisplayedState() {
    this.displayedState.style = InventoryComponent.TEXT_CLASS_PRIMARY;

    if (this.statusObject.status == InventoryComponent.STATE_IDLE) {
      if (this.syncIssuesHappened()) {
        this.displayedState.value = this.displayedState.value = this.translation.translate(
          InventoryComponent.TRANSLATION_KEY_SYNC_HAS_ISSUES
        );

        this.displayedState.style = InventoryComponent.TEXT_CLASS_DANGER;

        return;
      }

      if (!this.syncsAttempted()) {
        this.displayedState.value = this.translation.translate(
          InventoryComponent.TRANSLATION_KEY_WAITING_FOR_FIRST_SYNC
        );

        this.displayedState.style = InventoryComponent.TEXT_CLASS_WARNING;
        return;
      }

      this.displayedState.value = this.translation.translate(
        InventoryComponent.TRANSLATION_KEY_WAITING_FOR_NEXT_SYNC
      );

      return;
    }

    this.displayedState.value =
      this.translation.translate(
        "inventory_status_label_" + this.statusObject.status
      ) +
      " " +
      this.translation.translate(
        InventoryComponent.TRANSLATION_KEY_IN_PROGRESS
      );
  }

  private showLoading() {
    this.displayedState.value = InventoryComponent.TRANSLATION_KEY_LOADING;
    this.displayedState.style = InventoryComponent.TEXT_CLASS_INFO;

    this.updateRefreshButton();
  }

  private showError() {
    this.displayedState.value = InventoryComponent.TRANSLATION_KEY_ERROR_FETCH;
    this.displayedState.style = InventoryComponent.TEXT_CLASS_DANGER;

    this.updateRefreshButton();
    this.updateFetchTime();
  }

  public loadingIconEnabled(): boolean {
    return (
      this.displayedState &&
      this.displayedState.value == InventoryComponent.TRANSLATION_KEY_LOADING
    );
  }

  public runningIconEnabled(kind?: string): boolean {
    if (!this.statusObject) {
      return false;
    }
    if (!kind || kind.length == 0) {
      return this.statusObject.status != InventoryComponent.STATE_IDLE;
    }

    return this.statusObject.status == kind;
  }

  public successIconEnabled(kind?: string): boolean {
    return !(
      this.clockIconEnabled(kind) ||
      this.runningIconEnabled(kind) ||
      this.errorIconEnabled(kind)
    );
  }

  public errorIconEnabled(kind?: string): boolean {
    return (
      (kind && this.syncIssuesHappened(kind)) ||
      (!kind &&
        (this.displayedState.value.startsWith("error") ||
          this.syncIssuesHappened()))
    );
  }

  public clockIconEnabled(kind?: string): boolean {
    return !this.syncsAttempted(kind);
  }

  public syncsAttempted(kind?: string): boolean {
    if (!this.statusObject || !this.statusObject.details) {
      return false;
    }

    if (kind) {
      return (
        this.statusObject.details[kind] &&
        this.statusObject.details[kind].attemptedStart &&
        this.statusObject.details[kind].attemptedStart.length > 0
      );
    }

    for (const key in this.statusObject.details) {
      if (
        this.statusObject.details[key] &&
        this.statusObject.details[key].attemptedStart &&
        this.statusObject.details[key].attemptedStart.length > 0
      )
        return true;
    }

    return false;
  }

  public syncIssuesHappened(kind?: string): boolean {
    if (!this.statusObject || !this.statusObject.details) {
      return false;
    }

    if (kind) {
      return this.statusObject[kind] && this.statusObject[kind].needsAttention;
    }

    for (const key in this.statusObject.details) {
      if (
        this.statusObject.details[key] &&
        this.statusObject.details[key].needsAttention
      )
        return true;
    }

    return false;
  }
}
