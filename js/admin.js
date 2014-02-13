(function($) {
	function cleanup_alert(callback) {
		$('#domainmapping-ppp, #domainmapping-ppp-overlay').fadeOut(50, function() {
			$('#domainmapping-ppp').remove();
			$('#domainmapping-ppp-overlay').remove();
		});

		if ($.isFunction(callback)) {
			callback();
		}
	}

	function show_alert(msg, callback, classes) {
		var ppp, body, footer, overflow, close;

		body = $('<div id="domainmapping-ppp-body"></div>');
		body.html(msg);

		close = $('<button type="button" class="button domainmapping-button"></button>');
		close.append(domainmapping.button.close);
		close.click(function() {
			cleanup_alert(callback);
		});

		footer = $('<div id="domainmapping-ppp-footer"></div>');
		footer.append(close);

		ppp = $('<div id="domainmapping-ppp"></div>');
		ppp.addClass(classes);
		ppp.append(body);
		ppp.append(footer);

		overflow = $('<div id="domainmapping-ppp-overlay"></div>"');

		cleanup_alert();
		$('body').append(ppp, overflow);

		ppp.css({
			'margin-left': '-' + (ppp.outerWidth() / 2) + 'px',
			'margin-top': '-' + (ppp.outerHeight() / 2) + 'px'
		});

		$('#domainmapping-ppp, #domainmapping-ppp-overlay').fadeIn(50);

		close.focus();
	};

	function show_success(msg, callback) {
		show_alert(msg, callback, 'domainmapping-ppp-success');
	};

	function show_error(msg, callback) {
		show_alert(msg, callback, 'domainmapping-ppp-error');
	};

	$(document).ready(function() {
		var $domains = $('.domainmapping-domains');

		$('#domainmapping-front-mapping select').change(function() {
			var form = $(this).parents('form');
			$.post(form.attr('action'), form.serialize());
		});

		$('#domainmapping-form-map-domain').submit(function() {
			var self = this,
				$self = $(self),
				domain = $.trim($self.find('.domainmapping-input-domain').val()),
				wrapper = $self.parents('.domainmapping-domains-wrapper');

			if (domain) {
				wrapper.addClass('domainmapping-domains-wrapper-locked');
				$.post($self.attr('action'), $self.serialize(), function(response) {
					wrapper.removeClass('domainmapping-domains-wrapper-locked');

					if (response.success == undefined) {
						return;
					}

					if (response.success) {
						$(response.data.html).insertBefore($self.parent());
						self.reset();
					} else {
						if (response.data.message) {
							show_error(response.data.message);
						}
					}

					if (response.data.hide_form) {
						wrapper.addClass('domainmapping-form-hidden');
					}

					$('a.domainmapping-need-revalidate').click();
				});
			} else {
				show_error(domainmapping.message.empty);
			}

			return false;
		});

		$domains.on('click', 'a.domainmapping-map-remove', function() {
			var $self = $(this),
				parent = $self.parent(),
				wrapper = $self.parents('.domainmapping-domains-wrapper');

			if (confirm(domainmapping.message.unmap)) {
				$.get($self.attr('data-href'), {}, function(response) {
					parent.fadeOut(300, function() {
						parent.remove();
						if (response && response.data && response.data.show_form) {
							wrapper.removeClass('domainmapping-form-hidden');
						}
					});
				});
			}

			return false;
		});

		$domains.on('click', 'a.domainmapping-map-state', function() {
			var $self = $(this),
				parent = $self.parent();

			$self.hide();
			parent.addClass('domainmapping-wait-status-refresh');

			$.get($self.attr('href'), {}, function(response) {
				parent.removeClass('domainmapping-wait-status-refresh');
				if (response.success != undefined && response.success) {
					$self.replaceWith(response.data.html);
				}
				$self.show();
			});

			return false;
		});

		$domains.on('click', 'a.domainmapping-map-primary', function() {
			var $this = $(this), message;

			if ($this.hasClass('icon-star-empty')) {
				message = $this.parents('li').find('a.domainmapping-map-state').hasClass('domainmapping-valid-domain')
					? domainmapping.message.valid_selection
					: domainmapping.message.invalid_selection;

				if (confirm(message)) {
					$domains.find('a.domainmapping-map-primary.icon-star').toggleClass('icon-star icon-star-empty');
					$this.toggleClass('icon-star icon-star-empty');
					$.get($this.attr('data-select-href'));
				}
			} else {
				if (confirm(domainmapping.message.deselect)) {
					$this.toggleClass('icon-star icon-star-empty');
					$.get($this.attr('data-deselect-href'));
				}
			}

			return false;
		});

		$('a.domainmapping-need-revalidate').click();

		$('.domainmapping-reseller-switch').change(function() {
			$('.domainmapping-reseller-settings').hide();
			$('#reseller-' + $(this).val()).show();
		});

		$('#domainmapping-check-domain-form').submit(function() {
			var $self = $(this),
				domain = $.trim($self.find('.domainmapping-input-domain').val()),
				wrapper = $self.parents('.domainmapping-domains-wrapper');

			if (domain) {
				wrapper.addClass('domainmapping-domains-wrapper-locked');
				$.post($self.attr('action'), $self.serialize(), function(response) {
					wrapper.removeClass('domainmapping-domains-wrapper-locked');

					if (response.success == undefined) {
						return;
					}

					if (response.success) {
						wrapper.find('.domainmapping-form-results').html(response.data.html);
					} else {
						if (response.data.message) {
							show_error(response.data.message);
						}
					}
				});
			} else {
				show_error(domainmapping.message.empty);
			}

			return false;
		});

		$('.domainmapping-tab').on('submit', '#domainmapping-iframe-form', function() {
			var $this = $(this),
				wrapper = $this.parents('.domainmapping-domains-wrapper'),
				card_number = $this.find('#card_number').val(),
				card_expiry = $this.find('#card_expiration').payment('cardExpiryVal'),
				card_type = null;

			if (!$.payment.validateCardNumber(card_number)) {
				show_error(domainmapping.message.invalid.card_number);
				return false;
			}

			if (!card_expiry.month || !card_expiry.year || !$.payment.validateCardExpiry(card_expiry.month, card_expiry.year)) {
				show_error(domainmapping.message.invalid.card_expiry);
				return false;
			}

			card_type = $.payment.cardType(card_number);
			$this.find('#card_type').val(card_type);
			if (card_type === null) {
				show_error(domainmapping.message.invalid.card_type);
				return false;
			}

			if (!$.payment.validateCardCVC($this.find('#card_cvv2').val(), card_type)) {
				show_error(domainmapping.message.invalid.card_cvv);
				return false;
			}

			wrapper.addClass('domainmapping-domains-wrapper-locked');

			return true;
		});

		if ($.payment != undefined) {
			$('#domainmapping-box-iframe #card_number').payment('restrictNumeric').payment('formatCardNumber');
			$('#domainmapping-box-iframe #card_expiration').payment('formatCardExpiry');
			$('#domainmapping-box-iframe #card_cvv2').payment('formatCardCVC');
		}
	});
})(jQuery);