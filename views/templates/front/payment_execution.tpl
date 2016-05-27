{*
* 2016 Soft Industry
*
*    @author Skorobogatko Alexei <a.skorobogatko@soft-industry.com>
*    @copyright 2016 Soft-Industry
*    @version $Id$
*}

<h1>{l s='You will be redirected to the Privat24 site in a few seconds...' mod='privat24'}</h1>

<form id="privat24_payment" method="POST" action="{$payment_url|escape:'htmlall':'UTF-8'}">
    <input type="hidden" name="amt" value="{$amount|escape:'htmlall':'UTF-8'}">
    <input type="hidden" name="ccy" value="{$currency|escape:'htmlall':'UTF-8'}">
    <input type="hidden" name="merchant" value="{$merchant_id|escape:'htmlall':'UTF-8'}">
    <input type="hidden" name="order" value="{$order_id|escape:'htmlall':'UTF-8'}">
    <input type="hidden" name="details" value="{$details|escape:'htmlall':'UTF-8'}">
    <input type="hidden" name="ext_details" value="">
    <input type="hidden" name="pay_way" value="privat24">
    <input type="hidden" name="return_url" value="{$return_url|escape:'htmlall':'UTF-8'}">
    <input type="hidden" name="server_url" value="{$link->getModuleLink('privat24', 'validation')|escape:'htmlall':'UTF-8'}">
</form>
    
<script type="text/javascript">
    document.getElementById("privat24_payment").submit();
</script>
