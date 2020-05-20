import { Component, OnInit } from "@angular/core";
import { Language, TranslationService } from "angular-l10n";
import { CarrierService } from "../../core/services/carrier/carrier.service";
import { CarrierScacService } from "../../core/services/carrierScac/carrierScac.service";
import { ShippingMethodService } from "../../core/services/shippingMethod/shippingMethod.service";

@Component({
  selector: "carrier-scac",
  template: require("./carrierScacMapping.component.html"),
})
export class CarrierScacMappingComponent implements OnInit {
  private static readonly SHIP_OPTION_WAYFAIR = "wayfair_shipping";

  @Language()
  public lang: string;
  public wfShipping = CarrierScacMappingComponent.SHIP_OPTION_WAYFAIR;
  public carrierScacs = [];
  public carriers = [];
  public status = { type: null, value: null };

  constructor(
    private carrierService: CarrierService,
    private carrierScacService: CarrierScacService,
    private translationService: TranslationService,
    private shippingMethodService: ShippingMethodService
  ) {}

  ngOnInit(): void {
    this.carrierService.fetchCarriers().subscribe(
      (data) => {
        this.carriers = data.entries;
      },
      (err) => {
        this.showFetchError();
      }
    );

    this.carrierScacService.fetchMappings().subscribe(
      (data) => {
        this.updateCarrierScacs(data);
      },
      (err) => {
        this.showFetchError();
      }
    );

    this.shippingMethodService.fetch().subscribe(
      (data) => {
        if (data.name) {
          this.wfShipping = data.name;
        }
      },
      (err) => {
        this.showFetchError();
      }
    );
  }

  /**
   * Save the shipping method,
   * Then save the carrier SCAC mappings if using "own shipping"
   */
  public save(): void {
    /* clear any old status message */
    this.showMessage("text-info", "saving_status");

    this.shippingMethodService.post(this.wfShipping).subscribe(
      (data) => {},
      (error) => {
        this.showMessage("text-danger", "error_save");
        return;
      }
    );

    if (this.wfShipping == CarrierScacMappingComponent.SHIP_OPTION_WAYFAIR) {
      /* Wayfair shipping ignores everything else on the page - early exit is ok. */
      this.showMessage("text-info", "saved");
      return;
    }

    /*
     * Using own account for shipping.
     * update back-end info on SCACS, even if the UI was cleared out!
     */
    let obj = this.carrierScacs;
    let carrierScacsArray = Object.entries(obj).map(function ([key, val]) {
      return { carrierId: key, scac: val };
    });

    /*
     * we do not want to send rows that have empty Wayfair SCACs.
     * The repository will be refreshed to match this array.
     */
    carrierScacsArray = carrierScacsArray.filter((row) =>
      this.validateScacRow(row)
    );

    this.carrierScacService.postMappings(carrierScacsArray).subscribe(
      (data) => {
        this.updateCarrierScacs(data);
      },
      (err) => {
        this.showMessage("text-danger", "error_save");
        return;
      }
    );

    this.showMessage("text-info", "saved");
  }

  private updateCarrierScacs(data) {
    this.carrierScacs = data.reduce((acc, datum) => {
      return { ...acc, [datum.carrierId]: datum.scac };
    }, {});
  }

  private showFetchError() {
    this.showMessage("text-danger", "error_fetch");
  }

  private showMessage(type, messageKey) {
    this.status.type = type;
    this.status.value = this.translationService.translate(messageKey);
  }

  /**
   * Make sure a row in the SCAC configuration is filled in
   * @param row
   */
  private validateScacRow(row) {
    return !Object.values(row).some((itemValue) => !itemValue);
  }

  private clearMessage() {
    this.status.type = null;
    this.status.value = null;
  }
}
