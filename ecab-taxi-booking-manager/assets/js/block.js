const { registerBlockType } = wp.blocks;
const { __ } = wp.i18n;
const { InspectorControls } = wp.blockEditor;
const { 
    PanelBody, 
    SelectControl, 
    TextControl, 
    ToggleControl 
} = wp.components;

registerBlockType('mptbm/booking', {
    title: __('E-Cab Booking Form', 'ecab-taxi-booking-manager'),
    icon: 'car',
    category: 'widgets',
    attributes: {
        cat: {
            type: 'string',
            default: '0'
        },
        org: {
            type: 'string',
            default: '0'
        },
        style: {
            type: 'string',
            default: 'list'
        },
        show: {
            type: 'string',
            default: '9'
        },
        pagination: {
            type: 'string',
            default: 'yes'
        },
        city: {
            type: 'string',
            default: ''
        },
        country: {
            type: 'string',
            default: ''
        },
        sort: {
            type: 'string',
            default: 'ASC'
        },
        status: {
            type: 'string',
            default: ''
        },
        paginationStyle: {
            type: 'string',
            default: 'load_more'
        },
        column: {
            type: 'string',
            default: '3'
        },
        priceBased: {
            type: 'string',
            default: 'dynamic'
        },
        progressbar: {
            type: 'string',
            default: 'yes'
        },
        map: {
            type: 'string',
            default: 'yes'
        },
        form: {
            type: 'string',
            default: 'horizontal'
        },
        tab: {
            type: 'string',
            default: 'no'
        },
        tabs: {
            type: 'string',
            default: 'distance,hourly,manual'
        }
    },

    edit: function(props) {
        const { attributes, setAttributes } = props;

        return [
            wp.element.createElement(InspectorControls, { key: 'inspector' },
                wp.element.createElement(PanelBody, {
                    title: __('Booking Form Settings', 'ecab-taxi-booking-manager'),
                    initialOpen: true
                },
                    wp.element.createElement(SelectControl, {
                        label: __('Display Style', 'ecab-taxi-booking-manager'),
                        value: attributes.style,
                        options: [
                            { label: 'List', value: 'list' },
                            { label: 'Grid', value: 'grid' }
                        ],
                        onChange: (value) => setAttributes({ style: value })
                    }),

                    wp.element.createElement(TextControl, {
                        label: __('Items to Show', 'ecab-taxi-booking-manager'),
                        value: attributes.show,
                        onChange: (value) => setAttributes({ show: value })
                    }),

                    wp.element.createElement(SelectControl, {
                        label: __('Price Based', 'ecab-taxi-booking-manager'),
                        value: attributes.priceBased,
                        options: [
                            { label: 'Dynamic', value: 'dynamic' },
                            { label: 'Manual', value: 'manual' },
                            { label: 'Fixed Hourly', value: 'fixed_hourly' }
                        ],
                        onChange: (value) => setAttributes({ priceBased: value })
                    }),

                    wp.element.createElement(SelectControl, {
                        label: __('Form Layout', 'ecab-taxi-booking-manager'),
                        value: attributes.form,
                        options: [
                            { label: 'Horizontal', value: 'horizontal' },
                            { label: 'Inline', value: 'inline' }
                        ],
                        onChange: (value) => setAttributes({ form: value })
                    }),

                    wp.element.createElement(ToggleControl, {
                        label: __('Show Pagination', 'ecab-taxi-booking-manager'),
                        checked: attributes.pagination === 'yes',
                        onChange: (value) => setAttributes({ pagination: value ? 'yes' : 'no' })
                    }),

                    wp.element.createElement(ToggleControl, {
                        label: __('Show Progress Bar', 'ecab-taxi-booking-manager'),
                        checked: attributes.progressbar === 'yes',
                        onChange: (value) => setAttributes({ progressbar: value ? 'yes' : 'no' })
                    }),

                    wp.element.createElement(ToggleControl, {
                        label: __('Show Map', 'ecab-taxi-booking-manager'),
                        checked: attributes.map === 'yes',
                        onChange: (value) => setAttributes({ map: value ? 'yes' : 'no' })
                    }),

                    wp.element.createElement(ToggleControl, {
                        label: __('Enable Tabs', 'ecab-taxi-booking-manager'),
                        checked: attributes.tab === 'yes',
                        onChange: (value) => setAttributes({ tab: value ? 'yes' : 'no' })
                    })
                )
            ),
            wp.element.createElement('div', { className: props.className },
                wp.element.createElement('div', { className: 'mptbm-block-preview' },
                    wp.element.createElement('div', { className: 'mptbm-block-placeholder' },
                        wp.element.createElement('span', { className: 'dashicons dashicons-car' }),
                        wp.element.createElement('h3', {}, __('E-Cab Booking Form', 'ecab-taxi-booking-manager')),
                        wp.element.createElement('p', {}, __('Your booking form will appear here on the frontend.', 'ecab-taxi-booking-manager'))
                    )
                )
            )
        ];
    },

    save: function() {
        return null; // Dynamic block, render handled by PHP
    }
});
