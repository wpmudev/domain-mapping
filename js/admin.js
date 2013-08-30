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

		$('#dommainmapping-check-domain-form').submit(function() {
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
	});
})(jQuery);