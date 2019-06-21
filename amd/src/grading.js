define(['jquery', 'core/modal_factory'], function($, ModalFactory) {

    var SELECTORS = {
        VIDEO_PREVIEW_THUMB : '.media_thumbnail_cl',
    };

    var _modal = null;

    var _showVideoPreview = function(event) {
        var entryId = event.target.id;
        entryId = entryId.substr(6);
        var video = '<div class="embed-responsive embed-responsive-item embed-responsive-16by9">';
        video += $("#hidden_markup_" + entryId).html();
        video += '</div>';
        _modal.setBody(video);
        _modal.show();
    };

    var _setModal = function(modal) {
        _modal = modal;
    };

    var init = function() {
        // create video preview modal
        if (_modal === null) {
            var videoPreviewPromise = ModalFactory.create({large: true});
            videoPreviewPromise.then(_setModal);
        }
        // register event listeners
        $(SELECTORS.VIDEO_PREVIEW_THUMB).click(_showVideoPreview);
    };

    return {
        init : init
    };
});