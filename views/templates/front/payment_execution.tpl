<h1>{l s='You will be redirected to the Privat24 site in a few seconds...' mod='privat24'}</h1>

<form id="privat24_payment" method="POST" action="{$payment_url}">
    <input type="hidden" name="amt" value="{$amount}">
    <input type="hidden" name="ccy" value="{$currency}">
    <input type="hidden" name="merchant" value="{$merchant_id}">
    <input type="hidden" name="order" value="{$order_id}">
    <input type="hidden" name="details" value="{$details}">
    <input type="hidden" name="ext_details" value="">
    <input type="hidden" name="pay_way" value="privat24">
    <input type="hidden" name="return_url" value="{$return_url}">
    <input type="hidden" name="server_url" value="{$link->getModuleLink('privat24', 'validation')}">
</form>
    
<script type="text/javascript">
    document.getElementById("privat24_payment").submit();
</script>
