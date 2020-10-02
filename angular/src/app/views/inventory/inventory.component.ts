import { Component } from '@angular/core';
import { InventoryStatusInterface } from '../../core/services/inventory/data/inventoryStatus.interface';
import { InventoryService } from '../../core/services/inventory/inventory.service';
import { Language, TranslationService } from 'angular-l10n';
import * as moment from 'moment';
import { Icon } from './icon.struct';
import { DisplayedState } from './displayedState.struct';

@Component({
  selector: "inventory",
  template: require("./inventory.component.html"),
})
export class InventoryComponent {
  private static readonly TRANSLATION_KEY_LOADING = "loading";
  private static readonly TRANSLATION_KEY_ERROR_FETCH = "error_fetch";
  private static readonly TRANSLATION_KEY_NO_ISSUES = "inventory_no_issues";
  private static readonly TRANSLATION_KEY_SYNC_HAS_ISSUES =
    "inventory_has_issues";
  private static readonly TRANSLATION_KEY_AT = "at";
  private static readonly TRANSLATION_KEY_COMPLETED_WITH = "completed_with";
  private static readonly TRANSLATION_KEY_PRODUCTS = "products";
  private static readonly TRANSLATION_KEY_SKIPPED = "inventory_skipped";
  private static readonly TRANSLATION_KEY_HAS_NEVER_SUCCEEDED =
    "has_never_succeeded";
  private static readonly TRANSLATION_KEY_HAS_NEVER_BEEN_ATTEMPTED =
    "has_never_been_attempted";
  private static readonly TRANSLATION_KEY_INV_SYNC_LABEL =
    "inventory_synchronization_label";
  private static readonly TRANSLATION_KEY_IS_CURRENTLY_RUNNING =
    "is_currently_running";
  private static readonly TRANSLATION_KEY_AND = "and";
  private static readonly TRANSLATION_KEY_IS_OVERDUE = "is_overdue";

  private static readonly STATE_IDLE = "idle";

  /**
   * The interval on which the UI will automatically refresh
   */
  private static readonly REFRESH_INTERVAL = 60000;

  private static readonly ICON_LOADING: Icon = {
    iconClass: "bi bi-arrow-repeat",
    iconDrawings: [
      "M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z",
      "M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z",
    ],
  };

  private static readonly ICON_ERROR: Icon = {
    iconClass: "bi bi-question-diamond-fill",
    iconDrawings: [
      "M9.05.435c-.58-.58-1.52-.58-2.1 0L.436 6.95c-.58.58-.58 1.519 0 2.098l6.516 6.516c.58.58 1.519.58 2.098 0l6.516-6.516c.58-.58.58-1.519 0-2.098L9.05.435zM5.495 6.033a.237.237 0 0 1-.24-.247C5.35 4.091 6.737 3.5 8.005 3.5c1.396 0 2.672.73 2.672 2.24 0 1.08-.635 1.594-1.244 2.057-.737.559-1.01.768-1.01 1.486v.105a.25.25 0 0 1-.25.25h-.81a.25.25 0 0 1-.25-.246l-.004-.217c-.038-.927.495-1.498 1.168-1.987.59-.444.965-.736.965-1.371 0-.825-.628-1.168-1.314-1.168-.803 0-1.253.478-1.342 1.134-.018.137-.128.25-.266.25h-.825zm2.325 6.443c-.584 0-1.009-.394-1.009-.927 0-.552.425-.94 1.01-.94.609 0 1.028.388 1.028.94 0 .533-.42.927-1.029.927z",
    ],
  };

  private static readonly ICON_SCHEDULED: Icon = {
    iconClass: "bi bi-calendar3",
    iconDrawings: [
      "M14 0H2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2zM1 3.857C1 3.384 1.448 3 2 3h12c.552 0 1 .384 1 .857v10.286c0 .473-.448.857-1 .857H2c-.552 0-1-.384-1-.857V3.857z",
      "M6.5 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-9 3a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-9 3a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2z",
    ],
  };

  private static readonly ICON_CLOUD_SLASH: Icon = {
    iconClass: "bi bi-cloud-slash-fill",
    iconDrawings: [
      "M3.112 5.112a3.125 3.125 0 0 0-.17.613C1.266 6.095 0 7.555 0 9.318 0 11.366 1.708 13 3.781 13H11L3.112 5.112zm11.372 7.372L4.937 2.937A5.512 5.512 0 0 1 8 2c2.69 0 4.923 2 5.166 4.579C14.758 6.804 16 8.137 16 9.773a3.2 3.2 0 0 1-1.516 2.711zm-.838 1.87l-12-12 .708-.708 12 12-.707.707z",
    ],
  };

