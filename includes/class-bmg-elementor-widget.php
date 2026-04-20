<?php
defined( 'ABSPATH' ) || exit;

/**
 * Elementor widget for the [bmg_map] shortcode.
 *
 * Registered only when Elementor is active (via the elementor/widgets/register
 * hook). Delegates all rendering to BMG_Shortcode::render() so behaviour is
 * identical across shortcode, Gutenberg block, and this widget.
 */
class BMG_Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name(): string {
		return 'bmg-interactive-map';
	}

	public function get_title(): string {
		return esc_html__( 'Interactive Map', 'bmg-interactive-map' );
	}

	public function get_icon(): string {
		return 'eicon-map-pin';
	}

	public function get_categories(): array {
		return [ 'general' ];
	}

	public function get_keywords(): array {
		return [ 'map', 'interactive', 'location', 'marker', 'bmg' ];
	}

	/**
	 * Tell Elementor which scripts this widget needs so they are enqueued even
	 * when Elementor's improved/optimised asset loading is active.
	 *
	 * Both handles are listed explicitly because Elementor's improved asset
	 * loader may use this list directly without walking WordPress's own
	 * dependency chain — so leaflet must appear here, not just as a WordPress
	 * dependency of bmg-public.
	 */
	public function get_script_depends(): array {
		return [ 'leaflet', 'bmg-public' ];
	}

	/**
	 * Tell Elementor which stylesheets this widget needs.
	 */
	public function get_style_depends(): array {
		return [ 'leaflet', 'bmg-public' ];
	}

	// -------------------------------------------------------------------------
	// Controls
	// -------------------------------------------------------------------------

	protected function register_controls(): void {

		// ── Map Settings ──────────────────────────────────────────────────────
		$this->start_controls_section( 'section_map', [
			'label' => esc_html__( 'Map Settings', 'bmg-interactive-map' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		] );

		$maps        = \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode()
			? get_posts( [
				'post_type'      => 'bmg_map',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			] )
			: [];
		$map_options = [ '' => esc_html__( '— Select a map —', 'bmg-interactive-map' ) ];
		foreach ( $maps as $map ) {
			$map_options[ $map->ID ] = $map->post_title;
		}

		$this->add_control( 'map_id', [
			'label'   => esc_html__( 'Select Map', 'bmg-interactive-map' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => $map_options,
			'default' => '',
		] );

		$this->add_control( 'width', [
			'label'       => esc_html__( 'Width', 'bmg-interactive-map' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => '800px',
			'description' => esc_html__( 'px or % — e.g. 800px or 100%. Leave blank to fill the column.', 'bmg-interactive-map' ),
		] );

		$this->add_responsive_control( 'map_height', [
			'label'       => esc_html__( 'Map Height', 'bmg-interactive-map' ),
			'type'        => \Elementor\Controls_Manager::SLIDER,
			'size_units'  => [ 'px', 'vh' ],
			'range'       => [
				'px' => [ 'min' => 100, 'max' => 1200, 'step' => 10 ],
				'vh' => [ 'min' => 10,  'max' => 100,  'step' => 1  ],
			],
			'default'     => [ 'size' => '', 'unit' => 'px' ],
			'selectors'   => [
				'{{WRAPPER}} .bmg-map-aspect-wrapper' => 'height: {{SIZE}}{{UNIT}};',
			],
			'description' => esc_html__( 'Leave blank to use the image aspect ratio.', 'bmg-interactive-map' ),
		] );

		$this->add_control( 'zoom_position', [
			'label'       => esc_html__( 'Zoom Control Position', 'bmg-interactive-map' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => '',
			'description' => esc_html__( 'Leave on "Default" to use the site-wide setting.', 'bmg-interactive-map' ),
			'options'     => [
				''            => esc_html__( 'Default (site setting)', 'bmg-interactive-map' ),
				'topleft'     => esc_html__( 'Top Left',               'bmg-interactive-map' ),
				'topright'    => esc_html__( 'Top Right',              'bmg-interactive-map' ),
				'bottomleft'  => esc_html__( 'Bottom Left',            'bmg-interactive-map' ),
				'bottomright' => esc_html__( 'Bottom Right',           'bmg-interactive-map' ),
			],
		] );

		$this->add_control( 'show_tooltips', [
			'label'        => esc_html__( 'Show Name on Hover', 'bmg-interactive-map' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Yes', 'bmg-interactive-map' ),
			'label_off'    => esc_html__( 'No',  'bmg-interactive-map' ),
			'return_value' => '1',
			'default'      => '',
			'description'  => esc_html__( 'Display a tooltip with the location name when hovering over a marker.', 'bmg-interactive-map' ),
		] );

		$this->add_control( 'list_position', [
			'label'     => esc_html__( 'Location List Position', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'none',
			'separator' => 'before',
			'options'   => [
				'none'     => esc_html__( 'None (hidden)',    'bmg-interactive-map' ),
				'right'    => esc_html__( 'Right',            'bmg-interactive-map' ),
				'left'     => esc_html__( 'Left',             'bmg-interactive-map' ),
				'float-tl' => esc_html__( 'Float: Top Left',  'bmg-interactive-map' ),
				'float-tr' => esc_html__( 'Float: Top Right', 'bmg-interactive-map' ),
				'float-bl' => esc_html__( 'Float: Bot Left',  'bmg-interactive-map' ),
				'float-br' => esc_html__( 'Float: Bot Right', 'bmg-interactive-map' ),
			],
		] );

		$this->add_control( 'list_title', [
			'label'       => esc_html__( 'List Title', 'bmg-interactive-map' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => esc_html__( 'Locations', 'bmg-interactive-map' ),
			'condition'   => [ 'list_position!' => 'none' ],
		] );

		$this->add_control( 'area_list_position', [
			'label'     => esc_html__( 'Area List Position', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'none',
			'options'   => [
				'none'     => esc_html__( 'None (hidden)',    'bmg-interactive-map' ),
				'right'    => esc_html__( 'Right',            'bmg-interactive-map' ),
				'left'     => esc_html__( 'Left',             'bmg-interactive-map' ),
				'float-tl' => esc_html__( 'Float: Top Left',  'bmg-interactive-map' ),
				'float-tr' => esc_html__( 'Float: Top Right', 'bmg-interactive-map' ),
				'float-bl' => esc_html__( 'Float: Bot Left',  'bmg-interactive-map' ),
				'float-br' => esc_html__( 'Float: Bot Right', 'bmg-interactive-map' ),
			],
		] );

		$this->add_control( 'area_list_title', [
			'label'       => esc_html__( 'Area List Title', 'bmg-interactive-map' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => esc_html__( 'Areas', 'bmg-interactive-map' ),
			'condition'   => [ 'area_list_position!' => 'none' ],
		] );

		$this->add_responsive_control( 'toolbar_hide', [
			'label'        => esc_html__( 'Hide Toolbar', 'bmg-interactive-map' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => esc_html__( 'Hidden', 'bmg-interactive-map' ),
			'label_off'    => esc_html__( 'Shown',  'bmg-interactive-map' ),
			'return_value' => 'yes',
			'default'      => '',
			'separator'    => 'before',
			'selectors'    => [
				'{{WRAPPER}} .bmg-map-toolbar' => 'display: none !important;',
			],
		] );

$this->add_responsive_control( 'start_zoom', [
			'label'       => esc_html__( 'Starting Zoom', 'bmg-interactive-map' ),
			'type'        => \Elementor\Controls_Manager::NUMBER,
			'min'         => -5,
			'max'         => 5,
			'step'        => 0.5,
			'default'     => '',
			'placeholder' => esc_html__( 'Default (fit all)', 'bmg-interactive-map' ),
			'description' => esc_html__( 'Leaflet zoom level. Leave blank to fit the whole map.', 'bmg-interactive-map' ),
			'separator'   => 'before',
		] );

		$this->add_responsive_control( 'start_x', [
			'label'       => esc_html__( 'Starting Center X %', 'bmg-interactive-map' ),
			'type'        => \Elementor\Controls_Manager::NUMBER,
			'min'         => 0,
			'max'         => 100,
			'step'        => 0.1,
			'default'     => '',
			'placeholder' => '50',
		] );

		$this->add_responsive_control( 'start_y', [
			'label'       => esc_html__( 'Starting Center Y %', 'bmg-interactive-map' ),
			'type'        => \Elementor\Controls_Manager::NUMBER,
			'min'         => 0,
			'max'         => 100,
			'step'        => 0.1,
			'default'     => '',
			'placeholder' => '50',
		] );

		$this->end_controls_section();

		// ── Marker Tooltip Style ─────────────────────────────────────────────
		// Tooltips render inside the Leaflet map pane, so {{WRAPPER}} scoping works.
		$this->start_controls_section( 'section_style_tooltip', [
			'label'     => esc_html__( 'Marker Tooltip', 'bmg-interactive-map' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => [ 'show_tooltips' => '1' ],
		] );

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'tooltip_typography',
				'selector' => '{{WRAPPER}} .leaflet-tooltip',
			]
		);

		$this->add_control( 'tooltip_color', [
			'label'     => esc_html__( 'Text Color', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .leaflet-tooltip' => 'color: {{VALUE}};',
			],
		] );

		$this->add_control( 'tooltip_bg_color', [
			'label'     => esc_html__( 'Background Color', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .leaflet-tooltip' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
			],
		] );

		$this->end_controls_section();

		// ── Map Style ─────────────────────────────────────────────────────────
		// The map container is inside the widget wrapper, so {{WRAPPER}} scoping works.
		$this->start_controls_section( 'section_style_map', [
			'label' => esc_html__( 'Map', 'bmg-interactive-map' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'map_bg_color', [
			'label'     => esc_html__( 'Background Color', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#e8e8e8',
			'selectors' => [
				'{{WRAPPER}} .bmg-map-container' => 'background-color: {{VALUE}};',
			],
		] );

		$this->end_controls_section();

		// ── Toolbar Style ─────────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_toolbar', [
			'label' => esc_html__( 'Toolbar', 'bmg-interactive-map' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'toolbar_btn_bg', [
			'label'     => esc_html__( 'Button Background', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-toolbar-btn' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'toolbar_btn_bg_hover', [
			'label'     => esc_html__( 'Button Background (Hover)', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-toolbar-btn:hover' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'toolbar_btn_bg_active', [
			'label'     => esc_html__( 'Button Background (Active)', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-toolbar-btn[aria-pressed="true"]' => 'background-color: {{VALUE}};',
			],
		] );

		$this->end_controls_section();

		// ── Location List Style ───────────────────────────────────────────────
		// The list is rendered inside the widget wrapper, so {{WRAPPER}} scoping works.
		$this->start_controls_section( 'section_style_list', [
			'label'     => esc_html__( 'Location List', 'bmg-interactive-map' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => [ 'list_position!' => 'none' ],
		] );

		$this->add_control( 'list_bg_color', [
			'label'     => esc_html__( 'Panel Background', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'selectors' => [
				'{{WRAPPER}} .bmg-location-list' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'list_heading_title', [
			'label'     => esc_html__( 'Title Bar', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'list_title_typography',
				'selector' => '{{WRAPPER}} .bmg-location-list__label',
			]
		);

		$this->add_control( 'list_title_color', [
			'label'     => esc_html__( 'Color', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-location-list__label' => 'color: {{VALUE}};',
			],
		] );

		$this->add_control( 'list_title_bg', [
			'label'     => esc_html__( 'Background', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-location-list__header' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'list_heading_search', [
			'label'     => esc_html__( 'Search Field', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'list_search_typography',
				'selector' => '{{WRAPPER}} .bmg-location-search',
			]
		);

		$this->add_control( 'list_search_color', [
			'label'     => esc_html__( 'Text Color', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-location-search' => 'color: {{VALUE}};',
			],
		] );

		$this->add_control( 'list_search_bg', [
			'label'     => esc_html__( 'Background', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-location-search' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'list_heading_items', [
			'label'     => esc_html__( 'Items', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'list_item_typography',
				'selector' => '{{WRAPPER}} .bmg-location-list__title',
			]
		);

		$this->add_control( 'list_item_color', [
			'label'     => esc_html__( 'Text Color', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-location-list__title' => 'color: {{VALUE}};',
			],
		] );

		$this->add_control( 'list_item_bg', [
			'label'     => esc_html__( 'Item Background', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-location-list__item' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'list_item_bg_hover', [
			'label'     => esc_html__( 'Item Background (Hover)', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-location-list__item:hover,
				 {{WRAPPER}} .bmg-location-list__item:focus' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'list_item_bg_active', [
			'label'     => esc_html__( 'Item Background (Active)', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-location-list__item--active' => 'background-color: {{VALUE}};',
			],
		] );

		$this->end_controls_section();

		// ── Area List Style ───────────────────────────────────────────────────
		$this->start_controls_section( 'section_style_area_list', [
			'label'     => esc_html__( 'Area List', 'bmg-interactive-map' ),
			'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
			'condition' => [ 'area_list_position!' => 'none' ],
		] );

		$this->add_control( 'area_list_bg_color', [
			'label'     => esc_html__( 'Panel Background', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'selectors' => [
				'{{WRAPPER}} .bmg-area-list' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'area_list_heading_title', [
			'label'     => esc_html__( 'Title Bar', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'area_list_title_typography',
				'selector' => '{{WRAPPER}} .bmg-area-list .bmg-location-list__label',
			]
		);

		$this->add_control( 'area_list_title_color', [
			'label'     => esc_html__( 'Color', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-area-list .bmg-location-list__label' => 'color: {{VALUE}};',
			],
		] );

		$this->add_control( 'area_list_title_bg', [
			'label'     => esc_html__( 'Background', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-area-list .bmg-location-list__header' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'area_list_heading_search', [
			'label'     => esc_html__( 'Search Field', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'area_list_search_typography',
				'selector' => '{{WRAPPER}} .bmg-area-list .bmg-location-search',
			]
		);

		$this->add_control( 'area_list_search_color', [
			'label'     => esc_html__( 'Text Color', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-area-list .bmg-location-search' => 'color: {{VALUE}};',
			],
		] );

		$this->add_control( 'area_list_search_bg', [
			'label'     => esc_html__( 'Background', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-area-list .bmg-location-search' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'area_list_heading_items', [
			'label'     => esc_html__( 'Items', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'area_list_item_typography',
				'selector' => '{{WRAPPER}} .bmg-area-list .bmg-location-list__title',
			]
		);

		$this->add_control( 'area_list_item_color', [
			'label'     => esc_html__( 'Text Color', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-area-list .bmg-location-list__title' => 'color: {{VALUE}};',
			],
		] );

		$this->add_control( 'area_list_item_bg', [
			'label'     => esc_html__( 'Item Background', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-area-list .bmg-location-list__item' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'area_list_item_bg_hover', [
			'label'     => esc_html__( 'Item Background (Hover)', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-area-list .bmg-location-list__item:hover,
				 {{WRAPPER}} .bmg-area-list .bmg-location-list__item:focus' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'area_list_item_bg_active', [
			'label'     => esc_html__( 'Item Background (Active)', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} .bmg-area-list .bmg-location-list__item--active' => 'background-color: {{VALUE}};',
			],
		] );

		$this->end_controls_section();

		// ── Popup Style ───────────────────────────────────────────────────────
		// Leaflet appends popups to <body>, outside the widget wrapper, so
		// {{WRAPPER}} cannot be used here — selectors target the popup classes directly.
		$this->start_controls_section( 'section_style_popup', [
			'label' => esc_html__( 'Popup', 'bmg-interactive-map' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		// Container
		$this->add_control( 'popup_heading_container', [
			'label' => esc_html__( 'Container', 'bmg-interactive-map' ),
			'type'  => \Elementor\Controls_Manager::HEADING,
		] );

		$this->add_control( 'popup_bg_color', [
			'label'     => esc_html__( 'Background Color', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'selectors' => [
				'.bmg-leaflet-popup .leaflet-popup-content-wrapper' => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name'     => 'popup_border',
				'selector' => '.bmg-leaflet-popup .leaflet-popup-content-wrapper',
			]
		);

		$this->add_control( 'popup_border_radius', [
			'label'      => esc_html__( 'Border Radius', 'bmg-interactive-map' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px', '%' ],
			'range'      => [
				'px' => [ 'min' => 0, 'max' => 50 ],
				'%'  => [ 'min' => 0, 'max' => 50 ],
			],
			'default'    => [ 'unit' => 'px', 'size' => 8 ],
			'selectors'  => [
				'.bmg-leaflet-popup .leaflet-popup-content-wrapper' => 'border-radius: {{SIZE}}{{UNIT}} !important;',
			],
		] );

		// Title
		$this->add_control( 'popup_heading_title', [
			'label'     => esc_html__( 'Title', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'popup_title_typography',
				'selector' => '.bmg-leaflet-popup .bmg-popup-title',
			]
		);

		$this->add_control( 'popup_title_color', [
			'label'     => esc_html__( 'Color', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'.bmg-leaflet-popup .bmg-popup-title' => 'color: {{VALUE}};',
			],
		] );

		// Body
		$this->add_control( 'popup_heading_body', [
			'label'     => esc_html__( 'Body', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'popup_body_typography',
				'selector' => '.bmg-leaflet-popup .bmg-popup-body',
			]
		);

		$this->add_control( 'popup_body_color', [
			'label'     => esc_html__( 'Color', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'.bmg-leaflet-popup .bmg-popup-body' => 'color: {{VALUE}};',
			],
		] );

		// Close Button
		$this->add_control( 'popup_heading_close', [
			'label'     => esc_html__( 'Close Button', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::HEADING,
			'separator' => 'before',
		] );

		$this->add_control( 'popup_close_icon', [
			'label'   => esc_html__( 'Icon', 'bmg-interactive-map' ),
			'type'    => \Elementor\Controls_Manager::ICONS,
			'default' => [
				'value'   => 'fas fa-times',
				'library' => 'fa-solid',
			],
		] );

		$this->add_control( 'popup_close_bg', [
			'label'     => esc_html__( 'Background Color', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'.bmg-leaflet-popup .leaflet-popup-close-button' => 'background: {{VALUE}} !important;',
			],
		] );

		$this->add_control( 'popup_close_color', [
			'label'     => esc_html__( 'Icon Color', 'bmg-interactive-map' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => [
				'.bmg-leaflet-popup .leaflet-popup-close-button'     => 'color: {{VALUE}} !important;',
				'.bmg-leaflet-popup .leaflet-popup-close-button svg, .bmg-leaflet-popup .leaflet-popup-close-button svg *' => 'fill: {{VALUE}} !important;',
			],
		] );

		$this->add_control( 'popup_close_size', [
			'label'      => esc_html__( 'Size', 'bmg-interactive-map' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => [ 'px' ],
			'range'      => [ 'px' => [ 'min' => 16, 'max' => 64 ] ],
			'selectors'  => [
				'.bmg-leaflet-popup .leaflet-popup-close-button'     => 'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;',
				'.bmg-leaflet-popup .leaflet-popup-close-button i'   => 'font-size: calc({{SIZE}}{{UNIT}} * 0.55) !important; line-height: 1 !important;',
				'.bmg-leaflet-popup .leaflet-popup-close-button svg' => 'width: calc({{SIZE}}{{UNIT}} * 0.55) !important; height: calc({{SIZE}}{{UNIT}} * 0.55) !important;',
			],
		] );

		$this->add_control( 'popup_close_shape', [
			'label'                => esc_html__( 'Shape', 'bmg-interactive-map' ),
			'type'                 => \Elementor\Controls_Manager::SELECT,
			'default'              => 'circle',
			'options'              => [
				'circle'  => esc_html__( 'Circle',         'bmg-interactive-map' ),
				'rounded' => esc_html__( 'Rounded square', 'bmg-interactive-map' ),
				'square'  => esc_html__( 'Square',         'bmg-interactive-map' ),
			],
			'selectors_dictionary' => [
				'circle'  => '50%',
				'rounded' => '4px',
				'square'  => '0',
			],
			'selectors'            => [
				'.bmg-leaflet-popup .leaflet-popup-close-button' => 'border-radius: {{VALUE}} !important;',
			],
		] );

		$this->end_controls_section();
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$map_id   = absint( $settings['map_id'] ?? 0 );

		if ( ! $map_id ) {
			echo '<p style="border:1px dashed #ccc;padding:12px;margin:0;">'
				. esc_html__( 'Interactive Map: select a map in the widget settings panel.', 'bmg-interactive-map' )
				. '</p>';
			return;
		}

		// Render the chosen close-button icon HTML and pass it to the shortcode
		// via a static property so it never travels through shortcode attributes.
		$close_icon = $settings['popup_close_icon'] ?? [];
		if ( ! empty( $close_icon['value'] ) ) {
			ob_start();
			\Elementor\Icons_Manager::render_icon( $close_icon, [ 'aria-hidden' => 'true' ] );
			BMG_Shortcode::set_close_icon_html( (string) ob_get_clean() );
		}

		// Build per-breakpoint starting view JSON.
		$make_bp = function ( $suffix ) use ( $settings ) {
			$sfx  = $suffix ? '_' . $suffix : '';
			return [
				'zoom' => (string) ( $settings[ 'start_zoom' . $sfx ] ?? '' ),
				'x'    => (string) ( $settings[ 'start_x'    . $sfx ] ?? '' ),
				'y'    => (string) ( $settings[ 'start_y'    . $sfx ] ?? '' ),
			];
		};
		$responsive_start = wp_json_encode( [
			'desktop' => $make_bp( '' ),
			'tablet'  => $make_bp( 'tablet' ),
			'mobile'  => $make_bp( 'mobile' ),
		] );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo BMG_Shortcode::render( [
			'id'               => $map_id,
			'width'            => (string) ( $settings['width'] ?? '' ),
			'height'           => '',
			'list_position'    => $settings['list_position'] ?? 'none',
			'zoom_position'    => $settings['zoom_position'] ?? '',
			'show_tooltips'    => ( $settings['show_tooltips'] ?? '' ) === '1' ? '1' : '0',
			'list_title'         => $settings['list_title'] ?? '',
			'area_list_position' => $settings['area_list_position'] ?? 'none',
			'area_list_title'    => $settings['area_list_title']    ?? '',
			'responsive_start'   => $responsive_start,
		] );
	}
}
