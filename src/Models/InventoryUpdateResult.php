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
    const KEY_FULL_INVENTORY = 'fullInventory';
    const KEY_DTOS_ATTEMPTED = 'dtosAttempted';
    const KEY_DTOS_SAVED = 'dtosSaved';
    const KEY_DTOS_FAILED = 'dtosFailed';
    const KEY_ELAPSED_TIME = 'elapsedTime';
    const KEY_VARIATIONS_ATTEMPTED = 'variationsAttempted';

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
    private $fullInventory;

    /** @var int */
    private $dtosAttempted;

    /** @var int */
    private $variationsAttempted;

    /** @var int */
    private $dtosSaved;

    /** @var int */
    private $dtosFailed;

    /** @var int */
    private $elapsedTime;

    public function setFullInventory(bool $fullInventory)
    {
        $this->fullInventory = $fullInventory;
    }

    public function getFullInventory(): bool
    {
        return $this->fullInventory;
    }

    public function setDtosAttempted(int $dtosAttempted)
    {
        $this->dtosAttempted = $dtosAttempted;
    }

    public function getDtosAttempted(): int
    {
        return $this->dtosAttempted;
    }

    public function setDtosSaved(int $dtosSaved)
    {
        $this->dtosSaved = $dtosSaved;
    }

    public function getDtosSaved(): int
    {
        return $this->dtosSaved;
    }

    public function setDtosFailed(int $dtosFailed)
    {
        $this->dtosFailed = $dtosFailed;
    }

    public function getDtosFailed(): int
    {
        return $this->dtosFailed;
    }

    public function setElapsedTime(int $elapsedTime)
    {
        $this->elapsedTime = $elapsedTime;
    }

    public function getElapsedTime(): int
    {
        return $this->elapsedTime;
    }

    public function setVariationsAttempted(int $variationsAttempted)
    {
        $this->variationsAttempted = $variationsAttempted;
    }

    public function getVariationsAttempted(): int
    {
        return $this->variationsAttempted;
    }

    public function toArray(): array
    {
        return [
            self::KEY_FULL_INVENTORY =>  $this->fullInventory,
            self::KEY_DTOS_ATTEMPTED => $this->dtosAttempted,
            self::KEY_DTOS_SAVED => $this->dtosSaved,
            self::KEY_DTOS_FAILED => $this->dtosFailed,
            self::KEY_ELAPSED_TIME => $this->elapsedTime,
            self::KEY_VARIATIONS_ATTEMPTED => $this->variationsAttempted
        ];
    }

    public function adoptArray(array $data)
    {
        if (!isset($data) || empty($data)) {
            return;
        }

        $this->setFullInventory($data[self::KEY_FULL_INVENTORY] ?? false);
        $this->setDtosAttempted($data[self::KEY_DTOS_ATTEMPTED] ?? 0);
        $this->setDtosSaved($data[self::KEY_DTOS_SAVED] ?? 0);
        $this->setDtosFailed($data[self::KEY_DTOS_FAILED] ?? 0);
        $this->setElapsedTime($data[self::KEY_ELAPSED_TIME] ?? 0);
        $this->setVariationsAttempted($data[self::KEY_VARIATIONS_ATTEMPTED] ?? 0);
    }

    public function isSuccessful()
    {
        return $this->variationsAttempted == 0 || $this->dtosFailed == 0;
    }
}
