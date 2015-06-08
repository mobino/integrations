
{extends file="frontend/checkout/confirm.tpl"}

{block name='frontend_index_content_left'}{/block}

{* Javascript *}
{block name="frontend_index_header_javascript" append}
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
<script src="https://app.mobino.com/merchants/api/v1/mobino.js"></script>
<script src="https://app.mobino.com/merchants/javascripts/sha1.js"></script>
<link rel="stylesheet" type="text/css" href="https://app.mobino.com/merchants/css/widget_example_style.css" media="all">
<link href="https://fonts.googleapis.com/css?family=Imprima|Roboto" rel="stylesheet" type="text/css">
<div id="payment" class="grid_20" style="margin:10px 0 10px 20px;width:959px;">
<script>
    function fireEvent(element,event) {
       if (document.createEvent) {
           // dispatch for firefox + others
           var evt = document.createEvent("HTMLEvents");
           evt.initEvent(event, true, true ); // event type,bubbling,cancelable
           return !element.dispatchEvent(evt);
       } else {
           // dispatch for IE
           var evt = document.createEventObject();
           return element.fireEvent('on'+event,evt)
       }
    }

    function successFunction() {
        window.location.replace("/payment_mobino/result")
    };

    function closeFunction() {
        window.location.replace("/checkout/confirm")
    };

    /*
     * create a payment button on the div whose id is 'mobino_payment'
     */
    var userLang = navigator.language || navigator.userLanguage; 
    Mobino.createButton('mobino_payment', {
        lang: userLang,
        popup: true, // immediately open the popup widget
        success_callback: successFunction,
        close_callback: closeFunction,
        api_key: '{$apikey}',
        transaction: {
            amount: "{$amount}",
            currency: "{$currency}",
            reference_number: "{$refnum}",
            nonce: "{$nonce}",
            signature: "{$signature}"
        }
    });
</script>

{/block}

{* Main content *}
{block name="frontend_index_content"}
    <div id="mobino_payment"></div>

    <div id="payment_loader" class="ajaxSlider" style="height:100px;border:0 none;display:none">
    	<div class="loader" style="width:80px;margin-left:-50px;">{s name="PaymentInfoWait"}Bitte warten...{/s}</div>
    </div>
<div class="doublespace">&nbsp;</div>
{/block}
