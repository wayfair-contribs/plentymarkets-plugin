import { Component } from '@angular/core';
import { Language } from 'angular-l10n';

@Component({
    selector: 'router-view',
    template: require('./router-view.component.html'),
    styles: [require('./router-view.component.scss')]

})
export class RouterViewComponent
{
    @Language()
    public lang: string;
}
