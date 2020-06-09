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

    public status = {type: null, value: null, timestamp: null};

    public constructor(private fullInventoryService: FullInventoryService, private translation: TranslationService) {
    }

    public syncFullInventory(): void {
        this.showInfo('synchronizing');
        this.fullInventoryService.syncFullInventory().subscribe(data => {
            this.showInfo(data.status);
        }, err => {
            this.showFailure('error_sync');
        })
    }

    private showMessage(type, messageKey, timestamp = Date().toLocaleString()) {
        this.status.type = type;
        this.status.value = this.translation.translate(messageKey);
        this.status.timestamp = timestamp;
    }

    private showInfo(messageKey)
    {
        this.showMessage('text-info', messageKey);
    }

    private showFailure(messageKey)
    {
        this.showMessage('text-danger', messageKey);
    }
}
