import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { TextControl, Button, TextareaControl, Notice } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { useState } from '@wordpress/element';

const MetaFieldsPanel = () => {
    const [error, setError] = useState(null);
    const postMeta = useSelect((select) => {
        return select('core/editor').getEditedPostAttribute('meta');
    }, []);

    const { editPost } = useDispatch('core/editor');

    const validateImageUrl = async (url) => {
        try {
            const response = await fetch(`${window.metaFieldsManager.restUrl}validate-image`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.metaFieldsManager.nonce
                },
                body: JSON.stringify({ url })
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.message || __('Invalid image URL', 'meta-fields-manager'));
            }

            return true;
        } catch (error) {
            setError(error.message);
            return false;
        }
    };

    const updateMetaValue = async (key, value) => {
        setError(null);
        
        if (key === '_meta_image' && value) {
            const isValid = await validateImageUrl(value);
            if (!isValid) {
                return;
            }
        }

        editPost({ meta: { ...postMeta, [key]: value } });
    };

    return (
        <PluginDocumentSettingPanel
            name="meta-fields-panel"
            title={__('Meta', 'meta-fields-manager')}
            className="meta-fields-panel"
        >
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
                            onSelect={(media) => updateMetaValue('_meta_image', media.url)}
                            allowedTypes={['image']}
                            value={postMeta?._meta_image}
                            render={({ open }) => (
                                <Button
                                    onClick={open}
                                    variant="secondary"
                                    className="editor-post-featured-image__toggle"
                                >
                                    {postMeta?._meta_image
                                        ? __('Change Image', 'meta-fields-manager')
                                        : __('Add Image', 'meta-fields-manager')}
                                </Button>
                            )}
                        />
                    </MediaUploadCheck>
                    {postMeta?._meta_image && (
                        <div className="meta-fields-image-preview">
                            <img src={postMeta._meta_image} alt="" />
                            <Button
                                onClick={() => updateMetaValue('_meta_image', '')}
                                variant="link"
                                isDestructive
                            >
                                {__('Remove Image', 'meta-fields-manager')}
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </PluginDocumentSettingPanel>
    );
};

registerPlugin('meta-fields-manager', {
    render: MetaFieldsPanel,
    icon: 'admin-generic'
}); 