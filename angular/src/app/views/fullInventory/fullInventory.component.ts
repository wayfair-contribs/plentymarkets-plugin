import { Component } from "@angular/core";
import { FullInventoryInterface } from "../../core/services/fullInventory/data/fullInventory.interface";
import { FullInventoryService } from "../../core/services/fullInventory/fullInventory.service";
import { Language, TranslationService } from "angular-l10n";

@Component({
  selector: "fullInventory",
  template: require("./fullInventory.component.html"),
})
export class FullInventoryComponent {
  @Language()
  public lang: string;

  public serviceState = null;
  public lastServiceCompletion = null;

  public constructor(
    private fullInventoryService: FullInventoryService,
    private translation: TranslationService
  ) {}

  public ngOnInit(): void {
    this.refreshState();
  }

  public syncFullInventory(): void {
    this.setState("synchronizing");
    this.fullInventoryService.syncFullInventory().subscribe(
      (data) => {
        this.refreshSateFromData(data);
      },
      (err) => {
        this.setState("error_sync");
      }
    );
  }

  private refreshState(): void {
    this.setState("loading");
    this.fullInventoryService.getState().subscribe(
      (data) => {
        this.refreshSateFromData(data);
      },
      (err) => {
        this.setState("error_fetch");
      }
    );
  }

  /**
   *
   * @param data FullInventoryInterface
   */
  private refreshSateFromData(data: FullInventoryInterface) {
    let unknown = this.translation.translate("unknown");
    let datestamp = (this.serviceState = data.status ? data.status : unknown);

    // service may not know last completion datestamp. Don't clear out a value if we already had one.
    this.lastServiceCompletion = data.lastCompletion
      ? data.lastCompletion
      : this.lastServiceCompletion
      ? this.lastServiceCompletion
      : unknown;
  }

  private setState(messageKey) {
    this.serviceState = this.translation.translate(messageKey);
  }
}
