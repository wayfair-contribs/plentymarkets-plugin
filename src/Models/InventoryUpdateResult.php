<?php

/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

/**
 * Class InventoryUpdateResult
 *
 * @package Wayfair\Models
 */
class InventoryUpdateResult
{
    public function __construct(
        bool $fullInventory = false,
        int $dtosAttempted = 0,
        int $variationsAttempted = 0,
        int $dtosSaved = 0,
        int $dtosFailed = 0,
        int $elapsedTime = 0
    ) {
        $this->fullInventory = $fullInventory;
        $this->dtosAttempted = $dtosAttempted;
        $this->variationsAttempted = $variationsAttempted;
        $this->dtosSaved = $dtosSaved;
        $this->dtosFailed = $dtosFailed;
        $this->elapsedTime = $elapsedTime;
    }

    /** @var bool */
    public $fullInventory;

    /** @var int */
    public $dtosAttempted;

    /** @var int */
    public $variationsAttempted;

    /** @var int */
    public $dtosSaved;

    /** @var int */
    public $dtosFailed;

    /** @var int */
    public $elapsedTime;

    public function toArray(): array
    {
        return [
            'fullInventory' =>  $this->fullInventory,
            'dtosAttempted' => $this->dtosAttempted,
            'dtosSaved' => $this->dtosSaved,
            'dtosFailed' => $this->dtosFailed,
            'elapsedTime' => $this->elapsedTime,
            'variationsAttempted' => $this->variationsAttempted
        ];
    }

    public function isSuccessful()
    {
        return $this->variationsAttempted == 0 || $this->dtosFailed == 0;
    }
}
