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

<div class="row">
	<div class="col-xs-12 col-md-6">
		<p class="payment_module" id="soisy_free_payment_button">
			<a href="{$link->getModuleLink('soisy_free', 'redirect', array(), true)|escape:'htmlall':'UTF-8'}" title="{l s='Pay via Soisy' mod='soisy_free'}">
				<img src="{$module_dir|escape:'htmlall':'UTF-8'}/logo.png" alt="{l s='Pay via Soisy' mod='soisy_free'}" width="32" height="32" />
                {l s='Pay via Soisy (you will be redirected to soisy.it)' mod='soisy_free'}
			<soisy-loan-quote
                shop-id="{$shop_id}"
                amount="{$amount}"
                instalments="{$instalments}"
                zero-interest-rate="false"></soisy-loan-quote>
            </a>
		</p>
	</div>
</div>
