jQuery(document).ready(function($) {
	
	if($('#woocommerce_integration-serwersms_username').length>0)
		$('#woocommerce_integration-serwersms_username').parents('form').addClass('serwersms');
	
	if($('#woocommerce_integration-serwersms_username').length>0)
		$('#woocommerce_integration-serwersms_username').prop('required',true);
		
	if($('#woocommerce_integration-serwersms_password').length>0)
		$('#woocommerce_integration-serwersms_password').prop('required',true);
		
	if($('#woocommerce_integration-serwersms_phone').length>0)
		$('#woocommerce_integration-serwersms_phone').prop('required',true);
		
		
	$('#woocommerce_integration-serwersms_reset').click(function() {
	
		var tr = $(this).parents('tr');
		if($(this).is(':checked')) {
			tr.nextAll('tr').hide();
		} else {
			tr.nextAll('tr').show();
		}
	});
	
	
});