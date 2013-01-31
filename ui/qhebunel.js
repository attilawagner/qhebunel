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
	form = jQuery('#newThreadForm');
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
 * Loads SCEditor for the given textarea.
 */
function initSCE(selector, options) {
	var toolbar = "bold,italic,underline,strike,subscript,superscript|left,center,right,justify|" +
		"size,color,removeformat|cut,copy,paste,pastetext|bulletlist,orderedlist|" +
		"table|code,quote,spoiler|horizontalrule,image,email,link,unlink|emoticon,youtube,date,time|" +
		"print,source";
	jQuery.sceditorBBCodePlugin.bbcode.set(
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
	jQuery.sceditorBBCodePlugin.bbcode.set(
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
					user = cite.text().match(/^(.*):\s*$/)[1];
					content = content.substr(cite.text().length);
					console.log('[quote="'+user+'" post='+post+']'+content+'[/quote]');
					return '[quote="'+user+'" post='+post+']'+content+'[/quote]';
				},
				html: function(token, attrs, content) {
					if(typeof attrs.defaultattr !== "undefined") {
						content = '<cite post="'+attrs.post+'">' + attrs.defaultattr + ': </cite>' + content;
					}
					return '<blockquote>' + content + '</blockquote>';
				}
			}
		);
	return jQuery(selector).sceditorBBCodePlugin(
		jQuery.extend(
			{toolbar:toolbar,emoticonsRoot:qhebunelConfig.SCEditor.emoticonsRoot,emoticons:qhebunelConfig.SCEditor.emoticons},
			options
		)
	);
}

function initReplyForm() {
	var w = jQuery('#replyForm textarea').width;
	var editor = initSCE('#replyForm textarea', {resizeMinWidth:w, resizeMaxWidth:w, resizeMinHeight:150});
	
	jQuery('#replyForm').submit(function(){
		if (editor.sceditor('instance').val() == "") {
			return false;
		}
	});
	
}
function initNewThreadForm() {
	w = jQuery('#newThreadForm textarea').width;
	initSCE('#newThreadForm textarea', {resizeMinWidth:w, resizeMaxWidth:w, resizeMinHeight:150});

	initUploadForm();
}
function initProfileForm() {
	initSCE('.profile_settings textarea', {resizeEnabled:false});
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
	if ((pass1.val() != "" || pass2.val() != "") && pass1.val() != pass2.val()) {
		pass1.css('border-color', '#d00');
		pass2.css('border-color', '#d00');
		ok = false;
	} else {
		pass1.css('border-color', '');
		pass2.css('border-color', '');
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
/*
 * Page initialization
 */
jQuery(document).ready(function() {
	initReplyForm();
	initProfileForm();
	initNewThreadForm();
	initSpoilers();
	initPostPermalinks();
});