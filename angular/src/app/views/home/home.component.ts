import { Component } from '@angular/core';
import { Language } from 'angular-l10n';

@Component({
    selector: 'home',
    template: require('./home.component.html'),
    styles:   [require('./home.component.scss')],
})
export class HomeComponent
{
    @Language()
    public lang: string;
}
