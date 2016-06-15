/**
 *
 * @file Responsive Header customizer view & media cropper
 * @author Svetlana http://cadros.eu
 *
 * Adds an option to set multiple img sources to the current header
 * @implements {wp.customize.HeaderTool}
 *
 * @exports
 * HeaderTool wp-inlude/js/customize-vews.js
 * HeaderControl wp-admin/js/customize-controls.js
 * HeaderTool.ImageModel wp-inlude/js/customize-models.js
 *
 * @requires
 * wp-inlude/js/wp-backbone.js (.min)
 * wp-inlude/js/underscore.min.js
 * wp-inlude/js/media-vews.js
 *
 */

/**
 * @type {string} 
 */
var lex = _wpMediaViewsL10n, saveButt = jQuery('#customize-header-actions #save');

/**
 * Image dimension guidance from customizer
 * @see _customizer.php
 * @type {(Array.)} 
 */
  // Sizes yet to add
var due_widths = respHeader['due_widths'],
  // Widths supported by current theme
  supported_widths = respHeader['supported_widths'],
  // Fallback after all supported sizes are covered
  fallback_width = respHeader['fallback_width'];



/** @callback ascending For sort function */
  function ascending(a,b) {
    if( a.width > b.width ) {
      return 1;
    }
    if( a.width < b.width ) {
      return - 1;
    }
    return 0;
  };

 /** 
  * @func within20
  * @param {number} needle Width to check
  * @param {Obj[]} List of sizes to check against
  * @return {(number|bool)} needle index | False if needle is away of desired range more than 20px
  */
  function within20(needle,list) {
    for (var i = 0; i < list.length; i++ ) {
      if( 20 > Math.abs(needle - list[i].width)) { 
        return i;
      }
    }
    return false;
  };

  /** 
  * @func source_match Checks if the given source matches any in the current set
  * @param {number} added Img Source to check
  * @param {Obj[]} set List of sources to check against
  * @return {number|bool} Match's index | False for no match
  */
  function source_match(added,set) {
    for( var i in set ) {
      if( added.width == set[i].width && added.img_id == set[i].img_id ) {   
        return i;
      }
    }
    return false;
  }

/**
 * @func updateSRCSET To update current header srcset & due widths on add & remove actions
 * Updates {Obj[]} header.srcset & header.toadd of the header ImageModel
 *
 * @this {obj} HeaderTool model
 * @param {number} width Img Source width
 * @param {number} img_id Img attachment_id 
 * @param {string= } [url=false] On Remove action | Img url on Add action
 *
 */
wp.customize.HeaderTool.ImageModel.prototype.updateSRCSET = function( width, img_id, url) {
    var url = url || false;
    var current = this.get('header');
    var toadd = current.toadd || false, sources = current.srcset || [];

    // ADD SOURCE
    if( url ) {
      // only if it's gonna be unique
      if( ! source_match({ width:width, img_id:img_id }, sources) ) {

        // add source
        sources.push({ width : width, guid: url, img_id:img_id });
        // add updated sources back to header, in case header.srcset turned empty new
        current.srcset = sources;
      }

      // remove the width from due sizes if it's covered 
      if( toadd ) {
        dueWidthsUpdate(toadd, width, sources, true ); 
      }
      // flag the event to HeaderTool.CurrentView, who listens, so it knows to render change
      this.set('addedSRC', width);

    } else {
      // REMOVE SOURCE

      /**
       * @param {obj[]} sources Header srcset to find the index of the img to remove by given width & img_id
       */
      var indx = source_match({ width:width, img_id:img_id }, sources); 
      if( ! indx ) {
        return; // the sources have gotten empty
      }
      // otherwise 
      sources.splice(indx, 1);

      /** @param {ascending} cb Sort filtered srcset */
      sources.sort(ascending);
      current.srcset = sources;
        
      // add the freed width to due sizes
      if( toadd ) {
        dueWidthsUpdate(toadd, width, sources );
      }
      // flag done
      this.set('removedSRC', width);
    }
};


