import { Component } from "@angular/core";
import { Language } from "angular-l10n";
import { InventoryStatusInterface } from "../../core/services/inventory/data/inventoryStatus.interface";
import { InventoryService } from "../../core/services/inventory/inventory.service";

@Component({
  selector: "home",
  template: require("./home.component.html"),
  styles: [require("./home.component.scss")],
})
export class HomeComponent {
  @Language()
  public lang: string;

  public constructor(private inventoryService: InventoryService) {}

  public ngOnInit(): void {
    // attempt a single full inventory sync as this may be the first use of the plugin
    this.conditionallyPerformAFullInventorySync();
  }

  /**
   * Silently attempt to do a full inventory sync in case it is due
   */
  protected conditionallyPerformAFullInventorySync(): void {
    this.inventoryService.getState().subscribe(
      (data: InventoryStatusInterface) => {
        this.inventoryService.performFullSyncIfNeeded(data);
      },
      (err: any) => {
        // eat error
        // the inventory page is available for further sync attempts
      }
    );
  }
}
