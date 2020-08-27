<?php 
$payg_url = "https://uat.payg.in/ProcessPayment/EcomPayment/ProcessPayment";
$SecureHashKey='1e612caab7194578bdb821d72ae7512e';
$MerchantKeyId = $_POST['MerchantKeyId']; 
$post = $_POST;
$return_elements = array();

//this need to be same order for hashing works
$txn_details = $SecureHashKey.'|'.$post['MerchantKeyId'] . '|' . $post['RequestUniqueId'] . '|' . $post['AuthenticationKey'] . '|' . $post['AuthenticationToken'] . '|' . $post['Amount'] ;
$return_elements['txn_details'] =hash("sha256",$txn_details);

// html redirect to PayG
if (isset($return_elements)) {
	echo '<HTML>
<HEAD>
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>

</HEAD>
<BODY>
  <form class="form-horizontal" id="payg_payment_form" action="' . $payg_url . '" method="POST">
 
  <input type="hidden" class="form-control"  id="" value="' . $post['payment_url'] . '" /><br/>
    
    <input type="hidden" class="form-control" name="MerchantKeyId" id="" value="' . $post['MerchantKeyId'] . '" /><br/>
    
    <input type="hidden" class="form-control" name="AuthenticationKey" id="" value="' . $post['AuthenticationKey'] . '" /><br/>
     
    <input type="hidden" class="form-control" name="AuthenticationToken" id="" value="' . $post['AuthenticationToken'] . '" /><br/>
   
    <input type="hidden" class="form-control" name="RequestUniqueId" id="" value="' . $post['RequestUniqueId'] . '" /><br/>
    
    <input type="hidden" class="form-control" name="PaymentType" id="" value="' . $post['PaymentType'] . '" /><br/>
    
    <input type="hidden" class="form-control" name="Amount" id="" value="' . $post['Amount'] . '" /><br/>
    
    <input type="hidden" class="form-control" name="ResponseRedirectUrl" id="" value="' . $post['ResponseRedirectUrl'] . '" /><br/>
    
    <input type="hidden" class="form-control" name="CancelRedirectUrl" id="" value="' . $post['CancelRedirectUrl'] . '" /> <br/>
     
    <input type="hidden" class="form-control" name="HashData" id="" value="' . $return_elements['txn_details'] . '" /><br/>
    
    <input type="hidden" class="form-control" name="RequestDateTime" value="'.date("d/m/Y h:m:i").'" /> <br/>
     
    <input type="hidden" class="form-control" name="UserName" value="praveen@inducocial.com" />  <br/>
    
    <input type="hidden" class="form-control" name="OrderId" id="" value="' . $post['OrderId'] . '" /><br/>
    
    <input type="hidden" class="form-control" name="CustomerEmail" id="" value="' . $post['CustomerEmail'] . '" /> <br/>
    
    <input type="hidden" class="form-control" name="TransactionType" id="" value="' . $post['TransactionType'] . '" /> <br/>
    
    <input type="hidden" class="form-control" name="IntegrationType" id="" value="0" /> <br/>
    <input type="hidden" class="form-control" name="RefTransactionId" id="" value="' . $post['RefTransactionId'] . '" /> <br/> 
    </form>
    <div class="container cs-border-light-blue">

    <!-- first line -->
    <div class="row pad-top"></div>
    <!-- end first line -->

    <div class="equalheight row" style="padding-top: 10px;">
      <div id="cs-main-body" class="cs-text-size-default pad-bottom">
        <div class="col-sm-9  equalheight-col pad-top">
          <div style="padding-bottom: 50px;">
            <h1>Initiating your payment process</h1>
            <div class="row">
              <div class="col-sm-12">
                <legend>Your payment is being processed, Please wait for a moment.</legend>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  </div>

</BODY>
</HTML>
<script type="text/javascript">
$(document).ready(function(e) {
    $("#payg_payment_form").submit();
});
</script>';
} else {
	echo "no data found";
}

?>
