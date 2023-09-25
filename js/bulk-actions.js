jQuery(document).ready(function ($) {
//Bulk select templates or folders
	$('body').on('click', '.clickToSelect', function() {
		// Delay the execution of the code inside setTimeout by 100 milliseconds.
		setTimeout(function() {
			if ($('.clickToSelect.selected').is(':visible')) {
				$('#bulkActionsSelect').attr('disabled', false);
			} else {
				$('#bulkActionsSelect').attr('disabled', true);
			}
		}, 100);
		
		if ($(this).hasClass('template')) {
			var objectType = 'template';
			$('.clickToSelect.folder.selected').closest('tr').removeClass('selected');
			$('.clickToSelect.folder.selected').find('i').toggle();
			$('.clickToSelect.folder.selected').removeClass('selected');
		} else if ($(this).hasClass('folder')) {
			var objectType = 'folder';
			$('.clickToSelect.template.selected').closest('tr').removeClass('selected');
			$('.clickToSelect.template.selected').find('i').toggle();
			$('.clickToSelect.template.selected').removeClass('selected');
		}
		$(this).find('i').toggle();
		$(this).toggleClass('selected');
		$(this).closest('tr').toggleClass('selected');
	});
	
	//on bulk option dropdown select
	$('#bulkActionsSelect').on('change', function() {
		
		var selectedOption = $(this).val();
		
		var selectedObjects = $('.clickToSelect.selected');
		if (selectedObjects.hasClass('folder')) {
			var objectType = 'folder';
		} else if (selectedObjects.hasClass('template')) {
			var objectType = 'template';
		} 
		
		id_do_bulk_action(selectedOption, objectType);
	});

	//do bulk actions
	function id_do_bulk_action(action, objectType) {

		// Select all elements with the class .clickToSelect.selected
			var selectedIds = [];
		$('.clickToSelect.selected').each(function() {
			// Get the closest tr parent and its data-folderid attribute
			selectedIds.push($(this).closest('tr').attr('data-objectid'));
			
		});
		//console.log(selectedIds);
		if (action == 'move') {
			if (objectType == 'folder') {
				id_move_folder(selectedIds);
			} else if ( objectType == 'template') {
				id_move_template(selectedIds);
			}
		} else if (action =='delete') {
			if (objectType == 'folder') {
				id_delete_folders(selectedIds);
			} else if ( objectType == 'template') {
				id_delete_templates(selectedIds);
			}
			
		} else if (action == 'restore') {
				id_restore_templates(selectedIds);
		}
	}
	
});