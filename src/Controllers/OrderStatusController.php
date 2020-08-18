<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */
namespace Wayfair\Controllers;

use Plenty\Modules\Order\Status\Contracts\OrderStatusRepositoryContract;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Templates\Twig;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\TranslationHelper;

/**
 * Class OrderStatusController
 *
 * @package Wayfair\Controllers
 */
class OrderStatusController extends Controller {

  const LOG_KEY_CONTROLLER_IN = "controllerInput";
  const LOG_KEY_CONTROLLER_OUT = "controllerOutput";

  /**
   * @var LoggerContract
   */
  private $logger;

   /**
   * OrderStatusController constructor.
   *
   * @param LoggerContract $logger
   */
  public function __construct(LoggerContract $logger)
  {
    $this->logger = $logger;
  }

  /**
   * @param Twig $twig
   *
   * @return string
   */
  public function index(Twig $twig): string {
    $orderStatuses = '{a:1}';

    $data = $twig->render('Wayfair::content.orderStatus', ['orderStatuses' => $orderStatuses]);

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
      'additionalInfo' => ['payloadOut' => $data],
      'method'         => __METHOD__
    ]);

    return $data;
  }

  /**
   * @param Twig                        $twig
   * @param OrderStatusController $orderStatusRepositoryContract
   *
   * @return string
   */
  public function show(Twig $twig, OrderStatusRepositoryContract $orderStatusRepositoryContract): string {
    $orderStatuses = json_encode($orderStatusRepositoryContract->all());

    $data = $twig->render('Wayfair::content.orderStatus', ['orderStatuses' => $orderStatuses]);

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
      'additionalInfo' => ['payloadOut' => $data],
      'method'         => __METHOD__
    ]);

    return $data;
  }

  /**
   * @param OrderStatusRepositoryContract $orderStatusRepositoryContract
   *
   * @return string
   */
  public function fetch(OrderStatusRepositoryContract $orderStatusRepositoryContract): string {

    $data = json_encode($orderStatusRepositoryContract->all());

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
      'additionalInfo' => ['payloadOut' => $data],
      'method'         => __METHOD__
    ]);

    return $data;
  }
}
