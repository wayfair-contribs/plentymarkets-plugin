import {
    APP_INITIALIZER,
    NgModule
} from '@angular/core';
import {BrowserModule} from '@angular/platform-browser';
import {WayfairAppComponent} from './wayfair-app.component';
import {MenuComponent} from './views/menu/menu.component';
import {HttpModule} from '@angular/http';
import {
    L10nLoader,
    TranslationModule
} from 'angular-l10n';
import {FormsModule} from '@angular/forms';
import {l10nConfig} from './core/localization/l10n.config';
import {HttpClientModule} from '@angular/common/http';
import {TerraComponentsModule} from '@plentymarkets/terra-components/app';
import {RouterModule} from '@angular/router';
import {
    appRoutingProviders,
    routing
} from './wayfair.routing';

// Components
import {HomeComponent} from './views/home/home.component';
import {RouterViewComponent} from './views/router/router-view.component';
import {WarehouseSupplierComponent} from './views/warehouseSupplier/warehouseSupplier.component';
import {SettingsComponent} from './views/settings/settings.component';
import {FullInventoryComponent} from './views/fullInventory/fullInventory.component';
import {TerraNodeTreeConfig} from '@plentymarkets/terra-components';

// Services
import {WarehouseSupplierService} from './core/services/warehouseSupplier/warehouseSupplier.service';
import {WarehouseService} from './core/services/warehouse/warehouse.service';
import {SettingsService} from './core/services/settings/settings.service';
import {FullInventoryService} from './core/services/fullInventory/fullInventory.service';
import {LocalHTTPInterceptor} from './core/interceptors/LocalHttpInterceptor';
import {CarrierScacMappingComponent} from "./views/carrierScacMapping/carrierScacMapping.component";
import {CarrierService} from "./core/services/carrier/carrier.service";
import {CarrierScacService} from "./core/services/carrierScac/carrierScac.service";
import {ShippingMethodService} from "./core/services/shippingMethod/shippingMethod.service";

@NgModule({
    imports: [
        BrowserModule,
        HttpModule,
        FormsModule,
        HttpClientModule,
        TranslationModule.forRoot(l10nConfig),
        RouterModule.forRoot([]),
        TerraComponentsModule.forRoot(),
        routing
    ],
    declarations: [
        WayfairAppComponent,
        RouterViewComponent,
        HomeComponent,
        MenuComponent,
        WarehouseSupplierComponent,
        SettingsComponent,
        FullInventoryComponent,
        CarrierScacMappingComponent
    ],
    providers: [
        {
            provide: APP_INITIALIZER,
            useFactory: initL10n,
            deps: [L10nLoader],
            multi: true
        },
        // {
        //     provide: HTTP_INTERCEPTORS,
        //     useClass: LocalHTTPInterceptor,
        //     multi: true,
        // },
        appRoutingProviders,
        TerraNodeTreeConfig,
        WarehouseSupplierService,
        WarehouseService,
        SettingsService,
        FullInventoryService,
        CarrierService,
        CarrierScacService,
        ShippingMethodService
    ],
    bootstrap: [
        WayfairAppComponent
    ]
})
export class WayfairPluginModule {
    constructor(public l10nLoader: L10nLoader) {
        this.l10nLoader.load();
    }
}

function initL10n(l10nLoader: L10nLoader): Function {
    return (): Promise<void> => l10nLoader.load();
}