  private static readonly ICON_CLOUD_CHECK: Icon = {
    iconClass: "bi bi-cloud-check-fill",
    iconDrawings: [
      "M8 2a5.53 5.53 0 0 0-3.594 1.342c-.766.66-1.321 1.52-1.464 2.383C1.266 6.095 0 7.555 0 9.318 0 11.366 1.708 13 3.781 13h8.906C14.502 13 16 11.57 16 9.773c0-1.636-1.242-2.969-2.834-3.194C12.923 3.999 10.69 2 8 2zm2.354 4.854a.5.5 0 0 0-.708-.708L7 8.793 5.854 7.646a.5.5 0 1 0-.708.708l1.5 1.5a.5.5 0 0 0 .708 0l3-3z",
    ],
  };

  private static readonly ICON_RUNNING: Icon = {
    iconClass: "bi bi-cloud-upload-fill",
    iconDrawings: [
      "M8 0a5.53 5.53 0 0 0-3.594 1.342c-.766.66-1.321 1.52-1.464 2.383C1.266 4.095 0 5.555 0 7.318 0 9.366 1.708 11 3.781 11H7.5V5.707L5.354 7.854a.5.5 0 1 1-.708-.708l3-3a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 5.707V11h4.188C14.502 11 16 9.57 16 7.773c0-1.636-1.242-2.969-2.834-3.194C12.923 1.999 10.69 0 8 0zm-.5 14.5V11h1v3.5a.5.5 0 0 1-1 0z",
    ],
  };

  @Language()
  public lang: string;

  public statusObject: InventoryStatusInterface;

  public mainState: DisplayedState = {
    style: "",
    message: "",
    icon: InventoryComponent.ICON_ERROR,
  };

  public table: DisplayedState[];

  public fetchTime: string;

  public constructor(
    private inventoryService: InventoryService,
    private translation: TranslationService
  ) {}

  public ngOnInit(): void {
    // pull state from the DB on load
    this.refreshState();
    // repeatedly pull state from the DB on the prescribed interval
    setInterval(() => this.refreshState(), InventoryComponent.REFRESH_INTERVAL);
  }

  /**
   * Check if a Full Sync should be performed now, separately from the Cron Job
   * - Cron Job is not allowed to run until 24 hours after plugin deployment
   * - Cron Job could have failed last time
   */
  public needsFullSync(): boolean {
    return (
      this.statusObject.status != "full" &&
      (!this.syncsAttempted("full") || this.overdue("full"))
    );
  }

  /**
   * Load the Inventory Sync state from the DB and update UI to match
   */
  public refreshState(): void {
    this.showLoading();

    this.inventoryService.getState().subscribe(
      (data) => {
        this.refreshStateFromData(data);

        if (this.needsFullSync) {
          // avoid subscribing, as this could take a long time.
          this.inventoryService.sync({ full: true });
        }
      },
      (err) => {
        this.statusObject = null;
        this.showError();
      }
    );
  }

  private updateFetchTime(): void {
    this.fetchTime = moment().toLocaleString();
  }

  /**
   * Update the UI to match back-end data provided in the argument
   * @param data InventoryStatusInterface
   */
  private refreshStateFromData(data: InventoryStatusInterface) {
    this.statusObject = data;

    this.updateFetchTime();

    this.updateMainState();
    this.updateTable();
  }

  /**
   * Update the icon, text, and style of the big state in the UI
   */
  private updateMainState() {
    if (!this.syncsAttempted()) {
      this.mainState.message =
        this.translation.translate(
          InventoryComponent.TRANSLATION_KEY_INV_SYNC_LABEL
        ) +
        " " +
        this.translation.translate(
          InventoryComponent.TRANSLATION_KEY_HAS_NEVER_BEEN_ATTEMPTED
        );

      this.mainState.style = DisplayedState.TEXT_CLASS_WARNING;

      this.mainState.icon = InventoryComponent.ICON_SCHEDULED;

      return;
    }

    if (this.overdue()) {
      if (
        this.mainState.style != DisplayedState.TEXT_CLASS_DANGER &&
        this.statusObject.status != InventoryComponent.STATE_IDLE
      ) {
        // avoid saying there are issues - this is newly overdue and we're actively syncing
        // this is also where we are during the first sync!
        this.mainState.message =
          this.translation.translate(
            InventoryComponent.TRANSLATION_KEY_INV_SYNC_LABEL
          ) +
          " " +
          this.translation.translate(
            InventoryComponent.TRANSLATION_KEY_IS_CURRENTLY_RUNNING
          );

        this.mainState.icon = InventoryComponent.ICON_RUNNING;

        // keep the same style as it already had
        return;
      }

      // newly overdue, or continues to be overdue (and is possibly running)

      this.mainState.message = this.translation.translate(
        InventoryComponent.TRANSLATION_KEY_SYNC_HAS_ISSUES
      );

      this.mainState.style = DisplayedState.TEXT_CLASS_DANGER;

      this.mainState.icon = InventoryComponent.ICON_CLOUD_SLASH;

      return;
    }

    this.mainState.message = this.translation.translate(
      InventoryComponent.TRANSLATION_KEY_NO_ISSUES
    );

    this.mainState.style = DisplayedState.TEXT_CLASS_SUCCESS;

    this.mainState.icon = InventoryComponent.ICON_CLOUD_CHECK;

    return;
  }

