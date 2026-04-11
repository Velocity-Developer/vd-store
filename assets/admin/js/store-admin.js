jQuery(document).ready(function ($) {
    const $doc = $(document);
    let validationDialog = null;

    function ensureValidationDialog() {
        if (validationDialog) {
            return validationDialog;
        }

        const dialog = document.createElement('div');
        dialog.className = 'vmp-validation-dialog';
        dialog.innerHTML = [
            '<div class="vmp-validation-dialog__backdrop" data-vmp-dialog-close="1"></div>',
            '<div class="vmp-validation-dialog__panel" role="alertdialog" aria-modal="true" aria-labelledby="vmp-validation-dialog-title">',
            '<div class="vmp-validation-dialog__header">',
            '<h3 id="vmp-validation-dialog-title" class="vmp-validation-dialog__title">Field wajib belum lengkap</h3>',
            '<button type="button" class="vmp-validation-dialog__close" aria-label="Tutup" data-vmp-dialog-close="1">×</button>',
            '</div>',
            '<div class="vmp-validation-dialog__body">',
            '<p class="vmp-validation-dialog__text">Lengkapi field berikut sebelum menyimpan produk.</p>',
            '<ul class="vmp-validation-dialog__list"></ul>',
            '</div>',
            '<div class="vmp-validation-dialog__footer">',
            '<button type="button" class="button button-primary" data-vmp-dialog-close="1">Tutup</button>',
            '</div>',
            '</div>',
        ].join('');

        dialog.addEventListener('click', function (event) {
            if (event.target && event.target.getAttribute('data-vmp-dialog-close') === '1') {
                dialog.classList.remove('is-open');
            }
        });

        document.body.appendChild(dialog);
        validationDialog = dialog;
        return validationDialog;
    }

    function fieldLabel($control) {
        const explicit = String($control.attr('data-field-label') || '').trim();
        if (explicit) {
            return explicit;
        }

        const id = String($control.attr('id') || '').trim();
        if (id) {
            const label = $('label[for="' + id.replace(/"/g, '\\"') + '"]').first().text().trim();
            if (label) {
                return label.replace(/\s*\*+\s*$/, '');
            }
        }

        const wrapLabel = $control.closest('[data-field-required], .col-12, .col-md-6, .form-field, p, .cmb-row').find('label').first().text().trim();
        if (wrapLabel) {
            return wrapLabel.replace(/\s*\*+\s*$/, '');
        }

        return 'Field wajib';
    }

    function isMissingRequired($control) {
        if ($control.prop('disabled')) {
            return false;
        }

        const tag = String($control.prop('tagName') || '').toLowerCase();
        const type = String($control.attr('type') || '').toLowerCase();

        if (type === 'hidden') {
            return false;
        }

        if (type === 'checkbox') {
            return !$control.prop('checked');
        }

        if (type === 'radio') {
            const name = String($control.attr('name') || '');
            if (!name) {
                return !$control.prop('checked');
            }
            return $control.closest('form').find('input[type="radio"][name="' + name.replace(/"/g, '\\"') + '"]:checked').length === 0;
        }

        if (tag === 'select') {
            return String($control.val() || '').trim() === '';
        }

        return String($control.val() || '').trim() === '';
    }

    function collectMissingRequiredFields($form) {
        const labels = [];
        const seen = new Set();

        $form.find('[data-required="1"]').each(function () {
            const $control = $(this);
            if (!isMissingRequired($control)) {
                $control.removeClass('is-invalid');
                return;
            }

            $control.addClass('is-invalid');
            const label = fieldLabel($control);
            if (!seen.has(label)) {
                labels.push(label);
                seen.add(label);
            }
        });

        return labels;
    }

    function showValidationDialog(labels) {
        const dialog = ensureValidationDialog();
        const list = dialog.querySelector('.vmp-validation-dialog__list');
        if (list) {
            list.innerHTML = (Array.isArray(labels) ? labels : []).map(function (label) {
                return '<li>' + $('<div>').text(label).html() + '</li>';
            }).join('');
        }
        dialog.classList.add('is-open');
    }

    function fieldWrap(selector) {
        const $field = $(selector);
        if (!$field.length) {
            return $();
        }
        return $field.closest('.col-12, .col-md-6, .form-field, p, .cmb-row');
    }

    function toggleProductTypeFields() {
        const $typeSelect = $('#_store_product_type');
        if (!$typeSelect.length) {
            return;
        }

        const type = String($typeSelect.val() || 'physical');
        $('[data-show-if-product-type]').each(function () {
            const $fieldWrap = $(this);
            const expectedType = String($fieldWrap.data('show-if-product-type') || '').trim();
            if (!expectedType) {
                return;
            }

            const visible = expectedType === type;
            $fieldWrap.find('input, select, textarea').each(function () {
                const $control = $(this);
                if ($control.is('[type="hidden"]')) {
                    return;
                }

                const shouldRequire = visible && $control.attr('data-required') === '1';
                $control.attr('aria-required', shouldRequire ? 'true' : 'false');
                $control.prop('disabled', !visible);
            });

            if (visible) {
                $fieldWrap.show();
            } else {
                $fieldWrap.hide();
            }
        });
    }

    function parseAttachmentIds(value) {
        return String(value || '')
            .split(',')
            .map(function (item) { return Number(String(item).trim()); })
            .filter(function (item) { return item > 0; });
    }

    function currentPreviewItems(preview) {
        return Array.from(preview?.querySelectorAll('.vmp-media-field__item') || []).map(function (item) {
            return {
                id: Number(item.dataset.id || 0),
                url: String(item.querySelector('.vmp-media-field__image')?.getAttribute('src') || ''),
                title: String(item.querySelector('.vmp-media-field__image')?.getAttribute('alt') || ''),
            };
        }).filter(function (item) {
            return item.id > 0;
        });
    }

    function mergeItemsById(items) {
        const map = new Map();
        (Array.isArray(items) ? items : []).forEach(function (item) {
            const id = Number(item?.id || 0);
            if (id <= 0) {
                return;
            }
            map.set(id, {
                id: id,
                url: String(item.url || ''),
                title: String(item.title || ''),
            });
        });
        return Array.from(map.values());
    }

    function renderMediaPreview(preview, items, multiple, emptyText) {
        if (!preview) {
            return;
        }

        if (!Array.isArray(items) || items.length === 0) {
            preview.innerHTML = '<div class="vmp-media-field__empty text-muted small">' + emptyText + '</div>';
            return;
        }

        const gridClass = multiple
            ? 'vmp-media-field__grid'
            : 'vmp-media-field__grid vmp-media-field__grid--single';

        preview.innerHTML = '<div class="' + gridClass + '">' +
            items.map(function (item) {
                return '<div class="vmp-media-field__item" data-id="' + Number(item.id || 0) + '">' +
                    '<img src="' + String(item.url || '') + '" alt="' + String(item.title || '') + '" class="vmp-media-field__image">' +
                    '<button type="button" class="btn-close vmp-media-field__remove" aria-label="Hapus gambar"></button>' +
                    '</div>';
            }).join('') +
            '</div>';
    }

    function renderFileLinkPreview(preview, url, emptyText) {
        if (!preview) {
            return;
        }

        const value = String(url || '').trim();
        if (!value) {
            preview.innerHTML = '<div class="vmp-file-link-field__empty text-muted small">' + emptyText + '</div>';
            return;
        }

        let label = value;
        try {
            const parsed = new URL(value, window.location.origin);
            const path = String(parsed.pathname || '');
            const parts = path.split('/').filter(Boolean);
            if (parts.length) {
                label = parts[parts.length - 1];
            }
        } catch (e) {}

        preview.innerHTML = '<div class="vmp-file-link-field__summary">' +
            '<div class="vmp-file-link-field__name">' + label + '</div>' +
            '<a class="vmp-file-link-field__link" href="' + value + '" target="_blank" rel="noopener noreferrer">' + value + '</a>' +
            '</div>';
    }

    function initMediaFields() {
        if (!window.wp || !wp.media) {
            return;
        }

        const cfg = window.vmpSettings || {};
        const currentUserId = Number(cfg.currentUserId || 0);
        const canManageOptions = !!cfg.canManageOptions;

        document.querySelectorAll('.vmp-media-field').forEach(function (field) {
            const input = field.querySelector('.vmp-media-field__input');
            const preview = field.querySelector('.vmp-media-field__preview');
            const openBtn = field.querySelector('.vmp-media-field__open');
            const clearBtn = field.querySelector('.vmp-media-field__clear');
            const multiple = field.dataset.multiple === '1';
            const emptyText = preview && preview.dataset.placeholder
                ? preview.dataset.placeholder
                : 'Belum ada gambar dipilih.';

            if (!input || !preview || !openBtn || !clearBtn) {
                return;
            }

            const syncButtons = function () {
                clearBtn.disabled = String(input.value || '').trim() === '';
            };

            openBtn.addEventListener('click', function (event) {
                event.preventDefault();

                const frame = wp.media({
                    title: openBtn.dataset.title || 'Pilih Media',
                    button: {
                        text: openBtn.dataset.button || 'Gunakan file ini',
                    },
                    multiple: multiple ? 'add' : false,
                    library: {
                        type: 'image',
                        ...(currentUserId > 0 && !canManageOptions ? { author: currentUserId } : {}),
                    },
                });

                frame.on('open', function () {
                    if (currentUserId > 0 && !canManageOptions) {
                        const library = frame.state().get('library');
                        if (library && library.props) {
                            library.props.set({
                                author: currentUserId,
                                type: 'image',
                            });
                        }
                    }

                    if (multiple) {
                        const selection = frame.state().get('selection');
                        parseAttachmentIds(input.value).forEach(function (id) {
                            const attachment = wp.media.attachment(id);
                            if (attachment) {
                                attachment.fetch();
                                selection.add(attachment);
                            }
                        });
                    }
                });

                frame.on('select', function () {
                    const selection = frame.state().get('selection');
                    const items = multiple ? currentPreviewItems(preview) : [];

                    selection.each(function (attachment) {
                        const data = attachment.toJSON();
                        const imageUrl =
                            (data.sizes &&
                                (data.sizes.medium?.url ||
                                    data.sizes.thumbnail?.url ||
                                    data.sizes.full?.url)) ||
                            data.url ||
                            '';

                        if (!data.id || !imageUrl) {
                            return;
                        }

                        items.push({
                            id: Number(data.id),
                            url: imageUrl,
                            title: data.title || '',
                        });
                    });

                    const normalizedItems = multiple ? mergeItemsById(items) : items.slice(0, 1);
                    input.value = multiple
                        ? normalizedItems.map(function (item) { return item.id; }).join(',')
                        : String(normalizedItems[0]?.id || '');
                    renderMediaPreview(preview, normalizedItems, multiple, emptyText);
                    syncButtons();
                });

                frame.open();
            });

            clearBtn.addEventListener('click', function (event) {
                event.preventDefault();
                input.value = '';
                renderMediaPreview(preview, [], multiple, emptyText);
                syncButtons();
            });

            preview.addEventListener('click', function (event) {
                const removeButton = event.target.closest('.vmp-media-field__remove');
                if (!removeButton) {
                    return;
                }

                event.preventDefault();

                const item = removeButton.closest('.vmp-media-field__item');
                if (!item) {
                    return;
                }

                const itemId = Number(item.dataset.id || 0);
                if (multiple) {
                    const ids = String(input.value || '')
                        .split(',')
                        .map(function (value) { return Number(String(value).trim()); })
                        .filter(function (value) { return value > 0 && value !== itemId; });
                    input.value = ids.join(',');
                } else {
                    input.value = '';
                }

                item.remove();
                if (!preview.querySelector('.vmp-media-field__item')) {
                    renderMediaPreview(preview, [], multiple, emptyText);
                }
                syncButtons();
            });

            syncButtons();
        });
    }

    function initFileLinkFields() {
        if (!window.wp || !wp.media) {
            return;
        }

        const cfg = window.vmpSettings || {};
        const currentUserId = Number(cfg.currentUserId || 0);
        const canManageOptions = !!cfg.canManageOptions;

        document.querySelectorAll('.vmp-file-link-field').forEach(function (field) {
            const input = field.querySelector('.vmp-file-link-field__input');
            const preview = field.querySelector('.vmp-file-link-field__preview');
            const openBtn = field.querySelector('.vmp-file-link-field__open');
            const clearBtn = field.querySelector('.vmp-file-link-field__clear');
            const emptyText = preview && preview.dataset.placeholder
                ? preview.dataset.placeholder
                : 'Belum ada file dipilih.';

            if (!input || !preview || !openBtn || !clearBtn) {
                return;
            }

            const syncButtons = function () {
                clearBtn.disabled = String(input.value || '').trim() === '';
            };

            const syncPreview = function () {
                renderFileLinkPreview(preview, input.value, emptyText);
                syncButtons();
            };

            openBtn.addEventListener('click', function (event) {
                event.preventDefault();

                const frame = wp.media({
                    title: openBtn.dataset.title || 'Pilih File',
                    button: {
                        text: openBtn.dataset.button || 'Gunakan file ini',
                    },
                    multiple: false,
                    library: {
                        ...(currentUserId > 0 && !canManageOptions ? { author: currentUserId } : {}),
                    },
                });

                frame.on('open', function () {
                    if (currentUserId > 0 && !canManageOptions) {
                        const library = frame.state().get('library');
                        if (library && library.props) {
                            library.props.set({
                                author: currentUserId,
                            });
                        }
                    }
                });

                frame.on('select', function () {
                    const attachment = frame.state().get('selection').first();
                    if (!attachment) {
                        return;
                    }

                    const data = attachment.toJSON();
                    input.value = String(data.url || '');
                    syncPreview();
                });

                frame.open();
            });

            clearBtn.addEventListener('click', function (event) {
                event.preventDefault();
                input.value = '';
                syncPreview();
            });

            input.addEventListener('input', syncPreview);
            input.addEventListener('change', syncPreview);
            syncPreview();
        });
    }

    $doc.on('change', '#_store_product_type', toggleProductTypeFields);
    toggleProductTypeFields();
    initMediaFields();
    initFileLinkFields();

    const $productForm = $('#post');
    if ($productForm.length) {
        $productForm.on('submit', function (event) {
            const missingFields = collectMissingRequiredFields($productForm);
            if (!missingFields.length) {
                return;
            }

            event.preventDefault();
            showValidationDialog(missingFields);

            const firstInvalid = $productForm.find('.is-invalid:visible').first();
            if (firstInvalid.length) {
                firstInvalid.trigger('focus');
            }
        });
    }
});
