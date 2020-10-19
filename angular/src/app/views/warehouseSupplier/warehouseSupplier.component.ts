import { Component, OnInit } from "@angular/core";
import { Language, TranslationService } from "angular-l10n";
import { WarehouseSupplierInterface } from "../..//core/services/warehouseSupplier/data/warehouseSupplier.interface";
import { WarehouseSupplierService } from "../../core/services/warehouseSupplier/warehouseSupplier.service";
import { WarehouseService } from "../../core/services/warehouse/warehouse.service";
import { fail } from "assert";

@Component({
  selector: "warehouse-supplier",
  template: require("./warehouseSupplier.component.html"),
  styles: [require("./warehouseSupplier.component.scss")],
})
export class WarehouseSupplierComponent implements OnInit {
  @Language()
  public lang: string;

  public warehouseSuppliers: Array<WarehouseSupplierInterface> = [];

  public removedWarehouseSuppliers: Array<WarehouseSupplierInterface> = [];

  public warehouses = [];

  public status = { type: null, value: null };

  constructor(
    private warehouseService: WarehouseService,
    private warehouseSupplierService: WarehouseSupplierService,
    private translation: TranslationService
  ) {}

  public ngOnInit(): void {
    this.loadEverythingFromBackend();
  }

  /**
   * update the status to show an error during fetch
   */
  private showFetchError(): void {
    this.status.type = "text-danger";
    this.status.value = this.translation.translate("error_fetch");
  }

  /**
   * update the status to show that the save passed
   */
  public showSaved(): void {
    this.status.type = "text-info";
    this.status.value = this.translation.translate("saved");
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
    this.clearMessage();

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

  /**
   * Check the current mappings for issues
   * Return the translated issues, or empty string if none
   * @returns string
   */
  public validateMappings(): string {
    let emptyFields = false;
    let duplicateKeys = false;
    let warehouseIds = [];
    this.warehouseSuppliers.forEach((item) => {
      emptyFields = emptyFields || !(item.warehouseId && item.supplierId);

      if (item.warehouseId) {
        if (warehouseIds.includes(item.warehouseId)) {
          duplicateKeys = true;
        } else {
          warehouseIds.push(item.warehouseId);
        }
      }
    });

    let buffer = "";
    if (emptyFields) {
      buffer += this.translation.translate("empty_fields");
    }
    if (duplicateKeys) {
      if (buffer.length > 0) {
        buffer += " ";
      }
      buffer += this.translation.translate("duplicate_warehouses");
    }

    return buffer;
  }

  /**
   * Push the current state of the mappings to the plugin backend
   */
  public saveMappings(): void {
    this.clearMessage();

    let validationResult = this.validateMappings();
    if (validationResult && validationResult.length > 0) {
      this.status.type = "text-danger";
      this.status.value = validationResult;
      return;
    }

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
  public removeMapping(warehouseSupplier): void {
    this.clearMessage();
    let foundIndex = this.warehouseSuppliers.findIndex((elem, idx) => {
      return (
        elem.supplierId == warehouseSupplier.supplierId &&
        elem.warehouseId == warehouseSupplier.warehouseId
      );
    });
    let targetWarehouseSupplier = this.warehouseSuppliers[foundIndex];
    if (
      targetWarehouseSupplier.supplierId &&
      targetWarehouseSupplier.warehouseId
    ) {
      targetWarehouseSupplier.removed = true;
      this.removedWarehouseSuppliers.push(targetWarehouseSupplier);
    }
    this.warehouseSuppliers.splice(foundIndex, 1);
  }
}