/**
 * @func dueWidthsUpdate to update the widths for img sources on source add | remove
 *
 * Updates header.due_widths for implementing header-current template $see _cusromizer.php
 * 
 * @param {obj[]} toadd Widths left to add to the given header
 * @param {number} width Source width
 * @param {obj[]} sources Current header img sources
 * @param {bool} remove True to cut size off | false to add back
 */
function dueWidthsUpdate(toadd, width, sources, remove) {

  // the source is removed -- ADD SIZE BACK to due -- 
  if( ! remove ) {

    // check if the width was covering one of the due +-20px
    var match = {
      /** It has a match at theme recommended widths */
      atSupported: within20( width, supported_widths ), // int if yes. Can be 0! | false if not

      /** It's not yet covered by some current source */ 
      atAdded: within20(width, sources) // int if match | false if not covered
    };
    
    if( ! Number.isInteger( match.atSupported )  || Number.isInteger( match.atAdded ) ) {
      // Do not add the width back to due list. It's either custom (freescale) or still covered
      return;
    }

    // width is good to be added back so the next source use it as due, remove freescale
    if( due_widths.freescale ) {
      due_widths.freescale = false;
      toadd.freescale = false;
      toadd.fix = {};// add smth to the obj so it has length that will fix error on shift()
      toadd.shift();
      due_widths.shift();
    }

    // add width back to the lists
    var newwidth = { width : supported_widths[match.atSupported].width, height: supported_widths[match.atSupported].height };
    toadd.push(newwidth);
    due_widths.push(newwidth);

    /** @param {ascending} cb Sort from lowest */
    toadd.sort(ascending);
    due_widths.sort(ascending); 

  } else {
    // the source is Added -- REMOVE SIZE from due -- 

    // check on index
    var indx = within20(width, toadd);
    if( Number.isInteger(indx) ) {
      // if found cut it off
      if(toadd.length > indx){
        toadd.splice(indx, 1);
      }
      // shift cropper size guidance to the next due width if any
      if( toadd.length ) {
        due_widths.shift();
      } else {
        // let it scale free if due is over
        due_widths = respHeader['fallback_width'];
        due_widths.freescale = true;
      }
    }
  }
}



/**
 * @func cropSize to Calculate media cropper guidance size
 *
 * @param {number} w Width to crop source
 * @param {number} h Height to crop source
 * @param {bool=} scale True for free scale | false for cropping to due width 
 * @returns {obj} imgSelectOptions
 */
cropSize = function(attachment, controller, w,h, scale) {
  var xInit = parseInt(w, 10) || 320,
    yInit = parseInt(h, 10) || 320,
    freescale = !! scale,
    flexWidth = !! parseInt(_wpCustomizeHeader.data['flex-width'], 10),
    flexHeight = !! parseInt(_wpCustomizeHeader.data['flex-height'], 10),
    ratio, realHeight, realWidth,
    imgSelectOptions;

  realWidth = attachment.get('width');
  realHeight = attachment.get('height');

  this.headerImage = new wp.customize.HeaderTool.ImageModel();
  this.headerImage.set({
    themeWidth: xInit,
    themeHeight: yInit,
    themeFlexWidth: flexWidth,
    themeFlexHeight: flexHeight,
    imageWidth: realWidth,
    imageHeight: realHeight
  });

  // let skip cropping if img size fits or is free scale mode
  controller.set( 'canSkipCrop', ! this.headerImage.shouldBeCropped() || freescale );
  if( freescale ) {
    controller.set( 'freescale', true);
  }

  ratio = xInit / yInit;

  if ( realHeight  < yInit ) {
    yInit = realHeight;
  } 
  if ( realWidth  < xInit ) {
    xInit = realWidth;
  }

  /** area select plugin @see wp-include/js/jquery.imgareaselect.js */
  imgSelectOptions = {
    handles: true,
    keys: true,
    instance: true,
    persistent: true,
    imageWidth: realWidth,
    imageHeight: realHeight,
    x1: 0,
    y1: 0,
    x2: xInit,
    y2: yInit
  };


  if (flexHeight === false && flexWidth === false) {
    imgSelectOptions.aspectRatio = xInit + ':' + yInit;
  }
  // stop restricting by max Size after all due sizes are added up (on page reload freestyle is set true by _customizer.php ->strings )
  if (flexHeight === false && ! freescale ) {
    imgSelectOptions.maxHeight = yInit;
  }
  if (flexWidth === false && ! freescale ) {
    imgSelectOptions.maxWidth = xInit;
  }
  return imgSelectOptions;
};




