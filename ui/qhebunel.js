/**
 * Qhebunel
 * User interface JS
 */

/**
 * Called on the new thread page, this function validates the form,
 * shows visual hints on errors, and stops the submit.
 */
function qheb_validateNewThreadForm() {
	isOk = true;
	form = jQuery('#new-thread-form');
	title = form.find('input[name="topic_title"]');
	title.val(jQuery.trim(title.val()));
	message = form.find('textarea[name="topic_message"]');
	message.val(jQuery.trim(message.val()));
	if (title.val() == "") {
		qheb_inputSetError(title);
		isOk = false;
	}
	if (message.val() == "") {
		qheb_inputSetError(message);
		isOk = false;
	}
	
	return isOk;
}

/**
 * Sets the CSS class of the field, and adds the event handler.
 * Call this on an input field to provide graphical feedback for the user.
 * @param field jQuery wrapped input/textarea/select
 */
function qheb_inputSetError(field) {
	field.addClass('error');
	field.bind('keydown', function(){
		jQuery(this).removeClass('error').unbind('keydown');
	});
}

/**
 * Adds custom code handling to the SCEditor plugin.
 */
function initSCEPlugin() {
	jQuery.sceditor.plugins.bbcode.bbcode.set(
		"spoiler",
		{
			styles: {
			},
			tags: {
				div: {
					"class": ["spoiler"]
				}
			},
			isSelfClosing: false,
			isInline: false,
			isHtmlInline: undefined,
			allowedChildren: null,
			allowsEmpty: false,
			excludeClosing: false,
			skipLastLineBreak: false,
			
			breakBefore: false,
			breakStart: true,
			breakEnd: true,
			breakAfter: true,
			
			format: '[spoiler]{0}[/spoiler]',
			html: '<div class="spoiler" style="border:2px dashed #aaa;padding: 3px;">{0}</div>'
		}
	);
	jQuery.sceditor.command.set(
		"spoiler",
		{
			exec: function (caller) {
				var	editor  = this;
				editor.getRangeHelper().insertHTML('<div class="spoiler" style="border:2px dashed #aaa;padding: 3px;">','</div>');
			},
			errorMessage: "Cannot add spoiler tag.",
			txtExec: ["[spoiler]","[/spoiler]"],
			keyPress: undefined,
			tooltip: "Spoiler"
		}
	);
	jQuery.sceditor.plugins.bbcode.bbcode.set(
		"quote",
		{
			styles: {
			},
			tags: {
				blockquote: null
			},
			isSelfClosing: false,
			isInline: false,
			isHtmlInline: undefined,
			allowedChildren: null,
			allowsEmpty: false,
			excludeClosing: false,
			skipLastLineBreak: false,
			
			breakBefore: false,
			breakStart: true,
			breakEnd: true,
			breakAfter: true,
			
			format: function(element, content){
				var cite,post,user;
				element = jQuery(element);
				cite = element.children('cite');
				post = cite.attr('post');
				user = cite.text().match(/^\s*(.*):\s*$/);
				if (typeof user !== "undefined" && user != null && user.length > 0){
					user = user[1];
				} else {
					user = '';
				}
				content = content.substr(cite.text().length);
				console.log('[quote="'+user+'" post='+post+']'+content+'[/quote]');
				return '[quote="'+user+'" post='+post+']'+content+'[/quote]';
			},
			html: function(token, attrs, content) {
				var name='', post='';
				if(typeof attrs.defaultattr !== "undefined") {
					name = attrs.defaultattr + ': ';
				}
				if (typeof attrs.post !== "undefined") {
					post = attrs.post;
				}
				content = '<cite post="' + post + '">' + name + '</cite>' + content;
				console.log('<blockquote>' + content + '</blockquote>');
				return '<blockquote>' + content + '</blockquote>';
			}
		}
	);
}

/**
 * Loads SCEditor for the given textarea.
 * @param selector jQuery selector string to target the textarea that will be used as the base element.
 * @param options Object containing parameters for the SCEditor instance.
 * @returns The created editor instance.
 */
