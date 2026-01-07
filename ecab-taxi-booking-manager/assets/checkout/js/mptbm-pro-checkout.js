(function ($) {
	"use strict";
	function init()
	{
		const tabItems = document.querySelectorAll('.mpStyles .checkout .tab-item');
		const tabContents = document.querySelectorAll('.mpStyles .checkout .tab-content');

		tabItems.forEach((tabItem) => {
		tabItem.addEventListener('click', () => {

			tabItems.forEach((item) => {
			item.classList.remove('active');
			});
			tabContents.forEach((content) => {
			content.classList.remove('active');
			});

			const target = tabItem.getAttribute('data-tabs-target');
			tabItem.classList.add('active');
			document.querySelector(target).classList.add('active');
			
			window.location.hash = target;
		});
		});

		const currentHash = window.location.hash;

		if(currentHash.length > 0)
		{
			tabItems.forEach((tabItem) => {
				tabItem.classList.remove('active');
			});

			tabContents.forEach((tabContent) => {
				tabContent.classList.remove('active');
			});

			tabItems.forEach((tabItem) => {
			const target = tabItem.getAttribute('data-tabs-target');
			if (target === currentHash) {
				tabItem.classList.add('active');
			}
			});

			tabContents.forEach((tabContent) => {
			if (tabContent.getAttribute('id') === currentHash.substring(1)) {
				tabContent.classList.add('active');
			}
			});
		}

	}

	function reset_type_select()
	{
		var option = { 'text' : "text", 'select' : "select", 'file' : "file" };
		$('.mpStyles .checkout select#type option').remove();
		$.each(option, function(key, value) {	
			$('.mpStyles .checkout select#type').append($("<option></option>").attr("value",key).text(value)); 
		});
		$('.mpStyles .checkout select#type').prop('disabled', false);
		type_rendering($('.mpStyles .checkout select#type').find(":selected").val(),$('.mpStyles .checkout .open-modal').data('action'));
	}

	function type_rendering(type,action,field=null)
	{
		if(type == 'text') 
		{
			prepare_text(action,field);
		}
		else if(type == 'select') 
		{
			prepare_select(action,field);
		}
		else if(type == 'file') 
		{
			prepare_file(action,field);
		}
		else
		{
			prepare_other(action,field);
		}
	}

	function prepare_text(action,field=null)
	{
		if(action === 'add')
		{
			$('.mpStyles .checkout .custom-var-attr-section').empty();
			$('.mpStyles .checkout .custom-var-attr-section').html(
				
				'<label for="placeholder">Placeholder:</label>'+
				'<input type="text" name="placeholder" id="placeholder">'
			
			);
		}
		else if(action === 'edit')
		{
			$('.mpStyles .checkout .custom-var-attr-section').empty();
			$('.mpStyles .checkout .custom-var-attr-section').html(
				
				'<label for="placeholder">Placeholder:</label>'+
				'<input type="text" name="placeholder" id="placeholder">'
			
			);
			$('.mpStyles .checkout input[name="placeholder"]').val(field.attributes.placeholder);
		}
		
	}

	function prepare_select(action,field=null)
	{
		if(action === 'add')
		{
			$('.mpStyles .checkout .custom-var-attr-section').empty();
			$('.mpStyles .checkout .custom-var-attr-section').html(
				
				'<table>'+
					'<tbody class="ui-sortable">'+
						'<tr>'+
							'<td>'+
								'<div class="option-row">'+
									'<div class="input-cell">'+
										'<input type="text" name="option_value[]" placeholder="Option Value">'+
									'</div>'+
									'<div class="input-cell">'+
										'<input type="text" name="option_text[]" placeholder="Option Text">'+
									'</div>'+
									'<div class="action-cell">'+
										'<a class="action-plus" href="javascript:void(0)" onclick="thwcfdAddNewOptionRow(this)" title="Add option"><i class="dashicons dashicons-plus-alt2"></i></a>'+
										'<a class="action-minus" href="javascript:void(0)" onclick="thwcfdRemoveOptionRow(this)" title="Remove option"><i class="dashicons dashicons-minus"></i></a>'+
										'<a class="action-move sort ui-sortable-handle" href="javascript:void(0)" title="Move option"><i class="dashicons dashicons-move"></i></a>'+
									'</div>'+
								'</div>'+
							'</td>'+
						'</tr>'+
					'</tbody>'+
				'</table>'
			
			);

		}
		else if(action === 'edit')
		{
			let html = "";
			if ( typeof field.attributes.options === 'object' && !Array.isArray(field.attributes.options) && field.attributes.options !== null ) 
			{
				html += ('<table>'+
							'<tbody class="ui-sortable">');
				for (const [key, value] of Object.entries(field.attributes.options)) 
				{
					html += ('<tr>'+
								'<td>'+
									'<div class="option-row">'+
										'<div class="input-cell">'+
											'<input type="text" name="option_value[]" placeholder="Option Value" value="'+ key +'">' +
										'</div>'+
										'<div class="input-cell">'+
											'<input type="text" name="option_text[]" placeholder="Option Text" value="'+ value +'">' +
										'</div>'+
										'<div class="action-cell">'+
											'<a class="action-plus" href="javascript:void(0)" onclick="thwcfdAddNewOptionRow(this)" title="Add option"><i class="dashicons dashicons-plus-alt2"></i></a>'+
											'<a class="action-minus" href="javascript:void(0)" onclick="thwcfdRemoveOptionRow(this)" title="Remove option"><i class="dashicons dashicons-minus"></i></a>'+
											'<a class="action-move sort ui-sortable-handle" href="javascript:void(0)" title="Move option"><i class="dashicons dashicons-move"></i></a>'+
										'</div>'+
									'</div>'+
								'</td>'+
							'</tr>');
				}

				html += ('</tbody>'+
					'</table>');
			}

			$('.mpStyles .checkout .custom-var-attr-section').empty();
			$('.mpStyles .checkout .custom-var-attr-section').html(html);

		}

		$(".ui-sortable").sortable();
	}

	function prepare_file(action,field=null)
	{
		if(action === 'add')
		{
			$('.mpStyles .checkout input[name="validate"]').val('');
			$('.mpStyles .checkout .custom-var-attr-section').empty();
			$('.mpStyles .checkout .custom-var-attr-section').html(
				
				'<label for="placeholder">Placeholder:</label>'+
				'<input type="text" name="placeholder" id="placeholder">'
			
			);
		}
		else if(action === 'edit')
		{
			$('.mpStyles .checkout .custom-var-attr-section').empty();
			$('.mpStyles .checkout .custom-var-attr-section').html(
				
				'<label for="placeholder">Placeholder:</label>'+
				'<input type="text" name="placeholder" id="placeholder">'
			
			);
			$('.mpStyles .checkout input[name="placeholder"]').val(field.attributes.placeholder);
		}
		
	}

	function prepare_other(action,field=null)
	{
		if(action === 'add')
		{
			$('.mpStyles .checkout .custom-var-attr-section').empty();
			$('.mpStyles .checkout .custom-var-attr-section').html(
				
				'<label for="placeholder">Placeholder:</label>'+
				'<input type="text" name="placeholder" id="placeholder">'
			
			);
		}
		else if(action === 'edit')
		{
			$('.mpStyles .checkout .custom-var-attr-section').empty();
			$('.mpStyles .checkout .custom-var-attr-section').html(
				
				'<label for="placeholder">Placeholder:</label>'+
				'<input type="text" name="placeholder" id="placeholder">'
			
			);
			$('.mpStyles .checkout input[name="placeholder"]').val(field.attributes.placeholder);
		}
		
	}

	$(document).ready(
		function()
		{
			init();
			$('.mpStyles .checkout .open-modal').click(function() {
				$('.mpStyles .checkout #field-modal').css('display', 'block');
				$('.mpStyles .checkout #field-modal input[name="action"]').val($(this).data('action'));
				$('.mpStyles .checkout #field-modal input[name="key"]').val($(this).data('key'));
				if($(this).data('action')=='add')
				{
					$('.mpStyles .checkout .edit-nonce input').prop("disabled", true);
					$('.mpStyles .checkout .add-nonce input').prop("disabled", false);
					$('.mpStyles .checkout input[name="old_name"]').val('');
					$('.mpStyles .checkout input[name="new_name"]').val('');
					$('.mpStyles .checkout input[name="new_type"]').val('');
					$('.mpStyles .checkout input[name="name"]').val('');
					$('.mpStyles .checkout input[name="name"]').prop('disabled', false);					
					reset_type_select();
					$('.mpStyles .checkout input[name="label"]').val('');
					$('.mpStyles .checkout input[name="priority"]').val('');
					$('.mpStyles .checkout input[name="class"]').val('');
					$('.mpStyles .checkout input[name="validate"]').val('');
					$('.mpStyles .checkout input[name="required"]').prop('checked',true);
					$('.mpStyles .checkout input[name="disabled"]').prop('checked',false);
					type_rendering($('.mpStyles .checkout select#type').find(":selected").val(),$('.mpStyles .checkout #field-modal input[name="action"]').val());					
				}
				else if($(this).data('action')=='edit')
				{
					$('.mpStyles .checkout .edit-nonce input').prop("disabled", false);
					$('.mpStyles .checkout .add-nonce input').prop("disabled", true);
					let field = JSON.parse($('input[name="'+$(this).data('name')+'"]').val());
					$('.mpStyles .checkout input[name="old_name"]').val(field.name);
					$('.mpStyles .checkout input[name="new_name"]').val(field.name);
					$('.mpStyles .checkout input[name="name"]').val(field.name);
					$('.mpStyles .checkout input[name="name"]').prop('disabled', true);
					var option = new Option(field.attributes.type, field.attributes.type,'1','1');
					$('.mpStyles .checkout select#type').append(option);
					$('.mpStyles .checkout select#type').prop('disabled', true);
					$('.mpStyles .checkout input[name="new_type"]').val(field.attributes.type);
					$('.mpStyles .checkout input[name="label"]').val(field.attributes.label);
					$('.mpStyles .checkout input[name="priority"]').val(field.attributes.priority);
					$('.mpStyles .checkout input[name="class"]').val(field.attributes.class);
					$('.mpStyles .checkout input[name="validate"]').val(field.attributes.validate);					
					$('.mpStyles .checkout input[name="required"]').prop('checked',field.attributes.required == 1?true:false);
					$('.mpStyles .checkout input[name="disabled"]').prop('checked',field.attributes.disabled == 1?true:false);
					type_rendering($('.mpStyles .checkout input[name="new_type"]').val(),$('.mpStyles .checkout #field-modal input[name="action"]').val(),field);					
				}
			});

			$('.mpStyles .checkout select#type').on('change', function() {
				type_rendering(this.value,$('.mpStyles .checkout .open-modal').data('action'));
			});

			$(".ui-sortable").sortable();

			function thwcfdAddNewOptionRow(button) {
				var $row = $(button).closest("tr");
				var $clone = $row.clone();
				$row.after($clone);
			}

			function thwcfdRemoveOptionRow(button) {
				var rowCount = $(".ui-sortable tr").length;
				if (rowCount > 1) {
					$(button).closest("tr").remove();
				}
			}

			window.thwcfdAddNewOptionRow = thwcfdAddNewOptionRow;
			window.thwcfdRemoveOptionRow = thwcfdRemoveOptionRow;

			$('.mpStyles .checkout .close,.mpStyles .checkout .modal').click(function() {
				$('.mpStyles .checkout #field-modal').css('display', 'none');
			});

			$('.mpStyles .checkout .modal-content').click(function(e) {
				e.stopPropagation();
			});

			$('.mpStyles .checkout .checkoutSwitchButton').on('change', function() {
				var element = $(this);
				var key = $(this).data('key');
				var name = $(this).data('name');
				var isChecked = this.checked;
				
				$.ajax({
					type: 'POST',
					url: mp_ajax_url,
					data: { 
						action: "mptbm_disable_field",
						key: key,
						name: name,
						isChecked: isChecked,
						nonce: mptbm_checkout_object.nonce 
					},
					success: function(response) {
						var jsonResponse = response;
						if (jsonResponse == 'success') 
						{
							element.prop('checked', isChecked);
						}
						else
						{
							element.prop('checked', !isChecked);
						}

						if(isChecked)
						{
							element.closest('tr').find('td .checkout-disabled').removeClass("dashicons dashicons-yes tips");
						}
						else
						{
							element.closest('tr').find('td .checkout-disabled').addClass("dashicons dashicons-yes tips");
						}
					}
				});
				
			});

			// Handle form submission
			$('.mpStyles .checkout form').on('submit', function(e) {
				// Make sure nonces are properly enabled based on the action
				if ($('.mpStyles .checkout #field-modal input[name="action"]').val() == 'add') {
					$('.mpStyles .checkout .edit-nonce input').prop("disabled", true);
					$('.mpStyles .checkout .add-nonce input').prop("disabled", false);
				} else if ($('.mpStyles .checkout #field-modal input[name="action"]').val() == 'edit') {
					$('.mpStyles .checkout .edit-nonce input').prop("disabled", false);
					$('.mpStyles .checkout .add-nonce input').prop("disabled", true);
				}
			});

		}
	);
}(jQuery));

