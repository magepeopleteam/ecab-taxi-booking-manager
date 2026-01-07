jQuery(document).ready(function($){
	$('form.checkout').on('change', 'input[type="file"][data-mptbm-file-upload]', function(e){
		var $input = $(this);
		var file = this.files[0];
		if (!file) return;
		var formData = new FormData();
		formData.append('action', 'mptbm_file_upload');
		formData.append('nonce', mptbmFileUpload.nonce);
		formData.append('file', file);
		$input.prop('disabled', true);
		$.ajax({
			url: mptbmFileUpload.ajax_url,
			type: 'POST',
			data: formData,
			contentType: false,
			processData: false,
			success: function(resp){
				$input.prop('disabled', false);
				if(resp.success && resp.data.url){
					// Store URL in hidden field
					var hidden = $input.siblings('input[type="hidden"][name="'+$input.attr('name')+'_url"]');
					if(hidden.length === 0){
						hidden = $('<input type="hidden" name="'+$input.attr('name')+'_url" />').insertAfter($input);
					}
					hidden.val(resp.data.url);
					// Show preview/link
					var preview = $input.siblings('.mptbm-file-preview');
					if(preview.length === 0){
						preview = $('<div class="mptbm-file-preview"></div>').insertAfter($input);
					}
					preview.html('<a href="'+resp.data.url+'" target="_blank">'+file.name+'</a>');
				}else{
					alert('Upload failed');
				}
			},
			error: function(){
				$input.prop('disabled', false);
				alert('Upload failed');
			}
		});
	});

	// On form submit, check if any file field's hidden field is empty
	$('form.checkout').on('submit', function(e){
		var missing = false;
		$('input[type="file"][data-mptbm-file-upload]').each(function(){
			var $input = $(this);
			var hidden = $input.siblings('input[type="hidden"][name="'+$input.attr('name')+'_url"]');
			if($input.val() && (!hidden.length || !hidden.val())){
				missing = true;
			}
		});
		if(missing){
			alert('Please wait for file upload to complete before submitting the form.');
			e.preventDefault();
		}
	});
}); 