// MTA JS
jQuery(function(){
	jQuery('img').each(function(){
		var img=jQuery(this);
		var imgsrc=img.attr('src');
		
		// Lookup image for extra meta
		mtaGetInfo(img,imgsrc);
	});
})
function mtaGetInfo(img,imgsrc){
	// Get DB info for image
	jQuery.post(
		MTA.ajaxurl,
		{
			action : 'mta-imglookup',
			src : imgsrc
		},
		function(data) {
			addMtaData(data,img);
		}
	);
}
function addMtaData(data,img){
	// If the image is a WP attachment, add data-mta-* attributes
	if(data.guid){
		//img.css({border:'5px solid red'});
		img.attr('data-mta-guid',data.guid);
		img.attr('data-mta-nocache','false');
		if(data.title)img.attr('data-mta-title',data.title);
		if(data.tags)img.attr('data-mta-tags',data.tags.join());
		if(data.isbn)img.attr('data-mta-isbn',data.isbn);
		if(data.exif)img.attr('data-mta-exif',JSON.stringify(data.exif));
		if(data.sha1)img.attr('data-mta-sha1',data.sha1);

		addMtaIcon(img);

		mtaGo(img);
	}else{
		//console.log(data.error);
	}
}
function addMtaIcon(img){
	img.wrap('<div class="mtatmp"></div>');
	jQuery('.mtatmp').css({position:'relative'});
	var icon=jQuery('<a class="mtaicon" href="" onclick="return false"></a>');
	icon.css({background:'url('+MTA.plugindir+'ajax-loader.gif) no-repeat 50% 50%',opacity:'0.1',width:'20px',height:'20px',zIndex:'999',position:'absolute',display:'block',top:'4px',left:img.width()-24});
	img.after(icon);
	icon.animate({opacity:'1'});
}
function mtaGo(img){
	// Call MTA & populate results
	//var api='http://localhost/MTA/neo.php';
	//var api='http://dhrobinson.com/swarm/neo/neo.php';
	var api		=	MTA.ajaxurl;
	var action	=	'mta-go';
	var uri		=	img.attr('data-mta-guid');
	var tags	=	img.attr('data-mta-tags');
	var isbn	=	img.attr('data-mta-isbn');
	var exif	=	img.attr('data-mta-exif');
	var sha1	=	img.attr('data-mta-sha1');
	var nocache	=	img.attr('data-mta-nocache');

	//img.after('<p class="tmp">Loading MTA data...<br/></p>');

	// Get DB info for image
	jQuery.post(
		api,
		{
			action	: action,
			uri		: uri,
			tags	: tags,
			isbn	: isbn,
			exif	: exif,
			id		: sha1,
			nocache	: nocache
		},
		function(data) {
			addUnintentional(data,img);
		}
	);

}
function addUnintentional(data,img){
	var gotitems=false;
	jQuery('#images').remove();
	/*
	if(data.places&&data.places.length){
		var places=jQuery('<ul id="places">').css({clear:'left'});
		//alert(data.places[0].name);
		for(var i=0;i<data.places.length;i++){
			var li=jQuery('<li>');
			var a=jQuery('<a>')
				.text(data.places[i].name+' ('+data.places[i].location.formattedAddress.join(', ')+')')
				.attr('href','http://www.foursquare.com/v/'+data.places[i].id)
				.attr('target','_blank');
			places.append(li.append(a));
		}
		if(i>0)places.prepend(jQuery('<li><strong>Places</strong></li>'));
		img.after(places);
		jQuery('p.tmp').fadeOut();
	}
	*/
	/*
	if(data.books&&data.books.length){
		var books=jQuery('<ul id="books">').css({clear:'left'});
		//alert(data.places[0].name);
		if(data.books[0].similar_books){
			for(var i=0;i<data.books[0].similar_books.book.length;i++){
				var similar=data.books[0].similar_books.book[i];
				var li=jQuery('<li>').css({clear:'left'});
				var a=jQuery('<a>');
				var cover=jQuery('<img>').css({float:'left',margin:'5px 5px 0 0'});
				//li.prepend(cover);	
				a.html(similar.title+'<br/>by '+similar.authors.author.name)
					.attr('href','https://www.goodreads.com/book/show/'+similar.id)
					.attr('target','_blank');
				cover.attr('src',similar.small_image_url).css({width:'50px'});
				a.prepend(cover);
				books.append(li.append(a));
			}
			if(i>0)books.prepend(jQuery('<li><strong>Similar books</strong></li>'));
			img.after(books);
			jQuery('p.tmp').fadeOut();
		}
	}
	*/


	if(data!=null&&data.images.length){
		var imgs=jQuery('<ul id="images">').css({listStyleType:'none'});
		var bubbles=jQuery('<div class="bubbleholder"></div>');
		for(var i=0;i<data.images.length;i++){
			var li=jQuery('<li>').css({position:'relative',float:'left',margin:'1px',padding:'0'});
			li.attr('data-source',data.images[i].source);
			li.css({margin:'0 0 10px 10px'});
			var a=jQuery('<a>').attr('href',data.images[i].link);
			a.attr('target','_blank');
			a.attr('mtaid',data.images[i].sid);
			var tags=data.images[i].tags;
			a.attr('tags','#'+tags.join(', #'));
			if(!data.images[i].caption.text)data.images[i].caption.text='[No title]';
			try{
				a.attr('score','['+data.images[i].score+'] ');
				//a.attr('title',data.images[i].caption.text);
			}catch(err){
				//a.attr('title','[No title]');
			}

			// Bubbles
			bubbles.append('<span mtaid="'+data.images[i].sid+'" class="bubble '+data.images[i].source+'" style="display:none;"><span class="title">'+data.images[i].caption.text+'</span><span class="tags">'+a.attr('tags')+'</span><span class="arrowholder"><span class="arrow"></span></span></span>');
			a.hover(
				function(){
					var a=jQuery(this);
					var p=a.offset();
					jQuery('.bubbleholder span[mtaid='+a.attr('mtaid')+']').css({display:'block',left:(p.left-285),top:(p.top-5)+'px'}).show();
				},
				function(){
					var a=jQuery(this);
					jQuery('.bubbleholder span[mtaid='+a.attr('mtaid')+']').css({display:'none'}).hide();
				}
			);

			// Tags

			if(data.images[0].type=='image'){
				a.css({display:'block',width:'70px',height:'70px',backgroundImage:'url('+data.images[i].images.thumbnail.url+')',backgroundSize:'cover',backgroundPosition:'50% 50%'});
			}

			imgs.append(li.append(a));
		}
		jQuery('body').append(bubbles);

		if(i>0)imgs.prepend(jQuery('<li class="title"><strong>Related photo results</strong></li>'));
		gotitems=true;

		img.css({float:'left',width:img.width(),borderRight:'8px solid #8ba95f'});
		imgs.css({width:'260',border:'6px solid #3e4243',borderWidth:'8px 8px 8px 0',padding:'0 0 0 0',position:'absolute',left:(img.width()+8),height:(img.height()-16),overflow:'auto',background:'#3e4243'});
		img.wrap('<div id="mtacontainer"><div class="mta"></div></div>');
		var container=jQuery('div#mtacontainer');
		var mta=jQuery('div.mta');
		mta.css({width:img.width()*2,height:img.height(),position:'relative'});
		container.css({overflow:'hidden',width:img.width(),height:img.height()});

		
		mta.append(imgs);

		if (typeof add_admin_controls == 'function') { 
			add_admin_controls(); 
		}else{
		}

		imgs.parent().find('.scrollbar').css({});
		if(mta.find('.mtaicon').length<1)mta.append(jQuery('.mtaicon'));
		mta.find('.mtaicon').animate(
			{opacity:'0.1'},
			function(){
				jQuery('.mtaicon').unbind('click');
				jQuery('.mtaicon').css({opacity:'0.1',background:'url('+MTA.plugindir+'mta-logo.png) no-repeat 50% 50%'}).animate({opacity:'1'}).bind('click',function(){
				var a=jQuery(this);
				if(a.hasClass('open')){
					a.removeClass('open');
					a.parent().find('img.wp-post-image,ul,a.mtaicon').animate({marginLeft:'0'});
				}else{
					a.addClass('open');
					a.parent().find('img.wp-post-image,ul,a.mtaicon').animate({marginLeft:'-276px'});
				}
			});
		});


	}else{
		jQuery('a.mtaicon').fadeOut();
	}

	jQuery('li.flickr img, li.instagram img').css({width:'67px',height:'67px'});
	jQuery('li.flickr,li.instagram').css({margin:'0 0 12px 12px'});
	jQuery('li.title').css({margin:'12px',color:'#fff',fontWeight:'normal',borderBottom:'2px solid #8ba95f'});
}