(function ($) {
  Drupal.behaviors.apdrHeader = {
    attach: function(context, settings) {
			setTimeout(function() {
				$('header.navbar').addClass('nav-down');
			}, 500);

			// read more button on grid pages
			if ($('.button-more').length) {
				$('.button-more').click(function() {

					if ($(this).attr('aria-expanded') === 'true') {
						$(this).text('Read More');
					} else {
						$(this).text('Read Less');
					}
				});
			}

			// make h1 contained on career path page
			$('.page-node-type-stacked-layout .main-container h1').addClass('container career-container');

			var $window = $(window);

			// floating menu on career paths
			if ($('#block-views-block-stack-floating-menu-block-1').length) {
				if ($window.width() > 1023) {
					setFloatingMenu();
				}

				$(window).resize(function() {
					if ($(window).width() > 1023) {
						setFloatingMenu();
					}	else {
						$('#block-views-block-stack-floating-menu-block-1').fadeOut('slow');
					}
				});

				var menudistance = $('#block-views-block-stack-floating-menu-block-1').offset().top-175;

				$window.scroll(function() {
			    if ($window.scrollTop() >= menudistance) {
			     $('#block-views-block-stack-floating-menu-block-1').css('top', '').addClass('fixed');
			    } else {
				    setFloatingMenu();
				    $('#block-views-block-stack-floating-menu-block-1').removeClass('fixed');
			    }
				});

				// set the starting location of the floating menu
				function setFloatingMenu() {
					var bodyMarginTop = parseInt($('body').css('margin-top')),
							bodyPaddingTop = parseInt($('body').css('padding-top')),
							leveloneHeight = $('.field--name-field-stack .field--item:nth-child(2)').outerHeight(),
							levelonepaddingTop = parseInt($('.field--name-field-stack .field--item:nth-child(2)').css('padding-top')),
							welcomeHeight = $('.field--name-field-stack .field--item:first-child').outerHeight() + bodyMarginTop + bodyPaddingTop + 750;
					$('#block-views-block-stack-floating-menu-block-1').css('top', welcomeHeight).fadeIn('slow');
				}
			}

			$('.view-stack-floating-menu .views-row a, .view-academy-quick-links .item-list li a').click(function() {
				var url = $(this).attr('href');
				$('html, body').stop(true, false).animate({
				  	scrollTop: $(url).offset().top - 150
					}, 2000);

					return false;
			});

			// Allow users mouse events to take perference over smooth scrolling.
			$('body,html').bind('scroll mousedown wheel DOMMouseScroll mousewheel keyup touchmove', function (e) {
			  if (e.which > 0 || e.type == "mousedown" || e.type == "mousewheel" || e.type == "touchmove") {
			    $("html,body").stop();
			  }
			});
    }
  }
})(jQuery);