(function( $, _ ) {


  /**
   * Bind customizer to reload the entire page (not preview refresh) on Save if the header width | height setting has been changed
   *
   * So far this looks the easiest way to pass the new size to the native cropper, which respHeader still be using for Add new image
   *
   * As far as the default dimensions for the native cropper are totally php dependent
   *
   * @see _customizer.php enqueue() for _wpCustomizeHeader[data][width] | [height]
   * @see wp-includes/js/customize-base.js for {wp.customize} 
   *
   */
  wp.customize('respheader_height', function(boo) {
    // get current value
    var oldval = this.instance('respheader_height').get();

    this.bind("saved", function() {
      // get after save value
      var newval = this.instance('respheader_height').get();

      // if value got changed. reload
      if( oldval !== parseInt(newval) ) {
        window.location.reload( true );
      }
    });
  });

  wp.customize('respheader_width', function(boo) {
    var oldval = this.instance('respheader_width').get();

    this.bind("saved", function() {
      // get after save value
      var newval = this.instance('respheader_width').get();

      // if value got changed. reload
      if( oldval !== parseInt(newval) ) {
        window.location.reload( true );
      }
    });
  });



/**
 * custom Cropper
 *
 * A media frame state for cropping source for responsive header.
 * @see wp-inlude/js/media-views.js Cropper
 * @inheritdoc Cropper
 *
 */
var cropper = wp.media.controller.State.extend({
  defaults: {
    id:          'cropper',
    title:       lex.cropImage,
    // Region mode defaults.
    toolbar:     'crop',
    content:     'crop',
    router:      false
  },

  activate: function() {
    this.frame.on( 'content:create:crop', this.createCropContent, this );
    this.frame.on( 'close', this.removeCropper, this );
    this.set('selection', new Backbone.Collection(this.frame._selection.single));
  },

  deactivate: function() {
    this.frame.toolbar.mode('browse');
  },

  createCropContent: function() {
    this.cropperView = new wp.media.view.Cropper({
      controller: this,
      attachment: this.get('selection').first()
    });
    this.cropperView.on('image-loaded', this.createCropToolbar, this);
    this.frame.content.set(this.cropperView);
  },

  removeCropper: function() {
    this.imgSelect.cancelSelection();
    this.imgSelect.setOptions({remove: true});
    this.imgSelect.update();
    this.cropperView.remove();
  },

  createCropToolbar: function() {
    var canSkipCrop, toolbarOptions, ajax, freescale;
    canSkipCrop = this.get('canSkipCrop') || false;
    freescale = this.get('freescale') || false;

    toolbarOptions = {
      controller: this.frame,
      items: {
        insert: {
          style:    'primary',
          text:     lex.cropImage,
          priority: 80,
          requires: { 
            library: false, 
            selection: false
          },

          click: function() {
            var frame = this.controller, selection = this.controller.state().get('selection').first();

            selection.set({cropDetails: this.controller.state().imgSelect.getSelection() });

            // handle button
            this.$el.text(lex.cropping);
            this.$el.attr('disabled', true);

            /** send data to handle the image, @see this.doCrop() */
            this.controller.state().doCrop( selection )
            .done( function( croppedImage ) {
              frame.trigger('cropped', croppedImage );

              // update header srcset at customizer
              wp.customize.HeaderTool.currentHeader.updateSRCSET( croppedImage.width, croppedImage.img_id, croppedImage.guid);
                frame.close();
            })
            .fail( function() {
              frame.trigger('content:error:crop');
            });
          }///end click func
        }
      }//items
    };///toobaroptions


    if ( canSkipCrop ) {
      // add skip button
      _.extend( toolbarOptions.items, {
        skip: {
          style:      'primary',
          text:       lex.skipCropping,
          priority:   70,
          requires:   { library: false, selection: false },

          click: function() {
            var frame = this.controller, selection = this.controller.state().get('selection').first();

            /** send ajax */
            this.controller.state().doCrop( selection )
            .done( function( croppedImage ) {
              frame.trigger('skippedcrop', croppedImage );

              // update srcset 
              wp.customize.HeaderTool.currentHeader.updateSRCSET(croppedImage.width, croppedImage.img_id, croppedImage.guid);
              frame.state().cropperView.remove(); 
              frame.close();
            }); //done function
          } //click func
        }
      }); //underscore extend
    }

    this.frame.toolbar.set( new wp.media.view.Toolbar(toolbarOptions) );
  },///toolbar

  /**
   * Ajax send data
   * @see _ajax.php ajax_header_crop()
   */
  doCrop: function( selection ) {
    var he = api.HeaderTool.currentHeader.get('header'), size, param;
    // {obj} crop detail or selection size
    size = selection.get('cropDetails') || {width: selection.get('width'), height: selection.get('height')};

    param = {
      nonce: selection.get('nonces').edit,
      id: selection.get('id'),// original img id
      url: selection.get('url'),
      currentHeader : he.attachment_id,// header to add the source to
      cropDetails: size
    };
    if(! this.get('canSkipCrop') || this.get('freescale') ) {
      // flag to crop
      param.crop = true;
      // pass mime for the new file metadata
      param.mime = selection.get('mime')
    } 

    return wp.ajax.post( 'custom-header-srcset', param );
  }
}); // cropper



/**
 * @callback sourceState to set library state for Add source frame
 * Sets custom suggested size
 *
 * @see wp-inlude/js/media-vews.js wp.media.controller.Library
 */
var sourceState = function(suggestedSize) {
  console.log(suggestedSize);
  return new wp.media.controller.Library({
            title:     lex.chooseImage,
            library:   wp.media.query({ type: 'image' }),
            date:      false,
            suggestedWidth: suggestedSize.width,
            suggestedHeight: suggestedSize.height // useless if  date are other than false
          });
}

/**
 * Open Add source media frame
 *
 * @see wp-inlude/js/media-vews.js media.view.MediaFrame
 * Instantiates custom cropper()
 */
jQuery(document).on('click', '#responsive_header .srcset_button', function(event) {
    event.preventDefault();

    // get sizes to crop to
    cropsize = function(attachment, controller) {
      return cropSize( attachment, controller, due_widths[0].width, due_widths[0].height, due_widths.freescale );
    };
   
    frame = wp.media({
        button : { text : lex.selectAndCrop, close: false },
        states : [
          sourceState(size),
          new cropper({
            imgSelectOptions: cropsize
          })
        ]
    });

    frame.on('select', function() {
      frame.setState('cropper');
    } );

    frame.open();
});


/**
 * Remove header source
 * 
 * @see /wp-includes/js/wp-utils.js wp.ajax.send
 * @see _ajax.php ajax_remove_header_source() 
 */

  /** @callback removeSRCSuccess On Remove success */
  function removeSRCSuccess( data ) {
    // disable Save button
    saveButt.val(wp.customize.l10n.saved).prop('disabled', true);
    // update header
    wp.customize.HeaderTool.currentHeader.updateSRCSET( data.srcWidth, data.img_id );
  }

  function removeSRCError( data ) {}

  jQuery(document).on('click', '#responsive_header .close', function() {
    var closeButt = jQuery(this),
    header = closeButt.data('header-id'),
    imgWidth = closeButt.data('src-width'),
    imgID = closeButt.data('img-id');

    // mute the source icon
    saveButt.val(wp.customize.l10n.save).prop('disabled', false);
    closeButt.parent('.holder').fadeTo('fast',.7);

    // send the source data to remove it 
    saveButt.click(function() {
      wp.ajax.send( "custom-header-source-remove", {
        success: removeSRCSuccess,
        error:   removeSRCError,
        data: {
          headerID: header,
          srcWidth: imgWidth,
          img_id  : imgID
        }
      });
    });
  });

  
/**
 * @class
 * @arguments wp.customize.HeaderTool.CurrentView
 * 
 * Customize current header view
 */
  wp.customize.HeaderTool.CurrentView = wp.customize.HeaderTool.CurrentView.extend({    
    
    /**
     * @method getHeight
     * @default
     *
     * Use it to Adjust the current image container height for Responsive Header and run custom headerEntourage() */
    getHeight: function() {

      // Actually just let the container height be

      // default hide/show No image message 
      var image = this.$el.find('.current_img');

      if (image.length) {
        this.$el.find('.inner').hide();
      } else {
        this.$el.find('.inner').show();
      }

      // call custom function
      this.headerEntourage();
    },


    /** @method addwidths to send data and get response */
    addwidths: function(id) {    
      return wp.ajax.post( "reset_header_srcset", { headerID : id } ); 
    },

    /** @method resetSrcset to reset responsive data on header change
     * @param {number} hid Header id
     */
    resetSrcset: function(hid) {
      thismodel = this.model;
      header = this.model.get("header");

      
      this.addwidths( hid ).done(function( widths ) {
        // On ajax response change srcset
        if( widths.srcset ) {
          header.srcset = widths.srcset;
        }

        // fix due widths
        header.toadd = widths.toadd;
        if( widths.toadd.length ) {
          due_widths = widths.toadd.slice(); // copy obj to break from header.toadd

        } else {
          // use fallback if due-s are over
          due_widths = fallback_width;
        }

        // flag the change
        thismodel.set("resetRespDatafor", hid );
      }); //.done
    },

    /**
     * @method headerEntourage to do many things
     */
    headerEntourage: function() {

      var 
      change      = this.model.changedAttributes(),
      sourceButt  = this.$el.find('#add_source'),
      message     = $("p.respHeaderNote"),
      actionButt  = $('button.new')
      prevUploads = wp.customize.HeaderTool.UploadsList ? wp.customize.HeaderTool.UploadsList.length : 0,
      type        = this.model.get('collection') ? this.model.get('collection').type : this.model.get('type');


      /** Do things for Uploaded headers */

      if( 'default' !== type ) {
        // un-highlight the Add New button
        actionButt.removeClass('hardsell');
        // hide the message explaining Responsive Header
        message.hide();

        /**
         * Reset the entire srcset on header change
         * Since default type has no att_id, the resetSrcset() will do the job on header type change as well, so no need to bother calling it at the else block below
         */
        var hid = this.model.get('header').attachment_id,
         newHid = change.header ? change.header.attachment_id : -1;

        if( newHid == hid ) {
          this.resetSrcset(hid);
        }

      } else {
        /** Default headers */

        // disable Add source button
        sourceButt.attr('disabled', true);

        if( 2 > prevUploads && this.model.get("choice") !== "") {
          // no previously uploaded headers, let them read homecoming message
          message.show();
          // highlight Add New button to show that sources can only be added for uploaded 
          actionButt.addClass('hardsell');

        } else {
          // uploaded headers found, hide the message assuming they had a chance to read it
          message.hide();
        }
      }///end default type
    }
  });

})( jQuery, _ );
