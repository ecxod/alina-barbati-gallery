(function (blocks, element, components, blockEditor, i18n, serverSideRender) {
    const el = element.createElement;
    const __ = i18n.__;
    const InspectorControls = blockEditor.InspectorControls;
    const PanelBody = components.PanelBody;
    const RangeControl = components.RangeControl;
    const TextControl = components.TextControl;
    const ServerSideRender = serverSideRender;

    blocks.registerBlockType('alina-barbati-gallery/gallery', {
        edit: function (props) {
            const attributes = props.attributes;

            return el(
                element.Fragment,
                {},
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        {
                            title: __('Barbati Gallery Settings', 'alina-barbati-gallery'),
                            initialOpen: true
                        },
                        el(RangeControl, {
                            label: __('Limit', 'alina-barbati-gallery'),
                            min: 0,
                            max: 60,
                            value: attributes.limit || 0,
                            onChange: function (value) {
                                props.setAttributes({ limit: value || 0 });
                            }
                        }),
                        el(RangeControl, {
                            label: __('Mobile columns', 'alina-barbati-gallery'),
                            min: 1,
                            max: 3,
                            value: attributes.columns_mobile || 2,
                            onChange: function (value) {
                                props.setAttributes({ columns_mobile: value || 2 });
                            }
                        }),
                        el(RangeControl, {
                            label: __('Tablet columns', 'alina-barbati-gallery'),
                            min: 1,
                            max: 4,
                            value: attributes.columns_tablet || 2,
                            onChange: function (value) {
                                props.setAttributes({ columns_tablet: value || 2 });
                            }
                        }),
                        el(RangeControl, {
                            label: __('Desktop columns', 'alina-barbati-gallery'),
                            min: 1,
                            max: 6,
                            value: attributes.columns_desktop || 4,
                            onChange: function (value) {
                                props.setAttributes({ columns_desktop: value || 4 });
                            }
                        }),
                        el(TextControl, {
                            label: __('Source endpoint override', 'alina-barbati-gallery'),
                            value: attributes.source_endpoint || '',
                            onChange: function (value) {
                                props.setAttributes({ source_endpoint: value || '' });
                            }
                        }),
                        el(TextControl, {
                            label: __('Image target URL override', 'alina-barbati-gallery'),
                            value: attributes.target_url || '',
                            onChange: function (value) {
                                props.setAttributes({ target_url: value || '' });
                            }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'alina-barbati-gallery/gallery',
                    attributes: attributes
                })
            );
        },
        save: function () {
            return null;
        }
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.blockEditor,
    window.wp.i18n,
    window.wp.serverSideRender
);
