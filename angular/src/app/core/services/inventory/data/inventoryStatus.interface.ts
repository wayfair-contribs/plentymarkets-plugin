import { InventoryStatusDetailsInterface } from "./inventoryStatusDetails.interface";

export interface InventoryStatusInterface
{
    status:string;
    details:InventoryStatusDetailsInterface;
}
