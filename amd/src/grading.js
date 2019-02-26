define(['jquery'], function($) {
    
    // selectors
    var videoPreviewThumb = ".media_thumbnail_cl";
    var videoPreviewModal = "#video_preview_modal";
    var videoPreviewBody = "#video_preview_body";

    function init() {
        $(videoPreviewThumb).click(showVideoPreview);
    }

    function showVideoPreview(event) {
        var entryId = event.target.id;
        entryId = entryId.substr(6);
        var video = $("#hidden_markup_" + entryId).html();

        if (video !== null) {
            $(videoPreviewBody).html(video);
        }
        else {
            $(videoPreviewBody).html("Sorry! Media not found.");
        }

        $(videoPreviewModal).modal("show");
    }
    
    return {init : init};
});