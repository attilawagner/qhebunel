function qheb_catlist_up(id) {
	jQuery('#qheb_catlist_catid').val(id);
	jQuery('#qheb_catlist_direction').val('up');
	jQuery('#qheb_catorderform').submit();
}
function qheb_catlist_down(id) {
	jQuery('#qheb_catlist_catid').val(id);
	jQuery('#qheb_catlist_direction').val('down');
	jQuery('#qheb_catorderform').submit();
}
function qheb_ulist_actchange() {
	act = jQuery('#qheb_ulist_action').val();
	if (act == 'addgroup' || act == 'removegroup') {
		jQuery('#qheb_ulist_group').show();
	} else {
		jQuery('#qheb_ulist_group').hide();
	}
}

jQuery(document).ready(function() {
 
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

});