function initSCEInstance(selector, options) {
	var toolbar = "bold,italic,underline,strike,subscript,superscript|left,center,right,justify|" +
		"size,color,removeformat|cut,copy,paste,pastetext|bulletlist,orderedlist|" +
		"table|code,quote,spoiler|horizontalrule,image,email,link,unlink|emoticon,youtube,date,time|" +
		"print,source";
	
	var field = jQuery(selector);
	if (field.length == 0) {
		return; //Textarea does not exist
	}
	var w = field.width();
	
	return field.sceditor(
		jQuery.extend(
			{
				plugins: "bbcode",
				toolbar: toolbar,
				emoticonsRoot: qhebunelConfig.SCEditor.emoticonsRoot,
				emoticons: qhebunelConfig.SCEditor.emoticons,
				resizeMinWidth:w,
				resizeMaxWidth:w,
				resizeMinHeight:150
			},
			options || {}
		)
	);
}

function initReplyForm() {
	var field = jQuery('#reply-form textarea');
	if (field.length == 0) {
		return; //No reply form
	}
	var editor = initSCEInstance('#reply-form textarea');
	
	jQuery('#reply-form').submit(function(){
		if (editor.sceditor('instance').val() == "") {
			return false;
		}
	});
	
	initUploadForm();
}
function initNewThreadForm() {
	var field = jQuery('#new-thread-form textarea');
	if (field.length == 0) {
		return; //No reply form
	}
	
	var editor = initSCEInstance('#new-thread-form textarea');

	jQuery('#new-thread-form').submit(function(){
		if (editor.sceditor('instance').val() == "") {
			return false;
		}
	});
	
	initUploadForm();
}
function initEditPostForm() {
	var field = jQuery('#edit-post-form textarea');
	if (field.length == 0) {
		return; //No reply form
	}
	var editor = initSCEInstance('#edit-post-form textarea');

	jQuery('#edit-post-form').submit(function(){
		if (editor.sceditor('instance').val() == "") {
			return false;
		}
	});
	
	initUploadForm();
}
function initProfileForm() {
	initSCEInstance('.profile_settings textarea', {resizeEnabled:false});
}
function validateProfileForm() {
	var ok = true;
	if (jQuery('#profileForm #nickname').val().trim() == "") {
		jQuery('#profileForm #nickname').css('border-color', '#d00');
		ok = false;
	} else {
		jQuery('#profileForm #nickname').css('border-color', '');
	}
	var pass1 = jQuery('#profileForm #pass1');
	var pass2 = jQuery('#profileForm #pass2');
	var oldPass = jQuery('#profileForm #old-pass');
	if ((pass1.val() != "" || pass2.val() != "") && pass1.val() != pass2.val()) {
		pass1.css('border-color', '#d00');
		pass2.css('border-color', '#d00');
		ok = false;
	} else {
		pass1.css('border-color', '');
		pass2.css('border-color', '');
		if (oldPass.val() == "") {
			oldPass.css('border-color', '#d00');
		} else {
			oldPass.css('border-color', '');
		}
	}
	
	return ok;
}

/*
 * Upload form for attachments
 * Does not work for multiple fields.
 */
function initUploadForm() {
	//Reset
	jQuery('form .file input').unbind();
	
	//Create empty field if needed
	files = jQuery('form .file');
	used = 0;
	files.each(function(k,v) {
		if (jQuery(v).children('input:file').val() != "") {
			used++;
		}
	});
	if (files.length == used) {
		files.first().clone().appendTo(files.first().parent()).children('input:file').val("");
	}
	
	//File field onChange
	jQuery('form .file input:file').change(function(e) {
		initUploadForm();
	});
	
	//Remove button
	jQuery('form .file input.remove').click(function(e) {
		files = jQuery('form .file');
		t = jQuery(e.target);
		if (files.length == 1) {
			t.parent().children('input:file').val(null);
		} else {
			t.parent().remove();
		}
		initUploadForm();
	});
}

/**
 * Initalizes the [spoiler] buttons.
 */
