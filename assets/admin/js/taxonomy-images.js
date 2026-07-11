jQuery(function ($) {
    function setPreview($field, imageId, imageUrl, alt) {
        const $preview = $field.find('.wps-term-image-preview');

        $field.find('input[name="wp_store_term_image_id"]').val(imageId || '');
        $preview.find('img').remove();
        if (imageId && imageUrl) {
            $preview.append($('<img>', {
                class: 'wps-term-image-preview__image',
                src: imageUrl,
                alt: alt || ''
            }));
            $preview.addClass('has-image');
            $field.find('.wps-term-image-remove').prop('hidden', false);
            return;
        }

        $preview.removeClass('has-image');
        $field.find('.wps-term-image-remove').prop('hidden', true);
    }

    $(document).on('click', '.wps-term-image-upload', function (event) {
        event.preventDefault();

        const $field = $(this).closest('.wps-term-image-field');
        const frame = wp.media({
            title: 'Pilih gambar',
            button: { text: 'Gunakan gambar ini' },
            library: { type: 'image' },
            multiple: false
        });

        frame.on('select', function () {
            const image = frame.state().get('selection').first().toJSON();
            const thumbnail = image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url;
            setPreview($field, image.id, thumbnail, image.alt || image.title || '');
        });

        frame.open();
    });

    $(document).on('click', '.wps-term-image-remove', function (event) {
        event.preventDefault();

        const $field = $(this).closest('.wps-term-image-field');
        setPreview($field, '', '', '');
    });

    if (window.inlineEditTax && typeof window.inlineEditTax.edit === 'function') {
        const originalEdit = window.inlineEditTax.edit;

        window.inlineEditTax.edit = function (id) {
            const termId = typeof id === 'object' ? this.getId(id) : id;
            const result = originalEdit.apply(this, arguments);
            const $source = $('#' + this.type + '-' + termId).find('.wps-term-image-column-wrap');
            const imageId = parseInt($source.data('image-id'), 10) || 0;
            const $image = $source.find('img');
            const $field = $('#edit-' + termId).find('.wps-term-image-field--quick');

            if ($field.length) {
                setPreview($field, imageId, imageId ? $image.attr('src') : '', $image.attr('alt') || '');
            }

            return result;
        };
    }
});
