/*jslint browser: true */
jQuery(document).ready(function ($) {
	"use strict";
	// var ld_ajax is passed with wp_localize script()

	function toggle_class(id) {
		var votes = $.jStorage.get('votes') || {}, like_button, dislike_button;

		like_button = $('.like-button[data-content-id="' + id + '"]');
		dislike_button = $('.dislike-button[data-content-id="' + id + '"]');

		switch (votes[id]) {
			case 'like':
				like_button.addClass('voted').removeClass('inactive');
				dislike_button.removeClass('voted').addClass('inactive');
				break;

			case 'unlike':
				like_button.removeClass('voted').removeClass('inactive');
				dislike_button.removeClass('voted').removeClass('inactive');
				break;

			case 'dislike':
				like_button.removeClass('voted').addClass('inactive');
				dislike_button.addClass('voted').removeClass('inactive');
				break;

			case 'undislike':
				like_button.removeClass('voted').removeClass('inactive');
				dislike_button.removeClass('voted').removeClass('inactive');
				break;
		}
	}

	function toggle_action(action) {
		switch (action) {
			case 'like':
				return 'unlike';
			case 'unlike':
				return 'like';
			case 'dislike':
				return 'undislike';
			case 'undislike':
				return 'dislike';
			default:
				return null;
		}
	}

	function set_action(id) {
		var votes = $.jStorage.get('votes') || {}, action, like_button, dislike_button;

		action = votes[id];

		like_button = $('.like-button[data-content-id="' + id + '"]');
		dislike_button = $('.dislike-button[data-content-id="' + id + '"]');

		switch (action) {
			case 'like':
				like_button.data('action', 'unlike');
				break;
			case 'dislike':
				dislike_button.data('action', 'undislike');
				break;
		}
	}

	function get_popularity(old_action, new_action) {
		switch (new_action) {
			case 'like':
				return old_action === 'dislike' ? 2 : 1;
			case 'unlike':
				return -1;
			case 'dislike':
				return old_action === 'like' ? -2 : -1;
			case 'undislike':
				return 1;
			default:
				return 0;
		}
	}

	$('.like-dislike-button').each(function () {
		toggle_class($(this).data('content-id'));
		set_action($(this).data('content-id'));
	});

	$(".like-dislike-wrapper button").click(function (e) {
		var button = $(this), id, action, old_action, votes = $.jStorage.get('votes') || {}, i;

		// Retrieve from data attribute of buttons
		id = button.data("content-id");

		// Old action from local storage
		old_action = votes[id];

		// New action from data
		action = button.data("action");

		// Ajax call
		$.ajax({
			type : "post",
			url  : ld_ajax.ajax_url,
			data : {
				action		:	'dp_like_dislike', // WordPress ajax action handler
				popularity	:	get_popularity(old_action, action),
				id			:	id,
				user_action	:	action,
				old_action	:	old_action,
				nonce		:	ld_ajax.nonce
			},
			beforeSend: function () {
				var img = $('<img class="loading">'); //Equivalent: $(document.createElement('img'))
				img.attr('src', ld_ajax.loading_image);

				// Hide the existing counters
				for( i = 0; i < ld_ajax.counters.length; i++) {
					var counter_name = '.' + ld_ajax.counters[i] + '-counter';
					if ( button.siblings(counter_name).length ) {
						button.siblings(counter_name).hide();
					}
				}

				// add the loading image
				img.appendTo(button.siblings(".status"));
			},
			success: function (response) { // If vote successful
				var response_obj = JSON.parse(response);

				// Switch action to opposite (like - unlike, dislike - undislike)
				button.data('action', toggle_action(action));

				// Set the other button to opposite
				button.siblings('.like-dislike-button').not(button).data('action', button.siblings('.like-dislike-button').not(button).attr('data-action'));

				// Update vote count in HTML and show the existing counters
				for( i = 0; i < ld_ajax.counters.length; i++) {
					var counter_name = '.' + ld_ajax.counters[i] + '-counter',
						response_name = ld_ajax.counters[i] + '-count';
					if ( button.siblings(counter_name).length ) {
						button.siblings(counter_name).children(".count").html(response_obj[response_name]);
						button.siblings(counter_name).show();
					}
				}

				// Remove loading image
				button.siblings(".status").children(".loading").remove();

				// Store the value
				votes[id] = action;

				// Save to local storage
				$.jStorage.set('votes', votes);

				toggle_class(id);
			}
		});

		e.preventDefault();
	});

});