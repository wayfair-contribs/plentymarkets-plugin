import {Component} from '@angular/core';
import {SettingsService} from '../../core/services/settings/settings.service';
import {Language, TranslationService} from 'angular-l10n';

@Component({
    selector: 'settings',
    template: require('./settings.component.html')
})
export class SettingsComponent {
    @Language()
    public lang: string;

    public status = {type: null, value: null};

    public stockBuffer = 0
    public defaultOrderStatus = null
    public defaultShippingProvider = null
    public defaultItemMappingMethod = null
    public importOrdersSince = null
    public isAllInventorySyncEnabled = null

    public constructor(private settingsService: SettingsService, private translation: TranslationService) {
    }

    public ngOnInit(): void {
        this.settingsService.fetch().subscribe(data => {
            this.stockBuffer = data.stockBuffer
            this.defaultOrderStatus = data.defaultOrderStatus
            this.defaultShippingProvider = data.defaultShippingProvider
            this.defaultItemMappingMethod = data.defaultItemMappingMethod
            this.importOrdersSince = data.importOrdersSince
            this.isAllInventorySyncEnabled = data.isAllInventorySyncEnabled
        }, err => {
            this.status.type = 'text-danger'
            this.status.value = this.translation.translate('error_fetch')
        })
    }


    public saveBuffer(): void {
        if (this.stockBuffer < 0 || isNaN(this.stockBuffer)) {
            this.status.type = 'text-danger'
            this.status.value = this.translation.translate('negative_not_allowed') + ' - ' + this.translation.translate('buffer')
            return;
        }
        if (this.defaultOrderStatus < 0 || isNaN(this.defaultOrderStatus)) {
            this.status.type = 'text-danger'
            this.status.value = this.translation.translate('negative_not_allowed') + ' - ' + this.translation.translate('order_status_id')
            return;
        }
        if (this.defaultShippingProvider < 0 || isNaN(this.defaultShippingProvider)) {
            this.status.type = 'text-danger'
            this.status.value = this.translation.translate('negative_not_allowed') + ' - ' + this.translation.translate('shipping_provider_id')
            return;
        }
        if (this.importOrdersSince) {
            this.importOrdersSince = new Date(this.importOrdersSince).toISOString().slice(0,10)
        }
        this.settingsService.save({
            stockBuffer: this.stockBuffer,
            defaultOrderStatus: this.defaultOrderStatus,
            defaultShippingProvider: this.defaultShippingProvider,
            defaultItemMappingMethod: this.defaultItemMappingMethod,
            importOrdersSince: this.importOrdersSince,
            isAllInventorySyncEnabled: this.isAllInventorySyncEnabled,
        }).subscribe(data => {
            this.isAllInventorySyncEnabled = data.isAllInventorySyncEnabled
            this.stockBuffer = data.stockBuffer
            this.defaultItemMappingMethod = data.defaultItemMappingMethod
            this.status.type = 'text-info'
            this.status.value = this.translation.translate('saved')
        }, err => {
            this.status.type = 'text-danger'
            this.status.value = this.translation.translate('error_save')
        })
    }
}
