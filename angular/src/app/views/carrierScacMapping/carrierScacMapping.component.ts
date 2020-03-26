import {
    Component,
    OnInit
} from '@angular/core';
import {Language, TranslationService} from 'angular-l10n';
import {CarrierService} from "../../core/services/carrier/carrier.service";
import {CarrierScacService} from "../../core/services/carrierScac/carrierScac.service";
import {ShippingMethodService} from "../../core/services/shippingMethod/shippingMethod.service";

@Component({
    selector: 'carrier-scac',
    template: require('./carrierScacMapping.component.html')
})
export class CarrierScacMappingComponent implements OnInit {
    @Language()
    public lang: string;
    public wfShipping = 'wayfair_shipping';
    public carrierScacs = [];
    public carriers = [];
    public status = {type: null, value: null};

    constructor(
        private carrierService: CarrierService,
        private carrierScacService: CarrierScacService,
        private translationService: TranslationService,
        private shippingMethodService: ShippingMethodService
    ) {
    }

    ngOnInit(): void {
        this.carrierService.fetchCarriers()
            .subscribe(data => {
                this.carriers = data.entries
            }, err => {
                this.showFetchError()
            })

        this.carrierScacService.fetchMappings()
            .subscribe(data => {
                this.updateCarrierScacs(data);
            }, err => {
                this.showFetchError()
            })

        this.shippingMethodService.fetch()
            .subscribe(data => {
                if (data.name) {
                    this.wfShipping = data.name;
                }
            }, err => {
                this.showFetchError()
            })
    }

    public save(): void {
        this.shippingMethodService.post(this.wfShipping)
            .subscribe(data => {
            }, error => {
                this.showMessage('text-danger', 'error_save')
            });

        let validation = true
        let obj = this.carrierScacs
        let carrierScacsArray = Object.entries(obj).map(function ([key, val]) {
            return {'carrierId': key, 'scac': val}
        })

        carrierScacsArray.forEach((item) => {
            if (Object.values(item).some(itemValue => !itemValue)) {
                this.showMessage('text-danger', 'empty_values_mapping')
                validation = false
            }
        })

        if (!validation) return;

        if (carrierScacsArray.length > 0) {
            this.showMessage('text-info', 'saving_status')
            this.carrierScacService.postMappings(carrierScacsArray)
                .subscribe(data => {
                    this.updateCarrierScacs(data);
                    this.showMessage('text-info', 'saved')
                }, err => {
                    this.showMessage('text-danger', 'error_save')
                })
        } else {
            this.showMessage('text-info', 'nothing_to_save')
        }
    }

    private updateCarrierScacs(data) {
        this.carrierScacs = data.reduce((acc, datum) => {
            return {...acc, [datum.carrierId]: datum.scac}
        }, {})
    }

    private showFetchError() {
        this.showMessage('text-danger', 'error_fetch')
    }

    private showMessage(type, messageKey) {
        this.status.type = type
        this.status.value = this.translationService.translate(messageKey)
    }
}
