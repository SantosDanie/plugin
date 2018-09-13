// wait unit the page and JQuery have loaded before running the code below
jQuery(document).ready(function($) {
	
	// setup our wp ajax URL
	var wpajax_url = document.location.protocol + '//' + document.location.host + '/BootstrapToWordpress/wp-admin/admin-ajax.php';

	// email capture action url
	var email_capture_url = wpajax_url + '?action=slb_save_subscription';

	$('form#slb-form').bind('submit', function() {

		// get the jquery form object
		$form = $(this);

		// setup our form data for our with ajax
		var form_data = $form.serialize();

		// submit our form data with ajax
		$.ajax({
			'method': 'post',
			'url': email_capture_url,
			'data': form_data,
			'dataType': 'json',
			'cache': false,
			'success': function(data, textStatus) {
				if (data.status == 1) {
					// success
					// reset the form
					$form[0].reset();
					// notify the user of success
					alert(data.message);
				} else {
					// error
					// begin building our error message text
					var msg = data.message + '\r' + data.error + '\r';

					$.each(data.errors, function (key, value) {
						// append each error on a new line
						msg += '\r';
						msg += '- ' + value;
					});

					// notify the user of the error
					alert(msg);
				}
			},
			'error': function(jqXHR, textStatus, errorThrown) {
				// ajax didn't work
			}
		});

		// stop the form from submitting normally
		return false;
	}); // End of the $('form#slb-form').bind('submit') function



	// email capture action url
	var unsubscribe_url = wpajax_url + '?action=slb_unsubscribe';

	$(document).on('submit', 'form#slb_manage_subscriptions_form', function() {

		// get the jquery form object
		$form = $(this);

		// setup our form data for our ajax post
		var form_data = $form.serialize();
		// submit our form data with ajax
		$.ajax({
			'method':'post',
			'url':unsubscribe_url,
			'data':form_data,
			'dataType':'json',
			'cache': false,
			'success': function(data, textStatus) {
				if (data.status == 1) {
					// alert(data);	
					// success
					// update form html
					$form.replaceWith(data.html);
					// $form[0].reset();

					// notyfy the user of success
					alert(data.message);
				} else {
					// error
					// begin building our error message text
					var msg = data.message + '\r' + data.error + '\r';

					alert(msg);
				}
			},
			'error': function(jqXHR, textStatus, errorThrown) {
				// ajax didn't work
			}
		});
		//  stop the formn from submitting normally
		return false; 

	}); // End of the $(ducument).on function



	// wp uploader
	// this adds Wordpress' file uploader to specially formatted html div.wp-uploader
	// here's an example of what the html should look like this ..
	/*
	<div class="wp-wploader">
		<input type="text" name="input_name" class="file-url regular-text" eccept="jpg|gif">
		<input type="hidden" name="input_name" class="file-id" value="0">
		<input type="button" name="upload-btn" class="upload-btn button-secondary" value="Upload">
	</div>
	*/ 
	$('.wp-wploader').each(function() {
		$uploader = $(this);

		$('.upload-btn', $uploader).click(function(e) {
			e.preventDefault();
			var file = wp.media({
				title: 'Upload',
				//mutiple: true if you wat to upload multiple file at once
				mutiple: false
			}).open()
			.on('selec', function(e) {
				// this will return the select image form the media Uploader, the result is an object
				var upload_file = file.state().get('selection').first();
				// we convert uploaded_image to a JSON object to make accessing it easier
				// Output to the console uploaded_image
				var file_url = uploaded_file.attributes.url;
				var file_id = uploaded_file.id;

				if ( $('.file-url').attr('accept') !== undefined ) {
					var filetype = $('.file-url', $uploader).attr('eccept');
					if ( filetype !== uploaded_file.attributes.subtype ) {
						$('.upload-text', $uploader).val('');
						alert('The file must be of type: '+ filetype);
					} else {
						// let's assign the url value to the input field
						$('.file-url', $uploader).val(file_url).trigger('change');
						$('.file-id', $uploader).val(file_id).trigger('change');

					}
				}
			});
		});
	});
	
	// setup variable to store our import forms jQuery objects
	$import_form_1 = $('#import_form_1', '#import_subscribers');
	$import_form_2 = $('#import_form_2', '#import_subscribers');

	// this event triggered when import_form_1 file is selected
	$('.file-id', $import_form_1).bind('changea', function() {
		alert('a csv file has been added successfully');
		// get the form data and serialize it
		var form_1_data = $import_form_1.serialize();
	});

}); // End of the jQuery(document) function