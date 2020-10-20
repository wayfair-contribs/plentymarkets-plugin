import { Component, OnInit } from "@angular/core";
import { Language, TranslationService } from "angular-l10n";
import { WarehouseSupplierInterface } from "../..//core/services/warehouseSupplier/data/warehouseSupplier.interface";
import { WarehouseSupplierService } from "../../core/services/warehouseSupplier/warehouseSupplier.service";
import { WarehouseInterface } from "../../core/services/warehouse/data/warehouse.interface";
import { WarehouseService } from "../../core/services/warehouse/warehouse.service";

@Component({
  selector: "warehouse-supplier",
  template: require("./warehouseSupplier.component.html"),
  styles: [require("./warehouseSupplier.component.scss")],
})
export class WarehouseSupplierComponent implements OnInit {
  /**
   * The interval on which the UI will automatically refresh Warehouses
   */
  private static readonly REFRESH_WAREHOUSES_INTERVAL: number = 300000;

  @Language()
  public lang: string;

  public warehouseSuppliers: Array<WarehouseSupplierInterface> = [];

  public removedWarehouseSuppliers: Array<WarehouseSupplierInterface> = [];

  public warehouses: Array<WarehouseInterface> = [];

  public status = { type: null, value: null };

  constructor(
    private warehouseService: WarehouseService,
    private warehouseSupplierService: WarehouseSupplierService,
    private translation: TranslationService
  ) {}

  public ngOnInit(): void {
    this.loadEverythingFromBackend();

    // repeatedly pull Warehouses list from Plenty on the prescribed interval
    setInterval(
      () =>
        this.loadWarehousesFromBackend(() =>
          this.validateMappings(null, (issues) => {
            // notify user that changes in backend have invalidated the mappings
            this.status.type = "text-danger";
            this.status.value = issues;
          })
        ),
      WarehouseSupplierComponent.REFRESH_WAREHOUSES_INTERVAL
    );
  }

  /**
   * update the status to show an error during fetch
   */
  private showFetchError(): void {
    this.status.type = "text-danger";
    this.status.value = this.translation.translate("error_fetch");
  }

  /**
   * update the status to show tha the save failed
   */
  public showSaveError(): void {
    this.status.type = "text-danger";
    this.status.value = this.translation.translate("error_save");
  }

  /**
   * Clear the current status
   */
  public clearMessage(): void {
    this.status.type = "";
    this.status.value = "";
  }

  /**
   * Load all warehouses and mappings from backend
   * @param successCallback action to take if ALL loading passes
   * @param failureCallBack action to take if ANY loading fails
   */
  private loadEverythingFromBackend(
    successCallback?: () => void,
    failureCallBack?: () => void
  ): void {
    this.clearMessage();
    this.loadWarehousesFromBackend(() => {
      this.loadMappingsFromBackend(successCallback, failureCallBack),
        failureCallBack;
    });
  }

  /**
   * Load the Mappings data from the plugin backend
   * @param successCallback action to take if ALL loading passes
   * @param failureCallBack action to take if ANY loading fails
   */
  private loadMappingsFromBackend(
    successCallback?: () => void,
    failureCallBack?: () => void
  ): void {
    this.clearMessage();
    this.warehouseSupplierService.fetchMappings().subscribe(
      (data) => {
        this.warehouseSuppliers = data;
        if (successCallback) {
          successCallback();
        }
      },
      (err) => {
        this.showFetchError();
        if (failureCallBack) {
          failureCallBack();
        }
      }
    );
  }

  /**
   * Load information on the Plentymarkets system's warehouses
   * @param successCallback action to take if ALL loading passes
   * @param failureCallBack action to take if ANY loading fails
   */
  public loadWarehousesFromBackend(
    successCallback?: () => void,
    failureCallBack?: () => void
  ): void {
    this.warehouseService.fetch().subscribe(
      (data) => {
        this.warehouses = data;
        if (successCallback) {
          successCallback();
        }
      },
      (err) => {
        this.showFetchError();
        if (failureCallBack) {
          failureCallBack();
        }
      }
    );
  }

  private isWarehouseIdValid(warehouseId: string): boolean {
    if (!warehouseId) {
      return false;
    }

    let idAsNumber = parseInt(warehouseId);
    if (isNaN(idAsNumber) || idAsNumber < 1) {
      // all warehouse IDs are at least 1
      return false;
    }

    let indexInWarehouses = this.warehouses.findIndex((elem) => {
      return elem.id == idAsNumber;
    });

    return indexInWarehouses >= 0;
  }

