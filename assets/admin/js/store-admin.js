jQuery(document).ready(function ($) {
    const $doc = $(document);

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
        const $weightWrap = fieldWrap('#_store_weight_kg');
        const $fileWrap = fieldWrap('#_store_digital_file');

        if (type === 'digital') {
            $weightWrap.hide();
            $fileWrap.show();
        } else {
            $weightWrap.show();
            $fileWrap.hide();
        }
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

    $doc.on('change', '#_store_product_type', toggleProductTypeFields);
    toggleProductTypeFields();
    initMediaFields();
});
