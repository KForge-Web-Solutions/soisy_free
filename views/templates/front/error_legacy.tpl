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

    {capture name=path}{l s='Pay via Soisy' mod='soisy_free'}{/capture}

    <h2>{l s='Payment error' mod='soisy_free'}</h2>

    {assign var='current_step' value='payment'}
    {include file="$tpl_dir./order-steps.tpl"}

    <p class="warning">
        {l s='We noticed a problem with your order' mod='soisy_free'}<br/>
        <ul class="alert alert-danger">
            {foreach from=$soisy_free_errors item='error'}
                <li>{$error}</li>
            {/foreach}
        </ul>
        {l s='you can contact our' mod='soisy_free'}
        <a href="{$link->getPageLink('contact', true)|escape:'htmlall':'UTF-8'}">{l s='customer support' mod='soisy_free'}</a>.
    </p>
