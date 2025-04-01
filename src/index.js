import { registerPlugin } from '@wordpress/plugins';
import { TextControl, Button, TextareaControl, Notice } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { useState } from '@wordpress/element';

const MetaFieldsPanel = () => {
    const [error, setError] = useState(null);
    const [imageUrl, setImageUrl] = useState('');
    const postMeta = useSelect((select) => {
        return select('core/editor').getEditedPostAttribute('meta');
    }, []);

    const { editPost } = useDispatch('core/editor');

    const updateMetaValue = (key, value) => {
        setError(null);
        editPost({ meta: { ...postMeta, [key]: value } });
    };

    // Update image URL when meta changes
    if (postMeta?._meta_image && !imageUrl) {
        const attachment = wp.media.attachment(postMeta._meta_image);
        attachment.fetch().then(() => {
            setImageUrl(attachment.get('url'));
        });
    }

    return (
        <wp.editor.PluginDocumentSettingPanel
            name="meta-fields"
            title={__('Meta ', 'meta-fields-manager')}
            className="meta-fields-panel"
        >
            <div className="meta-fields-panel-content">
                {error && (
                    <Notice status="error" onRemove={() => setError(null)}>
                        {error}
                    </Notice>
                )}
                <TextControl
                    label={__('Title', 'meta-fields-manager')}
                    value={postMeta?._meta_title || ''}
                    onChange={(value) => updateMetaValue('_meta_title', value)}
                    className="meta-fields-title"
                />
                <TextareaControl
                    label={__('Description', 'meta-fields-manager')}
                    value={postMeta?._meta_description || ''}
                    onChange={(value) => updateMetaValue('_meta_description', value)}
                    className="meta-fields-description"
                />
                <div className="meta-fields-image-field">
                    <p className="meta-fields-label">
                        {__('Image', 'meta-fields-manager')}
                    </p>
                    <div className="meta-fields-image-upload">
                        <MediaUploadCheck>
                            <MediaUpload
                                onSelect={(media) => {
                                    updateMetaValue('_meta_image', media.id);
                                    setImageUrl(media.url);
                                }}
                                allowedTypes={['image']}
                                value={postMeta?._meta_image}
                                render={({ open }) => (
                                    <Button
                                        onClick={open}
                                        className="editor-post-featured-image__preview"
                                    >
                                        {postMeta?._meta_image
                                            ? __('Change Image', 'meta-fields-manager')
                                            : __('Set image', 'meta-fields-manager')}
                                    </Button>
                                )}
                            />
                        </MediaUploadCheck>
                        {postMeta?._meta_image && imageUrl && (
                            <div className="meta-fields-image-preview">
                                <img src={imageUrl} alt="" />
                                <Button
                                    onClick={() => {
                                        updateMetaValue('_meta_image', '');
                                        setImageUrl('');
                                    }}
                                    variant="link"
                                    isDestructive
                                >
                                    {__('Remove Image', 'meta-fields-manager')}
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </wp.editor.PluginDocumentSettingPanel>
    );
};

registerPlugin('meta-fields-manager', {
    render: MetaFieldsPanel,
    icon: 'admin-generic'
}); 