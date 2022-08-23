(function($){
  'use strict';

  // This has become such a mess over years of Gutenberg gallery HTML changes...

  $(document).ready(function(){
    $('div.gallery').each(function(){
      $(this).find('.gallery-item a').attr('data-fancybox', $(this).attr('id'));
      $('a + figcaption', this).each(function(){
        $(this).prev('a').attr('data-caption', $(this).text());
      });
    });

    $('figure.wp-block-gallery, ul.wp-block-gallery, ul.blocks-gallery-grid').each(function(){
      var randomId = 'gallery-block-' + Math.floor(Math.random() * 1000);
      $(this).attr('id', randomId);
      $(this).find('a').attr('data-fancybox', randomId);
      $('a + figcaption', this).each(function(){
        $(this).prev('a').attr('data-caption', $(this).text());
      });
    });

    $('.gallery-item a, .wp-block-gallery a, .wp-block-image a')
    // Don't grab links in image captions
    .filter((i, e) => {return !jQuery(e).parents('figcaption').length > 0})
    .fancybox({
      loop: true,
      buttons: ["zoom", "close"]
    });
  });
})(jQuery);