  /**
   * Put the UI in the "loading" state.
   */
  private showLoading() {
    this.mainState.message = this.translation.translate(
      InventoryComponent.TRANSLATION_KEY_LOADING
    );
    this.mainState.style = DisplayedState.TEXT_CLASS_INFO;
    this.mainState.icon = InventoryComponent.ICON_LOADING;
    this.updateFetchTime();
  }

  /**
   * Put the UI in the "error" state
   */
  private showError() {
    this.mainState.message = this.translation.translate(
      InventoryComponent.TRANSLATION_KEY_ERROR_FETCH
    );
    this.mainState.style = DisplayedState.TEXT_CLASS_DANGER;
    this.mainState.icon = InventoryComponent.ICON_ERROR;
    this.table = null;
    this.updateFetchTime();
  }

  /**
   * Update the table data.
   * Always run this AFTER updating the main state, as the main state may influence the table.
   */
  public updateTable() {
    this.table = [];
    if (
      !this.statusObject ||
      !this.statusObject.details ||
      !this.mainState ||
      this.mainState.icon == InventoryComponent.ICON_LOADING ||
      this.mainState.icon == InventoryComponent.ICON_ERROR ||
      !this.syncsAttempted()
    ) {
      // don't show a table
      return;
    }

    for (const key in this.statusObject.details) {
      let rowLabel = this.translation.translate(
        "inventory_status_label_" + key
      );

      let nextRow: DisplayedState = {
        icon: InventoryComponent.ICON_CLOUD_CHECK,
        message: rowLabel,
        style: DisplayedState.TEXT_CLASS_BODY,
      };

      if (this.statusObject.status == key) {
        nextRow.message +=
          " " +
          this.translation.translate(
            InventoryComponent.TRANSLATION_KEY_IS_CURRENTLY_RUNNING
          );
        nextRow.icon = InventoryComponent.ICON_RUNNING;
      } else if (this.syncsAttempted(key)) {
        if (this.overdue(key)) {
          nextRow.style = DisplayedState.TEXT_CLASS_DANGER;
          nextRow.message +=
            " " +
            this.translation.translate(
              InventoryComponent.TRANSLATION_KEY_IS_OVERDUE
            ) +
            " " +
            this.translation.translate(InventoryComponent.TRANSLATION_KEY_AND) +
            " ";
        }

        if (this.statusObject.details[key].completedStart) {
          nextRow.message +=
            " " +
            this.translation.translate(InventoryComponent.TRANSLATION_KEY_AT) +
            " " +
            moment(
              new Date(this.statusObject.details[key].completedStart)
            ).toLocaleString();

          let amt = this.statusObject.details[key].completedAmount;

          if (amt && amt > 0) {
            nextRow.message +=
              " " +
              this.translation.translate(
                InventoryComponent.TRANSLATION_KEY_COMPLETED_WITH
              ) +
              " " +
              amt +
              " " +
              this.translation.translate(
                InventoryComponent.TRANSLATION_KEY_PRODUCTS
              );
          } else {
            nextRow.message +=
              " " +
              this.translation.translate(
                InventoryComponent.TRANSLATION_KEY_SKIPPED
              );
          }
        } else {
          nextRow.message +=
            " " +
            this.translation.translate(
              InventoryComponent.TRANSLATION_KEY_HAS_NEVER_SUCCEEDED
            );

          nextRow.style = DisplayedState.TEXT_CLASS_DANGER;
          nextRow.icon = InventoryComponent.ICON_CLOUD_SLASH;
        }
      } else {
        nextRow.message +=
          " " +
          this.translation.translate(
            InventoryComponent.TRANSLATION_KEY_HAS_NEVER_BEEN_ATTEMPTED
          );

        nextRow.icon = InventoryComponent.ICON_SCHEDULED;
      }

      nextRow.message += ".";
      this.table.push(nextRow);
    }
  }

  /**
   * Check if any syncs were attempted
   * @param syncKind 'full' or 'partial' sync, or null for "any of the syncs"
   */
  public syncsAttempted(syncKind?: string): boolean {
    if (!this.statusObject || !this.statusObject.details) {
      return false;
    }

    if (syncKind) {
      return (
        this.statusObject.details[syncKind] &&
        this.statusObject.details[syncKind].attemptedStart &&
        this.statusObject.details[syncKind].attemptedStart.length > 0
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

  /**
   * Check for the overdue flag
   * @param syncKind 'full' or 'partial' sync, or null for "any of the syncs"
   */
  public overdue(syncKind?: string): boolean {
    if (!this.statusObject || !this.statusObject.details) {
      // lack of data should be considered overdue
      return true;
    }

    if (syncKind) {
      return (
        this.statusObject.details[syncKind] &&
        this.statusObject.details[syncKind].overdue
      );
    }

    for (const key in this.statusObject.details) {
      if (
        this.statusObject.details[key] &&
        this.statusObject.details[key].overdue
      )
        return true;
    }

    return false;
  }
}
