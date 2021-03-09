<?php
/**
 * 2007-2021 KForge
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@kforge.it so we can send you a copy immediately.
 *
 * @author    KForge snc <info@kforge.it>
 * @copyright 2007-2021 KForge snc
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'soisy_free` (
    `id_soisy_free` int(11) NOT NULL AUTO_INCREMENT,
    `id_shop` int(10) NULL,
    `id_cart` int(10) UNSIGNED NOT NULL,
    `id_customer` INT NOT NULL,
    `order_reference` char(18) NOT NULL,
    `token` varchar(255) NOT NULL,
    `total_cart` decimal(10,2) NOT NULL,
    `total_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
    `payment_status` varchar(255) NOT NULL DEFAULT \'\',
    `sandbox` tinyint(1) UNSIGNED NOT NULL,
    `created_at` datetime NOT NULL,
    `updated_at` timestamp NOT NULL DEFAULT \'0000-00-00 00:00:00\' ON UPDATE current_timestamp(),
    PRIMARY KEY  (`id_soisy_free`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

