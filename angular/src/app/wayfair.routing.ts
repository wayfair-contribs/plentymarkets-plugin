import { ModuleWithProviders } from '@angular/core';
import {
    RouterModule,
    Routes
} from '@angular/router';
import { HomeComponent } from './views/home/home.component';
import { RouterViewComponent } from './views/router/router-view.component';
import { WarehouseSupplierComponent } from './views/warehouseSupplier/warehouseSupplier.component';
import { SettingsComponent } from './views/settings/settings.component';
import { InventoryComponent } from './views/inventory/inventory.component';
import {CarrierScacMappingComponent} from "./views/carrierScacMapping/carrierScacMapping.component";

const appRoutes: Routes = [
    {
        path: '',
        redirectTo: 'plugin',
        pathMatch: 'full',
    },
    {
        path: 'plugin',
        component: RouterViewComponent,
        children: [
            {
                path: '',
                data: {
                    label: 'menu'
                },
                redirectTo: 'home',
                pathMatch: 'full'
            },
            {
                path: 'home',
                component: HomeComponent,
                data: {
                    label: 'Home'
                }
            },
            {
                path: 'warehouse',
                component: WarehouseSupplierComponent,
                data: {
                    label: 'Warehouse Supplier'
                }
            },
            {
                path: 'settings',
                component: SettingsComponent,
                data: {
                    label: 'Stock Buffer'
                }
            },
            {
                path: 'inventory',
                component: InventoryComponent,
                data: {
                    label: 'Inventory'
                }
            },
            {
                path: 'carrierScac',
                component: CarrierScacMappingComponent,
                data: {
                    label: 'Shipping carrier setting'
                }
            }
        ]
    },

];

export const appRoutingProviders: Array<any> = [];

export const routing: ModuleWithProviders =
    RouterModule.forRoot(appRoutes, { useHash: true });
