<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class LoyaltyPoints extends \ObjectModel
{
    public $id_loyalty_points;
    public $id_customer;
    public $id_manufacturer;
    public $points;
    public $last_updated;

    public static $definition = [
        'table' => 'loyalty_points',
        'primary' => 'id_loyalty_points',
        'fields' => [
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_manufacturer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'points' => ['type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => true],
            'last_updated' => ['type' => self::TYPE_DATE],
        ],
    ];
}