function initSpoilers() {
	jQuery('input.spoiler_show').click(function(){
		button = jQuery(this);
		button.animate(
			{
				"height": "0px",
				"opacity": "0"
			},
			"fast",
			"linear",
			function(){
				button.next().css("opacity","0").animate({
					"height": "toggle",
					"opacity": "1"
				},
				"fast",
				"linear");
				button.parent().removeClass('spoiler-closed').addClass('spoiler-open');
				button.remove();
			}
		);
	});
}

/**
 * Adds an onclick event to post permalinks
 * that scrolls the view to the post then sets the hash in the address bar
 * if the referenced post is on the same page
 */
function initPostPermalinks() {
	(function($){
		var baseUrlLength = qhebunelConfig.forumRoot.length;
		var regEx = /^post-(\d+)/;
		$('a').each(function(){
			var a = $(this);
			var href = a.attr('href');
			if (href != undefined && href.substr(0, baseUrlLength) == qhebunelConfig.forumRoot) {
				var rxres = href.substr(baseUrlLength).match(regEx);
				if (rxres != null) {
					var post = $('#'+rxres[0]);
					if (post.length > 0) {
						a.click(function(){
							$('html, body').animate(
								{scrollTop: post.offset().top},
								'slow',
								function(){
									window.location.hash = rxres[0];
								});
							return false;
						});
					}
				}
			}
		});
	})(jQuery);
}

/**
 * Adds an onclick event to the quote and reply links.
 */
function initPostReplyLinks() {
	(function($){
		$('a.reply-link').each(function(){
			var a = $(this);
			a.click(onReplyLinkClick);
		});
		$('a.quote-link').each(function(){
			var a = $(this);
			a.click(onQuoteLinkClick);
		});
	})(jQuery);
}

function onReplyLinkClick() {
	var a = jQuery(this);
	var post = a.parent().parent().parent().parent();
	if (post.next().attr('id') != 'send-reply') {
		var oldContainer = jQuery('#send-reply');
		var container = jQuery('<div style="opacity:0;display:none;"></div>').insertAfter(post);
		oldContainer.animate({
				"height": "toggle",
				"opacity": "0"
			},
			"fast",
			"linear",
			function() {
				container.append(oldContainer.contents().detach());
				oldContainer.remove();
				container.attr('id','send-reply');
				
				container.animate({
						"height": "toggle",
						"opacity": "1"
					},
					"fast",
					"linear",
					function(){
						var editor = jQuery('#reply-form textarea').sceditor('instance');
						if (editor.sourceMode() == false) {
							editor.setWysiwygEditorValue("");
							editor.sourceMode(true);
						}
						editor.setSourceEditorValue("");
						editor.sourceMode(false);
						editor.readOnly(false);
					}
				);
			}
		);
	}
	return false;
}

function onQuoteLinkClick() {
	var container = jQuery('#send-reply');
	var a = jQuery(this);
	var post = a.parent().parent().parent().parent();
	var oldContainer = jQuery('#send-reply');
	var container = jQuery('<div style="opacity:0;display:none;"></div>').insertAfter(post);
	oldContainer.animate({
			"height": "toggle",
			"opacity": "0"
		},
		"fast",
		"linear",
		function() {
			container.append(oldContainer.contents().detach());
			oldContainer.remove();
			container.attr('id','send-reply');
			
			//Load quote content
			var postId = post.attr('id').match(/\d+/);
			jQuery.get(
				qhebunelConfig.forumRoot+"quote/"+postId,
				function(data){
					var editor = jQuery('#reply-form textarea').sceditor('instance');
					if (editor.sourceMode() == false) {
						editor.setWysiwygEditorValue("");
						editor.sourceMode(true);
					}
					editor.setSourceEditorValue(data);
					editor.sourceMode(false);
					editor.readOnly(false);
				}
			);
			
			container.animate({
					"height": "toggle",
					"opacity": "1"
				},
				"fast",
				"linear"
			);
		}
	);
	return false;
}

