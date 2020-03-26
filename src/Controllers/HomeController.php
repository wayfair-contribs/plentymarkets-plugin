<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Templates\Twig;

/**
 * Class HomeController
 *
 * @package Wayfair\Controllers
 */
class HomeController extends Controller {

  /**
   * @param Twig $twig
   *
   * @return string
   */
  public function index(Twig $twig): string {
    return $twig->render('Wayfair::content.home');
  }

}
