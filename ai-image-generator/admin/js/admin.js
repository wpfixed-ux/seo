/**
 * AI Image Generator - Admin JavaScript
 */

(function($) {
    'use strict';

    const AIMG = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Генерация изображения для поста
            $('.aimg-generate-post-image').on('click', this.generatePostImage);

            // Замена фона товара
            $('.aimg-replace-background').on('click', this.replaceProductBackground);
        },

        generatePostImage: function(e) {
            e.preventDefault();

            const $button = $(this);
            const $metabox = $button.closest('.aimg-metabox');
            const $loader = $metabox.find('.aimg-loader');
            const $result = $metabox.find('.aimg-result');
            const postId = $button.data('post-id');

            // Показываем лоадер
            $button.prop('disabled', true);
            $loader.show();
            $result.hide();

            // AJAX запрос
            $.ajax({
                url: aimgAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aimg_generate_post_image',
                    nonce: aimgAjax.nonce,
                    post_id: postId
                },
                success: function(response) {
                    $loader.hide();
                    $button.prop('disabled', false);

                    if (response.success) {
                        $result.removeClass('error').addClass('success');
                        $result.html('<p>' + response.data.message + '</p>');
                        $result.show();

                        // Обновляем Featured Image если есть
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $result.removeClass('success').addClass('error');
                        $result.html('<p>' + response.data.message + '</p>');
                        $result.show();
                    }
                },
                error: function() {
                    $loader.hide();
                    $button.prop('disabled', false);
                    $result.removeClass('success').addClass('error');
                    $result.html('<p>' + aimgAjax.strings.error + '</p>');
                    $result.show();
                }
            });
        },

        replaceProductBackground: function(e) {
            e.preventDefault();

            const $button = $(this);
            const $metabox = $button.closest('.aimg-metabox');
            const $loader = $metabox.find('.aimg-loader');
            const $result = $metabox.find('.aimg-result');
            const attachmentId = $button.data('attachment-id');
            const backgroundType = $metabox.find('.aimg-background-type').val();

            // Показываем лоадер
            $button.prop('disabled', true);
            $loader.show();
            $result.hide();

            // AJAX запрос
            $.ajax({
                url: aimgAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aimg_replace_product_background',
                    nonce: aimgAjax.nonce,
                    attachment_id: attachmentId,
                    background_type: backgroundType
                },
                success: function(response) {
                    $loader.hide();
                    $button.prop('disabled', false);

                    if (response.success) {
                        $result.removeClass('error').addClass('success');
                        $result.html('<p>' + response.data.message + '</p>');
                        $result.show();

                        // Обновляем изображение
                        if (response.data.image_url) {
                            $metabox.find('.aimg-current-image img').attr('src', response.data.image_url);
                        }

                        // Перезагружаем через 2 секунды
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $result.removeClass('success').addClass('error');
                        $result.html('<p>' + response.data.message + '</p>');
                        $result.show();
                    }
                },
                error: function() {
                    $loader.hide();
                    $button.prop('disabled', false);
                    $result.removeClass('success').addClass('error');
                    $result.html('<p>' + aimgAjax.strings.error + '</p>');
                    $result.show();
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        AIMG.init();
    });

})(jQuery);