  /**
   * Check the current mappings for issues
   * Return the translated issues, or empty string if none
   * @param successCallback action to take if ALL loading passes
   * @param failureCallBack action to take if ANY loading fails
   * @returns string
   */
  public validateMappings(
    successCallback?: () => void,
    failureCallBack?: (issues?: string) => void
  ): void {
    // need the latest warehouses
    this.loadWarehousesFromBackend(() => {
      let emptyFields = false;
      let duplicateKeys = false;
      let invalidWarehouse = false;
      let warehouseIdsSeen = [];
      this.warehouseSuppliers.forEach((item) => {
        emptyFields = emptyFields || !(item.warehouseId && item.supplierId);

        if (item.warehouseId) {
          if (this.isWarehouseIdValid(item.warehouseId)) {
            if (warehouseIdsSeen.includes(item.warehouseId)) {
              duplicateKeys = true;
            } else {
              warehouseIdsSeen.push(item.warehouseId);
            }
          } else {
            invalidWarehouse = true;
          }
        }
      });

      let buffer = "";
      if (emptyFields) {
        buffer += this.translation.translate("empty_fields");
      }
      if (duplicateKeys) {
        if (buffer.length > 0) {
          buffer += ". ";
        }
        buffer += this.translation.translate("duplicate_warehouses");
      }
      if (invalidWarehouse) {
        if (buffer.length > 0) {
          buffer += ". ";
        }
        buffer += this.translation.translate("warehouse_missing");
      }

      if (buffer) {
        buffer += ".";
        if (failureCallBack) {
          failureCallBack(buffer);
        }
      } else if (successCallback) {
        successCallback();
      }
    }, failureCallBack);
  }

  /**
   * Push the current state of the mappings to the plugin backend
   */
  public saveMappings(): void {
    this.clearMessage();

    this.validateMappings(
      () => {
        if (
          this.warehouseSuppliers.length > 0 ||
          this.removedWarehouseSuppliers.length > 0
        ) {
          this.status.type = "text-info";
          this.status.value = this.translation.translate("saving_status");
          let postData = this.removedWarehouseSuppliers.concat(
            this.warehouseSuppliers
          );
          this.warehouseSupplierService.postMappings(postData).subscribe(
            (data) => {
              // now that mappings were removed, clear out the "to remove" list
              this.removedWarehouseSuppliers = [];
              // warehouses and mappings may have been impacted by this save or by other actions,
              // including the actions made by other users
              this.loadEverythingFromBackend(() => {
                this.status.type = "text-info";
                this.status.value = this.translation.translate("saved");
              }, this.showSaveError);
            },
            (err) => {
              this.showSaveError;
            }
          );
        } else {
          // nothing to save, but warehouses and mappings may have changed in the backend
          this.loadEverythingFromBackend();
        }
      },
      (issues) => {
        // display save failure with reason(s)
        this.status.type = "text-danger";
        this.status.value = this.translation.translate("error_save") + ": " + issues;
      }
    );
  }

  /**
   * Add a new empty mapping row
   */
  public addMapping(): void {
    this.clearMessage();
    this.warehouseSuppliers.push({
      supplierId: null,
      warehouseId: null,
    });
  }

  /**
   * Mark a mapping for removal from the database, and hide it from view
   * @param warehouseSupplier a mapping
   */
  public removeMapping(warehouseSupplier: WarehouseSupplierInterface): void {
    this.clearMessage();
    if (!warehouseSupplier) {
      return;
    }

    let foundIndex = this.warehouseSuppliers.findIndex((elem) => {
      return (
        elem.supplierId == warehouseSupplier.supplierId &&
        elem.warehouseId == warehouseSupplier.warehouseId
      );
    });

    if (foundIndex < 0) {
      return;
    }

    let targetWarehouseSupplier = this.warehouseSuppliers[foundIndex];
    // update the view to no longer contain the row
    this.warehouseSuppliers.splice(foundIndex, 1);

    if (
      targetWarehouseSupplier.warehouseId ||
      targetWarehouseSupplier.supplierId
    ) {
      // mark for removal from the database by the backend
      targetWarehouseSupplier.removed = true;
      // keep record of this for the next save operation
      this.removedWarehouseSuppliers.push(targetWarehouseSupplier);
    }
  }
}
