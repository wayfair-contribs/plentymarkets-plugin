import { Component } from '@angular/core';
import { Language } from 'angular-l10n';

@Component({
    selector: 'menu',
    template: require('./menu.component.html'),
    styles:   [require('./menu.component.scss')],
})
export class MenuComponent
{
    @Language()
    public lang: string;
}
