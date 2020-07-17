<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Tests\Mappers;

use Plenty\Modules\Item\VariationStock\Models\VariationStock;
use Wayfair\Core\Dto\Inventory\RequestDTO;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Services\ShipmentNotificationService;

final class ShipmentNotificationServiceTest extends \PHPUnit\Framework\TestCase {

  public function testPrepareRequestDto($order, $isWayfairShipping, $shippingProfile) {

  }

  public function dataProviderForPrepareRequestDto(){
    return [order, false, 'DHL']
  }
}
