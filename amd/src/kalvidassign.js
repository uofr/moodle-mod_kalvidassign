import $ from 'jquery';

import Notification from 'core/notification';
import {subscribe} from 'core/pubsub';
import {get_string as getString} from 'core/str';

import ModalFactory from 'core/modal_factory';
import ModalVideoPicker from 'local_kaltura/modal_video_picker';
import ModalVideoPickerEvents from 'local_kaltura/modal_video_picker_events';
import ModalKalturaUpload from 'local_kaltura/modal_kaltura_upload';

import KalturaEvents from 'local_kaltura/kaltura_events';
import KalturaAjax from 'local_kaltura/kaltura_ajax';

const SELECTORS = {
    OPEN_VIDEO_PICKER: '[data-action="add-media"]',
    OPEN_UPLOAD_MODAL: '[data-action="upload"]',
    ENTRY_ID: '#entry_id',
    ENTRY_THUMBNAIL: '#media_thumbnail',
    SELECTED_ENTRY_HEADER: '[data-region="selected-entry-header"]',
    SUBMIT: '#submit_media'
};

export const init = async (contextid, entryid, entryname, entrythumbnail, hasCe) => {
    try {
        const [modal, uploadModal] = await Promise.all([
            ModalFactory.create({type: ModalVideoPicker.getType()}),
            ModalFactory.create({type: ModalKalturaUpload.getType()})
        ]);

        modal.contextid = contextid;
        modal.selectedEntryId = entryid;
        modal.selectedEntryName = entryname;
        modal.selectedEntryThumbnail = entrythumbnail;
        modal.hasCe = hasCe;

        uploadModal.contextid = contextid;

        registerEventListeners(modal, uploadModal, contextid);
    } catch (error) {
        Notification.exception(error);
    }
};

const registerEventListeners = (modal, uploadModal, contextid) => {

    $(SELECTORS.OPEN_VIDEO_PICKER).on('click', () => {
        modal.show();
    });

    $(SELECTORS.OPEN_UPLOAD_MODAL).on('click', (e) => {
        e.preventDefault();
        const uploadFormType = $(e.currentTarget).attr('data-upload-type');
        uploadModal.renderUploadForm(uploadFormType);
        uploadModal.show();
    });

    subscribe(ModalVideoPickerEvents.entrySelected, async (data) => {
        updateSubmission(data.entryId, data.entryName, data.entryThumbnail);
    });

    subscribe(KalturaEvents.uploadComplete, async (entryid) => {
        uploadModal.hide();
        const entry = await KalturaAjax.getEntry(contextid, entryid);
        updateSubmission(entry.id, entry.name, entry.thumbnailUrl);

        modal.selectedEntryId = entry.id;
        modal.selectedEntryName = entry.name;
        modal.selectedEntryThumbnail = entry.thumbnailUrl;
    });

};

const updateSubmission = async (entryid, name, thumbnailUrl) => {
    const selectedEntryText = await getString('selected_entry', 'local_kaltura', name);
    $(SELECTORS.ENTRY_ID).val(entryid);
    $(SELECTORS.ENTRY_THUMBNAIL).attr('src', thumbnailUrl);
    $(SELECTORS.SELECTED_ENTRY_HEADER).text(selectedEntryText);
    $(SELECTORS.SUBMIT).prop('disabled', false);
};