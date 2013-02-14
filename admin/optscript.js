/*
 * Category list
 */

/**
 * Moves the category up in the list
 */
function qhebCatlistUp(id) {
	jQuery('#qheb-catlist-catid').val(id);
	jQuery('#qheb-catlist-direction').val('up');
	jQuery('#qheb-catorderform').submit();
}

/**
 * Moves the category down in the list
 */
function qhebCatlistDown(id) {
	jQuery('#qheb-catlist-catid').val(id);
	jQuery('#qheb-catlist-direction').val('down');
	jQuery('#qheb-catorderform').submit();
}

/**
 * Updates the permissions for the category:
 * Every permission below Everyone is set to the rights of Everyone.
 * Every permissions for custom groups are set to the rights of Registered users, if it was lower.
 */
function qhebCatpermsUpdate() {
	var everyonePerm = jQuery("input[id^=qheb-catperm-1-]:checked").val();
	var registeredPerm = jQuery("input[id^=qheb-catperm-2-]:checked").val();
	
	for(var i=0; i<4; i++) {
		jQuery('#qheb-catperm-2-'+i).prop('disabled', i<everyonePerm);
	}
	if (registeredPerm < everyonePerm) {
		jQuery('#qheb-catperm-2-'+everyonePerm).attr('checked','checked');
		registeredPerm = everyonePerm;
	}
	
	jQuery("input[id^=qheb-catperm-]:checked").each(function(){
		var rb = jQuery(this); 
		var id = rb.attr('id');
		var regs = id.match(/-(\d+)-(\d+)/);
		if (regs[1] > 2) {
			for(var i=0; i<4; i++) {
				jQuery('#qheb-catperm-'+regs[1]+'-'+i).prop('disabled', i<registeredPerm);
			}
			if (regs[2] < registeredPerm) {
				jQuery('#qheb-catperm-'+regs[1]+'-'+registeredPerm).attr('checked','checked');
			}
		}
	});
}

function qhebCatpermsInit() {
	qhebCatpermsUpdate();
	jQuery("input[id^=qheb-catperm-]").change(qhebCatpermsUpdate);
}

/*
 * User list
 */
function qhebUlistActchange() {
	act = jQuery('#qheb_ulist_action').val();
	if (act == 'addgroup' || act == 'removegroup') {
		jQuery('#qheb-ulist-group').show();
	} else {
		jQuery('#qheb-ulist-group').hide();
	}
}

/*
 * Badges
 */
function qhebBadgesInit() {
	jQuery('#upload_image_button').click(function() {
		formfield = jQuery('#upload_image').attr('name');
		tb_show('', 'media-upload.php?type=image&amp;TB_iframe=true');
		return false;
	});

	window.send_to_editor = function(html) {
		imgurl = jQuery('img',html).attr('src');
		jQuery('#upload_image').val(imgurl);
		tb_remove();
	}
}


jQuery(document).ready(function() {
	qhebBadgesInit();
	qhebCatpermsInit();
});

