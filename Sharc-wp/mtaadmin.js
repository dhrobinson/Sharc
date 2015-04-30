function add_admin_controls(){
	var comment=false;
	jQuery('.mtacontrols').remove();
	jQuery('#mtacontainer').after('<ul class="mtacontrols" style="opacity:0"><li class="cache"><a href="#">Clear cache</a></li><li class="blacklist"><a href="#">Remove</a></li></ul>');

	if(!MTA.instagram&&MTA.instagram_connect){
		jQuery('.mtacontrols').append('<li class="connect_instagram"><a href="'+MTA.instagram_connect+'">Connect to Instagram</a></li>');
	}else if(MTA.instagram){
		comment=true;
	}
	if(!MTA.flickr&&MTA.flickr_connect){
		jQuery('.mtacontrols').append('<li class="connect_flickr"><a href="'+MTA.flickr_connect+'">Connect to Flickr</a></li>');
	}else{
		comment=true;
	}

	if(comment){
		jQuery('.mtacontrols').append('<li class="comment"><a href="'+MTA.instagram_connect+'">Comment on selected</a> <textarea class="leavecomment"></textarea><input type="button" value="Comment" class="leavecomment"/><input type="button" value="Cancel" class="leavecomment cancel"/></li>');
	}
	jQuery('.mtacontrols').animate({opacity:'1'},'slow');
	
	jQuery('.mtacontrols .cache a').bind(
		'click',
		function(){
			img=jQuery('#mtacontainer img.wp-post-image');
			mtaRefresh(img);
			return false;
		}
	);
	jQuery('.mtacontrols .comment a').bind(
		'click',
		function(){
			jQuery('.leavecomment').fadeToggle();
			return false;
		}
	);
	jQuery('.mtacontrols .comment input').bind(
		'click',
		function(){
			if(jQuery(this).hasClass('cancel')){
				jQuery('textarea.leavecomment').val('');
				jQuery('.mtacontrols .comment a').click();
			}else{
				img=jQuery('#mtacontainer img.wp-post-image');
				var items=jQuery('#mtacontainer input:checked');
				if(items.length){
					itemList=[];
					items.each(function(){
						var item=jQuery(this);
						itemList.push(item.parent().parent().attr('data-source')+'|'+item.parent().attr('mtaid'));
					});
					if(confirm('Are you sure you want to leave this comment on all selected images?')){

						// Get DB info for image
						jQuery.post(
							MTA.ajaxurl,
							{
								action	: 'mta-comment',
								items	: itemList,
								comment	: jQuery('textarea.leavecomment').val()
							},
							function(data) {
								//addUnintentional(data,img);
								if(data.ok){
									//mtaRefresh(img);
									jQuery('textarea.leavecomment').val('');
									alert('Your comment has been left successfully.');
								}
							}
						);
					}
				}
			}
		}
	);
	jQuery('.mtacontrols .blacklist a').bind(
		'click',
		function(){
			img=jQuery('#mtacontainer img.wp-post-image');
			var items=jQuery('#mtacontainer input:checked');
			if(items.length){
				itemList=[];
				items.each(function(){
					var item=jQuery(this);
					itemList.push(item.parent().attr('mtaid'));
				});
				if(confirm('Are you sure you want to blacklist these images? This cannot be undone.')){

					// Get DB info for image
					jQuery.post(
						MTA.ajaxurl,
						{
							action	: 'mta-blacklist',
							items	: itemList,
							guid	: img.attr('data-mta-guid')
						},
						function(data) {
							//addUnintentional(data,img);
							if(data.ok){
								mtaRefresh(img);
							}
						}
					);
				}
			}
		}
	);
	jQuery('#mtacontainer li a').each(function(){
		var a=jQuery(this);
		var sid=a.attr('mtaid');
		var input=jQuery('<input type="checkbox" name="item['+sid+']" data-sid="'+sid+'"/>');
		a.append(input);
		a.hover(function(){
			jQuery(this).parent().find('input').fadeIn(200);
		},function(){
			var input=jQuery(this).parent().find('input');
			if(!input.is(':checked'))input.fadeOut(200);
		});
	});
}

function mtaRefresh(img){
	var reopen=false;
	jQuery('#images li').remove();
	var icon=jQuery('.mtaicon');
	if(icon.hasClass('open')){
		icon.click();
		reopen=true;
	}
	icon.unbind('click');

	icon.css({background:'url('+MTA.plugindir+'ajax-loader.gif) no-repeat 50% 50%'});

	img.attr('data-mta-nocache','true');
	mtaGo(img);
	img.attr('data-mta-nocache','false');
	if(reopen)icon.click();
}