// JavaScript/jQuery code for the multistep functionality
jQuery(document).ready(function ($) {
    function changecolour() {
        var elements = $(".is_dark_mode");
        
        if($('body').hasClass('dark-mode')){
            elements.each(function() {
                var color = $(this).css('background-color');
              
                    if (color === 'rgb(255, 255, 255)') { // Check for white color in RGB format
                        $(this).css('background-color', 'black');
                        $(this).css('color', 'white');
                        var siblingTds = $(this).siblings('td');
                        siblingTds.css('background-color', 'black');
                        siblingTds.css('color', 'white');
                    }
                });
        } else{
            elements.each(function() {
                var color = $(this).css('background-color');
                console.log(color);
                    if(color === 'rgb(0, 0, 0)') {
                        $(this).css('background-color', 'white');
                        $(this).css('color', 'black');
                        var siblingTds = $(this).siblings('td');
                        siblingTds.css('background-color', 'white');
                        siblingTds.css('color', 'black');
                    }
                });
          
        }
     
    }
    changecolour();
    $(document).on("change",".toggle-dark-mode",function(){
        let check_value = $(this).is(":checked");
        if(check_value){
            $('body').addClass('dark-mode')
        }else{
            $('body').removeClass('dark-mode')
        }
        
        $.ajax({
			url: ajax_object.ajax_url,
			type: "POST",
			data: {
				action: "save_dark_mode_settings",
				data: {
                    check_value: check_value,
                }
			},
			dataType: "json",
			success: function (response) {
                changecolour();
			},
		});
    });
   
});

