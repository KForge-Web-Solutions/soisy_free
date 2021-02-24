{**
 * 2007-2020 KForge
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
 *}

<section>
  
    <soisy-loan-quote
        shop-id="{$shop_id}"
        amount="{$amount}"
        instalments="{$instalments}"
        zero-interest-rate="false"></soisy-loan-quote>
    <p>
      {l s='Soisy, the private lending platform that allows you to buy your products in installments. ' mod='soisy_free'}<br>
      {l s='By clicking on the button below the order will be closed and you will be redirected to the Soisy website for the financing request.' mod='soisy_free'}
  </p>
</section>
