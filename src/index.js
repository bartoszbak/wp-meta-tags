import { registerPlugin } from '@wordpress/plugins';
import { TextControl, Button, TextareaControl, Notice, Popover } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { useState, useEffect, useRef } from '@wordpress/element';
import { PluginPostStatusInfo } from '@wordpress/editor';
import { Icon, closeSmall } from '@wordpress/icons';

const MetaFieldsPanel = () => {
    const [error, setError] = useState(null);
    const [imageUrl, setImageUrl] = useState('');
    const [hasImage, setHasImage] = useState(false);
    const [isPopoverOpen, setIsPopoverOpen] = useState(false);
    const [buttonClicked, setButtonClicked] = useState(false);
    const buttonRef = useRef();
    const popoverRef = useRef();
    
    const postMeta = useSelect((select) => {
        return select('core/editor').getEditedPostAttribute('meta');
    }, []);

    const { editPost } = useDispatch('core/editor');

    // Set initial state based on meta
    useEffect(() => {
        const imageId = postMeta?._meta_image;
        if (imageId && imageId > 0) {
            setHasImage(true);
            if (!imageUrl) {
                const attachment = wp.media.attachment(imageId);
                attachment.fetch().then(() => {
                    setImageUrl(attachment.get('url'));
                });
            }
        } else {
            setHasImage(false);
            setImageUrl('');
        }
    }, [postMeta?._meta_image]);

    // Add a click outside handler to properly close the popover
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (
                isPopoverOpen && 
                buttonRef.current && 
                popoverRef.current && 
                !buttonRef.current.contains(event.target) && 
                !popoverRef.current.contains(event.target)
            ) {
                setIsPopoverOpen(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [isPopoverOpen]);

    // Reset button clicked state when popover closes
    useEffect(() => {
        if (!isPopoverOpen) {
            setButtonClicked(false);
        }
    }, [isPopoverOpen]);

    const updateMetaValue = (key, value) => {
        setError(null);
        editPost({ meta: { ...postMeta, [key]: value } });
    };

    const handleImageSelect = (media) => {
        console.log('Image selected:', media);
        updateMetaValue('_meta_image', media.id);
        setImageUrl(media.url);
        setHasImage(true);
    };

    const handleImageRemove = () => {
        updateMetaValue('_meta_image', 0);
        setImageUrl('');
        setHasImage(false);
    };

    const handleImageChange = () => {
        // Open the media library to select a new image
        const mediaFrame = wp.media({
            title: __('Select or Upload Meta Image', 'meta-fields-manager'),
            button: {
                text: __('Use this image', 'meta-fields-manager')
            },
            multiple: false
        });

        mediaFrame.on('select', () => {
            const attachment = mediaFrame.state().get('selection').first().toJSON();
            handleImageSelect(attachment);
            // Ensure popover stays open
            setIsPopoverOpen(true);
        });

        mediaFrame.open();
    };

    return (
        <PluginPostStatusInfo>
            <div className="components-flex">
                <span className="editor-post-panel__row-label ">{__('SEO fields:', 'meta-fields-manager')}</span>
                <span className="editor-post-panel__row-control ">
                    <Button
                        ref={buttonRef}
                        onClick={() => {
                            console.log('Button clicked, current state:', isPopoverOpen);
                            setIsPopoverOpen(!isPopoverOpen);
                        }}
                        isPressed={isPopoverOpen}
                        variant="tertiary"
                        className=" is-compact is-tertiary"
                    >
                        {__('Edit', 'meta-fields-manager')}
                    </Button>
                </span>
            </div>
            
            {isPopoverOpen && (
                <Popover
                    Ref={buttonRef} // ✅ This is the correct prop name
                    className="meta-fields-popover components-popover components-dropdown__content editor-post-parent__panel-dialog is-positioned"
                    noArrow={true}
                    onClose={() => setIsPopoverOpen(false)}
                    focusOnMount={false}
                    placement="right-end"
                    offset={36}
                    expandOnMobile={true}
                >
                    <div className="meta-fields-panel-content">
                        <div className="meta-fields-panel-header">
                            <h2>Meta fields</h2>
                            <button 
                                type="button" 
                                className="components-button block-editor-inspector-popover-header__action is-small has-icon" 
                                aria-label="Close"
                                onClick={() => setIsPopoverOpen(false)}
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
                                    <path d="M12 13.06l3.712 3.713 1.061-1.06L13.061 12l3.712-3.712-1.06-1.06L12 10.938 8.288 7.227l-1.061 1.06L10.939 12l-3.712 3.712 1.06 1.061L12 13.061z"></path>
                                </svg>
                            </button>
                        </div>
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
                            rows={3}
                        />
                        <TextControl
                            label={__('Author', 'meta-fields-manager')}
                            value={postMeta?._meta_author || ''}
                            onChange={(value) => updateMetaValue('_meta_author', value)}
                            className="meta-fields-author"
                        />
                        <TextControl
                            label={__('Keywords', 'meta-fields-manager')}
                            value={postMeta?._meta_keywords || ''}
                            onChange={(value) => updateMetaValue('_meta_keywords', value)}
                            className="meta-fields-keywords"
                            help={__('Separate keywords with comma.', 'meta-fields-manager')}
                        />
                        <div className="meta-fields-image-field">
                            <p className="meta-fields-label">
                                {__('Image', 'meta-fields-manager')}
                            </p>
                            <div className="meta-fields-image-upload">
                                {!hasImage && (
                                    <MediaUploadCheck>
                                        <Button
                                            onClick={() => {
                                                console.log('Set image button clicked');
                                                // Use the WordPress media library API directly
                                                const mediaFrame = wp.media({
                                                    title: __('Select or Upload Meta Image', 'meta-fields-manager'),
                                                    button: {
                                                        text: __('Use this image', 'meta-fields-manager')
                                                    },
                                                    multiple: false
                                                });

                                                mediaFrame.on('select', () => {
                                                    const attachment = mediaFrame.state().get('selection').first().toJSON();
                                                    handleImageSelect(attachment);
                                                    // Ensure popover stays open
                                                    setIsPopoverOpen(true);
                                                });

                                                mediaFrame.open();
                                            }}
                                            className="is-compact is-tertiary"
                                        >
                                            {__('Set image', 'meta-fields-manager')}
                                        </Button>
                                    </MediaUploadCheck>
                                )}
                                {hasImage && imageUrl && (
                                    <div className="meta-fields-image-preview">
                                        <div className="meta-fields-image-preview-inner" style={{backgroundImage: `url(${imageUrl})`}}>
                                            {/* <img src={imageUrl} alt="" /> */}
                                        </div>
                                        <p className="meta-fields-helper-text">{__('Recommended resolution: 1200x628px', 'meta-fields-manager')}</p>
                                        <div className="meta-fields-image-actions">
                                            <Button
                                                onClick={() => {
                                                    // Use the WordPress media library API directly
                                                    const mediaFrame = wp.media({
                                                        title: __('Select or Upload Meta Image', 'meta-fields-manager'),
                                                        button: {
                                                            text: __('Use this image', 'meta-fields-manager')
                                                        },
                                                        multiple: false
                                                    });

                                                    mediaFrame.on('select', () => {
                                                        const attachment = mediaFrame.state().get('selection').first().toJSON();
                                                        handleImageSelect(attachment);
                                                        // Ensure popover stays open
                                                        setIsPopoverOpen(true);
                                                    });

                                                    mediaFrame.open();
                                                }}
                                                variant="secondary"
                                                className="meta-fields-change-image is-compact"
                                            >
                                                {__('Change', 'meta-fields-manager')}
                                            </Button>
                                            <Button
                                                onClick={handleImageRemove}
                                                isDestructive
                                                className="meta-fields-remove-image is-compact "
                                            >
                                                {__('Remove', 'meta-fields-manager')}
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </Popover>
            )}
        </PluginPostStatusInfo>
    );
};

// Register the plugin in the Document sidebar
registerPlugin('meta-fields-manager', {
    render: () => {
        // Get the current post type
        const postType = useSelect((select) => {
            return select('core/editor').getCurrentPostType();
        }, []);

        // Only render the plugin for posts and pages
        if (postType !== 'post' && postType !== 'page') {
            return null;
        }

        return <MetaFieldsPanel />;
    },
    icon: 'admin-generic'
});