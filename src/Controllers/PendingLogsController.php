<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Wayfair\Repositories\PendingLogsRepository;
use Wayfair\Services\SendLogsService;

class PendingLogsController extends Controller {

  /**
   * @return array
   * @throws \Exception
   */
  public function showAll()
  {
    $pendingLogsRepository = pluginApp(PendingLogsRepository::class);
    return $pendingLogsRepository->getAll();
  }

  /**
   * @param Request $request
   *
   * @return string
   */
  public function delete(Request $request)
  {
    $ids = $request->input('ids');
    $pendingLogsRepository = pluginApp(PendingLogsRepository::class);
    return $pendingLogsRepository->delete($ids) ? 'Done' : 'Error';
  }

  /**
   * @param Request $request
   *
   * @return string
   */
  public function deleteAll(Request $request)
  {
    $pendingLogsRepository = pluginApp(PendingLogsRepository::class);
    $pendingLogsRepository->deleteAll();
    return 'Done';
  }

  /**
   * @return string
   */
  public function insert()
  {
    return 'Done';
  }

  /**
   * @return string
   */
  public function sendLogs()
  {
    $sendLogsService = pluginApp(SendLogsService::class);
    $sendLogsService->process();
    return 'Done';
  }
}
