(function () {
	'use strict';

	function closeHelpTooltips(except) {
		document.querySelectorAll('.atfb-help-tooltip.is-open').forEach(function (tooltip) {
			if (tooltip === except) {
				return;
			}
			tooltip.classList.remove('is-open');
			tooltip.querySelector('.atfb-help-button').setAttribute('aria-expanded', 'false');
		});
	}

	document.querySelectorAll('.atfb-help-button').forEach(function (button) {
		button.addEventListener('click', function (event) {
			var tooltip = button.closest('.atfb-help-tooltip');
			var willOpen = !tooltip.classList.contains('is-open');

			event.preventDefault();
			event.stopPropagation();
			closeHelpTooltips(tooltip);
			tooltip.classList.toggle('is-open', willOpen);
			button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
		});
	});

	document.addEventListener('click', function () {
		closeHelpTooltips(null);
	});
	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closeHelpTooltips(null);
		}
	});

	document.querySelectorAll('.atfb-publication-editor').forEach(function (editor) {
		var modeFields = editor.querySelectorAll('[name="atfb_publication_mode"]');
		var targetSelect = editor.querySelector('#atfb-standard-target');
		var customContent = editor.querySelector('.atfb-custom-content');

		function currentMode() {
			var checked = editor.querySelector('[name="atfb_publication_mode"]:checked');
			return checked ? checked.value : (modeFields[0] ? modeFields[0].value : 'custom');
		}

		function updatePublicationEditor() {
			var mode = currentMode();
			var usesStandardTarget = mode === 'standard' || mode === 'disabled';

			editor.dataset.publicationMode = mode;
			editor.querySelectorAll('.atfb-publication-panel').forEach(function (panel) {
				var visible = panel.dataset.publicationPanel === 'custom' ? mode === 'custom' : usesStandardTarget;
				panel.hidden = !visible;
			});
			if (customContent) {
				customContent.hidden = usesStandardTarget;
			}
			editor.querySelectorAll('.atfb-output-settings').forEach(function (section) {
				section.hidden = mode === 'disabled';
			});
			['atfb-mappings', 'atfb-preview'].forEach(function (id) {
				var box = document.getElementById(id);
				if (box) {
					box.hidden = mode === 'disabled';
				}
			});

			if (usesStandardTarget && targetSelect) {
				var selected = targetSelect.options[targetSelect.selectedIndex];
				var targetTypes = (selected.dataset.postTypes || '').split(',');
				editor.querySelectorAll('.atfb-custom-content input[type="checkbox"]').forEach(function (checkbox) {
					checkbox.checked = targetTypes.indexOf(checkbox.value) !== -1;
				});
			}
		}

		modeFields.forEach(function (field) {
			field.addEventListener('change', updatePublicationEditor);
		});
		if (targetSelect) {
			targetSelect.addEventListener('change', updatePublicationEditor);
		}
		updatePublicationEditor();
	});

	function updateSourceControl(control) {
		var kindSelect = control.querySelector('.atfb-source-select');
		var sourceInput = control.querySelector('.atfb-source-value');
		var selectedKind;
		var activeDetail;

		if (!kindSelect || !sourceInput) {
			return;
		}

		selectedKind = kindSelect.value;
		control.classList.toggle('is-fixed', selectedKind === 'fixed');

		control.querySelectorAll('.atfb-source-detail').forEach(function (detail) {
			var isActive = detail.getAttribute('data-source-kind') === selectedKind;
			detail.hidden = !isActive;
			detail.querySelectorAll('select, input').forEach(function (field) {
				field.disabled = !isActive;
			});
			if (isActive) {
				activeDetail = detail;
			}
		});

		if (selectedKind.indexOf('adapter:') === 0 && activeDetail) {
			sourceInput.value = activeDetail.querySelector('.atfb-adapter-field').value;
			return;
		}

		if (activeDetail && activeDetail.querySelector('.atfb-manual-key')) {
			var manualInput = activeDetail.querySelector('.atfb-manual-key');
			sourceInput.value = manualInput.getAttribute('data-source-prefix') + ':' + manualInput.value.trim();
			return;
		}

		sourceInput.value = selectedKind;
	}

	document.querySelectorAll('.atfb-source-picker').forEach(function (control) {
		updateSourceControl(control);
		control.addEventListener('change', function () {
			updateSourceControl(control);
		});
		control.addEventListener('input', function (event) {
			if (event.target.classList.contains('atfb-manual-key')) {
				updateSourceControl(control);
			}
		});
	});

	document.querySelectorAll('.atfb-meta-filters').forEach(function (container) {
		var section = container.closest('.atfb-filter-group');
		var addButton = section.querySelector('.atfb-add-meta-filter');
		var template = section.querySelector('.atfb-meta-filter-template');
		var nextIndex = container.querySelectorAll('.atfb-meta-filter-row').length;

		function updateAddButton() {
			addButton.disabled = container.querySelectorAll('.atfb-meta-filter-row').length >= 5;
		}

		function updateMetaFilterRow(row) {
			var compare = row.querySelector('select').value;
			var valueInput = row.querySelector('.atfb-meta-filter-value');
			var needsValue = compare !== 'EXISTS' && compare !== 'NOT EXISTS';

			row.classList.toggle('is-key-only', !needsValue);
			valueInput.disabled = !needsValue;
		}

		function bindMetaFilterRow(row) {
			row.querySelector('select').addEventListener('change', function () {
				updateMetaFilterRow(row);
			});
			row.querySelector('.atfb-remove-meta-filter').addEventListener('click', function () {
				row.remove();
				updateAddButton();
			});
			updateMetaFilterRow(row);
		}

		container.querySelectorAll('.atfb-meta-filter-row').forEach(bindMetaFilterRow);
		addButton.addEventListener('click', function () {
			var wrapper = document.createElement('div');
			var row;

			if (container.querySelectorAll('.atfb-meta-filter-row').length >= 5) {
				return;
			}
			wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
			row = wrapper.firstElementChild;
			nextIndex += 1;
			container.appendChild(row);
			bindMetaFilterRow(row);
			updateAddButton();
			row.querySelector('input').focus();
		});
		updateAddButton();
	});

	function formatMessage(template, format) {
		return template.replace('%s', format);
	}

	function copyText(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text);
		}

		return new Promise(function (resolve, reject) {
			var textarea = document.createElement('textarea');
			textarea.value = text;
			textarea.setAttribute('readonly', 'readonly');
			textarea.style.position = 'fixed';
			textarea.style.opacity = '0';
			document.body.appendChild(textarea);
			textarea.select();
			if (document.execCommand('copy')) {
				resolve();
			} else {
				reject(new Error('Copy failed'));
			}
			textarea.remove();
		});
	}

	function setActivePreviewPanel(dialog, panelName) {
		dialog.querySelectorAll('.atfb-preview-tab').forEach(function (tab) {
			var isActive = tab.getAttribute('data-preview-panel') === panelName;
			tab.classList.toggle('is-active', isActive);
			tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
			tab.tabIndex = isActive ? 0 : -1;
		});
		dialog.querySelectorAll('.atfb-preview-panel').forEach(function (panel) {
			panel.hidden = panel.getAttribute('data-preview-panel') !== panelName;
		});
		dialog.querySelector('.atfb-preview-actions').hidden = panelName !== 'source';
	}

	function appendPreviewText(parent, tagName, className, text) {
		var element;

		if (!text) {
			return null;
		}

		element = document.createElement(tagName);
		element.className = className;
		element.textContent = text;
		parent.appendChild(element);
		return element;
	}

	function renderReaderPreview(container, preview) {
		var header = document.createElement('header');
		var title;
		var list = document.createElement('div');

		container.textContent = '';
		header.className = 'atfb-reader-header';
		title = document.createElement(preview.home_url ? 'a' : 'span');
		title.className = 'atfb-reader-feed-title';
		title.textContent = preview.title || '';
		if (preview.home_url) {
			title.href = preview.home_url;
			title.target = '_blank';
			title.rel = 'noopener';
		}
		header.appendChild(title);
		appendPreviewText(header, 'p', 'atfb-reader-feed-description', preview.description);
		if (preview.publisher) {
			appendPreviewText(header, 'p', 'atfb-reader-feed-publisher', formatMessage(window.atfbPreviewData.publishedBy, preview.publisher));
		}
		container.appendChild(header);

		list.className = 'atfb-reader-items';
		(preview.items || []).forEach(function (item) {
			var article = document.createElement('article');
			var body = document.createElement('div');
			var heading = document.createElement('h3');
			var itemTitle = document.createElement(item.url ? 'a' : 'span');
			var meta = document.createElement('div');
			var date;
			var image;

			article.className = 'atfb-reader-item';
			body.className = 'atfb-reader-item-body';
			itemTitle.textContent = item.title || '';
			if (item.url) {
				itemTitle.href = item.url;
				itemTitle.target = '_blank';
				itemTitle.rel = 'noopener';
			}
			heading.appendChild(itemTitle);
			body.appendChild(heading);

			meta.className = 'atfb-reader-meta';
			appendPreviewText(meta, 'span', '', item.author);
			if (item.reviewer) {
				appendPreviewText(meta, 'span', '', formatMessage(window.atfbPreviewData.reviewedBy, item.reviewer));
			}
			if (item.date) {
				date = new Date(item.date);
				appendPreviewText(meta, 'time', '', Number.isNaN(date.getTime()) ? item.date : date.toLocaleString());
			}
			if (meta.childNodes.length) {
				body.appendChild(meta);
			}
			appendPreviewText(body, 'p', 'atfb-reader-summary', item.summary);
			if (item.source_name || item.source_url) {
				var source = document.createElement(item.source_url ? 'a' : 'span');
				source.className = 'atfb-reader-source';
				source.textContent = formatMessage(window.atfbPreviewData.source, item.source_name || item.source_url);
				if (item.source_url) {
					source.href = item.source_url;
					source.target = '_blank';
					source.rel = 'noopener';
				}
				body.appendChild(source);
			}
			article.appendChild(body);

			if (item.image) {
				image = document.createElement('img');
				image.src = item.image;
				image.alt = '';
				image.loading = 'lazy';
				image.addEventListener('error', function () {
					image.hidden = true;
				});
				article.appendChild(image);
			}
			list.appendChild(article);
		});
		container.appendChild(list);
	}

	document.querySelectorAll('.atfb-preview-open').forEach(function (button) {
		var metaBox = button.closest('.postbox');
		var dialog = metaBox ? metaBox.querySelector('.atfb-preview-dialog') : null;
		var form = document.getElementById('post');
		var status;
		var output;
		var copyButton;
		var readerPreview;

		if (!dialog || !form || typeof window.atfbPreviewData === 'undefined') {
			return;
		}

		status = dialog.querySelector('.atfb-preview-status');
		output = dialog.querySelector('.atfb-preview-output code');
		copyButton = dialog.querySelector('.atfb-preview-copy');
		readerPreview = dialog.querySelector('.atfb-reader-preview');

		dialog.querySelectorAll('.atfb-preview-tab').forEach(function (tab) {
			tab.addEventListener('click', function () {
				setActivePreviewPanel(dialog, tab.getAttribute('data-preview-panel'));
			});
		});

		button.addEventListener('click', function () {
			var formData = new FormData(form);

			formData.set('action', 'atfb_preview_feed');
			formData.set('nonce', window.atfbPreviewData.nonce);
			formData.set('post_id', button.getAttribute('data-post-id'));
			status.textContent = window.atfbPreviewData.loading;
			status.classList.remove('is-error', 'is-success');
			output.textContent = '';
			readerPreview.textContent = '';
			copyButton.disabled = true;
			button.disabled = true;
			setActivePreviewPanel(dialog, 'reader');

			if (typeof dialog.showModal === 'function') {
				dialog.showModal();
			} else {
				dialog.setAttribute('open', 'open');
			}

			fetch(window.atfbPreviewData.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: new URLSearchParams(formData)
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (response) {
					if (!response.success) {
						throw new Error(response.data && response.data.message ? response.data.message : window.atfbPreviewData.error);
					}

					output.textContent = response.data.body;
					renderReaderPreview(readerPreview, response.data.preview);
					status.textContent = formatMessage(window.atfbPreviewData.status, response.data.format);
					status.classList.add('is-success');
					copyButton.disabled = false;
				})
				.catch(function (error) {
					status.textContent = error.message || window.atfbPreviewData.error;
					status.classList.add('is-error');
				})
				.finally(function () {
					button.disabled = false;
				});
		});

		dialog.querySelector('.atfb-preview-close').addEventListener('click', function () {
			dialog.close();
		});

		dialog.addEventListener('click', function (event) {
			if (event.target === dialog) {
				dialog.close();
			}
		});

		copyButton.addEventListener('click', function () {
			copyText(output.textContent).then(function () {
				status.textContent = window.atfbPreviewData.copied;
				status.classList.remove('is-error');
				status.classList.add('is-success');
			}).catch(function () {
				status.textContent = window.atfbPreviewData.copyFailed;
				status.classList.remove('is-success');
				status.classList.add('is-error');
			});
		});
	});
}());
