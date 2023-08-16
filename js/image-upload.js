jQuery(document).ready(function($) {
    $('.upload-image-button').click(function(e) {
        e.preventDefault();
        var targetInput = $('#' + $(this).data('target'));
        var imageFrame = wp.media({
            title: 'Select Image',
            multiple: false,
            library: {
                type: 'image'
            }
        });

        imageFrame.open();

        imageFrame.on('select', function() {
            var image = imageFrame.state().get('selection').first().toJSON();
            targetInput.val(image.url);
        });
    });
});
