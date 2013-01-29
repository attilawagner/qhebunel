/*
 * Category list
 */

/**
 * Moves the category up in the list
 */
function qheb_catlist_up(id) {
	jQuery('#qheb_catlist_catid').val(id);
	jQuery('#qheb_catlist_direction').val('up');
	jQuery('#qheb_catorderform').submit();
}

/**
 * Moves the category down in the list
 */
function qheb_catlist_down(id) {
	jQuery('#qheb_catlist_catid').val(id);
	jQuery('#qheb_catlist_direction').val('down');
	jQuery('#qheb_catorderform').submit();
}

/**
 * Updates the permissions for the category:
 * Every permission below Everyone is set to the rights of Everyone.
 * Every permissions for custom groups are set to the rights of Registered users, if it was lower.
 */
function qheb_catperms_update() {
	var everyonePerm = jQuery("input[id^=qheb_catperm_1_]:checked").val();
	var registeredPerm = jQuery("input[id^=qheb_catperm_2_]:checked").val();
	
	for(var i=0; i<4; i++) {
		jQuery('#qheb_catperm_2_'+i).prop('disabled', i<everyonePerm);
	}
	if (registeredPerm < everyonePerm) {
		jQuery('#qheb_catperm_2_'+everyonePerm).attr('checked','checked');
		registeredPerm = everyonePerm;
	}
	
	jQuery("input[id^=qheb_catperm_]:checked").each(function(){
		var rb = jQuery(this); 
		var id = rb.attr('id');
		var regs = id.match(/_(\d+)_(\d+)/);
		if (regs[1] > 2) {
			for(var i=0; i<4; i++) {
				jQuery('#qheb_catperm_'+regs[1]+'_'+i).prop('disabled', i<registeredPerm);
			}
			if (regs[2] < registeredPerm) {
				jQuery('#qheb_catperm_'+regs[1]+'_'+registeredPerm).attr('checked','checked');
			}
		}
	});
}

function qheb_catperms_init() {
	qheb_catperms_update();
	jQuery("input[id^=qheb_catperm_]").change(qheb_catperms_update);
}

/*
 * User list
 */
function qheb_ulist_actchange() {
	act = jQuery('#qheb_ulist_action').val();
	if (act == 'addgroup' || act == 'removegroup') {
		jQuery('#qheb_ulist_group').show();
	} else {
		jQuery('#qheb_ulist_group').hide();
	}
}

/*
 * Badges
 */
function qheb_badges_init() {
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
	qheb_badges_init();
	qheb_catperms_init();
});

