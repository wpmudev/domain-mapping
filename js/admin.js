(function($) {
	$(document).ready(function() {
		var $domains = $('.domainmapping-domains');

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
							alert(response.data.message);
						}
					}

					if (response.data.hide_form) {
						wrapper.addClass('domainmapping-form-hidden');
					}

					$('a.domainmapping-need-revalidate').click();
				});
			} else {
				alert(domainmapping.message.empty);
			}

			return false;
		});

		$domains.on('click', 'a.domainmapping-map-remove', function() {
			var $self = $(this),
				parent = $self.parent(),
				wrapper = $self.parents('.domainmapping-domains-wrapper');

			if (confirm(domainmapping.message.unmap)) {
				$.get($self.attr('href'), {}, function(response) {
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

		$('a.domainmapping-need-revalidate').click();

		$('.domainmapping-tab-switch-js a').click(function() {
			var $this = $(this),
				tab = $this.attr('href');

			$this.parents('.domainmapping-tab-switch').find('a.active').removeClass('active');
			$this.addClass('active');

			$this.parents('#domainmapping-content').find('.domainmapping-tab.active').removeClass('active');
			$(tab).addClass('active');

			$this.parents('form').find('#domainmapping-active-tab').val(tab);

			return false;
		});

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
							alert(response.data.message);
						}
					}
				});
			} else {
				alert(domainmapping.message.empty);
			}

			return false;
		});

		$('.domainmapping-form-results').on('click', '.domainmapping-purchase-link', function() {
			var $this = $(this),
				tab = $this.parents('.domainmapping-tab'),
				step = tab.find('#domainmapping-box-purchase-domain');

			$.get($this.attr('href'), {}, function(response) {
				if (response.success == undefined) {
					return;
				}

				if (response.success) {
					if (step.length == 0) {
						tab.append(response.data.html);
					} else {
						step.replaceWith(response.data.html);
					}

					step = tab.find('#domainmapping-box-purchase-domain');
					if ($.payment != undefined) {
						step.find('#card_number').payment('restrictNumeric').payment('formatCardNumber');
						step.find('#card_expiration').payment('formatCardExpiry');
						step.find('#card_cvv2').payment('formatCardCVC');
					}
				}
			});
			return false;
		});

		$('.domainmapping-tab').on('submit', '#domainmapping-box-purchase-domain', function() {
			var $this = $(this),
				card_number = $this.find('#card_number').val(),
				card_expiry = $this.find('#card_expiration').payment('cardExpiryVal'),
				card_type = null;

			if (!$.payment.validateCardNumber(card_number)) {
				alert(domainmapping.message.invalid.card_number);
				return false;
			}

			if (!card_expiry.month || !card_expiry.year || !$.payment.validateCardExpiry(card_expiry.month, card_expiry.year)) {
				alert(domainmapping.message.invalid.card_expiry);
				return false;
			}

			card_type = $.payment.cardType(card_number);
			$this.find('#card_type').val(card_type);
			if (card_type === null) {
				alert(domainmapping.message.invalid.card_type);
				return false;
			}

			if (!$.payment.validateCardCVC($this.find('#card_cvv2').val(), card_type)) {
				alert(domainmapping.message.invalid.card_cvv);
				return false;
			}

			return false;
		});
	});
})(jQuery);