function initPostMoveLinks() {
	jQuery('.qheb-thread a.post-action.move-link').click(function(){
		var a = jQuery(this);
		var pfooter = a.parent().parent();
		var post = pfooter.parent().parent();
		var movediv = jQuery('#move-post');
		var postId = /post-(\d+)/.exec(post.attr('id'));
		if (postId == null) {
			return false;
		}
		postId = postId[1];
		
		jQuery('#move-post-id').val(postId);
		pfooter.append(movediv.detach());
		movediv.show();
		
		jQuery.get(qhebunelConfig.forumRoot+'move-post-ajax/categories/'+postId, function(data){
			var select = jQuery(data);
			if (select.prop('tagName') == "SELECT") {
				var catSelect = jQuery('#move-post-category');
				catSelect.contents().remove();
				catSelect.append(select.contents());
				catSelect.val(catSelect.find('option[selected="selected"]').val());
				catSelect.unbind('change');
				catSelect.bind('change', onPostMoveCategoryChange);
				catSelect.removeAttr('disabled');
				
				onPostMoveCategoryChange();
			}
		});
		
		return false;
	});
}

function onPostMoveCategoryChange() {
	var catSelect = jQuery('#move-post-category');
	var threadSelect = jQuery('#move-post-thread');
	var submitButton = jQuery('#move-post-submit');
	var threadTitle = jQuery('#move-post-thread-title');
	threadSelect.attr('disabled', 'disabled');
	submitButton.attr('disabled', 'disabled');
	
	var catId = catSelect.val();
	jQuery.get(qhebunelConfig.forumRoot+'move-post-ajax/threads/'+catId, function(data){
		var newThread = threadSelect.find('option:last').detach();
		threadSelect.contents().remove();
		threadSelect.append(data);
		threadSelect.append(newThread);
		
		threadSelect.unbind('change');
		threadSelect.bind('change', onPostMoveThreadChange);
		threadTitle.unbind('input');
		threadTitle.bind('input', onPostMoveThreadChange);
		threadSelect.removeAttr('disabled');
		onPostMoveThreadChange();
	});
}

function onPostMoveThreadChange() {
	var threadSelect = jQuery('#move-post-thread');
	var submitButton = jQuery('#move-post-submit');
	var threadTitle = jQuery('#move-post-thread-title');
	
	if (threadSelect.val() != 'new') {
		submitButton.removeAttr('disabled');
		threadTitle.attr('disabled', 'disabled');
	} else {
		threadTitle.removeAttr('disabled');
		if (threadTitle.val().trim().length > 0) {
			submitButton.removeAttr('disabled');
		} else {
			submitButton.attr('disabled', 'disabled');
		}
	}
}

function initPostReportLinks() {
	jQuery('.qheb-thread a.post-action.report-link').click(function(){
		var a = jQuery(this);
		var pfooter = a.parent().parent();
		var post = pfooter.parent().parent();
		var reportdiv = jQuery('#report-post');
		var postId = /post-(\d+)/.exec(post.attr('id'));
		if (postId == null) {
			return false;
		}
		postId = postId[1];
		
		jQuery('#report-post-id').val(postId);
		pfooter.append(reportdiv.detach());
		reportdiv.show();
		
		return false;
	});
	var reasonField =jQuery('#report-post-reason');
	var submitButton = jQuery('#report-post-submit');
	reasonField.bind('input', function() {
		if (reasonField.val().trim().length > 0) {
			submitButton.removeAttr('disabled');
		} else {
			submitButton.attr('disabled', 'disabled');
		}
	})
}

function initPMPage() {
	var pmdiv = jQuery('.private-msg-conversation');
	pmdiv.scrollTop(pmdiv.prop("scrollHeight")); 
}

/*
 * Page initialization
 */
jQuery(document).ready(function() {
	initSCEPlugin();
	initReplyForm();
	initProfileForm();
	initNewThreadForm();
	initEditPostForm();
	initSpoilers();
	initPostPermalinks();
	initPostReplyLinks();
	initPostMoveLinks();
	initPostReportLinks();
	initPMPage();
});