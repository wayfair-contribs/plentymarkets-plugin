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
    this.showLoading();
    this.fullInventoryService.getState().subscribe(
      (data) => {
        this.refreshSateFromData(data);
      },
      (err) => {
        this.showFetchError();
      }
    );
  }

  /**
   *
   * @param data FullInventoryInterface
   */
  private refreshSateFromData(data: FullInventoryInterface) {
    let unknown = this.translation.translate("unknown");
    // service may not know last completion datestamp. Don't clear out a value if we already had one.
    
    this.setState(data.status);

    this.lastServiceCompletion = data.lastCompletion
      ? data.lastCompletion
      : this.lastServiceCompletion
      ? this.lastServiceCompletion
      : unknown;
  }

  private setState(messageKey) {
    this.serviceState = this.translation.translate(messageKey);
  }

  private showLoading() {
    this.setState("loading");
    this.lastServiceCompletion = this.lastServiceCompletion
      ? this.lastServiceCompletion
      : this.translation.translate("loading");
  }

  private showFetchError() {
    this.setState("error_fetch");
    this.lastServiceCompletion =
      this.lastServiceCompletion != this.translation.translate("loading")
        ? this.lastServiceCompletion
        : this.translation.translate("unknown");
  }
}
