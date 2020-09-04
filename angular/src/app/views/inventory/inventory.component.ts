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

  private static readonly STATE_IDLE = "idle";

  private static readonly TEXT_CLASS_WARNING = "warning";
  private static readonly TEXT_CLASS_DANGER = "danger";
  private static readonly TEXT_CLASS_INFO = "info";

  /**
   * The interval on which the UI will automatically refresh
   */
  private static readonly REFRESH_INTERVAL = 300000;

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
    this.displayedState.style = InventoryComponent.TEXT_CLASS_INFO;

    if (this.statusObject.status == InventoryComponent.STATE_IDLE) {
      if (this.noSyncsAttempted()) {
        this.displayedState.value =
        this.translation.translate(InventoryComponent.TRANSLATION_KEY_WAITING_FOR_FIRST_SYNC);

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

  public shouldDisplayLoading(): boolean {
    return (
      this.displayedState &&
      this.displayedState.value == InventoryComponent.TRANSLATION_KEY_LOADING
    );
  }

  public shouldDisplayRunning(kind: string): boolean {
    return this.displayedState && this.displayedState.value == kind;
  }

  public shouldDisplayCheckmark(kind: string): boolean {
    return (
      this.statusObject &&
      this.statusObject.status != kind &&
      !this.displayedState.value.startsWith("error") &&
      !this.statusObject.details[kind].needsAttention
    );
  }

  public shouldDisplayErrorIcon(kind: string): boolean {
    return (
      !this.displayedState ||
      null == this.displayedState.value ||
      this.displayedState.value.length == 0 ||
      !(this.shouldDisplayCheckmark(kind) || this.shouldDisplayRunning(kind))
    );
  }

  public noSyncsAttempted(): boolean {
    if (!this.statusObject || !this.statusObject.details) {
      return false;
    }

    let detailBodies: InventoryStatusDetailsBodyInterface[] = [
      this.statusObject.details.full,
      this.statusObject.details.partial,
    ];
    return (
      detailBodies.findIndex(
        (d) => d && d.attemptedStart && d.attemptedStart.length > 0
      ) < 0
    );
  }
}
