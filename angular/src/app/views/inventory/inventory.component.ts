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
  private static readonly TRANSLATION_KEY_NO_ISSUES = "inventory_no_issues";
  private static readonly TRANSLATION_KEY_SYNC_HAS_ISSUES =
    "inventory_has_issues";
  private static readonly TRANSLATION_KEY_NO_SYNCS =
    "inventory_no_syncs_attempted";
  private static readonly TRANSLATION_KEY_AT = "at";
  private static readonly TRANSLATION_KEY_COMPLETED_WITH = "completed_with";
  private static readonly TRANSLATION_KEY_PRODUCTS = "products";
  private static readonly TRANSLATION_KEY_SKIPPED = "inventory_skipped";
  private static readonly TRANSLATION_KEY_HAS_NEVER_SUCCEEDED =
    "has_never_succeeded";
  private static readonly TRANSLATION_KEY_HAS_NEVER_BEEN_ATTEMPTED =
    "has_never_been_attempted";

  private static readonly STATE_IDLE = "idle";

  private static readonly TEXT_CLASS_WARNING = "text-warning";
  private static readonly TEXT_CLASS_DANGER = "text-danger";
  private static readonly TEXT_CLASS_INFO = "text-info";
  private static readonly TEXT_CLASS_SUCCESS = "text-success";

  private static readonly ICON_SIZE_MAIN= "8vw";
  private static readonly ICON_SIZE_TABLE = "2vw";

  /**
   * The interval on which the UI will automatically refresh
   */
  private static readonly REFRESH_INTERVAL = 60000;

  @Language()
  public lang: string;

  objectKeysWrapper = Object.keys;

  public statusObject: InventoryStatusInterface;

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
    if (this.needsAttention()) {
      this.displayedState.value = this.displayedState.value = this.translation.translate(
        InventoryComponent.TRANSLATION_KEY_SYNC_HAS_ISSUES
      );

      this.displayedState.style = InventoryComponent.TEXT_CLASS_DANGER;

      return;
    }

    if (!this.syncsAttempted()) {
      this.displayedState.value = this.translation.translate(
        InventoryComponent.TRANSLATION_KEY_NO_SYNCS
      );

      this.displayedState.style = InventoryComponent.TEXT_CLASS_WARNING;
      return;
    }

    this.displayedState.value = this.translation.translate(
      InventoryComponent.TRANSLATION_KEY_NO_ISSUES
    );

    this.displayedState.style = InventoryComponent.TEXT_CLASS_SUCCESS;

    return;
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
    return (
      this.statusObject &&
      this.statusObject.status.length > 0 &&
      this.statusObject.status != InventoryComponent.STATE_IDLE &&
      (!kind || this.statusObject.status == kind)
    );
  }

  public successIconEnabled(kind?: string): boolean {
    return !(this.runningIconEnabled(kind) || this.errorIconEnabled(kind));
  }

  public errorIconEnabled(kind?: string): boolean {
    return (
      this.displayedState.value.startsWith("error") || this.needsAttention(kind)
    );
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

  public getTextClass(kind?: string): string {
    if (!kind) {
      return this.displayedState.style;
    }

    if (this.needsAttention(kind)) {
      return InventoryComponent.TEXT_CLASS_DANGER;
    }

    if (!this.syncsAttempted(kind)) {
      return InventoryComponent.TEXT_CLASS_WARNING;
    }

    return InventoryComponent.TEXT_CLASS_SUCCESS;
  }

  public getStatusText(kind?: string): string {
    if (kind) {
      let buffer = this.translation.translate("inventory_status_label_" + kind);

      if (this.statusObject.details[kind]) {
        if (this.statusObject[kind].completedStart) {
          buffer +=
            " " +
            this.translation.translate(InventoryComponent.TRANSLATION_KEY_AT) +
            " " +
            this.statusObject[kind].completedStart;

          let amt = this.statusObject[kind].completedAmount;

          if (amt && amt > 0) {
            return (
              buffer +
              " " +
              this.translation.translate(
                InventoryComponent.TRANSLATION_KEY_COMPLETED_WITH
              ) +
              " " +
              amt +
              " " +
              this.translation.translate(
                InventoryComponent.TRANSLATION_KEY_PRODUCTS
              )
            );
          }

          return (
            buffer +
            " " +
            this.translation.translate(
              InventoryComponent.TRANSLATION_KEY_SKIPPED
            )
          );
        }

        if (!this.syncsAttempted(kind)) {
          return (
            buffer +
            " " +
            this.translation.translate(
              InventoryComponent.TRANSLATION_KEY_HAS_NEVER_BEEN_ATTEMPTED
            )
          );
        }

        return (
          buffer +
          " " +
          this.translation.translate(
            InventoryComponent.TRANSLATION_KEY_HAS_NEVER_SUCCEEDED
          )
        );
      }
      // unexpected state - requested details for a row that doesn't exist
      return "";
    }

    return this.displayedState.value;
  }

  public needsAttention(kind?: string): boolean {
    if (!this.statusObject || !this.statusObject.details) {
      // lack of data is a problem
      return true;
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

  public getIconSize(inTable?: boolean): string
  {
    if (inTable)
    {
      return InventoryComponent.ICON_SIZE_TABLE;
    }

    return InventoryComponent.ICON_SIZE_MAIN;
  }
}
