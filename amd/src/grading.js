import $ from 'jquery';
import ModalFactory from 'core/modal_factory';
import ModalKalturaView from 'local_kaltura/modal_kaltura_view';

const SELECTORS = {
    VIDEO_PREVIEW_THUMB: '.media_thumbnail_cl'
};

export const init = async () => {
    const modal = await ModalFactory.create({type: ModalKalturaView.getType()});
    registerEventListeners(modal);
};

const registerEventListeners = (modal) => {
    $(SELECTORS.VIDEO_PREVIEW_THUMB).on('click', (e) => {
        const entryid = e.currentTarget.id.substr(6);
        modal.renderEntryPlayer(entryid);
    });
};