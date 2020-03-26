import {Component} from '@angular/core';
import {FullInventoryService} from '../../core/services/fullInventory/fullInventory.service';
import {Language, TranslationService} from 'angular-l10n';

@Component({
    selector: 'fullInventory',
    template: require('./fullInventory.component.html')
})
export class FullInventoryComponent {
    @Language()
    public lang: string;

    public status = {type: null, value: null};

    public constructor(private fullInventoryService: FullInventoryService, private translation: TranslationService) {
    }

    public syncFullInventory(): void {
        this.fullInventoryService.syncFullInventory().subscribe(data => {
            this.status.type = 'text-info'
            this.status.value = this.translation.translate('synced')
        }, err => {
            this.status.type = 'text-danger'
            this.status.value = this.translation.translate('error_sync')
        })
    }
}
